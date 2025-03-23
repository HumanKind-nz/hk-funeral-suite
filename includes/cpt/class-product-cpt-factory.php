<?php
/**
 * Factory for creating product-type custom post types
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
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
        $sheets_option = "hk_fs_{$post_type}_price_google_sheets";
        
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
            
            $args = array(
                'labels'              => $labels,
                'public'              => true,
                'publicly_queryable'  => $make_public,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'menu_position'       => 6,
                'query_var'           => $make_public,
                'rewrite'             => $make_public ? array('slug' => $slug) : false,
                'capability_type'     => 'post',
                'capabilities'        => array(
                    'edit_post'          => 'manage_funeral_content',
                    'edit_posts'         => 'manage_funeral_content',
                    'edit_others_posts'  => 'manage_funeral_content',
                    'publish_posts'      => 'manage_funeral_content',
                    'read_post'          => 'manage_funeral_content',
                    'read_private_posts' => 'manage_funeral_content',
                    'delete_post'        => 'manage_funeral_content'
                ),
                'has_archive'         => $make_public,
                'hierarchical'        => false,
                'menu_icon'           => $icon,
                'supports'            => array('title', 'editor', 'thumbnail', 'page-attributes', 'revisions'),
                'show_in_rest'        => true,
                'exclude_from_search' => !$make_public,
                'template'            => array(
                    array("hk-funeral-suite/{$post_type}"),
                    array('core/paragraph')
                ),
                'template_lock'       => false,
            );
            
            $args = apply_filters("hk_fs_{$post_type}_post_type_args", $args);
            
            register_post_type("hk_fs_{$post_type}", $args);
        }, 0);
        
        // Register taxonomy
        add_action('init', function() use ($post_type, $singular, $plural) {
            $tax_labels = array(
                'name'              => _x("{$singular} Categories", 'taxonomy general name', 'hk-funeral-cpt'),
                'singular_name'     => _x("{$singular} Category", 'taxonomy singular name', 'hk-funeral-cpt'),
                'menu_name'         => __('Categories', 'hk-funeral-cpt'),
                'all_items'         => __('All Categories', 'hk-funeral-cpt'),
                'edit_item'         => __('Edit Category', 'hk-funeral-cpt'),
                'update_item'       => __('Update Category', 'hk-funeral-cpt'),
                'add_new_item'      => __('Add New Category', 'hk-funeral-cpt'),
                'new_item_name'     => __('New Category Name', 'hk-funeral-cpt'),
                'search_items'      => __('Search Categories', 'hk-funeral-cpt'),
                'parent_item'       => __('Parent Category', 'hk-funeral-cpt'),
                'parent_item_colon' => __('Parent Category:', 'hk-funeral-cpt'),
            );
            
            $tax_args = array(
                'labels'            => $tax_labels,
                'hierarchical'      => true,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'show_tagcloud'     => true,
                'show_in_rest'      => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => strtolower($post_type) . '-category'),
            );
            
            register_taxonomy("hk_fs_{$post_type}_category", array("hk_fs_{$post_type}"), $tax_args);
        }, 0);
        
        // Add settings link
        add_action('admin_menu', function() use ($post_type) {
            add_submenu_page(
                "edit.php?post_type=hk_fs_{$post_type}",
                'HK Funeral Suite Settings',
                'HK Funeral Suite Settings',
                'manage_funeral_settings',
                'options-general.php?page=hk-funeral-suite-settings'
            );
        });
        
        // Add custom icon style if SVG provided
        if ($svg_icon) {
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
                    
                    /* Google Sheets integration styles */
                    #hk_fs_<?php echo $post_type; ?>_price[disabled] {
                        background-color: #f0f0f1;
                        border-color: #dcdcde;
                        color: #8c8f94;
                        box-shadow: none;
                    }
                    
                    .price-field-container.sheet-managed {
                        position: relative;
                    }
                    
                    .sheet-integration-notice {
                        background-color: rgba(214, 54, 56, 0.05);
                        border-left: 4px solid #d63638;
                        padding: 8px;
                        margin-top: 10px;
                        border-radius: 2px;
                    }
                </style>
                <?php
            });
        }
        
        // Change title placeholder
        add_filter('enter_title_here', function($title) use ($post_type, $singular) {
            $screen = get_current_screen();
            
            if ('hk_fs_' . $post_type == $screen->post_type) {
                $title = 'Enter ' . strtolower($singular) . ' name here';
            }
            
            return $title;
        });
        
        // Add meta box for pricing
        add_action('add_meta_boxes', function() use ($post_type, $singular) {
            if (!current_user_can('manage_funeral_content')) {
                return;
            }
            
            add_meta_box(
                "hk_fs_{$post_type}_pricing",
                __('Pricing Information', 'hk-funeral-cpt'),
                "hk_fs_{$post_type}_pricing_callback",
                "hk_fs_{$post_type}",
                'side',
                'high'
            );
        });
        
        // Define meta box callback
        if (!function_exists("hk_fs_{$post_type}_pricing_callback")) {
            $callback_function = function($post) use ($post_type, $sheets_option) {
                wp_nonce_field("hk_fs_{$post_type}_pricing_nonce", "hk_fs_{$post_type}_pricing_nonce");
                $price = get_post_meta($post->ID, "_hk_fs_{$post_type}_price", true);
                $managed_by_sheets = get_option($sheets_option, false);
                
                ?>
                <div class="price-field-container <?php echo $managed_by_sheets ? 'sheet-managed' : ''; ?>">
                    <p>
                        <label for="hk_fs_<?php echo $post_type; ?>_price"><?php _e('Price ($):', 'hk-funeral-cpt'); ?></label>
                        <input type="number" id="hk_fs_<?php echo $post_type; ?>_price" name="hk_fs_<?php echo $post_type; ?>_price" 
                               value="<?php echo esc_attr($price); ?>" step="0.01" min="0" style="width: 100%;"
                               <?php echo $managed_by_sheets ? 'disabled="disabled"' : ''; ?>>
                    </p>
                    
                    <?php if ($managed_by_sheets): ?>
                    <div class="sheet-integration-notice">
                        <p style="color: #d63638; margin-top: 8px; display: flex; align-items: center;">
                            <span class="dashicons dashicons-cloud" style="margin-right: 5px;"></span>
                            <strong><?php _e('Managed via Google Sheets', 'hk-funeral-cpt'); ?></strong>
                        </p>
                        <p class="description" style="margin-top: 5px;">
                            <?php _e('Price is managed through Google Sheets integration and cannot be modified here.', 'hk-funeral-cpt'); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
            };
            
            // Register the callback function with a dynamic name
            $function_name = "hk_fs_{$post_type}_pricing_callback";
            if (!function_exists($function_name)) {
                $GLOBALS[$function_name] = $callback_function;
                $GLOBALS['wp_filter'][$function_name] = array();
                add_action($function_name, $GLOBALS[$function_name]);
            }
        }
        
        // Save meta data
        add_action("save_post_hk_fs_{$post_type}", function($post_id) use ($post_type, $sheets_option) {
            if (!current_user_can('manage_funeral_content')) {
                return;
            }
            
            if (!isset($_POST["hk_fs_{$post_type}_pricing_nonce"]) || 
                !wp_verify_nonce($_POST["hk_fs_{$post_type}_pricing_nonce"], "hk_fs_{$post_type}_pricing_nonce")) {
                return;
            }
            
            // Skip autosaves and revisions
            if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
                return;
            }
            
            // Only update price if not managed by Google Sheets
            $managed_by_sheets = get_option($sheets_option, false);
            
            if (!$managed_by_sheets && isset($_POST["hk_fs_{$post_type}_price"])) {
                $price = sanitize_text_field($_POST["hk_fs_{$post_type}_price"]);
                update_post_meta($post_id, "_hk_fs_{$post_type}_price", $price);
            }
        });
        
        // Add admin notice for Google Sheets
        add_action('admin_notices', function() use ($post_type, $sheets_option, $singular) {
            $screen = get_current_screen();
            
            if (!$screen || $screen->post_type !== "hk_fs_{$post_type}") {
                return;
            }
            
            $managed_by_sheets = get_option($sheets_option, false);
            
            if ($managed_by_sheets) {
                ?>
                <div class="notice notice-info">
                    <p>
                        <span class="dashicons dashicons-cloud" style="color:#0073aa; font-size:18px; vertical-align:middle;"></span>
                        <strong><?php _e('Google Sheets Integration Active:', 'hk-funeral-cpt'); ?></strong>
                        <?php printf(
                            __('%s pricing is currently managed via Google Sheets. Price fields are disabled in the admin interface.', 'hk-funeral-cpt'),
                            $singular
                        ); ?>
                        <a href="<?php echo admin_url('options-general.php?page=hk-funeral-suite-settings'); ?>">
                            <?php _e('Change this setting', 'hk-funeral-cpt'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        });
        
        // Custom admin columns
        add_filter("manage_hk_fs_{$post_type}_posts_columns", function($columns) {
            $new_columns = array();
            
            foreach($columns as $key => $value) {
                $new_columns[$key] = $value;
                if ($key === 'title') {
                    $new_columns['price'] = __('Price', 'hk-funeral-cpt');
                }
            }
            
            return $new_columns;
        });
        
        // Display column content
        add_action("manage_hk_fs_{$post_type}_posts_custom_column", function($column, $post_id) use ($post_type, $sheets_option) {
            if ($column === 'price') {
                $price = get_post_meta($post_id, "_hk_fs_{$post_type}_price", true);
                $managed_by_sheets = get_option($sheets_option, false);
                
                if (!empty($price)) {
                    echo '$' . number_format((float)$price, 2);
                    
                    // Add icon for Google Sheets managed prices
                    if ($managed_by_sheets) {
                        echo ' <span class="dashicons dashicons-cloud" style="color:#0073aa;" title="Managed via Google Sheets"></span>';
                    }
                } else {
                    echo '—';
                }
            }
        }, 10, 2);
        
        // Make columns sortable
        add_filter("manage_edit-hk_fs_{$post_type}_sortable_columns", function($columns) {
            $columns['price'] = 'price';
            return $columns;
        });
        
        // Add sorting functionality
        add_action('pre_get_posts', function($query) use ($post_type) {
            if (!is_admin() || !$query->is_main_query()) {
                return;
            }
            
            if ($query->get('post_type') === "hk_fs_{$post_type}" && $query->get('orderby') === 'price') {
                $query->set('meta_key', "_hk_fs_{$post_type}_price");
                $query->set('orderby', 'meta_value_num');
            }
        });
        
        // Auto-insert block for new posts
        add_action('wp_insert_post', function($post_id, $post = null, $update = false) use ($post_type) {
            // If we only got the ID, get the post object
            if (!is_object($post)) {
                $post = get_post($post_id);
            }
            
            // Only proceed for our custom post type and new posts
            if ($post->post_type !== "hk_fs_{$post_type}" || $post->post_content !== '') {
                return;
            }
            
            // Create block content
            $block_content = "<!-- wp:hk-funeral-suite/{$post_type} /-->";
            
            // Update the post with our block
            wp_update_post(array(
                'ID' => $post->ID,
                'post_content' => $block_content,
            ));
        }, 10, 3);
        
        // Register post type template
        add_action('init', function() use ($post_type, $singular) {
            $post_type_object = get_post_type_object("hk_fs_{$post_type}");
            
            if ($post_type_object) {
                $post_type_object->template = array(
                    array("hk-funeral-suite/{$post_type}"),
                    array('core/paragraph', array(
                        'placeholder' => __("Add {$singular} description...", 'hk-funeral-cpt')
                    ))
                );
                
                // Allow adding/removing other blocks
                $post_type_object->template_lock = false;
            }
        }, 11); // Run after CPT registration
    }
}
