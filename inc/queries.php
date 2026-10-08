<?php
/**
 * Query Loop Support
 *
 * Makes the core Query Loop block work over HK post types on block themes,
 * without making them public (products have no single pages or archives).
 *
 * Core keeps the Query block's postType only when the post type is
 * publicly viewable and silently queries posts otherwise
 * (build_query_vars_from_query_block()). HK post types are not publicly
 * queryable by default, so a loop over caskets showed blog posts. The
 * query_loop_block_query_vars filter below puts the HK post type back.
 *
 * A loop ordered by menu_order gets the catalogue order: menu_order, then
 * title. The editor preview runs through the REST API instead, so the same
 * tie-break is added to REST queries of HK post types, and the preview
 * lists products in the order visitors see.
 *
 * The editor's post type picker hides non-viewable post types, so this
 * module also registers Query Loop variations ("Caskets (HK)", "Urns (HK)"
 * and so on) that preset the post type and a product card.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\Queries;

use HKFuneralSuite\Display;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_filter( 'query_loop_block_query_vars', __NAMESPACE__ . '\\restore_post_type', 10, 2 );
	add_filter( 'get_block_type_variations', __NAMESPACE__ . '\\add_query_variations', 10, 2 );
	add_action( 'init', __NAMESPACE__ . '\\add_rest_order_filters', 20 );
}

/**
 * Whether Query Loop support is enabled.
 *
 * @return bool
 */
function is_enabled(): bool {
	/**
	 * Filters whether HK post types are restored in core Query Loops.
	 *
	 * @param bool $enabled Default true.
	 */
	return (bool) apply_filters( 'hk_fs_enable_query_loop_support', true );
}

/**
 * HK post types registered on this site.
 *
 * @return string[]
 */
function get_registered_post_types(): array {
	return array_values( array_filter( \HKFuneralSuite\PostTypes\get_all_cpt_slugs(), 'post_type_exists' ) );
}

/**
 * Put an HK post type back into a Query Loop's WP_Query arguments.
 *
 * @param array     $query Query vars built from the Query block.
 * @param \WP_Block $block The Post Template (or pagination) block, whose context carries the query.
 * @return array
 */
function restore_post_type( $query, $block ) {
	if ( ! is_array( $query ) || ! $block instanceof \WP_Block || ! is_enabled() ) {
		return $query;
	}

	$post_type = $block->context['query']['postType'] ?? '';
	if ( ! is_string( $post_type ) || ! in_array( $post_type, get_registered_post_types(), true ) ) {
		return $query;
	}

	$query['post_type'] = $post_type;

	// Catalogue order: menu_order, then title so equal ranks are stable.
	if ( 'menu_order' === ( $block->context['query']['orderBy'] ?? '' ) ) {
		$query['orderby'] = [
			'menu_order' => 'DESC' === ( $query['order'] ?? 'ASC' ) ? 'DESC' : 'ASC',
			'title'      => 'ASC',
		];
		unset( $query['order'] );
	}

	return $query;
}

/**
 * Hook the REST tie-break for each registered HK post type. Runs after
 * the post types are registered (init, priority 5).
 */
function add_rest_order_filters(): void {
	foreach ( get_registered_post_types() as $post_type ) {
		add_filter( "rest_{$post_type}_query", __NAMESPACE__ . '\\rest_catalogue_order' );
	}
}

/**
 * Give REST queries ordered by menu_order the same title tie-break as the
 * front end, so the Query Loop's editor preview matches what visitors see.
 *
 * @param array $args WP_Query arguments built from the REST request.
 * @return array
 */
function rest_catalogue_order( $args ) {
	if ( ! is_array( $args ) || ! is_enabled() || 'menu_order' !== ( $args['orderby'] ?? '' ) ) {
		return $args;
	}

	$args['orderby'] = [
		'menu_order' => 'DESC' === strtoupper( (string) ( $args['order'] ?? 'ASC' ) ) ? 'DESC' : 'ASC',
		'title'      => 'ASC',
	];
	unset( $args['order'] );

	return $args;
}

/**
 * Add a Query Loop variation per enabled product type.
 *
 * Each presets the post type and catalogue order, and a card of core
 * blocks: the featured image and title (neither linked, products have no
 * public URL) and a paragraph bound to the hk-funeral-suite/price source.
 *
 * @param array          $variations Registered variations.
 * @param \WP_Block_Type $block_type Block type.
 * @return array
 */
function add_query_variations( $variations, $block_type ) {
	if ( ! is_array( $variations ) || ! $block_type instanceof \WP_Block_Type || 'core/query' !== $block_type->name || ! is_enabled() ) {
		return $variations;
	}

	$labels = [
		'casket'   => [ __( 'Caskets (HK)', 'hk-funeral-suite' ), __( 'Caskets in catalogue order, as cards with image, name and price.', 'hk-funeral-suite' ) ],
		'urn'      => [ __( 'Urns (HK)', 'hk-funeral-suite' ), __( 'Urns in catalogue order, as cards with image, name and price.', 'hk-funeral-suite' ) ],
		'monument' => [ __( 'Monuments (HK)', 'hk-funeral-suite' ), __( 'Monuments in catalogue order, as cards with image, name and price.', 'hk-funeral-suite' ) ],
		'keepsake' => [ __( 'Keepsakes (HK)', 'hk-funeral-suite' ), __( 'Keepsakes in catalogue order, as cards with image, name and price.', 'hk-funeral-suite' ) ],
	];

	foreach ( Display\get_enabled_product_types() as $type ) {
		$namespace = 'hk-funeral-suite/' . $type . 's';

		$variations[] = [
			'name'            => $namespace,
			'title'           => $labels[ $type ][0],
			'description'     => $labels[ $type ][1],
			'category'        => 'hk-funeral-suite',
			'icon'            => 'grid-view',
			'keywords'        => [ $type, 'hk', 'products', 'catalogue' ],
			'scope'           => [ 'inserter' ],
			'isActive'        => [ 'namespace' ],
			// The post type picker would offer only public types, and the
			// order picker has no catalogue order, so both are hidden.
			'allowedControls' => [ 'taxQuery', 'search', 'offset' ],
			'attributes'      => [
				'namespace' => $namespace,
				'metadata'  => [ 'name' => $labels[ $type ][0] ],
				'query'     => [
					'perPage'  => 100,
					'pages'    => 0,
					'offset'   => 0,
					'postType' => Display\get_product_types()[ $type ]['post_type'],
					'order'    => 'asc',
					'orderBy'  => 'menu_order',
					'author'   => '',
					'search'   => '',
					'exclude'  => [],
					'sticky'   => '',
					'inherit'  => false,
				],
			],
			'innerBlocks'     => get_card_template(),
		];
	}

	return $variations;
}

/**
 * The product card inside the variations, as an inner blocks template.
 *
 * @return array
 */
function get_card_template(): array {
	return [
		[
			'core/post-template',
			[
				'layout' => [
					'type'        => 'grid',
					'columnCount' => 3,
				],
			],
			[
				[
					'core/post-featured-image',
					[
						'isLink'      => false,
						'aspectRatio' => '4/3',
					],
				],
				[
					'core/post-title',
					[
						'level'  => 3,
						'isLink' => false,
					],
				],
				[
					'core/paragraph',
					[
						'className' => 'hk-product-card__price',
						'metadata'  => [
							'name'     => __( 'Price', 'hk-funeral-suite' ),
							'bindings' => [
								'content' => [
									'source' => 'hk-funeral-suite/price',
									'args'   => [ 'suffix' => __( 'incl. GST', 'hk-funeral-suite' ) ],
								],
							],
						],
					],
				],
			],
		],
	];
}
