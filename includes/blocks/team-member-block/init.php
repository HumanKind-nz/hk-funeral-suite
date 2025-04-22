<?php
/**
 * Register Team Member Block
 *
 * @package    HK_Funeral_Suite
 * @subpackage Blocks
 * @version    1.0.0
 * @since      1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register the Team Member block and its scripts
 */
function hk_fs_register_team_member_block() {
	// Skip block registration if Gutenberg is not available
	if (!function_exists('register_block_type')) {
		return;
	}

	// Get file paths
	$js_path = HK_FS_PLUGIN_URL . 'includes/blocks/team-member-block/index.js';
	$js_file = HK_FS_PLUGIN_DIR . 'includes/blocks/team-member-block/index.js';

	// Check if JS file exists
	if (!file_exists($js_file)) {
		return;
	}
	
	// Generate a unique version to avoid caching during development
	$version = HK_FS_VERSION . '.' . time(); // Use timestamp for development
	
	// Register block script with dependencies
	// Note: Make sure to include both wp-editor and wp-block-editor for compatibility
	wp_register_script(
		'hk-fs-team-member-block',
		$js_path,
		array(
			'wp-blocks',
			'wp-i18n',
			'wp-element',
			'wp-block-editor',
			'wp-editor', // Required for MediaUpload in WP 5.x
			'wp-components',
			'wp-data',
			'wp-api-fetch',
			'wp-media-utils', // Required for MediaUpload
		),
		$version
	);

	// Register the block
	register_block_type('hk-funeral-suite/team-member', array(
		'editor_script' => 'hk-fs-team-member-block',
		'editor_style' => 'hk-fs-block-editor-styles', // Use shared styles from block-styles.php
		'render_callback' => 'hk_fs_render_team_member_block',
		'attributes' => array(
			'qualification' => array(
				'type' => 'string',
				'default' => '',
			),
			'position' => array(
				'type' => 'string',
				'default' => '',
			),
			'phone' => array(
				'type' => 'string',
				'default' => '',
			),
			'email' => array(
				'type' => 'string',
				'default' => '',
			),
			'selectedLocation' => array(
				'type' => 'string',
				'default' => '',
			),
			'selectedRole' => array(
				'type' => 'string',
				'default' => '',
			),
			'featuredImageId' => array(
				'type' => 'number',
				'default' => 0,
			),
			'featuredImageUrl' => array(
				'type' => 'string',
				'default' => '',
			),
		),
	));
}
add_action('init', 'hk_fs_register_team_member_block', 20);

/**
 * Render callback for the Team Member block
 */
function hk_fs_render_team_member_block($attributes, $content) {
	// Just return empty for admin blocks
	return '';
}

/**
 * Save block data to post meta when the post is saved
 */
function hk_fs_save_team_member_block_data($post_id, $post) {
	// Skip autosaves and revisions
	if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
		return;
	}
	
	if ($post->post_type !== 'hk_fs_staff') {
		return;
	}

	// Check for and extract data from our block in the post content
	$blocks = parse_blocks($post->post_content);
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'hk-funeral-suite/team-member') {
			$attrs = $block['attrs'];
			
			// Save staff position (job title)
			if (isset($attrs['position'])) {
				update_post_meta($post_id, '_hk_fs_staff_position', sanitize_text_field($attrs['position']));
			}
			
			// Save qualification
			if (isset($attrs['qualification'])) {
				update_post_meta($post_id, '_hk_fs_staff_qualification', sanitize_text_field($attrs['qualification']));
			}
			
			// Save phone number
			if (isset($attrs['phone'])) {
				update_post_meta($post_id, '_hk_fs_staff_phone', sanitize_text_field($attrs['phone']));
			}
			
			// Save email
			if (isset($attrs['email'])) {
				update_post_meta($post_id, '_hk_fs_staff_email', sanitize_email($attrs['email']));
			}
			
			// Ensure social links are saved as expected format
			if (isset($attrs['socialLinks']) && is_array($attrs['socialLinks'])) {
				$sanitized_links = array();
				foreach ($attrs['socialLinks'] as $link) {
					if (isset($link['url']) && !empty($link['url'])) {
						$sanitized_links[] = array(
							'platform' => isset($link['platform']) ? sanitize_text_field($link['platform']) : '',
							'url' => esc_url_raw($link['url']),
							'icon' => isset($link['icon']) ? sanitize_text_field($link['icon']) : ''
						);
					}
				}
				update_post_meta($post_id, '_hk_fs_staff_social_links', $sanitized_links);
			}
			
			// Handle featured image
			if (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] > 0) {
				set_post_thumbnail($post_id, $attrs['featuredImageId']);
			} elseif (isset($attrs['featuredImageId']) && $attrs['featuredImageId'] === 0) {
				delete_post_thumbnail($post_id);
			}
			
			break; // Only process the first team member block found
		}
	}
	
	// Use the shared cache purging function for consistent behavior
	if (function_exists('hk_fs_optimized_cache_purge')) {
		hk_fs_optimized_cache_purge($post_id, 'team member block save');
	}
}
add_action('save_post', 'hk_fs_save_team_member_block_data', 10, 2);

/**
 * Load block data from post meta when editing
 */

function hk_fs_load_team_member_block_data() {
 global $post;
 
 // Only proceed for our custom post type on edit screens
 if (!is_admin() || empty($post) || $post->post_type !== 'hk_fs_staff') {
	 return;
 }
 
 // Get meta values - keep this simple like in your Casket block
 $meta_values = array(
	 'position' => get_post_meta($post->ID, '_hk_fs_staff_position', true),
	 'qualification' => get_post_meta($post->ID, '_hk_fs_staff_qualification', true),
	 'phone' => get_post_meta($post->ID, '_hk_fs_staff_phone', true),
	 'email' => get_post_meta($post->ID, '_hk_fs_staff_email', true),
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
 
 // Make sure the script is registered before localizing
 if (wp_script_is('hk-fs-team-member-block', 'registered')) {
	 // Enqueue the script with the data
	 wp_localize_script('hk-fs-team-member-block', 'hkFsTeamMemberData', $meta_values);
 } else {
	 // Log an error if the script isn't registered
	 error_log('Team Member Block script not registered when trying to localize data');
 }
}
add_action('admin_enqueue_scripts', 'hk_fs_load_team_member_block_data');
 
 