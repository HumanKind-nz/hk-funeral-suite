<?php
/**
 * Catalogue Sync
 *
 * External catalogue sync feature module. A central master catalogue
 * pushes canonical product data to this site over authenticated REST
 * and pulls the site's current state for visibility and drift checks.
 *
 * REST namespace: hk-fs-catalogue/v1
 *   GET  /state  Export current products (pull / observe / import source).
 *   POST /stamp  Adopt existing posts by writing master IDs to them.
 *   POST /sync   Full-state reconcile: upsert, categorise, image sync,
 *                deactivate anything stamped but absent from the payload.
 *
 * Disabled by default: every route requires a per-site shared secret,
 * provisioned via the HK_FS_CATALOGUE_SECRET constant in wp-config.php
 * (option fallback: hk_fs_catalogue_secret). Without a secret the
 * routes refuse all requests.
 *
 * Authentication is an HMAC signature over each request:
 *   X-HKFS-Timestamp: unix seconds, accepted within ±5 minutes
 *   X-HKFS-Signature: hex( hmac_sha256( secret, timestamp . "." . raw_body ) )
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\CatalogueSync;

defined( 'WPINC' ) || exit;

const REST_NAMESPACE = 'hk-fs-catalogue/v1';
const SCHEMA_VERSION = 1;

/**
 * Timestamp tolerance for signed requests, in seconds (replay guard).
 */
const TIMESTAMP_TOLERANCE = 300;

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_meta_fields', 12 );
	add_action( 'init', __NAMESPACE__ . '\\register_settings', 12 );
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );
}

// ─── Type Maps ──────────────────────────────────────────────────────────────

/**
 * Catalogue types the sync understands, mapped to plugin settings keys.
 *
 * Packages are observed-state only: they appear in the /state export but
 * are never pushed via /sync or adopted via /stamp.
 *
 * @return array<string, string> Type → settings key.
 */
function get_supported_types(): array {
	return [
		'casket'   => 'caskets',
		'urn'      => 'urns',
		'monument' => 'monuments',
		'keepsake' => 'keepsakes',
		'package'  => 'packages',
	];
}

/**
 * Types the master catalogue can manage (push-synced).
 *
 * @return string[]
 */
function get_managed_capable_types(): array {
	return [ 'casket', 'urn', 'monument', 'keepsake' ];
}

/**
 * Post type slug for a catalogue type.
 *
 * @param string $type Catalogue type (e.g. 'casket').
 * @return string
 */
function post_type_for( string $type ): string {
	return "hk_fs_{$type}";
}

/**
 * Category taxonomy for a catalogue type, or null if it has none.
 *
 * @param string $type Catalogue type.
 * @return string|null
 */
function taxonomy_for( string $type ): ?string {
	if ( 'package' === $type ) {
		return null;
	}
	return "hk_fs_{$type}_category";
}

/**
 * Whether a catalogue type's CPT is enabled and registered on this site.
 *
 * @param string $type Catalogue type.
 * @return bool
 */
function is_type_available( string $type ): bool {
	$settings_key = get_supported_types()[ $type ] ?? null;
	if ( ! $settings_key ) {
		return false;
	}
	return \HK_Funeral_Settings::get_instance()->is_cpt_enabled( $settings_key )
		&& post_type_exists( post_type_for( $type ) );
}

// ─── Configuration ──────────────────────────────────────────────────────────

/**
 * Get the per-site shared secret.
 *
 * Primary source is the HK_FS_CATALOGUE_SECRET wp-config constant;
 * the hk_fs_catalogue_secret option is a fallback for hosts where
 * editing wp-config is impractical. The option is deliberately not
 * exposed via the REST settings endpoint.
 *
 * @return string Empty string when not provisioned.
 */
function get_secret(): string {
	if ( defined( 'HK_FS_CATALOGUE_SECRET' ) && is_string( HK_FS_CATALOGUE_SECRET ) ) {
		return HK_FS_CATALOGUE_SECRET;
	}
	$option = get_option( 'hk_fs_catalogue_secret', '' );
	return is_string( $option ) ? $option : '';
}

/**
 * Whether the module is configured (a secret has been provisioned).
 *
 * @return bool
 */
function is_configured(): bool {
	return '' !== get_secret();
}

/**
 * Catalogue types currently locked for local editing (managed by the
 * master catalogue). Flipping this option is the per-site switchover.
 *
 * @return string[]
 */
function get_locked_types(): array {
	$types = get_option( 'hk_fs_catalogue_managed_types', [] );
	if ( ! is_array( $types ) ) {
		return [];
	}
	return array_values( array_intersect( $types, get_managed_capable_types() ) );
}

/**
 * Register the managed-types option for the settings REST endpoint.
 */
function register_settings(): void {
	register_setting( 'hk_fs_settings', 'hk_fs_catalogue_managed_types', [
		'type'              => 'array',
		'default'           => [],
		'sanitize_callback' => __NAMESPACE__ . '\\sanitize_managed_types',
		'show_in_rest'      => [
			'schema' => [
				'type'  => 'array',
				'items' => [
					'type' => 'string',
					'enum' => get_managed_capable_types(),
				],
			],
		],
	] );
}

/**
 * Sanitize the managed-types option value.
 *
 * @param mixed $value Raw option value.
 * @return string[]
 */
function sanitize_managed_types( $value ): array {
	if ( ! is_array( $value ) ) {
		return [];
	}
	return array_values( array_intersect( array_map( 'strval', $value ), get_managed_capable_types() ) );
}

// ─── Meta Registration ──────────────────────────────────────────────────────

/**
 * Register catalogue sync meta: the stable master ID on posts and
 * category terms, plus image version hashes for sync diffing.
 *
 * None of these are editable in the UI or exposed via REST — the sync
 * writes them internally and they must never drift by hand.
 */
function register_meta_fields(): void {
	$hidden_string = [
		'show_in_rest'      => false,
		'single'            => true,
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => '__return_false',
	];

	foreach ( get_managed_capable_types() as $type ) {
		$post_type = post_type_for( $type );

		register_post_meta( $post_type, '_hk_fs_master_id', $hidden_string );
		register_post_meta( $post_type, '_hk_fs_image_version', $hidden_string );
		register_post_meta( $post_type, '_hk_fs_image_version_2', $hidden_string );
		register_post_meta( $post_type, '_hk_fs_image_id_2', [
			'show_in_rest'      => false,
			'single'            => true,
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'auth_callback'     => '__return_false',
		] );

		$taxonomy = taxonomy_for( $type );
		if ( $taxonomy ) {
			register_term_meta( $taxonomy, '_hk_fs_master_category_id', $hidden_string );
		}
	}
}

// ─── Authentication ─────────────────────────────────────────────────────────

/**
 * Verify the HMAC signature on a sync request.
 *
 * Used as the permission_callback for every route.
 *
 * @param \WP_REST_Request $request The request.
 * @return true|\WP_Error
 */
function authenticate( \WP_REST_Request $request ) {
	$secret = get_secret();

	if ( '' === $secret ) {
		return new \WP_Error(
			'hk_fs_catalogue_not_configured',
			__( 'Catalogue sync is not enabled on this site.', 'hk-funeral-suite' ),
			[ 'status' => 403 ]
		);
	}

	$timestamp = $request->get_header( 'X-HKFS-Timestamp' );
	$signature = $request->get_header( 'X-HKFS-Signature' );

	if ( ! $timestamp || ! $signature ) {
		return new \WP_Error(
			'hk_fs_catalogue_unauthorised',
			__( 'Missing signature headers.', 'hk-funeral-suite' ),
			[ 'status' => 401 ]
		);
	}

	if ( abs( time() - (int) $timestamp ) > TIMESTAMP_TOLERANCE ) {
		return new \WP_Error(
			'hk_fs_catalogue_stale_timestamp',
			__( 'Request timestamp outside the accepted window.', 'hk-funeral-suite' ),
			[ 'status' => 401 ]
		);
	}

	$expected = hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $secret );

	if ( ! hash_equals( $expected, strtolower( trim( $signature ) ) ) ) {
		return new \WP_Error(
			'hk_fs_catalogue_bad_signature',
			__( 'Invalid request signature.', 'hk-funeral-suite' ),
			[ 'status' => 401 ]
		);
	}

	return true;
}

// ─── Routes ─────────────────────────────────────────────────────────────────

/**
 * Register the REST routes.
 */
function register_routes(): void {
	register_rest_route( REST_NAMESPACE, '/state', [
		'methods'             => \WP_REST_Server::READABLE,
		'callback'            => __NAMESPACE__ . '\\rest_state',
		'permission_callback' => __NAMESPACE__ . '\\authenticate',
		'args'                => [
			'types' => [
				'description'       => __( 'Comma-separated catalogue types to export.', 'hk-funeral-suite' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	] );
}

// ─── GET /state ─────────────────────────────────────────────────────────────

/**
 * Export the site's current product state.
 *
 * Used by the master for the one-off import snapshot, package
 * visibility, and drift detection against the last published payload.
 *
 * @param \WP_REST_Request $request The request.
 * @return \WP_REST_Response
 */
function rest_state( \WP_REST_Request $request ): \WP_REST_Response {
	$supported = array_keys( get_supported_types() );
	$requested = $supported;

	$types_param = (string) $request->get_param( 'types' );
	if ( '' !== $types_param ) {
		$requested = array_values( array_intersect(
			array_map( 'trim', explode( ',', $types_param ) ),
			$supported
		) );
	}

	$out = [
		'schema_version' => SCHEMA_VERSION,
		'plugin_version' => HK_FS_VERSION,
		'site_url'       => home_url(),
		'generated_at'   => gmdate( 'c' ),
		'locked_types'   => get_locked_types(),
		'types'          => [],
	];

	foreach ( $requested as $type ) {
		$out['types'][ $type ] = export_type( $type );
	}

	return rest_ensure_response( $out );
}

/**
 * Export all products of one catalogue type.
 *
 * @param string $type Catalogue type.
 * @return array
 */
function export_type( string $type ): array {
	if ( ! is_type_available( $type ) ) {
		return [
			'enabled'  => false,
			'products' => [],
		];
	}

	$post_type = post_type_for( $type );

	$query = new \WP_Query( [
		'post_type'              => $post_type,
		'post_status'            => [ 'publish', 'future', 'draft', 'pending', 'private' ],
		'posts_per_page'         => -1,
		'orderby'                => 'menu_order title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_term_cache' => true,
	] );

	$products = [];
	foreach ( $query->posts as $post ) {
		$products[] = export_product( $post, $type );
	}

	return [
		'enabled'  => true,
		'products' => $products,
	];
}

/**
 * Export a single product post.
 *
 * @param \WP_Post $post The post.
 * @param string   $type Catalogue type.
 * @return array
 */
function export_product( \WP_Post $post, string $type ): array {
	$post_type = post_type_for( $type );
	$master_id = get_post_meta( $post->ID, '_hk_fs_master_id', true );

	// Export every registered meta field for this CPT, keyed without
	// the "_hk_fs_{type}_" prefix (price, intro, order, metal, …).
	$meta   = [];
	$fields = \HKFuneralSuite\PostTypes\get_meta_fields()[ $post_type ] ?? [];
	foreach ( array_keys( $fields ) as $meta_key ) {
		$short          = str_replace( "_hk_fs_{$type}_", '', $meta_key );
		$meta[ $short ] = get_post_meta( $post->ID, $meta_key, true );
	}

	$terms    = [];
	$taxonomy = taxonomy_for( $type );
	if ( $taxonomy ) {
		foreach ( wp_get_object_terms( $post->ID, $taxonomy ) as $term ) {
			$terms[] = [
				'term_id'   => $term->term_id,
				'name'      => $term->name,
				'slug'      => $term->slug,
				'master_id' => get_term_meta( $term->term_id, '_hk_fs_master_category_id', true ) ?: null,
			];
		}
	}

	$thumbnail_id = get_post_thumbnail_id( $post->ID );

	return [
		'post_id'        => $post->ID,
		'status'         => $post->post_status,
		'title'          => $post->post_title,
		'slug'           => $post->post_name,
		'master_id'      => $master_id ?: null,
		'menu_order'     => (int) $post->menu_order,
		'content'        => $post->post_content,
		'meta'           => $meta,
		'terms'          => $terms,
		'featured_image' => $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : null,
		'image_versions' => [
			'primary'   => get_post_meta( $post->ID, '_hk_fs_image_version', true ) ?: null,
			'secondary' => get_post_meta( $post->ID, '_hk_fs_image_version_2', true ) ?: null,
		],
		'modified_at'    => mysql2date( 'c', $post->post_modified_gmt, false ),
	];
}
