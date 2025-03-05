<?php
/**
 * Register Pricing Package Block
 *
 * @package    HK_Funeral_Suite
 * @subpackage Blocks
 * @version    1.0.3
 * @since      1.0.1
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Register the Pricing Package block and its scripts
 */
function hk_fs_register_pricing_package_block() {
	// Skip block registration if Gutenberg is not available
	if (!function_exists('register_block_type')) {
		return;
	}
	// Get file paths
	$js_path = HK_FS_PLUGIN_URL . 'includes/blocks/pricing-package-block/index.js';
	$js_file = HK_FS_PLUGIN_DIR . 'includes/blocks/pricing-package-block/index.js';
	// Check if JS file exists
	if (!file_exists($js_file)) {
		return;
	}
	
	// Register block script with a version parameter to avoid caching
	wp_register_script(
		'hk-fs-pricing-package-block',
		$js_path,
		array(
			'wp-blocks',
			'wp-i18n',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-data'
		),
		HK_FS_VERSION
	);
	// Register the block
	register_block_type('hk-funeral-suite/pricing-package', array(
		'editor_script' => 'hk-fs-pricing-package-block',
		'editor_style' => 'hk-fs-block-editor-styles', // Use shared styles from block-styles.php
		'render_callback' => 'hk_fs_render_pricing_package_block',
		'attributes' => array(
			'price' => array(
				'type' => 'string',
				'default' => '',
			),
			'order' => array(
				'type' => 'string',
				'default' => '10',
			),
		),
	));
}
add_action('init', 'hk_fs_register_pricing_package_block', 20);
/**
 * Render callback for the Pricing Package block
 */
function hk_fs_render_pricing_package_block($attributes, $content) {
	// Just return empty for admin blocks
	return '';
}
/**
 * Save block data to post meta when the post is saved
 */
function hk_fs_save_pricing_package_block_data($post_id, $post) {
	// Only proceed for our custom post type
	if ($post->post_type !== 'hk_fs_package') {
		return;
	}
	
	// Check if pricing is managed by Google Sheets
	$managed_by_sheets = get_option('hk_fs_package_price_google_sheets', false);
	
	// Check if the post content has our block
	if (has_block('hk-funeral-suite/pricing-package', $post->post_content)) {
		// Parse blocks to get our data
		$blocks = parse_blocks($post->post_content);
		
		foreach ($blocks as $block) {
			if ($block['blockName'] === 'hk-funeral-suite/pricing-package') {
				$attrs = $block['attrs'];
				
				// Save price only if not managed by Google Sheets
				if (!$managed_by_sheets && isset($attrs['price'])) {
					update_post_meta($post_id, '_hk_fs_package_price', sanitize_text_field($attrs['price']));
				}
				
				// Order is always saved, regardless of Google Sheets status
				if (isset($attrs['order'])) {
					update_post_meta($post_id, '_hk_fs_package_order', absint($attrs['order']));
				}
				
				// We only need to process the first instance of our block
				break;
			}
		}
	}
}
add_action('save_post', 'hk_fs_save_pricing_package_block_data', 10, 2);
/**
 * Load block data from post meta when editing
 */
function hk_fs_load_pricing_package_block_data() {
	global $post;
	
	// Only proceed for our custom post type on edit screens
	if (!is_admin() || empty($post) || $post->post_type !== 'hk_fs_package') {
		return;
	}
	
	// Get meta values
	$meta_values = array(
		'price' => get_post_meta($post->ID, '_hk_fs_package_price', true),
		'order' => get_post_meta($post->ID, '_hk_fs_package_order', true) ?: '10',
		'is_price_managed' => get_option('hk_fs_package_price_google_sheets', false)
	);
	
	// Enqueue the script with the data
	wp_localize_script('hk-fs-pricing-package-block', 'hkFsPackageData', $meta_values);
}
add_action('admin_enqueue_scripts', 'hk_fs_load_pricing_package_block_data');
