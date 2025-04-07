<?php
/**
 * Admin Column Utilities
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.1.1
 * @since      1.1.9
 * @changelog
 *   1.1.1 - Added monuments cpt
 *   1.1.0 - Added support for dynamically registered CPTs through hooks
 *   1.0.0 - Initial version with hardcoded CPT support
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HK_Funeral_Admin_Columns
 * 
 * Handles shared admin column functionality across all custom post types.
 * Provides consistent column display, image handling, SEO column removal,
 * and dynamic registration of new CPTs through the factory system.
 *
 * @since 1.1.9
 */
class HK_Funeral_Admin_Columns {
    
    /**
     * CPT configuration
     * 
     * @var array
     * @access private
     * @static
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
            'title_label' => 'Name' 
        ),
        'hk_fs_urn' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'urns',
            'title_label' => 'Name' 
        ),
        'hk_fs_package' => array(
            'needs_featured_image' => false,
            'option_suffix' => 'packages',
            'title_label' => 'Name' 
        ),
        'hk_fs_monument' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'monuments',
            'title_label' => 'Name' 
        ),
        'hk_fs_keepsake' => array(
            'needs_featured_image' => true,
            'option_suffix' => 'keepsakes',
            'title_label' => 'Name' 
        )
    );
    
    /**
     * Registry of dynamically registered CPT configurations
     *
     * @var array
     * @access private
     * @static
     */
    private static $registered_cpt_config = array();
    
    /**
     * HappyFiles column names to remove if needed
     * 
     * @var array
     * @access private
     * @static
     */
    private static $happyfiles_column_names = array('hf_featured_image', 'hf_featured_image hide');
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Set up hook listener for CPT registration
        add_action('hk_fs_register_cpt', array(__CLASS__, 'register_cpt_config'), 10, 3);
        
        // Process all CPTs (core + dynamically registered)
        self::setup_all_cpt_columns();
        
        // Add admin styles for columns
        add_action('admin_head', array(__CLASS__, 'add_admin_column_styles'));
    }
    
    /**
     * Register a custom post type configuration
     *
     * This method is called via the 'hk_fs_register_cpt' action hook when a new
     * CPT is registered through the factory. It adds the CPT to the admin columns
     * configuration for consistent column handling.
     *
     * @param string $post_type       The post type (without hk_fs_ prefix)
     * @param string $option_suffix   The suffix to use for option names
     * @param array  $args            Additional arguments about the CPT
     */
    public static function register_cpt_config($post_type, $option_suffix, $args) {
        // Store in registry for later use
        self::$registered_cpt_config["hk_fs_{$post_type}"] = array(
            'needs_featured_image' => true, // Default to true for product CPTs
            'option_suffix' => $option_suffix,
            'title_label' => isset($args['singular']) ? $args['singular'] : 'Name'
        );
        
        // Set up column handling for this CPT
        $cpt_slug = "hk_fs_{$post_type}";
        
        // Add featured image column
        add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'add_featured_image_column'), 5); 
        add_action("manage_{$cpt_slug}_posts_custom_column", array(__CLASS__, 'display_featured_image'), 10, 2);
        
        // Rename title column
        add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'rename_title_column'));
        
        // Check if this CPT should have SEO columns removed
        $option_name = "hk_fs_enable_public_{$option_suffix}";
        $make_public = get_option($option_name, false);
        
        if (!$make_public) {
            // Remove SEO columns
            add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'remove_seo_columns'), 100);
            add_filter("manage_edit-{$cpt_slug}_columns", array(__CLASS__, 'remove_seo_columns'), 100);
        }
    }
    
    /**
     * Set up admin columns for all registered CPTs
     * 
     * This handles both the core CPTs and dynamically registered ones
     */
    private static function setup_all_cpt_columns() {
        // Process core CPTs
        foreach (self::$cpt_config as $cpt_slug => $config) {
            // Add featured image if needed
            if ($config['needs_featured_image']) {
                add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'add_featured_image_column'), 5); 
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
        
        // Process dynamically registered CPTs
        foreach (self::$registered_cpt_config as $cpt_slug => $config) {
            // Add featured image if needed
            if ($config['needs_featured_image']) {
                add_filter("manage_{$cpt_slug}_posts_columns", array(__CLASS__, 'add_featured_image_column'), 5); 
                add_action("manage_{$cpt_slug}_posts_custom_column", array(__CLASS__, 'display_featured_image'), 10, 2);
            }
            
            // Rename title column for all except packages
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
        
        // Check if this is one of our CPTs (core or dynamically registered)
        $config = null;
        if (isset(self::$cpt_config[$post_type])) {
            $config = self::$cpt_config[$post_type];
        } elseif (isset(self::$registered_cpt_config[$post_type])) {
            $config = self::$registered_cpt_config[$post_type];
        }
        
        // If we have a config and title_label, apply it
        if ($config && isset($config['title_label'])) {
            $custom_label = $config['title_label'];
            
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
        
        // Apply the same JS approach to dynamically registered CPTs that need special handling
        foreach (self::$registered_cpt_config as $cpt_slug => $config) {
            if ($screen && $screen->post_type === $cpt_slug && isset($config['js_title_fix']) && $config['js_title_fix']) {
                ?>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Change the title column text
                    var $titleColumn = $('.wp-list-table th.column-title');
                    if ($titleColumn.length > 0) {
                        $titleColumn.html('<a href="#"><?php echo esc_js($config['title_label']); ?></a>');
                    }
                });
                </script>
                <?php
            }
        }
    }
}

// Initialize the class
HK_Funeral_Admin_Columns::init();
