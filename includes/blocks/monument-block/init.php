<?php
/**
 * Register Monument Block
 *
 * @package    HK_Funeral_Suite
 * @subpackage Blocks
 * @version    1.0.0
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Register Monument Block
 */
function hk_fs_register_monument_block() {
	if (!function_exists('register_block_type')) {
		return;
	}
	$js_path = HK_FS_PLUGIN_URL . 'includes/blocks/monument-block/index.js';
	$js_file = HK_FS_PLUGIN_DIR . 'includes/blocks/monument-block/index.js';
	if (!file_exists($js_file)) {
		return;
	}
	wp_register_script(
		'hk-fs-monument-block',
		$js_path,
		array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-core-data'),
		HK_FS_VERSION
	);
	register_block_type('hk-funeral-suite/monument', array(
		'editor_script' => 'hk-fs-monument-block',
		'editor_style' => 'hk-fs-block-editor-styles',
		'attributes' => array(
			'price' => array('type' => 'string', 'default' => ''),
			'selectedCategory' => array('type' => 'string', 'default' => ''),
			'featuredImageId' => array('type' => 'number', 'default' => 0),
			'featuredImageUrl' => array('type' => 'string', 'default' => ''),
		),
	));
}
add_action('init', 'hk_fs_register_monument_block', 20);

/**
 * Save block data to post meta when the post is saved
 */
function hk_fs_save_monument_block_data($post_id, $post) {
	// Skip autosaves and revisions
	if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
		return;
	}
	
	if ($post->post_type !== 'hk_fs_monument') {
		return;
	}
	
	// Skip if this is a REST API request (Google Sheets integration)
	// The REST API handles meta field updates directly
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return;
	}

	// Check if pricing is managed by Google Sheets
	$managed_by_sheets = get_option('hk_fs_monument_price_google_sheets', false);

	$blocks = parse_blocks($post->post_content);
	$block_found = false;
	
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'hk-funeral-suite/monument') {
			$block_found = true;
			$attrs = $block['attrs'];
			
			// Save price to post meta only if not managed by Google Sheets
			if (!$managed_by_sheets && isset($attrs['price']) && $attrs['price'] !== '') {
				update_post_meta($post_id, '_hk_fs_monument_price', sanitize_text_field($attrs['price']));
			} elseif (!$managed_by_sheets && isset($attrs['price']) && $attrs['price'] === '') {
				// Don't overwrite existing meta if block attribute is empty (e.g., from imports)
				$existing_price = get_post_meta($post_id, '_hk_fs_monument_price', true);
				if (empty($existing_price)) {
					// Only clear if there's no existing value
					update_post_meta($post_id, '_hk_fs_monument_price', '');
				}
			} elseif ($managed_by_sheets && defined('WP_DEBUG') && WP_DEBUG) {
				error_log("HK Funeral Suite: Monument price update blocked - managed by Google Sheets integration");
			}
			
			// Save featured image
			if (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] > 0) {
				set_post_thumbnail($post_id, $attrs['featuredImageId']);
			} elseif (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] === 0) {
				delete_post_thumbnail($post_id);
			}
			
			break; // Process only the first instance of the block
		}
	}
	
	// If no blocks found (e.g., during import), preserve existing meta data
	// This prevents imports from being overwritten when blocks haven't been added yet
	if (!$block_found) {
		// During imports, meta fields may have been set directly - don't interfere
		$existing_price = get_post_meta($post_id, '_hk_fs_monument_price', true);
		if (!empty($existing_price) && defined('WP_DEBUG') && WP_DEBUG) {
			error_log("HK Funeral Suite: Preserving imported price data: $existing_price for post $post_id");
		}
	}
	
	// Use the shared cache purging function for consistent behavior
	if (function_exists('hk_fs_optimized_cache_purge')) {
		hk_fs_optimized_cache_purge($post_id, 'monument block save');
	}
}
add_action('save_post', 'hk_fs_save_monument_block_data', 10, 2);

/**
 * Load block data from post meta when editing
 */
function hk_fs_load_monument_block_data() {
	global $post;
	
	// Only proceed for our custom post type on edit screens
	if (!is_admin() || empty($post) || $post->post_type !== 'hk_fs_monument') {
		return;
	}
	
	// Get meta values
	$meta_values = array(
		'price' => get_post_meta($post->ID, '_hk_fs_monument_price', true),
		'is_price_managed' => get_option('hk_fs_monument_price_google_sheets', false)
	);
	
	// Get featured image data
	$featured_image_id = get_post_thumbnail_id($post->ID);
	$meta_values['featuredImageId'] = $featured_image_id ? (int)$featured_image_id : 0;
	
	// Get the image URL if we have an ID
	if ($featured_image_id) {
		$image = wp_get_attachment_image_src($featured_image_id, 'full');
		$meta_values['featuredImageUrl'] = $image ? $image[0] : '';
	} else {
		$meta_values['featuredImageUrl'] = '';
	}
	
	// Get taxonomy terms
	$category_terms = wp_get_object_terms($post->ID, 'hk_fs_monument_category');
	$category_id = !empty($category_terms) ? $category_terms[0]->term_id : '';
	
	// Add taxonomy terms to our data
	$meta_values['selectedCategory'] = $category_id;
	
	// Make sure the script is registered before localizing
	if (wp_script_is('hk-fs-monument-block', 'registered')) {
		// Enqueue the script with the data
		wp_localize_script('hk-fs-monument-block', 'hkFsMonumentData', $meta_values);
		
		// Add a small inline script to force refresh the data on page load
		wp_add_inline_script('hk-fs-monument-block', 
			'window.hkFsMonumentData = ' . json_encode($meta_values) . ';', 
			'before'
		);
	}
}
add_action('admin_enqueue_scripts', 'hk_fs_load_monument_block_data');
