<?php
/**
 * Class Shortcodes
 *
 * Registers and handles front-end shortcodes for HK Funeral Suite.
 *
 * Version: 1.1
 * Changelog: Updated container and price classes to 'hk-item-price-container' and 'hk-item-price', removed extra spans for prefix/suffix, and added the text_suffix option.
 *
 * @package HK_Funeral_Suite
 */

// Prevent direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HK_Shortcodes {

	/**
	 * Initialise shortcodes.
	 */
	public static function init() {
		add_shortcode( 'hk_formatted_price', array( __CLASS__, 'formatted_price_shortcode' ) );
	}

	/**
	 * Format the price output based on meta value.
	 *
	 * Attributes:
	 * - key: The meta key to retrieve the price.
	 * - symbol: Currency symbol (default: '$').
	 * - prefix: Text to appear before the formatted price.
	 * - suffix: Text to appear after the formatted price (for numeric fields).
	 * - text_suffix: Suffix to append when the meta value is a non-numeric string.
	 * - decimals: Number of decimals to display (default: 2).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Formatted price or original value.
	 */
	public static function formatted_price_shortcode( $atts ) {
		global $post;

		// Set default attributes.
		$atts = shortcode_atts( array(
			'key'         => '',
			'symbol'      => '$',   // Default currency symbol.
			'prefix'      => '',
			'suffix'      => '',
			'text_suffix' => '',    // Suffix for non-numeric string fields.
			'decimals'    => 2,     // Default number of decimals.
		), $atts, 'hk_formatted_price' );

		// If no key is provided or no post context, return empty.
		if ( empty( $atts['key'] ) || ! isset( $post->ID ) ) {
			return '';
		}

		$price = get_post_meta( $post->ID, $atts['key'], true );

		// Check if the price is numeric.
		if ( is_numeric( $price ) ) {
			$formatted_price = number_format( (float) $price, (int) $atts['decimals'] );
			
			// Begin main container with new class name.
			$output = '<span class="hk-item-price-container">';
			
			// Add prefix text directly (no span) if provided.
			if ( ! empty( $atts['prefix'] ) ) {
				$output .= esc_html( trim( $atts['prefix'] ) ) . ' ';
			}

			// Output the formatted price inside a span with new class name.
			$output .= '<span class="hk-item-price">' . esc_html( $atts['symbol'] . $formatted_price ) . '</span>';

			// Append suffix text directly (no span) if provided.
			if ( ! empty( $atts['suffix'] ) ) {
				$output .= ' ' . esc_html( trim( $atts['suffix'] ) );
			}

			$output .= '</span>'; // Close main container.
			return $output;
		}

		// For non-numeric values, output using a container and price span,
		// then append the text_suffix if provided.
		$output = '<span class="hk-item-price-container">';
		$output .= '<span class="hk-item-price">' . esc_html( $price ) . '</span>';
		if ( ! empty( $atts['text_suffix'] ) ) {
			$output .= ' ' . esc_html( trim( $atts['text_suffix'] ) );
		}
		$output .= '</span>';
		return $output;
	}
}

// Initialise the shortcodes.
HK_Shortcodes::init();
