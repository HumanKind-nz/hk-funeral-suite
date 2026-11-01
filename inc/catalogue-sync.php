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
 * Image batching: a first publish can need dozens of image sideloads,
 * more than fit in one request under the host's 60 second gateway
 * timeout. /sync always reconciles every product's data, but sideloads
 * at most image_fetch_limit images per request (body field, 1 to 100,
 * default 8 via the hk_fs_catalogue_image_fetch_limit filter), starts no
 * new sideload once 35 seconds of the request have gone (filter:
 * hk_fs_catalogue_image_time_budget), gives each download 15 seconds
 * (filter: hk_fs_catalogue_image_download_timeout), and reports the rest
 * as pending. The master replays the same payload until the response
 * says complete.
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
 * Image sideloads per /sync request when the payload doesn't say.
 */
const IMAGE_FETCH_LIMIT_DEFAULT = 8;

/**
 * Upper bound on image_fetch_limit, whatever the payload asks for.
 */
const IMAGE_FETCH_LIMIT_MAX = 100;

/**
 * Seconds into a /sync request after which no new image sideload starts.
 * Whatever is left is reported as pending for the next call.
 */
const IMAGE_TIME_BUDGET_DEFAULT = 35;

/**
 * Seconds one image download may take inside /sync. download_url()
 * otherwise waits up to 300 seconds, far past the gateway timeout.
 */
const IMAGE_DOWNLOAD_TIMEOUT_DEFAULT = 15;

/**
 * How long a failed image download is remembered for its sync run, so
 * repeated calls in one publish don't spend their image budget on it.
 */
const IMAGE_FAILURE_TTL = HOUR_IN_SECONDS;

/**
 * Give the catalogue's own queries back the post statuses they asked for.
 *
 * Some plugins narrow post_status on every query during a REST request
 * (one in the wild forces publish/private site-wide). That hides the
 * drafts the sync deactivated, so re-adding a product created a second
 * post instead of reactivating the first. Queries built in this module
 * carry hk_fs_catalogue_status, and that list wins after everyone else.
 *
 * @param \WP_Query $query The query.
 */
function protect_query_status( \WP_Query $query ): void {
	$status = $query->get( 'hk_fs_catalogue_status' );
	if ( ! empty( $status ) ) {
		$query->set( 'post_status', $status );
	}
}

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_meta_fields', 12 );
	add_action( 'init', __NAMESPACE__ . '\\register_settings', 12 );
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

	// Runs last, so no other plugin can narrow the statuses our own
	// lookups ask for (see protect_query_status()).
	add_action( 'pre_get_posts', __NAMESPACE__ . '\\protect_query_status', PHP_INT_MAX );

	// The lock is readable through /wp/v2/settings but never writable
	// there: switching a site to central management is a provisioning
	// step, not something a site admin does from the browser.
	add_filter( 'rest_pre_update_setting', __NAMESPACE__ . '\\refuse_rest_lock_update', 10, 2 );

	// Responses on this namespace must never be page-cached: a cached
	// /state body gets served to unsigned requests, bypassing the HMAC
	// wall entirely (observed live on GridPane's srcache, which ignores
	// Cache-Control — srcache_response_cache_control off — but skips the
	// store when upstream sends Do-Not-Cache).
	add_filter( 'rest_post_dispatch', __NAMESPACE__ . '\\no_store_headers', 10, 3 );

	// Editor lock for catalogue-managed types (hk_fs_catalogue_managed_types
	// option — flipping it is the per-site switchover). Server-side
	// capability denial: nothing can save around it. The sync itself writes
	// via wp_insert_post / wp_update_post and the term functions, which do
	// not check capabilities.
	add_filter( 'register_post_type_args', __NAMESPACE__ . '\\lock_post_type_caps', 10, 2 );
	add_filter( 'register_taxonomy_args', __NAMESPACE__ . '\\lock_taxonomy_caps', 10, 2 );
	add_filter( 'map_meta_cap', __NAMESPACE__ . '\\lock_meta_caps', 10, 4 );
	add_filter( 'display_post_states', __NAMESPACE__ . '\\lock_post_state', 10, 2 );
	add_action( 'admin_notices', __NAMESPACE__ . '\\lock_admin_notice' );
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
 *
 * Exposed read-only: the schema marks it readonly and
 * refuse_rest_lock_update() drops any write sent to /wp/v2/settings.
 * WP-CLI and update_option() still set it.
 */
function register_settings(): void {
	register_setting( 'hk_fs_settings', 'hk_fs_catalogue_managed_types', [
		'type'              => 'array',
		'description'       => __( 'Catalogue types managed by the central catalogue on this site. Read-only here: it is set when the site is provisioned.', 'hk-funeral-suite' ),
		'default'           => [],
		'sanitize_callback' => __NAMESPACE__ . '\\sanitize_managed_types',
		'show_in_rest'      => [
			'schema' => [
				'type'     => 'array',
				'readonly' => true,
				'items'    => [
					'type' => 'string',
					'enum' => get_managed_capable_types(),
				],
			],
		],
	] );
}

/**
 * Refuse updates to the editor lock through /wp/v2/settings.
 *
 * Returning true tells the settings controller the value was handled, so
 * the stored lock stays as it is and the response reports it unchanged.
 * Other settings pass through untouched.
 *
 * @param mixed $updated Whether another callback already handled the update.
 * @param mixed $name    Setting name as shown in REST responses.
 * @return mixed
 */
function refuse_rest_lock_update( $updated, $name ) {
	if ( 'hk_fs_catalogue_managed_types' === $name ) {
		return true;
	}
	return $updated;
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

/**
 * Mark every response on our namespace uncacheable, including auth errors.
 *
 * Cache-Control covers standards-respecting layers (CDNs, other hosts);
 * Do-Not-Cache is the GridPane srcache store-skip signal.
 *
 * @param \WP_REST_Response $response The response.
 * @param \WP_REST_Server   $server   The REST server.
 * @param \WP_REST_Request  $request  The request.
 * @return \WP_REST_Response
 */
function no_store_headers( \WP_REST_Response $response, \WP_REST_Server $server, \WP_REST_Request $request ): \WP_REST_Response {
	if ( str_starts_with( $request->get_route(), '/' . REST_NAMESPACE . '/' ) ) {
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Do-Not-Cache', '1' );
	}
	return $response;
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

	register_rest_route( REST_NAMESPACE, '/stamp', [
		'methods'             => \WP_REST_Server::CREATABLE,
		'callback'            => __NAMESPACE__ . '\\rest_stamp',
		'permission_callback' => __NAMESPACE__ . '\\authenticate',
	] );

	register_rest_route( REST_NAMESPACE, '/sync', [
		'methods'             => \WP_REST_Server::CREATABLE,
		'callback'            => __NAMESPACE__ . '\\rest_sync',
		'permission_callback' => __NAMESPACE__ . '\\authenticate',
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
		'hk_fs_catalogue_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ], // Restored after other plugins' pre_get_posts.
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
				'name'      => decode_text( $term->name ),
				'slug'      => $term->slug,
				'master_id' => get_term_meta( $term->term_id, '_hk_fs_master_category_id', true ) ?: null,
			];
		}
	}

	$thumbnail_id = get_post_thumbnail_id( $post->ID );

	return [
		'post_id'        => $post->ID,
		'status'         => $post->post_status,
		// Decoded, so the master sees the name it sent ("&", not "&amp;").
		'title'          => decode_text( $post->post_title ),
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

// ─── POST /stamp ────────────────────────────────────────────────────────────

/**
 * Whether a string looks like a UUID (the master ID format).
 *
 * @param string $value Candidate value.
 * @return bool
 */
function is_uuid( string $value ): bool {
	return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value );
}

/**
 * Find the post carrying a master ID, if any.
 *
 * Matching is only ever on this meta, never name or slug. Trash is
 * included so a trashed synced post can't cause a duplicate.
 *
 * @param string $master_id Master catalogue UUID.
 * @param string $post_type Post type slug.
 * @return int Post ID, or 0 when not found.
 */
function find_post_by_master_id( string $master_id, string $post_type ): int {
	$found = get_posts( [
		'post_type'      => $post_type,
		'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ],
		'hk_fs_catalogue_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ], // Restored after other plugins' pre_get_posts.
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_hk_fs_master_id',
		'meta_value'     => $master_id,
		'no_found_rows'  => true,
	] );

	return $found ? (int) $found[0] : 0;
}

/**
 * Adopt existing posts into the catalogue by stamping master IDs.
 *
 * Body: { "stamp": [ { "post_id": 123, "master_id": "uuid" }, … ] }
 *
 * Used once per site during import so the first publish updates
 * existing posts (and keeps their media) instead of duplicating them.
 *
 * @param \WP_REST_Request $request The request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_stamp( \WP_REST_Request $request ) {
	$body  = $request->get_json_params();
	$pairs = $body['stamp'] ?? null;

	if ( ! is_array( $pairs ) || empty( $pairs ) ) {
		return new \WP_Error(
			'hk_fs_catalogue_bad_request',
			__( 'Expected a non-empty "stamp" array of { post_id, master_id } pairs.', 'hk-funeral-suite' ),
			[ 'status' => 400 ]
		);
	}

	$managed_post_types = array_map( __NAMESPACE__ . '\\post_type_for', get_managed_capable_types() );

	$results = [];
	$counts  = [ 'stamped' => 0, 'unchanged' => 0, 'error' => 0 ];

	foreach ( $pairs as $pair ) {
		$post_id   = isset( $pair['post_id'] ) ? (int) $pair['post_id'] : 0;
		$master_id = isset( $pair['master_id'] ) ? strtolower( trim( (string) $pair['master_id'] ) ) : '';

		$result = [
			'post_id'   => $post_id,
			'master_id' => $master_id,
		];

		if ( $post_id < 1 || ! is_uuid( $master_id ) ) {
			$result['result'] = 'error';
			$result['error']  = 'invalid_pair';
			$counts['error']++;
			$results[] = $result;
			continue;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, $managed_post_types, true ) ) {
			$result['result'] = 'error';
			$result['error']  = 'post_not_found_or_not_managed_type';
			$counts['error']++;
			$results[] = $result;
			continue;
		}

		$existing = (string) get_post_meta( $post_id, '_hk_fs_master_id', true );
		if ( $existing === $master_id ) {
			$result['result'] = 'unchanged';
			$counts['unchanged']++;
			$results[] = $result;
			continue;
		}

		// One master ID maps to exactly one post per site.
		$holder = find_post_by_master_id( $master_id, $post->post_type );
		if ( $holder && $holder !== $post_id ) {
			$result['result'] = 'error';
			$result['error']  = 'master_id_already_stamped';
			$result['holder'] = $holder;
			$counts['error']++;
			$results[] = $result;
			continue;
		}

		update_post_meta( $post_id, '_hk_fs_master_id', $master_id );
		$result['result'] = $existing ? 'restamped' : 'stamped';
		$counts['stamped']++;
		$results[] = $result;
	}

	return rest_ensure_response( [
		'schema_version' => SCHEMA_VERSION,
		'plugin_version' => HK_FS_VERSION,
		'counts'         => $counts,
		'results'        => $results,
	] );
}

// ─── POST /sync ─────────────────────────────────────────────────────────────

/**
 * Full-state reconcile: receive a location's complete desired product
 * set from the master catalogue and converge the local copy to it.
 *
 * Products are matched only on the _hk_fs_master_id meta, never name
 * or slug. Anything local and stamped but absent from the payload is
 * deactivated (status → draft). Idempotent: replaying the same payload
 * is a no-op.
 *
 * Product data is always reconciled in full. Image sideloads are capped
 * per request by count (resolve_image_fetch_limit()) and by time
 * (resolve_image_time_budget()); images over either cap are reported as
 * pending and "complete" stays false until a replay of the same payload
 * has fetched them all.
 *
 * @param \WP_REST_Request $request The request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_sync( \WP_REST_Request $request ) {
	$body = $request->get_json_params();

	if ( (int) ( $body['schema_version'] ?? 0 ) !== SCHEMA_VERSION ) {
		return new \WP_Error(
			'hk_fs_catalogue_unsupported_schema',
			__( 'Unsupported payload schema version.', 'hk-funeral-suite' ),
			[ 'status' => 400 ]
		);
	}

	$products = $body['products'] ?? null;
	if ( ! is_array( $products ) ) {
		return new \WP_Error(
			'hk_fs_catalogue_bad_request',
			__( 'Expected a "products" array (the full desired set for this site).', 'hk-funeral-suite' ),
			[ 'status' => 400 ]
		);
	}

	// Image sideloads are capped by count and time per request, but the
	// last download and its thumbnails can still run past the time budget.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	wp_raise_memory_limit( 'admin' );

	$started     = microtime( true );
	$sync_run_id = $body['sync_run_id'] ?? null;
	$results     = [];
	$counts      = [
		'created'        => 0,
		'updated'        => 0,
		'unchanged'      => 0,
		'deactivated'    => 0,
		'errors'         => 0,
		'images_fetched' => 0,
		'images_pending' => 0,
		'image_errors'   => 0,
	];

	// The image budget: a count (image_fetch_limit), a clock (no new
	// sideload after the time budget, measured from here) and a per-download
	// timeout. Worst case for the sideloads is the time budget plus one
	// download timeout, plus thumbnail generation for that last image.
	$image_fetch_limit = resolve_image_fetch_limit( $body['image_fetch_limit'] ?? null );
	$image_budget      = [
		'remaining' => $image_fetch_limit,
		'deadline'  => $started + resolve_image_time_budget(),
		'timeout'   => resolve_image_download_timeout(),
		'failures'  => get_image_failures( $sync_run_id ),
		'dirty'     => false,
	];

	// Every master ID present in the payload, per type — including ones
	// whose processing errors — so a mid-run failure can never cause the
	// deactivation pass to draft a product the master still wants live.
	$payload_ids = [];

	foreach ( $products as $product ) {
		$product   = is_array( $product ) ? $product : [];
		$type      = (string) ( $product['type'] ?? '' );
		$master_id = strtolower( trim( (string) ( $product['master_id'] ?? '' ) ) );

		if ( in_array( $type, get_managed_capable_types(), true ) && is_uuid( $master_id ) ) {
			$payload_ids[ $type ][] = $master_id;
		}

		$result    = reconcile_product( $product, $type, $master_id, $image_budget );
		$results[] = $result;

		if ( 'error' === $result['result'] ) {
			$counts['errors']++;
		} else {
			$counts[ $result['result'] ]++;
		}

		foreach ( $result['images'] ?? [] as $image_result ) {
			if ( 'sideloaded' === $image_result['result'] ) {
				$counts['images_fetched']++;
			} elseif ( 'pending' === $image_result['result'] ) {
				$counts['images_pending']++;
			} elseif ( 'error' === $image_result['result'] ) {
				$counts['image_errors']++;
			}
		}

		if ( in_array( $result['result'], [ 'created', 'updated' ], true ) && ! empty( $result['post_id'] ) ) {
			\HKFuneralSuite\Hooks\optimised_cache_purge( (int) $result['post_id'], 'catalogue-sync' );
		}
	}

	if ( $image_budget['dirty'] ) {
		save_image_failures( $sync_run_id, $image_budget['failures'] );
	}

	// Deactivation + stray scope: the locked (managed) types where they
	// are set, otherwise the types present in this payload. The payload
	// is the full desired set for every type in scope, so a stamped post
	// missing from it means the master no longer wants it live here.
	$scope = get_locked_types();
	if ( empty( $scope ) ) {
		$scope = array_values( array_unique( array_keys( $payload_ids ) ) );
	}

	foreach ( deactivate_absent( $scope, $payload_ids ) as $deactivated ) {
		$results[] = $deactivated;
		$counts['deactivated']++;
		\HKFuneralSuite\Hooks\optimised_cache_purge( (int) $deactivated['post_id'], 'catalogue-sync' );
	}

	return rest_ensure_response( [
		'schema_version'    => SCHEMA_VERSION,
		'plugin_version'    => HK_FS_VERSION,
		'sync_run_id'       => $sync_run_id,
		'location_id'       => $body['location_id'] ?? null,
		'complete'          => 0 === $counts['images_pending'],
		'image_fetch_limit' => $image_fetch_limit,
		'counts'            => $counts,
		'results'           => $results,
		'strays'            => report_strays( $scope ),
		'duration_ms'       => (int) round( ( microtime( true ) - $started ) * 1000 ),
	] );
}

/**
 * Work out how many image sideloads this request may perform.
 *
 * The payload's image_fetch_limit wins when it is a number; otherwise the
 * default applies. Either way the result is clamped to the range 1 to 100.
 *
 * @param mixed $requested The payload's image_fetch_limit, if any.
 * @return int
 */
function resolve_image_fetch_limit( $requested ): int {
	if ( is_int( $requested ) || ( is_string( $requested ) && is_numeric( $requested ) ) || is_float( $requested ) ) {
		$limit = (int) $requested;
	} else {
		/**
		 * Filters the default number of image sideloads per /sync request,
		 * used when the payload doesn't set image_fetch_limit.
		 *
		 * Keep it low enough that one request's downloads and thumbnail
		 * generation finish inside the host's gateway timeout.
		 *
		 * @param int $limit Default limit (8).
		 */
		$limit = (int) apply_filters( 'hk_fs_catalogue_image_fetch_limit', IMAGE_FETCH_LIMIT_DEFAULT );
	}

	return max( 1, min( IMAGE_FETCH_LIMIT_MAX, $limit ) );
}

/**
 * Seconds into a /sync request after which no new image sideload starts.
 *
 * @return float Between 1 and 300.
 */
function resolve_image_time_budget(): float {
	/**
	 * Filters how many seconds of a /sync request may be spent starting
	 * image sideloads. After that, the remaining images are reported as
	 * pending and the master fetches them on its next call.
	 *
	 * The default leaves room under a 60 second gateway timeout for one
	 * last download (see hk_fs_catalogue_image_download_timeout).
	 *
	 * @param int|float $seconds Default 35.
	 */
	$seconds = (float) apply_filters( 'hk_fs_catalogue_image_time_budget', IMAGE_TIME_BUDGET_DEFAULT );
	return max( 1.0, min( 300.0, $seconds ) );
}

/**
 * Seconds one image download may take inside a /sync request.
 *
 * @return float Between 1 and 300.
 */
function resolve_image_download_timeout(): float {
	/**
	 * Filters the HTTP timeout for each image download during /sync.
	 * Applies only to the sync's own sideloads, not to other HTTP requests.
	 *
	 * @param int|float $seconds Default 15.
	 */
	$seconds = (float) apply_filters( 'hk_fs_catalogue_image_download_timeout', IMAGE_DOWNLOAD_TIMEOUT_DEFAULT );
	return max( 1.0, min( 300.0, $seconds ) );
}

/**
 * Transient key for a sync run's remembered image failures.
 *
 * @param mixed $sync_run_id The payload's sync_run_id.
 * @return string Empty when the payload carries no usable run ID.
 */
function image_failures_key( $sync_run_id ): string {
	if ( ! is_string( $sync_run_id ) || '' === trim( $sync_run_id ) ) {
		return '';
	}
	return 'hk_fs_catalogue_image_failures_' . md5( trim( $sync_run_id ) );
}

/**
 * Image downloads that already failed earlier in this sync run.
 *
 * The master replays the same payload (same sync_run_id) to drain
 * pending images. Without this memory a broken image URL would be
 * retried on every replay and use up the per-request budget, so the
 * images queued behind it would never be fetched. A new run (a later
 * publish) tries the failed images again.
 *
 * @param mixed $sync_run_id The payload's sync_run_id.
 * @return array<string, string> Failure key → error message.
 */
function get_image_failures( $sync_run_id ): array {
	$key = image_failures_key( $sync_run_id );
	if ( '' === $key ) {
		return [];
	}
	$failures = get_transient( $key );
	return is_array( $failures ) ? $failures : [];
}

/**
 * Remember this run's failed image downloads for its later replays.
 *
 * @param mixed $sync_run_id The payload's sync_run_id.
 * @param array $failures    Failure key → error message.
 */
function save_image_failures( $sync_run_id, array $failures ): void {
	$key = image_failures_key( $sync_run_id );
	if ( '' !== $key ) {
		set_transient( $key, $failures, IMAGE_FAILURE_TTL );
	}
}

/**
 * Reconcile one payload product into its local post.
 *
 * @param array  $product      Payload product.
 * @param string $type         Catalogue type (pre-extracted).
 * @param string $master_id    Master ID (pre-normalised).
 * @param array  $image_budget This request's image budget (see rest_sync()), updated in place.
 * @return array Result entry.
 */
function reconcile_product( array $product, string $type, string $master_id, array &$image_budget ): array {
	$result = [
		'master_id'       => $master_id ?: null,
		'type'            => $type ?: null,
		'post_id'         => null,
		'result'          => 'error',
		// No image work happens for a product that fails before its images.
		'image_primary'   => 'none',
		'image_secondary' => 'none',
	];

	if ( ! is_uuid( $master_id ) ) {
		$result['error'] = 'invalid_master_id';
		return $result;
	}
	if ( ! in_array( $type, get_managed_capable_types(), true ) ) {
		$result['error'] = 'unsupported_type';
		return $result;
	}
	if ( ! is_type_available( $type ) ) {
		$result['error'] = 'type_disabled_on_site';
		return $result;
	}

	$name = trim( (string) ( $product['name'] ?? '' ) );
	if ( '' === $name ) {
		$result['error'] = 'missing_name';
		return $result;
	}

	$post_type  = post_type_for( $type );
	$content    = build_content( $post_type, (string) ( $product['description'] ?? '' ) );
	$menu_order = (int) ( $product['sort_order'] ?? 0 );
	$post_id    = find_post_by_master_id( $master_id, $post_type );
	$changed    = false;

	if ( ! $post_id ) {
		$post_id = wp_insert_post( wp_slash( [
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_title'   => $name,
			'post_content' => $content,
			'menu_order'   => $menu_order,
		] ), true );

		if ( is_wp_error( $post_id ) ) {
			$result['error'] = 'insert_failed: ' . $post_id->get_error_message();
			return $result;
		}

		update_post_meta( $post_id, '_hk_fs_master_id', $master_id );
		$result['post_id'] = $post_id;
		$result['result']  = 'created';
		$changed           = true;
	} else {
		$result['post_id'] = $post_id;
		$post              = get_post( $post_id );

		// Compare like with like: what this request would store, against
		// what is stored. The sync runs as user 0, so kses encodes "&" as
		// "&amp;" on save; a raw comparison saw a change on every publish.
		// Existing slug is kept on rename — no URL churn.
		$updates = [];
		if ( decode_text( $post->post_title ) !== decode_text( db_post_field( 'post_title', $name, $post_id ) ) ) {
			$updates['post_title'] = $name;
		}
		if ( trim( $post->post_content ) !== trim( db_post_field( 'post_content', $content, $post_id ) ) ) {
			$updates['post_content'] = $content;
		}
		if ( (int) $post->menu_order !== $menu_order ) {
			$updates['menu_order'] = $menu_order;
		}
		if ( 'publish' !== $post->post_status ) {
			$updates['post_status'] = 'publish'; // Reactivation, or untrash-by-sync.
		}

		if ( $updates ) {
			$updates['ID'] = $post_id;
			$updated       = wp_update_post( wp_slash( $updates ), true );
			if ( is_wp_error( $updated ) ) {
				$result['error'] = 'update_failed: ' . $updated->get_error_message();
				return $result;
			}
			$changed = true;
		}
	}

	// Price: numeric string, or the literal "POA" — the existing site
	// templates render non-numeric price strings verbatim.
	$price_meta = "_hk_fs_{$type}_price";
	$price      = format_price( $product['price'] ?? null, ! empty( $product['price_on_application'] ) );
	if ( (string) get_post_meta( $post_id, $price_meta, true ) !== $price ) {
		update_post_meta( $post_id, $price_meta, $price );
		$changed = true;
	}

	// Attributes map onto this CPT's registered meta fields (keepsakes:
	// product_code, metal, stones). Unknown keys are ignored.
	$attributes = is_array( $product['attributes'] ?? null ) ? $product['attributes'] : [];
	$registered = \HKFuneralSuite\PostTypes\get_meta_fields()[ $post_type ] ?? [];
	foreach ( $attributes as $key => $value ) {
		$meta_key = "_hk_fs_{$type}_" . sanitize_key( (string) $key );
		if ( $price_meta === $meta_key || ! isset( $registered[ $meta_key ] ) ) {
			continue;
		}
		$value = sanitize_text_field( (string) $value );
		if ( (string) get_post_meta( $post_id, $meta_key, true ) !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
			$changed = true;
		}
	}

	// Category (optional, master-owned, matched on stable master term ID).
	if ( sync_category( $post_id, $type, $product['category'] ?? null ) ) {
		$changed = true;
	}

	// Images, version-diffed so steady-state publishes re-download nothing,
	// and capped per request by the image budget. An image problem never
	// fails the product: its data above is already saved.
	$images           = is_array( $product['images'] ?? null ) ? $product['images'] : [];
	$image_results    = sync_images( $post_id, $images, $name, $image_budget );
	$result['images'] = $image_results;

	$image_errors = [];
	foreach ( $image_results as $image_result ) {
		if ( in_array( $image_result['result'], [ 'sideloaded', 'removed' ], true ) ) {
			$changed = true;
		}
		$result[ 'image_' . $image_result['role'] ] = image_status( $image_result['result'] );
		if ( 'error' === $image_result['result'] ) {
			$image_errors[] = $image_result['role'] . ': ' . ( $image_result['error'] ?? 'unknown_error' );
		}
	}
	if ( $image_errors ) {
		$result['image_error'] = implode( '; ', $image_errors );
	}

	if ( 'created' !== $result['result'] ) {
		$result['result'] = $changed ? 'updated' : 'unchanged';
	}

	return $result;
}

/**
 * Per-product image status for one role, from that role's image result.
 *
 * @param string $image_result Result from sync_images().
 * @return string One of none, unchanged, fetched, pending, error.
 */
function image_status( string $image_result ): string {
	$map = [
		'sideloaded' => 'fetched',
		'unchanged'  => 'unchanged',
		'pending'    => 'pending',
		'error'      => 'error',
		'removed'    => 'none',
	];
	return $map[ $image_result ] ?? 'none';
}

/**
 * The value WordPress would store for a post field in this request.
 *
 * Runs the same pre-save filters wp_insert_post() applies (kses for
 * users without unfiltered_html, which includes the sync's user 0), so
 * the result compares like with like against the stored field.
 *
 * @param string $field   Post field, e.g. post_title.
 * @param string $value   Raw value.
 * @param int    $post_id Post ID.
 * @return string
 */
function db_post_field( string $field, string $value, int $post_id ): string {
	return (string) wp_unslash( sanitize_post_field( $field, wp_slash( $value ), $post_id, 'db' ) );
}

/**
 * The name WordPress would store for a term in this request.
 *
 * Term names always go through sanitize_text_field, kses and
 * _wp_specialchars on save, so "Wood & Bamboo" is stored as
 * "Wood &amp; Bamboo".
 *
 * @param string $name     Raw name.
 * @param int    $term_id  Term ID (0 for a new term).
 * @param string $taxonomy Taxonomy.
 * @return string
 */
function db_term_name( string $name, int $term_id, string $taxonomy ): string {
	return (string) wp_unslash( sanitize_term_field( 'name', wp_slash( $name ), $term_id, $taxonomy, 'db' ) );
}

/**
 * Decode HTML entities in a stored title or term name.
 *
 * @param string $value Stored value, e.g. "Harper &amp; Lowe".
 * @return string Plain text, e.g. "Harper & Lowe".
 */
function decode_text( string $value ): string {
	return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/**
 * Normalise a name for case-insensitive matching.
 *
 * @param string $value Name, raw or as stored.
 * @return string
 */
function normalise_name( string $value ): string {
	$value = trim( (string) preg_replace( '/\s+/u', ' ', decode_text( $value ) ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
}

/**
 * Format a payload price for the site's price meta.
 *
 * @param mixed $price Numeric price from the payload (GST-inclusive NZD).
 * @param bool  $poa   Price-on-application flag.
 * @return string
 */
function format_price( $price, bool $poa ): string {
	if ( $poa ) {
		return 'POA';
	}
	if ( null === $price || '' === $price || ! is_numeric( $price ) ) {
		return '';
	}
	$formatted = number_format( (float) $price, 2, '.', '' );
	// Whole-dollar prices store without decimals, matching existing data.
	return rtrim( rtrim( $formatted, '0' ), '.' );
}

/**
 * Build post content for a synced product: the CPT's meta block first
 * (the block editor template expects it), then the description.
 *
 * @param string $post_type   Post type slug.
 * @param string $description Resolved description from the master.
 * @return string
 */
function build_content( string $post_type, string $description ): string {
	$content     = \HKFuneralSuite\Import\get_default_block( $post_type );
	$description = trim( $description );

	if ( '' === $description ) {
		return $content;
	}

	if ( strpos( $description, '<!-- wp:' ) !== false ) {
		// Already block markup (e.g. captured from a site during import).
		return $content . "\n\n" . wp_kses_post( $description );
	}

	if ( has_block_level_html( $description ) ) {
		// Lists, headings, tables and the like can't be split per line:
		// that wrapped "<ul>" in its own paragraph. Keep the description
		// whole in one Custom HTML block; wpautop() wraps any loose text
		// lines in paragraphs and leaves block-level elements alone.
		return $content . "\n\n<!-- wp:html -->\n" . trim( wp_kses_post( wpautop( $description ) ) ) . "\n<!-- /wp:html -->";
	}

	// Plain text or inline HTML: one paragraph block per line, same
	// convention as the WP All Import path in inc/import.php.
	$paragraphs = array_filter( array_map( 'trim', explode( "\n", $description ) ) );
	foreach ( $paragraphs as $paragraph ) {
		$content .= "\n\n<!-- wp:paragraph -->\n<p>" . wp_kses_post( $paragraph ) . "</p>\n<!-- /wp:paragraph -->";
	}

	return $content;
}

/**
 * Whether a description contains block-level HTML elements.
 *
 * Any of these means the description can't be split into one paragraph
 * per line without wrapping a block element in "<p>". Inline tags
 * (strong, em, a, span, br) don't count.
 *
 * @param string $description Description from the master.
 * @return bool
 */
function has_block_level_html( string $description ): bool {
	$tags = 'p|div|h[1-6]|blockquote|pre|hr|address'
		. '|ul|ol|li|dl|dd|dt'
		. '|table|caption|thead|tbody|tfoot|tr|td|th'
		. '|figure|figcaption|details|summary'
		. '|section|article|aside|nav|header|footer';
	return (bool) preg_match( '/<(?:' . $tags . ')[\s>\/]/i', $description );
}

/**
 * Ensure the payload category term exists and is assigned to the post.
 *
 * Terms are matched on _hk_fs_master_category_id term meta (rename-safe,
 * same stable-ID pattern as products). A null category clears the post's
 * terms in the catalogue taxonomy.
 *
 * @param int        $post_id  Post ID.
 * @param string     $type     Catalogue type.
 * @param array|null $category Payload category, or null.
 * @return bool Whether anything changed.
 */
function sync_category( int $post_id, string $type, $category ): bool {
	$taxonomy = taxonomy_for( $type );
	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return false;
	}

	$current = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
	$current = is_wp_error( $current ) ? [] : array_map( 'intval', $current );

	$name      = is_array( $category ) ? trim( (string) ( $category['name'] ?? '' ) ) : '';
	$master_id = is_array( $category ) ? strtolower( trim( (string) ( $category['master_id'] ?? '' ) ) ) : '';

	if ( '' === $name || ! is_uuid( $master_id ) ) {
		if ( $current ) {
			wp_set_object_terms( $post_id, [], $taxonomy );
			return true;
		}
		return false;
	}

	$term_id = 0;
	$changed = false;

	$existing = get_terms( [
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'number'     => 1,
		'meta_key'   => '_hk_fs_master_category_id',
		'meta_value' => $master_id,
	] );

	if ( ! is_wp_error( $existing ) && $existing ) {
		$term_id = (int) $existing[0]->term_id;
		if ( rename_term_if_needed( $existing[0], $name, $taxonomy ) ) {
			$changed = true;
		}
	} else {
		// Adopt an unstamped site term that already carries this name,
		// ignoring case, or this name's slug, before creating one:
		// wp_insert_term() only catches an exact-case duplicate, so
		// "solid wood range" with a custom slug would otherwise gain a
		// near-duplicate sibling. A stamped term belongs to its own master
		// category and is only ever matched by that master ID (above).
		$term_id = find_adoptable_term( $taxonomy, $name );

		if ( $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				rename_term_if_needed( $term, $name, $taxonomy );
			}
		} else {
			$term_id = insert_category_term( $taxonomy, $name );
			if ( ! $term_id ) {
				return $changed;
			}
		}
		update_term_meta( $term_id, '_hk_fs_master_category_id', $master_id );
		$changed = true;
	}

	if ( [ $term_id ] !== $current ) {
		wp_set_object_terms( $post_id, [ $term_id ], $taxonomy );
		$changed = true;
	}

	return $changed;
}

/**
 * Rename a term to the master's name if the stored name differs.
 *
 * Compares like with like (the name as WordPress would store it, both
 * sides entity-decoded), so "Wood & Bamboo" never counts as a rename of
 * "Wood &amp; Bamboo". The slug is kept: wp_update_term() only changes
 * it when one is passed.
 *
 * @param \WP_Term $term     Existing term.
 * @param string   $name     Master name.
 * @param string   $taxonomy Taxonomy.
 * @return bool Whether the term was renamed.
 */
function rename_term_if_needed( \WP_Term $term, string $name, string $taxonomy ): bool {
	$stored = decode_text( $term->name );
	if ( decode_text( db_term_name( $name, (int) $term->term_id, $taxonomy ) ) === $stored ) {
		return false;
	}
	$updated = wp_update_term( (int) $term->term_id, $taxonomy, wp_slash( [ 'name' => $name ] ) );
	return ! is_wp_error( $updated );
}

/**
 * Find an existing unstamped term a master category should adopt.
 *
 * Matches the name case-insensitively (entities decoded, whitespace
 * collapsed), or the slug WordPress derives from the name. A name match
 * wins over a slug match; ties go to the oldest term.
 *
 * Terms stamped with a master category ID are never adopted here, even
 * when the stamp is stale. The master's category names are unique only
 * case-sensitively, so "Solid Wood" and "Solid-Wood" (same slug) or
 * "Solid Wood" and "solid wood" can both exist; adopting a stamped term
 * would let two master categories take turns restamping one site term on
 * every publish.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $name     Master category name.
 * @return int Term ID, or 0 when nothing matches.
 */
function find_adoptable_term( string $taxonomy, string $name ): int {
	$terms = get_terms( [
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'orderby'    => 'term_id',
		'order'      => 'ASC',
	] );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return 0;
	}

	$wanted_name = normalise_name( $name );
	$wanted_slug = sanitize_title( $name );
	$best_id     = 0;
	$best_rank   = PHP_INT_MAX;

	foreach ( $terms as $term ) {
		if ( normalise_name( $term->name ) === $wanted_name ) {
			$rank = 0;
		} elseif ( '' !== $wanted_slug && $term->slug === $wanted_slug ) {
			$rank = 1;
		} else {
			continue;
		}
		if ( is_stamped_term( (int) $term->term_id ) ) {
			continue;
		}
		if ( $rank < $best_rank ) {
			$best_id   = (int) $term->term_id;
			$best_rank = $rank;
		}
	}

	return $best_id;
}

/**
 * Whether a term carries a master category ID.
 *
 * @param int $term_id Term ID.
 * @return bool
 */
function is_stamped_term( int $term_id ): bool {
	return '' !== (string) get_term_meta( $term_id, '_hk_fs_master_category_id', true );
}

/**
 * Create the site term for a master category that has none yet.
 *
 * WordPress refuses a new term whose name already exists (ignoring case
 * for the duplicate check). If that term is unstamped it is adopted. If
 * another master category owns it, a separate term is created under the
 * same name with the next free slug ("solid-wood-2"), so each master
 * category keeps its own term.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $name     Master category name.
 * @return int Term ID, or 0 on failure.
 */
function insert_category_term( string $taxonomy, string $name ): int {
	$inserted = wp_insert_term( wp_slash( $name ), $taxonomy );
	if ( ! is_wp_error( $inserted ) ) {
		return (int) $inserted['term_id'];
	}

	$conflict_id = (int) ( $inserted->get_error_data( 'term_exists' ) ?: 0 );
	if ( ! $conflict_id ) {
		return 0;
	}
	if ( ! is_stamped_term( $conflict_id ) ) {
		return $conflict_id;
	}

	$base = sanitize_title( $name );
	if ( '' === $base ) {
		return 0;
	}
	$new_term = (object) [
		'taxonomy' => $taxonomy,
		'parent'   => 0,
	];
	$inserted = wp_insert_term( wp_slash( $name ), $taxonomy, [ 'slug' => wp_unique_term_slug( $base, $new_term ) ] );

	return is_wp_error( $inserted ) ? 0 : (int) $inserted['term_id'];
}

/**
 * Sync a product's images, diffed on the version hash so unchanged
 * images are never re-downloaded.
 *
 * Primary becomes the featured image; secondary is stored as an
 * attachment + meta for future template use. Replaced attachments stay
 * in the media library (media retained, per design).
 *
 * Each download spends one unit of the request's image budget. When the
 * budget is used up, or the request's time budget has run out, remaining
 * downloads are reported as "pending" and the post keeps whatever image
 * it has until a later call fetches them.
 * A download that failed earlier in the same sync run is reported as an
 * error again without retrying it.
 *
 * @param int    $post_id Post ID.
 * @param array  $images  Payload images: [ { role, version, url }, … ].
 * @param string $title   Product name, used as the attachment description.
 * @param array  $budget  This request's image budget, updated in place.
 * @return array Per-role results.
 */
function sync_images( int $post_id, array $images, string $title, array &$budget ): array {
	$roles = [
		'primary'   => [ 'version_meta' => '_hk_fs_image_version', 'id_meta' => '_thumbnail_id' ],
		'secondary' => [ 'version_meta' => '_hk_fs_image_version_2', 'id_meta' => '_hk_fs_image_id_2' ],
	];

	$by_role = [];
	foreach ( $images as $image ) {
		if ( is_array( $image ) && isset( $roles[ $image['role'] ?? '' ] ) ) {
			$by_role[ $image['role'] ] = $image;
		}
	}

	$results = [];

	foreach ( $roles as $role => $meta_keys ) {
		$entry           = [ 'role' => $role ];
		$current_version = (string) get_post_meta( $post_id, $meta_keys['version_meta'], true );
		$current_id      = (int) get_post_meta( $post_id, $meta_keys['id_meta'], true );
		$image           = $by_role[ $role ] ?? null;

		if ( ! $image ) {
			// Desired state has no image in this role. Only remove an image
			// the sync itself set, marked by its version meta. An adopted
			// post's pre-existing featured image is site presentation, and
			// a master that has no image yet must never strip it. (The
			// secondary id meta is sync-owned, so it counts as a marker.)
			$sync_owned = 'primary' === $role
				? '' !== $current_version
				: ( '' !== $current_version || $current_id );
			if ( $sync_owned ) {
				delete_post_meta( $post_id, $meta_keys['version_meta'] );
				if ( 'primary' === $role ) {
					delete_post_thumbnail( $post_id );
				} else {
					delete_post_meta( $post_id, $meta_keys['id_meta'] );
				}
				$entry['result'] = 'removed';
				$results[]       = $entry;
			}
			continue;
		}

		$url     = (string) ( $image['url'] ?? '' );
		$version = (string) ( $image['version'] ?? '' );

		if ( '' === $url ) {
			$entry['result'] = 'error';
			$entry['error']  = 'missing_url';
			$results[]       = $entry;
			continue;
		}

		// Without a version, an image would never count as unchanged and
		// every replay would download it again. The master's storage paths
		// are content-addressed, so the URL stands in as the version.
		if ( '' === $version ) {
			$version = $url;
		}

		$attachment_exists = $current_id && get_post( $current_id );
		if ( $version === $current_version && $attachment_exists ) {
			$entry['result']        = 'unchanged';
			$entry['attachment_id'] = $current_id;
			$results[]              = $entry;
			continue;
		}

		$failure_key = implode( '|', [ $post_id, $role, $version, $url ] );
		if ( isset( $budget['failures'][ $failure_key ] ) ) {
			$entry['result'] = 'error';
			$entry['error']  = (string) $budget['failures'][ $failure_key ];
			$results[]       = $entry;
			continue;
		}

		if ( $budget['remaining'] < 1 || microtime( true ) >= $budget['deadline'] ) {
			$entry['result'] = 'pending';
			$results[]       = $entry;
			continue;
		}
		--$budget['remaining'];

		$attachment_id = sideload_image( $url, $post_id, $title, (float) $budget['timeout'] );

		if ( is_wp_error( $attachment_id ) ) {
			$entry['result']                    = 'error';
			$entry['error']                     = $attachment_id->get_error_message();
			$budget['failures'][ $failure_key ] = $entry['error'];
			$budget['dirty']                    = true;
			$results[]                          = $entry;
			continue;
		}

		if ( 'primary' === $role ) {
			set_post_thumbnail( $post_id, $attachment_id );
		} else {
			update_post_meta( $post_id, $meta_keys['id_meta'], (int) $attachment_id );
		}
		update_post_meta( $post_id, $meta_keys['version_meta'], $version );

		$entry['result']        = 'sideloaded';
		$entry['attachment_id'] = (int) $attachment_id;
		$results[]              = $entry;
	}

	return $results;
}

/**
 * Sideload one image with a capped download timeout.
 *
 * WordPress sideloads through download_url(), which asks for a 300 second
 * timeout. The http_request_args filter lowers it for this
 * download only: it is added just before the call and removed straight
 * after, so no other HTTP request in the site is affected.
 *
 * @param string $url     Image URL.
 * @param int    $post_id Post the attachment belongs to.
 * @param string $title   Attachment description.
 * @param float  $timeout Seconds the download may take.
 * @return int|\WP_Error Attachment ID, or the error.
 */
function sideload_image( string $url, int $post_id, string $title, float $timeout ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$cap_timeout = static function ( $args ) use ( $timeout ) {
		if ( is_array( $args ) && ( ! isset( $args['timeout'] ) || (float) $args['timeout'] > $timeout ) ) {
			$args['timeout'] = $timeout;
		}
		return $args;
	};

	add_filter( 'http_request_args', $cap_timeout, PHP_INT_MAX );
	try {
		$attachment_id = media_sideload_image( esc_url_raw( $url ), $post_id, $title, 'id' );
	} finally {
		remove_filter( 'http_request_args', $cap_timeout, PHP_INT_MAX );
	}

	return is_wp_error( $attachment_id ) ? $attachment_id : (int) $attachment_id;
}

/**
 * Deactivate stamped posts the payload no longer contains: post status
 * → draft. Soft and reversible; media is retained and a later payload
 * containing the master ID republishes the same post.
 *
 * @param string[] $scope_types Catalogue types the payload governs.
 * @param array    $payload_ids Master IDs present in the payload, per type.
 * @return array Result entries.
 */
function deactivate_absent( array $scope_types, array $payload_ids ): array {
	$results = [];

	foreach ( $scope_types as $type ) {
		if ( ! is_type_available( $type ) ) {
			continue;
		}

		$keep       = $payload_ids[ $type ] ?? [];
		$meta_query = [
			[
				'key'     => '_hk_fs_master_id',
				'value'   => '',
				'compare' => '!=',
			],
		];
		if ( $keep ) {
			$meta_query[] = [
				'key'     => '_hk_fs_master_id',
				'value'   => $keep,
				'compare' => 'NOT IN',
			];
		}

		$absent = get_posts( [
			'post_type'      => post_type_for( $type ),
			'post_status'    => [ 'publish', 'future', 'pending', 'private' ],
			'hk_fs_catalogue_status' => [ 'publish', 'future', 'pending', 'private' ], // Restored after other plugins' pre_get_posts.
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		] );

		foreach ( $absent as $post_id ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'draft',
			] );
			$results[] = [
				'master_id'       => (string) get_post_meta( $post_id, '_hk_fs_master_id', true ),
				'type'            => $type,
				'post_id'         => (int) $post_id,
				'result'          => 'deactivated',
				'image_primary'   => 'none',
				'image_secondary' => 'none',
			];
		}
	}

	return $results;
}

/**
 * Report stray posts: managed-type posts with no master ID. Never
 * touched by the sync — after switchover none should exist, so the
 * master surfaces them for manual resolution.
 *
 * @param string[] $scope_types Catalogue types in scope.
 * @return array
 */
function report_strays( array $scope_types ): array {
	$strays = [];

	foreach ( $scope_types as $type ) {
		if ( ! is_type_available( $type ) ) {
			continue;
		}

		$posts = get_posts( [
			'post_type'      => post_type_for( $type ),
			'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
			'hk_fs_catalogue_status' => [ 'publish', 'future', 'draft', 'pending', 'private' ], // Restored after other plugins' pre_get_posts.
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				[
					'key'     => '_hk_fs_master_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_hk_fs_master_id',
					'value'   => '',
					'compare' => '=',
				],
			],
		] );

		foreach ( $posts as $post ) {
			$strays[] = [
				'type'    => $type,
				'post_id' => $post->ID,
				'title'   => decode_text( $post->post_title ),
				'status'  => $post->post_status,
			];
		}
	}

	return $strays;
}

// ─── Editor Lock ────────────────────────────────────────────────────────────

/**
 * Post type slugs currently locked for local editing.
 *
 * @return string[]
 */
function get_locked_post_types(): array {
	return array_map( __NAMESPACE__ . '\\post_type_for', get_locked_types() );
}

/**
 * Remove the add-new UI for locked types by denying the create cap.
 *
 * map_meta_cap is pinned true so edit/delete stay ordinary meta caps
 * (denied per post in lock_meta_caps) rather than unmapped primitives.
 *
 * @param array  $args      Post type registration args.
 * @param string $post_type Post type slug.
 * @return array
 */
function lock_post_type_caps( array $args, string $post_type ): array {
	if ( ! in_array( $post_type, get_locked_post_types(), true ) ) {
		return $args;
	}

	$capabilities                 = isset( $args['capabilities'] ) && is_array( $args['capabilities'] ) ? $args['capabilities'] : [];
	$capabilities['create_posts'] = 'do_not_allow';

	$args['capabilities'] = $capabilities;
	$args['map_meta_cap'] = true;

	return $args;
}

/**
 * Lock the category taxonomy of a locked type: the catalogue owns these
 * names and matches terms on _hk_fs_master_category_id, so a local rename or
 * delete would be overwritten or orphan products on the next publish.
 *
 * Without manage_terms WordPress drops the menu item and refuses the screen.
 * Reading terms needs no capability, so grids and filters are unaffected.
 *
 * @param array  $args     Taxonomy registration args.
 * @param string $taxonomy Taxonomy slug.
 * @return array
 */
function lock_taxonomy_caps( array $args, string $taxonomy ): array {
	if ( ! in_array( $taxonomy, array_map( __NAMESPACE__ . '\\taxonomy_for', get_locked_types() ), true ) ) {
		return $args;
	}

	$args['capabilities'] = array_merge(
		isset( $args['capabilities'] ) && is_array( $args['capabilities'] ) ? $args['capabilities'] : [],
		array_fill_keys( [ 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms' ], 'do_not_allow' )
	);

	return $args;
}

/**
 * Deny edit and delete on posts of locked types.
 *
 * @param string[]   $caps    Primitive capabilities required.
 * @param string     $cap     Meta capability being checked.
 * @param int|string $user_id User ID (untyped: callers vary under strict_types).
 * @param array      $args    Additional args — [0] is the post ID.
 * @return string[]
 */
function lock_meta_caps( array $caps, string $cap, $user_id, array $args ): array {
	if ( ! in_array( $cap, [ 'edit_post', 'delete_post' ], true ) || empty( $args[0] ) ) {
		return $caps;
	}

	$post = get_post( (int) $args[0] );
	if ( $post && in_array( $post->post_type, get_locked_post_types(), true ) ) {
		return [ 'do_not_allow' ];
	}

	return $caps;
}

/**
 * Badge locked posts in list tables.
 *
 * @param string[] $post_states Post state labels.
 * @param \WP_Post $post        The post.
 * @return string[]
 */
function lock_post_state( array $post_states, $post ): array {
	if ( $post instanceof \WP_Post && in_array( $post->post_type, get_locked_post_types(), true ) ) {
		$post_states['hk_fs_catalogue'] = __( 'Managed by HumanKind Catalogue', 'hk-funeral-suite' );
	}
	return $post_states;
}

/**
 * Explain the lock on list tables for locked types.
 */
function lock_admin_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit' !== $screen->base || ! in_array( $screen->post_type, get_locked_post_types(), true ) ) {
		return;
	}

	$post_type_object = get_post_type_object( $screen->post_type );
	$plural           = $post_type_object ? $post_type_object->labels->name : $screen->post_type;
	?>
	<div class="notice notice-info">
		<p>
			<span class="dashicons dashicons-lock" style="color:#0073aa; font-size:18px; vertical-align:middle;"></span>
			<strong><?php esc_html_e( 'Managed by HumanKind Catalogue:', 'hk-funeral-suite' ); ?></strong>
			<?php
			printf(
				/* translators: %s: post type plural name */
				esc_html__( '%s and their categories on this site are managed by the central catalogue. Adding, editing and deleting here is disabled. Changes arrive automatically when the catalogue publishes.', 'hk-funeral-suite' ),
				esc_html( $plural )
			);
			?>
		</p>
	</div>
	<?php
}
