<?php
/**
 * Capabilities management for HumanKind Funeral Suite
 *
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.1.3
 * @since      1.1.9
 *
 * v1.1.3 - Added version checking and performance optimizations
 * v1.1.0 - Added custom user roles (Funeral Staff and Funeral Manager)
 * v1.0.0 - Initial class
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
		
		// Register custom roles on activation and plugin update
		add_action('init', array(__CLASS__, 'register_custom_roles'));
	}

	/**
	 * Log debug messages if debug mode is enabled
	 *
	 * @param string $message The message to log
	 */
	private static function debug_log($message) {
		if (defined('HK_FS_DEBUG') && HK_FS_DEBUG) {
			error_log('HK Funeral Suite: ' . $message);
		}
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
		// Left commented out as added in specific user roles.
		// Uncomment to let Editors add/remove CPTs
		// $editor = get_role('editor');
		// if ($editor) {
		// 	$editor->add_cap('manage_funeral_content');
		// }
		
		// Register custom roles
		self::register_custom_roles();
	}
	
	/**
	 * Register custom user roles for funeral staff
	 */
	public static function register_custom_roles() {
		// Check if roles are already set up properly
		static $roles_checked = false;
		
		// Only run once per request
		if ($roles_checked) {
			return;
		}
		
		// Check flag in transient to avoid constant updates
		$current_version = get_transient('hk_fs_roles_version');
		if ($current_version === HK_FS_VERSION) {
			$roles_checked = true;
			return;
		}

		$staff_capabilities = array(
			'read' => true,
			'edit_posts' => true,
			'delete_posts' => true,
			'publish_posts' => true,
			'upload_files' => true,
			'manage_funeral_content' => true,
		);
		
		$manager_capabilities = array(
			'read' => true,
			'read_private_posts' => true,
			'edit_posts' => true,
			'edit_others_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'delete_posts' => true,
			'delete_others_posts' => true,
			'delete_published_posts' => true,
			'delete_private_posts' => true,
			'edit_private_posts' => true,
			'manage_categories' => true,
			'moderate_comments' => true,
			'upload_files' => true,
			'manage_funeral_content' => true,
			'manage_funeral_settings' => true,
		);
		
		// Funeral Staff role - check if it exists
		$staff_role = get_role('funeral_staff');
		if ($staff_role) {
			// Role exists, update capabilities
			self::debug_log('Updating funeral_staff role capabilities');
			foreach ($staff_capabilities as $cap => $grant) {
				if ($grant) {
					$staff_role->add_cap($cap);
				} else {
					$staff_role->remove_cap($cap);
				}
			}
		} else {
			// Role doesn't exist, create it
			self::debug_log('Creating funeral_staff role');
			add_role('funeral_staff', __('Funeral Staff', 'hk-funeral-cpt'), $staff_capabilities);
		}
		
		// Funeral Manager role - check if it exists
		$manager_role = get_role('funeral_manager');
		if ($manager_role) {
			// Role exists, update capabilities
			self::debug_log('Updating funeral_manager role capabilities');
			foreach ($manager_capabilities as $cap => $grant) {
				if ($grant) {
					$manager_role->add_cap($cap);
				} else {
					$manager_role->remove_cap($cap);
				}
			}
		} else {
			// Role doesn't exist, create it
			self::debug_log('Creating funeral_manager role');
			add_role('funeral_manager', __('Funeral Manager', 'hk-funeral-cpt'), $manager_capabilities);
		}
		
		// Ensure Administrator has our capabilities too
		$admin_role = get_role('administrator');
		if ($admin_role) {
			$admin_role->add_cap('manage_funeral_content');
			$admin_role->add_cap('manage_funeral_settings');
		}
		
		// Update the version flag to prevent further updates until plugin version changes
		set_transient('hk_fs_roles_version', HK_FS_VERSION, WEEK_IN_SECONDS);
		
		// Also update the legacy option for backwards compatibility
		update_option('hk_fs_roles_updated', true);
		
		self::debug_log('Roles have been updated to version ' . HK_FS_VERSION);
		
		$roles_checked = true;
	}

	/**
	 * Remove capabilities when plugin is uninstalled
	 */
	public static function remove_capabilities() {
		$roles = array('administrator', 'editor', 'funeral_staff', 'funeral_manager');
		$capabilities = array('manage_funeral_content', 'manage_funeral_settings');
		
		foreach ($roles as $role_name) {
			$role = get_role($role_name);
			if ($role) {
				foreach ($capabilities as $cap) {
					$role->remove_cap($cap);
				}
			}
		}
		
		// Remove custom roles
		self::remove_custom_roles();
		
		// Clean up options and transients
		delete_transient('hk_fs_roles_version');
		delete_option('hk_fs_roles_updated');
	}
	
	/**
	 * Remove custom roles on uninstall
	 */
	public static function remove_custom_roles() {
		remove_role('funeral_staff');
		remove_role('funeral_manager');
	}

	/**
	 * Helper function to check capabilities
	 */
	public static function can($capability) {
		return current_user_can($capability);
	}
}
