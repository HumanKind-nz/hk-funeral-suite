<?php
/**
 * Block Importer
 *
 * Injects the required meta block into programmatically created posts
 * (WP All Import Pro, REST API). Posts created via the block editor get
 * the block from the CPT template automatically — this handles the
 * non-editor creation paths.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\Import;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	// WP All Import Pro.
	add_action( 'pmxi_saved_post', __NAMESPACE__ . '\\add_blocks_after_import', 10, 3 );

	// REST API inserts (Google Sheets, external tools).
	$cpt_slugs = \HKFuneralSuite\PostTypes\get_all_cpt_slugs();
	foreach ( $cpt_slugs as $slug ) {
		add_action( "rest_insert_{$slug}", __NAMESPACE__ . '\\add_blocks_after_api_insert', 10, 3 );
	}

	// Auto-insert block for new posts via wp_insert_post.
	add_action( 'wp_insert_post', __NAMESPACE__ . '\\auto_insert_block', 10, 3 );
}

/**
 * Get the default block HTML for a post type.
 *
 * @param string $post_type Full post type slug.
 * @return string Block HTML or empty string.
 */
function get_default_block( string $post_type ): string {
	$block_map = \HKFuneralSuite\PostTypes\get_post_type_block_map();

	if ( ! isset( $block_map[ $post_type ] ) ) {
		return '';
	}

	$block_name = $block_map[ $post_type ];

	// Meta blocks store no attributes — data lives in post meta via useEntityProp.
	return "<!-- wp:{$block_name} /-->";
}

/**
 * Add default blocks after WP All Import Pro import.
 *
 * @param int   $post_id        Post ID.
 * @param array $data           Import data.
 * @param array $import_options Import options.
 */
function add_blocks_after_import( int $post_id, $data, $import_options ): void {
	$post_type = get_post_type( $post_id );
	$block     = get_default_block( $post_type );

	if ( ! empty( $block ) ) {
		maybe_add_block_to_post( $post_id, $block, $post_type );
	}
}

/**
 * Add default blocks after REST API insertion.
 *
 * @param \WP_Post         $post     Post object.
 * @param \WP_REST_Request $request  Request object.
 * @param bool             $creating Whether this is a new post.
 */
function add_blocks_after_api_insert( $post, $request, bool $creating ): void {
	if ( ! $creating ) {
		return;
	}

	$block = get_default_block( $post->post_type );
	if ( ! empty( $block ) ) {
		maybe_add_block_to_post( $post->ID, $block, $post->post_type );
	}
}

/**
 * Auto-insert block when a new post is created.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post object.
 * @param bool     $update  Whether this is an update.
 */
function auto_insert_block( int $post_id, $post = null, bool $update = false ): void {
	if ( ! is_object( $post ) ) {
		$post = get_post( $post_id );
	}

	$block_map = \HKFuneralSuite\PostTypes\get_post_type_block_map();

	if ( ! array_key_exists( $post->post_type, $block_map ) || $post->post_content !== '' ) {
		return;
	}

	$block = get_default_block( $post->post_type );
	if ( ! empty( $block ) ) {
		wp_update_post( [
			'ID'           => $post->ID,
			'post_content' => $block,
		] );
	}
}

/**
 * Add block to post if it doesn't already contain the required block.
 *
 * @param int    $post_id    Post ID.
 * @param string $block_html Block HTML to insert.
 * @param string $post_type  Post type slug.
 */
function maybe_add_block_to_post( int $post_id, string $block_html, string $post_type ): void {
	$post    = get_post( $post_id );
	$content = $post->post_content;

	$block_map  = \HKFuneralSuite\PostTypes\get_post_type_block_map();
	$block_name = $block_map[ $post_type ] ?? '';

	if ( empty( $block_name ) || strpos( $content, $block_name ) !== false ) {
		return;
	}

	$new_content = $block_html;

	if ( ! empty( trim( $content ) ) ) {
		$paragraphs = array_filter( explode( "\n", trim( $content ) ) );
		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( ! empty( $paragraph ) ) {
				$new_content .= "\n\n<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->";
			}
		}
	}

	wp_update_post( [
		'ID'           => $post_id,
		'post_content' => $new_content,
	] );
}
