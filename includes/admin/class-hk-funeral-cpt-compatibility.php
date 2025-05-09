<?php
/**
 * HK Funeral Suite Theme/Plugin Compatibility
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.4
 * @since      1.2.1
 * @changelog 
 *   1.0.4 - Added SEOPress metabox removal
 *   1.0.3 - Added HappyFiles compatibility
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
        'hk_fs_package',
        'hk_fs_monument',
        'hk_fs_keepsake'
    );
    
    /**
     * HappyFiles column names
     * 
     * @var array
     */
    private static $happyfiles_column_names = array('hf_featured_image', 'hf_featured_image hide');
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Register settings
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        
        // Enable HappyFiles compatibility by default if installed
        self::maybe_enable_happyfiles_compatibility();
        
        // Enable SEO Press metabox removal by default if installed
        self::maybe_enable_seopress_compatibility();
        
        // Load compatibility filters based on settings
        add_action('admin_init', array(__CLASS__, 'load_compatibility_filters'));
    }

    /**
     * Enable HappyFiles compatibility by default if installed
     */
    private static function maybe_enable_happyfiles_compatibility() {
        // Check if HappyFiles Pro is active
        if (class_exists('HappyFiles\\Pro')) {
            // Only set default if the option doesn't exist yet
            if (get_option('hk_fs_happyfiles_compatibility', null) === null) {
                update_option('hk_fs_happyfiles_compatibility', true);
            }
        }
    }
    
    /**
     * Enable SEO Press metabox removal by default if installed
     */
    private static function maybe_enable_seopress_compatibility() {
        // Check if SEO Press is active
        if (function_exists('seopress_init') || class_exists('\\SEOPRESS\\Core\\Kernel')) {
            // Only set default if the option doesn't exist yet
            if (get_option('hk_fs_seopress_metabox_compatibility', null) === null) {
                update_option('hk_fs_seopress_metabox_compatibility', true);
            }
        }
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
        // Register UI optimization section
        add_settings_section(
            'hk_fs_compatibility_section',
            'Theme & Plugin Meta Box Cleanup',
            array(__CLASS__, 'render_compatibility_section'),
            'hk-funeral-suite-settings'
        );
        
        // Add compatibility fields
        add_settings_field(
            'hk_fs_theme_compatibility_field',
            'Theme Meta Box Cleanup',
            array(__CLASS__, 'render_theme_compatibility_field'),
            'hk-funeral-suite-settings',
            'hk_fs_compatibility_section'
        );
        
        add_settings_field(
            'hk_fs_plugin_compatibility_field',
            'Plugin Meta Box Cleanup',
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
        register_setting('hk_fs_settings', 'hk_fs_happyfiles_compatibility', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
        
        register_setting('hk_fs_settings', 'hk_fs_seopress_metabox_compatibility', array(
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
        echo '<p class="description">This is currently an \'opinionated\' list of themes and plugins we use ourselves at Weave Digital Studio and HumanKind Funeral Websites. These settings help clean up the editing interface by removing UI elements not needed for funeral content management. Reach out to <a href="mailto:support@weave.co.nz">support@weave.co.nz</a> if you think there are others we should consider adding here.</p>';
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
        // Check if plugins are active
        $happyfiles_active = class_exists('HappyFiles\\Pro');
        $seopress_active = function_exists('seopress_init') || class_exists('\\SEOPRESS\\Core\\Kernel');
        ?>
        <fieldset>
            <label<?php echo !$happyfiles_active ? ' class="disabled-option"' : ''; ?>>
                <input type="checkbox" name="hk_fs_happyfiles_compatibility" value="1" 
                    <?php checked(get_option('hk_fs_happyfiles_compatibility', false)); ?>
                    <?php disabled(!$happyfiles_active); ?>>
                <a href="https://happyfiles.io/" target="_blank">HappyFiles Pro</a>
                <span class="description"> - Remove duplicate featured image column from funeral post types</span>
                <?php if (!$happyfiles_active) : ?>
                    <em class="inactive-notice">(Plugin not active)</em>
                <?php endif; ?>
            </label><br>
            
            <label<?php echo !$seopress_active ? ' class="disabled-option"' : ''; ?>>
                <input type="checkbox" name="hk_fs_seopress_metabox_compatibility" value="1" 
                    <?php checked(get_option('hk_fs_seopress_metabox_compatibility', false)); ?>
                    <?php disabled(!$seopress_active); ?>>
                <a href="https://www.seopress.org/" target="_blank">SEO Press</a>
                <span class="description"> - Remove SEO and content analysis metaboxes from funeral post types</span>
                <?php if (!$seopress_active) : ?>
                    <em class="inactive-notice">(Plugin not active)</em>
                <?php endif; ?>
            </label><br><br>
            
            <p class="description">Additional plugin compatibility options will be added in future updates. If you have specific plugins you'd like to see supported, please contact <a href="mailto:support@weave.co.nz">support@weave.co.nz</a>.</p>
        </fieldset>
        <?php
    }
    
    /**
     * Load the appropriate compatibility filters based on enabled settings
     */
    public static function load_compatibility_filters() {
        // Add HappyFiles compatibility
        if (get_option('hk_fs_happyfiles_compatibility', false)) {
            self::setup_happyfiles_compatibility();
        }
        
        // Add SEOPress compatibility 
        if (get_option('hk_fs_seopress_metabox_compatibility', false)) {
            add_filter('seopress_metaboxe_term_seo', array(__CLASS__, 'remove_seopress_metabox_for_funeral_cpts'));
            add_filter('seopress_metaboxe_content_analysis', array(__CLASS__, 'remove_seopress_metabox_for_funeral_cpts'));
            add_filter('seopress_metaboxe_titles', array(__CLASS__, 'remove_seopress_metabox_for_funeral_cpts'));
            add_filter('seopress_metaboxe_social', array(__CLASS__, 'remove_seopress_metabox_for_funeral_cpts'));
            add_filter('seopress_metaboxe_advanced', array(__CLASS__, 'remove_seopress_metabox_for_funeral_cpts'));
        }
        
        // Setup theme-specific compatibility
        if (get_option('hk_fs_generatepress_compatibility', false) && self::is_theme_active('generatepress')) {
            add_action('add_meta_boxes', array(__CLASS__, 'remove_generatepress_meta_boxes'), 100);
        }
        
        if (get_option('hk_fs_wpbf_compatibility', false) && self::is_theme_active('page-builder-framework')) {
            add_action('add_meta_boxes', array(__CLASS__, 'remove_wpbf_meta_boxes'), 100);
        }
        
        // Update CPT slugs when keepsake cpt is registered
        add_action('hk_fs_register_cpt', function($post_type, $option_suffix, $args) {
            if ($post_type === 'keepsake') {
                self::$cpt_slugs[] = "hk_fs_{$post_type}";
            }
        }, 10, 3);
    }
    
    /**
     * Setup HappyFiles compatibility
     */
    public static function setup_happyfiles_compatibility() {
        // Apply to all CPTs in the funeral suite
        foreach (self::$cpt_slugs as $cpt) {
            // Add with a high priority (1000) to ensure it runs after HappyFiles adds its columns
            add_filter("manage_{$cpt}_posts_columns", array(__CLASS__, 'remove_happyfiles_featured_image_column'), 1000);
        }
    }
    
    /**
     * Remove HappyFiles featured image column from specified post types
     * 
     * @param array $columns The admin columns
     * @return array Modified columns
     */
    public static function remove_happyfiles_featured_image_column($columns) {
        // Remove both possible HappyFiles column names
        foreach (self::$happyfiles_column_names as $column_name) {
            if (isset($columns[$column_name])) {
                unset($columns[$column_name]);
            }
        }
        return $columns;
    }
    
    /**
     * Remove SEO Press metabox from funeral custom post types
     * 
     * @param array $enabled_post_types Post types where SEO Press metabox is enabled
     * @return array Modified post types list
     */
    public static function remove_seopress_metabox_for_funeral_cpts($enabled_post_types) {
        // If enabled_post_types is not an array, make it one (defensive coding)
        if (!is_array($enabled_post_types)) {
            $enabled_post_types = array();
        }
        
        // Get all registered post types
        $all_post_types = get_post_types(array('public' => true), 'names');
        
        // Start with all post types enabled except our funeral ones
        $filtered_post_types = array();
        
        // If SEO Press already set specific post types, use those as a base
        if (!empty($enabled_post_types)) {
            $filtered_post_types = $enabled_post_types;
        } else {
            // Otherwise, enable for all public post types
            foreach ($all_post_types as $post_type) {
                $filtered_post_types[$post_type] = $post_type;
            }
        }
        
        // Remove our funeral post types
        foreach (self::$cpt_slugs as $cpt) {
            if (isset($filtered_post_types[$cpt])) {
                unset($filtered_post_types[$cpt]);
            }
        }
        
        return $filtered_post_types;
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
}

// Initialize the class
HK_Funeral_Compatibility::init();
