<?php
/**
 * Router for the test image server (php -S), started by run.sh.
 *
 * Serves the test images from the document root as normal. A request for
 * /slow/<seconds>/<file>.png waits that many seconds before sending the
 * file, standing in for a slow or stalled image host.
 *
 * Only runs under PHP's built-in web server.
 *
 * @package HKFuneralSuite
 */

if ( 'cli-server' !== PHP_SAPI ) {
	exit;
}

$hk_fs_path = (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url, WordPress.Security.ValidatedSanitizedInput -- Test server, no WordPress loaded.

if ( ! preg_match( '#^/slow/([0-9]+(?:\.[0-9]+)?)/([a-z0-9-]+\.png)$#', $hk_fs_path, $hk_fs_match ) ) {
	return false; // Serve the file as it is.
}

usleep( (int) round( min( 120.0, (float) $hk_fs_match[1] ) * 1000000 ) );

$hk_fs_file = rtrim( (string) $_SERVER['DOCUMENT_ROOT'], '/' ) . '/' . $hk_fs_match[2]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Test server, no WordPress loaded.
if ( ! is_file( $hk_fs_file ) ) {
	http_response_code( 404 );
	return true;
}

header( 'Content-Type: image/png' );
header( 'Content-Length: ' . filesize( $hk_fs_file ) );
readfile( $hk_fs_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Test server, no WordPress loaded.
return true;
