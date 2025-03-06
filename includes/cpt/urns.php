<?php
/**
 * Urns Custom Post Type
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.4  
 * @since      1.0.0
 * @changelog
 *   1.0.4 - Visibility public change
 *   1.0.3 - Google sheet / pricing sync
 *   1.0.0 - Initial version
 *   - Added urns post type
 *   - Added category taxonomy 
 *   - Added price meta field
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Register Urns Custom Post Type
 */
if (!function_exists('hk_fs_cpt_register_urns')) {
	function hk_fs_cpt_register_urns() {
		$labels = array(
			'name'                  => _x('Urns', 'Post type general name', 'hk-funeral-cpt'),
			'singular_name'         => _x('Urn', 'Post type singular name', 'hk-funeral-cpt'),
			'menu_name'             => _x('HK Urns', 'Admin Menu text', 'hk-funeral-cpt'),
			'add_new'               => __('Add New', 'hk-funeral-cpt'),
			'add_new_item'          => __('Add New Urn', 'hk-funeral-cpt'),
			'edit_item'             => __('Edit Urn', 'hk-funeral-cpt'),
			'new_item'              => __('New Urn', 'hk-funeral-cpt'),
			'view_item'             => __('View Urn', 'hk-funeral-cpt'),
			'view_items'            => __('View Urns', 'hk-funeral-cpt'),
			'search_items'          => __('Search Urns', 'hk-funeral-cpt'),
			'not_found'             => __('No urns found.', 'hk-funeral-cpt'),
			'not_found_in_trash'    => __('No urns found in Trash.', 'hk-funeral-cpt'),
			'featured_image'        => __('Urn Image', 'hk-funeral-cpt'),
			'set_featured_image'    => __('Set urn image', 'hk-funeral-cpt'),
			'remove_featured_image' => __('Remove urn image', 'hk-funeral-cpt'),
			'use_featured_image'    => __('Use as urn image', 'hk-funeral-cpt'),
		);

		// Get the public setting from options with a default of false
		$make_public = get_option('hk_fs_enable_public_urns', false);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => $make_public,             // Allow front-end queries only if public
			'show_ui'             => true,                     // Always show admin UI
			'show_in_menu'        => true,                     // Always show in menu
			'menu_position' 	  => 6,
			'query_var'           => $make_public,             // Allow query vars only if public
			'rewrite'             => $make_public ? array('slug' => 'urns') : false, // Rewrite only if public
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
			'has_archive'         => $make_public,             // Archive only if public
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-art',
			'supports'            => array('title', 'editor', 'thumbnail', 'page-attributes', 'revisions'),
			'show_in_rest'        => true,                     // Keep REST API enabled for Gutenberg
			'exclude_from_search' => !$make_public,            // Exclude from search if not public
			'template' => array(
				array('hk-funeral-suite/urn'),
				array('core/paragraph')
			),
			'template_lock' => 'insert',
		);

		// Allow theme/plugin overrides
		$args = apply_filters('hk_fs_urn_post_type_args', $args);

		register_post_type('hk_fs_urn', $args);
	}
	add_action('init', 'hk_fs_cpt_register_urns', 0);
}


function hk_add_settings_link_to_urn_menu() {
	add_submenu_page(
		'edit.php?post_type=hk_fs_urn', // CPT menu slug
		'HK Funeral Suite Settings', // Page title
		'HK Funeral Suite Settings', // Menu title
		'manage_funeral_settings', // Capability
		'options-general.php?page=hk-funeral-suite-settings' // Link to settings
	);
}
add_action('admin_menu', 'hk_add_settings_link_to_urn_menu');

/**
 * Register Urns Category Taxonomy
 */
if (!function_exists('hk_fs_tax_register_urn_categories')) {
	function hk_fs_tax_register_urn_categories() {
		$labels = array(
			'name'              => _x('Urn Categories', 'taxonomy general name', 'hk-funeral-cpt'),
			'singular_name'     => _x('Urn Category', 'taxonomy singular name', 'hk-funeral-cpt'),
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
			'rewrite'           => array('slug' => 'urn-category'),
		);

		register_taxonomy('hk_fs_urn_category', array('hk_fs_urn'), $args);
	}
	add_action('init', 'hk_fs_tax_register_urn_categories', 0);
}

/**
 * Custom icon for Urns CPT
 */
function hk_fs_urn_admin_style() {
	?>
	<style>
		/* URN Icon */
		#adminmenu .menu-icon-hk_fs_urn div.wp-menu-image::before {
			content: '';
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23a7aaad" d="M73.744,19.624c-0.748-1.007-2.44-1.765-3.691-1.765H29.946c-1.251,0-2.943,0.758-3.692,1.765c-3.211,4.291-8.396,13.58-8.396,30.381c0,13.462,3.378,23.809,7.176,31.244c2.917,5.719,6.092,9.711,8.154,12.069C34.015,94.262,35.73,95,36.981,95h26.036c1.252,0,2.966-0.738,3.792-1.683c2.062-2.358,5.238-6.351,8.154-12.069c3.798-7.435,7.176-17.781,7.176-31.244C82.14,33.204,76.955,23.915,73.744,19.624z M62.857,88.573H37.141c0,0-12.855-12.854-12.855-38.569c0-17.903,6.427-25.718,6.427-25.718h38.573c0,0,6.427,7.815,6.427,25.718C75.713,75.72,62.857,88.573,62.857,88.573z"/></svg>');
			background-repeat: no-repeat;
			background-position: center;
			background-size: 20px;
			opacity: 0.6;
		}
		
		/* Hover state */
		#adminmenu .menu-icon-hk_fs_urn:hover div.wp-menu-image::before,
		#adminmenu .menu-icon-hk_fs_urn.current div.wp-menu-image::before {
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23ffffff" d="M73.744,19.624c-0.748-1.007-2.44-1.765-3.691-1.765H29.946c-1.251,0-2.943,0.758-3.692,1.765c-3.211,4.291-8.396,13.58-8.396,30.381c0,13.462,3.378,23.809,7.176,31.244c2.917,5.719,6.092,9.711,8.154,12.069C34.015,94.262,35.73,95,36.981,95h26.036c1.252,0,2.966-0.738,3.792-1.683c2.062-2.358,5.238-6.351,8.154-12.069c3.798-7.435,7.176-17.781,7.176-31.244C82.14,33.204,76.955,23.915,73.744,19.624z M62.857,88.573H37.141c0,0-12.855-12.854-12.855-38.569c0-17.903,6.427-25.718,6.427-25.718h38.573c0,0,6.427,7.815,6.427,25.718C75.713,75.72,62.857,88.573,62.857,88.573z"/></svg>');
			opacity: 1;
		}
		
		/* Google Sheets integration styles */
		#hk_fs_urn_price[disabled] {
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
}
add_action('admin_head', 'hk_fs_urn_admin_style');

/**
 * Customize the title placeholder for Urn post type
 */
if (!function_exists('hk_fs_urn_change_title_text')) {
	function hk_fs_urn_change_title_text($title) {
		$screen = get_current_screen();
		
		if ('hk_fs_urn' == $screen->post_type) {
			$title = 'Enter urn name here';
		}
		
		return $title;
	}
	add_filter('enter_title_here', 'hk_fs_urn_change_title_text');
}

/**
 * Register REST API fields
 */
function hk_fs_register_urn_meta() {
	register_rest_field(
		'hk_fs_urn',
		'price',
		array(
			'get_callback' => function($post) {
				return get_post_meta($post['id'], '_hk_fs_urn_price', true);
			},
			'schema' => array(
				'type' => 'string',
				'description' => 'Price of the urn'
			)
		)
	);
}
add_action('rest_api_init', 'hk_fs_register_urn_meta');

/**
 * Register meta fields for REST API access
 */
function hk_fs_register_urn_meta_fields() {
	 register_post_meta('hk_fs_urn', '_hk_fs_urn_price', [
		 'show_in_rest' => true,
		 'single' => true,
		 'type' => 'string',
		 'auth_callback' => function() {
			 return current_user_can('edit_posts');
		 }
	 ]);
	 
	 // If you have category meta
	 register_post_meta('hk_fs_urn', '_hk_fs_urn_category', [
		 'show_in_rest' => true,
		 'single' => true,
		 'type' => 'string',
		 'auth_callback' => function() {
			 return current_user_can('edit_posts');
		 }
	 ]);
 }
add_action('init', 'hk_fs_register_urn_meta_fields');

/**
 * Add a custom meta box for urn pricing information
 */
function hk_fs_add_urn_meta_boxes() {
	if (!current_user_can('manage_funeral_content')) {
		return;
	}

	add_meta_box(
		'hk_fs_urn_pricing',
		__('Pricing Information', 'hk-funeral-cpt'),
		'hk_fs_urn_pricing_callback',
		'hk_fs_urn',
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'hk_fs_add_urn_meta_boxes');

/**
 * Pricing meta box callback function
 */
function hk_fs_urn_pricing_callback($post) {
	wp_nonce_field('hk_fs_urn_pricing_nonce', 'hk_fs_urn_pricing_nonce');
	$price = get_post_meta($post->ID, '_hk_fs_urn_price', true);
	$managed_by_sheets = get_option('hk_fs_urn_price_google_sheets', false);
	
	?>
	<div class="price-field-container <?php echo $managed_by_sheets ? 'sheet-managed' : ''; ?>">
		<p>
			<label for="hk_fs_urn_price"><?php _e('Price ($):', 'hk-funeral-cpt'); ?></label>
			<input type="number" id="hk_fs_urn_price" name="hk_fs_urn_price" 
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
}

/**
 * Save the meta box data
 */
function hk_fs_save_urn_meta($post_id) {
	if (!current_user_can('manage_funeral_content')) {
		return;
	}

	if (!isset($_POST['hk_fs_urn_pricing_nonce']) || 
		!wp_verify_nonce($_POST['hk_fs_urn_pricing_nonce'], 'hk_fs_urn_pricing_nonce')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	// Only update price if not managed by Google Sheets
	$managed_by_sheets = get_option('hk_fs_urn_price_google_sheets', false);
	
	if (!$managed_by_sheets && isset($_POST['hk_fs_urn_price'])) {
		$price = sanitize_text_field($_POST['hk_fs_urn_price']);
		update_post_meta($post_id, '_hk_fs_urn_price', $price);
	}
}
add_action('save_post_hk_fs_urn', 'hk_fs_save_urn_meta');

/**
 * Add admin notice for Google Sheets integration
 */
function hk_fs_urn_admin_notices() {
	$screen = get_current_screen();
	
	if (!$screen || $screen->post_type !== 'hk_fs_urn') {
		return;
	}
	
	$managed_by_sheets = get_option('hk_fs_urn_price_google_sheets', false);
	
	if ($managed_by_sheets) {
		?>
		<div class="notice notice-info">
			<p>
				<span class="dashicons dashicons-cloud" style="color:#0073aa; font-size:18px; vertical-align:middle;"></span>
				<strong><?php _e('Google Sheets Integration Active:', 'hk-funeral-cpt'); ?></strong>
				<?php _e('Urn pricing is currently managed via Google Sheets. Price fields are disabled in the admin interface.', 'hk-funeral-cpt'); ?>
				<a href="<?php echo admin_url('options-general.php?page=hk-funeral-suite-settings'); ?>">
					<?php _e('Change this setting', 'hk-funeral-cpt'); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
add_action('admin_notices', 'hk_fs_urn_admin_notices');

/**
 * Add custom columns to urn admin list
 */
function hk_fs_add_urn_columns($columns) {
	$new_columns = array();
	
	foreach($columns as $key => $value) {
		$new_columns[$key] = $value;
		if ($key === 'title') {
			$new_columns['price'] = __('Price', 'hk-funeral-cpt');
		}
	}
	
	return $new_columns;
}
add_filter('manage_hk_fs_urn_posts_columns', 'hk_fs_add_urn_columns');

/**
 * Display urn data in the custom column
 */
function hk_fs_display_urn_columns($column, $post_id) {
	if ($column === 'price') {
		$price = get_post_meta($post_id, '_hk_fs_urn_price', true);
		$managed_by_sheets = get_option('hk_fs_urn_price_google_sheets', false);
		
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
}
add_action('manage_hk_fs_urn_posts_custom_column', 'hk_fs_display_urn_columns', 10, 2);

/**
 * Make the columns sortable
 */
function hk_fs_sortable_urn_columns($columns) {
	$columns['price'] = 'price';
	return $columns;
}
add_filter('manage_edit-hk_fs_urn_sortable_columns', 'hk_fs_sortable_urn_columns');

/**
 * Add sorting functionality to price column
 */
function hk_fs_urn_orderby($query) {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}
	
	if ($query->get('post_type') === 'hk_fs_urn' && $query->get('orderby') === 'price') {
		$query->set('meta_key', '_hk_fs_urn_price');
		$query->set('orderby', 'meta_value_num');
	}
}
add_action('pre_get_posts', 'hk_fs_urn_orderby');

/**
 * Auto-insert the Urn block into new urn posts
 */
function hk_fs_auto_insert_urn_block($post_id, $post = null, $update = false) {
	// If we only got the ID, get the post object
	if (!is_object($post)) {
		$post = get_post($post_id);
	}
	
	// Only proceed for our custom post type and new posts
	if ($post->post_type !== 'hk_fs_urn' || $post->post_content !== '') {
		return;
	}
	
	// Create block content
	$block_content = '<!-- wp:hk-funeral-suite/urn /-->';
	
	// Update the post with our block
	wp_update_post(array(
		'ID' => $post->ID,
		'post_content' => $block_content,
	));
}
add_action('wp_insert_post', 'hk_fs_auto_insert_urn_block', 10, 3);

/**
 * Add a template for the Urn post type
 */
function hk_fs_register_urn_template() {
	$post_type_object = get_post_type_object('hk_fs_urn');
	
	if ($post_type_object) {
		$post_type_object->template = array(
			array('hk-funeral-suite/urn'),
			array('core/paragraph', array(
				'placeholder' => __('Add urn description...', 'hk-funeral-cpt')
			))
		);
		
		// Lock the template so users can't move or delete the block
		$post_type_object->template_lock = false;
	}
}
add_action('init', 'hk_fs_register_urn_template', 11); // Run after CPT registration
