<?php
/**
 * Admin Column Utilities
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.0
 * @since      1.0.7
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class to handle shared admin column functionality across all custom post types
 */
class HK_Funeral_Admin_Columns {
    
    /**
     * CPT configuration
     * 
     * @var array
     */
    private static $cpt_config = array(
        'hk_fs_staff' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'staff'
        ),
        'hk_fs_casket' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'caskets'
        ),
        'hk_fs_urn' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'urns'
        ),
        'hk_fs_package' => array(
            'needs_featured_image' => false,
            'option_suffix' => 'packages'
        )
    );
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Add featured image column to CPTs that need it
        foreach (self::$cpt_config as $cpt_slug => $config) {
            if ($config['needs_featured_image']) {
                add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'add_featured_image_column'), 5); // Priority 5 to run early
                add_action("manage_{$cpt_slug}_posts_custom_column", array(__CLASS__, 'display_featured_image'), 10, 2);
            }
        }
        
        // Remove SEO columns when CPTs are not public
        add_filter('manage_posts_columns', array(__CLASS__, 'conditionally_remove_seo_columns'), 100);
        
        // Add admin styles for columns
        add_action('admin_head', array(__CLASS__, 'add_admin_column_styles'));
    }
    
    /**
     * Add featured image column to the beginning of columns list
     * 
     * @param array $columns Current admin columns
     * @return array Modified columns with featured image added
     */
    public static function add_featured_image_column($columns) {
        $new_columns = array();
        
        // Add featured image column at the beginning
        $new_columns['featured_image'] = __('Image', 'hk-funeral-suite');
        
        // Add remaining columns
        foreach($columns as $key => $value) {
            $new_columns[$key] = $value;
        }
        
        return $new_columns;
    }
    
    /**
     * Display featured image in admin column
     * 
     * @param string $column Column ID
     * @param int $post_id Post ID
     */
    public static function display_featured_image($column, $post_id) {
        if ($column === 'featured_image') {
            // Display featured image with minimum size of 150px
            if (has_post_thumbnail($post_id)) {
                echo '<img src="' . get_the_post_thumbnail_url($post_id, 'full') . '" style="width:150px; height:auto; max-height:150px; object-fit:cover;">';
            } else {
                echo '<div style="width:150px; height:100px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border:1px solid #ddd; border-radius:3px;"><span class="dashicons dashicons-format-image" style="font-size:30px; color:#bbb;"></span></div>';
            }
        }
    }
    
    /**
     * Conditionally remove SEO columns when CPTs are not public
     * 
     * @param array $columns Current admin columns
     * @return array Modified columns with SEO columns removed if needed
     */
    public static function conditionally_remove_seo_columns($columns) {
        // Get the current screen to determine post type
        $screen = get_current_screen();
        if (!$screen) return $columns;
        
        $post_type = $screen->post_type;
        
        // Check if this is one of our CPTs
        if (isset(self::$cpt_config[$post_type])) {
            $config = self::$cpt_config[$post_type];
            
            // Get the corresponding public setting
            $option_name = 'hk_fs_enable_public_' . $config['option_suffix'];
            $make_public = get_option($option_name, false);
            
            // If not public, remove SEO columns
            if (!$make_public) {
                // Remove SEO columns from various plugins
                $columns = self::remove_seo_plugin_columns($columns);
            }
        }
        
        return $columns;
    }
    
    /**
     * Remove columns from various SEO plugins
     * 
     * @param array $columns Current admin columns
     * @return array Modified columns with SEO columns removed
     */
    private static function remove_seo_plugin_columns($columns) {
        // Check if SEO Press is active
        if (function_exists('seopress_init') || defined('SEOPRESS_VERSION')) {
            // Remove SEO Press columns
            unset($columns['seopress_title']);
            unset($columns['seopress_desc']);
            unset($columns['seopress_score']);
            unset($columns['seopress_noindex']);
            unset($columns['seopress_nofollow']);
            unset($columns['seopress_insights_score']);
        }
        
        // Check for Yoast SEO
        if (defined('WPSEO_VERSION')) {
            unset($columns['wpseo-title']);
            unset($columns['wpseo-metadesc']);
            unset($columns['wpseo-focuskw']);
            unset($columns['wpseo-score']);
            unset($columns['wpseo-score-readability']);
            unset($columns['wpseo-links']);
            unset($columns['wpseo-linked']);
        }
        
        // Check for Rank Math
        if (defined('RANK_MATH_VERSION')) {
            unset($columns['rank_math_title']);
            unset($columns['rank_math_description']);
            unset($columns['rank_math_seo_details']);
            unset($columns['rank_math_schema']);
        }
        
        // Check for All in One SEO
        if (defined('AIOSEO_VERSION')) {
            unset($columns['aioseo-title']);
            unset($columns['aioseo-description']);
            unset($columns['aioseo-keywords']);
            unset($columns['aioseo-score']);
        }
        
        return $columns;
    }
    
    /**
     * Add CSS styles for admin columns
     */
    public static function add_admin_column_styles() {
        ?>
        <style>
            /* Image column width */
            .column-featured_image {
                width: 150px !important;
                overflow: hidden;
            }
            
            /* Add spacing to admin columns */
            .column-featured_image img {
                border-radius: 3px;
                border: 1px solid #ddd;
            }
        </style>
        <?php
    }
}

// Initialize the class
HK_Funeral_Admin_Columns::init();
