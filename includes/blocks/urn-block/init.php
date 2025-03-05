<?php
/**
 * Register Urn Block
 *
 * @package    HK_Funeral_Suite
 * @subpackage Blocks
 * @version    1.0.3
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Register Urn Block
 */
function hk_fs_register_urn_block() {
	if (!function_exists('register_block_type')) {
		return;
	}
	$js_path = HK_FS_PLUGIN_URL . 'includes/blocks/urn-block/index.js';
	$js_file = HK_FS_PLUGIN_DIR . 'includes/blocks/urn-block/index.js';
	if (!file_exists($js_file)) {
		return;
	}
	wp_register_script(
		'hk-fs-urn-block',
		$js_path,
		array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-core-data'),
		HK_FS_VERSION
	);
	register_block_type('hk-funeral-suite/urn', array(
		'editor_script' => 'hk-fs-urn-block',
		'attributes' => array(
			'price' => array('type' => 'string', 'default' => ''),
			'selectedCategory' => array('type' => 'string', 'default' => ''),
			'featuredImageId' => array('type' => 'number', 'default' => 0),
			'featuredImageUrl' => array('type' => 'string', 'default' => ''),
		),
	));
}
add_action('init', 'hk_fs_register_urn_block', 20);
function hk_fs_save_urn_block_data($post_id, $post) {
	if ($post->post_type !== 'hk_fs_urn') {
		return;
	}

	// Check if pricing is managed by Google Sheets
	$managed_by_sheets = get_option('hk_fs_urn_price_google_sheets', false);

	$blocks = parse_blocks($post->post_content);
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'hk-funeral-suite/urn') {
			$attrs = $block['attrs'];
			
			// Save price to post meta only if not managed by Google Sheets
			if (!$managed_by_sheets && isset($attrs['price'])) {
				update_post_meta($post_id, '_hk_fs_urn_price', sanitize_text_field($attrs['price']));
			}
			
			// Sync featured image with actual post featured image
			if (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] > 0) {
				set_post_thumbnail($post_id, $attrs['featuredImageId']);
			} elseif (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] === 0) {
				delete_post_thumbnail($post_id);
			}
			
			// Save selected category
			if (!empty($attrs['selectedCategory'])) {
				wp_set_object_terms($post_id, intval($attrs['selectedCategory']), 'hk_fs_urn_category');
			}
			
			break; // Process only the first instance of the block
		}
	}
}
add_action('save_post', 'hk_fs_save_urn_block_data', 10, 2);
/**
 * Load block data from post meta when editing
 */
function hk_fs_load_urn_block_data() {
	global $post;
	
	// Only proceed for our custom post type on edit screens
	if (!is_admin() || empty($post) || $post->post_type !== 'hk_fs_urn') {
		return;
	}
	
	// Get meta values
	$meta_values = array(
		'price' => get_post_meta($post->ID, '_hk_fs_urn_price', true),
		'is_price_managed' => get_option('hk_fs_urn_price_google_sheets', false)
	);
	
	// Get taxonomy terms
	$category_terms = wp_get_object_terms($post->ID, 'hk_fs_urn_category');
	$category_id = !empty($category_terms) ? $category_terms[0]->term_id : '';
	
	// Add taxonomy terms to our data
	$meta_values['selectedCategory'] = $category_id;
	
	// Enqueue the script with the data
	wp_localize_script('hk-fs-urn-block', 'hkFsUrnData', $meta_values);
}
add_action('admin_enqueue_scripts', 'hk_fs_load_urn_block_data');
