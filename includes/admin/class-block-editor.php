<?php
/**
 * Block Editor Customizations
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.5
 * @Since      1.1.0
 * @changelog
 *   1.0.5 - Added template locking for required blocks
 *   1.0.4 - Previous version
 */

// Exit if accessed directly
if (!defined('WPINC')) {
	exit;
}

/**
 * Class for managing block editor customizations
 */
class HK_Funeral_Block_Editor {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Register filter to limit allowed blocks
		add_filter('allowed_block_types_all', array($this, 'filter_allowed_block_types'), 10, 2);
		
		// Add capability fix for block binding
		add_filter('map_meta_cap', array($this, 'add_block_binding_caps'), 10, 4);
		
		// Add template locking to prevent deletion of required blocks
		add_filter('block_editor_settings_all', array($this, 'add_template_lock_settings'), 10, 2);
		
		// Enqueue script to lock specific blocks
		add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_locking_script'));
	}
	
	/**
	 * Add custom capabilities for block binding
	 *
	 * @param array $caps The user's capabilities.
	 * @param string $cap The capability name.
	 * @param int $user_id The user ID.
	 * @param array $args Additional arguments passed to the capability check.
	 * @return array Modified capabilities array.
	 */
	public function add_block_binding_caps($caps, $cap, $user_id, $args) {
		// Only modify for block binding capabilities
		if ($cap === 'edit_block_binding') {
			// Map it to an existing capability for your post type
			return array('manage_funeral_content');
		}
		
		return $caps;
	}
	
	/**
	 * Filter the allowed block types for specific post types
	 *
	 * @param array|bool $allowed_blocks Array of allowed block types or boolean to enable/disable all.
	 * @param WP_Block_Editor_Context $editor_context The current block editor context.
	 * @return array|bool Filtered array of allowed block types.
	 */
	public function filter_allowed_block_types($allowed_blocks, $editor_context) {
		if (!$editor_context || !isset($editor_context->post)) {
			return $allowed_blocks;
		}
		
		$post_type = $editor_context->post->post_type;
		
		// Define common allowed blocks for all HK CPTs
		$common_blocks = array(
			'core/paragraph',
			'core/heading',
			'core/list',
			'core/list-item',
			'core/separator'
		);
		
		// Define mapping of post types to their required blocks
		$post_type_mapping = array(
			'hk_fs_package' => 'hk-funeral-suite/pricing-package',
			'hk_fs_casket'  => 'hk-funeral-suite/casket',
			'hk_fs_urn'     => 'hk-funeral-suite/urn',
			'hk_fs_staff'   => 'hk-funeral-suite/team-member'
		);
		
		// Check if this is one of our custom post types
		if (array_key_exists($post_type, $post_type_mapping)) {
			$required_block = $post_type_mapping[$post_type];
			
			// Always include the required block in allowed blocks
			return array_merge(
				array($required_block),
				$common_blocks
			);
		}
		
		// For other post types, return all blocks
		return $allowed_blocks;
	}
	
	/**
	 * Add template lock settings for our post types
	 *
	 * @param array $editor_settings The editor settings.
	 * @param WP_Block_Editor_Context $editor_context The current block editor context.
	 * @return array Modified editor settings.
	 */
	public function add_template_lock_settings($editor_settings, $editor_context) {
		if (!$editor_context || !isset($editor_context->post)) {
			return $editor_settings;
		}
		
		$post_type = $editor_context->post->post_type;
		
		// Define mapping of post types to their required blocks
		$post_type_mapping = array(
			'hk_fs_package' => 'hk-funeral-suite/pricing-package',
			'hk_fs_casket'  => 'hk-funeral-suite/casket',
			'hk_fs_urn'     => 'hk-funeral-suite/urn',
			'hk_fs_staff'   => 'hk-funeral-suite/team-member'
		);
		
		// Only apply settings to our post types
		if (array_key_exists($post_type, $post_type_mapping)) {
			// Explicitly state no template lock at the editor level
			// We'll implement more specific locking at the block level
			$editor_settings['templateLock'] = false;
		}
		
		return $editor_settings;
	}
	
	/**
	 * Enqueue script to lock specific blocks from deletion
	 */
	public function enqueue_block_locking_script() {
		$screen = get_current_screen();
		if (!$screen || !method_exists($screen, 'is_block_editor') || !$screen->is_block_editor()) {
			return;
		}
		
		$post_type = get_post_type();
		
		// Define mapping of post types to their required blocks
		$post_type_mapping = array(
			'hk_fs_package' => 'hk-funeral-suite/pricing-package',
			'hk_fs_casket'  => 'hk-funeral-suite/casket',
			'hk_fs_urn'     => 'hk-funeral-suite/urn',
			'hk_fs_staff'   => 'hk-funeral-suite/team-member'
		);
		
		// Only apply to our post types
		if (!array_key_exists($post_type, $post_type_mapping)) {
			return;
		}
		
		$required_block = $post_type_mapping[$post_type];
		
		// Register and localize the script
		wp_register_script(
			'hk-fs-block-locking',
			HK_FS_PLUGIN_URL . 'assets/js/block-locking.js',
			array('wp-blocks', 'wp-dom-ready', 'wp-edit-post', 'wp-data', 'wp-compose', 'wp-hooks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-notices'),
			HK_FS_VERSION,
			true
		);
		
		wp_localize_script('hk-fs-block-locking', 'hkFsBlockLocking', array(
			'requiredBlock' => $required_block
		));
		
		wp_enqueue_script('hk-fs-block-locking');
	}
	
	/**
	 * Check if a post already has a required block
	 *
	 * @param WP_Post $post The post object
	 * @param string $block_name The block name to check for
	 * @return bool True if the post has the required block
	 */
	private function post_has_required_block($post, $block_name) {
		if (!function_exists('parse_blocks')) {
			return false;
		}
		
		$blocks = parse_blocks($post->post_content);
		
		foreach ($blocks as $block) {
			if ($block['blockName'] === $block_name) {
				return true;
			}
		}
		
		return false;
	}
}

// Initialize the class
new HK_Funeral_Block_Editor();
