<?php
/**
 * Admin menu tweaks on funeral sites.
 *
 * Funeral sites rarely publish blog posts, so Posts sits below Pages in the
 * Website Owner menu that the Weave Dashboard plugin builds. Without that
 * plugin the filter never runs and nothing changes.
 *
 * Switch it off per site with:
 *
 *   add_filter( 'hk_fs_posts_below_pages', '__return_false' );
 *
 * Funeral staff also lose Tools and Presto Player from their sidebar (from
 * 1.4.24). See tidy_staff_menu().
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\AdminMenu;

defined( 'ABSPATH' ) || exit;

add_filter( 'weave_dashboard_owner_menu', __NAMESPACE__ . '\\posts_below_pages' );

/**
 * Move Posts to sit straight after Pages.
 *
 * @param array $items Owner menu items keyed by id, in display order.
 * @return array
 */
function posts_below_pages( $items ) {
	$items = (array) $items;

	/**
	 * Filters whether Posts sits below Pages in the Website Owner menu.
	 *
	 * @param bool $enabled Default true.
	 */
	if ( ! apply_filters( 'hk_fs_posts_below_pages', true ) || ! isset( $items['posts'], $items['pages'] ) ) {
		return $items;
	}

	$posts = $items['posts'];
	unset( $items['posts'] );

	$out = [];
	foreach ( $items as $id => $item ) {
		$out[ $id ] = $item;
		if ( 'pages' === $id ) {
			$out['posts'] = $posts;
		}
	}

	return $out;
}

add_action( 'admin_menu', __NAMESPACE__ . '\\tidy_staff_menu', 9999 );

/**
 * Hide menus funeral staff have no use for.
 *
 * Tools needs edit_posts and Presto Player needs publish_posts, both of which
 * the legacy funeral roles keep for notices (inc/legacy-roles.php never takes a
 * capability off them), so the menus are hidden rather than the capabilities
 * removed. Cosmetic only: the screens still open by URL. Applies to Funeral
 * Staff and Funeral Manager users who are not Administrators. Runs late so
 * other plugins have added their menus first. Carried over from 1.4.24.
 */
function tidy_staff_menu(): void {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	$user = wp_get_current_user();
	if ( ! array_intersect( [ 'funeral_staff', 'funeral_manager' ], (array) $user->roles ) ) {
		return;
	}

	/**
	 * Filters the menu slugs hidden from funeral staff.
	 *
	 * @param string[] $menus Top-level menu slugs. Default Tools and Presto Player.
	 * @param \WP_User $user  The current user.
	 */
	$menus = (array) apply_filters( 'hk_fs_staff_hidden_menus', [ 'tools.php', 'presto-dashboard' ], $user );

	foreach ( $menus as $slug ) {
		remove_menu_page( (string) $slug );
	}
}
