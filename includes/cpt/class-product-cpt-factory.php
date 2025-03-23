<?php
/**
 * Factory for creating product-type custom post types
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.0
 * @since      1.3.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class HK_Funeral_Product_CPT_Factory {
    /**
     * Register a new product-type CPT
     *
     * @param array $args Configuration array for the CPT
     */
    public static function register_product_cpt($args) {
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
        
        // Register post type
        add_action('init', function() use ($post_type, $singular, $plural, $menu_name, $slug, $icon, $public_option) {
            $labels = array(
                'name'                  => _x($plural, 'Post type general name', 'hk-funeral-cpt'),
                'singular_name'         => _x($singular, 'Post type singular name', 'hk-funeral-cpt'),
                'menu_name'             => _x($menu_name, 'Admin Menu text', 'hk-funeral-cpt'),
                'add_new'               => __('Add New', 'hk-funeral-cpt'),
                'add_new_item'          => __("Add New {$singular}", 'hk-funeral-cpt'),
                'edit_item'             => __("Edit {$singular}", 'hk-funeral-cpt'),
                'new_item'              => __("New {$singular}", 'hk-funeral-cpt'),
                'view_item'             => __("View {$singular}", 'hk-funeral-cpt'),
                'view_items'            => __("View {$plural}", 'hk-funeral-cpt'),
                'search_items'          => __("Search {$plural}", 'hk-funeral-cpt'),
                'not_found'             => __("No {$plural} found.", 'hk-funeral-cpt'),
                'not_found_in_trash'    => __("No {$plural} found in Trash.", 'hk-funeral-cpt'),
                'featured_image'        => __("{$singular} Image", 'hk-funeral-cpt'),
                'set_featured_image'    => __("Set {$singular} image", 'hk-funeral-cpt'),
                'remove_featured_image' => __("Remove {$singular} image", 'hk-funeral-cpt'),
                'use_featured_image'    => __("Use as {$singular} image", 'hk-funeral-cpt'),
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
            
            register_post_type("hk_fs_{$post_type}", $cpt_args);
        }, 0);
        
        // Register category taxonomy
        add_action('init', function() use ($post_type, $singular, $plural) {
            hk_fs_register_category_taxonomy($post_type, $singular, $plural);
        }, 0);
        
        // Add custom SVG icon if provided
        if ($svg_icon) {
            self::register_svg_icon($post_type, $svg_icon);
        }
        
        // Use shared functions for standard CPT features
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
    }
    
    /**
     * Register SVG icon for a CPT
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
}
