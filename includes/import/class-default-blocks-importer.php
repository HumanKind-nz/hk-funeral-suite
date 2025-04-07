<?php
/**
 * HK Funeral Suite - Default Blocks Importer
 * 
 * Automatically adds required blocks to imported posts
 * 
 * @package    HK_Funeral_Suite
 * @subpackage Import
 * @version    1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

class HK_Default_Blocks_Importer {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook for All Import Pro
		add_action('pmxi_saved_post', [$this, 'add_default_blocks_after_import'], 10, 3);
		
		// Hooks for REST API - one for each CPT
		add_action('rest_insert_hk_fs_staff', [$this, 'add_default_blocks_after_api_insert'], 10, 3);
		add_action('rest_insert_hk_fs_casket', [$this, 'add_default_blocks_after_api_insert'], 10, 3);
		add_action('rest_insert_hk_fs_urn', [$this, 'add_default_blocks_after_api_insert'], 10, 3);
		add_action('rest_insert_hk_fs_package', [$this, 'add_default_blocks_after_api_insert'], 10, 3);
		add_action('rest_insert_hk_fs_monument', [$this, 'add_default_blocks_after_api_insert'], 10, 3);
		add_action('rest_insert_hk_fs_keepsake', [$this, 'add_default_blocks_after_api_insert'], 10, 3);
	}

	/**
	 * Initialize the class
	 */
	public static function init() {
		$instance = new self();
		return $instance;
	}

	/**
	 * Get default block for a specific post type
	 * 
	 * @param string $post_type The post type
	 * @return string The default block HTML
	 */
	private function get_default_block_for_post_type($post_type) {
		switch ($post_type) {
			case 'hk_fs_staff':
				return '<!-- wp:hk-funeral-suite/team-member {"position":"","qualification":"","phone":"","email":"","selectedLocation":"","selectedRole":""} /-->';
				
			case 'hk_fs_casket':
				return '<!-- wp:hk-funeral-suite/casket {"price":"","selectedCategory":""} /-->';
				
			case 'hk_fs_urn':
				return '<!-- wp:hk-funeral-suite/urn {"price":"","selectedCategory":""} /-->';
				
			case 'hk_fs_package':
				return '<!-- wp:hk-funeral-suite/pricing-package {"price":"","order":"10"} /-->';
				
			case 'hk_fs_monument':
				return '<!-- wp:hk-funeral-suite/monument {"price":"","selectedCategory":""} /-->';
				
			case 'hk_fs_keepsake':
				return '<!-- wp:hk-funeral-suite/keepsake {"price":"","selectedCategory":""} /-->';
				
			default:
				return '';
		}
	}

	/**
	 * Add default blocks after import via All Import Pro
	 * 
	 * @param int $post_id The post ID
	 * @param array $data Import data
	 * @param array $import_options Import options
	 */
	public function add_default_blocks_after_import($post_id, $data, $import_options) {
		$post_type = get_post_type($post_id);
		
		// Check if this is one of our custom post types
		if (!in_array($post_type, ['hk_fs_staff', 'hk_fs_casket', 'hk_fs_urn', 'hk_fs_package', 'hk_fs_monument', 'hk_fs_keepsake'])) {
			return;
		}
		
		// Get the default block for this post type
		$default_block = $this->get_default_block_for_post_type($post_type);
		
		if (!empty($default_block)) {
			$this->maybe_add_block_to_post($post_id, $default_block, $post_type);
		}
	}

	/**
	 * Add default blocks after REST API insertion
	 * 
	 * @param WP_Post $post The post object
	 * @param WP_REST_Request $request The request object
	 * @param bool $creating Whether this is a new post
	 */
	public function add_default_blocks_after_api_insert($post, $request, $creating) {
		// Only add blocks for newly created posts
		if ($creating) {
			$post_type = $post->post_type;
			$default_block = $this->get_default_block_for_post_type($post_type);
			
			if (!empty($default_block)) {
				$this->maybe_add_block_to_post($post->ID, $default_block, $post_type);
			}
		}
	}

	/**
	 * Add block to post if it doesn't already exist
	 * 
	 * @param int $post_id The post ID
	 * @param string $block_html The block HTML
	 * @param string $post_type The post type
	 */
	private function maybe_add_block_to_post($post_id, $block_html, $post_type) {
		// Get current content
		$post = get_post($post_id);
		$content = $post->post_content;
		
		// Define block name based on post type
		$block_name = '';
		switch ($post_type) {
			case 'hk_fs_staff':
				$block_name = 'hk-funeral-suite/team-member';
				break;
			case 'hk_fs_casket':
				$block_name = 'hk-funeral-suite/casket';
				break;
			case 'hk_fs_urn':
				$block_name = 'hk-funeral-suite/urn';
				break;
			case 'hk_fs_package':
				$block_name = 'hk-funeral-suite/pricing-package';
				break;
			case 'hk_fs_monument':
				$block_name = 'hk-funeral-suite/monument';
				break;
			case 'hk_fs_keepsake':
				$block_name = 'hk-funeral-suite/keepsake';
				break;
		}
		
		// Check if content already has our block
		if (!empty($block_name) && strpos($content, $block_name) === false) {
			// Append default block to existing content
			$new_content = $content . "\n" . $block_html;
			
			// Update the post
			wp_update_post([
				'ID' => $post_id,
				'post_content' => $new_content
			]);
			
			// Log for debugging (optional)
			error_log("Added default block to {$post_type} ID: {$post_id}");
		}
	}
}