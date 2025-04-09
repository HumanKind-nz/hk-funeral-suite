<?php
/**
 * Funeral Pricing Packages Custom Post Type
 *
 * @package    HK_Funeral_Suite
 * @subpackage CPT
 * @version    1.0.12  
 * @since      1.0.0
 * @changelog 
 *   1.0.12 - Added shortcode admin column with copy functionality
 *   1.0.11 - updated capability_type from page to post
 *   1.0.10 - Remove content admin column. Not needed.
 *   1.0.9 - Remove extra intro meta box when block used 
 *   1.0.8 - Added autosave checks for improved performance
 *   1.0.7 - Fix block editor template integration
 *   1.0.6 - Visibility settings
 *   1.0.5 - Change price field to txt
 *   1.0.4 - Added Intro Paragraph field
 *   1.0.3 - Google sheet / pricing sync
 *   1.0.0 - Initial version
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
 *    - Price, intro, and order fields are registered to show in REST API
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
			 'public'              => true,
			 'publicly_queryable'  => $make_public,                  // Allow front-end queries only if public
			 'show_ui'             => true,                          // Always show admin UI
			 'show_in_menu'        => true,                          // Always show in menu
			 'menu_position'       => 6,
			 'query_var'           => $make_public,                  // Allow query vars only if public
			 'rewrite'             => $make_public ? array('slug' => 'funeral-packages') : false, // Rewrite only if public
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
			 'menu_icon'           => 'data:image/svg+xml;base64,' . base64_encode('<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.1 5.74988C11.1 5.25283 11.5029 4.84988 12 4.84988C12.4971 4.84988 12.9001 5.25283 12.9001 5.74988C12.9001 6.24694 12.4972 6.64988 12.0001 6.64988C11.503 6.64988 11.1 6.24694 11.1 5.74988Z" fill="#a7aaad"/><path d="M12 8.24988C12.4142 8.24988 12.75 8.58567 12.75 8.99988V9.43766C13.7408 9.5883 14.5 10.4438 14.5 11.4767C14.5 11.8909 14.1642 12.2267 13.75 12.2267C13.3358 12.2267 13 11.8909 13 11.4767C13 11.166 12.7481 10.9141 12.4374 10.9141H11.75C11.3358 10.9141 11 11.2499 11 11.6641V11.9292C11 12.2418 11.1939 12.5217 11.4866 12.6314L13.0401 13.214C13.9182 13.5434 14.5 14.3829 14.5 15.3207V15.5858C14.5 16.6566 13.752 17.5527 12.75 17.7801V18.2499C12.75 18.6641 12.4142 18.9999 12 18.9999C11.5858 18.9999 11.25 18.6641 11.25 18.2499V17.8123C10.2592 17.6616 9.5 16.8061 9.5 15.7732C9.5 15.359 9.83579 15.0232 10.25 15.0232C10.6642 15.0232 11 15.359 11 15.7732C11 16.0839 11.2519 16.3358 11.5626 16.3358H12.25C12.6642 16.3358 13 16 13 15.5858V15.3207C13 15.0081 12.8061 14.7283 12.5134 14.6185L10.9599 14.0359C10.0818 13.7066 9.5 12.8671 9.5 11.9292V11.6641C9.5 10.5933 10.248 9.69724 11.25 9.46988V8.99988C11.25 8.58567 11.5858 8.24988 12 8.24988Z" fill="#a7aaad"/><path fill-rule="evenodd" clip-rule="evenodd" d="M10.9073 2.26459C11.5869 1.88706 12.4131 1.88706 13.0927 2.26459L18.3427 5.18126C19.057 5.57809 19.5 6.33099 19.5 7.14811V19.7499C19.5 20.9925 18.4926 21.9999 17.25 21.9999H6.75C5.50736 21.9999 4.5 20.9925 4.5 19.7499V7.14811C4.5 6.33099 4.94301 5.57809 5.6573 5.18126L10.9073 2.26459ZM12.3642 3.57583C12.1377 3.44998 11.8623 3.44998 11.6358 3.57583L6.38577 6.49249C6.14767 6.62477 6 6.87574 6 7.14811V19.7499C6 20.1641 6.33579 20.4999 6.75 20.4999H17.25C17.6642 20.4999 18 20.1641 18 19.7499V7.14811C18 6.87574 17.8523 6.62477 17.6142 6.49249L12.3642 3.57583Z" fill="#a7aaad"/></svg>'),
			 'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
			 'show_in_rest'        => true,                          // Keep REST API enabled for Gutenberg
			 'exclude_from_search' => !$make_public,                 // Exclude from search if not public
			 'template' => array(
				 array('hk-funeral-suite/pricing-package'),
				 array('core/paragraph')
			 ),
			 'template_lock' => false,                               // Allow adding/removing other blocks
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
	register_post_meta('hk_fs_package', '_hk_fs_package_intro', array(
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
		'auth_callback' => function() {
			return current_user_can('manage_funeral_content');
		}
	));
	
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
	 
	 // Keep the other meta boxes
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
 * Intro paragraph meta box callback
 */
function hk_fs_package_intro_callback($post) {
 wp_nonce_field('hk_fs_package_intro_nonce', 'hk_fs_package_intro_nonce');
 
 $intro = get_post_meta($post->ID, '_hk_fs_package_intro', true);
 
 ?>
 <p>
	 <input type="text" id="hk_fs_package_intro" name="hk_fs_package_intro" 
			value="<?php echo esc_attr($intro); ?>" style="width: 100%;" 
			placeholder="<?php _e('Enter brief summary to be shown at top ...', 'hk-funeral-cpt'); ?>">
	 <span class="description"><?php _e('Enter an optional introductory sentence for this pricing package.', 'hk-funeral-cpt'); ?></span>
 </p>
 <?php
}

/**
 * Pricing meta box callback
 */
function hk_fs_package_pricing_callback($post) {
	wp_nonce_field('hk_fs_package_pricing_nonce', 'hk_fs_package_pricing_nonce');
	
	$price = get_post_meta($post->ID, '_hk_fs_package_price', true);
	$managed_by_sheets = get_option('hk_fs_package_price_google_sheets', false);
	
	?>
	<div class="price-field-container <?php echo $managed_by_sheets ? 'sheet-managed' : ''; ?>">
		<p>
			<label for="hk_fs_package_price"><?php _e('Price ($):', 'hk-funeral-cpt'); ?></label>
			<input type="text" id="hk_fs_package_price" name="hk_fs_package_price" 
				   value="<?php echo esc_attr($price); ?>" style="width: 100%;"
				   <?php echo $managed_by_sheets ? 'disabled="disabled"' : ''; ?>>
			<span class="description" style="font-size: 11px; color: #757575;">
				<?php _e('Enter a numeric price (e.g., 1295.00) or text like "P.O.A."', 'hk-funeral-cpt'); ?>
			</span>
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
 * Order meta box callback
 */
function hk_fs_package_ordering_callback($post) {
	wp_nonce_field('hk_fs_package_ordering_nonce', 'hk_fs_package_ordering_nonce');
	
	$order = get_post_meta($post->ID, '_hk_fs_package_order', true);
	if (empty($order) && $order !== 0) {
		$order = 10;
	}
	
	// Don't ever disable the order field, even with Google Sheets integration
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
	// Skip autosaves and revisions
	if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
	    return;
	}
	
	// Check intro nonce
	if (isset($_POST['hk_fs_package_intro_nonce']) && 
		wp_verify_nonce($_POST['hk_fs_package_intro_nonce'], 'hk_fs_package_intro_nonce')) {
		
		if (isset($_POST['hk_fs_package_intro'])) {
			update_post_meta($post_id, '_hk_fs_package_intro', 
				sanitize_textarea_field($_POST['hk_fs_package_intro']));
		}
	}
	
	// Check pricing nonce
	if (isset($_POST['hk_fs_package_pricing_nonce']) && 
		wp_verify_nonce($_POST['hk_fs_package_pricing_nonce'], 'hk_fs_package_pricing_nonce')) {
		
		// Always get a fresh version of the setting
		$managed_by_sheets = get_option('hk_fs_package_price_google_sheets', false);
		
		if (!$managed_by_sheets && isset($_POST['hk_fs_package_price'])) {
			update_post_meta($post_id, '_hk_fs_package_price', 
				sanitize_text_field($_POST['hk_fs_package_price']));
		}
	}
	
	// Check ordering nonce
	if (isset($_POST['hk_fs_package_ordering_nonce']) && 
		wp_verify_nonce($_POST['hk_fs_package_ordering_nonce'], 'hk_fs_package_ordering_nonce')) {
		
		// Order can always be updated regardless of price management
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
    
    // Remove 'content' column by not including it in the new array
    foreach($columns as $key => $value) {
        if ($key === 'title') {
            $new_columns[$key] = $value;
            $new_columns['intro'] = __('Intro', 'hk-funeral-cpt');
            $new_columns['price'] = __('Price', 'hk-funeral-cpt');
            $new_columns['order'] = __('Order', 'hk-funeral-cpt');
            $new_columns['shortcode'] = __('Shortcode', 'hk-funeral-cpt');
        } elseif ($key !== 'content') {
            // Add all columns except 'content'
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
	if ($column === 'intro') {
		$intro = get_post_meta($post_id, '_hk_fs_package_intro', true);
		echo !empty($intro) ? wp_trim_words($intro, 10, '...') : '—';
	}
	
	if ($column === 'price') {
		$price = get_post_meta($post_id, '_hk_fs_package_price', true);
		$managed_by_sheets = get_option('hk_fs_package_price_google_sheets', false);
		
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
	
	if ($column === 'order') {
		$order = get_post_meta($post_id, '_hk_fs_package_order', true);
		echo !empty($order) ? esc_html($order) : '10';
	}
	
	if ($column === 'shortcode') {
		$shortcode = '[hk_formatted_price key="_hk_fs_package_price" post_id="' . esc_attr($post_id) . '" decimals="0"]';
		echo '<div class="hk-shortcode-container">';
		echo '<input type="text" readonly class="hk-shortcode-display" value="' . esc_attr($shortcode) . '" onclick="this.select();" style="width: 100%; max-width: 300px; font-size: 12px; padding: 4px; background: #f0f0f1;">';
		echo '<button type="button" class="button button-small hk-copy-shortcode" data-shortcode="' . esc_attr($shortcode) . '"><span class="dashicons dashicons-clipboard"></span></button>';
		echo '</div>';
	}
}
add_action('manage_hk_fs_package_posts_custom_column', 'hk_fs_display_package_columns', 10, 2);

/**
 * Make columns sortable
 */
function hk_fs_sortable_package_columns($columns) {
	$columns['price'] = 'price';
	$columns['order'] = 'order';
	$columns['intro'] = 'intro';
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
			// On frontend, default sort by display order instead of price
			// (since price may now contain text values)
			if (!$query->get('orderby')) {
				$query->set('meta_key', '_hk_fs_package_order');
				$query->set('orderby', 'meta_value_num');
				$query->set('order', 'ASC');
			}
		}
		
		// Handle explicit sorting requests
		if ($query->get('orderby') === 'price') {
			$query->set('meta_key', '_hk_fs_package_price');
			// Changed from meta_value_num to meta_value to handle text
			$query->set('orderby', 'meta_value');
		} elseif ($query->get('orderby') === 'order') {
			$query->set('meta_key', '_hk_fs_package_order');
			$query->set('orderby', 'meta_value_num');
		} elseif ($query->get('orderby') === 'intro') {
			$query->set('meta_key', '_hk_fs_package_intro');
			$query->set('orderby', 'meta_value');
		}
	}
}
add_action('pre_get_posts', 'hk_fs_package_orderby');

/**
 * Add admin notice for Google Sheets integration
 */
function hk_fs_package_admin_notices() {
	$screen = get_current_screen();
	
	if (!$screen || $screen->post_type !== 'hk_fs_package') {
		return;
	}
	
	$managed_by_sheets = get_option('hk_fs_package_price_google_sheets', false);
	
	if ($managed_by_sheets) {
		?>
		<div class="notice notice-info">
			<p>
				<span class="dashicons dashicons-cloud" style="color:#0073aa; font-size:18px; vertical-align:middle;"></span>
				<strong><?php _e('Google Sheets Integration Active:', 'hk-funeral-cpt'); ?></strong>
				<?php _e('Package pricing is currently managed via Google Sheets. Price fields are disabled in the admin interface.', 'hk-funeral-cpt'); ?>
				<a href="<?php echo admin_url('options-general.php?page=hk-funeral-suite-settings'); ?>">
					<?php _e('Change this setting', 'hk-funeral-cpt'); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
add_action('admin_notices', 'hk_fs_package_admin_notices');

/**
 * Add styles for Google Sheets integration
 */
function hk_fs_package_admin_styles() {
	$screen = get_current_screen();
	
	if (!$screen || $screen->post_type !== 'hk_fs_package') {
		return;
	}
	
	$managed_by_sheets = get_option('hk_fs_package_price_google_sheets', false);
	
	?>
	<style type="text/css">
		.column-intro {
			width: 30% !important;
			min-width: 250px;
		}

		.column-shortcode {
			width: 350px !important;
		}

		.hk-shortcode-display {
			cursor: pointer;
			border: 1px solid #ddd;
		}

		.hk-shortcode-display:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
			outline: 2px solid transparent;
		}
		
		.hk-shortcode-container {
			display: flex;
			align-items: center;
		}
		
		.hk-copy-shortcode {
			margin-left: 5px !important;
			padding: 0 !important;
			height: 28px !important;
			width: 28px !important;
		}
		
		.hk-copy-shortcode .dashicons {
			width: 20px;
			height: 20px;
			font-size: 16px;
			line-height: 1.3;
		}
		
		<?php if ($managed_by_sheets): ?>
		#hk_fs_package_price[disabled] {
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
	
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.hk-copy-shortcode').on('click', function() {
				var shortcode = $(this).data('shortcode');
				var $button = $(this);
				var $icon = $button.find('.dashicons');
				
				// Try to use modern clipboard API first
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(shortcode).then(function() {
						showCopySuccess($button, $icon);
					}).catch(function() {
						// Fall back to the old method if clipboard API fails
						fallbackCopyToClipboard(shortcode, $button, $icon);
					});
				} else {
					// Use the older method for browsers that don't support clipboard API
					fallbackCopyToClipboard(shortcode, $button, $icon);
				}
			});
			
			// Fallback copy method
			function fallbackCopyToClipboard(text, $button, $icon) {
				var tempInput = $('<input>');
				$('body').append(tempInput);
				tempInput.val(text).select();
				document.execCommand('copy');
				tempInput.remove();
				showCopySuccess($button, $icon);
			}
			
			// Show success animation
			function showCopySuccess($button, $icon) {
				$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
				$button.css('background-color', '#00a32a');
				$button.css('color', '#fff');
				
				// Reset after 2 seconds
				setTimeout(function() {
					$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
					$button.css('background-color', '');
					$button.css('color', '');
				}, 2000);
			}
		});
	</script>
	<?php
}
add_action('admin_head', 'hk_fs_package_admin_styles');

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

/**
 * Register template for pricing package
 * 
 * This sets up the default template for the pricing package post type.
 */
function hk_fs_register_pricing_package_template() {
	$post_type_object = get_post_type_object('hk_fs_package');
	
	if ($post_type_object) {
		$post_type_object->template = array(
			array('hk-funeral-suite/pricing-package'),
			array('core/paragraph', array(
				'placeholder' => __('Add pricing package description...', 'hk-funeral-cpt')
			))
		);
		
		// Allow adding/removing other blocks
		$post_type_object->template_lock = false;
	}
}
add_action('init', 'hk_fs_register_pricing_package_template', 11); // Run after CPT registration
