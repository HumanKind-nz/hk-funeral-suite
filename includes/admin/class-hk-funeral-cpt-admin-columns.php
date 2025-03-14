<?php
/**
 * Admin Column Utilities
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.0
 * @since      1.1.9
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
            'option_suffix' => 'staff',
            'title_label' => 'Name' // Custom title label
        ),
        'hk_fs_casket' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'caskets',
            'title_label' => 'Name' // Custom title label
        ),
        'hk_fs_urn' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'urns',
            'title_label' => 'Name' // Custom title label
        ),
        'hk_fs_package' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'packages',
            'title_label' => 'Name' // Custom title label
        )
    );
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Add featured image column to CPTs that need it
        foreach (self::$cpt_config as $cpt_slug => $config) {
            // Add featured image if needed
            if ($config['needs_featured_image']) {
                add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'add_featured_image_column'), 5); // Priority 5 to run early
                add_action("manage_{$cpt_slug}_posts_custom_column", array(__CLASS__, 'display_featured_image'), 10, 2);
            }
            
            // For the package CPT, we'll handle title renaming separately
            if (isset($config['title_label']) && $cpt_slug !== 'hk_fs_package') {
                add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'rename_title_column'));
            }
            
            // Add direct SEO Press column removal for each CPT
            $option_name = 'hk_fs_enable_public_' . $config['option_suffix'];
            $make_public = get_option($option_name, false);
            
            if (!$make_public) {
                // Use direct filters for each post type - this is key
                add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'remove_seo_columns'), 100);
                add_filter("manage_edit-{$cpt_slug}_columns", array(__CLASS__, 'remove_seo_columns'), 100);
            }
        }
        
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
     * Rename the title column to a custom label
     * 
     * @param array $columns Current admin columns
     * @return array Modified columns with renamed title
     */
    public static function rename_title_column($columns) {
        $screen = get_current_screen();
        if (!$screen) return $columns;
        
        $post_type = $screen->post_type;
        
        // Check if this is one of our CPTs
        if (isset(self::$cpt_config[$post_type]) && isset(self::$cpt_config[$post_type]['title_label'])) {
            $custom_label = self::$cpt_config[$post_type]['title_label'];
            
            // Rename the title column
            if (isset($columns['title'])) {
                $columns['title'] = __($custom_label, 'hk-funeral-suite');
            }
        }
        
        return $columns;
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
     * Remove SEO columns using a direct approach
     * 
     * @param array $columns Current admin columns
     * @return array Modified columns
     */
    public static function remove_seo_columns($columns) {
        // SEO Press columns
        unset($columns['seopress_title']);
        unset($columns['seopress_desc']);
        unset($columns['seopress_score']);
        unset($columns['seopress_noindex']);
        unset($columns['seopress_nofollow']);
        unset($columns['seopress_insights_score']);
        
        // More aggressive removal for any SEO Press columns
        foreach ($columns as $key => $value) {
            if (strpos($key, 'seopress') === 0) {
                unset($columns[$key]);
            }
        }
        
        // Yoast SEO columns
        unset($columns['wpseo-title']);
        unset($columns['wpseo-metadesc']);
        unset($columns['wpseo-focuskw']);
        unset($columns['wpseo-score']);
        unset($columns['wpseo-score-readability']);
        
        // Rank Math columns
        unset($columns['rank_math_title']);
        unset($columns['rank_math_description']);
        unset($columns['rank_math_seo_details']);
        
        // All in One SEO columns
        unset($columns['aioseo-title']);
        unset($columns['aioseo-description']);
        unset($columns['aioseo-keywords']);
        unset($columns['aioseo-score']);
        
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
        // JavaScript approach for package title - this is the only method we'll use
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'hk_fs_package') {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Change the package title column text to 'Name'
                // Only do this once to avoid multiple replacements
                var $titleColumn = $('.wp-list-table th.column-title');
                if ($titleColumn.length > 0 && $titleColumn.text().indexOf('Name Name') === -1) {
                    $titleColumn.html('<a href="#">Name</a>');
                }
            });
            </script>
            <?php
        }
    }
}

// Initialize the class
HK_Funeral_Admin_Columns::init();
