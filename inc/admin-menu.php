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
 * Users whose only funeral role is Funeral Staff add and remove funeral notices
 * and change their own password, nothing else, until a site moves to FireHawk.
 * tidy_funeral_staff_menu() hides Posts, the suite post types and Relevanssi from
 * them, and tidy_funeral_staff_new_menu() takes the same types out of the admin
 * bar's New menu. Funeral Managers, Website Owners and Administrators keep
 * everything. Cosmetic, like tidy_staff_menu(): the screens still open by URL.
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

/**
 * Whether the current user's only funeral role is Funeral Staff.
 *
 * @return bool
 */
function is_funeral_staff_only(): bool {
	if ( current_user_can( 'manage_options' ) ) {
		return false;
	}
	$roles = (array) wp_get_current_user()->roles;
	if ( ! in_array( 'funeral_staff', $roles, true ) ) {
		return false;
	}
	/**
	 * Filters the roles that keep the full menu even when the user is also Funeral Staff.
	 *
	 * @param string[] $roles Role slugs.
	 */
	$full = (array) apply_filters( 'hk_fs_full_menu_roles', [ 'administrator', 'editor', 'owner', 'funeral_manager' ] );
	return ! array_intersect( $full, $roles );
}

/**
 * Post types Funeral Staff do not work on.
 *
 * @return string[]
 */
function funeral_staff_hidden_types(): array {
	/**
	 * Filters the post types hidden from Funeral Staff menus.
	 *
	 * @param string[] $types Post type slugs. Default Posts and the suite post types.
	 */
	return array_map( 'strval', (array) apply_filters( 'hk_fs_funeral_staff_hidden_types', [ 'post', 'hk_fs_staff', 'hk_fs_package', 'hk_fs_casket', 'hk_fs_urn', 'hk_fs_monument', 'hk_fs_keepsake' ] ) );
}

add_action( 'admin_menu', __NAMESPACE__ . '\\tidy_funeral_staff_menu', 9999 );

/**
 * Hide everything but notices, Media and Profile from Funeral Staff.
 */
function tidy_funeral_staff_menu(): void {
	if ( ! is_funeral_staff_only() ) {
		return;
	}
	$menus = [];
	foreach ( funeral_staff_hidden_types() as $type ) {
		$menus[] = 'post' === $type ? 'edit.php' : 'edit.php?post_type=' . $type;
	}
	// Relevanssi's top-level slug is its plugin path (relevanssi/ or relevanssi-premium/), shown
	// to staff only because of its Admin search submenu. Match it by name in the menu list.
	global $menu;
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && false !== strpos( (string) $item[2], 'relevanssi' ) ) {
			$menus[] = (string) $item[2];
		}
	}
	/**
	 * Filters the menu slugs hidden from Funeral Staff.
	 *
	 * @param string[] $menus Top-level menu slugs.
	 */
	foreach ( (array) apply_filters( 'hk_fs_funeral_staff_hidden_menus', $menus ) as $slug ) {
		remove_menu_page( (string) $slug );
	}
}

add_action( 'admin_bar_menu', __NAMESPACE__ . '\\tidy_funeral_staff_new_menu', 999 );

/**
 * Take the same post types out of the admin bar's New menu for Funeral Staff.
 *
 * @param \WP_Admin_Bar $bar The admin bar.
 */
function tidy_funeral_staff_new_menu( $bar ): void {
	if ( ! $bar instanceof \WP_Admin_Bar || ! is_funeral_staff_only() ) {
		return;
	}
	foreach ( funeral_staff_hidden_types() as $type ) {
		$bar->remove_node( 'new-' . $type );
	}
}

