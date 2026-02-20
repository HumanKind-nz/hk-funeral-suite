<?php
/**
 * Admin Column Utilities
 *
 * Shared admin column functionality across all CPTs: featured image column,
 * title rename, price/order columns, SEO column removal, shortcode column.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\AdminColumns;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_action( 'admin_init', __NAMESPACE__ . '\\setup_columns' );
	add_action( 'admin_head', __NAMESPACE__ . '\\admin_column_styles' );
}

/**
 * Set up columns for all registered CPTs.
 */
function setup_columns(): void {
	$definitions = \HKFuneralSuite\PostTypes\get_cpt_definitions();
	$settings    = \HK_Funeral_Settings::get_instance();

	foreach ( $definitions as $key => $def ) {
		if ( ! $settings->is_cpt_enabled( $key ) ) {
			continue;
		}

		$slug = $def['slug'];
		$type = $def['type'];

		// Featured image column (all CPTs except packages).
		if ( $key !== 'packages' ) {
			add_filter( "manage_{$slug}_posts_columns", __NAMESPACE__ . '\\add_featured_image_column', 5 );
			add_action( "manage_{$slug}_posts_custom_column", __NAMESPACE__ . '\\display_featured_image', 10, 2 );
		}

		// Rename title → Name (all CPTs).
		add_filter( "manage_{$slug}_posts_columns", __NAMESPACE__ . '\\rename_title_column' );

		// SEO column removal (non-public CPTs).
		$is_public = (bool) get_option( "hk_fs_enable_public_{$key}", false );
		if ( ! $is_public ) {
			add_filter( "manage_{$slug}_posts_columns", __NAMESPACE__ . '\\remove_seo_columns', 100 );
			add_filter( "manage_edit-{$slug}_columns", __NAMESPACE__ . '\\remove_seo_columns', 100 );
		}

		// Product CPTs: price + order columns.
		if ( $type === 'product' ) {
			$post_type = str_replace( 'hk_fs_', '', $slug );
			setup_product_columns( $slug, $post_type );
		}

		// Package CPT: price, order, shortcode columns.
		if ( $type === 'package' ) {
			setup_package_columns();
		}

		// Staff CPT: position + qualification columns.
		if ( $type === 'staff' ) {
			setup_staff_columns();
		}
	}
}

// ─── Column Callbacks ───────────────────────────────────────────────────────

/**
 * Add featured image column at the beginning.
 *
 * @param array $columns Current columns.
 * @return array
 */
function add_featured_image_column( array $columns ): array {
	return [ 'featured_image' => __( 'Image', 'hk-funeral-suite' ) ] + $columns;
}

/**
 * Display featured image thumbnail.
 *
 * @param string $column  Column ID.
 * @param int    $post_id Post ID.
 */
function display_featured_image( string $column, int $post_id ): void {
	if ( $column !== 'featured_image' ) {
		return;
	}

	if ( has_post_thumbnail( $post_id ) ) {
		echo '<img src="' . esc_url( get_the_post_thumbnail_url( $post_id, 'full' ) ) . '" style="width:150px; height:auto; max-height:150px; object-fit:cover;">';
	} else {
		echo '<div style="width:150px; height:100px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border:1px solid #ddd; border-radius:3px;"><span class="dashicons dashicons-format-image" style="font-size:30px; color:#bbb;"></span></div>';
	}
}

/**
 * Rename title column to "Name".
 *
 * @param array $columns Current columns.
 * @return array
 */
function rename_title_column( array $columns ): array {
	if ( isset( $columns['title'] ) ) {
		$columns['title'] = __( 'Name', 'hk-funeral-suite' );
	}
	return $columns;
}

/**
 * Remove SEO plugin columns.
 *
 * @param array $columns Current columns.
 * @return array
 */
function remove_seo_columns( array $columns ): array {
	// SEOPress.
	foreach ( $columns as $key => $value ) {
		if ( strpos( $key, 'seopress' ) === 0 ) {
			unset( $columns[ $key ] );
		}
	}

	// Yoast.
	$yoast_keys = [ 'wpseo-title', 'wpseo-metadesc', 'wpseo-focuskw', 'wpseo-score', 'wpseo-score-readability' ];
	foreach ( $yoast_keys as $key ) {
		unset( $columns[ $key ] );
	}

	// Rank Math.
	unset( $columns['rank_math_title'], $columns['rank_math_description'], $columns['rank_math_seo_details'] );

	// All in One SEO.
	$aioseo_keys = [ 'aioseo-title', 'aioseo-description', 'aioseo-keywords', 'aioseo-score' ];
	foreach ( $aioseo_keys as $key ) {
		unset( $columns[ $key ] );
	}

	return $columns;
}

// ─── Product Columns ────────────────────────────────────────────────────────

/**
 * Set up price and order columns for product CPTs.
 *
 * @param string $slug      Full post type slug (e.g. 'hk_fs_casket').
 * @param string $post_type Post type without prefix (e.g. 'casket').
 */
function setup_product_columns( string $slug, string $post_type ): void {
	// Add columns.
	add_filter( "manage_{$slug}_posts_columns", function ( $columns ) {
		$new = [];
		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( $key === 'title' ) {
				$new['price'] = __( 'Price', 'hk-funeral-suite' );
			}
		}
		return $new;
	} );

	// Display column data.
	add_action( "manage_{$slug}_posts_custom_column", function ( $column, $post_id ) use ( $post_type ) {
		if ( $column === 'price' ) {
			$price   = get_post_meta( $post_id, "_hk_fs_{$post_type}_price", true );
			$managed = \HKFuneralSuite\GoogleSheets\is_managed_by_sheets( $post_type );
			echo_price_column( $price, $managed );
		}
	}, 10, 2 );

	// Sortable.
	add_filter( "manage_edit-{$slug}_sortable_columns", function ( $columns ) {
		$columns['price'] = 'price';
		return $columns;
	} );

	// Sort query.
	add_action( 'pre_get_posts', function ( $query ) use ( $slug, $post_type ) {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== $slug ) {
			return;
		}
		if ( $query->get( 'orderby' ) === 'price' ) {
			$query->set( 'meta_key', "_hk_fs_{$post_type}_price" );
			$query->set( 'orderby', 'meta_value_num' );
		}
	} );
}

// ─── Package Columns ────────────────────────────────────────────────────────

/**
 * Set up columns for the package CPT.
 */
function setup_package_columns(): void {
	add_filter( 'manage_hk_fs_package_posts_columns', function ( $columns ) {
		$new = [];
		foreach ( $columns as $key => $value ) {
			if ( $key === 'title' ) {
				$new[ $key ] = $value;
				$new['price']     = __( 'Price', 'hk-funeral-suite' );
				$new['order']     = __( 'Order', 'hk-funeral-suite' );
				$new['shortcode'] = __( 'Shortcode', 'hk-funeral-suite' );
			} elseif ( $key !== 'content' ) {
				$new[ $key ] = $value;
			}
		}
		return $new;
	} );

	add_action( 'manage_hk_fs_package_posts_custom_column', function ( $column, $post_id ) {
		if ( $column === 'price' ) {
			$price   = get_post_meta( $post_id, '_hk_fs_package_price', true );
			$managed = \HKFuneralSuite\GoogleSheets\is_managed_by_sheets( 'package' );
			echo_price_column( $price, $managed );
		}
		if ( $column === 'order' ) {
			$order = get_post_meta( $post_id, '_hk_fs_package_order', true );
			echo ! empty( $order ) ? esc_html( $order ) : '10';
		}
		if ( $column === 'shortcode' ) {
			$shortcode = '[hk_formatted_price key="_hk_fs_package_price" post_id="' . esc_attr( (string) $post_id ) . '"]';
			echo '<div class="hk-shortcode-container">';
			echo '<input type="text" readonly class="hk-shortcode-display" value="' . esc_attr( $shortcode ) . '" onclick="this.select();" style="width: 100%; max-width: 300px; font-size: 12px; padding: 4px; background: #f0f0f1;">';
			echo '<button type="button" class="button button-small hk-copy-shortcode" data-shortcode="' . esc_attr( $shortcode ) . '"><span class="dashicons dashicons-clipboard"></span></button>';
			echo '</div>';
		}
	}, 10, 2 );

	add_filter( 'manage_edit-hk_fs_package_sortable_columns', function ( $columns ) {
		$columns['price'] = 'price';
		$columns['order'] = 'order';
		return $columns;
	} );

	add_action( 'pre_get_posts', function ( $query ) {
		if ( ! $query->is_main_query() || $query->get( 'post_type' ) !== 'hk_fs_package' ) {
			return;
		}
		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_hk_fs_package_order' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'ASC' );
		} elseif ( $query->get( 'orderby' ) === 'price' ) {
			$query->set( 'meta_key', '_hk_fs_package_price' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( $query->get( 'orderby' ) === 'order' ) {
			$query->set( 'meta_key', '_hk_fs_package_order' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	} );
}

// ─── Staff Columns ──────────────────────────────────────────────────────────

/**
 * Set up columns for the staff CPT.
 */
function setup_staff_columns(): void {
	add_filter( 'manage_hk_fs_staff_posts_columns', function ( $columns ) {
		$new = [];
		foreach ( $columns as $key => $value ) {
			if ( $key === 'title' ) {
				$new[ $key ]           = $value;
				$new['position']       = __( 'Position', 'hk-funeral-suite' );
				$new['qualification']  = __( 'Qualification', 'hk-funeral-suite' );
			} elseif ( $key === 'taxonomy-hk_fs_location' || $key === 'taxonomy-hk_fs_role' || $key === 'date' ) {
				// Skip — we'll re-add at end in the correct order.
			} else {
				$new[ $key ] = $value;
			}
		}
		$new['taxonomy-hk_fs_location'] = __( 'Location', 'hk-funeral-suite' );
		$new['taxonomy-hk_fs_role']     = __( 'Job Role', 'hk-funeral-suite' );
		if ( isset( $columns['date'] ) ) {
			$new['date'] = $columns['date'];
		}
		return $new;
	} );

	add_action( 'manage_hk_fs_staff_posts_custom_column', function ( $column, $post_id ) {
		if ( in_array( $column, [ 'position', 'qualification' ], true ) ) {
			$value = get_post_meta( $post_id, '_hk_fs_staff_' . $column, true );
			echo ! empty( $value ) ? esc_html( $value ) : '&mdash;';
		}
	}, 10, 2 );

	add_filter( 'manage_edit-hk_fs_staff_sortable_columns', function ( $columns ) {
		$columns['position']      = 'position';
		$columns['qualification'] = 'qualification';
		return $columns;
	} );

	add_action( 'pre_get_posts', function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'hk_fs_staff' ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( $orderby === 'position' ) {
			$query->set( 'meta_key', '_hk_fs_staff_position' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( $orderby === 'qualification' ) {
			$query->set( 'meta_key', '_hk_fs_staff_qualification' );
			$query->set( 'orderby', 'meta_value' );
		}
	} );
}

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Echo price column HTML.
 *
 * @param string $price   Price value.
 * @param bool   $managed Whether managed by Google Sheets.
 */
function echo_price_column( string $price, bool $managed ): void {
	if ( ! empty( $price ) ) {
		if ( is_numeric( $price ) ) {
			echo '$' . number_format( (float) $price, 2 );
		} else {
			echo esc_html( $price );
		}
		if ( $managed ) {
			echo ' <span class="dashicons dashicons-cloud" style="color:#0073aa;" title="Managed via Google Sheets"></span>';
		}
	} else {
		echo '&mdash;';
	}
}

/**
 * Admin column CSS and shortcode copy JS.
 */
function admin_column_styles(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$cpt_slugs = \HKFuneralSuite\PostTypes\get_all_cpt_slugs();
	if ( ! in_array( $screen->post_type, $cpt_slugs, true ) ) {
		return;
	}
	?>
	<style>
		.column-featured_image { width: 150px !important; overflow: hidden; }
		.column-featured_image img { border-radius: 3px; border: 1px solid #ddd; }
		.column-price, .column-order { width: 100px; }
		.column-shortcode { width: 350px !important; }
		.hk-shortcode-container { display: flex; align-items: center; }
		.hk-shortcode-display { cursor: pointer; border: 1px solid #ddd; }
		.hk-copy-shortcode { margin-left: 5px !important; padding: 0 !important; height: 28px !important; width: 28px !important; }
		.hk-copy-shortcode .dashicons { width: 20px; height: 20px; font-size: 16px; line-height: 1.3; }
	</style>
	<?php

	// Shortcode copy JS (packages only).
	if ( $screen->post_type === 'hk_fs_package' ) :
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.hk-copy-shortcode').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var shortcode = this.dataset.shortcode;
				var icon = this.querySelector('.dashicons');
				navigator.clipboard.writeText(shortcode).then(function() {
					icon.className = 'dashicons dashicons-yes';
					btn.style.backgroundColor = '#00a32a';
					btn.style.color = '#fff';
					setTimeout(function() {
						icon.className = 'dashicons dashicons-clipboard';
						btn.style.backgroundColor = '';
						btn.style.color = '';
					}, 2000);
				});
			});
		});
	});
	</script>
	<?php
	endif;
}
