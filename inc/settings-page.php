<?php
/**
 * Settings Page
 *
 * Registers all settings with REST API exposure and renders a React mount
 * point. The React app (built from src/js/settings/) handles the UI via
 * @wordpress/components and the /wp/v2/settings endpoint.
 *
 * The HK_Funeral_Settings class is kept for backward compatibility — other
 * modules call HK_Funeral_Settings::get_instance()->is_cpt_enabled().
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

// Settings class stays in global namespace for backwards compatibility.

defined( 'WPINC' ) || exit;

class HK_Funeral_Settings {
	private static ?self $instance = null;

	private array $enabled_cpts = [
		'staff'     => 'hk_fs_staff',
		'caskets'   => 'hk_fs_casket',
		'urns'      => 'hk_fs_urn',
		'packages'  => 'hk_fs_package',
		'monuments' => 'hk_fs_monument',
		'keepsakes' => 'hk_fs_keepsake',
	];

	private array $product_types = [
		'packages'  => 'package',
		'caskets'   => 'casket',
		'urns'      => 'urn',
		'monuments' => 'monument',
		'keepsakes' => 'keepsake',
	];

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );

		$saved_cpts = get_option( 'hk_fs_enabled_cpts', [] );
		foreach ( $this->enabled_cpts as $key => $slug ) {
			if ( ! isset( $saved_cpts[ $key ] ) ) {
				$saved_cpts[ $key ] = true;
			}
		}
		$this->enabled_cpts = $saved_cpts;
	}

	public function add_menu_page(): void {
		$hook = add_options_page(
			__( 'HumanKind Funeral Suite', 'hk-funeral-suite' ),
			__( 'HK Funeral Suite', 'hk-funeral-suite' ),
			'manage_options',
			'hk-funeral-suite-settings',
			[ $this, 'render_settings_page' ]
		);

		if ( $hook ) {
			add_action( "admin_enqueue_scripts", function ( $admin_hook ) use ( $hook ) {
				if ( $admin_hook !== $hook ) {
					return;
				}
				$this->enqueue_settings_assets();
			} );
		}
	}

	/**
	 * Register all settings with REST API exposure.
	 */
	public function register_settings(): void {
		// CPT enabled/disabled toggles (object).
		$cpt_properties = [];
		$cpt_keys = [ 'staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes' ];
		foreach ( $cpt_keys as $key ) {
			$cpt_properties[ $key ] = [ 'type' => 'boolean' ];
		}

		register_setting( 'hk_fs_settings', 'hk_fs_enabled_cpts', [
			'type'              => 'object',
			'sanitize_callback' => [ $this, 'sanitize_cpt_settings' ],
			'default'           => [
				'staff'     => true,
				'caskets'   => true,
				'urns'      => true,
				'packages'  => true,
				'monuments' => false,
				'keepsakes' => false,
			],
			'show_in_rest' => [
				'schema' => [
					'type'       => 'object',
					'properties' => $cpt_properties,
				],
			],
		] );

		// Public visibility (per CPT).
		foreach ( $cpt_keys as $cpt ) {
			register_setting( 'hk_fs_settings', "hk_fs_enable_public_{$cpt}", [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			] );
		}

		// Google Sheets price sync (per product CPT).
		foreach ( $this->product_types as $api_key ) {
			register_setting( 'hk_fs_settings', "hk_fs_{$api_key}_price_google_sheets", [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			] );
		}

		// Theme/plugin compatibility.
		$compat_settings = [
			'hk_fs_generatepress_compatibility',
			'hk_fs_wpbf_compatibility',
			'hk_fs_happyfiles_compatibility',
			'hk_fs_seopress_metabox_compatibility',
		];
		foreach ( $compat_settings as $setting ) {
			register_setting( 'hk_fs_settings', $setting, [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			] );
		}

		// Rewrite rule flushing when CPTs change.
		add_action( 'update_option_hk_fs_enabled_cpts', [ $this, 'maybe_flush_rules' ], 10, 2 );
	}

	/**
	 * Enqueue React settings app and dependencies.
	 */
	private function enqueue_settings_assets(): void {
		$asset_file = HK_FS_PLUGIN_DIR . 'build/settings/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'hk-fs-settings',
			HK_FS_PLUGIN_URL . 'build/settings/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Pass plugin data and active theme/plugin info to the React app.
		wp_localize_script( 'hk-fs-settings', 'hkFsSettings', [
			'version' => HK_FS_VERSION,
			'iconUrl' => HK_FS_PLUGIN_URL . 'assets/images/icon-256x256.png',
			'activePlugins' => [
				'generatepress' => \HKFuneralSuite\Hooks\is_theme_active( 'generatepress' ),
				'wpbf'          => \HKFuneralSuite\Hooks\is_theme_active( 'page-builder-framework' ),
				'happyfiles'    => class_exists( 'HappyFiles\\Pro' ),
				'seopress'      => function_exists( 'seopress_init' ) || class_exists( '\\SEOPRESS\\Core\\Kernel' ),
			],
		] );

		// WordPress components styles.
		wp_enqueue_style( 'wp-components' );
	}

	/**
	 * Render settings page — minimal mount point for React app.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hk-funeral-suite' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'HumanKind Funeral Suite', 'hk-funeral-suite' ); ?></h1>
			<div id="hk-fs-settings"></div>
		</div>
		<?php
	}

	public function sanitize_cpt_settings( $input ): array {
		$valid     = [ 'staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes' ];
		$sanitised = array_fill_keys( $valid, false );

		if ( is_array( $input ) ) {
			foreach ( $input as $key => $value ) {
				if ( in_array( $key, $valid, true ) ) {
					$sanitised[ $key ] = (bool) $value;
				}
			}
		}
		return $sanitised;
	}

	public function is_cpt_enabled( string $cpt ): bool {
		return isset( $this->enabled_cpts[ $cpt ] ) && $this->enabled_cpts[ $cpt ];
	}

	public function maybe_flush_rules( $old_value, $value ): void {
		if ( $old_value !== $value ) {
			update_option( 'hk_fs_flush_rewrite_rules', 'yes' );
		}
	}

	public static function init(): void {
		self::get_instance();
	}
}
