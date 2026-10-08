<?php
/**
 * Block Registration
 *
 * Auto-discovers and registers all blocks from the build/blocks/ directory.
 * Skips blocks for disabled CPTs. Dynamic blocks get their PHP render
 * callback from get_block_render_callbacks(). Also registers the plugin's
 * block patterns, and enqueues the Google Sheets lock data so block edit
 * components can disable price fields when needed.
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
	add_action( 'init', __NAMESPACE__ . '\\register_patterns', 20 );
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
 * Block name → PHP render callback, for dynamic blocks.
 *
 * Passed to register_block_type() rather than a render.php, so the block
 * and its shortcode share one renderer.
 *
 * @return array<string, callable-string>
 */
function get_block_render_callbacks(): array {
	return [
		'product-grid' => '\\HKFuneralSuite\\ProductGrid\\render_block',
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

	// The product grid needs at least one product type.
	if ( ! \HKFuneralSuite\Display\get_enabled_product_types() ) {
		$skip_blocks[] = 'product-grid';
	}

	$render_callbacks = get_block_render_callbacks();

	$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

	if ( ! $block_folders ) {
		return;
	}

	foreach ( $block_folders as $block_folder ) {
		$block_name = basename( $block_folder );

		if ( in_array( $block_name, $skip_blocks, true ) ) {
			continue;
		}

		$args = [];
		if ( isset( $render_callbacks[ $block_name ] ) ) {
			$args['render_callback'] = $render_callbacks[ $block_name ];
		}

		register_block_type( $block_folder, $args );
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
 * Register the plugin's block patterns, one set per enabled product type:
 * a product grid block, and Query Loop cards. Themes copy and restyle them.
 */
function register_patterns(): void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern_category( 'hk-funeral-suite', [
		'label'       => __( 'HK Funeral Suite', 'hk-funeral-suite' ),
		'description' => __( 'Product grids and cards from HK Funeral Suite.', 'hk-funeral-suite' ),
	] );

	$labels = [
		'casket'   => [ __( 'Casket grid', 'hk-funeral-suite' ), __( 'Casket cards (Query Loop)', 'hk-funeral-suite' ), __( 'Our caskets', 'hk-funeral-suite' ) ],
		'urn'      => [ __( 'Urn grid', 'hk-funeral-suite' ), __( 'Urn cards (Query Loop)', 'hk-funeral-suite' ), __( 'Our urns', 'hk-funeral-suite' ) ],
		'monument' => [ __( 'Monument grid', 'hk-funeral-suite' ), __( 'Monument cards (Query Loop)', 'hk-funeral-suite' ), __( 'Our monuments', 'hk-funeral-suite' ) ],
		'keepsake' => [ __( 'Keepsake grid', 'hk-funeral-suite' ), __( 'Keepsake cards (Query Loop)', 'hk-funeral-suite' ), __( 'Our keepsakes', 'hk-funeral-suite' ) ],
	];

	$grid_registered = \WP_Block_Type_Registry::get_instance()->is_registered( 'hk-funeral-suite/product-grid' );
	$types           = \HKFuneralSuite\Display\get_product_types();

	foreach ( \HKFuneralSuite\Display\get_enabled_product_types() as $type ) {
		$pattern = [
			'type'      => $type,
			'post_type' => $types[ $type ]['post_type'],
			'heading'   => $labels[ $type ][2],
			'namespace' => 'hk-funeral-suite/' . $type . 's',
		];

		if ( $grid_registered ) {
			register_block_pattern( "hk-funeral-suite/{$type}-grid", [
				'title'       => $labels[ $type ][0],
				'description' => __( 'A heading and the product grid block: grouped by category, in catalogue order.', 'hk-funeral-suite' ),
				'categories'  => [ 'hk-funeral-suite' ],
				'keywords'    => [ $type, 'grid', 'products' ],
				'content'     => get_pattern_content( 'product-grid.php', $pattern ),
			] );
		}

		register_block_pattern( "hk-funeral-suite/{$type}-cards-query", [
			'title'       => $labels[ $type ][1],
			'description' => __( 'A Query Loop of core blocks with the price bound to the HK price source. Restyle freely.', 'hk-funeral-suite' ),
			'categories'  => [ 'hk-funeral-suite' ],
			'keywords'    => [ $type, 'query', 'cards' ],
			'blockTypes'  => [ 'core/query' ],
			'content'     => get_pattern_content( 'product-cards-query.php', $pattern ),
		] );
	}
}

/**
 * Render a pattern file from patterns/ to markup.
 *
 * @param string $file    File name in patterns/.
 * @param array  $pattern Values the file reads: type, post_type, heading, namespace.
 * @return string
 */
function get_pattern_content( string $file, array $pattern ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $pattern is read by the included file.
	$path = HK_FS_PLUGIN_DIR . 'patterns/' . $file;
	if ( ! file_exists( $path ) ) {
		return '';
	}

	ob_start();
	include $path;
	return (string) ob_get_clean();
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
