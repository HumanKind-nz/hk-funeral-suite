<?php
/**
 * Admin Class
 *
 * Handles general admin functionality for the HK Funeral Suite plugin.
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.0
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    exit;
}

/**
 * Class for handling general admin functionality
 */
class HK_Funeral_Admin {
    /**
     * Initialize the admin class
     */
    public static function init() {
        new self();
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Filter admin footer text on HK Funeral Suite admin pages
        add_filter('admin_footer_text', array($this, 'admin_footer_text'), 10, 1);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        
        // Check if we're on one of our post types
        $hk_post_types = array(
            'hk_fs_staff', 
            'hk_fs_casket', 
            'hk_fs_urn', 
            'hk_fs_package', 
            'hk_fs_monument',
            'hk_fs_keepsake'
        );
        
        if (in_array($screen->post_type, $hk_post_types)) {
            // Enqueue admin styles for our post types
            wp_enqueue_style(
                'hk-funeral-admin-style',
                HK_FS_PLUGIN_URL . 'assets/css/admin-style.css',
                array(),
                HK_FS_VERSION
            );
        }
    }

    /**
     * Customize admin footer text
     */
    public function admin_footer_text($footer_text) {
        $screen = get_current_screen();
        if (!$screen) {
            return $footer_text;
        }
        
        // Check if we're on one of our post types or settings page
        $hk_post_types = array(
            'hk_fs_staff', 
            'hk_fs_casket', 
            'hk_fs_urn', 
            'hk_fs_package', 
            'hk_fs_monument',
            'hk_fs_keepsake'
        );
        
        if (in_array($screen->post_type, $hk_post_types) || 
            (isset($_GET['page']) && $_GET['page'] === 'hk-funeral-suite-settings')) {
            $footer_text = sprintf(
                __('Thank you for using <a href="%s" target="_blank">HK Funeral Suite</a>.', 'hk-funeral-cpt'),
                'https://github.com/HumanKind-nz/hk-funeral-suite/'
            );
        }
        
        return $footer_text;
    }
}

// Initialize the admin class using the hook, not directly
add_action('admin_init', array('HK_Funeral_Admin', 'init')); 