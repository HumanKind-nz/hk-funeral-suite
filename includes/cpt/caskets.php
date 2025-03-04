<?php
/**
 * Caskets Custom Post Type
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.0  
 * @since      1.0.0
 * @changelog  
 *   1.0.0 - Initial version
 *   - Added casket post type
 *   - Added category taxonomy
 *   - Added price meta field
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Register Caskets Custom Post Type
 */
if (!function_exists('hk_fs_cpt_register_caskets')) {
	function hk_fs_cpt_register_caskets() {
		$labels = array(
			'name'                  => _x('Caskets', 'Post type general name', 'hk-funeral-cpt'),
			'singular_name'         => _x('Casket', 'Post type singular name', 'hk-funeral-cpt'),
			'menu_name'             => _x('HK Caskets', 'Admin Menu text', 'hk-funeral-cpt'),
			'add_new'               => __('Add New', 'hk-funeral-cpt'),
			'add_new_item'          => __('Add New Casket', 'hk-funeral-cpt'),
			'edit_item'             => __('Edit Casket', 'hk-funeral-cpt'),
			'new_item'              => __('New Casket', 'hk-funeral-cpt'),
			'view_item'             => __('View Casket', 'hk-funeral-cpt'),
			'view_items'            => __('View Caskets', 'hk-funeral-cpt'),
			'search_items'          => __('Search Caskets', 'hk-funeral-cpt'),
			'not_found'             => __('No caskets found.', 'hk-funeral-cpt'),
			'not_found_in_trash'    => __('No caskets found in Trash.', 'hk-funeral-cpt'),
			'featured_image'        => __('Casket Image', 'hk-funeral-cpt'),
			'set_featured_image'    => __('Set casket image', 'hk-funeral-cpt'),
			'remove_featured_image' => __('Remove casket image', 'hk-funeral-cpt'),
			'use_featured_image'    => __('Use as casket image', 'hk-funeral-cpt'),
		);
		
		// Get the public setting from options with a default of false
		$make_public = get_option('hk_fs_enable_public_caskets', false);
		
		$args = array(
			'labels'              => $labels,
			'public'              => $make_public,                  // Set public based on option
			'publicly_queryable'  => $make_public,                  // Allow front-end queries only if public
			'show_ui'             => true,                          // Always show admin UI
			'show_in_menu'        => true,                          // Always show in menu
			'menu_position' 	  => 6,
			'query_var'           => $make_public,                  // Allow query vars only if public
			'rewrite'             => $make_public ? array('slug' => 'caskets') : false, // Rewrite only if public
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
			'has_archive'         => $make_public,                  // Archive only if public
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-archive',
			'supports'            => array('title', 'editor', 'thumbnail', 'page-attributes', 'revisions'),
			'show_in_rest'        => true,                          // Keep REST API enabled for Gutenberg
			'exclude_from_search' => !$make_public,                 // Exclude from search if not public
			'template' => array(
				array('hk-funeral-suite/casket'), 
				array('core/paragraph')
			),
			'template_lock' => 'insert',
		);
		
		// Allow theme/plugin overrides
		$args = apply_filters('hk_fs_casket_post_type_args', $args);
		
		register_post_type('hk_fs_casket', $args);
	}
	add_action('init', 'hk_fs_cpt_register_caskets', 0);
}

function hk_add_settings_link_to_casket_menu() {
	add_submenu_page(
		'edit.php?post_type=hk_fs_casket', // CPT menu slug
		'HK Funeral Suite Settings', // Page title
		'HK Funeral Suite Settings', // Menu title
		'manage_funeral_settings', // Capability
		'options-general.php?page=hk-funeral-suite-settings' // Link to settings
	);
}
add_action('admin_menu', 'hk_add_settings_link_to_casket_menu');

/**
 * Register Caskets Category Taxonomy
 */
if (!function_exists('hk_fs_tax_register_casket_categories')) {
	function hk_fs_tax_register_casket_categories() {
		$labels = array(
			'name'              => _x('Casket Categories', 'taxonomy general name', 'hk-funeral-cpt'),
			'singular_name'     => _x('Casket Category', 'taxonomy singular name', 'hk-funeral-cpt'),
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
			'rewrite'           => array('slug' => 'casket-category'),
		);

		register_taxonomy('hk_fs_casket_category', array('hk_fs_casket'), $args);
	}
	add_action('init', 'hk_fs_tax_register_casket_categories', 0);
}

/**
 * Register REST API fields
 */
 
 /**
  * Register meta fields for REST API access
  */
 // For caskets
 function hk_fs_register_casket_meta_fields() {
	 register_post_meta('hk_fs_casket', '_hk_fs_casket_price', [
		 'show_in_rest' => true,
		 'single' => true,
		 'type' => 'string',
		 'auth_callback' => function() {
			 return current_user_can('edit_posts');
		 }
	 ]);
 }
 add_action('init', 'hk_fs_register_casket_meta_fields');
 
function hk_fs_register_casket_meta() {
	register_rest_field(
		'hk_fs_casket',
		'price',
		array(
			'get_callback' => function($post) {
				return get_post_meta($post['id'], '_hk_fs_casket_price', true);
			},
			'schema' => array(
				'type' => 'string',
				'description' => 'Price of the casket'
			)
		)
	);
}
add_action('rest_api_init', 'hk_fs_register_casket_meta');

/**
 * Custom icon for Caskets CPT
 * 
 * This is a modular approach - we add this hook only for this CPT
 * instead of a single function for all CPTs
 */
function hk_fs_casket_admin_style() {
	?>
	<style>
		/* Casket Icon */
		#adminmenu .menu-icon-hk_fs_casket div.wp-menu-image::before {
			content: '';
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="%23a7aaad" d="M406.5 115.2l-107.8-105.9C292.6 3.375 284.3 0 275.6 0H172.4C163.7 0 155.4 3.375 149.2 9.375L41.46 115.2c-8.002 7.875-11.25 19.38-8.502 30.38l87.14 342.1C123.7 502 136.7 512 151.7 512h144.7c14.88 0 27.88-9.1 31.51-24.25l87.14-342.1C417.8 134.6 414.5 123.1 406.5 115.2zM284.5 464H163.5l-81.64-321.1L178.5 48h91.02l96.64 94.88L284.5 464z"/></svg>');
			background-repeat: no-repeat;
			background-position: center;
			background-size: 20px;
			opacity: 0.6;
		}
		
		/* Hover state */
		#adminmenu .menu-icon-hk_fs_casket:hover div.wp-menu-image::before,
		#adminmenu .menu-icon-hk_fs_casket.current div.wp-menu-image::before {
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="%23ffffff" d="M406.5 115.2l-107.8-105.9C292.6 3.375 284.3 0 275.6 0H172.4C163.7 0 155.4 3.375 149.2 9.375L41.46 115.2c-8.002 7.875-11.25 19.38-8.502 30.38l87.14 342.1C123.7 502 136.7 512 151.7 512h144.7c14.88 0 27.88-9.1 31.51-24.25l87.14-342.1C417.8 134.6 414.5 123.1 406.5 115.2zM284.5 464H163.5l-81.64-321.1L178.5 48h91.02l96.64 94.88L284.5 464z"/></svg>');
			opacity: 1;
		}
	</style>
	<?php
}
add_action('admin_head', 'hk_fs_casket_admin_style');

/**
 * Customize the title placeholder for Casket post type
 */
if (!function_exists('hk_fs_casket_change_title_text')) {
	function hk_fs_casket_change_title_text($title) {
		$screen = get_current_screen();
		
		if ('hk_fs_casket' == $screen->post_type) {
			$title = 'Enter casket name here';
		}
		
		return $title;
	}
	add_filter('enter_title_here', 'hk_fs_casket_change_title_text');
}

/**
 * Add a custom meta box for casket pricing information
 */
function hk_fs_add_casket_meta_boxes() {
	if (!current_user_can('manage_funeral_content')) {
		return;
	}
	
	add_meta_box(
		'hk_fs_casket_pricing',
		__('Pricing Information', 'hk-funeral-cpt'),
		'hk_fs_casket_pricing_callback',
		'hk_fs_casket',
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'hk_fs_add_casket_meta_boxes');

/**
 * Meta box callback function
 */
function hk_fs_casket_pricing_callback($post) {
	wp_nonce_field('hk_fs_casket_pricing_nonce', 'hk_fs_casket_pricing_nonce');
	$price = get_post_meta($post->ID, '_hk_fs_casket_price', true);
	?>
	<p>
		<label for="hk_fs_casket_price"><?php _e('Price ($):', 'hk-funeral-cpt'); ?></label>
		<input type="number" id="hk_fs_casket_price" name="hk_fs_casket_price" 
			   value="<?php echo esc_attr($price); ?>" step="0.01" min="0" style="width: 100%;">
	</p>
	<?php
}

/**
 * Save the meta box data
 */
function hk_fs_save_casket_meta($post_id) {
	if (!current_user_can('manage_funeral_content')) {
		return;
	}

	if (!isset($_POST['hk_fs_casket_pricing_nonce']) || 
		!wp_verify_nonce($_POST['hk_fs_casket_pricing_nonce'], 'hk_fs_casket_pricing_nonce')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (isset($_POST['hk_fs_casket_price'])) {
		$price = sanitize_text_field($_POST['hk_fs_casket_price']);
		update_post_meta($post_id, '_hk_fs_casket_price', $price);
	}
}
add_action('save_post_hk_fs_casket', 'hk_fs_save_casket_meta');

/**
 * Add custom columns to casket admin list
 */
function hk_fs_add_casket_columns($columns) {
	$new_columns = array();
	
	foreach($columns as $key => $value) {
		$new_columns[$key] = $value;
		if ($key === 'title') {
			$new_columns['price'] = __('Price', 'hk-funeral-cpt');
		}
	}
	
	return $new_columns;
}
add_filter('manage_hk_fs_casket_posts_columns', 'hk_fs_add_casket_columns');

/**
 * Display price data in the custom column
 */
function hk_fs_display_casket_columns($column, $post_id) {
	if ($column === 'price') {
		$price = get_post_meta($post_id, '_hk_fs_casket_price', true);
		if (!empty($price)) {
			echo '$' . number_format((float)$price, 2);
		} else {
			echo '—';
		}
	}
}
add_action('manage_hk_fs_casket_posts_custom_column', 'hk_fs_display_casket_columns', 10, 2);

/**
 * Make the price column sortable
 */
function hk_fs_sortable_casket_columns($columns) {
	$columns['price'] = 'price';
	return $columns;
}
add_filter('manage_edit-hk_fs_casket_sortable_columns', 'hk_fs_sortable_casket_columns');

/**
 * Add sorting functionality to price column
 */
function hk_fs_casket_orderby($query) {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}
	
	if ($query->get('post_type') === 'hk_fs_casket' && $query->get('orderby') === 'price') {
		$query->set('meta_key', '_hk_fs_casket_price');
		$query->set('orderby', 'meta_value_num');
	}
}
add_action('pre_get_posts', 'hk_fs_casket_orderby');

/**
 * Auto-insert the Casket block into new casket posts
 */
function hk_fs_auto_insert_casket_block($post_id, $post = null, $update = false) {
	// If we only got the ID, get the post object
	if (!is_object($post)) {
		$post = get_post($post_id);
	}
	
	// Only proceed for our custom post type and new posts
	if ($post->post_type !== 'hk_fs_casket' || $post->post_content !== '') {
		return;
	}
	
	// Create block content
	$block_content = '<!-- wp:hk-funeral-suite/casket /-->';
	
	// Update the post with our block
	wp_update_post(array(
		'ID' => $post->ID,
		'post_content' => $block_content,
	));
}
add_action('wp_insert_post', 'hk_fs_auto_insert_casket_block', 10, 3);

/**
 * Add a template for the Casket post type
 */
function hk_fs_register_casket_template() {
	$post_type_object = get_post_type_object('hk_fs_casket');
	
	if ($post_type_object) {
		$post_type_object->template = array(
			array('hk-funeral-suite/casket'),
			array('core/paragraph', array(
				'placeholder' => __('Add casket description...', 'hk-funeral-cpt')
			))
		);
		
		// Lock the template so users can't move or delete the block
		$post_type_object->template_lock = 'insert';
	}
}
add_action('init', 'hk_fs_register_casket_template', 11); // Run after CPT registration