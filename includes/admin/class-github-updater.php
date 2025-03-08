<?php
/**
 * GitHub Update Checker
 *
 * @package    HK_Funeral_Suite
 * @subpackage Updates
 * @version    1.2.1
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

    // Plugin icons
    private const ICON_SMALL = "https://weave-hk-github.b-cdn.net/humankind/icon-128x128.png";
    private const ICON_LARGE = "https://weave-hk-github.b-cdn.net/humankind/icon-256x256.png";
    
    // Cache keys and durations
    private const CACHE_KEY = 'hk_funeral_github_response';
    private const CACHE_DURATION = 4; // Hours
    private const ERROR_CACHE_DURATION = 1; // Hour for error responses

    /**
     * Constructor
     * 
     * @param string $file Main plugin file path
     */
    public function __construct($file) {
        $this->file = $file;
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
        static $instance = null;
        
        // Ensure we only create one instance
        if ($instance === null) {
            $instance = new self($file);
        }
        
        return $instance;
    }
    
    /**
     * Get plugin data only when needed
     *
     * @return array Plugin data
     */
    private function get_plugin_data() {
        if (empty($this->plugin) && is_admin() && function_exists("get_plugin_data")) {
            $this->plugin = get_plugin_data($this->file);
        }
        
        return $this->plugin;
    }

    /**
     * Normalize a version string by removing 'v' prefix
     * 
     * @param string $version Version string
     * @return string Normalized version
     */
    private function normalize_version($version) {
        return ltrim($version, "v");
    }

    /**
     * Get repository information from GitHub with caching
     * 
     * @return object|false Repository info or false on failure
     */
    private function get_repository_info() {
        if (!is_null($this->github_response)) {
            return $this->github_response;
        }
        
        // Check for a cached response
        $cached = get_transient(self::CACHE_KEY);
        if (false !== $cached) {
            // Check if this is an error response (we store errors as an array with status key)
            if (is_array($cached) && isset($cached['status']) && $cached['status'] === 'error') {
                return false; // Return false but don't make a new request
            }
            
            $this->github_response = $cached;
            return $this->github_response;
        }

        $request_uri = sprintf(
            "https://api.github.com/repos/%s/%s/releases/latest",
            $this->github_username,
            $this->github_repo
        );

        $args = [
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
            ]
        ];

        $response = wp_remote_get($request_uri, $args);

        if (is_wp_error($response)) {
            error_log("GitHub API request failed: " . $response->get_error_message());
            // Cache error response to prevent constant retries
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log("GitHub API request failed with response code: " . $response_code);
            // Cache error response to prevent constant retries
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (!isset($body->tag_name, $body->assets) || empty($body->assets)) {
            error_log("GitHub API response missing required fields or assets.");
            // Cache error response to prevent constant retries
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        // Fetch the actual zip file URL
        $body->zipball_url = $body->assets[0]->browser_download_url ?? '';

        if (empty($body->zipball_url)) {
            error_log("No valid download URL found for the latest release.");
            // Cache error response to prevent constant retries
            set_transient(self::CACHE_KEY, ['status' => 'error'], self::ERROR_CACHE_DURATION * HOUR_IN_SECONDS);
            return false;
        }

        // Cache the successful response
        set_transient(self::CACHE_KEY, $body, self::CACHE_DURATION * HOUR_IN_SECONDS);
        $this->github_response = $body;
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

        // Load plugin data only when needed
        $plugin_data = $this->get_plugin_data();
        
        $repository_info = $this->get_repository_info();
        if (!$repository_info) {
            return $transient;
        }

        // Get current version from plugin header data (not transient)
        $current_version = $plugin_data['Version'];
        
        // Normalize versions by removing 'v' prefix
        $current_version_normalized = $this->normalize_version($current_version);
        $latest_version = $this->normalize_version($repository_info->tag_name);

        // Debug log to help troubleshoot
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("HK Funeral GitHub Updater - Current version: {$current_version_normalized}, Latest version: {$latest_version}");
        }

        // Only add to update response if GitHub version is strictly greater than current version
        if (version_compare($latest_version, $current_version_normalized, ">")) {
            $plugin = [
                "url" => $plugin_data["PluginURI"] ?? "",
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
        } else {
            // Make sure we're not in the response array if the version is the same or older
            if (isset($transient->response[$this->basename])) {
                unset($transient->response[$this->basename]);
            }
            
            // Add to the no_update list to show as "up to date"
            if (!isset($transient->no_update[$this->basename])) {
                $plugin = [
                    "slug" => dirname($this->basename),
                    "plugin" => $this->basename,
                    "new_version" => $latest_version,
                    "url" => $plugin_data["PluginURI"] ?? "",
                    "package" => "",
                    "icons" => [
                        "1x" => self::ICON_SMALL,
                        "2x" => self::ICON_LARGE,
                    ],
                ];
                $transient->no_update[$this->basename] = (object) $plugin;
            }
        }

        return $transient;
    }

    /**
     * Provide plugin information in the update UI
     * 
     * @param object|false $res Result object or false
     * @param string $action Action name
     * @param object $args Request arguments
     * @return object Plugin info object
     */
    public function plugin_info($res, $action, $args) {
        if ($action !== "plugin_information" || $args->slug !== dirname($this->basename)) {
            return $res;
        }

        // Load plugin data only when needed
        $plugin_data = $this->get_plugin_data();
        
        $repository_info = $this->get_repository_info();
        if (!$repository_info) {
            return $res;
        }

        $info = new \stdClass();

        $info->name = $plugin_data["Name"] ?? "";
        $info->slug = dirname($this->basename);
        $info->version = $this->normalize_version($repository_info->tag_name);
        $info->author = $plugin_data["Author"] ?? "";
        $info->author_profile = $plugin_data["AuthorURI"] ?? "";
        $info->tested = get_bloginfo("version");
        $info->last_updated = $repository_info->published_at ?? "";
        $info->sections = [
            "description" => $plugin_data["Description"] ?? "",
            "changelog" => $repository_info->body ?? "",
        ];
        $info->icons = [
            "1x" => self::ICON_SMALL,
            "2x" => self::ICON_LARGE,
        ];
        $info->download_link = $repository_info->zipball_url;

        return $info;
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
        
        // Clear the cache to force a fresh check
        delete_transient(self::CACHE_KEY);

        return $result;
    }
}
