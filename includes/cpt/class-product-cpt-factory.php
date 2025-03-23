<?php
/**
 * Factory for creating product-type custom post types
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.1.3
 * @since      1.3.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class HK_Funeral_Product_CPT_Factory
 * 
 * A factory class for registering product-type custom post types with consistent
 * features, fields, and behavior. Uses a centralized registration system that
 * notifies other components in the plugin about new CPT registrations.
 *
 * @since 1.3.0
 */
class HK_Funeral_Product_CPT_Factory {
    
    /**
     * Registry of all registered product CPTs
     * 
     * @var array
     * @access private
     * @static
     */
    private static $registered_cpts = array();
    
    /**
     * Track registered CPTs within a single request
     *
     * @var array
     * @access private
     * @static
     */
    private static $request_registered = array();
    
    /**
     * Log debug messages if debug mode is enabled
     *
     * @param string $message The message to log
     */
    private static function debug_log($message) {
        if (defined('HK_FS_DEBUG') && HK_FS_DEBUG) {
            error_log('HK Funeral Suite: ' . $message);
        }
    }
    
    /**
     * Register a new product-type CPT
     *
     * Creates a new custom post type with all standard funeral product features.
     * Broadcasts registration via action hooks so other components can respond.
     *
     * @param array $args {
     *     Configuration array for the CPT.
     *     
     *     @type string $post_type Required. The post type name without the 'hk_fs_' prefix.
     *     @type string $singular  Required. Singular label for the post type.
     *     @type string $plural    Required. Plural label for the post type.
     *     @type string $menu_name Optional. Custom menu name. Defaults to "HK {Plural}".
     *     @type string $slug      Optional. URL slug for the post type. Defaults to lowercase plural.
     *     @type string $icon      Optional. Dashicon name or full URL to icon.
     *     @type string $svg_icon  Optional. SVG markup for custom menu icon.
     * }
     * 
     * @return bool True on successful registration, false on failure.
     */
    public static function register_product_cpt($args) {    
        // Check if this CPT is already registered in this request
        if (isset(self::$request_registered[$args['post_type']])) {
            return true; // Already registered in this request
        }
        
        // Track this registration in the request cache
        self::$request_registered[$args['post_type']] = true;
        
        // Check for required fields
        if (!isset($args['post_type']) || !isset($args['singular']) || !isset($args['plural'])) {
            self::debug_log('Failed to register CPT - missing required fields');
            return false;
        }
        
        // Extract required arguments
        $post_type = $args['post_type'];
        $singular = $args['singular'];
        $plural = $args['plural'];     
        $menu_name = isset($args['menu_name']) ? $args['menu_name'] : 'HK ' . $plural;
        $slug = isset($args['slug']) ? $args['slug'] : strtolower($plural);
        $icon = isset($args['icon']) ? $args['icon'] : 'dashicons-archive';
        $svg_icon = isset($args['svg_icon']) ? $args['svg_icon'] : null;
        
        // Set up option names
        $public_option = 'hk_fs_enable_public_' . strtolower($plural);
        
        // Store in registry for later reference
        self::$registered_cpts[$post_type] = array(
            'post_type' => $post_type,
            'singular' => $singular,
            'plural' => $plural,
            'option_suffix' => strtolower($plural)
        );
        
        // Check if we've already registered this CPT in a previous request
        $registered_cpts = get_transient('hk_fs_registered_cpts');
        if (is_array($registered_cpts) && isset($registered_cpts[$post_type])) {
            // Already registered in a previous request
            return true;
        }
        
        // Fire pre-registration hook - allows early modification of args
        do_action('hk_fs_before_register_cpt', $post_type, $args);
        
        // Register post type - with default priority
        add_action('init', function() use ($post_type, $singular, $plural, $menu_name, $slug, $icon, $public_option) {
            $labels = array(
                'name'                  => $plural, // Already translated in the call
                'singular_name'         => $singular, // Already translated in the call
                'menu_name'             => _x($menu_name, 'Admin Menu text', 'hk-funeral-cpt'),
                'add_new'               => __('Add New', 'hk-funeral-cpt'),
                'add_new_item'          => sprintf(__('Add New %s', 'hk-funeral-cpt'), $singular),
                'edit_item'             => sprintf(__('Edit %s', 'hk-funeral-cpt'), $singular),
                'new_item'              => sprintf(__('New %s', 'hk-funeral-cpt'), $singular),
                'view_item'             => sprintf(__('View %s', 'hk-funeral-cpt'), $singular),
                'view_items'            => sprintf(__('View %s', 'hk-funeral-cpt'), $plural),
                'search_items'          => sprintf(__('Search %s', 'hk-funeral-cpt'), $plural),
                'not_found'             => sprintf(__('No %s found.', 'hk-funeral-cpt'), strtolower($plural)),
                'not_found_in_trash'    => sprintf(__('No %s found in Trash.', 'hk-funeral-cpt'), strtolower($plural)),
                'featured_image'        => sprintf(__('%s Image', 'hk-funeral-cpt'), $singular),
                'set_featured_image'    => sprintf(__('Set %s image', 'hk-funeral-cpt'), strtolower($singular)),
                'remove_featured_image' => sprintf(__('Remove %s image', 'hk-funeral-cpt'), strtolower($singular)),
                'use_featured_image'    => sprintf(__('Use as %s image', 'hk-funeral-cpt'), strtolower($singular)),
            );
            
            // Get the public setting
            $make_public = get_option($public_option, false);
            
            // Generate CPT args using shared function
            $cpt_args = hk_fs_generate_cpt_args(
                [
                    'labels' => $labels,
                    'menu_icon' => $icon,
                    'supports' => array('title', 'editor', 'thumbnail', 'page-attributes', 'revisions'),
                    'rewrite' => $make_public ? array('slug' => $slug) : false,
                    'template' => array(
                        array("hk-funeral-suite/{$post_type}"),
                        array('core/paragraph')
                    ),
                ],
                $post_type,
                $make_public
            );
            
            $cpt_args = apply_filters("hk_fs_{$post_type}_post_type_args", $cpt_args);
            
            self::debug_log("Registering CPT: hk_fs_{$post_type}");
            
            register_post_type("hk_fs_{$post_type}", $cpt_args);
            
            // Track registrations in transient for future requests
            $registered_cpts = get_transient('hk_fs_registered_cpts') ?: array();
            $registered_cpts[$post_type] = array(
                'version' => HK_FS_VERSION,
                'time' => time()
            );
            set_transient('hk_fs_registered_cpts', $registered_cpts, DAY_IN_SECONDS);
            
            // Fire post-registration hook for this specific CPT
            do_action("hk_fs_registered_{$post_type}_cpt", $post_type);
        }, 10);
        
        // Register category taxonomy
        add_action('init', function() use ($post_type, $singular, $plural) {
            hk_fs_register_category_taxonomy($post_type, $singular, $plural);
        }, 10);
        
        // Add custom SVG icon if provided
        if ($svg_icon) {
            self::register_svg_icon($post_type, $svg_icon);
        }
        
        // Register all standard features for this product CPT
        self::register_standard_features($post_type, $singular, $plural, $public_option);
        
        // Fire action to notify other system components about this CPT registration
        do_action('hk_fs_register_cpt', $post_type, strtolower($plural), array(
            'singular' => $singular,
            'plural' => $plural,
            'option_suffix' => strtolower($plural)
        ));
        
        return true;
    }
    
    /**
     * Get all registered product CPTs
     *
     * @return array Associative array of registered CPTs
     */
    public static function get_registered_cpts() {
        return self::$registered_cpts;
    }
    
    /**
     * Register all standard features for a product CPT
     *
     * Sets up all the standard features, meta fields, and admin UI elements
     * that are common to all product-type CPTs.
     *
     * @param string $post_type The post type slug
     * @param string $singular Singular label
     * @param string $plural Plural label
     * @param string $public_option The option name for public visibility setting
     */
    private static function register_standard_features($post_type, $singular, $plural, $public_option) {
        // Register all standard features
        hk_fs_register_settings_submenu($post_type);
        hk_fs_register_title_placeholder($post_type, $singular);
        hk_fs_register_price_meta($post_type);
        hk_fs_register_price_metabox($post_type, $singular);
        hk_fs_register_sheets_notice($post_type, $singular);
        hk_fs_add_price_column($post_type);
        hk_fs_register_auto_insert_block($post_type);
        hk_fs_register_block_template($post_type, $singular);
        hk_fs_register_admin_styles($post_type);
        hk_fs_restrict_admin_screen_access($post_type);
        
        // Register option change handler for rewrite rules
        add_action("update_option_{$public_option}", 'hk_fs_handle_public_option_changes', 10, 2);
    }
    
    /**
     * Register SVG icon for a CPT
     * 
     * Sets up a custom SVG icon in the admin menu for a CPT using CSS.
     * 
     * @param string $post_type The post type slug
     * @param string $svg_icon SVG icon markup
     */
    private static function register_svg_icon($post_type, $svg_icon) {
        add_action('admin_head', function() use ($post_type, $svg_icon) {
            ?>
            <style>
                /* Custom Icon */
                #adminmenu .menu-icon-hk_fs_<?php echo $post_type; ?> div.wp-menu-image::before {
                    content: '';
                    background-image: url('data:image/svg+xml;utf8,<?php echo $svg_icon; ?>');
                    background-repeat: no-repeat;
                    background-position: center;
                    background-size: 20px;
                    opacity: 0.6;
                }
                
                /* Hover state */
                #adminmenu .menu-icon-hk_fs_<?php echo $post_type; ?>:hover div.wp-menu-image::before,
                #adminmenu .menu-icon-hk_fs_<?php echo $post_type; ?>.current div.wp-menu-image::before {
                    background-image: url('data:image/svg+xml;utf8,<?php echo str_replace('%23a7aaad', '%23ffffff', $svg_icon); ?>');
                    opacity: 1;
                }
            </style>
            <?php
        });
    }
    
    /**
     * Reset the registration cache
     * 
     * Useful after plugin update or when CPT settings change
     */
    public static function reset_registration_cache() {
        delete_transient('hk_fs_registered_cpts');
        self::debug_log('CPT registration cache reset');
    }
}
