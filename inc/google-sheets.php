<?php
/**
 * Google Sheets Integration
 *
 * One-way price sync: Google Sheets → WordPress via REST API.
 * Handles admin notices and REST API workarounds for the Google
 * Sheets price sync across all product CPTs.
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
	add_action( 'admin_notices', __NAMESPACE__ . '\\show_sheets_notices' );
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
// Removed in v2.0.0 — price entry is now handled by the in-content
// useEntityProp blocks (casket-meta, urn-meta, etc.). Google Sheets
// lock state is passed to blocks via window.hkFsBlockData.isSheetsManaged.

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

