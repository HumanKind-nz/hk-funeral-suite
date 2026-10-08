<?php
/**
 * Plugin uninstall handler.
 *
 * Removes all plugin data from the database when the plugin is deleted
 * from the WordPress admin (not just deactivated).
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin settings.
delete_option( 'hk_fs_enabled_cpts' );

// Remove public visibility settings.
$cpt_keys = [ 'staff', 'caskets', 'urns', 'packages', 'monuments', 'keepsakes' ];
foreach ( $cpt_keys as $cpt ) {
	delete_option( "hk_fs_enable_public_{$cpt}" );
}

// Remove Google Sheets integration settings.
$product_keys = [ 'package', 'casket', 'urn', 'monument', 'keepsake' ];
foreach ( $product_keys as $product ) {
	delete_option( "hk_fs_{$product}_price_google_sheets" );
}

// Remove theme/plugin compatibility settings.
delete_option( 'hk_fs_generatepress_compatibility' );
delete_option( 'hk_fs_wpbf_compatibility' );
delete_option( 'hk_fs_happyfiles_compatibility' );
delete_option( 'hk_fs_seopress_metabox_compatibility' );

// Remove migration and internal flags.
delete_option( 'hk_fs_caps_migrated_v2' );
delete_option( 'hk_fs_legacy_roles_version' );
delete_option( 'hk_fs_flush_rewrite_rules' );
delete_option( 'hk_fs_roles_updated' );

// Remove catalogue sync settings: the editor lock, the option fallback
// for the site secret, and any other hk_fs_catalogue_* option or
// transient (per-run image failure notes). Synced posts, their master
// IDs and their media are post data and stay, like every other post.
delete_option( 'hk_fs_catalogue_managed_types' );
delete_option( 'hk_fs_catalogue_secret' );

global $wpdb;
$hk_fs_catalogue_options = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall sweep; there is no API to list options by prefix.
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'hk_fs_catalogue_' ) . '%',
		$wpdb->esc_like( '_transient_hk_fs_catalogue_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_hk_fs_catalogue_' ) . '%'
	)
);
foreach ( $hk_fs_catalogue_options as $hk_fs_option ) {
	delete_option( $hk_fs_option );
}

// Remove GitHub updater transient.
delete_transient( 'hk_funeral_github_response' );

// Remove legacy transients.
delete_transient( 'hk_fs_roles_version' );

// The 1.x funeral_staff / funeral_manager roles are deliberately left in
// place: other plugins grant capabilities to them and users still hold them.

/*
 * Optionally remove all CPT posts and their meta data.
 * Uncomment the block below to delete everything on uninstall.
 * WARNING: This is destructive and cannot be undone.
 *
 * $post_types = [ 'hk_fs_staff', 'hk_fs_casket', 'hk_fs_urn', 'hk_fs_package', 'hk_fs_monument', 'hk_fs_keepsake' ];
 * foreach ( $post_types as $post_type ) {
 *     $posts = get_posts( [
 *         'post_type'   => $post_type,
 *         'numberposts' => -1,
 *         'post_status' => 'any',
 *     ] );
 *     foreach ( $posts as $post ) {
 *         wp_delete_post( $post->ID, true );
 *     }
 * }
 */
