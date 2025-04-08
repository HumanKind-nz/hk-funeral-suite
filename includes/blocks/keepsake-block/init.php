<?php
/**
 * Register Keepsake Block
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
 * Register Keepsake Block
 */
function hk_fs_register_keepsake_block() {
	if (!function_exists('register_block_type')) {
		return;
	}
	$js_path = HK_FS_PLUGIN_URL . 'includes/blocks/keepsake-block/index.js';
	$js_file = HK_FS_PLUGIN_DIR . 'includes/blocks/keepsake-block/index.js';
	if (!file_exists($js_file)) {
		return;
	}
	wp_register_script(
		'hk-fs-keepsake-block',
		$js_path,
		array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-core-data'),
		HK_FS_VERSION
	);
	register_block_type('hk-funeral-suite/keepsake', array(
		'editor_script' => 'hk-fs-keepsake-block',
		'editor_style' => 'hk-fs-block-editor-styles',
		'attributes' => array(
			'price' => array('type' => 'string', 'default' => ''),
			'selectedCategory' => array('type' => 'string', 'default' => ''),
			'featuredImageId' => array('type' => 'number', 'default' => 0),
			'featuredImageUrl' => array('type' => 'string', 'default' => ''),
			'productCode' => array('type' => 'string', 'default' => ''),
			'metal' => array('type' => 'string', 'default' => ''),
			'stones' => array('type' => 'string', 'default' => ''),
		),
	));
}
add_action('init', 'hk_fs_register_keepsake_block', 20);

function hk_fs_save_keepsake_block_data($post_id, $post) {
	if ($post->post_type !== 'hk_fs_keepsake') {
		return;
	}

	// Check if pricing is managed by Google Sheets
	$managed_by_sheets = get_option('hk_fs_keepsake_price_google_sheets', false);

	$blocks = parse_blocks($post->post_content);
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'hk-funeral-suite/keepsake') {
			$attrs = $block['attrs'];
			
			// Save price to post meta only if not managed by Google Sheets
			if (!$managed_by_sheets && isset($attrs['price'])) {
				update_post_meta($post_id, '_hk_fs_keepsake_price', sanitize_text_field($attrs['price']));
			}
			
			// Sync featured image with actual post featured image
			if (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] > 0) {
				set_post_thumbnail($post_id, $attrs['featuredImageId']);
			} elseif (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] === 0) {
				delete_post_thumbnail($post_id);
			}
			
			// Save selected category
			if (!empty($attrs['selectedCategory'])) {
				wp_set_object_terms($post_id, intval($attrs['selectedCategory']), 'hk_fs_keepsake_category');
			}
			
			// Save product code
			if (isset($attrs['productCode'])) {
				update_post_meta($post_id, '_hk_fs_keepsake_product_code', sanitize_text_field($attrs['productCode']));
			}
			
			// Save metal
			if (isset($attrs['metal'])) {
				update_post_meta($post_id, '_hk_fs_keepsake_metal', sanitize_text_field($attrs['metal']));
			}
			
			// Save stones
			if (isset($attrs['stones'])) {
				update_post_meta($post_id, '_hk_fs_keepsake_stones', sanitize_text_field($attrs['stones']));
			}
			
			break; // Process only the first instance of the block
		}
	}
}
add_action('save_post', 'hk_fs_save_keepsake_block_data', 10, 2);

/**
 * Load block data from post meta when editing
 */
function hk_fs_load_keepsake_block_data() {
	global $post;
	
	// Only proceed for our custom post type on edit screens
	if (!is_admin() || empty($post) || $post->post_type !== 'hk_fs_keepsake') {
		return;
	}
	
	// Get meta values
	$meta_values = array(
		'price' => get_post_meta($post->ID, '_hk_fs_keepsake_price', true),
		'is_price_managed' => get_option('hk_fs_keepsake_price_google_sheets', false),
		'productCode' => get_post_meta($post->ID, '_hk_fs_keepsake_product_code', true),
		'metal' => get_post_meta($post->ID, '_hk_fs_keepsake_metal', true),
		'stones' => get_post_meta($post->ID, '_hk_fs_keepsake_stones', true),
	);
	
	// Get taxonomy terms
	$category_terms = wp_get_object_terms($post->ID, 'hk_fs_keepsake_category');
	$category_id = !empty($category_terms) ? $category_terms[0]->term_id : '';
	
	// Add taxonomy terms to our data
	$meta_values['selectedCategory'] = $category_id;
	
	// Enqueue the script with the data
	wp_localize_script('hk-fs-keepsake-block', 'hkFsKeepsakeData', $meta_values);
	
	// Add a small inline script to force refresh the price managed status on page load
	wp_add_inline_script('hk-fs-keepsake-block', 
		'window.hkFsKeepsakeData = ' . json_encode($meta_values) . ';', 
		'before'
	);
	
	// Ensure editor styles are loaded for this post type
	wp_enqueue_style(
		'hk-fs-keepsake-editor-styles',
		HK_FS_PLUGIN_URL . 'includes/blocks/assets/block-editor-styles.css',
		array('wp-edit-blocks'),
		HK_FS_VERSION . '.keepsake.1'
	);
}
add_action('admin_enqueue_scripts', 'hk_fs_load_keepsake_block_data'); 