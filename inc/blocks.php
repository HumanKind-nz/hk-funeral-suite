<?php
/**
 * Block Registration
 *
 * Auto-discovers and registers all blocks from the build/blocks/ directory.
 * Skips blocks for disabled CPTs. Also enqueues the Google Sheets lock
 * data so block edit components can disable price fields when needed.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\Blocks;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_blocks' );
	add_action( 'init', __NAMESPACE__ . '\\register_block_category' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_block_data' );
}

/**
 * Block name → CPT settings key mapping.
 *
 * Used to skip blocks when their CPT is disabled.
 *
 * @return array<string, string>
 */
function get_block_cpt_map(): array {
	return [
		'staff-meta'    => 'staff',
		'casket-meta'   => 'caskets',
		'urn-meta'      => 'urns',
		'package-meta'  => 'packages',
		'monument-meta' => 'monuments',
		'keepsake-meta' => 'keepsakes',
	];
}

/**
 * Register all blocks found in the build directory.
 *
 * Each subdirectory of build/blocks/ containing a block.json is registered
 * automatically. Blocks are skipped if their CPT is disabled in settings.
 */
function register_blocks(): void {
	$blocks_dir = HK_FS_PLUGIN_DIR . 'build/blocks/';

	if ( ! is_dir( $blocks_dir ) ) {
		return;
	}

	$settings    = \HK_Funeral_Settings::get_instance();
	$block_map   = get_block_cpt_map();
	$skip_blocks = [];

	foreach ( $block_map as $block_dir => $cpt_key ) {
		if ( ! $settings->is_cpt_enabled( $cpt_key ) ) {
			$skip_blocks[] = $block_dir;
		}
	}

	$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

	if ( ! $block_folders ) {
		return;
	}

	foreach ( $block_folders as $block_folder ) {
		$block_name = basename( $block_folder );

		if ( in_array( $block_name, $skip_blocks, true ) ) {
			continue;
		}

		register_block_type( $block_folder );
	}
}

/**
 * Register the HK Funeral Suite block category.
 */
function register_block_category(): void {
	add_filter( 'block_categories_all', function ( array $categories ): array {
		return array_merge(
			$categories,
			[
				[
					'slug'  => 'hk-funeral-suite',
					'title' => __( 'HK Funeral Suite', 'hk-funeral-suite' ),
					'icon'  => 'admin-site',
				],
			]
		);
	} );
}

/**
 * Enqueue block data for the editor.
 *
 * Passes Google Sheets lock status so edit components can disable price fields.
 */
function enqueue_block_data(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$post_type = $screen->post_type;
	$type      = str_replace( 'hk_fs_', '', $post_type );

	$sheets_map = \HKFuneralSuite\GoogleSheets\get_sheets_cpt_map();

	// Only enqueue for our CPTs.
	if ( ! in_array( $type, $sheets_map, true ) ) {
		return;
	}

	$is_managed = \HKFuneralSuite\GoogleSheets\is_managed_by_sheets( $type );

	wp_add_inline_script(
		'wp-blocks',
		'window.hkFsBlockData = ' . wp_json_encode( [
			'isSheetsManaged' => $is_managed,
		] ) . ';',
		'before'
	);
}
