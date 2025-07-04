<?php
/**
 * Register Pricing Package Block
 *
 * @package    HK_Funeral_Suite
 * @subpackage Blocks
 * @version    1.1.0
 * @since      1.0.1
 * @changelog
 *   1.1.0 - Added intro paragraph field
 *   1.0.0 - Initial version
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
			'intro' => array(
				'type' => 'string',
				'default' => '',
			),
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
	// Skip autosaves and revisions
	if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
		return;
	}
	
	if ($post->post_type !== 'hk_fs_package') {
		return;
	}
	
	// Skip if this is a REST API request (Google Sheets integration)
	// The REST API handles meta field updates directly
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return;
	}
	
	// Check if pricing is managed by Google Sheets
	$managed_by_sheets = get_option('hk_fs_package_price_google_sheets', false);

	$blocks = parse_blocks($post->post_content);
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'hk-funeral-suite/pricing-package') {
			$attrs = $block['attrs'];
			
			// Save package intro text (always allowed)
			if (isset($attrs['intro'])) {
				update_post_meta($post_id, '_hk_fs_package_intro', sanitize_textarea_field($attrs['intro']));
			}
			
			// Save price only if not managed by Google Sheets
			if (!$managed_by_sheets && isset($attrs['price'])) {
				update_post_meta($post_id, '_hk_fs_package_price', sanitize_text_field($attrs['price']));
			} elseif ($managed_by_sheets && defined('WP_DEBUG') && WP_DEBUG) {
				error_log('HK Funeral Suite: Block price update blocked - managed by Google Sheets integration');
			}
			
			// Save package display order (always allowed)
			if (isset($attrs['order'])) {
				update_post_meta($post_id, '_hk_fs_package_order', absint($attrs['order']));
			}
			
			// Handle featured image
			if (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] > 0) {
				set_post_thumbnail($post_id, $attrs['featuredImageId']);
			} elseif (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] === 0) {
				delete_post_thumbnail($post_id);
			}
			
			break; // Process only the first instance of the block
		}
	}
	
	// Use the shared cache purging function for consistent behavior
	if (function_exists('hk_fs_optimized_cache_purge')) {
		hk_fs_optimized_cache_purge($post_id, 'pricing package block save');
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
	
	// Always fetch fresh data from the options table to avoid stale cache
	$is_price_managed = get_option('hk_fs_package_price_google_sheets', false);
	
	// Get meta values
	$meta_values = array(
		'intro' => get_post_meta($post->ID, '_hk_fs_package_intro', true),
		'price' => get_post_meta($post->ID, '_hk_fs_package_price', true),
		'order' => get_post_meta($post->ID, '_hk_fs_package_order', true) ?: '10',
		'is_price_managed' => $is_price_managed
	);
	
	// Enqueue the script with the data
	wp_localize_script('hk-fs-pricing-package-block', 'hkFsPackageData', $meta_values);
	
	// Add a small inline script to force refresh the price managed status on page load
	wp_add_inline_script('hk-fs-pricing-package-block', 
		'window.hkFsPackageData = ' . json_encode($meta_values) . ';', 
		'before'
	);
}
add_action('admin_enqueue_scripts', 'hk_fs_load_pricing_package_block_data');
