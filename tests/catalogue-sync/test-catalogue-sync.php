<?php
/**
 * Catalogue sync receiver tests.
 *
 * Repeatable end-to-end checks for inc/catalogue-sync.php. Every request
 * is HMAC-signed and dispatched through rest_do_request(), the way the
 * master catalogue sends it, and runs as user 0 like the real requests.
 *
 * Needs a disposable site with this plugin active and the casket and urn
 * post types enabled, plus a local web server for the test images. The
 * run.sh wrapper next to this file starts one and then runs:
 *
 *   wp eval-file tests/catalogue-sync/test-catalogue-sync.php <image_dir> <image_base_url>
 *
 * Never run this on a real site. It writes a test secret, creates and
 * deletes products, terms and media, and runs uninstall.php. It refuses
 * to start when HK_FS_CATALOGUE_SECRET is defined or when any post or
 * term carries a master ID it didn't create. Set HKFS_TEST_KEEP=1 to
 * leave the test products in place afterwards for inspection.
 *
 * No strict_types or namespace here: wp eval-file evals the file, and
 * both must be the first statement of a real file. Everything is
 * prefixed hk_fs_test_ instead.
 *
 * @package HKFuneralSuite
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

const HK_FS_TEST_SECRET         = 'hkfs-catalogue-sync-test-secret';
const HK_FS_TEST_ID_PREFIX      = 'a0000000-0000-4000-';
const HK_FS_TEST_LOCATION_ID    = 'a0000000-0000-4000-a000-000000000001';
const HK_FS_TEST_PURGE_CALLBACK = 'HKFuneralSuite\\Hooks\\run_cache_purge';

// ─── Harness state and helpers ──────────────────────────────────────────────

/**
 * Shared harness state.
 *
 * @return array
 */
function &hk_fs_test_state(): array {
	static $state = [
		'pass'      => 0,
		'fail'      => 0,
		'failures'  => [],
		'base_url'  => '',
		'updates'   => [],
		'http'      => [],
		'purges'    => [],
		'admin_id'  => 0,
		'test_ids'  => [],
		'legacy'    => [],
		'stray_id'  => 0,
		'term_ids'  => [],
		'last_body' => null,
	];
	return $state;
}

/**
 * Print a section heading.
 *
 * @param string $title Heading.
 */
function hk_fs_test_section( string $title ): void {
	\WP_CLI::log( '' );
	\WP_CLI::log( '== ' . $title );
}

/**
 * Record and print one assertion.
 *
 * @param string $label  What is being checked.
 * @param bool   $ok     Whether it held.
 * @param mixed  $detail Shown on failure.
 */
function hk_fs_test_check( string $label, bool $ok, $detail = null ): void {
	$state = &hk_fs_test_state();
	if ( $ok ) {
		$state['pass']++;
		\WP_CLI::log( '  PASS  ' . $label );
		return;
	}
	$state['fail']++;
	$state['failures'][] = $label;
	$suffix              = null === $detail ? '' : '  [got: ' . wp_json_encode( $detail ) . ']';
	\WP_CLI::log( '  FAIL  ' . $label . $suffix );
}

/**
 * Fixture master ID (products and categories share the test prefix).
 *
 * @param int $n Fixture number.
 * @return string
 */
function hk_fs_test_mid( int $n ): string {
	return sprintf( '%s8000-%012d', HK_FS_TEST_ID_PREFIX, $n );
}

/**
 * Fixture master category ID.
 *
 * @param int $n Fixture number.
 * @return string
 */
function hk_fs_test_cid( int $n ): string {
	return sprintf( '%s9000-%012d', HK_FS_TEST_ID_PREFIX, $n );
}

/**
 * Dispatch a REST request, optionally signed like the master signs it.
 *
 * @param string     $method HTTP method.
 * @param string     $route  Route, e.g. /hk-fs-catalogue/v1/state.
 * @param array|null $body   JSON body, or null for none.
 * @param array      $opts   unsigned, timestamp, signature, sign_body, query.
 * @return \WP_REST_Response
 */
function hk_fs_test_request( string $method, string $route, ?array $body = null, array $opts = [] ): \WP_REST_Response {
	$raw     = null === $body ? '' : (string) wp_json_encode( $body );
	$request = new \WP_REST_Request( $method, $route );

	if ( ! empty( $opts['query'] ) ) {
		$request->set_query_params( $opts['query'] );
	}
	if ( '' !== $raw ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $raw );
	}

	if ( empty( $opts['unsigned'] ) ) {
		$timestamp = (string) ( $opts['timestamp'] ?? time() );
		$signed    = $opts['sign_body'] ?? $raw;
		$signature = $opts['signature'] ?? hash_hmac( 'sha256', $timestamp . '.' . $signed, HK_FS_TEST_SECRET );
		$request->set_header( 'X-HKFS-Timestamp', $timestamp );
		$request->set_header( 'X-HKFS-Signature', $signature );
	}

	return rest_do_request( $request );
}

/**
 * Error code from a REST error response.
 *
 * @param \WP_REST_Response $response Response.
 * @return string
 */
function hk_fs_test_error_code( \WP_REST_Response $response ): string {
	$data = $response->get_data();
	return is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '';
}

/**
 * POST a /sync payload and return the response data.
 *
 * @param array    $products    Payload products.
 * @param string   $sync_run_id Run ID.
 * @param int|null $limit       image_fetch_limit, or null to omit it.
 * @param array    $extra       Extra top-level body fields.
 * @return array{status: int, data: array}
 */
function hk_fs_test_sync( array $products, string $sync_run_id, ?int $limit = null, array $extra = [] ): array {
	$body = array_merge( [
		'schema_version' => 1,
		'location_id'    => HK_FS_TEST_LOCATION_ID,
		'published_at'   => gmdate( 'c' ),
		'sync_run_id'    => $sync_run_id,
		'products'       => array_values( $products ),
	], $extra );
	if ( null !== $limit ) {
		$body['image_fetch_limit'] = $limit;
	}

	$state              = &hk_fs_test_state();
	$state['last_body'] = $body;
	$state['updates']   = [];

	$response = hk_fs_test_request( 'POST', '/hk-fs-catalogue/v1/sync', $body );
	$data     = $response->get_data();

	return [
		'status' => $response->get_status(),
		'data'   => is_array( $data ) ? $data : [],
	];
}

/**
 * The result entry for a master ID in a /sync response.
 *
 * @param array  $data      Response data.
 * @param string $master_id Master ID.
 * @param string $result    Optional result filter (e.g. deactivated).
 * @return array
 */
function hk_fs_test_result_for( array $data, string $master_id, string $result = '' ): array {
	foreach ( $data['results'] ?? [] as $entry ) {
		if ( ( $entry['master_id'] ?? '' ) === $master_id && ( '' === $result || $entry['result'] === $result ) ) {
			return $entry;
		}
	}
	return [];
}

/**
 * Local post ID for a fixture.
 *
 * @param array $product Fixture product.
 * @return int
 */
function hk_fs_test_post_id_for( array $product ): int {
	return \HKFuneralSuite\CatalogueSync\find_post_by_master_id( $product['master_id'], \HKFuneralSuite\CatalogueSync\post_type_for( $product['type'] ) );
}

/**
 * Run the queued cache purge now (what shutdown would do) and return
 * the purges it produced.
 *
 * @return array List of [ post_ids, contexts ].
 */
function hk_fs_test_flush_purges(): array {
	$state           = &hk_fs_test_state();
	$state['purges'] = [];
	\HKFuneralSuite\Hooks\run_cache_purge();
	return $state['purges'];
}

/**
 * How many times the purge callback is registered on shutdown.
 *
 * @return int
 */
function hk_fs_test_purge_callbacks_registered(): int {
	global $wp_filter;
	$count = 0;
	if ( isset( $wp_filter['shutdown'] ) ) {
		foreach ( $wp_filter['shutdown']->callbacks as $callbacks ) {
			if ( isset( $callbacks[ HK_FS_TEST_PURGE_CALLBACK ] ) ) {
				$count++;
			}
		}
	}
	return $count;
}

/**
 * Number of attachments on the site.
 *
 * @return int
 */
function hk_fs_test_attachment_count(): int {
	// Not wp_count_posts(): its result is cached for the whole request.
	return count( get_posts( [
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] ) );
}

/**
 * Revision counts per post.
 *
 * @param int[] $post_ids Post IDs.
 * @return array<int, int>
 */
function hk_fs_test_revision_counts( array $post_ids ): array {
	$out = [];
	foreach ( $post_ids as $post_id ) {
		$out[ $post_id ] = count( wp_get_post_revisions( $post_id ) );
	}
	return $out;
}

/**
 * HTTP requests made to a URL since the counter was last reset.
 *
 * @param string $url URL.
 * @return int
 */
function hk_fs_test_http_count( string $url ): int {
	$state = &hk_fs_test_state();
	return (int) ( $state['http'][ $url ] ?? 0 );
}

/**
 * Write the test images into the directory the local server serves.
 *
 * @param string $dir Directory.
 */
function hk_fs_test_write_images( string $dir ): void {
	$names = [ 'img-1', 'img-2', 'img-3', 'img-4', 'img-5', 'img-6', 'sec-1', 'legacy-1', 'legacy-2' ];
	foreach ( $names as $i => $name ) {
		$path = trailingslashit( $dir ) . $name . '.png';
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$image = imagecreatetruecolor( 64, 64 );
			imagefill( $image, 0, 0, (int) imagecolorallocate( $image, ( 40 * $i ) % 256, ( 90 + 30 * $i ) % 256, ( 200 - 20 * $i ) % 256 ) );
			imagepng( $image, $path );
		} else {
			// 1x1 PNG.
			file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fixture PNG.
		}
	}
}

/**
 * Image URL on the local test server.
 *
 * @param string $name File name without extension.
 * @return string
 */
function hk_fs_test_image_url( string $name ): string {
	return hk_fs_test_state()['base_url'] . '/' . $name . '.png';
}

/**
 * Fixture products: the full desired state the tests start from.
 *
 * @return array<string, array>
 */
function hk_fs_test_fixtures(): array {
	$wood = [
		'master_id'  => hk_fs_test_cid( 1 ),
		'name'       => 'Wood & Bamboo',
		'sort_order' => 10,
	];

	$solid = [
		'master_id'  => hk_fs_test_cid( 2 ),
		'name'       => 'Solid Wood Range',
		'sort_order' => 20,
	];

	$veneer = [
		'master_id'  => hk_fs_test_cid( 3 ),
		'name'       => 'Veneer Wood Range',
		'sort_order' => 30,
	];

	$ceramic = [
		'master_id'  => hk_fs_test_cid( 4 ),
		'name'       => 'ceramic, fibreglass & metal',
		'sort_order' => 10,
	];

	$image = static function ( string $role, string $file, string $version ): array {
		return [
			'role'    => $role,
			'version' => $version,
			'url'     => hk_fs_test_image_url( $file ),
		];
	};

	return [
		'C1' => [
			'master_id'            => hk_fs_test_mid( 1 ),
			'type'                 => 'casket',
			'name'                 => 'Harper & Lowe - Red',
			'description'          => "<p>Solid & strong.</p>\n<ul>\n<li>Brass handles</li>\n<li>Satin lining</li>\n</ul>\n<script>alert(1)</script>",
			'price'                => 2499.5,
			'price_on_application' => false,
			'category'             => $wood,
			'sort_order'           => 10,
			'attributes'           => [],
			'images'               => [ $image( 'primary', 'img-1', 'v-img-1' ) ],
		],
		'C2' => [
			'master_id'            => hk_fs_test_mid( 2 ),
			'type'                 => 'casket',
			'name'                 => 'Rimu Classic',
			'description'          => 'Native rimu, hand finished.',
			'price'                => 3200,
			'price_on_application' => false,
			'category'             => $solid,
			'sort_order'           => 20,
			'attributes'           => [],
			'images'               => [ $image( 'primary', 'img-2', 'v-img-2' ), $image( 'secondary', 'sec-1', 'v-sec-1' ) ],
		],
		'C3' => [
			'master_id'            => hk_fs_test_mid( 3 ),
			'type'                 => 'casket',
			'name'                 => 'Pine Simple',
			'description'          => '',
			'price'                => null,
			'price_on_application' => true,
			'category'             => $veneer,
			'sort_order'           => 30,
			'attributes'           => [],
			'images'               => [ $image( 'primary', 'img-3', 'v-img-3' ) ],
		],
		'C4' => [
			'master_id'            => hk_fs_test_mid( 4 ),
			'type'                 => 'casket',
			'name'                 => 'Bamboo Eco',
			'description'          => "Line one.\nLine two & more.",
			'price'                => 1500,
			'price_on_application' => false,
			'category'             => $wood,
			'sort_order'           => 40,
			'attributes'           => [],
			'images'               => [ $image( 'primary', 'img-4', 'v-img-4' ) ],
		],
		'C5' => [
			'master_id'            => hk_fs_test_mid( 5 ),
			'type'                 => 'casket',
			'name'                 => 'Oak Heritage',
			'description'          => '',
			'price'                => 5000,
			'price_on_application' => false,
			'category'             => null,
			'sort_order'           => 50,
			'attributes'           => [],
			'images'               => [ $image( 'primary', 'img-5', 'v-img-5' ) ],
		],
		'U1' => [
			'master_id'            => hk_fs_test_mid( 11 ),
			'type'                 => 'urn',
			'name'                 => 'Ceramic Urn - Blue',
			'description'          => '',
			'price'                => 450,
			'price_on_application' => false,
			'category'             => $ceramic,
			'sort_order'           => 10,
			'attributes'           => [],
			'images'               => [],
		],
		'U2' => [
			'master_id'            => hk_fs_test_mid( 12 ),
			'type'                 => 'urn',
			'name'                 => 'Brass Urn',
			'description'          => '',
			'price'                => 300,
			'price_on_application' => false,
			'category'             => null,
			'sort_order'           => 20,
			'attributes'           => [],
			'images'               => [],
		],
	];
}

/**
 * Delete everything a previous run left behind: fixture posts (and the
 * attachments they own), the stray and legacy posts, fixture terms and
 * the adoption terms, and the test secret.
 */
function hk_fs_test_cleanup(): void {
	$post_types = [ 'hk_fs_casket', 'hk_fs_urn' ];

	$posts   = get_posts( [
		'post_type'      => $post_types,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			[
				'key'     => '_hk_fs_master_id',
				'value'   => HK_FS_TEST_ID_PREFIX,
				'compare' => 'LIKE',
			],
			[
				'key'     => '_hkfs_test_fixture',
				'compare' => 'EXISTS',
			],
		],
	] );
	$trashed = get_posts( [
		'post_type'      => $post_types,
		'post_status'    => 'trash',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_hkfs_test_fixture', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	] );

	foreach ( array_unique( array_merge( $posts, $trashed ) ) as $post_id ) {
		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'post_parent'    => $post_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
		foreach ( $attachments as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
		wp_delete_post( $post_id, true );
	}

	foreach ( [ 'hk_fs_casket_category', 'hk_fs_urn_category' ] as $taxonomy ) {
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		] );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$stamp = (string) get_term_meta( $term->term_id, '_hk_fs_master_category_id', true );
			$test  = (string) get_term_meta( $term->term_id, '_hkfs_test_fixture', true );
			if ( str_starts_with( $stamp, HK_FS_TEST_ID_PREFIX ) || '' !== $test ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
		}
	}

	delete_option( 'hk_fs_catalogue_secret' );
	update_option( 'hk_fs_catalogue_managed_types', [] );
}

/**
 * Refuse to run where real catalogue data exists.
 *
 * @return string Empty when safe, otherwise the reason.
 */
function hk_fs_test_unsafe_reason(): string {
	if ( defined( 'HK_FS_CATALOGUE_SECRET' ) ) {
		return 'HK_FS_CATALOGUE_SECRET is defined: this looks like a provisioned site.';
	}

	global $wpdb;
	$foreign = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_hk_fs_master_id' AND meta_value <> '' AND meta_value NOT LIKE %s",
		$wpdb->esc_like( HK_FS_TEST_ID_PREFIX ) . '%'
	) );
	if ( $foreign ) {
		return "{$foreign} post(s) carry master IDs this harness did not create.";
	}

	$foreign_terms = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = '_hk_fs_master_category_id' AND meta_value <> '' AND meta_value NOT LIKE %s",
		$wpdb->esc_like( HK_FS_TEST_ID_PREFIX ) . '%'
	) );
	if ( $foreign_terms ) {
		return "{$foreign_terms} term(s) carry master category IDs this harness did not create.";
	}

	return '';
}

/**
 * Create a pre-existing (non-synced) product post, as a site would have
 * before adoption.
 *
 * @param string $post_type Post type.
 * @param string $title     Title.
 * @param string $image     Test image to set as its featured image, or ''.
 * @return int Post ID.
 */
function hk_fs_test_make_legacy_post( string $post_type, string $title, string $image = '' ): int {
	$post_id = (int) wp_insert_post( [
		'post_type'   => $post_type,
		'post_status' => 'publish',
		'post_title'  => $title,
	] );
	update_post_meta( $post_id, '_hkfs_test_fixture', '1' );

	if ( '' !== $image ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_sideload_image( hk_fs_test_image_url( $image ), $post_id, $title, 'id' );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, (int) $attachment_id );
		}
	}

	return $post_id;
}

// ─── Run ────────────────────────────────────────────────────────────────────

$hk_fs_image_dir = (string) ( $args[0] ?? '' );
$hk_fs_base_url  = untrailingslashit( (string) ( $args[1] ?? '' ) );

if ( '' === $hk_fs_image_dir || '' === $hk_fs_base_url || ! is_dir( $hk_fs_image_dir ) ) {
	\WP_CLI::error( 'Usage: wp eval-file test-catalogue-sync.php <image_dir> <image_base_url> (run.sh starts the image server for you).' );
}
if ( ! function_exists( 'HKFuneralSuite\\CatalogueSync\\rest_sync' ) ) {
	\WP_CLI::error( 'hk-funeral-suite is not active on this site.' );
}

$hk_fs_reason = hk_fs_test_unsafe_reason();
if ( '' !== $hk_fs_reason ) {
	\WP_CLI::error( 'Refusing to run: ' . $hk_fs_reason );
}

$hk_fs_state             = &hk_fs_test_state();
$hk_fs_state['base_url'] = $hk_fs_base_url;

// The sync runs as user 0 in production; so do these requests. WP-CLI
// removes the kses filters at init, but a real /sync request from the
// master has them (user 0 lacks unfiltered_html), so put them back.
wp_set_current_user( 0 );
kses_init_filters();

$hk_fs_admins            = get_users( [
	'role'   => 'administrator',
	'number' => 1,
	'fields' => 'ID',
] );
$hk_fs_state['admin_id'] = (int) ( $hk_fs_admins[0] ?? 0 );

// Let WordPress fetch images from the local test server (loopback and a
// non-standard port are refused by default).
$hk_fs_port = (int) wp_parse_url( $hk_fs_base_url, PHP_URL_PORT );
add_filter( 'http_request_host_is_external', static function ( $external, $host ) {
	return in_array( $host, [ '127.0.0.1', 'localhost' ], true ) ? true : $external;
}, 10, 2 );
add_filter( 'http_allowed_safe_ports', static function ( $ports ) use ( $hk_fs_port ) {
	$ports[] = $hk_fs_port;
	return $ports;
} );

// Counters: post updates on suite posts, HTTP requests, cache purges.
add_action( 'pre_post_update', static function ( $post_id ) {
	$state = &hk_fs_test_state();
	if ( in_array( get_post_type( (int) $post_id ), [ 'hk_fs_casket', 'hk_fs_urn' ], true ) ) {
		$state['updates'][] = (int) $post_id;
	}
} );
add_action( 'http_api_debug', static function ( $response, $context, $transport, $request_args, $url ) {
	$state                 = &hk_fs_test_state();
	$state['http'][ $url ] = ( $state['http'][ $url ] ?? 0 ) + 1;
}, 10, 5 );
add_action( 'hk_fs_cache_purged', static function ( $post_ids, $contexts ) {
	$state             = &hk_fs_test_state();
	$state['purges'][] = [ $post_ids, $contexts ];
}, 10, 2 );

// ── 0. Preflight ────────────────────────────────────────────────────────────

hk_fs_test_section( '0. Preflight' );

hk_fs_test_cleanup();
hk_fs_test_write_images( $hk_fs_image_dir );

hk_fs_test_check( 'casket type is enabled and registered', \HKFuneralSuite\CatalogueSync\is_type_available( 'casket' ) );
hk_fs_test_check( 'urn type is enabled and registered', \HKFuneralSuite\CatalogueSync\is_type_available( 'urn' ) );
hk_fs_test_check( 'requests run as user 0', 0 === get_current_user_id() );
hk_fs_test_check( 'an administrator exists for the settings checks', $hk_fs_state['admin_id'] > 0 );
hk_fs_test_check( 'local image server answers', ! is_wp_error( wp_safe_remote_get( hk_fs_test_image_url( 'img-1' ) ) ) && 200 === wp_remote_retrieve_response_code( wp_safe_remote_get( hk_fs_test_image_url( 'img-1' ) ) ) );

if ( $hk_fs_state['fail'] ) {
	\WP_CLI::error( 'Preflight failed; not running the tests.' );
}

// ── 1. Authentication ───────────────────────────────────────────────────────

hk_fs_test_section( '1. Authentication' );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state' );
hk_fs_test_check( 'no secret provisioned: 403 not_configured', 403 === $hk_fs_response->get_status() && 'hk_fs_catalogue_not_configured' === hk_fs_test_error_code( $hk_fs_response ), [ $hk_fs_response->get_status(), hk_fs_test_error_code( $hk_fs_response ) ] );

update_option( 'hk_fs_catalogue_secret', HK_FS_TEST_SECRET, false );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'unsigned' => true ] );
hk_fs_test_check( 'unsigned request: 401', 401 === $hk_fs_response->get_status() && 'hk_fs_catalogue_unauthorised' === hk_fs_test_error_code( $hk_fs_response ), [ $hk_fs_response->get_status(), hk_fs_test_error_code( $hk_fs_response ) ] );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'signature' => str_repeat( 'ab', 32 ) ] );
hk_fs_test_check( 'bad signature: 401', 401 === $hk_fs_response->get_status() && 'hk_fs_catalogue_bad_signature' === hk_fs_test_error_code( $hk_fs_response ), [ $hk_fs_response->get_status(), hk_fs_test_error_code( $hk_fs_response ) ] );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'timestamp' => time() - 301 ] );
hk_fs_test_check( 'stale timestamp (301 s old, correctly signed): 401', 401 === $hk_fs_response->get_status() && 'hk_fs_catalogue_stale_timestamp' === hk_fs_test_error_code( $hk_fs_response ), [ $hk_fs_response->get_status(), hk_fs_test_error_code( $hk_fs_response ) ] );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'timestamp' => time() + 301 ] );
hk_fs_test_check( 'future timestamp (301 s ahead): 401', 401 === $hk_fs_response->get_status() && 'hk_fs_catalogue_stale_timestamp' === hk_fs_test_error_code( $hk_fs_response ), [ $hk_fs_response->get_status(), hk_fs_test_error_code( $hk_fs_response ) ] );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'timestamp' => time() - 250 ] );
hk_fs_test_check( 'timestamp 250 s old is still accepted', 200 === $hk_fs_response->get_status(), $hk_fs_response->get_status() );

$hk_fs_fixtures = hk_fs_test_fixtures();
$hk_fs_response = hk_fs_test_request( 'POST', '/hk-fs-catalogue/v1/sync', [
	'schema_version' => 1,
	'products'       => [ $hk_fs_fixtures['C1'] ],
], [ 'sign_body' => '{"schema_version":1,"products":[]}' ] );
hk_fs_test_check( 'body changed after signing: 401 and nothing written', 401 === $hk_fs_response->get_status() && 0 === hk_fs_test_post_id_for( $hk_fs_fixtures['C1'] ), [ $hk_fs_response->get_status(), hk_fs_test_error_code( $hk_fs_response ) ] );

$hk_fs_response = hk_fs_test_request( 'POST', '/hk-fs-catalogue/v1/sync', [
	'schema_version' => 1,
	'products'       => [ $hk_fs_fixtures['C1'] ],
], [ 'unsigned' => true ] );
hk_fs_test_check( 'unsigned /sync: 401 and nothing written', 401 === $hk_fs_response->get_status() && 0 === hk_fs_test_post_id_for( $hk_fs_fixtures['C1'] ), $hk_fs_response->get_status() );

$hk_fs_response = hk_fs_test_request( 'POST', '/hk-fs-catalogue/v1/stamp', [ 'stamp' => [] ], [ 'unsigned' => true ] );
hk_fs_test_check( 'unsigned /stamp: 401', 401 === $hk_fs_response->get_status(), $hk_fs_response->get_status() );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'query' => [ 'types' => 'casket,urn' ] ] );
$hk_fs_data     = $hk_fs_response->get_data();
hk_fs_test_check( 'signed /state: 200 with schema_version 1', 200 === $hk_fs_response->get_status() && 1 === ( $hk_fs_data['schema_version'] ?? null ), $hk_fs_response->get_status() );
hk_fs_test_check( 'signed /state lists casket and urn as enabled', ! empty( $hk_fs_data['types']['casket']['enabled'] ) && ! empty( $hk_fs_data['types']['urn']['enabled'] ) );

// ── 2. Adoption: /stamp and pre-existing terms ──────────────────────────────

hk_fs_test_section( '2. Adoption (/stamp, pre-existing posts and terms)' );

$hk_fs_state['legacy']['urn']    = hk_fs_test_make_legacy_post( 'hk_fs_urn', 'Ceramic urn blue', 'legacy-1' );
$hk_fs_state['legacy']['casket'] = hk_fs_test_make_legacy_post( 'hk_fs_casket', 'Oak Heritage', 'legacy-2' );
$hk_fs_state['stray_id']         = hk_fs_test_make_legacy_post( 'hk_fs_casket', 'Stray casket (test)' );

$hk_fs_legacy_urn_thumb    = (int) get_post_thumbnail_id( $hk_fs_state['legacy']['urn'] );
$hk_fs_legacy_casket_thumb = (int) get_post_thumbnail_id( $hk_fs_state['legacy']['casket'] );
hk_fs_test_check( 'legacy posts have their own featured images', $hk_fs_legacy_urn_thumb > 0 && $hk_fs_legacy_casket_thumb > 0 );

$hk_fs_response = hk_fs_test_request( 'POST', '/hk-fs-catalogue/v1/stamp', [
	'stamp' => [
		[
			'post_id'   => $hk_fs_state['legacy']['urn'],
			'master_id' => $hk_fs_fixtures['U1']['master_id'],
		],
		[
			'post_id'   => $hk_fs_state['legacy']['casket'],
			'master_id' => strtoupper( $hk_fs_fixtures['C5']['master_id'] ),
		],
	],
] );
$hk_fs_data     = $hk_fs_response->get_data();
hk_fs_test_check( '/stamp stamps two existing posts', 200 === $hk_fs_response->get_status() && 2 === ( $hk_fs_data['counts']['stamped'] ?? null ), $hk_fs_data['counts'] ?? $hk_fs_data );
hk_fs_test_check( '/stamp lower-cases the master ID', $hk_fs_fixtures['C5']['master_id'] === get_post_meta( $hk_fs_state['legacy']['casket'], '_hk_fs_master_id', true ) );

$hk_fs_response = hk_fs_test_request( 'POST', '/hk-fs-catalogue/v1/stamp', [
	'stamp' => [
		[
			'post_id'   => $hk_fs_state['legacy']['urn'],
			'master_id' => $hk_fs_fixtures['U1']['master_id'],
		],
		[
			'post_id'   => $hk_fs_state['stray_id'],
			'master_id' => $hk_fs_fixtures['C5']['master_id'],
		],
		[
			'post_id'   => $hk_fs_state['stray_id'],
			'master_id' => 'not-a-uuid',
		],
	],
] );
$hk_fs_data     = $hk_fs_response->get_data();
$hk_fs_results  = $hk_fs_data['results'] ?? [];
hk_fs_test_check( '/stamp replay reports unchanged', 'unchanged' === ( $hk_fs_results[0]['result'] ?? '' ), $hk_fs_results[0] ?? null );
hk_fs_test_check( '/stamp refuses a second holder of a master ID', 'master_id_already_stamped' === ( $hk_fs_results[1]['error'] ?? '' ), $hk_fs_results[1] ?? null );
hk_fs_test_check( '/stamp refuses a non-UUID master ID', 'invalid_pair' === ( $hk_fs_results[2]['error'] ?? '' ), $hk_fs_results[2] ?? null );
hk_fs_test_check( 'stray stays unstamped', '' === get_post_meta( $hk_fs_state['stray_id'], '_hk_fs_master_id', true ) );

// Site terms the master categories should adopt rather than duplicate.
$hk_fs_solid  = wp_insert_term( 'solid wood range', 'hk_fs_casket_category', [ 'slug' => 'solid-wood' ] );
$hk_fs_veneer = wp_insert_term( 'Veneers', 'hk_fs_casket_category', [ 'slug' => 'veneer-wood-range' ] );
hk_fs_test_check( 'pre-existing terms created', ! is_wp_error( $hk_fs_solid ) && ! is_wp_error( $hk_fs_veneer ) );
$hk_fs_state['term_ids']['solid']  = is_wp_error( $hk_fs_solid ) ? 0 : (int) $hk_fs_solid['term_id'];
$hk_fs_state['term_ids']['veneer'] = is_wp_error( $hk_fs_veneer ) ? 0 : (int) $hk_fs_veneer['term_id'];
update_term_meta( $hk_fs_state['term_ids']['solid'], '_hkfs_test_fixture', '1' );
update_term_meta( $hk_fs_state['term_ids']['veneer'], '_hkfs_test_fixture', '1' );

$hk_fs_casket_terms_before = (int) wp_count_terms( [
	'taxonomy'   => 'hk_fs_casket_category',
	'hide_empty' => false,
] );

// ── 3. First publish, batched: limit 2, six images over five caskets ────────

hk_fs_test_section( '3. First publish with image batching (image_fetch_limit 2)' );

hk_fs_test_flush_purges();
$hk_fs_attachments_before = hk_fs_test_attachment_count();
$hk_fs_run                = 'a0000000-0000-4000-b000-000000000001';

$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, $hk_fs_run, 2 );
$hk_fs_c = $hk_fs_r['data']['counts'] ?? [];

hk_fs_test_check( 'call 1: HTTP 200', 200 === $hk_fs_r['status'], $hk_fs_r['data'] );
hk_fs_test_check( 'call 1: 5 created, 2 updated (adopted), 0 errors', 5 === ( $hk_fs_c['created'] ?? null ) && 2 === ( $hk_fs_c['updated'] ?? null ) && 0 === ( $hk_fs_c['errors'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'call 1: images_fetched 2, images_pending 4, image_errors 0', 2 === ( $hk_fs_c['images_fetched'] ?? null ) && 4 === ( $hk_fs_c['images_pending'] ?? null ) && 0 === ( $hk_fs_c['image_errors'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'call 1: complete is false', false === ( $hk_fs_r['data']['complete'] ?? null ), $hk_fs_r['data']['complete'] ?? null );
hk_fs_test_check( 'call 1: echoes sync_run_id and the effective limit', $hk_fs_run === ( $hk_fs_r['data']['sync_run_id'] ?? null ) && 2 === ( $hk_fs_r['data']['image_fetch_limit'] ?? null ) );
hk_fs_test_check( 'call 1: existing response fields still present', isset( $hk_fs_r['data']['schema_version'], $hk_fs_r['data']['plugin_version'], $hk_fs_r['data']['location_id'], $hk_fs_r['data']['strays'], $hk_fs_r['data']['duration_ms'], $hk_fs_c['unchanged'], $hk_fs_c['deactivated'] ) );

$hk_fs_c1 = hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C1']['master_id'] );
$hk_fs_c2 = hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C2']['master_id'] );
$hk_fs_c3 = hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C3']['master_id'] );
$hk_fs_c5 = hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C5']['master_id'] );
$hk_fs_u1 = hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['U1']['master_id'] );
hk_fs_test_check( 'call 1: C1 image_primary fetched, image_secondary none', 'fetched' === ( $hk_fs_c1['image_primary'] ?? '' ) && 'none' === ( $hk_fs_c1['image_secondary'] ?? '' ), $hk_fs_c1 );
hk_fs_test_check( 'call 1: C2 primary fetched, secondary pending', 'fetched' === ( $hk_fs_c2['image_primary'] ?? '' ) && 'pending' === ( $hk_fs_c2['image_secondary'] ?? '' ), $hk_fs_c2 );
hk_fs_test_check( 'call 1: C3 and C5 primary pending', 'pending' === ( $hk_fs_c3['image_primary'] ?? '' ) && 'pending' === ( $hk_fs_c5['image_primary'] ?? '' ) );
hk_fs_test_check( 'call 1: no image_error on clean products', ! isset( $hk_fs_c1['image_error'] ) && ! isset( $hk_fs_c2['image_error'] ) );
hk_fs_test_check( 'call 1: result entries keep the images array', isset( $hk_fs_c1['images'] ) && is_array( $hk_fs_c1['images'] ) );

// Data is reconciled in full even though most images are pending.
$hk_fs_all_data = true;
foreach ( $hk_fs_fixtures as $hk_fs_key => $hk_fs_product ) {
	$hk_fs_pid                             = hk_fs_test_post_id_for( $hk_fs_product );
	$hk_fs_state['test_ids'][ $hk_fs_key ] = $hk_fs_pid;
	$hk_fs_post                            = get_post( $hk_fs_pid );
	if ( ! $hk_fs_post || 'publish' !== $hk_fs_post->post_status || $hk_fs_product['sort_order'] !== (int) $hk_fs_post->menu_order ) {
		$hk_fs_all_data = false;
	}
}
hk_fs_test_check( 'call 1: every product exists, published, in master order', $hk_fs_all_data );
hk_fs_test_check( 'call 1: adopted posts kept their IDs', $hk_fs_state['test_ids']['U1'] === $hk_fs_state['legacy']['urn'] && $hk_fs_state['test_ids']['C5'] === $hk_fs_state['legacy']['casket'] );
hk_fs_test_check( 'call 1: adopted urn renamed to the master name', 'Ceramic Urn - Blue' === get_post( $hk_fs_state['test_ids']['U1'] )->post_title );

hk_fs_test_check( 'price stored as plain number (2499.5)', '2499.5' === get_post_meta( $hk_fs_state['test_ids']['C1'], '_hk_fs_casket_price', true ) );
hk_fs_test_check( 'whole-dollar price stored without decimals (3200)', '3200' === get_post_meta( $hk_fs_state['test_ids']['C2'], '_hk_fs_casket_price', true ) );
hk_fs_test_check( 'POA stored as the literal POA', 'POA' === get_post_meta( $hk_fs_state['test_ids']['C3'], '_hk_fs_casket_price', true ) );

hk_fs_test_check( 'pending product keeps its existing featured image', $hk_fs_legacy_casket_thumb === (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C5'] ) );
hk_fs_test_check( 'pending product has no thumbnail yet (new post)', 0 === (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C3'] ) );
hk_fs_test_check( 'fetched product has its featured image', (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C1'] ) > 0 );
hk_fs_test_check( 'adopted urn with no master image keeps its own image', 'none' === ( $hk_fs_u1['image_primary'] ?? '' ) && $hk_fs_legacy_urn_thumb === (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['U1'] ), $hk_fs_u1 );

// '&' in names: stored kses-encoded because the request runs as user 0.
hk_fs_test_check( 'stored title is kses-encoded as user 0 (so the replay check below is meaningful)', 'Harper &amp; Lowe - Red' === get_post( $hk_fs_state['test_ids']['C1'] )->post_title, get_post( $hk_fs_state['test_ids']['C1'] )->post_title );

// Categories: adopt case-insensitively by name and by slug, never duplicate.
$hk_fs_solid_term  = get_term( $hk_fs_state['term_ids']['solid'], 'hk_fs_casket_category' );
$hk_fs_veneer_term = get_term( $hk_fs_state['term_ids']['veneer'], 'hk_fs_casket_category' );
$hk_fs_c2_terms    = wp_get_object_terms( $hk_fs_state['test_ids']['C2'], 'hk_fs_casket_category', [ 'fields' => 'ids' ] );
$hk_fs_c3_terms    = wp_get_object_terms( $hk_fs_state['test_ids']['C3'], 'hk_fs_casket_category', [ 'fields' => 'ids' ] );
hk_fs_test_check( 'case-insensitive name match adopted ("solid wood range")', [ $hk_fs_state['term_ids']['solid'] ] === array_map( 'intval', $hk_fs_c2_terms ), $hk_fs_c2_terms );
hk_fs_test_check( 'adopted term stamped and renamed to the master name, custom slug kept', hk_fs_test_cid( 2 ) === get_term_meta( $hk_fs_state['term_ids']['solid'], '_hk_fs_master_category_id', true ) && 'Solid Wood Range' === $hk_fs_solid_term->name && 'solid-wood' === $hk_fs_solid_term->slug, [ $hk_fs_solid_term->name, $hk_fs_solid_term->slug ] );
hk_fs_test_check( 'slug match adopted ("Veneers" with slug veneer-wood-range)', [ $hk_fs_state['term_ids']['veneer'] ] === array_map( 'intval', $hk_fs_c3_terms ) && 'Veneer Wood Range' === $hk_fs_veneer_term->name, [ $hk_fs_c3_terms, $hk_fs_veneer_term->name ] );

$hk_fs_casket_terms_after = (int) wp_count_terms( [
	'taxonomy'   => 'hk_fs_casket_category',
	'hide_empty' => false,
] );
hk_fs_test_check( 'only one new casket term created ("Wood & Bamboo")', $hk_fs_casket_terms_before + 1 === $hk_fs_casket_terms_after, [ $hk_fs_casket_terms_before, $hk_fs_casket_terms_after ] );
$hk_fs_c1_terms = wp_get_object_terms( $hk_fs_state['test_ids']['C1'], 'hk_fs_casket_category' );
$hk_fs_c4_terms = wp_get_object_terms( $hk_fs_state['test_ids']['C4'], 'hk_fs_casket_category', [ 'fields' => 'ids' ] );
hk_fs_test_check( 'C1 and C4 share the "Wood & Bamboo" term', 1 === count( $hk_fs_c1_terms ) && [ (int) $hk_fs_c1_terms[0]->term_id ] === array_map( 'intval', $hk_fs_c4_terms ) );
hk_fs_test_check( 'term name stored entity-encoded', 'Wood &amp; Bamboo' === ( $hk_fs_c1_terms[0]->name ?? '' ), $hk_fs_c1_terms[0]->name ?? null );
$hk_fs_state['term_ids']['wood'] = (int) ( $hk_fs_c1_terms[0]->term_id ?? 0 );
hk_fs_test_check( 'C5 (no category) has no category term', [] === wp_get_object_terms( $hk_fs_state['test_ids']['C5'], 'hk_fs_casket_category', [ 'fields' => 'ids' ] ) );

// Descriptions.
$hk_fs_c1_content = get_post( $hk_fs_state['test_ids']['C1'] )->post_content;
$hk_fs_c1_blocks  = array_values( array_filter( parse_blocks( $hk_fs_c1_content ), static fn( $b ) => null !== $b['blockName'] ) );
hk_fs_test_check( 'HTML description: no paragraph wrapped around block elements', ! preg_match( '#<p>\s*<(ul|li|/ul)#i', $hk_fs_c1_content ), $hk_fs_c1_content );
hk_fs_test_check( 'HTML description: meta block then one core/html block', 2 === count( $hk_fs_c1_blocks ) && 'hk-funeral-suite/casket' === $hk_fs_c1_blocks[0]['blockName'] && 'core/html' === $hk_fs_c1_blocks[1]['blockName'], array_column( $hk_fs_c1_blocks, 'blockName' ) );
hk_fs_test_check( 'HTML description: list kept intact', str_contains( $hk_fs_c1_content, '<ul>' ) && str_contains( $hk_fs_c1_content, '<li>Brass handles</li>' ) && str_contains( $hk_fs_c1_content, '</ul>' ) );
hk_fs_test_check( 'HTML description: passed through wp_kses_post (script tag stripped)', ! str_contains( $hk_fs_c1_content, '<script' ) );
hk_fs_test_check( 'HTML description: renders through [hk_content]', str_contains( do_shortcode( '[hk_content post_id="' . $hk_fs_state['test_ids']['C1'] . '"]' ), '<li>Satin lining</li>' ) );

$hk_fs_c4_blocks = array_values( array_filter( parse_blocks( get_post( $hk_fs_state['test_ids']['C4'] )->post_content ), static fn( $b ) => null !== $b['blockName'] ) );
hk_fs_test_check( 'plain multi-line description: one paragraph block per line', 3 === count( $hk_fs_c4_blocks ) && 'core/paragraph' === $hk_fs_c4_blocks[1]['blockName'] && 'core/paragraph' === $hk_fs_c4_blocks[2]['blockName'], array_column( $hk_fs_c4_blocks, 'blockName' ) );

hk_fs_test_check( 'strays: unstamped casket reported', in_array( $hk_fs_state['stray_id'], array_column( $hk_fs_r['data']['strays'] ?? [], 'post_id' ), true ) );
hk_fs_test_check( 'strays: stray left published and untouched', 'publish' === get_post_status( $hk_fs_state['stray_id'] ) );

// One purge for the whole request, covering every changed product.
hk_fs_test_check( 'call 1: purge callback registered on shutdown exactly once', 1 === hk_fs_test_purge_callbacks_registered(), hk_fs_test_purge_callbacks_registered() );
$hk_fs_purges = hk_fs_test_flush_purges();
$hk_fs_purged = $hk_fs_purges[0][0] ?? [];
hk_fs_test_check( 'call 1: one purge for the request', 1 === count( $hk_fs_purges ), count( $hk_fs_purges ) );
hk_fs_test_check( 'call 1: purge covers all seven products', [] === array_diff( array_values( $hk_fs_state['test_ids'] ), $hk_fs_purged ), $hk_fs_purged );
hk_fs_test_check( 'purge does not run twice', [] === hk_fs_test_flush_purges() );

// Calls 2 and 3: same payload, same run ID, drain the pending images.
$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, $hk_fs_run, 2 );
$hk_fs_c = $hk_fs_r['data']['counts'] ?? [];
hk_fs_test_check( 'call 2: fetched 2, pending 2, complete false', 2 === ( $hk_fs_c['images_fetched'] ?? null ) && 2 === ( $hk_fs_c['images_pending'] ?? null ) && false === ( $hk_fs_r['data']['complete'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'call 2: nothing created, no post updates needed', 0 === ( $hk_fs_c['created'] ?? null ) && [] === $hk_fs_state['updates'], [ $hk_fs_c, $hk_fs_state['updates'] ] );
hk_fs_test_check( 'call 2: C2 secondary fetched, C2 now updated', 'fetched' === ( hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C2']['master_id'] )['image_secondary'] ?? '' ) && 'updated' === ( hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C2']['master_id'] )['result'] ?? '' ) );
hk_fs_test_check( 'call 2: C1 unchanged (image already there)', 'unchanged' === ( hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C1']['master_id'] )['result'] ?? '' ) && 'unchanged' === ( hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C1']['master_id'] )['image_primary'] ?? '' ) );
hk_fs_test_check( 'call 2: C5 still pending, still on its old image', 'pending' === ( hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C5']['master_id'] )['image_primary'] ?? '' ) && $hk_fs_legacy_casket_thumb === (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C5'] ) );

$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, $hk_fs_run, 2 );
$hk_fs_c = $hk_fs_r['data']['counts'] ?? [];
hk_fs_test_check( 'call 3: fetched 2, pending 0, complete true', 2 === ( $hk_fs_c['images_fetched'] ?? null ) && 0 === ( $hk_fs_c['images_pending'] ?? null ) && true === ( $hk_fs_r['data']['complete'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'call 3: C5 now on the master image', $hk_fs_legacy_casket_thumb !== (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C5'] ) && (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C5'] ) > 0 );
hk_fs_test_check( 'six images sideloaded in total', $hk_fs_attachments_before + 6 === hk_fs_test_attachment_count(), [ $hk_fs_attachments_before, hk_fs_test_attachment_count() ] );
hk_fs_test_check( 'secondary image stored as attachment meta', (int) get_post_meta( $hk_fs_state['test_ids']['C2'], '_hk_fs_image_id_2', true ) > 0 );
hk_fs_test_flush_purges();

// ── 4. Replay is a no-op, including "&" names ───────────────────────────────

hk_fs_test_section( '4. Replay of the identical payload' );

$hk_fs_revisions   = hk_fs_test_revision_counts( array_values( $hk_fs_state['test_ids'] ) );
$hk_fs_attachments = hk_fs_test_attachment_count();
$hk_fs_modified    = get_post( $hk_fs_state['test_ids']['C1'] )->post_modified_gmt;
$hk_fs_term_name   = get_term( $hk_fs_state['term_ids']['wood'] )->name;

$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, 'a0000000-0000-4000-b000-000000000002' );
$hk_fs_c = $hk_fs_r['data']['counts'] ?? [];
hk_fs_test_check( 'replay: all 7 unchanged, nothing created or updated', 7 === ( $hk_fs_c['unchanged'] ?? null ) && 0 === ( $hk_fs_c['created'] ?? null ) && 0 === ( $hk_fs_c['updated'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'replay: "Harper & Lowe - Red" reports unchanged', 'unchanged' === ( hk_fs_test_result_for( $hk_fs_r['data'], $hk_fs_fixtures['C1']['master_id'] )['result'] ?? '' ) );
hk_fs_test_check( 'replay: no wp_update_post call', [] === $hk_fs_state['updates'], $hk_fs_state['updates'] );
hk_fs_test_check( 'replay: no new revisions', hk_fs_test_revision_counts( array_values( $hk_fs_state['test_ids'] ) ) === $hk_fs_revisions );
hk_fs_test_check( 'replay: no new attachments', hk_fs_test_attachment_count() === $hk_fs_attachments );
hk_fs_test_check( 'replay: post_modified untouched', get_post( $hk_fs_state['test_ids']['C1'] )->post_modified_gmt === $hk_fs_modified );
hk_fs_test_check( 'replay: "Wood & Bamboo" term not rewritten', get_term( $hk_fs_state['term_ids']['wood'] )->name === $hk_fs_term_name );
hk_fs_test_check( 'replay: images all unchanged, complete true', 0 === ( $hk_fs_c['images_fetched'] ?? null ) && 0 === ( $hk_fs_c['images_pending'] ?? null ) && 0 === ( $hk_fs_c['image_errors'] ?? null ) && true === ( $hk_fs_r['data']['complete'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'replay: default image_fetch_limit is 8', 8 === ( $hk_fs_r['data']['image_fetch_limit'] ?? null ) );
hk_fs_test_check( 'replay: nothing queued for purge', [] === hk_fs_test_flush_purges() );

// Same payload sent by an administrator (no kses): still unchanged.
wp_set_current_user( $hk_fs_state['admin_id'] );
$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, 'a0000000-0000-4000-b000-000000000003' );
wp_set_current_user( 0 );
hk_fs_test_check( 'replay as an unfiltered_html user: still all unchanged', 7 === ( $hk_fs_r['data']['counts']['unchanged'] ?? null ) && [] === $hk_fs_state['updates'], [ $hk_fs_r['data']['counts'] ?? null, $hk_fs_state['updates'] ] );
hk_fs_test_flush_purges();

// image_fetch_limit handling.
add_filter( 'hk_fs_catalogue_image_fetch_limit', static fn() => 3 );
$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, 'a0000000-0000-4000-b000-000000000004' );
hk_fs_test_check( 'filter changes the default limit (3)', 3 === ( $hk_fs_r['data']['image_fetch_limit'] ?? null ) );
$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, 'a0000000-0000-4000-b000-000000000004', 5 );
hk_fs_test_check( 'payload limit wins over the filtered default (5)', 5 === ( $hk_fs_r['data']['image_fetch_limit'] ?? null ) );
remove_all_filters( 'hk_fs_catalogue_image_fetch_limit' );
$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, 'a0000000-0000-4000-b000-000000000004', 500 );
hk_fs_test_check( 'limit above 100 clamps to 100', 100 === ( $hk_fs_r['data']['image_fetch_limit'] ?? null ) );
$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, 'a0000000-0000-4000-b000-000000000004', 0 );
hk_fs_test_check( 'limit below 1 clamps to 1', 1 === ( $hk_fs_r['data']['image_fetch_limit'] ?? null ) );
$hk_fs_r = hk_fs_test_sync( $hk_fs_fixtures, 'a0000000-0000-4000-b000-000000000004', null, [ 'image_fetch_limit' => 'lots' ] );
hk_fs_test_check( 'non-numeric limit falls back to the default', 8 === ( $hk_fs_r['data']['image_fetch_limit'] ?? null ) );
hk_fs_test_flush_purges();

// ── 5. /state returns what the master sent ──────────────────────────────────

hk_fs_test_section( '5. /state export' );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'query' => [ 'types' => 'casket,urn' ] ] );
$hk_fs_data     = $hk_fs_response->get_data();
$hk_fs_exported = [];
foreach ( [ 'casket', 'urn' ] as $hk_fs_type ) {
	foreach ( $hk_fs_data['types'][ $hk_fs_type ]['products'] ?? [] as $hk_fs_product ) {
		$hk_fs_exported[ (int) $hk_fs_product['post_id'] ] = $hk_fs_product;
	}
}
$hk_fs_c1_export = $hk_fs_exported[ $hk_fs_state['test_ids']['C1'] ] ?? [];
hk_fs_test_check( '/state title decoded ("Harper & Lowe - Red")', 'Harper & Lowe - Red' === ( $hk_fs_c1_export['title'] ?? null ), $hk_fs_c1_export['title'] ?? null );
hk_fs_test_check( '/state term name decoded ("Wood & Bamboo")', 'Wood & Bamboo' === ( $hk_fs_c1_export['terms'][0]['name'] ?? null ), $hk_fs_c1_export['terms'][0]['name'] ?? null );
hk_fs_test_check( '/state carries master and category IDs', hk_fs_test_mid( 1 ) === ( $hk_fs_c1_export['master_id'] ?? null ) && hk_fs_test_cid( 1 ) === ( $hk_fs_c1_export['terms'][0]['master_id'] ?? null ) );
hk_fs_test_check( '/state reports image versions', 'v-img-1' === ( $hk_fs_c1_export['image_versions']['primary'] ?? null ) );
hk_fs_test_check( '/state urn category decoded', 'ceramic, fibreglass & metal' === ( $hk_fs_exported[ $hk_fs_state['test_ids']['U1'] ]['terms'][0]['name'] ?? null ) );
hk_fs_test_check( '/state lists the stray without a master ID', array_key_exists( $hk_fs_state['stray_id'], $hk_fs_exported ) && null === $hk_fs_exported[ $hk_fs_state['stray_id'] ]['master_id'] );
hk_fs_test_check( '/state locked_types empty', [] === ( $hk_fs_data['locked_types'] ?? null ) );

// ── 6. Cache purge on save_post ─────────────────────────────────────────────

hk_fs_test_section( '6. Cache purge on save_post, once per request' );

hk_fs_test_flush_purges();
wp_update_post( [
	'ID'         => $hk_fs_state['stray_id'],
	'post_title' => 'Stray casket (test, edited)',
] );
$hk_fs_purges = hk_fs_test_flush_purges();
hk_fs_test_check( 'editor-style save of a suite post queues a purge', 1 === count( $hk_fs_purges ) && in_array( $hk_fs_state['stray_id'], $hk_fs_purges[0][0] ?? [], true ) && in_array( 'save_post', $hk_fs_purges[0][1] ?? [], true ), $hk_fs_purges );

$hk_fs_plain_post = (int) wp_insert_post( [
	'post_type'   => 'post',
	'post_status' => 'draft',
	'post_title'  => 'hkfs test plain post',
] );
hk_fs_test_check( 'saving a non-suite post queues nothing', [] === hk_fs_test_flush_purges() );
wp_delete_post( $hk_fs_plain_post, true );

$hk_fs_changed                = $hk_fs_fixtures;
$hk_fs_changed['C2']['price'] = 3350.0;
$hk_fs_changed['C4']['name']  = 'Bamboo Eco Natural';
$hk_fs_r                      = hk_fs_test_sync( $hk_fs_changed, 'a0000000-0000-4000-b000-000000000005' );
hk_fs_test_check( 'sync with a price change and a rename: two updated', 2 === ( $hk_fs_r['data']['counts']['updated'] ?? null ), $hk_fs_r['data']['counts'] ?? null );
hk_fs_test_check( 'sync: purge callback still registered once', 1 === hk_fs_test_purge_callbacks_registered() );
$hk_fs_purges = hk_fs_test_flush_purges();
hk_fs_test_check( 'sync: one purge covering both products (save_post and sync merged)', 1 === count( $hk_fs_purges ) && [] === array_diff( [ $hk_fs_state['test_ids']['C2'], $hk_fs_state['test_ids']['C4'] ], $hk_fs_purges[0][0] ?? [] ), $hk_fs_purges );
hk_fs_test_check( 'sync: both save_post and catalogue-sync asked, still one purge', [] === array_diff( [ 'save_post', 'catalogue-sync' ], $hk_fs_purges[0][1] ?? [] ), $hk_fs_purges[0][1] ?? null );
hk_fs_test_check( 'price-only change stored (3350)', '3350' === get_post_meta( $hk_fs_state['test_ids']['C2'], '_hk_fs_casket_price', true ) );

// ── 7. Renames keep slugs ───────────────────────────────────────────────────

hk_fs_test_section( '7. Renames keep slugs' );

$hk_fs_slug_before               = get_post( $hk_fs_state['test_ids']['C2'] )->post_name;
$hk_fs_changed['C2']['name']     = 'Rimu Classic Deluxe';
$hk_fs_wood                      = [
	'master_id'  => hk_fs_test_cid( 1 ),
	'name'       => 'Wood & Bamboo Range',
	'sort_order' => 10,
];
$hk_fs_changed['C1']['category'] = $hk_fs_wood;
$hk_fs_changed['C4']['category'] = $hk_fs_wood;
$hk_fs_r                         = hk_fs_test_sync( $hk_fs_changed, 'a0000000-0000-4000-b000-000000000006' );

hk_fs_test_check( 'product rename: updated', 'updated' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 2 ) )['result'] ?? '' ) );
hk_fs_test_check( 'product rename: title changed, slug kept', 'Rimu Classic Deluxe' === get_post( $hk_fs_state['test_ids']['C2'] )->post_title && $hk_fs_slug_before === get_post( $hk_fs_state['test_ids']['C2'] )->post_name, [ get_post( $hk_fs_state['test_ids']['C2'] )->post_name ] );
$hk_fs_wood_term = get_term( $hk_fs_state['term_ids']['wood'] );
hk_fs_test_check( 'category rename: same term, new name, slug kept', 'Wood &amp; Bamboo Range' === $hk_fs_wood_term->name && 'wood-bamboo' === $hk_fs_wood_term->slug, [ $hk_fs_wood_term->name, $hk_fs_wood_term->slug ] );
hk_fs_test_check( 'category rename: products still on that term', [ $hk_fs_state['term_ids']['wood'] ] === array_map( 'intval', wp_get_object_terms( $hk_fs_state['test_ids']['C4'], 'hk_fs_casket_category', [ 'fields' => 'ids' ] ) ) );
$hk_fs_r = hk_fs_test_sync( $hk_fs_changed, 'a0000000-0000-4000-b000-000000000007' );
hk_fs_test_check( 'replay after renames: all unchanged', 7 === ( $hk_fs_r['data']['counts']['unchanged'] ?? null ) && [] === $hk_fs_state['updates'], $hk_fs_r['data']['counts'] ?? null );

$hk_fs_changed['C4']['price']                = null;
$hk_fs_changed['C4']['price_on_application'] = true;
$hk_fs_r                                     = hk_fs_test_sync( $hk_fs_changed, 'a0000000-0000-4000-b000-000000000008' );
hk_fs_test_check( 'switch to POA: updated, stored as POA', 'updated' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 4 ) )['result'] ?? '' ) && 'POA' === get_post_meta( $hk_fs_state['test_ids']['C4'], '_hk_fs_casket_price', true ) );
hk_fs_test_flush_purges();

// ── 8. Deactivate and reactivate ────────────────────────────────────────────

hk_fs_test_section( '8. Deactivate and reactivate' );

$hk_fs_without_c4 = $hk_fs_changed;
unset( $hk_fs_without_c4['C4'] );
$hk_fs_r     = hk_fs_test_sync( $hk_fs_without_c4, 'a0000000-0000-4000-b000-000000000009' );
$hk_fs_entry = hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 4 ), 'deactivated' );
hk_fs_test_check( 'absent product: deactivated', 1 === ( $hk_fs_r['data']['counts']['deactivated'] ?? null ) && [] !== $hk_fs_entry, $hk_fs_r['data']['counts'] ?? null );
hk_fs_test_check( 'deactivated product is a draft, same post', 'draft' === get_post_status( $hk_fs_state['test_ids']['C4'] ) );
hk_fs_test_check( 'deactivated entry carries image fields (none)', 'none' === ( $hk_fs_entry['image_primary'] ?? '' ) && 'none' === ( $hk_fs_entry['image_secondary'] ?? '' ), $hk_fs_entry );
hk_fs_test_check( 'deactivation keeps media', (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C4'] ) > 0 );

$hk_fs_r = hk_fs_test_sync( $hk_fs_without_c4, 'a0000000-0000-4000-b000-000000000010' );
hk_fs_test_check( 'replay while absent: nothing deactivated again', 0 === ( $hk_fs_r['data']['counts']['deactivated'] ?? null ) && 6 === ( $hk_fs_r['data']['counts']['unchanged'] ?? null ), $hk_fs_r['data']['counts'] ?? null );

$hk_fs_r = hk_fs_test_sync( $hk_fs_changed, 'a0000000-0000-4000-b000-000000000011' );
hk_fs_test_check( 'reappears in payload: updated (reactivated)', 'updated' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 4 ) )['result'] ?? '' ) );
hk_fs_test_check( 'reactivated product published again, same post ID', 'publish' === get_post_status( $hk_fs_state['test_ids']['C4'] ) && hk_fs_test_post_id_for( $hk_fs_changed['C4'] ) === $hk_fs_state['test_ids']['C4'] );
hk_fs_test_check( 'stray still untouched after deactivation passes', 'publish' === get_post_status( $hk_fs_state['stray_id'] ) );
hk_fs_test_flush_purges();

// ── 9. Image errors ─────────────────────────────────────────────────────────

hk_fs_test_section( '9. Image errors and the per-run failure memory' );

$hk_fs_missing                 = hk_fs_test_image_url( 'missing-urn' );
$hk_fs_errored                 = $hk_fs_changed;
$hk_fs_errored['U2']['price']  = 320;
$hk_fs_errored['U2']['images'] = [
	[
		'role'    => 'primary',
		'version' => 'v-missing',
		'url'     => $hk_fs_missing,
	],
];
$hk_fs_run_a                   = 'a0000000-0000-4000-b000-000000000012';

$hk_fs_state['http'] = [];
$hk_fs_r             = hk_fs_test_sync( $hk_fs_errored, $hk_fs_run_a );
$hk_fs_u2            = hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 12 ) );
$hk_fs_c             = $hk_fs_r['data']['counts'] ?? [];
hk_fs_test_check( '404 image: product still updated with new data', 'updated' === ( $hk_fs_u2['result'] ?? '' ) && '320' === get_post_meta( $hk_fs_state['test_ids']['U2'], '_hk_fs_urn_price', true ), $hk_fs_u2 );
hk_fs_test_check( '404 image: image_primary error with image_error message', 'error' === ( $hk_fs_u2['image_primary'] ?? '' ) && str_starts_with( (string) ( $hk_fs_u2['image_error'] ?? '' ), 'primary: ' ), $hk_fs_u2 );
hk_fs_test_check( '404 image: counts.image_errors 1, product errors 0', 1 === ( $hk_fs_c['image_errors'] ?? null ) && 0 === ( $hk_fs_c['errors'] ?? null ), $hk_fs_c );
hk_fs_test_check( '404 image: nothing pending, complete true', 0 === ( $hk_fs_c['images_pending'] ?? null ) && true === ( $hk_fs_r['data']['complete'] ?? null ) );
hk_fs_test_check( '404 image: one download attempt', 1 === hk_fs_test_http_count( $hk_fs_missing ), hk_fs_test_http_count( $hk_fs_missing ) );
hk_fs_test_check( '404 image: no version written (retried on a later run)', '' === get_post_meta( $hk_fs_state['test_ids']['U2'], '_hk_fs_image_version', true ) );

$hk_fs_r = hk_fs_test_sync( $hk_fs_errored, $hk_fs_run_a );
$hk_fs_c = $hk_fs_r['data']['counts'] ?? [];
hk_fs_test_check( 'same run replayed: still reported as an error', 'error' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 12 ) )['image_primary'] ?? '' ) && 1 === ( $hk_fs_c['image_errors'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'same run replayed: not downloaded again', 1 === hk_fs_test_http_count( $hk_fs_missing ), hk_fs_test_http_count( $hk_fs_missing ) );
hk_fs_test_check( 'same run replayed: product unchanged', 'unchanged' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 12 ) )['result'] ?? '' ) );

$hk_fs_r = hk_fs_test_sync( $hk_fs_errored, 'a0000000-0000-4000-b000-000000000013' );
hk_fs_test_check( 'new run: the failed image is tried again', 2 === hk_fs_test_http_count( $hk_fs_missing ) && 1 === ( $hk_fs_r['data']['counts']['image_errors'] ?? null ), hk_fs_test_http_count( $hk_fs_missing ) );

// A broken image ahead of a good one, limit 1: the failure must not
// block the queue across replays of the same run.
$hk_fs_blocked                 = $hk_fs_errored;
$hk_fs_blocked['C4']['images'] = [
	[
		'role'    => 'primary',
		'version' => 'v-img-6',
		'url'     => hk_fs_test_image_url( 'img-6' ),
	],
];
$hk_fs_blocked                 = array_merge( [ 'U2' => $hk_fs_blocked['U2'] ], $hk_fs_blocked );
$hk_fs_run_b                   = 'a0000000-0000-4000-b000-000000000014';

$hk_fs_r = hk_fs_test_sync( $hk_fs_blocked, $hk_fs_run_b, 1 );
$hk_fs_c = $hk_fs_r['data']['counts'] ?? [];
hk_fs_test_check( 'limit 1, broken image first: error 1, pending 1, not complete', 1 === ( $hk_fs_c['image_errors'] ?? null ) && 1 === ( $hk_fs_c['images_pending'] ?? null ) && 0 === ( $hk_fs_c['images_fetched'] ?? null ) && false === ( $hk_fs_r['data']['complete'] ?? null ), $hk_fs_c );
$hk_fs_r = hk_fs_test_sync( $hk_fs_blocked, $hk_fs_run_b, 1 );
$hk_fs_c = $hk_fs_r['data']['counts'] ?? [];
hk_fs_test_check( 'replay of that run: good image fetched, pending 0, complete', 1 === ( $hk_fs_c['images_fetched'] ?? null ) && 0 === ( $hk_fs_c['images_pending'] ?? null ) && 1 === ( $hk_fs_c['image_errors'] ?? null ) && true === ( $hk_fs_r['data']['complete'] ?? null ), $hk_fs_c );
hk_fs_test_check( 'replaced image: C4 now on the new version', 'v-img-6' === get_post_meta( $hk_fs_state['test_ids']['C4'], '_hk_fs_image_version', true ) );

$hk_fs_no_url                 = $hk_fs_blocked;
$hk_fs_no_url['U2']['images'] = [
	[
		'role'    => 'primary',
		'version' => 'v-x',
		'url'     => '',
	],
];
$hk_fs_r                      = hk_fs_test_sync( $hk_fs_no_url, 'a0000000-0000-4000-b000-000000000015' );
hk_fs_test_check( 'image with no URL: error missing_url, counted', 'primary: missing_url' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 12 ) )['image_error'] ?? '' ) && 1 === ( $hk_fs_r['data']['counts']['image_errors'] ?? null ), hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 12 ) ) );

// Image without a version: fetched once, then unchanged.
$hk_fs_unversioned                 = $hk_fs_blocked;
$hk_fs_unversioned['U2']['images'] = [
	[
		'role'    => 'primary',
		'version' => null,
		'url'     => hk_fs_test_image_url( 'img-6' ),
	],
];
$hk_fs_r                           = hk_fs_test_sync( $hk_fs_unversioned, 'a0000000-0000-4000-b000-000000000016' );
hk_fs_test_check( 'image with no version: fetched', 'fetched' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 12 ) )['image_primary'] ?? '' ) );
$hk_fs_r = hk_fs_test_sync( $hk_fs_unversioned, 'a0000000-0000-4000-b000-000000000017' );
hk_fs_test_check( 'image with no version: unchanged on replay (not downloaded again)', 'unchanged' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 12 ) )['image_primary'] ?? '' ) && 0 === ( $hk_fs_r['data']['counts']['images_fetched'] ?? null ) );

// Ownership rule: the sync removes only images it set.
$hk_fs_removed                 = $hk_fs_blocked;
$hk_fs_removed['C5']['images'] = [];
$hk_fs_removed['U2']['images'] = [];
$hk_fs_r                       = hk_fs_test_sync( $hk_fs_removed, 'a0000000-0000-4000-b000-000000000018' );
hk_fs_test_check( 'master drops a sync-set image: removed, status none', 'none' === ( hk_fs_test_result_for( $hk_fs_r['data'], hk_fs_test_mid( 5 ) )['image_primary'] ?? '' ) && 0 === (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['C5'] ) );
hk_fs_test_check( 'adopted urn image (never sync-set) still kept', $hk_fs_legacy_urn_thumb === (int) get_post_thumbnail_id( $hk_fs_state['test_ids']['U1'] ) );
hk_fs_test_flush_purges();

// ── 10. Editor lock is read-only over /wp/v2/settings ───────────────────────

hk_fs_test_section( '10. Editor lock via /wp/v2/settings' );

update_option( 'hk_fs_catalogue_managed_types', [ 'casket' ] );
hk_fs_test_check( 'update_option (provisioning path) still sets the lock', [ 'casket' ] === \HKFuneralSuite\CatalogueSync\get_locked_types() );

wp_set_current_user( $hk_fs_state['admin_id'] );
$hk_fs_settings = new \WP_REST_Request( 'POST', '/wp/v2/settings' );
$hk_fs_settings->set_header( 'Content-Type', 'application/json' );
$hk_fs_settings->set_body( (string) wp_json_encode( [ 'hk_fs_catalogue_managed_types' => [] ] ) );
$hk_fs_response = rest_do_request( $hk_fs_settings );
$hk_fs_data     = $hk_fs_response->get_data();
hk_fs_test_check( 'admin POST to unlock: request accepted but lock unchanged', 200 === $hk_fs_response->get_status() && [ 'casket' ] === get_option( 'hk_fs_catalogue_managed_types' ), [ $hk_fs_response->get_status(), get_option( 'hk_fs_catalogue_managed_types' ) ] );
hk_fs_test_check( 'admin POST response reports the real lock', [ 'casket' ] === ( $hk_fs_data['hk_fs_catalogue_managed_types'] ?? null ), $hk_fs_data['hk_fs_catalogue_managed_types'] ?? null );

$hk_fs_old_description = get_option( 'blogdescription' );
$hk_fs_settings        = new \WP_REST_Request( 'POST', '/wp/v2/settings' );
$hk_fs_settings->set_header( 'Content-Type', 'application/json' );
$hk_fs_settings->set_body( (string) wp_json_encode( [
	'description'                   => 'hkfs settings check',
	'hk_fs_catalogue_managed_types' => [ 'casket', 'urn' ],
] ) );
rest_do_request( $hk_fs_settings );
hk_fs_test_check( 'other settings in the same request still save', 'hkfs settings check' === get_option( 'blogdescription' ) && [ 'casket' ] === get_option( 'hk_fs_catalogue_managed_types' ) );
update_option( 'blogdescription', $hk_fs_old_description );

$hk_fs_response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/settings' ) );
hk_fs_test_check( 'admin GET still reads the lock', [ 'casket' ] === ( $hk_fs_response->get_data()['hk_fs_catalogue_managed_types'] ?? null ) );
$hk_fs_response = rest_do_request( new \WP_REST_Request( 'OPTIONS', '/wp/v2/settings' ) );
hk_fs_test_check( 'settings schema marks the lock readonly', true === ( $hk_fs_response->get_data()['schema']['properties']['hk_fs_catalogue_managed_types']['readonly'] ?? null ) );
wp_set_current_user( 0 );

$hk_fs_response = hk_fs_test_request( 'GET', '/hk-fs-catalogue/v1/state', null, [ 'query' => [ 'types' => 'casket' ] ] );
hk_fs_test_check( '/state reports locked_types ["casket"]', [ 'casket' ] === ( $hk_fs_response->get_data()['locked_types'] ?? null ) );
update_option( 'hk_fs_catalogue_managed_types', [] );

// ── 11. Uninstall ───────────────────────────────────────────────────────────

hk_fs_test_section( '11. uninstall.php removes catalogue options, keeps post data' );

global $wpdb;
$hk_fs_snapshot = [];
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'hk\\_fs\\_%' AND option_name NOT LIKE 'hk\\_fs\\_catalogue\\_%'" ) as $hk_fs_option ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$hk_fs_snapshot[ $hk_fs_option ] = get_option( $hk_fs_option );
}

update_option( 'hk_fs_catalogue_managed_types', [ 'casket', 'urn' ] );
update_option( 'hk_fs_catalogue_some_future_option', 'x' );
$hk_fs_failure_key = \HKFuneralSuite\CatalogueSync\image_failures_key( $hk_fs_run_b );
hk_fs_test_check( 'before uninstall: secret, lock and a failure transient exist', HK_FS_TEST_SECRET === get_option( 'hk_fs_catalogue_secret' ) && false !== get_transient( $hk_fs_failure_key ) );

$hk_fs_master_meta_before = get_post_meta( $hk_fs_state['test_ids']['C1'], '_hk_fs_master_id', true );
$hk_fs_term_meta_before   = get_term_meta( $hk_fs_state['term_ids']['wood'], '_hk_fs_master_category_id', true );
$hk_fs_posts_before       = count( array_filter( $hk_fs_state['test_ids'], static fn( $id ) => (bool) get_post( $id ) ) );
$hk_fs_attachments        = hk_fs_test_attachment_count();

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'hk-funeral-suite/hk-funeral-suite.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core constant uninstall.php checks for.
}
require HK_FS_PLUGIN_DIR . 'uninstall.php';
wp_cache_flush();

hk_fs_test_check( 'lock option removed', 'missing' === get_option( 'hk_fs_catalogue_managed_types', 'missing' ) );
hk_fs_test_check( 'option-fallback secret removed', 'missing' === get_option( 'hk_fs_catalogue_secret', 'missing' ) );
hk_fs_test_check( 'other hk_fs_catalogue_* options removed', 'missing' === get_option( 'hk_fs_catalogue_some_future_option', 'missing' ) );
hk_fs_test_check( 'image failure transient removed', false === get_transient( $hk_fs_failure_key ) );
hk_fs_test_check( 'synced posts kept', count( $hk_fs_state['test_ids'] ) === $hk_fs_posts_before && $hk_fs_posts_before === count( array_filter( $hk_fs_state['test_ids'], static fn( $id ) => (bool) get_post( $id ) ) ) );
hk_fs_test_check( 'post master IDs kept', $hk_fs_master_meta_before === get_post_meta( $hk_fs_state['test_ids']['C1'], '_hk_fs_master_id', true ) );
hk_fs_test_check( 'term master IDs kept', $hk_fs_term_meta_before === get_term_meta( $hk_fs_state['term_ids']['wood'], '_hk_fs_master_category_id', true ) );
hk_fs_test_check( 'media kept', hk_fs_test_attachment_count() === $hk_fs_attachments );

// Put the site back as it was so the suite can run again.
foreach ( $hk_fs_snapshot as $hk_fs_option => $hk_fs_value ) {
	update_option( $hk_fs_option, $hk_fs_value );
}

// ── Done ────────────────────────────────────────────────────────────────────

if ( getenv( 'HKFS_TEST_KEEP' ) ) {
	\WP_CLI::log( '' );
	\WP_CLI::log( 'HKFS_TEST_KEEP set: test products left in place.' );
	update_option( 'hk_fs_catalogue_secret', HK_FS_TEST_SECRET, false );
} else {
	hk_fs_test_cleanup();
	delete_option( 'hk_fs_catalogue_managed_types' );
}

\WP_CLI::log( '' );
\WP_CLI::log( sprintf( '%d passed, %d failed.', $hk_fs_state['pass'], $hk_fs_state['fail'] ) );

if ( $hk_fs_state['fail'] ) {
	foreach ( $hk_fs_state['failures'] as $hk_fs_failure ) {
		\WP_CLI::log( '  - ' . $hk_fs_failure );
	}
	\WP_CLI::halt( 1 );
}
