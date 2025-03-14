<?php
/**
 * HK Funeral Suite Theme/Plugin Compatibility
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.1
 * @since      1.2.1
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class to handle compatibility with popular themes and plugins
 */
class HK_Funeral_Compatibility {
    
    /**
     * CPT configuration
     * 
     * @var array
     */
    private static $cpt_slugs = array(
        'hk_fs_staff',
        'hk_fs_casket',
        'hk_fs_urn',
        'hk_fs_package'
    );
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Register settings
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        
        // Load compatibility filters based on settings
        add_action('admin_init', array(__CLASS__, 'load_compatibility_filters'));
    }

    /**
     * Check if a specific theme is active (either as main theme or parent)
     *
     * @param string $theme_slug The theme slug to check for
     * @return bool Whether the theme is active
     */
    private static function is_theme_active($theme_slug) {
        $current_theme = wp_get_theme();
        
        // Check if it's the active theme or parent theme
        if ($current_theme->get('Template') === $theme_slug || $current_theme->get_stylesheet() === $theme_slug) {
            return true;
        }
        return false;
    }
    
    /**
     * Register compatibility settings
     */
    public static function register_settings() {
        // Register compatibility section
        add_settings_section(
            'hk_fs_compatibility_section',
            'Theme & Plugin Compatibility',
            array(__CLASS__, 'render_compatibility_section'),
            'hk-funeral-suite-settings'
        );
        
        // Add compatibility fields
        add_settings_field(
            'hk_fs_theme_compatibility_field',
            'Theme Compatibility',
            array(__CLASS__, 'render_theme_compatibility_field'),
            'hk-funeral-suite-settings',
            'hk_fs_compatibility_section'
        );
        
        add_settings_field(
            'hk_fs_plugin_compatibility_field',
            'Plugin Compatibility',
            array(__CLASS__, 'render_plugin_compatibility_field'),
            'hk-funeral-suite-settings',
            'hk_fs_compatibility_section'
        );
        
        // Register theme compatibility settings
        register_setting('hk_fs_settings', 'hk_fs_generatepress_compatibility', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
        
        register_setting('hk_fs_settings', 'hk_fs_wpbf_compatibility', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
        
        // Register plugin compatibility settings
        register_setting('hk_fs_settings', 'hk_fs_perfmatters_compatibility', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
    }
    
    /**
     * Render the compatibility section description
     */
    public static function render_compatibility_section() {
        echo '<p>Simplify your post editing experience by removing unnecessary meta boxes from popular themes and plugins when editing funeral content types:</p>';
    }
    
    /**
     * Render the theme compatibility field
     */
    public static function render_theme_compatibility_field() {
        // Check if themes are active
        $generatepress_active = self::is_theme_active('generatepress');
        $wpbf_active = self::is_theme_active('page-builder-framework');
        ?>
        <fieldset>
            <label<?php echo !$generatepress_active ? ' class="disabled-option"' : ''; ?>>
                <input type="checkbox" name="hk_fs_generatepress_compatibility" value="1" 
                    <?php checked(get_option('hk_fs_generatepress_compatibility', false)); ?>
                    <?php disabled(!$generatepress_active); ?>>
                <a href="https://generatepress.com/" target="_blank">GeneratePress</a>
                <span class="description"> - Remove layout options and sections meta boxes</span>
                <?php if (!$generatepress_active) : ?>
                    <em class="inactive-notice">(Theme not active)</em>
                <?php endif; ?>
            </label><br>
            
            <label<?php echo !$wpbf_active ? ' class="disabled-option"' : ''; ?>>
                <input type="checkbox" name="hk_fs_wpbf_compatibility" value="1" 
                    <?php checked(get_option('hk_fs_wpbf_compatibility', false)); ?>
                    <?php disabled(!$wpbf_active); ?>>
                <a href="https://wp-pagebuilderframework.com/" target="_blank">Page Builder Framework</a>
                <span class="description"> - Remove theme settings meta boxes</span>
                <?php if (!$wpbf_active) : ?>
                    <em class="inactive-notice">(Theme not active)</em>
                <?php endif; ?>
            </label>
        </fieldset>
        <p class="description">Remove unnecessary meta boxes from these themes to clean up the editor interface for your funeral content types.</p>
        
        <style>
            .disabled-option {
                opacity: 0.6;
                cursor: default;
            }
            .inactive-notice {
                color: #d63638;
                font-style: italic;
                margin-left: 5px;
            }
        </style>
        <?php
    }
    
    /**
     * Render the plugin compatibility field
     */
    public static function render_plugin_compatibility_field() {
        ?>
        <fieldset>
            <label>
                <input type="checkbox" name="hk_fs_perfmatters_compatibility" value="1" 
                    <?php checked(get_option('hk_fs_perfmatters_compatibility', false)); ?>>
                <a href="https://perfmatters.io/" target="_blank">Perfmatters</a>
                <span class="description"> - Hide optimization meta boxes on each custom post type</span>
            </label>
        </fieldset>
        <p class="description">Remove unnecessary meta boxes from these plugins to clean up the editor interface for your funeral content types.</p>
        <?php
    }
    
    /**
     * Load the appropriate compatibility filters based on enabled settings
     */
    public static function load_compatibility_filters() {
        // GeneratePress compatibility
        if (get_option('hk_fs_generatepress_compatibility', false)) {
            add_action('add_meta_boxes', array(__CLASS__, 'remove_generatepress_meta_boxes'), 999);
        }
        
        // Page Builder Framework compatibility
        if (get_option('hk_fs_wpbf_compatibility', false)) {
            add_action('admin_head', array(__CLASS__, 'remove_wpbf_meta_boxes'));
        }
        
        // Perfmatters compatibility
        if (get_option('hk_fs_perfmatters_compatibility', false)) {
            add_filter('perfmatters/metabox', array(__CLASS__, 'disable_perfmatters_metabox'));
        }
    }
    
    /**
     * Remove GeneratePress meta boxes
     */
    public static function remove_generatepress_meta_boxes() {
        // Remove GeneratePress layout options and sections meta boxes
        remove_meta_box(
            'generate_layout_options_meta_box',
            self::$cpt_slugs,
            'normal'
        );
        remove_meta_box(
            'generate_layout_options_meta_box', 
            self::$cpt_slugs, 
            'side'
        );
        remove_meta_box(
            '_generate_use_sections_metabox', 
            self::$cpt_slugs, 
            'side'
        );
    }
    
    /**
     * Remove Page Builder Framework meta boxes
     */
    public static function remove_wpbf_meta_boxes() {
        // Remove WPBF meta boxes from all funeral CPTs
        foreach (self::$cpt_slugs as $cpt) {
            remove_meta_box('wpbf', $cpt, 'side');
            remove_meta_box('wpbf_header', $cpt, 'side');
            remove_meta_box('wpbf_sidebar', $cpt, 'side');
        }
    }
    
    /**
     * Disable Perfmatters meta box for funeral CPTs
     */
    public static function disable_perfmatters_metabox($display) {
        // Check if current post type is one of our funeral suite CPTs
        if (in_array(get_post_type(), self::$cpt_slugs)) {
            return false;
        }
        
        return $display;
    }
}

// Initialize the class
HK_Funeral_Compatibility::init();
