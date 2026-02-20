<?php
/**
 * GitHub release auto-updater.
 *
 * Hooks into the WordPress update system so updates from GitHub releases
 * appear in Dashboard -> Updates with one-click install. Based on the
 * pattern from weave-cache-purge-helper, improved with better error
 * handling and plugin details modal support.
 *
 * Uses a class because the updater maintains state (transients, version
 * info, plugin data). This is the one exception to the functions-only rule.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\GitHubUpdater;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap — initialise updater on admin pages.
 */
function bootstrap(): void {
	add_action( 'plugins_loaded', function () {
		if ( is_admin() ) {
			HK_Funeral_GitHub_Updater::init( HK_FS_PLUGIN_FILE );
		}
	} );
}

/**
 * GitHub release updater for self-hosted plugins.
 */
class HK_Funeral_GitHub_Updater {

	/** @var string Main plugin file path. */
	private string $file;

	/** @var array|null Plugin header data — loaded lazily. */
	private ?array $plugin = null;

	/** @var string Plugin basename (e.g. "hk-funeral-suite/hk-funeral-suite.php"). */
	private string $basename;

	/** @var object|false|null Cached GitHub API response. */
	private $github_response = null;

	// -- Configuration --------------------------------------------------------

	/** GitHub organisation or username. */
	private const GITHUB_USERNAME = 'HumanKind-nz';

	/** GitHub repository name. */
	private const GITHUB_REPO = 'hk-funeral-suite';

	/** Plugin icon — small (128x128). Served from BunnyCDN. */
	public const ICON_SMALL = 'https://weave-hk-github.b-cdn.net/humankind/icon-128x128.png';

	/** Plugin icon — large (256x256). Served from BunnyCDN. */
	public const ICON_LARGE = 'https://weave-hk-github.b-cdn.net/humankind/icon-256x256.png';

	/** Transient key for caching the GitHub API response. */
	private const CACHE_KEY = 'hk_funeral_github_response';

	/** Hours to cache a successful API response. */
	private const CACHE_DURATION = 4;

	/** Hours to cache an error response (prevents constant retries). */
	private const ERROR_CACHE_DURATION = 1;

	// -- Lifecycle ------------------------------------------------------------

	/**
	 * Private constructor — use init() instead.
	 *
	 * @param string $file Main plugin file path.
	 */
	private function __construct( string $file ) {
		$this->file     = $file;
		$this->basename = plugin_basename( $this->file );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
	}

	/**
	 * Create (or return) the singleton instance.
	 *
	 * @param string $file Main plugin file path.
	 * @return self
	 */
	public static function init( string $file ): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self( $file );
		}

		return $instance;
	}

	// -- Helpers --------------------------------------------------------------

	/**
	 * Get plugin header data, loading it lazily.
	 *
	 * @return array Plugin data array.
	 */
	private function get_plugin_data(): array {
		if ( null === $this->plugin && function_exists( 'get_plugin_data' ) ) {
			$this->plugin = get_plugin_data( $this->file );
		}

		return $this->plugin ?? [];
	}

	/**
	 * Normalise a version string by stripping a leading "v".
	 *
	 * @param string $version Raw version string (e.g. "v1.2.3").
	 * @return string Normalised version (e.g. "1.2.3").
	 */
	private function normalize_version( string $version ): string {
		return ltrim( $version, 'v' );
	}

	/**
	 * Fetch the latest release info from GitHub with transient caching.
	 *
	 * @return object|false Release object or false on failure.
	 */
	private function get_repository_info() {
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		// Check the transient cache first.
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			if ( is_array( $cached ) && isset( $cached['status'] ) && 'error' === $cached['status'] ) {
				return false;
			}

			$this->github_response = $cached;
			return $this->github_response;
		}

		$request_uri = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USERNAME,
			self::GITHUB_REPO
		);

		$response = wp_remote_get( $request_uri, [
			'headers' => [
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
			],
		] );

		if ( is_wp_error( $response ) ) {
			self::debug_log( 'GitHub API error: ' . $response->get_error_message() );
			set_transient( self::CACHE_KEY, [ 'status' => 'error' ], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			self::debug_log( 'GitHub API returned HTTP ' . $code );
			set_transient( self::CACHE_KEY, [ 'status' => 'error' ], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $body->tag_name, $body->assets ) || empty( $body->assets ) ) {
			self::debug_log( 'GitHub API response missing tag_name or assets.' );
			set_transient( self::CACHE_KEY, [ 'status' => 'error' ], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS );
			return false;
		}

		// Use the first release asset (the zip built by GitHub Actions).
		$body->zipball_url = $body->assets[0]->browser_download_url ?? '';

		if ( empty( $body->zipball_url ) ) {
			self::debug_log( 'No download URL found in release assets.' );
			set_transient( self::CACHE_KEY, [ 'status' => 'error' ], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS );
			return false;
		}

		set_transient( self::CACHE_KEY, $body, self::CACHE_DURATION * HOUR_IN_SECONDS );
		$this->github_response = $body;

		return $this->github_response;
	}

	/**
	 * Log a debug message when WP_DEBUG is enabled.
	 *
	 * @param string $message Message to log.
	 */
	private static function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[HK Funeral Suite Updater] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	// -- WordPress update hooks -----------------------------------------------

	/**
	 * Check whether a newer version is available on GitHub.
	 *
	 * @param object $transient The update_plugins transient data.
	 * @return object Modified transient.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$plugin_data = $this->get_plugin_data();
		$repo_info   = $this->get_repository_info();

		if ( ! $repo_info || empty( $plugin_data['Version'] ) ) {
			return $transient;
		}

		$current = $this->normalize_version( $plugin_data['Version'] );
		$latest  = $this->normalize_version( $repo_info->tag_name );

		$plugin_entry = [
			'slug'        => dirname( $this->basename ),
			'plugin'      => $this->basename,
			'new_version' => $latest,
			'tested'      => get_bloginfo( 'version' ),
			'package'     => $repo_info->zipball_url,
			'icons'       => [
				'1x' => self::ICON_SMALL,
				'2x' => self::ICON_LARGE,
			],
		];

		if ( version_compare( $latest, $current, '>' ) ) {
			$transient->response[ $this->basename ] = (object) $plugin_entry;
		} else {
			unset( $transient->response[ $this->basename ] );

			if ( ! isset( $transient->no_update[ $this->basename ] ) ) {
				$plugin_entry['package'] = '';
				$transient->no_update[ $this->basename ] = (object) $plugin_entry;
			}
		}

		return $transient;
	}

	/**
	 * Provide plugin details for the "View details" modal in the admin.
	 *
	 * @param object|false $res    Existing result.
	 * @param string       $action API action.
	 * @param object       $args   Request arguments.
	 * @return object|false Plugin info or pass-through.
	 */
	public function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || $args->slug !== dirname( $this->basename ) ) {
			return $res;
		}

		$plugin_data = $this->get_plugin_data();
		$repo_info   = $this->get_repository_info();

		if ( ! $repo_info ) {
			return $res;
		}

		$info                = new \stdClass();
		$info->name          = $plugin_data['Name'] ?? 'HumanKind Funeral Suite';
		$info->slug          = dirname( $this->basename );
		$info->version       = $this->normalize_version( $repo_info->tag_name );
		$info->author        = $plugin_data['AuthorName'] ?? 'HumanKind';
		$info->homepage      = $plugin_data['PluginURI'] ?? '';
		$info->tested        = get_bloginfo( 'version' );
		$info->requires      = $plugin_data['RequiresWP'] ?? '6.6';
		$info->requires_php  = $plugin_data['RequiresPHP'] ?? '8.1';
		$info->last_updated  = $repo_info->published_at ?? '';
		$info->download_link = $repo_info->zipball_url;

		$info->sections = [
			'description' => $plugin_data['Description'] ?? '',
			'changelog'   => $repo_info->body ?? '',
		];

		$info->icons = [
			'1x' => self::ICON_SMALL,
			'2x' => self::ICON_LARGE,
		];

		return $info;
	}

	/**
	 * After installing an update, move the files to the correct directory.
	 *
	 * GitHub release zips extract to a directory named {repo}-{version}.
	 * This renames it to match the existing plugin directory.
	 *
	 * @param bool|\WP_Error $response   Install response.
	 * @param array          $hook_extra Extra arguments.
	 * @param array          $result     Installation result data.
	 * @return array Modified result.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		$install_directory = plugin_dir_path( $this->file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		// Clear cache so the next check fetches fresh data.
		delete_transient( self::CACHE_KEY );

		return $result;
	}
}
