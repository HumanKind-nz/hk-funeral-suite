<?php
/**
 * GitHub Update Checker
 *
 * @package    HK_Funeral_Suite
 * @subpackage Updates
 * @version    1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

class HK_Funeral_GitHub_Updater {
	private $file;
	private $plugin;
	private $basename;
	private $active;
	private $github_response;
	private $github_username = "HumanKind-nz";
	private $github_repo = "hk-funeral-suite";

	// Plugin icons - replace with your own icon URLs
	private const ICON_SMALL = "https://weave-hk-github.b-cdn.net/humankind/icon-128x128.png";
	private const ICON_LARGE = "https://weave-hk-github.b-cdn.net/humankind/icon-256x256.png";

	/**
	 * Constructor
	 * 
	 * @param string $file Main plugin file path
	 */
	public function __construct($file) {
		$this->file = $file;
		
		if (is_admin() && function_exists("get_plugin_data")) {
			$this->plugin = get_plugin_data($this->file);
		}
		
		$this->basename = plugin_basename($this->file);
		$this->active = is_plugin_active($this->basename);

		// Hook into the update check system
		add_filter("pre_set_site_transient_update_plugins", [$this, "check_update"]);
		add_filter("plugins_api", [$this, "plugin_info"], 20, 3);
		add_filter("upgrader_post_install", [$this, "after_install"], 10, 3);
	}

	/**
	 * Initialize the updater
	 * 
	 * @param string $file Main plugin file path
	 * @return HK_Funeral_GitHub_Updater
	 */
	public static function init($file) {
		return new self($file);
	}

	/**
	 * Get repository information from GitHub
	 * 
	 * @return object|false Repository info or false on failure
	 */
	private function get_repository_info() {
		if (is_null($this->github_response)) {
			$request_uri = sprintf(
				"https://api.github.com/repos/%s/%s/releases/latest",
				$this->github_username,
				$this->github_repo
			);

			$response = wp_remote_get($request_uri);

			if (is_wp_error($response)) {
				error_log("GitHub API request failed: " . $response->get_error_message());
				return false;
			}

			if (wp_remote_retrieve_response_code($response) !== 200) {
				error_log("GitHub API request failed with response code: " . wp_remote_retrieve_response_code($response));
				return false;
			}

			$body = json_decode(wp_remote_retrieve_body($response));

			if (!isset($body->tag_name, $body->zipball_url, $body->published_at)) {
				error_log("GitHub API response missing required fields.");
				return false;
			}

			$this->github_response = $body;
		}

		return $this->github_response;
	}

	/**
	 * Check for plugin updates
	 * 
	 * @param object $transient Update transient
	 * @return object Updated transient
	 */
	public function check_update($transient) {
		if (empty($transient->checked)) {
			return $transient;
		}

		$repository_info = $this->get_repository_info();
		if (!$repository_info) {
			return $transient;
		}

		$current_version = $transient->checked[$this->basename] ?? "";
		$latest_version = ltrim($repository_info->tag_name, "v");

		if (version_compare($latest_version, $current_version, "gt")) {
			$plugin = [
				"url" => $this->plugin["PluginURI"] ?? "",
				"slug" => dirname($this->basename),
				"package" => $repository_info->zipball_url,
				"new_version" => $latest_version,
				"tested" => get_bloginfo("version"),
				"icons" => [
					"1x" => self::ICON_SMALL,
					"2x" => self::ICON_LARGE,
				],
			];

			$transient->response[$this->basename] = (object) $plugin;
		}

		return $transient;
	}

	/**
	 * Provide plugin information in the update UI
	 * 
	 * @param object|false $result Result object or false
	 * @param string $action Action name
	 * @param object $args Request arguments
	 * @return object Plugin info object
	 */
	public function plugin_info($res, $action, $args) {
		if ($action !== "plugin_information" || $args->slug !== dirname($this->basename)) {
			return $res;
		}

		$repository_info = $this->get_repository_info();
		if (!$repository_info) {
			return $res;
		}

		$plugin_data = $this->plugin;
		$info = new \stdClass();

		$info->name = $plugin_data["Name"] ?? "";
		$info->slug = dirname($this->basename);
		$info->version = ltrim($repository_info->tag_name, "v");
		$info->author = $plugin_data["Author"] ?? "";
		$info->author_profile = $plugin_data["AuthorURI"] ?? "";
		$info->tested = get_bloginfo("version");
		$info->last_updated = $repository_info->published_at ?? "";
		$info->sections = [
			"description" => $plugin_data["Description"] ?? "",
			"changelog" => $this->get_readme_content(),
		];
		$info->icons = [
			"1x" => self::ICON_SMALL,
			"2x" => self::ICON_LARGE,
		];
		$info->download_link = $repository_info->zipball_url;

		return $info;
	}

	/**
	 * Fetch README.md content from GitHub
	 * 
	 * @return string README content
	 */
	private function get_readme_content() {
		$request_uri = sprintf(
			"https://api.github.com/repos/%s/%s/contents/README.md",
			$this->github_username,
			$this->github_repo
		);

		$response = wp_remote_get($request_uri);

		if (is_wp_error($response)) {
			error_log("Failed to fetch README: " . $response->get_error_message());
			return "";
		}

		if (wp_remote_retrieve_response_code($response) !== 200) {
			error_log("Failed to fetch README: Invalid response code");
			return "";
		}

		$body = json_decode(wp_remote_retrieve_body($response));
		if ($body && isset($body->content)) {
			return base64_decode($body->content);
		}

		error_log("README content not found in response");
		return "";
	}

	/**
	 * Handle plugin installation process
	 * 
	 * @param bool|WP_Error $response Installation response
	 * @param array $hook_extra Extra arguments
	 * @param array $result Installation result data
	 * @return array Modified result data
	 */
	public function after_install($response, $hook_extra, $result) {
		global $wp_filesystem;

		$install_directory = plugin_dir_path($this->file);
		$wp_filesystem->move($result["destination"], $install_directory);
		$result["destination"] = $install_directory;

		if ($this->active) {
			activate_plugin($this->basename);
		}

		return $result;
	}
}