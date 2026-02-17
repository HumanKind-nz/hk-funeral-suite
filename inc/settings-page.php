<?php
/**
 * Settings Page
 *
 * Retained as a class for Phase 1 (will be converted to React in Phase 4).
 * Updated to use manage_options instead of custom capabilities.
 * Text domain corrected to hk-funeral-suite.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

// Settings class stays in global namespace for backwards compatibility
// with other components that call HK_Funeral_Settings::get_instance().

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

		$saved_cpts = get_option( 'hk_fs_enabled_cpts', [] );
		foreach ( $this->enabled_cpts as $key => $slug ) {
			if ( ! isset( $saved_cpts[ $key ] ) ) {
				$saved_cpts[ $key ] = true;
			}
		}
		$this->enabled_cpts = $saved_cpts;
	}

	public function add_menu_page(): void {
		add_options_page(
			__( 'HumanKind Funeral Suite', 'hk-funeral-suite' ),
			__( 'HK Funeral Suite', 'hk-funeral-suite' ),
			'manage_options',
			'hk-funeral-suite-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings(): void {
		// Sections.
		add_settings_section( 'hk_fs_features_section', '', '__return_false', 'hk-funeral-suite-settings' );
		add_settings_section( 'hk_fs_visibility_section', __( 'Public Visibility Settings', 'hk-funeral-suite' ), [ $this, 'render_visibility_section' ], 'hk-funeral-suite-settings' );
		add_settings_section( 'hk_fs_integrations_section', __( 'Google Sheets Data Sync', 'hk-funeral-suite' ), [ $this, 'render_integrations_section' ], 'hk-funeral-suite-settings' );

		// Register CPT enabled/disabled settings.
		register_setting( 'hk_fs_settings', 'hk_fs_enabled_cpts', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_cpt_settings' ],
			'default'           => [
				'staff'     => true,
				'caskets'   => true,
				'urns'      => true,
				'packages'  => true,
				'monuments' => false,
				'keepsakes' => false,
			],
		] );

		// Visibility settings.
		$all_cpts = [ 'staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes' ];
		foreach ( $all_cpts as $cpt ) {
			register_setting( 'hk_fs_settings', "hk_fs_enable_public_{$cpt}", [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			] );
		}

		// Google Sheets settings.
		foreach ( $this->product_types as $settings_key => $api_key ) {
			register_setting( 'hk_fs_settings', "hk_fs_{$api_key}_price_google_sheets", [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			] );
		}

		// Compatibility settings.
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
			] );
		}

		// Fields.
		add_settings_field( 'hk_fs_features_field', __( 'Enable / Disable Funeral Content Types', 'hk-funeral-suite' ), [ $this, 'render_features_field' ], 'hk-funeral-suite-settings', 'hk_fs_features_section' );
		add_settings_field( 'hk_fs_visibility_field', __( 'Public Pages', 'hk-funeral-suite' ), [ $this, 'render_visibility_field' ], 'hk-funeral-suite-settings', 'hk_fs_visibility_section' );
		add_settings_field( 'hk_fs_google_sheets_field', __( 'Google Sheets Price Management', 'hk-funeral-suite' ), [ $this, 'render_google_sheets_field' ], 'hk-funeral-suite-settings', 'hk_fs_integrations_section' );

		// Compatibility section.
		add_settings_section( 'hk_fs_compatibility_section', __( 'Theme & Plugin Meta Box Cleanup', 'hk-funeral-suite' ), [ $this, 'render_compatibility_section' ], 'hk-funeral-suite-settings' );
		add_settings_field( 'hk_fs_theme_compatibility_field', __( 'Theme Meta Box Cleanup', 'hk-funeral-suite' ), [ $this, 'render_theme_compatibility_field' ], 'hk-funeral-suite-settings', 'hk_fs_compatibility_section' );
		add_settings_field( 'hk_fs_plugin_compatibility_field', __( 'Plugin Meta Box Cleanup', 'hk-funeral-suite' ), [ $this, 'render_plugin_compatibility_field' ], 'hk-funeral-suite-settings', 'hk_fs_compatibility_section' );

		// Rewrite rule flushing.
		add_action( 'update_option_hk_fs_enabled_cpts', [ $this, 'maybe_flush_rules' ], 10, 2 );
	}

	public function render_visibility_section(): void {
		echo '<p>' . esc_html__( 'Enable publicly accessible single pages and archives for each:', 'hk-funeral-suite' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Enabling public pages will make "View" buttons appear in the editor and allow visitors to access individual pages for these items.', 'hk-funeral-suite' ) . '</p>';
		echo '<p class="description"><strong>' . esc_html__( 'Note:', 'hk-funeral-suite' ) . '</strong> ' . sprintf(
			/* translators: %s: Permalinks URL */
			esc_html__( 'After changing these settings, please visit the %s to refresh URL structures.', 'hk-funeral-suite' ),
			'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Permalinks page', 'hk-funeral-suite' ) . '</a>'
		) . '</p>';
	}

	public function render_integrations_section(): void {
		echo '<p>' . esc_html__( 'Enable Google Sheets data integration with the funeral site:', 'hk-funeral-suite' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Currently used when updating your product pricing via Google Sheets.', 'hk-funeral-suite' ) . '</p>';
	}

	public function render_features_field(): void {
		$all_cpts = [ 'staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes' ];
		?>
		<fieldset>
			<?php foreach ( $all_cpts as $cpt ) : ?>
				<label>
					<input type="checkbox" name="hk_fs_enabled_cpts[<?php echo esc_attr( $cpt ); ?>]" value="1"
						<?php checked( $this->is_cpt_enabled( $cpt ) ); ?>>
					<?php echo esc_html( ucfirst( $cpt ) ); ?>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Enable or disable different features in your funeral website.', 'hk-funeral-suite' ); ?></p>
		<?php
	}

	public function render_visibility_field(): void {
		$all_cpts = [ 'staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes' ];
		?>
		<fieldset>
			<?php foreach ( $all_cpts as $cpt ) :
				$option_name = 'hk_fs_enable_public_' . $cpt;
				$is_public   = get_option( $option_name, false );
				$is_enabled  = $this->is_cpt_enabled( $cpt );
				$disabled    = ! $is_enabled ? 'disabled="disabled"' : '';
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>" value="1"
						<?php checked( $is_public, true ); ?> <?php echo $disabled; ?>>
					<?php echo esc_html( ucfirst( $cpt ) ); ?>
				</label>
				<?php if ( ! $is_enabled ) : ?>
					<span class="description" style="color:#999; font-style:italic;">
						(<?php echo esc_html( sprintf( __( 'Disabled — enable %s first', 'hk-funeral-suite' ), $cpt ) ); ?>)
					</span>
				<?php endif; ?>
				<br>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	public function render_google_sheets_field(): void {
		?>
		<fieldset>
			<?php foreach ( $this->product_types as $settings_key => $api_key ) :
				$option_name = "hk_fs_{$api_key}_price_google_sheets";
				$is_managed  = get_option( $option_name, false );
				$is_enabled  = $this->is_cpt_enabled( $settings_key );
				$disabled    = ! $is_enabled ? 'disabled="disabled"' : '';
				?>
				<div style="margin-bottom: 10px;">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>" value="1"
							<?php checked( $is_managed, true ); ?> <?php echo $disabled; ?>>
						<?php echo esc_html( ucfirst( $settings_key ) ); ?> <?php esc_html_e( 'pricing is currently managed via Google Sheets', 'hk-funeral-suite' ); ?>
					</label>
					<?php if ( ! $is_enabled ) : ?>
						<span class="description" style="color:#999; font-style:italic; margin-left:10px;">
							(<?php echo esc_html( sprintf( __( 'Disabled — enable %s first', 'hk-funeral-suite' ), $settings_key ) ); ?>)
						</span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">
			<?php esc_html_e( 'When enabled, the price fields for these product types will be managed via Google Sheets integration and will be disabled in the WordPress admin interface.', 'hk-funeral-suite' ); ?>
		</p>
		<p class="description" style="color: #d63638;">
			<strong><?php esc_html_e( 'Important:', 'hk-funeral-suite' ); ?></strong>
			<?php esc_html_e( 'After changing these settings, please reload any open edit screens to apply your changes.', 'hk-funeral-suite' ); ?>
		</p>
		<?php
	}

	public function render_compatibility_section(): void {
		echo '<p>' . esc_html__( 'Simplify your post editing experience by removing unnecessary meta boxes from popular themes and plugins when editing funeral content types:', 'hk-funeral-suite' ) . '</p>';
	}

	public function render_theme_compatibility_field(): void {
		$generatepress_active = \HKFuneralSuite\Hooks\is_theme_active( 'generatepress' );
		$wpbf_active          = \HKFuneralSuite\Hooks\is_theme_active( 'page-builder-framework' );
		?>
		<fieldset>
			<label<?php echo ! $generatepress_active ? ' class="disabled-option"' : ''; ?>>
				<input type="checkbox" name="hk_fs_generatepress_compatibility" value="1"
					<?php checked( get_option( 'hk_fs_generatepress_compatibility', false ) ); ?>
					<?php disabled( ! $generatepress_active ); ?>>
				<a href="https://generatepress.com/" target="_blank">GeneratePress</a>
				<span class="description"> — <?php esc_html_e( 'Remove layout options and sections meta boxes', 'hk-funeral-suite' ); ?></span>
				<?php if ( ! $generatepress_active ) : ?>
					<em class="inactive-notice">(<?php esc_html_e( 'Theme not active', 'hk-funeral-suite' ); ?>)</em>
				<?php endif; ?>
			</label><br>
			<label<?php echo ! $wpbf_active ? ' class="disabled-option"' : ''; ?>>
				<input type="checkbox" name="hk_fs_wpbf_compatibility" value="1"
					<?php checked( get_option( 'hk_fs_wpbf_compatibility', false ) ); ?>
					<?php disabled( ! $wpbf_active ); ?>>
				<a href="https://wp-pagebuilderframework.com/" target="_blank">Page Builder Framework</a>
				<span class="description"> — <?php esc_html_e( 'Remove theme settings meta boxes', 'hk-funeral-suite' ); ?></span>
				<?php if ( ! $wpbf_active ) : ?>
					<em class="inactive-notice">(<?php esc_html_e( 'Theme not active', 'hk-funeral-suite' ); ?>)</em>
				<?php endif; ?>
			</label>
		</fieldset>
		<style>.disabled-option{opacity:.6;cursor:default}.inactive-notice{color:#d63638;font-style:italic;margin-left:5px}</style>
		<?php
	}

	public function render_plugin_compatibility_field(): void {
		$happyfiles_active = class_exists( 'HappyFiles\\Pro' );
		$seopress_active   = function_exists( 'seopress_init' ) || class_exists( '\\SEOPRESS\\Core\\Kernel' );
		?>
		<fieldset>
			<label<?php echo ! $happyfiles_active ? ' class="disabled-option"' : ''; ?>>
				<input type="checkbox" name="hk_fs_happyfiles_compatibility" value="1"
					<?php checked( get_option( 'hk_fs_happyfiles_compatibility', false ) ); ?>
					<?php disabled( ! $happyfiles_active ); ?>>
				<a href="https://happyfiles.io/" target="_blank">HappyFiles Pro</a>
				<span class="description"> — <?php esc_html_e( 'Remove duplicate featured image column', 'hk-funeral-suite' ); ?></span>
				<?php if ( ! $happyfiles_active ) : ?>
					<em class="inactive-notice">(<?php esc_html_e( 'Plugin not active', 'hk-funeral-suite' ); ?>)</em>
				<?php endif; ?>
			</label><br>
			<label<?php echo ! $seopress_active ? ' class="disabled-option"' : ''; ?>>
				<input type="checkbox" name="hk_fs_seopress_metabox_compatibility" value="1"
					<?php checked( get_option( 'hk_fs_seopress_metabox_compatibility', false ) ); ?>
					<?php disabled( ! $seopress_active ); ?>>
				<a href="https://www.seopress.org/" target="_blank">SEOPress</a>
				<span class="description"> — <?php esc_html_e( 'Remove SEO and content analysis metaboxes', 'hk-funeral-suite' ); ?></span>
				<?php if ( ! $seopress_active ) : ?>
					<em class="inactive-notice">(<?php esc_html_e( 'Plugin not active', 'hk-funeral-suite' ); ?>)</em>
				<?php endif; ?>
			</label>
		</fieldset>
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

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hk-funeral-suite' ) );
		}
		?>
		<div class="wrap">
			<div class="hk-banner">
				<img src="<?php echo esc_url( HK_FS_PLUGIN_URL . 'assets/images/hk-funeral-suite-banner.png' ); ?>" alt="HumanKind Funeral Suite">
			</div>
			<h1><?php esc_html_e( 'Welcome to the HumanKind Funeral Suite.', 'hk-funeral-suite' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Manage your funeral website products and features and their settings below.', 'hk-funeral-suite' ); ?></p>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'hk_fs_settings' );
				$this->do_custom_settings_section( 'hk_fs_features_section', 'hk-funeral-suite-settings' );
				?>
				<div class="hk-developer-options">
					<h2 class="hk-developer-heading"><?php esc_html_e( 'Internal Use Only / Developer Options', 'hk-funeral-suite' ); ?></h2>
					<?php
					$this->do_custom_settings_section( 'hk_fs_visibility_section', 'hk-funeral-suite-settings' );
					$this->do_custom_settings_section( 'hk_fs_integrations_section', 'hk-funeral-suite-settings' );
					$this->do_custom_settings_section( 'hk_fs_compatibility_section', 'hk-funeral-suite-settings' );
					?>
				</div>
				<?php submit_button( __( 'Save Settings', 'hk-funeral-suite' ) ); ?>
			</form>

			<div class="hk-banner">
				<img src="<?php echo esc_url( HK_FS_PLUGIN_URL . 'assets/images/icon-256x256.png' ); ?>" alt="HumanKind Funeral Suite">
			</div>
			<h2><?php esc_html_e( 'Other HumanKind Funeral Website Plugins', 'hk-funeral-suite' ); ?></h2>
			<p class="hk-support-link">
				<strong><?php esc_html_e( 'Need support, advice or want to contribute?', 'hk-funeral-suite' ); ?></strong>
				<?php
				printf(
					/* translators: %s: GitHub URL */
					esc_html__( 'Visit our %s for support, feedback, or contributions.', 'hk-funeral-suite' ),
					'<a href="https://github.com/HumanKind-nz/hk-funeral-suite/" target="_blank">' . esc_html__( 'GitHub Page', 'hk-funeral-suite' ) . '</a>'
				);
				?>
			</p>
		</div>

		<style>
			.hk-developer-options{border:1px solid #ccd0d4;border-radius:4px;padding:0 20px 10px;margin:20px 0;background-color:#f8f9fa}
			.hk-developer-heading{background-color:#f1f1f1;margin:0 -20px 20px;padding:15px 20px;border-bottom:1px solid #ccd0d4;color:#23282d;font-size:16px;font-weight:500;text-transform:uppercase}
			.hk-support-link{margin-top:20px;padding:15px;background-color:#f8f9fa;border:1px solid #ccd0d4;border-radius:4px}
			.hk-banner{margin-bottom:20px}
			.hk-banner img{max-width:100%;height:auto;display:block}
			.hk-banner:first-of-type img{margin:0 auto}
			.hk-banner:last-of-type{margin-top:30px;text-align:left}
			.hk-banner:last-of-type img{max-width:150px;margin-left:0}
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('input[name^="hk_fs_enabled_cpts"]').forEach(function(cb) {
				cb.addEventListener('change', function() {
					var cptName = this.name.match(/\[(.*?)\]/)[1];
					var vis = document.querySelector('input[name="hk_fs_enable_public_' + cptName + '"]');
					if (vis) {
						vis.disabled = !this.checked;
						if (!this.checked) vis.checked = false;
					}
				});
			});
		});
		</script>
		<?php
	}

	private function do_custom_settings_section( string $section_id, string $page ): void {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[ $page ][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections[ $page ][ $section_id ];

		if ( $section['title'] ) {
			echo '<h2>' . esc_html( $section['title'] ) . "</h2>\n";
		}
		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		if ( ! isset( $wp_settings_fields[ $page ][ $section_id ] ) ) {
			return;
		}

		echo '<table class="form-table" role="presentation">';
		do_settings_fields( $page, $section_id );
		echo '</table>';
	}

	public static function init(): void {
		self::get_instance();
	}
}
