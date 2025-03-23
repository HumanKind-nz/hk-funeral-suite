<?php
/**
 * Shared Functions for Custom Post Types
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.4
 * @since      1.3.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Registers a settings submenu link for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */

function hk_fs_register_settings_submenu($post_type) {
 // Use a lower priority (higher number) to make it appear at the bottom
 add_action('admin_menu', function() use ($post_type) {
     add_submenu_page(
         "edit.php?post_type=hk_fs_{$post_type}",
         'HK Funeral Suite Settings',
         'HK Funeral Suite Settings',
         'manage_funeral_settings',
         'options-general.php?page=hk-funeral-suite-settings'
     );
 }, 100); 
}

/**
 * Register price meta field for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_register_price_meta($post_type) {
    // Register REST API field
    register_rest_field(
        "hk_fs_{$post_type}",
        'price',
        array(
            'get_callback' => function($post) use ($post_type) {
                return get_post_meta($post['id'], "_hk_fs_{$post_type}_price", true);
            },
            'schema' => array(
                'type' => 'string',
                'description' => "Price of the {$post_type}"
            )
        )
    );

    // Register meta for REST API access
    register_post_meta("hk_fs_{$post_type}", "_hk_fs_{$post_type}_price", [
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ]);
}

/**
 * Register category taxonomy for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 * @param string $singular Singular label
 * @param string $plural Plural label
 */
function hk_fs_register_category_taxonomy($post_type, $singular, $plural) {
    $labels = array(
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

    $args = array(
        'labels'            => $labels,
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

    register_taxonomy("hk_fs_{$post_type}_category", array("hk_fs_{$post_type}"), $args);
}

/**
 * Add custom title placeholder for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 * @param string $singular Singular name for the placeholder
 */
function hk_fs_register_title_placeholder($post_type, $singular) {
    add_filter('enter_title_here', function($title) use ($post_type, $singular) {
        $screen = get_current_screen();
        
        if ("hk_fs_{$post_type}" == $screen->post_type) {
            $title = "Enter " . strtolower($singular) . " name here";
        }
        
        return $title;
    });
}

/**
 * Register price meta box for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 * @param string $singular Singular label for the CPT
 */
/**
  * Register price meta box for a CPT
  *
  * @param string $post_type The CPT slug without the 'hk_fs_' prefix
  * @param string $singular Singular label for the CPT
  */
 function hk_fs_register_price_metabox($post_type, $singular) {
     // Hook into the add_meta_boxes action
     add_action('add_meta_boxes', function() use ($post_type, $singular) {
         // Only proceed if user has necessary permissions
         if (!current_user_can('manage_funeral_content')) {
             return;
         }
         
         // Define the callback function name
         $callback_name = "hk_fs_{$post_type}_pricing_callback";
         
         // Create the callback function if it doesn't exist
         if (!function_exists($callback_name)) {
             // Define the callback function
             $GLOBALS[$callback_name] = function($post) use ($post_type) {
                 wp_nonce_field("hk_fs_{$post_type}_pricing_nonce", "hk_fs_{$post_type}_pricing_nonce");
                 $price = get_post_meta($post->ID, "_hk_fs_{$post_type}_price", true);
                 $managed_by_sheets = get_option("hk_fs_{$post_type}_price_google_sheets", false);
                 
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
         }
         
         // Now add the meta box using the proper callback
         add_meta_box(
             "hk_fs_{$post_type}_pricing",
             __('Pricing Information', 'hk-funeral-cpt'),
             $GLOBALS[$callback_name],
             "hk_fs_{$post_type}",
             'side',
             'high'
         );
     });
     
     // Register save handler
     add_action("save_post_hk_fs_{$post_type}", function($post_id) use ($post_type) {
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
         $managed_by_sheets = get_option("hk_fs_{$post_type}_price_google_sheets", false);
         
         if (!$managed_by_sheets && isset($_POST["hk_fs_{$post_type}_price"])) {
             $price = sanitize_text_field($_POST["hk_fs_{$post_type}_price"]);
             update_post_meta($post_id, "_hk_fs_{$post_type}_price", $price);
         }
     });
 }

/**
 * Register Google Sheets integration notice for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 * @param string $singular Singular label
 */

 function hk_fs_register_sheets_notice($post_type, $singular) {
     // Use the admin_notices hook instead of direct call
     add_action('admin_notices', function() use ($post_type, $singular) {
         $screen = get_current_screen();
         
         if (!$screen || $screen->post_type !== "hk_fs_{$post_type}") {
             return;
         }
         
         $managed_by_sheets = get_option("hk_fs_{$post_type}_price_google_sheets", false);
         
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
 }

/**
 * Add price column to admin list for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_add_price_column($post_type) {
    // Add price column
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
    
    // Display price in column
    add_action("manage_hk_fs_{$post_type}_posts_custom_column", function($column, $post_id) use ($post_type) {
        if ($column === 'price') {
            $price = get_post_meta($post_id, "_hk_fs_{$post_type}_price", true);
            $managed_by_sheets = get_option("hk_fs_{$post_type}_price_google_sheets", false);
            
            if (!empty($price)) {
                // Check if the price is numeric
                if (is_numeric($price)) {
                    echo '$' . number_format((float)$price, 2);
                } else {
                    // Display the text as-is
                    echo esc_html($price);
                }
                
                // Add icon for Google Sheets managed prices
                if ($managed_by_sheets) {
                    echo ' <span class="dashicons dashicons-cloud" style="color:#0073aa;" title="Managed via Google Sheets"></span>';
                }
            } else {
                echo '—';
            }
        }
    }, 10, 2);
    
    // Make price column sortable
    add_filter("manage_edit-hk_fs_{$post_type}_sortable_columns", function($columns) {
        $columns['price'] = 'price';
        return $columns;
    });
    
    // Add sorting functionality for price column
    add_action('pre_get_posts', function($query) use ($post_type) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') === "hk_fs_{$post_type}" && $query->get('orderby') === 'price') {
            $query->set('meta_key', "_hk_fs_{$post_type}_price");
            $query->set('orderby', 'meta_value_num');
        }
    });
}

/**
 * Register order meta for packages
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_register_order_meta($post_type) {
    // Register order meta for REST API
    register_post_meta("hk_fs_{$post_type}", "_hk_fs_{$post_type}_order", array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'number',
        'auth_callback' => function() {
            return current_user_can('manage_funeral_content');
        }
    ));
}

/**
 * Add order metabox for packages
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
/**
  * Add order metabox for packages
  *
  * @param string $post_type The CPT slug without the 'hk_fs_' prefix
  */
 function hk_fs_register_order_metabox($post_type) {
     // Add order meta box - using hook instead of direct call
     add_action('add_meta_boxes', function() use ($post_type) {
         add_meta_box(
             "hk_fs_{$post_type}_ordering",
             __('Display Order', 'hk-funeral-cpt'),
             "hk_fs_{$post_type}_ordering_callback",
             "hk_fs_{$post_type}",
             'side',
             'high'
         );
     });
     
     // Define callback function
     if (!function_exists("hk_fs_{$post_type}_ordering_callback")) {
         $GLOBALS["hk_fs_{$post_type}_ordering_callback"] = function($post) use ($post_type) {
             wp_nonce_field("hk_fs_{$post_type}_ordering_nonce", "hk_fs_{$post_type}_ordering_nonce");
             
             $order = get_post_meta($post->ID, "_hk_fs_{$post_type}_order", true);
             if (empty($order) && $order !== 0) {
                 $order = 10;
             }
             
             ?>
             <p>
                 <label for="hk_fs_<?php echo $post_type; ?>_order"><?php _e('Display Order:', 'hk-funeral-cpt'); ?></label>
                 <input type="number" id="hk_fs_<?php echo $post_type; ?>_order" name="hk_fs_<?php echo $post_type; ?>_order" 
                       value="<?php echo esc_attr($order); ?>" step="1" min="0" style="width: 100%;">
                 <span class="description"><?php _e('Lower numbers will be displayed first.', 'hk-funeral-cpt'); ?></span>
             </p>
             <?php
         };
     }
     
     // Register save handler for order
     add_action("save_post_hk_fs_{$post_type}", function($post_id) use ($post_type) {
         // Skip autosaves and revisions
         if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
             return;
         }
         
         // Check nonce
         if (isset($_POST["hk_fs_{$post_type}_ordering_nonce"]) && 
             wp_verify_nonce($_POST["hk_fs_{$post_type}_ordering_nonce"], "hk_fs_{$post_type}_ordering_nonce")) {
             
             if (isset($_POST["hk_fs_{$post_type}_order"])) {
                 update_post_meta($post_id, "_hk_fs_{$post_type}_order", 
                     absint($_POST["hk_fs_{$post_type}_order"]));
             }
         }
     });
 }

/**
 * Add order column to admin list
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_add_order_column($post_type) {
    // Add order column
    add_filter("manage_hk_fs_{$post_type}_posts_columns", function($columns) {
        $new_columns = array();
        
        foreach($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'price') {
                $new_columns['order'] = __('Order', 'hk-funeral-cpt');
            }
        }
        
        return $new_columns;
    });
    
    // Display order in column
    add_action("manage_hk_fs_{$post_type}_posts_custom_column", function($column, $post_id) use ($post_type) {
        if ($column === 'order') {
            $order = get_post_meta($post_id, "_hk_fs_{$post_type}_order", true);
            echo !empty($order) ? esc_html($order) : '10';
        }
    }, 10, 2);
    
    // Make order column sortable
    add_filter("manage_edit-hk_fs_{$post_type}_sortable_columns", function($columns) {
        $columns['order'] = 'order';
        return $columns;
    });
    
    // Add sorting functionality for order column
    add_action('pre_get_posts', function($query) use ($post_type) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Default sorting by order
        if ($query->get('post_type') === "hk_fs_{$post_type}" && !$query->get('orderby')) {
            $query->set('meta_key', "_hk_fs_{$post_type}_order");
            $query->set('orderby', 'meta_value_num');
            $query->set('order', 'ASC');
        }
        
        // Explicit sorting by order
        if ($query->get('post_type') === "hk_fs_{$post_type}" && $query->get('orderby') === 'order') {
            $query->set('meta_key', "_hk_fs_{$post_type}_order");
            $query->set('orderby', 'meta_value_num');
        }
    });
}

/**
 * Auto-insert block for a new post
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_register_auto_insert_block($post_type) {
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
}

/**
 * Register block template for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 * @param string $singular Singular label
 */
function hk_fs_register_block_template($post_type, $singular) {
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

/**
 * Add admin styles for Google Sheets integration
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_register_admin_styles($post_type) {
    add_action('admin_head', function() use ($post_type) {
        $screen = get_current_screen();
        
        if (!$screen || $screen->post_type !== "hk_fs_{$post_type}") {
            return;
        }
        
        $managed_by_sheets = get_option("hk_fs_{$post_type}_price_google_sheets", false);
        
        ?>
        <style type="text/css">
            <?php if ($managed_by_sheets): ?>
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
            <?php endif; ?>
        </style>
        <?php
    });
}

/**
 * Generate capability settings for CPT registration
 *
 * @param string $type The CPT type ('post' or 'page')
 * @return array Array of capability mappings
 */
function hk_fs_get_cpt_capabilities($type = 'post') {
    return array(
        'edit_post'          => 'manage_funeral_content',
        'edit_posts'         => 'manage_funeral_content',
        'edit_others_posts'  => 'manage_funeral_content',
        'publish_posts'      => 'manage_funeral_content',
        'read_post'          => 'manage_funeral_content',
        'read_private_posts' => 'manage_funeral_content',
        'delete_post'        => 'manage_funeral_content'
    );
}

/**
 * Check if current user has access to funeral content
 *
 * @return bool Whether the user can access funeral content
 */
function hk_fs_current_user_can_access() {
    return current_user_can('manage_funeral_content') || current_user_can('administrator');
}

/**
 * Restrict CPT admin menu access based on capabilities
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_restrict_admin_menu_access($post_type) {
    add_action('admin_menu', function() use ($post_type) {
        if (!current_user_can('manage_funeral_content')) {
            remove_menu_page("edit.php?post_type=hk_fs_{$post_type}");
        }
    }, 999);
}

/**
 * Generate standard CPT registration args with capability handling
 *
 * @param array $args Basic CPT args including labels, etc.
 * @param string $post_type The post type slug without the 'hk_fs_' prefix
 * @param bool $make_public Whether the CPT should be publicly visible
 * @return array Complete args array for register_post_type()
 */
function hk_fs_generate_cpt_args($args, $post_type, $make_public) {
     // Default args if not specified
     $default_args = array(
         'public'              => true,
         'publicly_queryable'  => $make_public,
         'show_ui'             => true,
         'show_in_menu'        => true,
         'menu_position'       => 5,
         'query_var'           => $make_public,
         'capability_type'     => 'post',
         'capabilities'        => hk_fs_get_cpt_capabilities('post'),
         'has_archive'         => $make_public,
         'hierarchical'        => false,
         'supports'            => array('title', 'editor', 'thumbnail', 'revisions'),
         'show_in_rest'        => true,
         'exclude_from_search' => !$make_public,
         'template_lock'       => false,  // Add this line
     );
     
     // Merge with provided args, with provided args taking precedence
     $merged_args = array_merge($default_args, $args);
     
     // Always ensure capabilities are set correctly
     $merged_args['capabilities'] = hk_fs_get_cpt_capabilities(
         isset($args['capability_type']) ? $args['capability_type'] : 'post'
     );
     
     return $merged_args;
 }

/**
 * Restrict direct admin screen access for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_restrict_admin_screen_access($post_type) {
    add_action('current_screen', function() use ($post_type) {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        // Check if this is our CPT's screen
        if ($screen->post_type === "hk_fs_{$post_type}" && !current_user_can('manage_funeral_content')) {
            wp_redirect(admin_url());
            exit;
        }
    });
}

/**
 * Utility function for block data loading
 * 
 * This function centralizes the block data loading logic for all product-type CPTs.
 * It ensures that JavaScript has the necessary data available on both edit and add new pages.
 *
 * @param string $post_type  The CPT slug without the 'hk_fs_' prefix
 * @param string $script_id  The registered script ID to localize
 * @param string $object_name The JavaScript object name to create
 */
function hk_fs_load_block_data($post_type, $script_id, $object_name) {
     global $post;
     
     // Add debug output
     if (defined('WP_DEBUG') && WP_DEBUG) {
         error_log("Attempting to load block data for {$post_type} - Script ID: {$script_id}");
     }
     
     // Only check if we're in admin
     if (!is_admin()) {
         return;
     }
     
     // Get the current screen to check if we're on the right post type
     $screen = get_current_screen();
     $is_new_post = $screen && $screen->action === 'add' && $screen->post_type === "hk_fs_{$post_type}";
     
     // Default meta values that will be available for both new and existing posts
     $meta_values = array(
         'price' => '',
         'is_price_managed' => get_option("hk_fs_{$post_type}_price_google_sheets", false),
         'selectedCategory' => ''
     );
     
     // Only load post meta if we have a valid existing post
     if (!empty($post) && isset($post->post_type) && $post->post_type === "hk_fs_{$post_type}" && isset($post->ID)) {
         // Get meta values for existing post
         $meta_values['price'] = get_post_meta($post->ID, "_hk_fs_{$post_type}_price", true);
         
         // Get taxonomy terms
         $category_terms = wp_get_object_terms($post->ID, "hk_fs_{$post_type}_category");
         $meta_values['selectedCategory'] = !empty($category_terms) ? $category_terms[0]->term_id : '';
     }
     
     // Check if script is registered
     if (wp_script_is($script_id, 'registered')) {
         wp_localize_script($script_id, $object_name, $meta_values);
         if (defined('WP_DEBUG') && WP_DEBUG) {
             error_log("Successfully localized script {$script_id} with {$object_name}");
         }
     } else {
         error_log("HK Funeral Suite: {$script_id} script not registered when trying to localize data");
     }
 }

/**
 * Register the block data loading function for a CPT
 *
 * @param string $post_type The CPT slug without the 'hk_fs_' prefix
 */
function hk_fs_register_block_data_loader($post_type) {
    $script_id = "hk-fs-{$post_type}-block";
    $object_name = "hkFs" . ucfirst($post_type) . "Data";
    
    add_action('admin_enqueue_scripts', function() use ($post_type, $script_id, $object_name) {
        hk_fs_load_block_data($post_type, $script_id, $object_name);
    });
}
