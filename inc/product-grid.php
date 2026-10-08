<?php
/**
 * Product Grid
 *
 * One server-side renderer behind two ways in: the dynamic block
 * hk-funeral-suite/product-grid (block themes, the editor previews it with
 * ServerSideRender) and the [hk_product_grid] shortcode (Beaver Builder and
 * classic themes). Both take the same settings, so they cannot drift apart.
 *
 * The grid runs its own WP_Query, so it works for visitors even though the
 * product post types are not publicly queryable, and it needs no front-end
 * JavaScript.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\ProductGrid;

use HKFuneralSuite\Display;

defined( 'WPINC' ) || exit;

/**
 * Front-end stylesheet handle, enqueued only when a grid renders.
 */
const STYLE_HANDLE = 'hk-fs-product-grid';

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_style' );
	add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\unwrap_shortcode_paragraph' );
}

/**
 * Register the grid stylesheet. It is enqueued from the renderer, so it
 * loads only on pages that show a grid (block or shortcode). The editor
 * gets the same file through the block's editorStyle.
 */
function register_style(): void {
	$path = 'build/blocks/product-grid/style-index.css';

	if ( ! file_exists( HK_FS_PLUGIN_DIR . $path ) ) {
		return;
	}

	wp_register_style( STYLE_HANDLE, HK_FS_PLUGIN_URL . $path, [], HK_FS_VERSION );
	wp_style_add_data( STYLE_HANDLE, 'path', HK_FS_PLUGIN_DIR . $path );
}

/**
 * Unwrap a paragraph block that holds nothing but [hk_product_grid].
 *
 * The grid is a <section>, which cannot sit inside a <p>. Core's
 * shortcode_unautop() strips a bare <p> around a lone shortcode, but
 * paragraph blocks render with a class on WordPress 7.1, so it no longer
 * matches and the browser would leave a stray empty paragraph.
 *
 * @param string $block_content Rendered paragraph, shortcode not yet expanded.
 * @return string
 */
function unwrap_shortcode_paragraph( $block_content ) {
	if ( ! is_string( $block_content ) || ! str_contains( $block_content, '[hk_product_grid' ) ) {
		return $block_content;
	}

	if ( preg_match( '#^\s*<p\b[^>]*>\s*(\[hk_product_grid\b[^\]]*\])\s*</p>\s*$#', $block_content, $matches ) ) {
		return $matches[1];
	}

	return $block_content;
}

/**
 * Default settings. Keys match the block attributes in
 * src/blocks/product-grid/block.json; keep the two in step.
 *
 * @return array<string, mixed>
 */
function get_defaults(): array {
	return [
		'type'            => 'casket',
		'categories'      => [],
		'groupByCategory' => true,
		'orderBy'         => 'catalogue',
		'columns'         => 3,
		'showImage'       => true,
		'imageSize'       => 'medium_large',
		'showPrice'       => true,
		'pricePrefix'     => '',
		'priceSuffix'     => __( 'incl. GST', 'hk-funeral-suite' ),
		'poaLabel'        => '',
		'showDescription' => 'full',
		'headingLevel'    => 3,
	];
}

/**
 * Validate and fill settings from either entry point.
 *
 * @param array $args Raw settings (block attributes or mapped shortcode attributes).
 * @return array<string, mixed>
 */
function normalise_args( array $args ): array {
	$args = array_merge( get_defaults(), array_intersect_key( $args, get_defaults() ) );

	$categories = $args['categories'];
	if ( is_string( $categories ) ) {
		$categories = explode( ',', $categories );
	}

	$image_sizes = array_merge( get_intermediate_image_sizes(), [ 'full' ] );

	return [
		'type'            => sanitize_key( (string) $args['type'] ),
		'categories'      => array_values( array_filter( array_map( static fn( $slug ): string => sanitize_title( (string) $slug ), (array) $categories ) ) ),
		'groupByCategory' => to_bool( $args['groupByCategory'] ),
		'orderBy'         => in_array( $args['orderBy'], [ 'catalogue', 'price', 'title' ], true ) ? $args['orderBy'] : 'catalogue',
		'columns'         => max( 1, min( 6, (int) $args['columns'] ) ),
		'showImage'       => to_bool( $args['showImage'] ),
		'imageSize'       => in_array( $args['imageSize'], $image_sizes, true ) ? $args['imageSize'] : 'medium_large',
		'showPrice'       => to_bool( $args['showPrice'] ),
		'pricePrefix'     => sanitize_text_field( (string) $args['pricePrefix'] ),
		'priceSuffix'     => sanitize_text_field( (string) $args['priceSuffix'] ),
		// Blank means the default label, so the hk_fs_poa_label filter applies.
		'poaLabel'        => '' !== trim( (string) $args['poaLabel'] ) ? sanitize_text_field( (string) $args['poaLabel'] ) : Display\default_poa_label(),
		'showDescription' => in_array( $args['showDescription'], [ 'none', 'excerpt', 'full' ], true ) ? $args['showDescription'] : 'full',
		'headingLevel'    => max( 2, min( 6, (int) $args['headingLevel'] ) ),
	];
}

/**
 * Read a boolean from a block attribute or a shortcode string.
 *
 * @param mixed $value Raw value: bool, "true"/"false", "yes"/"no", "1"/"0".
 * @return bool
 */
function to_bool( $value ): bool {
	return is_bool( $value ) ? $value : filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

/**
 * Block render callback. The block has no inner content.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function render_block( array $attributes ): string {
	$args = normalise_args( $attributes );

	$extra = [ 'class' => 'hk-product-grid hk-product-grid--' . $args['type'] ];
	$gap   = block_gap_value( $attributes['style']['spacing']['blockGap'] ?? null );
	if ( '' !== $gap ) {
		$extra['style'] = '--hk-product-grid-gap:' . $gap . ';';
	}

	return render( $args, get_block_wrapper_attributes( $extra ) );
}

/**
 * Turn a blockGap support value into a CSS value.
 *
 * Block gap only applies itself to blocks with layout support, so the grid
 * reads it and hands it to its own gap custom property.
 *
 * @param mixed $value "var:preset|spacing|50", a CSS length, or null.
 * @return string Safe CSS value, or empty string.
 */
function block_gap_value( $value ): string {
	if ( is_array( $value ) ) {
		$value = $value['top'] ?? '';
	}
	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}

	if ( str_starts_with( $value, 'var:' ) ) {
		$parts = explode( '|', substr( $value, 4 ) );
		return 'var(--wp--' . implode( '--', array_map( 'sanitize_key', $parts ) ) . ')';
	}

	return preg_match( '/^[0-9.]+(px|em|rem|%|vw|vh|ch)?$|^(calc|clamp|min|max|var)\([a-z0-9.,%+\-*\/ ()]+\)$/i', $value ) ? $value : '';
}

/**
 * [hk_product_grid] shortcode.
 *
 * Attributes are the block's, in snake_case: type, categories
 * (comma-separated slugs), group_by_category, order_by, columns,
 * show_image, image_size, show_price, price_prefix, price_suffix,
 * poa_label, show_description, heading_level.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function shortcode( $atts ): string {
	$map = [
		'type'              => 'type',
		'categories'        => 'categories',
		'group_by_category' => 'groupByCategory',
		'order_by'          => 'orderBy',
		'columns'           => 'columns',
		'show_image'        => 'showImage',
		'image_size'        => 'imageSize',
		'show_price'        => 'showPrice',
		'price_prefix'      => 'pricePrefix',
		'price_suffix'      => 'priceSuffix',
		'poa_label'         => 'poaLabel',
		'show_description'  => 'showDescription',
		'heading_level'     => 'headingLevel',
	];

	$defaults = [];
	foreach ( $map as $att => $key ) {
		$default          = get_defaults()[ $key ];
		$defaults[ $att ] = is_array( $default ) ? '' : $default;
	}

	$atts = shortcode_atts( $defaults, $atts, 'hk_product_grid' );

	$args = [];
	foreach ( $map as $att => $key ) {
		$args[ $key ] = $atts[ $att ];
	}

	$args = normalise_args( $args );

	$wrapper = sprintf(
		'class="%s"',
		esc_attr( 'wp-block-hk-funeral-suite-product-grid hk-product-grid hk-product-grid--' . $args['type'] )
	);

	return render( $args, $wrapper );
}

/**
 * Render a product grid.
 *
 * @param array  $args               Normalised settings ({@see normalise_args()}).
 * @param string $wrapper_attributes Attributes for the outer element, already escaped.
 * @return string
 */
function render( array $args, string $wrapper_attributes ): string {
	// A product description that itself contains a grid would recurse.
	static $rendering = false;

	if ( $rendering || ! in_array( $args['type'], Display\get_enabled_product_types(), true ) ) {
		return '';
	}

	$posts = Display\get_products( $args['type'], [
		'categories' => $args['categories'],
		'order_by'   => $args['orderBy'],
	] );

	if ( ! $posts ) {
		return '';
	}

	$rendering = true;

	try {
		$taxonomy = Display\get_product_types()[ $args['type'] ]['taxonomy'];
		$groups   = $args['groupByCategory']
			? Display\group_by_category( $posts, $taxonomy, $args['categories'] )
			: [
				[
					'term'  => null,
					'posts' => $posts,
				],
			];

		// Category headings only help when there is more than one group,
		// or the one group is a real category.
		$show_headings = $args['groupByCategory'] && ( count( $groups ) > 1 || null !== $groups[0]['term'] );

		// Card titles sit one level below the category headings.
		$card_level     = $show_headings ? max( 3, $args['headingLevel'] ) : $args['headingLevel'];
		$category_level = $card_level - 1;

		$html  = '<section ' . $wrapper_attributes . '>';
		$first = true;

		foreach ( $groups as $group ) {
			if ( $show_headings ) {
				$label = $group['term'] ? $group['term']->name : uncategorised_label();
				$html .= sprintf(
					'<h%1$d class="hk-product-grid__category">%2$s</h%1$d>',
					$category_level,
					esc_html( $label )
				);
			}

			$html .= sprintf(
				'<ul class="hk-product-grid__items" role="list" style="%s">',
				esc_attr( '--hk-columns:' . $args['columns'] )
			);

			foreach ( array_values( $group['posts'] ) as $index => $post ) {
				// The first row of the first group is usually in view, so it is
				// not lazy-loaded.
				$eager = $first && $index < $args['columns'];
				$html .= render_card( $post, $args, $card_level, $eager );
			}

			$html .= '</ul>';
			$first = false;
		}

		$html .= '</section>';
	} finally {
		$rendering = false;
	}

	wp_enqueue_style( STYLE_HANDLE );

	return $html;
}

/**
 * Heading for products without a category, when the grid shows categories.
 *
 * @return string
 */
function uncategorised_label(): string {
	/**
	 * Filters the heading for products that have no category.
	 *
	 * @param string $label Default "Other".
	 */
	return (string) apply_filters( 'hk_fs_product_grid_uncategorised_label', __( 'Other', 'hk-funeral-suite' ) );
}

/**
 * One product card.
 *
 * @param \WP_Post $post  Product.
 * @param array    $args  Normalised settings.
 * @param int      $level Heading level for the product name.
 * @param bool     $eager Whether the image should load without lazy-loading.
 * @return string
 */
function render_card( \WP_Post $post, array $args, int $level, bool $eager ): string {
	$title = get_the_title( $post );

	$html = sprintf( '<li class="hk-product-card"%s>', card_id_attribute( $post->ID ) );

	if ( $args['showImage'] ) {
		$html .= render_image( $post, $args['imageSize'], wp_strip_all_tags( $title ), $eager );
	}

	$html .= sprintf(
		'<h%1$d class="hk-product-card__title">%2$s</h%1$d>',
		$level,
		esc_html( $title )
	);

	if ( $args['showPrice'] ) {
		$html .= render_price( $post->ID, $args );
	}

	if ( 'none' !== $args['showDescription'] ) {
		$html .= render_description( $post, $args['showDescription'] );
	}

	return $html . '</li>';
}

/**
 * The card's id attribute: a stable "hk-product-{ID}" anchor (for links
 * and structured data). Given once per request, so two grids of the same
 * products on one page do not repeat an id.
 *
 * @param int $post_id Post ID.
 * @return string Attribute with a leading space, or empty string.
 */
function card_id_attribute( int $post_id ): string {
	static $used = [];

	if ( isset( $used[ $post_id ] ) ) {
		return '';
	}

	$used[ $post_id ] = true;

	return sprintf( ' id="%s"', esc_attr( 'hk-product-' . $post_id ) );
}

/**
 * The card image.
 *
 * @param \WP_Post $post  Product.
 * @param string   $size  Image size.
 * @param string   $title Plain-text product name, the alt fallback.
 * @param bool     $eager Skip lazy-loading.
 * @return string
 */
function render_image( \WP_Post $post, string $size, string $title, bool $eager ): string {
	$thumbnail_id = (int) get_post_thumbnail_id( $post );
	if ( ! $thumbnail_id ) {
		return '';
	}

	$alt = trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );

	$image = wp_get_attachment_image( $thumbnail_id, $size, false, [
		'class'   => 'hk-product-card__image',
		'alt'     => '' !== $alt ? $alt : $title,
		'loading' => $eager ? false : 'lazy',
	] );

	return $image ? '<figure class="hk-product-card__media">' . $image . '</figure>' : '';
}

/**
 * The card price line. Prefix and suffix apply to numeric prices only:
 * "From $3,595 incl. GST", but plain "Price on application".
 *
 * Reuses the hk-item-price classes of [hk_formatted_price] so existing
 * site CSS carries over.
 *
 * @param int   $post_id Post ID.
 * @param array $args    Normalised settings.
 * @return string Empty string when there is no price to show.
 */
function render_price( int $post_id, array $args ): string {
	$parts = Display\price_parts( $post_id, $args['type'], [ 'poa_label' => $args['poaLabel'] ] );

	if ( '' === $parts['text'] ) {
		return '';
	}

	$inner = '<span class="hk-item-price">' . esc_html( $parts['text'] ) . '</span>';

	if ( 'numeric' === $parts['state'] ) {
		if ( '' !== trim( $args['pricePrefix'] ) ) {
			$inner = esc_html( trim( $args['pricePrefix'] ) ) . ' ' . $inner;
		}
		if ( '' !== trim( $args['priceSuffix'] ) ) {
			$inner .= ' ' . esc_html( trim( $args['priceSuffix'] ) );
		}
	}

	return sprintf(
		'<p class="hk-product-card__price hk-product-card__price--%1$s"><span class="hk-item-price-container">%2$s</span></p>',
		esc_attr( $parts['state'] ),
		$inner
	);
}

/**
 * The card description: the product's content with the HK meta block
 * stripped (the same renderer as [hk_content]), in full or as a short
 * plain-text excerpt.
 *
 * @param \WP_Post $post Product.
 * @param string   $mode 'full' or 'excerpt'.
 * @return string
 */
function render_description( \WP_Post $post, string $mode ): string {
	$content = \HKFuneralSuite\Shortcodes\render_cpt_content( [ 'post_id' => $post->ID ], [] );

	if ( 'excerpt' === $mode ) {
		/**
		 * Filters the number of words in a product grid excerpt.
		 *
		 * @param int $length Default 25.
		 */
		$length = (int) apply_filters( 'hk_fs_product_grid_excerpt_length', 25 );
		$text   = has_excerpt( $post ) ? $post->post_excerpt : $content;
		$text   = wp_trim_words( $text, $length, '&hellip;' );

		return '' === $text ? '' : '<div class="hk-product-card__description"><p>' . wp_kses_post( $text ) . '</p></div>';
	}

	return '' === trim( $content ) ? '' : '<div class="hk-product-card__description">' . wp_kses_post( $content ) . '</div>';
}
