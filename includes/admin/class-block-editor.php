<?php
/**
 * Block Editor Customizations
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.4
 * @Since      1.1.0
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
			
			// Check if post already has the required block
			$has_required_block = $this->post_has_required_block($editor_context->post, $required_block);
			
			// Return the allowed blocks
			return array_merge(
				// Only include the required block if it doesn't exist already
				$has_required_block ? array() : array($required_block),
				$common_blocks
			);
		}
		
		// For other post types, return all blocks
		return $allowed_blocks;
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
