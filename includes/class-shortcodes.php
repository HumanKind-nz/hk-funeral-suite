<?php
/**
 * Class Shortcodes
 *
 * Registers and handles front-end shortcodes for HK Funeral Suite.
 *
 * Version: 1.4.7
 * Changelog: 
 * - Added optional 'post_id' attribute to fetch meta values from a specific post instead of the current post.
 * - Added new 'hk_custom_field' shortcode for reliable custom field display in Beaver Builder
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
		add_shortcode( 'hk_custom_field', array( __CLASS__, 'custom_field_shortcode' ) );
	}

	/**
	 * Format the price output based on meta value.
	 *
	 * Attributes:
	 * - key: The meta key to retrieve the price.
	 * - post_id: The post ID to fetch the meta value from (optional, defaults to the current post).
	 * - symbol: Currency symbol (default: '$').
	 * - prefix: Text to appear before the formatted price.
	 * - suffix: Text to appear after the formatted price (for numeric fields).
	 * - text_suffix: Suffix to append when the meta value is a non-numeric string.
	 * - decimals: Number of decimals to display (default: 0).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Formatted price or original value.
	 */
	public static function formatted_price_shortcode( $atts ) {
		global $post;

		// Set default attributes.
		$atts = shortcode_atts( array(
			'key'         => '',
			'post_id'     => '',   // Allow overriding post ID
			'symbol'      => '$',  // Default currency symbol.
			'prefix'      => '',
			'suffix'      => '',
			'text_suffix' => '',   // Suffix for non-numeric string fields.
			'decimals'    => 0,    // Default number of decimals.
		), $atts, 'hk_formatted_price' );

		// Determine which post ID to use (default to current post if not provided).
		$post_id = ! empty( $atts['post_id'] ) ? intval( $atts['post_id'] ) : ( isset( $post->ID ) ? $post->ID : 0 );

		// If no valid post ID or key is provided, return empty.
		if ( empty( $atts['key'] ) || empty( $post_id ) ) {
			return '';
		}

		$price = get_post_meta( $post_id, $atts['key'], true );

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

	/**
	 * Display a custom field value with optional formatting and fallback.
	 *
	 * Attributes:
	 * - key: The meta key to retrieve.
	 * - post_id: The post ID to fetch the meta value from (optional, defaults to current post).
	 * - format: Format for date values (optional).
	 * - before: Content to display before the value (only if value exists).
	 * - after: Content to display after the value (only if value exists).
	 * - fallback: Content to display if the custom field is empty.
	 * - raw: Set to "true" to return raw value without wrapper spans (default: false).
	 * - strip_tags: Set to "true" to strip HTML tags (default: false).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Formatted custom field value.
	 */
	public static function custom_field_shortcode( $atts ) {
		global $post;

		// Set default attributes
		$atts = shortcode_atts( array(
			'key'        => '',
			'post_id'    => '',
			'format'     => '',
			'before'     => '',
			'after'      => '',
			'fallback'   => '',
			'raw'        => 'false',
			'strip_tags' => 'false',
		), $atts, 'hk_custom_field' );

		// Determine which post ID to use (default to current post if not provided)
		$post_id = ! empty( $atts['post_id'] ) ? intval( $atts['post_id'] ) : ( isset( $post->ID ) ? $post->ID : 0 );

		// If no valid post ID or key is provided, return fallback or empty
		if ( empty( $atts['key'] ) || empty( $post_id ) ) {
			return ! empty( $atts['fallback'] ) ? $atts['fallback'] : '';
		}

		// Get the custom field value
		$value = get_post_meta( $post_id, $atts['key'], true );

		// Return fallback if value is empty
		if ( empty( $value ) && '' !== $atts['fallback'] ) {
			return $atts['fallback'];
		}

		// Apply date formatting if specified and value appears to be a date
		if ( ! empty( $atts['format'] ) && ! empty( $value ) && strtotime( $value ) ) {
			$value = date_i18n( $atts['format'], strtotime( $value ) );
		}

		// Strip tags if requested
		if ( 'true' === strtolower( $atts['strip_tags'] ) ) {
			$value = strip_tags( $value );
		}

		// Return raw value without wrappers if raw is true
		if ( 'true' === strtolower( $atts['raw'] ) ) {
			return $atts['before'] . $value . $atts['after'];
		}

		// Build output with wrappers
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
}

// Initialise the shortcodes.
HK_Shortcodes::init();

/**
 * Clear caches when specific meta fields are updated via REST API
 */
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    // Only trigger on REST API requests
    if (!defined('REST_REQUEST') || !REST_REQUEST) {
        return;
    }

    // Define the meta keys we want to watch for
    $watched_meta_keys = array(
        '_hk_fs_keepsake_price',
        '_hk_fs_keepsake_product_code',
        '_hk_fs_keepsake_metal',
        '_hk_fs_keepsake_stones',
        '_hk_fs_casket_price',
        '_hk_fs_urn_price',
        '_hk_fs_monument_price',
        '_hk_fs_package_price',
        // Add any other meta fields that would affect Beaver Builder layouts
    );

    // Check if the meta key starts with any of our watched meta keys prefixes
    $should_clear = false;
    foreach ($watched_meta_keys as $key) {
        if (strpos($meta_key, $key) === 0) {
            $should_clear = true;
            break;
        }
    }

    if (!$should_clear) {
        return;
    }

    // Get the post type
    $post_type = get_post_type($post_id);

    // Define which post types should trigger cache clearing
    $relevant_post_types = array(
        'hk_fs_keepsake',
        'hk_fs_casket',
        'hk_fs_urn',
        'hk_fs_monument',
        'hk_fs_package',
        'hk_fs_staff'
    );

    if (!in_array($post_type, $relevant_post_types)) {
        return;
    }

    // Log the update if we have a logging function available
    if (function_exists('wcph_write_log')) {
        wcph_write_log('[' . date('Y-m-d H:i:s') . '] HK Funeral Suite - REST API meta update for ' . $meta_key . ' on ' . $post_type . ' (ID: ' . $post_id . ')');
    }

    // Use the shared cache purging function for consistent behavior across the plugin
    if (function_exists('hk_fs_optimized_cache_purge')) {
        hk_fs_optimized_cache_purge($post_id, 'REST API meta update');
    } else {
        // Fall back to old method if the function isn't available
        // Clear caches if Weave Cache Purge Helper is active
        if (function_exists('wcph_purge')) {
            wcph_purge();
        }

        // Clear Beaver Builder caches if available
        if (class_exists('FLBuilderModel')) {
            FLBuilderModel::delete_all_asset_cache();
        }
    }
    
    // Trigger action for other cache clearing systems
    do_action('hk_fs_meta_updated_via_rest', $post_id, $meta_key, $post_type);
    
}, 10, 4);
