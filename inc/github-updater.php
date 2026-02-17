<?php
/**
 * GitHub Update Checker
 *
 * Stays as a class per scaffold convention — GitHub updaters are the exception
 * to the "namespaced functions only" rule.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\GitHubUpdater;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — initialise updater in admin only.
 */
function bootstrap(): void {
	add_action( 'init', function () {
		if ( is_admin() ) {
			HK_Funeral_GitHub_Updater::init( HK_FS_PLUGIN_FILE );
		}
	} );
}

/**
 * Class HK_Funeral_GitHub_Updater
 *
 * Checks GitHub releases for plugin updates and integrates with the
 * WordPress native update system.
 */
class HK_Funeral_GitHub_Updater {
	private string $file;
	private ?array $plugin = null;
	private string $basename;
	private bool $active;
	private ?object $github_response = null;

	private string $github_username = 'HumanKind-nz';
	private string $github_repo     = 'hk-funeral-suite';

	private const ICON_SMALL           = 'https://weave-hk-github.b-cdn.net/humankind/icon-128x128.png';
	private const ICON_LARGE           = 'https://weave-hk-github.b-cdn.net/humankind/icon-256x256.png';
	private const CACHE_KEY            = 'hk_funeral_github_response';
	private const CACHE_DURATION       = 4;
	private const ERROR_CACHE_DURATION = 1;

	public function __construct( string $file ) {
		$this->file     = $file;
		$this->basename = plugin_basename( $this->file );
		$this->active   = is_plugin_active( $this->basename );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
	}

	public static function init( string $file ): self {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self( $file );
		}
		return $instance;
	}

	private function get_plugin_data(): array {
		if ( empty( $this->plugin ) && is_admin() && function_exists( 'get_plugin_data' ) ) {
			$this->plugin = get_plugin_data( $this->file );
		}
		return $this->plugin ?? [];
	}

	private function normalize_version( string $version ): string {
		return ltrim( $version, 'v' );
	}

	private function get_repository_info(): ?object {
		if ( $this->github_response !== null ) {
			return $this->github_response;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			if ( is_array( $cached ) && isset( $cached['status'] ) && $cached['status'] === 'error' ) {
				return null;
			}
			$this->github_response = $cached;
			return $this->github_response;
		}

		$url  = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->github_username, $this->github_repo );
		$args = [ 'headers' => [ 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ] ];

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( self::CACHE_KEY, [ 'status' => 'error' ], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! isset( $body->tag_name, $body->assets ) || empty( $body->assets ) ) {
			set_transient( self::CACHE_KEY, [ 'status' => 'error' ], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS );
			return null;
		}

		$body->zipball_url = $body->assets[0]->browser_download_url ?? '';
		if ( empty( $body->zipball_url ) ) {
			set_transient( self::CACHE_KEY, [ 'status' => 'error' ], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE_KEY, $body, self::CACHE_DURATION * HOUR_IN_SECONDS );
		$this->github_response = $body;
		return $this->github_response;
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$plugin_data     = $this->get_plugin_data();
		$repository_info = $this->get_repository_info();
		if ( ! $repository_info ) {
			return $transient;
		}

		$current = $this->normalize_version( $plugin_data['Version'] ?? '0' );
		$latest  = $this->normalize_version( $repository_info->tag_name );

		if ( version_compare( $latest, $current, '>' ) ) {
			$transient->response[ $this->basename ] = (object) [
				'url'         => $plugin_data['PluginURI'] ?? '',
				'slug'        => dirname( $this->basename ),
				'package'     => $repository_info->zipball_url,
				'new_version' => $latest,
				'tested'      => get_bloginfo( 'version' ),
				'icons'       => [ '1x' => self::ICON_SMALL, '2x' => self::ICON_LARGE ],
			];
		} else {
			unset( $transient->response[ $this->basename ] );
			$transient->no_update[ $this->basename ] = (object) [
				'slug'        => dirname( $this->basename ),
				'plugin'      => $this->basename,
				'new_version' => $latest,
				'url'         => $plugin_data['PluginURI'] ?? '',
				'package'     => '',
				'icons'       => [ '1x' => self::ICON_SMALL, '2x' => self::ICON_LARGE ],
			];
		}

		return $transient;
	}

	public function plugin_info( $res, $action, $args ) {
		if ( $action !== 'plugin_information' || $args->slug !== dirname( $this->basename ) ) {
			return $res;
		}

		$plugin_data     = $this->get_plugin_data();
		$repository_info = $this->get_repository_info();
		if ( ! $repository_info ) {
			return $res;
		}

		$info                  = new \stdClass();
		$info->name            = $plugin_data['Name'] ?? '';
		$info->slug            = dirname( $this->basename );
		$info->version         = $this->normalize_version( $repository_info->tag_name );
		$info->author          = $plugin_data['Author'] ?? '';
		$info->author_profile  = $plugin_data['AuthorURI'] ?? '';
		$info->tested          = get_bloginfo( 'version' );
		$info->last_updated    = $repository_info->published_at ?? '';
		$info->sections        = [
			'description' => $plugin_data['Description'] ?? '',
			'changelog'   => $repository_info->body ?? '',
		];
		$info->icons           = [ '1x' => self::ICON_SMALL, '2x' => self::ICON_LARGE ];
		$info->download_link   = $repository_info->zipball_url;

		return $info;
	}

	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		$install_directory = plugin_dir_path( $this->file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		if ( $this->active ) {
			activate_plugin( $this->basename );
		}

		delete_transient( self::CACHE_KEY );
		return $result;
	}
}
