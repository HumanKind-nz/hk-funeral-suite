<?php
/**
 * Class Shortcodes
 *
 * Registers and handles front-end shortcodes for HK Funeral Suite.
 *
 * @package HK_Funeral_Suite
 */

// Prevent direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HK_Shortcodes {

	/**
	 * Initialize shortcodes.
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
	 * - suffix: Text to appear after the formatted price.
	 * - decimals: Number of decimals to display (default: 2).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Formatted price or original value.
	 */
	public static function formatted_price_shortcode( $atts ) {
		global $post;

		// Set default attributes.
		$atts = shortcode_atts( array(
			'key'      => '',
			'symbol'   => '$',   // Default currency symbol.
			'prefix'   => '',
			'suffix'   => '',
			'decimals' => 2,     // Default number of decimals.
		), $atts, 'hk_formatted_price' );

		// If no key is provided or no post context, return empty.
		if ( empty( $atts['key'] ) || ! isset( $post->ID ) ) {
			return '';
		}

		$price = get_post_meta( $post->ID, $atts['key'], true );

		// If the price is numeric, format it accordingly.
		if ( is_numeric( $price ) ) {
			$formatted_price = number_format( (float) $price, (int) $atts['decimals'] );
			$output = '<span class="hk-price-container">';

			if ( ! empty( $atts['prefix'] ) ) {
				$output .= '<span class="hk-price-prefix">' . esc_html( trim( $atts['prefix'] ) ) . '</span> ';
			}

			$output .= '<span class="hk-price">' . esc_html( $atts['symbol'] . $formatted_price ) . '</span>';

			if ( ! empty( $atts['suffix'] ) ) {
				$output .= ' <span class="hk-price-suffix">' . esc_html( trim( $atts['suffix'] ) ) . '</span>';
			}

			$output .= '</span>';
			return $output;
		}

		// If not numeric, just output the original value.
		return esc_html( $price );
	}
}

// Initialize the shortcodes.
HK_Shortcodes::init();
