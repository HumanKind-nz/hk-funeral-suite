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
 * Display team member content blocks (excluding the team-member block).
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function team_member_content( $atts ): string {
	global $post;

	$atts = shortcode_atts( [
		'post_id'  => '',
		'fallback' => '',
	], $atts, 'hk_team_member_content' );

	$post_id = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : ( isset( $post->ID ) ? $post->ID : 0 );

	if ( empty( $post_id ) ) {
		return ! empty( $atts['fallback'] ) ? $atts['fallback'] : '';
	}

	$post_obj = get_post( $post_id );
	if ( ! $post_obj || empty( $post_obj->post_content ) ) {
		return ! empty( $atts['fallback'] ) ? $atts['fallback'] : '';
	}

	$blocks = parse_blocks( $post_obj->post_content );
	if ( empty( $blocks ) ) {
		return ! empty( $atts['fallback'] ) ? $atts['fallback'] : '';
	}

	$content_blocks = [];
	foreach ( $blocks as $block ) {
		if ( isset( $block['blockName'] ) && $block['blockName'] === 'hk-funeral-suite/team-member' ) {
			continue;
		}
		if ( is_null( $block['blockName'] ) && empty( trim( $block['innerHTML'] ) ) ) {
			continue;
		}
		$content_blocks[] = $block;
	}

	if ( empty( $content_blocks ) ) {
		return ! empty( $atts['fallback'] ) ? $atts['fallback'] : '';
	}

	$output = '';
	foreach ( $content_blocks as $block ) {
		$rendered = render_block( $block );
		if ( ! empty( $rendered ) ) {
			$output .= $rendered;
		}
	}

	if ( ! empty( $output ) ) {
		$output = apply_filters( 'the_content', $output );
	}

	return ! empty( $output ) ? $output : ( ! empty( $atts['fallback'] ) ? $atts['fallback'] : '' );
}
