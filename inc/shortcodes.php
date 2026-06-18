<?php
/**
 * Shortcodes
 *
 * Registers and handles front-end shortcodes for HK Funeral Suite.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\Shortcodes;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — register all shortcodes.
 */
function bootstrap(): void {
	add_shortcode( 'hk_formatted_price', __NAMESPACE__ . '\\formatted_price' );
	add_shortcode( 'hk_custom_field', __NAMESPACE__ . '\\custom_field' );
	add_shortcode( 'hk_content', __NAMESPACE__ . '\\cpt_content' );
	add_shortcode( 'hk_team_member_content', __NAMESPACE__ . '\\team_member_content' );
}

/**
 * Format and display a price value.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function formatted_price( $atts ): string {
	global $post;

	$atts = shortcode_atts( [
		'key'         => '',
		'post_id'     => '',
		'symbol'      => '$',
		'prefix'      => '',
		'suffix'      => '',
		'text_suffix' => '',
		'decimals'    => 0,
	], $atts, 'hk_formatted_price' );

	$post_id = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : ( isset( $post->ID ) ? $post->ID : 0 );

	if ( empty( $atts['key'] ) || empty( $post_id ) ) {
		return '';
	}

	$price = get_post_meta( $post_id, $atts['key'], true );

	if ( is_numeric( $price ) ) {
		$formatted = number_format( (float) $price, (int) $atts['decimals'] );
		$output    = '<span class="hk-item-price-container">';

		if ( ! empty( $atts['prefix'] ) ) {
			$output .= esc_html( trim( $atts['prefix'] ) ) . ' ';
		}

		$output .= '<span class="hk-item-price">' . esc_html( $atts['symbol'] . $formatted ) . '</span>';

		if ( ! empty( $atts['suffix'] ) ) {
			$output .= ' ' . esc_html( trim( $atts['suffix'] ) );
		}

		$output .= '</span>';
		return $output;
	}

	// Non-numeric value.
	$output = '<span class="hk-item-price-container">';
	$output .= '<span class="hk-item-price">' . esc_html( $price ) . '</span>';
	if ( ! empty( $atts['text_suffix'] ) ) {
		$output .= ' ' . esc_html( trim( $atts['text_suffix'] ) );
	}
	$output .= '</span>';
	return $output;
}

/**
 * Display a custom field value with optional formatting.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function custom_field( $atts ): string {
	global $post;

	$atts = shortcode_atts( [
		'key'        => '',
		'post_id'    => '',
		'format'     => '',
		'before'     => '',
		'after'      => '',
		'fallback'   => '',
		'raw'        => 'false',
		'strip_tags' => 'false',
	], $atts, 'hk_custom_field' );

	$post_id = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : ( isset( $post->ID ) ? $post->ID : 0 );

	if ( empty( $atts['key'] ) || empty( $post_id ) ) {
		return ! empty( $atts['fallback'] ) ? $atts['fallback'] : '';
	}

	$value = get_post_meta( $post_id, $atts['key'], true );

	if ( empty( $value ) && '' !== $atts['fallback'] ) {
		return $atts['fallback'];
	}

	if ( ! empty( $atts['format'] ) && ! empty( $value ) && strtotime( $value ) ) {
		$value = date_i18n( $atts['format'], strtotime( $value ) );
	}

	if ( 'true' === strtolower( $atts['strip_tags'] ) ) {
		$value = wp_strip_all_tags( $value );
	}

	if ( 'true' === strtolower( $atts['raw'] ) ) {
		return $atts['before'] . $value . $atts['after'];
	}

	$output = '<span class="hk-custom-field-container">';
	if ( ! empty( $atts['before'] ) ) {
		$output .= '<span class="hk-custom-field-before">' . $atts['before'] . '</span>';
	}
	$output .= '<span class="hk-custom-field-value">' . $value . '</span>';
	if ( ! empty( $atts['after'] ) ) {
		$output .= '<span class="hk-custom-field-after">' . $atts['after'] . '</span>';
	}
	$output .= '</span>';
	return $output;
}

/**
 * Render a funeral CPT's freeform content, stripping the HK meta block.
 *
 * Works on any HK Funeral Suite CPT (casket, urn, monument, keepsake,
 * package, staff). Parses the post's blocks, drops the editor-only
 * `hk-funeral-suite/*` meta block, renders the remaining blocks, then
 * runs the result through `the_content`.
 *
 * Use in page builders (Beaver Themer/Builder) where the raw post_content
 * field otherwise leaks Gutenberg block markup. No attributes needed in a
 * loop — it picks up the current post automatically.
 *
 * @param array|string $atts Shortcode attributes (post_id, fallback).
 * @return string
 */
function cpt_content( $atts ): string {
	$atts = shortcode_atts( [
		'post_id'  => '',
		'fallback' => '',
	], $atts, 'hk_content' );

	// Empty exclude list = strip any hk-funeral-suite/* meta block.
	return render_cpt_content( $atts, [] );
}

/**
 * Display team member content blocks (excluding the team-member block).
 *
 * Back-compat alias for {@see cpt_content()}, scoped to the staff meta
 * block so existing `[hk_team_member_content]` layouts keep working.
 *
 * @param array|string $atts Shortcode attributes (post_id, fallback).
 * @return string
 */
function team_member_content( $atts ): string {
	$atts = shortcode_atts( [
		'post_id'  => '',
		'fallback' => '',
	], $atts, 'hk_team_member_content' );

	return render_cpt_content( $atts, [ 'hk-funeral-suite/team-member' ] );
}

/**
 * Shared renderer for the content shortcodes.
 *
 * @param array $atts           Parsed attributes (post_id, fallback).
 * @param array $exclude_blocks Block names to skip. Empty means skip any
 *                              `hk-funeral-suite/*` block.
 * @return string
 */
function render_cpt_content( array $atts, array $exclude_blocks ): string {
	global $post;

	$post_id  = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : ( isset( $post->ID ) ? $post->ID : 0 );
	$fallback = (string) ( $atts['fallback'] ?? '' );

	if ( empty( $post_id ) ) {
		return $fallback;
	}

	$post_obj = get_post( $post_id );
	if ( ! $post_obj || empty( $post_obj->post_content ) ) {
		return $fallback;
	}

	$blocks = parse_blocks( $post_obj->post_content );
	if ( empty( $blocks ) ) {
		return $fallback;
	}

	$output = '';
	foreach ( $blocks as $block ) {
		$name = $block['blockName'] ?? null;

		// Drop empty separators between blocks.
		if ( null === $name && '' === trim( (string) $block['innerHTML'] ) ) {
			continue;
		}

		// Drop the HK meta block(s).
		if ( null !== $name && is_excluded_block( $name, $exclude_blocks ) ) {
			continue;
		}

		$output .= render_block( $block );
	}

	$output = trim( $output );
	if ( '' === $output ) {
		return $fallback;
	}

	return apply_filters( 'the_content', $output );
}

/**
 * Whether a block should be excluded from rendered output.
 *
 * @param string $name           Block name.
 * @param array  $exclude_blocks Explicit names to exclude. Empty means
 *                               "any hk-funeral-suite/* block".
 * @return bool
 */
function is_excluded_block( string $name, array $exclude_blocks ): bool {
	if ( ! empty( $exclude_blocks ) ) {
		return in_array( $name, $exclude_blocks, true );
	}

	return str_starts_with( $name, 'hk-funeral-suite/' );
}
