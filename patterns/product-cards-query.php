<?php
/**
 * Pattern: Query Loop product cards built from core blocks.
 *
 * The same card as the "Caskets (HK)" style Query Loop variations
 * (inc/queries.php): featured image and title, neither linked, and a
 * paragraph bound to the hk-funeral-suite/price source. The query's
 * namespace matches the variation, so the editor shows it as one.
 *
 * Included by HKFuneralSuite\Blocks\get_pattern_content(), which supplies
 * $pattern (type, post_type, heading, namespace).
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

defined( 'WPINC' ) || exit;

$hk_fs_query_attributes = serialize_block_attributes( [
	'query'     => [
		'perPage'  => 100,
		'pages'    => 0,
		'offset'   => 0,
		'postType' => $pattern['post_type'],
		'order'    => 'asc',
		'orderBy'  => 'menu_order',
		'author'   => '',
		'search'   => '',
		'exclude'  => [],
		'sticky'   => '',
		'inherit'  => false,
	],
	'namespace' => $pattern['namespace'],
	'metadata'  => [ 'name' => $pattern['heading'] ],
	'align'     => 'wide',
] );

$hk_fs_price_attributes = serialize_block_attributes( [
	'metadata'  => [
		'name'     => __( 'Price', 'hk-funeral-suite' ),
		'bindings' => [
			'content' => [
				'source' => 'hk-funeral-suite/price',
				'args'   => [ 'suffix' => __( 'incl. GST', 'hk-funeral-suite' ) ],
			],
		],
	],
	'className' => 'hk-product-card__price',
] );
?>
<!-- wp:query <?php echo $hk_fs_query_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialize_block_attributes() output, block comment delimiter. ?> -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":false,"aspectRatio":"4/3"} /-->

<!-- wp:post-title {"level":3,"isLink":false} /-->

<!-- wp:paragraph <?php echo $hk_fs_price_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialize_block_attributes() output, block comment delimiter. ?> -->
<p class="hk-product-card__price"></p>
<!-- /wp:paragraph -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
