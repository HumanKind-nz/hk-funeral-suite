<?php
/**
 * Plugin Name: HumanKind Funeral Suite
 * Plugin URI: https://github.com/HumanKind-nz/hk-funeral-suite/
 * Description: A powerful WordPress plugin to streamline funeral home websites adding custom post types, taxonomies and fields for Staff, Caskets, Urns, and Pricing Packages, along with specialised Gutenberg blocks for easy content management. 
 * Version: 1.2.3
 * Author: HumanKind, Weave Digital Studio, Gareth Bissland
 * Author URI: https://weave.co.nz
 * License: GPL v2.0 or later
 * Text Domain: hk-funeral-cpt
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Tested up to: 6.7
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('HK_FS_VERSION', '1.2.3'); 
define('HK_FS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HK_FS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HK_FS_BASENAME', plugin_basename(__FILE__));
define('HK_FS_PLUGIN_FILE', __FILE__); 

/**
 * Load plugin textdomain.
 */
function hk_fs_load_textdomain() {
    load_plugin_textdomain('hk-funeral-cpt', false, dirname(HK_FS_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'hk_fs_load_textdomain');

/**
 * Cleanup plugin options on uninstall.
 */
function hk_fs_cleanup_plugin() {
    delete_option('hk_fs_enabled_cpts');
}
register_uninstall_hook(__FILE__, 'hk_fs_cleanup_plugin');

// Load core files
require_once HK_FS_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
require_once HK_FS_PLUGIN_DIR . 'includes/admin/class-capabilities.php';

// Load block styles integration
require_once HK_FS_PLUGIN_DIR . 'includes/blocks/block-styles.php';

// Block Editor Customizations
require_once HK_FS_PLUGIN_DIR . 'includes/admin/class-block-editor.php';

// Admin Post Modifications (only for admin)
require_once HK_FS_PLUGIN_DIR . 'includes/admin/class-post-mods.php';
HK_Post_Mods::init();

// Load shortcodes file (available on both front-end and admin)
require_once HK_FS_PLUGIN_DIR . 'includes/class-shortcodes.php';

// Load importer class for adding default blocks
if ( file_exists( HK_FS_PLUGIN_DIR . 'includes/import/class-default-blocks-importer.php' ) ) {
    require_once HK_FS_PLUGIN_DIR . 'includes/import/class-default-blocks-importer.php';
    HK_Default_Blocks_Importer::init();
}

// Load admin-specific column functionality
function hk_fs_load_admin_classes() {
    // Only load admin classes when in admin area
    if (is_admin()) {
        // Load admin columns management
        require_once HK_FS_PLUGIN_DIR . 'includes/admin/class-hk-funeral-cpt-admin-columns.php';
    }
}
add_action('plugins_loaded', 'hk_fs_load_admin_classes');

//Load any theme/plugin cpt optimsations
require_once HK_FS_PLUGIN_DIR . 'includes/admin/class-hk-funeral-cpt-compatibility.php';

// Initialize capabilities
HK_Funeral_Capabilities::init();
// Create block directories if they don't exist
hk_fs_create_block_directories();

// Initialize settings
$settings = HK_Funeral_Settings::get_instance();

// Load enabled CPTs
function hk_fs_load_enabled_cpts() {
    $settings = HK_Funeral_Settings::get_instance();
    
    if ($settings->is_cpt_enabled('staff')) {
        require_once HK_FS_PLUGIN_DIR . 'includes/cpt/staff.php';
    }
    if ($settings->is_cpt_enabled('caskets')) {
        require_once HK_FS_PLUGIN_DIR . 'includes/cpt/caskets.php';
    }
    if ($settings->is_cpt_enabled('urns')) {
        require_once HK_FS_PLUGIN_DIR . 'includes/cpt/urns.php';
    }
    if ($settings->is_cpt_enabled('packages')) {
        require_once HK_FS_PLUGIN_DIR . 'includes/cpt/packages.php';
    }
}
add_action('plugins_loaded', 'hk_fs_load_enabled_cpts', 20); // Higher priority to ensure settings are loaded first

/**
 * Register custom Gutenberg blocks
 */
function hk_fs_register_blocks() {
    // Only load blocks if the corresponding CPT is enabled
    $settings = HK_Funeral_Settings::get_instance();
    
    if ($settings->is_cpt_enabled('staff')) {
        // Include the Team Member block
        require_once HK_FS_PLUGIN_DIR . 'includes/blocks/team-member-block/init.php';
    }
    
    if ($settings->is_cpt_enabled('packages')) {
        // Include the Pricing Package block
        require_once HK_FS_PLUGIN_DIR . 'includes/blocks/pricing-package-block/init.php';
    }
    
    if ($settings->is_cpt_enabled('caskets')) {
        // Include the Casket block
        require_once HK_FS_PLUGIN_DIR . 'includes/blocks/casket-block/init.php';
    }
    
    if ($settings->is_cpt_enabled('urns')) {
        // Include the Urn block
        require_once HK_FS_PLUGIN_DIR . 'includes/blocks/urn-block/init.php';
    }
}
add_action('init', 'hk_fs_register_blocks', 15); // Run after CPTs are registered but before templates

/**
 * Create blocks directory structure if it doesn't exist during activation
 */
function hk_fs_create_block_directories() {
    $dirs = array(
        HK_FS_PLUGIN_DIR . 'includes/blocks/',
        HK_FS_PLUGIN_DIR . 'includes/blocks/team-member-block/',
        HK_FS_PLUGIN_DIR . 'includes/blocks/pricing-package-block/',
        HK_FS_PLUGIN_DIR . 'includes/blocks/casket-block/',
        HK_FS_PLUGIN_DIR . 'includes/blocks/urn-block/',
        HK_FS_PLUGIN_DIR . 'includes/blocks/assets/'
    );
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}

function hk_fs_register_price_meta() {
    register_rest_field(
        array('hk_fs_casket', 'hk_fs_urn', 'hk_fs_package'),
        'price',
        array(
            'get_callback' => function($post) {
                return get_post_meta($post['id'], '_hk_fs_' . $post['type'] . '_price', true);
            },
            'schema' => array(
                'type' => 'string',
                'description' => 'Price of the item'
            )
        )
    );
}
add_action('rest_api_init', 'hk_fs_register_price_meta');

/**
 * Initialize GitHub updater if we're in admin
 */
function hk_fs_init_github_updater() {
    if (is_admin() && file_exists(HK_FS_PLUGIN_DIR . 'includes/admin/class-github-updater.php')) {
        require_once HK_FS_PLUGIN_DIR . 'includes/admin/class-github-updater.php';
        // Pass the main plugin file path, not __FILE__ which would refer to the current file
        HK_Funeral_GitHub_Updater::init(HK_FS_PLUGIN_FILE);
    }
}
add_action('init', 'hk_fs_init_github_updater');

/**
 * Handle rewrite rules flushing when appropriate
 */
function hk_fs_maybe_flush_rules() {
    if (get_option('hk_fs_flush_rewrite_rules') === 'yes') {
        flush_rewrite_rules();
        delete_option('hk_fs_flush_rewrite_rules');
    }
}
add_action('admin_init', 'hk_fs_maybe_flush_rules');

/**
 * Schedule rewrite rule flushing when public status changes
 */
function hk_fs_handle_public_option_changes($old_value, $value) {
    if ($old_value !== $value) {
        update_option('hk_fs_flush_rewrite_rules', 'yes');
    }
}

// Add actions for each CPT public option
add_action('update_option_hk_fs_enable_public_staff', 'hk_fs_handle_public_option_changes', 10, 2);
add_action('update_option_hk_fs_enable_public_caskets', 'hk_fs_handle_public_option_changes', 10, 2);
add_action('update_option_hk_fs_enable_public_urns', 'hk_fs_handle_public_option_changes', 10, 2);
add_action('update_option_hk_fs_enable_public_packages', 'hk_fs_handle_public_option_changes', 10, 2);

/**
 * Activate the plugin
 */
function hk_fs_activate_plugin() {
    // Ensure the settings are initialized with defaults
    $default_settings = array(
        'staff' => true,
        'caskets' => true,
        'urns' => true,
        'packages' => false
    );
    
    if (!get_option('hk_fs_enabled_cpts')) {
        update_option('hk_fs_enabled_cpts', $default_settings);
    }
    
    // Create blocks directory structure
    hk_fs_create_block_directories();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'hk_fs_activate_plugin');

/**
 * Deactivate the plugin
 */
function hk_fs_deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'hk_fs_deactivate_plugin');
