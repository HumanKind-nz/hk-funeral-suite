<?php
/**
 * Plugin Name: HumanKind Funeral Suite
 * Plugin URI: https://github.com/HumanKind-nz/hk-funeral-suite/
 * Description: Custom post types, taxonomies, fields and specialised Gutenberg blocks for funeral home websites.
 * Version: 2.0.0-alpha.1
 * Author: HumanKind, Weave Digital Studio, Gareth Bissland
 * Author URI: https://weave.co.nz
 * License: GPL v2.0 or later
 * Text Domain: hk-funeral-suite
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Tested up to: 6.7
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

defined( 'WPINC' ) || exit;

// ─── Constants ──────────────────────────────────────────────────────────────

define( 'HK_FS_VERSION', '2.0.0-alpha.1' );
define( 'HK_FS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HK_FS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HK_FS_BASENAME', plugin_basename( __FILE__ ) );
define( 'HK_FS_PLUGIN_FILE', __FILE__ );

// ─── Load modules ───────────────────────────────────────────────────────────

require_once HK_FS_PLUGIN_DIR . 'inc/post-types.php';
require_once HK_FS_PLUGIN_DIR . 'inc/google-sheets.php';
require_once HK_FS_PLUGIN_DIR . 'inc/block-editor.php';
require_once HK_FS_PLUGIN_DIR . 'inc/admin-columns.php';
require_once HK_FS_PLUGIN_DIR . 'inc/hooks.php';
require_once HK_FS_PLUGIN_DIR . 'inc/shortcodes.php';
require_once HK_FS_PLUGIN_DIR . 'inc/github-updater.php';
require_once HK_FS_PLUGIN_DIR . 'inc/import.php';
require_once HK_FS_PLUGIN_DIR . 'inc/blocks.php';
require_once HK_FS_PLUGIN_DIR . 'inc/settings-page.php';

// ─── Bootstrap modules ─────────────────────────────────────────────────────

HKFuneralSuite\PostTypes\bootstrap();
HKFuneralSuite\GoogleSheets\bootstrap();
HKFuneralSuite\BlockEditor\bootstrap();
HKFuneralSuite\Blocks\bootstrap();
HKFuneralSuite\AdminColumns\bootstrap();
HKFuneralSuite\Hooks\bootstrap();
HKFuneralSuite\Shortcodes\bootstrap();
HKFuneralSuite\GitHubUpdater\bootstrap();
HKFuneralSuite\Import\bootstrap();

// Settings page — class-based, initialised via hooks.
add_action( 'plugins_loaded', [ 'HK_Funeral_Settings', 'init' ], 5 );

// ─── Textdomain ─────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', function (): void {
	load_plugin_textdomain( 'hk-funeral-suite', false, dirname( HK_FS_BASENAME ) . '/languages' );
} );

// ─── Uninstall ──────────────────────────────────────────────────────────────

register_uninstall_hook( __FILE__, 'hk_fs_cleanup_plugin' );

/**
 * Clean up plugin options on uninstall.
 */
function hk_fs_cleanup_plugin(): void {
	delete_option( 'hk_fs_enabled_cpts' );
	delete_option( 'hk_fs_caps_migrated_v2' );
	delete_option( 'hk_fs_flush_rewrite_rules' );
	delete_option( 'hk_fs_happyfiles_compatibility' );
	delete_option( 'hk_fs_seopress_metabox_compatibility' );
	delete_option( 'hk_fs_generatepress_compatibility' );
	delete_option( 'hk_fs_wpbf_compatibility' );
	delete_option( 'hk_fs_enable_public_staff' );
	delete_option( 'hk_fs_enable_public_caskets' );
	delete_option( 'hk_fs_enable_public_urns' );
	delete_option( 'hk_fs_enable_public_packages' );
	delete_option( 'hk_fs_enable_public_monuments' );
	delete_option( 'hk_fs_enable_public_keepsakes' );
	delete_transient( 'hk_funeral_github_response' );
}

// ─── Backward-compat shim ────────────────────────────────────────────────────

/**
 * Legacy cache purge function.
 *
 * Old block init.php save handlers may still fire for existing posts
 * that contain the legacy block markup. This shim ensures cache
 * purging continues to work during the transition.
 *
 * @param int|null $post_id Post ID.
 * @param string   $context Context string.
 */
function hk_fs_optimized_cache_purge( ?int $post_id = null, string $context = 'unknown' ): void {
	HKFuneralSuite\Hooks\optimised_cache_purge( $post_id, $context );
}
