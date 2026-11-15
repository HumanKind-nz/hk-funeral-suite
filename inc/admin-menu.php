<?php
/**
 * Admin menu order on funeral sites.
 *
 * Funeral sites rarely publish blog posts, so Posts sits below Pages in the
 * Website Owner menu that the Weave Dashboard plugin builds. Without that
 * plugin the filter never runs and nothing changes.
 *
 * Switch it off per site with:
 *
 *   add_filter( 'hk_fs_posts_below_pages', '__return_false' );
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
