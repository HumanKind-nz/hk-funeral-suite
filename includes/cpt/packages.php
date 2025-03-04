<?php
/**
 * Funeral Pricing Packages Custom Post Type
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.0  
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Usage Notes:
 * 
 * 1. Package Ordering:
 *    To query packages in the correct order in your templates:
 *    $args = array(
 *        'post_type' => 'hk_fs_package',
 *        'meta_key' => '_hk_fs_package_order',
 *        'orderby' => 'meta_value_num',
 *        'order' => 'ASC',
 *        'posts_per_page' => -1
 *    );
 *    $packages = new WP_Query($args);
 * 
 * 2. REST API Access:
 *    - Packages are available at: /wp-json/wp/v2/hk_fs_package
 *    - Price and order fields are registered to show in REST API
 *    - Example: /wp-json/wp/v2/hk_fs_package?orderby=meta_value_num&meta_key=_hk_fs_package_order
 */

/**
  * Register Packages Custom Post Type
  */
 if (!function_exists('hk_fs_cpt_register_packages')) {
	 function hk_fs_cpt_register_packages() {
		 $labels = array(
			 'name'                  => _x('Pricing Packages', 'Post type general name', 'hk-funeral-cpt'),
			 'singular_name'         => _x('Package', 'Post type singular name', 'hk-funeral-cpt'),
			 'menu_name'             => _x('HK Pricing Packages', 'Admin Menu text', 'hk-funeral-cpt'),
			 'name_admin_bar'        => _x('Pricing Package', 'Add New on Toolbar', 'hk-funeral-cpt'),
			 'add_new'               => __('Add Pricing Package', 'hk-funeral-cpt'),
			 'add_new_item'          => __('Add New Pricing Package', 'hk-funeral-cpt'),
			 'new_item'              => __('New Pricing Package', 'hk-funeral-cpt'),
			 'edit_item'             => __('Edit Pricing Package', 'hk-funeral-cpt'),
			 'view_item'             => __('View Pricing Package', 'hk-funeral-cpt'),
			 'all_items'             => __('Pricing Packages', 'hk-funeral-cpt'),
			 'search_items'          => __('Search Pricing Packages', 'hk-funeral-cpt'),
			 'not_found'             => __('No packages found.', 'hk-funeral-cpt'),
			 'not_found_in_trash'    => __('No packages found in Trash.', 'hk-funeral-cpt'),
			 'featured_image'        => __('Package Image', 'hk-funeral-cpt'),
			 'set_featured_image'    => __('Set package image', 'hk-funeral-cpt'),
			 'remove_featured_image' => __('Remove package image', 'hk-funeral-cpt'),
			 'use_featured_image'    => __('Use as package image', 'hk-funeral-cpt'),
		 );
		 
		 // Get the public setting from options with a default of false
		 $make_public = get_option('hk_fs_enable_public_packages', false);
		 
		 $args = array(
			 'labels'              => $labels,
			 'public'              => $make_public,                  // Set public based on option
			 'publicly_queryable'  => $make_public,                  // Allow front-end queries only if public
			 'show_ui'             => true,                          // Always show admin UI
			 'show_in_menu'        => true,                          // Always show in menu
			 'menu_position'       => 6,
			 'query_var'           => $make_public,                  // Allow query vars only if public
			 'rewrite'             => $make_public ? array('slug' => 'funeral-packages') : false, // Rewrite only if public
			 'capability_type'     => 'page',
			 'capabilities'        => array(
				 'edit_post'          => 'manage_funeral_content',
				 'edit_posts'         => 'manage_funeral_content',
				 'edit_others_posts'  => 'manage_funeral_content',
				 'publish_posts'      => 'manage_funeral_content',
				 'read_post'          => 'manage_funeral_content',
				 'read_private_posts' => 'manage_funeral_content',
				 'delete_post'        => 'manage_funeral_content'
			 ),
			 'has_archive'         => $make_public,                  // Archive only if public
			 'hierarchical'        => false,
			 'menu_icon'           => 'dashicons-money-alt',
			 'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
			 'show_in_rest'        => true,                          // Keep REST API enabled for Gutenberg
			 'exclude_from_search' => !$make_public,                 // Exclude from search if not public
			 'template' => array(
				 array('hk-funeral-suite/pricing-package'),
				 array('core/paragraph')
			 ),
			 'template_lock' => 'insert',
		 );
		 
		 // Allow theme/plugin overrides
		 $args = apply_filters('hk_fs_package_post_type_args', $args);
		 
		 register_post_type('hk_fs_package', $args);
	 }
	 add_action('init', 'hk_fs_cpt_register_packages', 0);
 }

/**
 * Register meta fields for REST API
 */
function hk_fs_register_meta_fields() {
	register_post_meta('hk_fs_package', '_hk_fs_package_price', array(
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
		'auth_callback' => function() {
			return current_user_can('manage_funeral_content');
		}
	));
	
	register_post_meta('hk_fs_package', '_hk_fs_package_order', array(
		'show_in_rest' => true,
		'single' => true,
		'type' => 'number',
		'auth_callback' => function() {
			return current_user_can('manage_funeral_content');
		}
	));
}
add_action('init', 'hk_fs_register_meta_fields');

/**
 * Add settings link to package menu
 */
function hk_add_settings_link_to_package_menu() {
	add_submenu_page(
		'edit.php?post_type=hk_fs_package',
		'HK Funeral Suite Settings',
		'HK Funeral Suite Settings',
		'manage_funeral_settings',
		'options-general.php?page=hk-funeral-suite-settings'
	);
}
add_action('admin_menu', 'hk_add_settings_link_to_package_menu');

/**
 * Customize the title placeholder
 */
function hk_fs_package_change_title_text($title) {
	$screen = get_current_screen();
	if ('hk_fs_package' == $screen->post_type) {
		$title = 'Enter package name here';
	}
	return $title;
}
add_filter('enter_title_here', 'hk_fs_package_change_title_text');

/**
 * Add custom meta boxes
 */
function hk_fs_add_package_meta_boxes() {
	add_meta_box(
		'hk_fs_package_pricing',
		__('Price Information', 'hk-funeral-cpt'),
		'hk_fs_package_pricing_callback',
		'hk_fs_package',
		'side',
		'high'
	);
	
	add_meta_box(
		'hk_fs_package_ordering',
		__('Display Order', 'hk-funeral-cpt'),
		'hk_fs_package_ordering_callback',
		'hk_fs_package',
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'hk_fs_add_package_meta_boxes');

/**
 * Pricing meta box callback
 */
function hk_fs_package_pricing_callback($post) {
	wp_nonce_field('hk_fs_package_pricing_nonce', 'hk_fs_package_pricing_nonce');
	
	$price = get_post_meta($post->ID, '_hk_fs_package_price', true);
	
	?>
	<p>
		<label for="hk_fs_package_price"><?php _e('Price ($):', 'hk-funeral-cpt'); ?></label>
		<input type="number" id="hk_fs_package_price" name="hk_fs_package_price" 
			   value="<?php echo esc_attr($price); ?>" step="0.01" min="0" style="width: 100%;">
	</p>
	<?php
}

/**
 * Order meta box callback
 */
function hk_fs_package_ordering_callback($post) {
	wp_nonce_field('hk_fs_package_ordering_nonce', 'hk_fs_package_ordering_nonce');
	
	$order = get_post_meta($post->ID, '_hk_fs_package_order', true);
	if (empty($order) && $order !== 0) {
		$order = 10;
	}
	
	?>
	<p>
		<label for="hk_fs_package_order"><?php _e('Display Order:', 'hk-funeral-cpt'); ?></label>
		<input type="number" id="hk_fs_package_order" name="hk_fs_package_order" 
			   value="<?php echo esc_attr($order); ?>" step="1" min="0" style="width: 100%;">
		<span class="description"><?php _e('Lower numbers will be displayed first.', 'hk-funeral-cpt'); ?></span>
	</p>
	<?php
}

/**
 * Save the meta box data
 */
function hk_fs_save_package_meta($post_id) {
	// Check pricing nonce
	if (isset($_POST['hk_fs_package_pricing_nonce']) && 
		wp_verify_nonce($_POST['hk_fs_package_pricing_nonce'], 'hk_fs_package_pricing_nonce')) {
		
		if (isset($_POST['hk_fs_package_price'])) {
			update_post_meta($post_id, '_hk_fs_package_price', 
				sanitize_text_field($_POST['hk_fs_package_price']));
		}
	}
	
	// Check ordering nonce
	if (isset($_POST['hk_fs_package_ordering_nonce']) && 
		wp_verify_nonce($_POST['hk_fs_package_ordering_nonce'], 'hk_fs_package_ordering_nonce')) {
		
		if (isset($_POST['hk_fs_package_order'])) {
			update_post_meta($post_id, '_hk_fs_package_order', 
				absint($_POST['hk_fs_package_order']));
		}
	}
}
add_action('save_post_hk_fs_package', 'hk_fs_save_package_meta');

/**
 * Add custom columns to admin list
 */
function hk_fs_add_package_columns($columns) {
	$new_columns = array();
	
	foreach($columns as $key => $value) {
		if ($key === 'title') {
			$new_columns[$key] = $value;
			$new_columns['price'] = __('Price', 'hk-funeral-cpt');
			$new_columns['order'] = __('Order', 'hk-funeral-cpt');
		} else {
			$new_columns[$key] = $value;
		}
	}
	
	return $new_columns;
}
add_filter('manage_hk_fs_package_posts_columns', 'hk_fs_add_package_columns');

/**
 * Display package data in custom columns
 */
function hk_fs_display_package_columns($column, $post_id) {
	if ($column === 'price') {
		$price = get_post_meta($post_id, '_hk_fs_package_price', true);
		
		if (!empty($price)) {
			echo '$' . number_format((float)$price, 2);
		} else {
			echo '—';
		}
	}
	
	if ($column === 'order') {
		$order = get_post_meta($post_id, '_hk_fs_package_order', true);
		echo !empty($order) ? esc_html($order) : '10';
	}
}
add_action('manage_hk_fs_package_posts_custom_column', 'hk_fs_display_package_columns', 10, 2);

/**
 * Make columns sortable
 */
function hk_fs_sortable_package_columns($columns) {
	$columns['price'] = 'price';
	$columns['order'] = 'order';
	return $columns;
}
add_filter('manage_edit-hk_fs_package_sortable_columns', 'hk_fs_sortable_package_columns');

/**
 * Add custom sorting
 */
function hk_fs_package_orderby($query) {
	if (!$query->is_main_query()) {
		return;
	}
	
	if ($query->get('post_type') === 'hk_fs_package') {
		if (is_admin()) {
			// In admin, default sort by menu order
			if (!$query->get('orderby')) {
				$query->set('meta_key', '_hk_fs_package_order');
				$query->set('orderby', 'meta_value_num');
				$query->set('order', 'ASC');
			}
		} else {
			// On frontend, default sort by price
			if (!$query->get('orderby')) {
				$query->set('meta_key', '_hk_fs_package_price');
				$query->set('orderby', 'meta_value_num');
				$query->set('order', 'ASC');
			}
		}
		
		// Handle explicit sorting requests
		if ($query->get('orderby') === 'price') {
			$query->set('meta_key', '_hk_fs_package_price');
			$query->set('orderby', 'meta_value_num');
		} elseif ($query->get('orderby') === 'order') {
			$query->set('meta_key', '_hk_fs_package_order');
			$query->set('orderby', 'meta_value_num');
		}
	}
}
add_action('pre_get_posts', 'hk_fs_package_orderby');

/**
 * Auto-insert the Pricing Package block into new package posts
 */
function hk_fs_auto_insert_pricing_package_block($post_id, $post = null, $update = false) {
	// If we only got the ID, get the post object
	if (!is_object($post)) {
		$post = get_post($post_id);
	}
	
	// Only proceed for our custom post type and new posts
	if ($post->post_type !== 'hk_fs_package' || $post->post_content !== '') {
		return;
	}
	
	// Create block content
	$block_content = '<!-- wp:hk-funeral-suite/pricing-package /-->';
	
	// Update the post with our block
	wp_update_post(array(
		'ID' => $post->ID,
		'post_content' => $block_content,
	));
}
add_action('wp_insert_post', 'hk_fs_auto_insert_pricing_package_block', 10, 3);


function hk_fs_register_pricing_package_template() {
	$post_type_object = get_post_type_object('hk_fs_package');
	
	if ($post_type_object) {
		$post_type_object->template = array(
			array('hk-funeral-suite/pricing-package'),
			array('core/paragraph', array(
				'placeholder' => __('Add package description...', 'hk-funeral-cpt')
			))
		);
		
		// Lock the template so users can't move or delete the block
		$post_type_object->template_lock = 'insert';
	}
}
add_action('init', 'hk_fs_register_pricing_package_template', 11); // Run after CPT registration