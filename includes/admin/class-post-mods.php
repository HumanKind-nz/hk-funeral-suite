<?php
/**
 * Class Post_Mods
 *
 * Handles modifications for CPTs including admin bar behavior.
 *
 * @package HK_Funeral_Suite
 * @subpackage Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HK_Post_Mods {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'remove_admin_bar_view_link_for_cpts' ), 999 );
	}

	/**
	 * Remove "View" link from the admin bar for CPTs when public pages are disabled.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
	 */
	public static function remove_admin_bar_view_link_for_cpts( $wp_admin_bar ) {
		// Only run on admin post editing screens.
		if ( ! is_admin() || ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id   = absint( $_GET['post'] );
		$post_type = get_post_type( $post_id );

		// Map CPTs to their public setting option names.
		$cpt_settings = array(
			'hk_fs_staff'   => 'hk_fs_enable_public_staff',
			'hk_fs_casket'  => 'hk_fs_enable_public_caskets',
			'hk_fs_urn'     => 'hk_fs_enable_public_urns',
			'hk_fs_package' => 'hk_fs_enable_public_packages',
			'hk_fs_monument' => 'hk_fs_enable_public_monuments',
			'hk_fs_keepsake' => 'hk_fs_enable_public_keepsakes',
		);

		// If the current post type is not one of our CPTs, exit.
		if ( ! isset( $cpt_settings[ $post_type ] ) ) {
			return;
		}

		// Check the setting for this CPT.
		$make_public = get_option( $cpt_settings[ $post_type ], false );

		// If the setting is enabled, allow the "View" link.
		if ( $make_public ) {
			return;
		}

		// Remove the "View" node from the admin bar.
		$wp_admin_bar->remove_node( 'view' );
	}
}

// Initialize the class using the admin_init hook for better compatibility
add_action('admin_init', array('HK_Post_Mods', 'init'));
