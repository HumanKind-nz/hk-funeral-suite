<?php
/**
 * Capabilities management for HumanKind Funeral Suite
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

class HK_Funeral_Capabilities {
	/**
	 * Initialize the capabilities
	 */
	public static function init() {
		register_activation_hook(HK_FS_BASENAME, array(__CLASS__, 'register_capabilities'));
		register_uninstall_hook(HK_FS_BASENAME, array(__CLASS__, 'remove_capabilities'));
	}

	/**
	 * Register capabilities for the plugin
	 */
	public static function register_capabilities() {
		// Define capabilities
		$capabilities = array(
			'manage_funeral_content',
			'manage_funeral_settings'
		);

		// Add to administrator
		$admin = get_role('administrator');
		if ($admin) {
			foreach ($capabilities as $cap) {
				$admin->add_cap($cap);
			}
		}

		// Add content management to editor
		$editor = get_role('editor');
		if ($editor) {
			$editor->add_cap('manage_funeral_content');
		}
	}

	/**
	 * Remove capabilities when plugin is uninstalled
	 */
	public static function remove_capabilities() {
		$roles = array('administrator', 'editor');
		$capabilities = array('manage_funeral_content', 'manage_funeral_settings');
		
		foreach ($roles as $role_name) {
			$role = get_role($role_name);
			if ($role) {
				foreach ($capabilities as $cap) {
					$role->remove_cap($cap);
				}
			}
		}
	}

	/**
	 * Helper function to check capabilities
	 */
	public static function can($capability) {
		return current_user_can($capability);
	}
}