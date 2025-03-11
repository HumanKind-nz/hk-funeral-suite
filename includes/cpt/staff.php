<?php
/**
 * Staff Custom Post Type
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.2  
 * @since      1.0.0
 * @changelog  
 *   1.0.2 - Fix block editor template integration
 *   1.0.1 - Minor adjustments
 *   1.0.0 - Initial version
 *   - Added staff post type
 *   - Added location taxonomy
 *   - Added job role taxonomy
 *   - Added meta fields for contact info and qualifications
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Register Staff Custom Post Type
 */
if (!function_exists('hk_fs_cpt_register_staff')) {
	function hk_fs_cpt_register_staff() {
		$labels = array(
			'name'                  => _x('Team Members', 'Post type general name', 'hk-funeral-cpt'),
			'singular_name'         => _x('Team Member', 'Post type singular name', 'hk-funeral-cpt'),
			'menu_name'             => _x('HK Team Members', 'Admin Menu text', 'hk-funeral-cpt'),
			'name_admin_bar'        => _x('Team Member', 'Add New on Toolbar', 'hk-funeral-cpt'),
			'add_new'               => __('Add Team Member', 'hk-funeral-cpt'),
			'add_new_item'          => __('Add New Team Member', 'hk-funeral-cpt'),
			'new_item'              => __('New Team Member', 'hk-funeral-cpt'),
			'edit_item'             => __('Edit Team Member', 'hk-funeral-cpt'),
			'view_item'             => __('View Team Members', 'hk-funeral-cpt'),
			'all_items'             => __('All Team', 'hk-funeral-cpt'),
			'search_items'          => __('Search Team', 'hk-funeral-cpt'),
			'not_found'             => __('No team members found.', 'hk-funeral-cpt'),
			'not_found_in_trash'    => __('No team members found in Trash.', 'hk-funeral-cpt'),
			'featured_image'        => __('Team Member Image', 'hk-funeral-cpt'),
			'set_featured_image'    => __('Set team member image', 'hk-funeral-cpt'),
			'remove_featured_image' => __('Remove team member image', 'hk-funeral-cpt'),
			'use_featured_image'    => __('Use as team member image', 'hk-funeral-cpt'),
		);
		
		// Get the public setting from options with a default of false
		$make_public = get_option('hk_fs_enable_public_staff', false);
		
		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => $make_public,                  // Allow front-end queries only if public
			'show_ui'            => true,                          // Always show admin UI
			'show_in_menu'       => true,                          // Always show in menu
			'menu_position'      => 6,
			'query_var'          => $make_public,                  // Allow query vars only if public
			'rewrite'            => $make_public ? array('slug' => 'team') : false, // Rewrite only if public
			'capability_type'    => 'post',
			'capabilities'       => array(
				'edit_post'          => 'manage_funeral_content',
				'edit_posts'         => 'manage_funeral_content',
				'edit_others_posts'  => 'manage_funeral_content',
				'publish_posts'      => 'manage_funeral_content',
				'read_post'          => 'manage_funeral_content',
				'read_private_posts' => 'manage_funeral_content',
				'delete_post'        => 'manage_funeral_content'
			),
			'has_archive'        => $make_public,                  // Archive only if public
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-businesswoman',
			'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
			'show_in_rest'       => true,                          // Keep REST API enabled for Gutenberg
			'show_in_nav_menus'  => $make_public,                  // Only show in nav menus if public
			'exclude_from_search'=> !$make_public,                 // Exclude from search if not public
			'template'           => array(
				array('hk-funeral-suite/team-member'),
				array('core/paragraph')
			),
			'template_lock'      => false,                         // Allow adding/removing blocks
		);
		
		// Allow theme/plugin overrides
		$args = apply_filters('hk_fs_staff_post_type_args', $args);
	
		register_post_type('hk_fs_staff', $args);
	}
	add_action('init', 'hk_fs_cpt_register_staff', 0);
}

function hk_add_settings_link_to_staff_menu() {
	add_submenu_page(
		'edit.php?post_type=hk_fs_staff', // CPT menu slug
		'HK Funeral Suite Settings', // Page title
		'HK Funeral Suite Settings', // Menu title
		'manage_funeral_settings', // Capability
		'options-general.php?page=hk-funeral-suite-settings' // Link to settings
	);
}
add_action('admin_menu', 'hk_add_settings_link_to_staff_menu');

/**
 * Register Staff Location Taxonomy
 */
if (!function_exists('hk_fs_tax_register_locations')) {
	function hk_fs_tax_register_locations() {
		$labels = array(
			'name'              => _x('Locations', 'taxonomy general name', 'hk-funeral-cpt'),
			'singular_name'     => _x('Location', 'taxonomy singular name', 'hk-funeral-cpt'),
			'menu_name'         => __('Location', 'hk-funeral-cpt'),
			'all_items'         => __('All Locations', 'hk-funeral-cpt'),
			'edit_item'         => __('Edit Location', 'hk-funeral-cpt'),
			'update_item'       => __('Update Location', 'hk-funeral-cpt'),
			'add_new_item'      => __('Add New Location', 'hk-funeral-cpt'),
			'new_item_name'     => __('New Location Name', 'hk-funeral-cpt'),
			'search_items'      => __('Search Locations', 'hk-funeral-cpt'),
			'parent_item'       => __('Parent Location', 'hk-funeral-cpt'),
			'parent_item_colon' => __('Parent Location:', 'hk-funeral-cpt'),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array('slug' => 'staff-location'),
		);

		register_taxonomy('hk_fs_location', array('hk_fs_staff'), $args);
	}
	add_action('init', 'hk_fs_tax_register_locations', 0);
}

/**
 * Register Staff Role Taxonomy
 */
if (!function_exists('hk_fs_tax_register_roles')) {
	function hk_fs_tax_register_roles() {
		$labels = array(
			'name'              => _x('Job Roles', 'taxonomy general name', 'hk-funeral-cpt'),
			'singular_name'     => _x('Job Role', 'taxonomy singular name', 'hk-funeral-cpt'),
			'menu_name'         => __('Job Roles', 'hk-funeral-cpt'),
			'all_items'         => __('All Job Roles', 'hk-funeral-cpt'),
			'edit_item'         => __('Edit Job Role', 'hk-funeral-cpt'),
			'update_item'       => __('Update Job Role', 'hk-funeral-cpt'),
			'add_new_item'      => __('Add New Job Role', 'hk-funeral-cpt'),
			'new_item_name'     => __('New Job Role Name', 'hk-funeral-cpt'),
			'search_items'      => __('Search Job Roles', 'hk-funeral-cpt'),
			'parent_item'       => __('Parent Job Role', 'hk-funeral-cpt'),
			'parent_item_colon' => __('Parent Job Role:', 'hk-funeral-cpt'),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array('slug' => 'job-role'),
		);

		register_taxonomy('hk_fs_role', array('hk_fs_staff'), $args);
	}
	add_action('init', 'hk_fs_tax_register_roles', 0);
}

/**
 * Register REST API fields
 */
function hk_fs_register_staff_meta() {
	$fields = array(
		'position' => '_hk_fs_staff_position',
		'qualification' => '_hk_fs_staff_qualification',
		'phone' => '_hk_fs_staff_phone',
		'email' => '_hk_fs_staff_email'
	);

	foreach ($fields as $field_name => $meta_key) {
		register_rest_field(
			'hk_fs_staff',
			$field_name,
			array(
				'get_callback' => function($post) use ($meta_key) {
					return get_post_meta($post['id'], $meta_key, true);
				},
				'schema' => array(
					'type' => 'string',
					'description' => 'Staff member ' . $field_name
				)
			)
		);
	}
}
add_action('rest_api_init', 'hk_fs_register_staff_meta');

/**
 * Register meta fields for REST API access
 */
function hk_fs_register_staff_meta_fields() {
	$fields = array(
		'_hk_fs_staff_position',
		'_hk_fs_staff_qualification',
		'_hk_fs_staff_phone',
		'_hk_fs_staff_email'
	);
	
	foreach ($fields as $meta_key) {
		register_post_meta('hk_fs_staff', $meta_key, [
			'show_in_rest' => true,
			'single' => true,
			'type' => 'string',
			'auth_callback' => function() {
				return current_user_can('edit_posts');
			}
		]);
	}
}
add_action('init', 'hk_fs_register_staff_meta_fields');

/**
 * Register post meta for the block editor
 */
function hk_fs_register_staff_meta_for_rest() {
	$meta_fields = array(
		'_hk_fs_staff_position' => 'string',
		'_hk_fs_staff_qualification' => 'string',
		'_hk_fs_staff_phone' => 'string',
		'_hk_fs_staff_email' => 'string'
	);
	
	foreach ($meta_fields as $meta_key => $type) {
		register_post_meta('hk_fs_staff', $meta_key, array(
			'show_in_rest' => true,
			'single' => true,
			'type' => $type,
			'auth_callback' => function() {
				return current_user_can('manage_funeral_content');
			}
		));
	}
}
add_action('init', 'hk_fs_register_staff_meta_for_rest');

/**
 * Optional: Remove the meta box in side column since we're using blocks now
 */
// function hk_fs_remove_staff_meta_boxes() {
// 	remove_meta_box('hk_fs_staff_contact', 'hk_fs_staff', 'side');
// }
// add_action('do_meta_boxes', 'hk_fs_remove_staff_meta_boxes');

/**
 * Custom icon for Staff CPT
 */
function hk_fs_staff_admin_style() {
	?>
	<style>
		/* Staff Icon */
		#adminmenu .menu-icon-hk_fs_staff div.wp-menu-image::before {
			content: '';
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23a7aaad" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0-6c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zM12 14c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm6 4H6v-.99c.2-.72 3.3-2.01 6-2.01s5.8 1.29 6 2v1z"/></svg>');
			background-repeat: no-repeat;
			background-position: center;
			background-size: 20px;
			opacity: 0.6;
		}
		
		/* Hover and active states */
		#adminmenu .menu-icon-hk_fs_staff:hover div.wp-menu-image::before,
		#adminmenu .menu-icon-hk_fs_staff.current div.wp-menu-image::before {
			background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23ffffff" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0-6c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zM12 14c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm6 4H6v-.99c.2-.72 3.3-2.01 6-2.01s5.8 1.29 6 2v1z"/></svg>');
			opacity: 1;
		}
	</style>
	<?php
}
add_action('admin_head', 'hk_fs_staff_admin_style');

/**
 * Customize the title placeholder for Staff post type
 */
if (!function_exists('hk_fs_staff_change_title_text')) {
	function hk_fs_staff_change_title_text($title) {
		$screen = get_current_screen();
		
		if ('hk_fs_staff' == $screen->post_type) {
			$title = 'Enter team member full name here';
		}
		
		return $title;
	}
	add_filter('enter_title_here', 'hk_fs_staff_change_title_text');
}

/**
 * Add a custom meta box for staff information
 */
function hk_fs_add_staff_meta_boxes() {
	if (!current_user_can('manage_funeral_content')) {
		return;
	}

	add_meta_box(
		'hk_fs_staff_contact',
		__('Staff Information', 'hk-funeral-cpt'),
		'hk_fs_staff_contact_callback',
		'hk_fs_staff',
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'hk_fs_add_staff_meta_boxes');

/**
 * Meta box callback function
 */
/**
  * Meta box callback function
  */
 function hk_fs_staff_contact_callback($post) {
	 wp_nonce_field('hk_fs_staff_contact_nonce', 'hk_fs_staff_contact_nonce');
	 
	 $fields = array(
		 'position' => array(
			 'label' => __('Position:', 'hk-funeral-cpt'),
			 'type' => 'text'
		 ),
		 'qualification' => array(
			 'label' => __('Qualification:', 'hk-funeral-cpt'),
			 'type' => 'text'
		 ),
		 'phone' => array(
			 'label' => __('Phone:', 'hk-funeral-cpt'),
			 'type' => 'text'
		 ),
		 'email' => array(
			 'label' => __('Email:', 'hk-funeral-cpt'),
			 'type' => 'email'
		 )
	 );
 
	 foreach ($fields as $field => $config) {
		 $value = get_post_meta($post->ID, '_hk_fs_staff_' . $field, true);
		 ?>
		 <p>
			 <label for="hk_fs_staff_<?php echo $field; ?>"><?php echo $config['label']; ?></label>
			 <input type="<?php echo $config['type']; ?>" 
					id="hk_fs_staff_<?php echo $field; ?>" 
					name="hk_fs_staff_<?php echo $field; ?>" 
					value="<?php echo esc_attr($value); ?>" 
					style="width: 100%;">
		 </p>
		 <?php
	 }
 } 
		
		/**
		 * Save the meta box data
		 */
		function hk_fs_save_staff_meta($post_id) {
			if (!current_user_can('manage_funeral_content')) {
				return;
			}
		
			if (!isset($_POST['hk_fs_staff_contact_nonce']) || 
				!wp_verify_nonce($_POST['hk_fs_staff_contact_nonce'], 'hk_fs_staff_contact_nonce')) {
				return;
			}
		
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}
		
			$fields = array(
				'position' => 'sanitize_text_field',
				'qualification' => 'sanitize_text_field',
				'phone' => 'sanitize_text_field',
				'email' => 'sanitize_email'
			);
		
			foreach ($fields as $field => $sanitize_callback) {
				if (isset($_POST['hk_fs_staff_' . $field])) {
					$value = call_user_func($sanitize_callback, $_POST['hk_fs_staff_' . $field]);
					update_post_meta($post_id, '_hk_fs_staff_' . $field, $value);
				}
			}
		}
		add_action('save_post_hk_fs_staff', 'hk_fs_save_staff_meta');
		
		/**
		 * Add custom columns to staff admin list
		 */
		function hk_fs_add_staff_columns($columns) {
			$new_columns = array();
			
			foreach($columns as $key => $value) {
				if ($key === 'title') {
					$new_columns[$key] = $value;
					$new_columns['position'] = __('Position', 'hk-funeral-cpt');
					$new_columns['qualification'] = __('Qualification', 'hk-funeral-cpt');
				} 
				elseif ($key === 'taxonomy-hk_fs_location' || $key === 'taxonomy-hk_fs_role') {
					// Skip for now, we'll add them later
				}
				else {
					$new_columns[$key] = $value;
				}
			}
			
			// Add the taxonomy columns back at the end
			$new_columns['taxonomy-hk_fs_location'] = __('Location', 'hk-funeral-cpt');
			$new_columns['taxonomy-hk_fs_role'] = __('Job Role', 'hk-funeral-cpt');
			
			return $new_columns;
		}
		add_filter('manage_hk_fs_staff_posts_columns', 'hk_fs_add_staff_columns');
		
		/**
		 * Display staff data in the custom columns
		 */
		function hk_fs_display_staff_columns($column, $post_id) {
			$fields = array('position', 'qualification');
			
			if (in_array($column, $fields)) {
				$value = get_post_meta($post_id, '_hk_fs_staff_' . $column, true);
				echo !empty($value) ? esc_html($value) : '—';
			}
		}
		add_action('manage_hk_fs_staff_posts_custom_column', 'hk_fs_display_staff_columns', 10, 2);
		
		/**
		 * Make custom columns sortable
		 */
		function hk_fs_sortable_staff_columns($columns) {
			$columns['position'] = 'position';
			$columns['qualification'] = 'qualification';
			return $columns;
		}
		add_filter('manage_edit-hk_fs_staff_sortable_columns', 'hk_fs_sortable_staff_columns');
		
		/**
		 * Add sorting functionality to custom columns
		 */
		function hk_fs_staff_orderby($query) {
			if (!is_admin() || !$query->is_main_query()) {
				return;
			}
			
			$orderby = $query->get('orderby');
			
			if ($query->get('post_type') === 'hk_fs_staff') {
				if ($orderby === 'position') {
					$query->set('meta_key', '_hk_fs_staff_position');
					$query->set('orderby', 'meta_value');
				}
				elseif ($orderby === 'qualification') {
					$query->set('meta_key', '_hk_fs_staff_qualification');
					$query->set('orderby', 'meta_value');
				}
			}
		}
		add_action('pre_get_posts', 'hk_fs_staff_orderby');


/**
 * Auto-insert Team Member Block
 * 
 * Add this to your main CPT file or include it separately
 */

/**
 * Auto-insert the Team Member block into new staff posts
 */
 function hk_fs_auto_insert_team_member_block($post_id, $post = null, $update = false) {
	 // If we only got the ID, get the post object
	 if (!is_object($post)) {
		 $post = get_post($post_id);
	 }
	 
	 // Only proceed for our custom post type and new posts
	 if ($post->post_type !== 'hk_fs_staff' || $post->post_content !== '') {
		 return;
	 }
	 
	 // Create block content
	 $block_content = '<!-- wp:hk-funeral-suite/team-member /-->';
	 
	 // Update the post with our block
	 wp_update_post(array(
		 'ID' => $post->ID,
		 'post_content' => $block_content,
	 ));
 }
add_action('wp_insert_post', 'hk_fs_auto_insert_team_member_block', 10, 3);

/**
 * Add a template for the Team Member post type
 */
function hk_fs_register_team_member_template() {
	$post_type_object = get_post_type_object('hk_fs_staff');
	
	if ($post_type_object) {
		$post_type_object->template = array(
			array('hk-funeral-suite/team-member'),
			array('core/paragraph', array(
				'placeholder' => __('Add team member biography...', 'hk-funeral-cpt')
			))
		);
		
		// Allow adding/removing other blocks
		$post_type_object->template_lock = false;
	}
}
add_action('init', 'hk_fs_register_team_member_template', 11); // Run after CPT registration
