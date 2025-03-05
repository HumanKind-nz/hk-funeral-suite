<?php
/**
 * Block Editor Customizations
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.0
 * @Since    1.1.0
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
			'core/list'
		);
		
		// For packages
		if ($post_type === 'hk_fs_package') {
			return array_merge(
				array('hk-funeral-suite/pricing-package'),
				$common_blocks
			);
		}
		
		// For caskets
		if ($post_type === 'hk_fs_casket') {
			return array_merge(
				array('hk-funeral-suite/casket'),
				$common_blocks
			);
		}
		
		// For urns
		if ($post_type === 'hk_fs_urn') {
			return array_merge(
				array('hk-funeral-suite/urn'),
				$common_blocks
			);
		}
		
		// For staff
		if ($post_type === 'hk_fs_staff') {
			return array_merge(
				array('hk-funeral-suite/team-member'),
				$common_blocks
			);
		}
		
		// For other post types, return all blocks
		return $allowed_blocks;
	}
}

// Initialize the class
new HK_Funeral_Block_Editor();
