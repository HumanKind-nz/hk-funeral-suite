<?php
/**
 * Google Sheets Integration
 *
 * One-way price sync: Google Sheets → WordPress via REST API.
 * Handles meta box locking, admin notices, and REST API workarounds
 * for the Google Sheets price sync across all product CPTs.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\GoogleSheets;

defined( 'WPINC' ) || exit;

/**
 * CPTs that support Google Sheets price sync.
 *
 * @return array<string, string> Settings key → post type (without prefix).
 */
function get_sheets_cpt_map(): array {
	return [
		'packages'  => 'package',
		'caskets'   => 'casket',
		'urns'      => 'urn',
		'monuments' => 'monument',
		'keepsakes' => 'keepsake',
	];
}

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_rest_meta_processing' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\register_price_metaboxes' );
	add_action( 'admin_notices', __NAMESPACE__ . '\\show_sheets_notices' );
	add_action( 'admin_head', __NAMESPACE__ . '\\sheets_admin_styles' );

	// Save handlers for each product CPT.
	foreach ( get_sheets_cpt_map() as $settings_key => $type ) {
		add_action( "save_post_hk_fs_{$type}", __NAMESPACE__ . '\\save_price_meta', 10 );
	}
	// Packages have their own save handler (different nonce names).
	add_action( 'save_post_hk_fs_package', __NAMESPACE__ . '\\save_package_meta', 10 );
}

/**
 * Check if a CPT's pricing is managed by Google Sheets.
 *
 * @param string $type Post type without prefix (e.g. 'casket').
 * @return bool
 */
function is_managed_by_sheets( string $type ): bool {
	return (bool) get_option( "hk_fs_{$type}_price_google_sheets", false );
}

// ─── REST API Meta Processing ───────────────────────────────────────────────

/**
 * Register REST API meta processing workarounds for Google Sheets integration.
 */
function register_rest_meta_processing(): void {
	foreach ( get_sheets_cpt_map() as $type ) {
		// Fix meta_input processing in REST requests.
		add_filter( "rest_pre_insert_hk_fs_{$type}", function ( $prepared_post, $request ) use ( $type ) {
			$params = $request->get_params();
			if ( isset( $params['meta'] ) && is_array( $params['meta'] ) ) {
				if ( ! isset( $prepared_post->meta_input ) || empty( $prepared_post->meta_input ) ) {
					$prepared_post->meta_input = [];
					foreach ( $params['meta'] as $meta_key => $meta_value ) {
						if ( strpos( $meta_key, "_hk_fs_{$type}_" ) === 0 ) {
							$prepared_post->meta_input[ $meta_key ] = $meta_value;
						}
					}
				}
			}
			return $prepared_post;
		}, 10, 2 );

		// Post-insert logging.
		add_action( "rest_insert_hk_fs_{$type}", function ( $post, $request, $creating ) use ( $type ) {
			if ( ! is_managed_by_sheets( $type ) ) {
				return;
			}
			$params = $request->get_params();
			$price_key = "_hk_fs_{$type}_price";
			if ( isset( $params['meta'][ $price_key ] ) ) {
				$requested = $params['meta'][ $price_key ];
				$saved     = get_post_meta( $post->ID, $price_key, true );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$status = ( $requested === $saved ) ? 'success' : 'FAILED';
					error_log( "HK Funeral Suite: Google Sheets {$type} price sync {$status}: {$saved}" );
				}
			}
		}, 10, 3 );
	}
}

// ─── Price Meta Boxes ───────────────────────────────────────────────────────

/**
 * Register price meta boxes for product CPTs.
 */
function register_price_metaboxes(): void {
	$settings = \HK_Funeral_Settings::get_instance();

	foreach ( get_sheets_cpt_map() as $settings_key => $type ) {
		if ( $type === 'package' || ! $settings->is_cpt_enabled( $settings_key ) ) {
			continue;
		}

		$defs = \HKFuneralSuite\PostTypes\get_cpt_definitions();
		$singular = $defs[ $settings_key ]['singular'] ?? ucfirst( $type );

		add_meta_box(
			"hk_fs_{$type}_pricing",
			__( 'Pricing Information', 'hk-funeral-suite' ),
			function ( $post ) use ( $type ) {
				wp_nonce_field( "hk_fs_{$type}_pricing_nonce", "hk_fs_{$type}_pricing_nonce" );
				$price      = get_post_meta( $post->ID, "_hk_fs_{$type}_price", true );
				$managed    = is_managed_by_sheets( $type );
				render_price_field( $type, $price, $managed );
			},
			"hk_fs_{$type}",
			'side',
			'high'
		);
	}

	// Package pricing meta box.
	if ( $settings->is_cpt_enabled( 'packages' ) ) {
		add_meta_box(
			'hk_fs_package_pricing',
			__( 'Price Information', 'hk-funeral-suite' ),
			function ( $post ) {
				wp_nonce_field( 'hk_fs_package_pricing_nonce', 'hk_fs_package_pricing_nonce' );
				$price   = get_post_meta( $post->ID, '_hk_fs_package_price', true );
				$managed = is_managed_by_sheets( 'package' );
				render_price_field( 'package', $price, $managed );
			},
			'hk_fs_package',
			'side',
			'high'
		);

		add_meta_box(
			'hk_fs_package_ordering',
			__( 'Display Order', 'hk-funeral-suite' ),
			function ( $post ) {
				wp_nonce_field( 'hk_fs_package_ordering_nonce', 'hk_fs_package_ordering_nonce' );
				$order = get_post_meta( $post->ID, '_hk_fs_package_order', true );
				if ( empty( $order ) && $order !== '0' ) {
					$order = '10';
				}
				?>
				<p>
					<label for="hk_fs_package_order"><?php esc_html_e( 'Display Order:', 'hk-funeral-suite' ); ?></label>
					<input type="number" id="hk_fs_package_order" name="hk_fs_package_order"
						value="<?php echo esc_attr( $order ); ?>" step="1" min="0" style="width: 100%;">
					<span class="description"><?php esc_html_e( 'Lower numbers will be displayed first.', 'hk-funeral-suite' ); ?></span>
				</p>
				<?php
			},
			'hk_fs_package',
			'side',
			'high'
		);
	}
}

/**
 * Render price field HTML (shared by all product CPTs).
 *
 * @param string $type    Post type without prefix.
 * @param string $price   Current price value.
 * @param bool   $managed Whether managed by Google Sheets.
 */
function render_price_field( string $type, string $price, bool $managed ): void {
	?>
	<div class="price-field-container <?php echo $managed ? 'sheet-managed' : ''; ?>">
		<p>
			<label for="hk_fs_<?php echo esc_attr( $type ); ?>_price"><?php esc_html_e( 'Price ($):', 'hk-funeral-suite' ); ?></label>
			<input type="text" id="hk_fs_<?php echo esc_attr( $type ); ?>_price"
				name="hk_fs_<?php echo esc_attr( $type ); ?>_price"
				value="<?php echo esc_attr( $price ); ?>" style="width: 100%;"
				<?php echo $managed ? 'disabled="disabled"' : ''; ?>>
			<span class="description" style="font-size: 11px; color: #757575;">
				<?php esc_html_e( 'Enter a numeric price (e.g., 1295.00) or text like "P.O.A."', 'hk-funeral-suite' ); ?>
			</span>
		</p>
		<?php if ( $managed ) : ?>
		<div class="sheet-integration-notice">
			<p style="color: #d63638; margin-top: 8px; display: flex; align-items: center;">
				<span class="dashicons dashicons-cloud" style="margin-right: 5px;"></span>
				<strong><?php esc_html_e( 'Managed via Google Sheets', 'hk-funeral-suite' ); ?></strong>
			</p>
			<p class="description" style="margin-top: 5px;">
				<?php esc_html_e( 'Price is managed through Google Sheets integration and cannot be modified here.', 'hk-funeral-suite' ); ?>
			</p>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

// ─── Save Handlers ──────────────────────────────────────────────────────────

/**
 * Save product CPT price meta from meta box.
 *
 * @param int $post_id Post ID.
 */
function save_price_meta( int $post_id ): void {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	$type      = str_replace( 'hk_fs_', '', $post_type );

	// Package has its own handler.
	if ( $type === 'package' ) {
		return;
	}

	$nonce_key = "hk_fs_{$type}_pricing_nonce";
	if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( $_POST[ $nonce_key ], $nonce_key ) ) {
		return;
	}

	if ( ! is_managed_by_sheets( $type ) && isset( $_POST[ "hk_fs_{$type}_price" ] ) ) {
		update_post_meta( $post_id, "_hk_fs_{$type}_price", sanitize_text_field( $_POST[ "hk_fs_{$type}_price" ] ) );
	}
}

/**
 * Save package meta from meta boxes.
 *
 * @param int $post_id Post ID.
 */
function save_package_meta( int $post_id ): void {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	// Price.
	if ( isset( $_POST['hk_fs_package_pricing_nonce'] ) &&
		wp_verify_nonce( $_POST['hk_fs_package_pricing_nonce'], 'hk_fs_package_pricing_nonce' ) ) {
		if ( ! is_managed_by_sheets( 'package' ) && isset( $_POST['hk_fs_package_price'] ) ) {
			update_post_meta( $post_id, '_hk_fs_package_price', sanitize_text_field( $_POST['hk_fs_package_price'] ) );
		}
	}

	// Order.
	if ( isset( $_POST['hk_fs_package_ordering_nonce'] ) &&
		wp_verify_nonce( $_POST['hk_fs_package_ordering_nonce'], 'hk_fs_package_ordering_nonce' ) ) {
		if ( isset( $_POST['hk_fs_package_order'] ) ) {
			update_post_meta( $post_id, '_hk_fs_package_order', absint( $_POST['hk_fs_package_order'] ) );
		}
	}
}

// ─── Admin Notices ──────────────────────────────────────────────────────────

/**
 * Show Google Sheets integration admin notices.
 */
function show_sheets_notices(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	foreach ( get_sheets_cpt_map() as $settings_key => $type ) {
		if ( $screen->post_type !== "hk_fs_{$type}" ) {
			continue;
		}

		if ( ! is_managed_by_sheets( $type ) ) {
			return;
		}

		$defs     = \HKFuneralSuite\PostTypes\get_cpt_definitions();
		$singular = $defs[ $settings_key ]['singular'] ?? ucfirst( $type );
		?>
		<div class="notice notice-info">
			<p>
				<span class="dashicons dashicons-cloud" style="color:#0073aa; font-size:18px; vertical-align:middle;"></span>
				<strong><?php esc_html_e( 'Google Sheets Integration Active:', 'hk-funeral-suite' ); ?></strong>
				<?php
				printf(
					/* translators: %s: CPT singular name */
					esc_html__( '%s pricing is currently managed via Google Sheets. Price fields are disabled in the admin interface.', 'hk-funeral-suite' ),
					esc_html( $singular )
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=hk-funeral-suite-settings' ) ); ?>">
					<?php esc_html_e( 'Change this setting', 'hk-funeral-suite' ); ?>
				</a>
			</p>
		</div>
		<?php
		return;
	}
}

// ─── Admin Styles ───────────────────────────────────────────────────────────

/**
 * Admin styles for Google Sheets managed fields.
 */
function sheets_admin_styles(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$is_managed = false;
	foreach ( get_sheets_cpt_map() as $type ) {
		if ( $screen->post_type === "hk_fs_{$type}" && is_managed_by_sheets( $type ) ) {
			$is_managed = true;
			break;
		}
	}

	if ( ! $is_managed ) {
		return;
	}
	?>
	<style type="text/css">
		input[disabled].sheet-managed-input,
		.price-field-container.sheet-managed input[disabled] {
			background-color: #f0f0f1;
			border-color: #dcdcde;
			color: #8c8f94;
			box-shadow: none;
		}
		.sheet-integration-notice {
			background-color: rgba(214, 54, 56, 0.05);
			border-left: 4px solid #d63638;
			padding: 8px;
			margin-top: 10px;
			border-radius: 2px;
		}
	</style>
	<?php
}
