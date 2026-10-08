<?php
/**
 * Pattern: product grid block under a heading.
 *
 * Included by HKFuneralSuite\Blocks\get_pattern_content(), which supplies
 * $pattern (type, post_type, heading, namespace). The heading is an H2, so
 * the grid's category headings are H3 and product names H4.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

defined( 'WPINC' ) || exit;

$hk_fs_group_attributes = serialize_block_attributes( [
	'metadata' => [ 'name' => $pattern['heading'] ],
	'align'    => 'wide',
	'layout'   => [ 'type' => 'constrained' ],
] );

$hk_fs_grid_attributes = serialize_block_attributes( [
	'type'         => $pattern['type'],
	'headingLevel' => 4,
	'align'        => 'wide',
] );
?>
<!-- wp:group <?php echo $hk_fs_group_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialize_block_attributes() output, block comment delimiter. ?> -->
<div class="wp-block-group alignwide"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html( $pattern['heading'] ); ?></h2>
<!-- /wp:heading -->

<!-- wp:hk-funeral-suite/product-grid <?php echo $hk_fs_grid_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialize_block_attributes() output, block comment delimiter. ?> /--></div>
<!-- /wp:group -->
