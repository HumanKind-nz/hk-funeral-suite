<?php
/**
 * Display Helpers
 *
 * Shared read-side helpers for showing products on the front end: the
 * product query in catalogue order, price formatting with its four states,
 * and category grouping. Used by the product grid block and shortcode and
 * by the block bindings sources, so every display path formats a price the
 * same way.
 *
 * The existing [hk_formatted_price] shortcode deliberately does not use
 * these helpers. Beaver Builder layouts depend on its exact markup.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\Display;

defined( 'WPINC' ) || exit;

/**
 * Upper bound on products fetched for one grid. A site's whole range of
 * one type is well under this; the cap only guards against runaway pages.
 */
const MAX_PRODUCTS = 200;

/**
 * Product types the display layer knows about, keyed by type.
 *
 * @return array<string, array{settings_key: string, post_type: string, taxonomy: string}>
 */
function get_product_types(): array {
	return [
		'casket'   => [
			'settings_key' => 'caskets',
			'post_type'    => 'hk_fs_casket',
			'taxonomy'     => 'hk_fs_casket_category',
		],
		'urn'      => [
			'settings_key' => 'urns',
			'post_type'    => 'hk_fs_urn',
			'taxonomy'     => 'hk_fs_urn_category',
		],
		'monument' => [
			'settings_key' => 'monuments',
			'post_type'    => 'hk_fs_monument',
			'taxonomy'     => 'hk_fs_monument_category',
		],
		'keepsake' => [
			'settings_key' => 'keepsakes',
			'post_type'    => 'hk_fs_keepsake',
			'taxonomy'     => 'hk_fs_keepsake_category',
		],
	];
}

/**
 * Product types whose CPT is enabled and registered on this site.
 *
 * @return string[] Types, e.g. [ 'casket', 'urn' ].
 */
function get_enabled_product_types(): array {
	$enabled = [];

	foreach ( get_product_types() as $type => $def ) {
		if ( post_type_exists( $def['post_type'] ) ) {
			$enabled[] = $type;
		}
	}

	return $enabled;
}

/**
 * Price meta key for a type that carries a price (products and packages).
 *
 * @param string $type Type, e.g. 'casket' or 'package'.
 * @return string Empty string for a type without a price.
 */
function price_meta_key( string $type ): string {
	$priced = array_merge( array_keys( get_product_types() ), [ 'package' ] );
	return in_array( $type, $priced, true ) ? "_hk_fs_{$type}_price" : '';
}

/**
 * Default label shown in place of a POA price.
 *
 * @return string
 */
function default_poa_label(): string {
	/**
	 * Filters the label shown for a price on application.
	 *
	 * @param string $label Default "Price on application".
	 */
	return (string) apply_filters( 'hk_fs_poa_label', __( 'Price on application', 'hk-funeral-suite' ) );
}

/**
 * Published products of one type, in catalogue order.
 *
 * Catalogue order is menu_order ascending, then title. The catalogue
 * writes menu_order as a category-major rank (category, then price, POA
 * last), so this one sort gives the catalogue's display order.
 *
 * @param string $type Product type, e.g. 'casket'.
 * @param array  $opts {
 *     Optional.
 *
 *     @type string[] $categories Category slugs to include. Empty means all.
 *     @type string   $order_by   'catalogue' (default), 'price' or 'title'.
 *     @type int      $limit      Maximum products, capped at MAX_PRODUCTS.
 * }
 * @return \WP_Post[]
 */
function get_products( string $type, array $opts = [] ): array {
	$types = get_product_types();
	if ( ! isset( $types[ $type ] ) || ! post_type_exists( $types[ $type ]['post_type'] ) ) {
		return [];
	}

	$opts = wp_parse_args( $opts, [
		'categories' => [],
		'order_by'   => 'catalogue',
		'limit'      => MAX_PRODUCTS,
	] );

	$args = [
		'post_type'              => $types[ $type ]['post_type'],
		'post_status'            => 'publish',
		'posts_per_page'         => max( 1, min( (int) $opts['limit'], MAX_PRODUCTS ) ),
		'orderby'                => [
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		],
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	];

	if ( 'title' === $opts['order_by'] ) {
		$args['orderby'] = [
			'title'      => 'ASC',
			'menu_order' => 'ASC',
		];
	}

	$categories = array_values( array_filter( array_map( 'sanitize_title', (array) $opts['categories'] ) ) );
	if ( $categories ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One small, capped query per grid.
		$args['tax_query'] = [
			[
				'taxonomy' => $types[ $type ]['taxonomy'],
				'field'    => 'slug',
				'terms'    => $categories,
			],
		];
	}

	$query = new \WP_Query( $args );
	update_post_thumbnail_cache( $query );

	$posts = $query->posts;

	if ( 'price' === $opts['order_by'] ) {
		$posts = sort_by_price( $posts, $type );
	}

	return $posts;
}

/**
 * Sort posts by price: numeric ascending, then other text, then POA, then
 * no price. Ties keep their incoming (catalogue) order.
 *
 * Sorting in PHP avoids meta_value_num, which reads "POA" as 0 and puts
 * it first.
 *
 * @param \WP_Post[] $posts Posts in catalogue order.
 * @param string     $type  Product type.
 * @return \WP_Post[]
 */
function sort_by_price( array $posts, string $type ): array {
	$rank = [
		'numeric' => 0,
		'text'    => 1,
		'poa'     => 2,
		'empty'   => 3,
	];

	$keyed = [];
	foreach ( array_values( $posts ) as $index => $post ) {
		$parts   = price_parts( $post->ID, $type );
		$keyed[] = [
			'post'   => $post,
			'rank'   => $rank[ $parts['state'] ],
			'amount' => $parts['amount'] ?? 0.0,
			'index'  => $index,
		];
	}

	usort(
		$keyed,
		static fn( array $a, array $b ): int => [ $a['rank'], $a['amount'], $a['index'] ] <=> [ $b['rank'], $b['amount'], $b['index'] ]
	);

	return array_column( $keyed, 'post' );
}

/**
 * A product's price, split into its display state.
 *
 * - numeric: "$" plus the amount with thousands separators, e.g. "$3,595".
 * - poa: the stored literal "POA" (any case), shown as the POA label.
 * - text: any other stored text, shown verbatim (legacy "P.O.A." values).
 * - empty: no price; callers hide the price line.
 *
 * @param int    $post_id Post ID.
 * @param string $type    Type that owns the price, e.g. 'casket' or 'package'.
 * @param array  $opts {
 *     Optional.
 *
 *     @type string $poa_label Label for POA. Default {@see default_poa_label()}.
 *     @type int    $decimals  Decimal places for numeric prices. Default 0.
 *     @type string $symbol    Currency symbol. Default '$'.
 * }
 * @return array{state: string, text: string, amount: ?float}
 */
function price_parts( int $post_id, string $type, array $opts = [] ): array {
	$opts = wp_parse_args( $opts, [
		'poa_label' => null,
		'decimals'  => 0,
		'symbol'    => '$',
	] );

	$key = price_meta_key( $type );
	$raw = '' !== $key ? get_post_meta( $post_id, $key, true ) : '';
	$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';

	if ( '' === $raw ) {
		return [
			'state'  => 'empty',
			'text'   => '',
			'amount' => null,
		];
	}

	if ( is_numeric( $raw ) ) {
		$amount = (float) $raw;
		return [
			'state'  => 'numeric',
			'text'   => (string) $opts['symbol'] . number_format( $amount, max( 0, (int) $opts['decimals'] ) ),
			'amount' => $amount,
		];
	}

	if ( 0 === strcasecmp( $raw, 'POA' ) ) {
		return [
			'state'  => 'poa',
			'text'   => null === $opts['poa_label'] ? default_poa_label() : (string) $opts['poa_label'],
			'amount' => null,
		];
	}

	return [
		'state'  => 'text',
		'text'   => $raw,
		'amount' => null,
	];
}

/**
 * Group posts by their category.
 *
 * Groups come out in the order their products first appear in catalogue
 * order (lowest menu_order first), so no term-order meta is needed: the
 * catalogue's ranks are category-major. Groups whose lowest menu_order is
 * equal (sites not on the catalogue, where every product is 0) are ordered
 * by category name. Products without a category form one last group with
 * a null term.
 *
 * A product with several categories is grouped under the first of them by
 * name, preferring one listed in $prefer (the grid's category filter).
 *
 * @param \WP_Post[] $posts    Posts in display order; order within a group is kept.
 * @param string     $taxonomy Category taxonomy.
 * @param string[]   $prefer   Optional term slugs to prefer when a post has several.
 * @return array<int, array{term: ?\WP_Term, posts: \WP_Post[]}>
 */
function group_by_category( array $posts, string $taxonomy, array $prefer = [] ): array {
	$groups        = [];
	$uncategorised = [];

	foreach ( $posts as $post ) {
		$terms = get_the_terms( $post, $taxonomy );

		if ( ! is_array( $terms ) || ! $terms ) {
			$uncategorised[] = $post;
			continue;
		}

		usort( $terms, static fn( \WP_Term $a, \WP_Term $b ): int => strnatcasecmp( $a->name, $b->name ) );

		$term = $terms[0];
		if ( $prefer ) {
			foreach ( $terms as $candidate ) {
				if ( in_array( $candidate->slug, $prefer, true ) ) {
					$term = $candidate;
					break;
				}
			}
		}

		if ( ! isset( $groups[ $term->term_id ] ) ) {
			$groups[ $term->term_id ] = [
				'term'  => $term,
				'posts' => [],
				'rank'  => (int) $post->menu_order,
			];
		}

		$groups[ $term->term_id ]['posts'][] = $post;
		$groups[ $term->term_id ]['rank']    = min( $groups[ $term->term_id ]['rank'], (int) $post->menu_order );
	}

	uasort(
		$groups,
		static fn( array $a, array $b ): int => [ $a['rank'], strtolower( $a['term']->name ) ] <=> [ $b['rank'], strtolower( $b['term']->name ) ]
	);

	$result = [];
	foreach ( $groups as $group ) {
		$result[] = [
			'term'  => $group['term'],
			'posts' => $group['posts'],
		];
	}

	if ( $uncategorised ) {
		$result[] = [
			'term'  => null,
			'posts' => $uncategorised,
		];
	}

	return $result;
}
