<?php
/**
 * Block Bindings Sources
 *
 * Lets block themes show HK data in core blocks, for example a paragraph
 * inside a Query Loop card:
 *
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"hk-funeral-suite/price","args":{"suffix":"incl. GST"}}}}} -->
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"hk-funeral-suite/field","args":{"key":"_hk_fs_staff_position"}}}}} -->
 *
 * core/post-meta cannot do this. It refuses protected (underscore) keys,
 * and every HK key is one, and it shows a visitor nothing when the post
 * type is not publicly viewable, which HK post types are not by default.
 *
 * hk-funeral-suite/field reads only the keys this plugin registers for the
 * post's type ({@see \HKFuneralSuite\PostTypes\get_meta_fields()}). It
 * never serves the catalogue's internal keys (master IDs, image version
 * hashes) or anything else. Do not widen the allow-list to private data.
 *
 * Both sources resolve the post from the block's postId context, so the
 * same markup works in any Query Loop over an HK post type.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\Bindings;

use HKFuneralSuite\Display;

defined( 'WPINC' ) || exit;

const PRICE_SOURCE = 'hk-funeral-suite/price';
const FIELD_SOURCE = 'hk-funeral-suite/field';

/**
 * Bootstrap — hook everything.
 *
 * Block Bindings arrived in WordPress 6.5; on older sites this does nothing.
 */
function bootstrap(): void {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}

	add_action( 'init', __NAMESPACE__ . '\\register_sources' );
	add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\\remove_empty_bound_block', 10, 3 );
	add_filter( 'render_block_core/heading', __NAMESPACE__ . '\\remove_empty_bound_block', 10, 3 );
}

/**
 * Whether the sources are enabled.
 *
 * @return bool
 */
function is_enabled(): bool {
	/**
	 * Filters whether the HK block bindings sources are registered.
	 *
	 * @param bool $enabled Default true.
	 */
	return (bool) apply_filters( 'hk_fs_enable_block_bindings', true );
}

/**
 * Register the sources.
 */
function register_sources(): void {
	if ( ! is_enabled() ) {
		return;
	}

	register_block_bindings_source( PRICE_SOURCE, [
		'label'              => __( 'HK price', 'hk-funeral-suite' ),
		'get_value_callback' => __NAMESPACE__ . '\\get_price_value',
		'uses_context'       => [ 'postId', 'postType' ],
	] );

	register_block_bindings_source( FIELD_SOURCE, [
		'label'              => __( 'HK field', 'hk-funeral-suite' ),
		'get_value_callback' => __NAMESPACE__ . '\\get_field_value',
		'uses_context'       => [ 'postId', 'postType' ],
	] );
}

/**
 * The HK post the block is rendering for, if a visitor may see it.
 *
 * Skips core's "post type must be publicly viewable" rule on purpose, but
 * keeps the rest: the post must be an HK post type, published (or readable
 * by the current user), and not password protected.
 *
 * @param \WP_Block $block Block instance.
 * @return \WP_Post|null
 */
function get_context_post( \WP_Block $block ): ?\WP_Post {
	$post_id = (int) ( $block->context['postId'] ?? 0 );
	if ( ! $post_id ) {
		return null;
	}

	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, \HKFuneralSuite\PostTypes\get_all_cpt_slugs(), true ) ) {
		return null;
	}

	if ( post_password_required( $post ) ) {
		return null;
	}

	if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
		return null;
	}

	return $post;
}

/**
 * Meta keys hk-funeral-suite/field may serve for a post type: the keys
 * this plugin registers for it, never an internal catalogue key.
 *
 * @param string $post_type Post type slug.
 * @return string[]
 */
function get_allowed_keys( string $post_type ): array {
	$fields = \HKFuneralSuite\PostTypes\get_meta_fields()[ $post_type ] ?? [];

	return array_values( array_filter(
		array_keys( $fields ),
		static fn( string $key ): bool => ! is_internal_key( $key )
	) );
}

/**
 * Keys that must never reach the front end, even if one were added to the
 * registered fields by mistake: catalogue master IDs, image version hashes
 * and image IDs, and anything that looks like a secret.
 *
 * @param string $key Meta key.
 * @return bool
 */
function is_internal_key( string $key ): bool {
	return (bool) preg_match( '/^_hk_fs_(master_|image_)|secret/i', $key );
}

/**
 * Value of the hk-funeral-suite/price source: the post's price as display text.
 *
 * Args (all optional): prefix and suffix (numeric prices only, e.g.
 * "From" and "incl. GST"), poa (label for POA, default "Price on
 * application"), decimals (minimum decimal places, default 0; an amount
 * with cents always shows two, e.g. "$2,499.50"). Other text is shown
 * verbatim.
 * Returns null when there is no price, and the bound block is removed.
 *
 * @param array     $args  Source args.
 * @param \WP_Block $block Block instance.
 * @return string|null
 */
function get_price_value( array $args, \WP_Block $block ): ?string {
	$post = get_context_post( $block );
	if ( ! $post ) {
		return null;
	}

	$parts = Display\price_parts( $post->ID, str_replace( 'hk_fs_', '', $post->post_type ), [
		'poa_label' => isset( $args['poa'] ) ? (string) $args['poa'] : null,
		'decimals'  => (int) ( $args['decimals'] ?? 0 ),
	] );

	if ( '' === $parts['text'] ) {
		return null;
	}

	$text = $parts['text'];

	if ( 'numeric' === $parts['state'] ) {
		$text = implode( ' ', array_filter( [
			trim( (string) ( $args['prefix'] ?? '' ) ),
			$text,
			trim( (string) ( $args['suffix'] ?? '' ) ),
		], 'strlen' ) );
	}

	return esc_html( $text );
}

/**
 * Value of the hk-funeral-suite/field source: one allow-listed meta value of the post.
 *
 * Args: key (required), one of the keys registered for the post's type,
 * e.g. _hk_fs_staff_position, _hk_fs_package_intro, _hk_fs_keepsake_metal.
 * Returns null for an unknown or internal key, or an empty value, and the
 * bound block is removed.
 *
 * @param array     $args  Source args.
 * @param \WP_Block $block Block instance.
 * @return string|null
 */
function get_field_value( array $args, \WP_Block $block ): ?string {
	$key  = (string) ( $args['key'] ?? '' );
	$post = get_context_post( $block );

	if ( '' === $key || ! $post || ! in_array( $key, get_allowed_keys( $post->post_type ), true ) ) {
		return null;
	}

	$value = get_post_meta( $post->ID, $key, true );
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	return '' === $value ? null : esc_html( $value );
}

/**
 * Drop a paragraph or heading bound to an HK source that has no value.
 *
 * Core keeps the block's own text when a source returns null, which would
 * leave an empty paragraph (or placeholder text) on a card without a price.
 *
 * @param string    $block_content Rendered block HTML.
 * @param array     $block         Parsed block.
 * @param \WP_Block $instance      Block instance, with the source's context.
 * @return string
 */
function remove_empty_bound_block( $block_content, $block, $instance = null ) {
	$binding = $block['attrs']['metadata']['bindings']['content'] ?? null;
	$source  = is_array( $binding ) ? (string) ( $binding['source'] ?? '' ) : '';

	if ( ! in_array( $source, [ PRICE_SOURCE, FIELD_SOURCE ], true ) || ! $instance instanceof \WP_Block ) {
		return $block_content;
	}

	$registered = get_block_bindings_source( $source );
	if ( ! $registered ) {
		return $block_content;
	}

	$args  = is_array( $binding['args'] ?? null ) ? $binding['args'] : [];
	$value = $registered->get_value( $args, $instance, 'content' );

	return ( null === $value || '' === $value ) ? '' : $block_content;
}
