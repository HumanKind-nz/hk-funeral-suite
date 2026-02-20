<?php
/**
 * Block Editor Customisations
 *
 * Filters allowed block types per CPT, maps capabilities for block binding,
 * and configures template locking.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\BlockEditor;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_filter( 'allowed_block_types_all', __NAMESPACE__ . '\\filter_allowed_block_types', 10, 2 );
	add_filter( 'map_meta_cap', __NAMESPACE__ . '\\add_block_binding_caps', 10, 4 );
	add_filter( 'block_editor_settings_all', __NAMESPACE__ . '\\add_template_lock_settings', 10, 2 );
}

/**
 * Filter allowed block types for our CPTs.
 *
 * @param array|bool                $allowed_blocks Allowed block types.
 * @param \WP_Block_Editor_Context $editor_context Editor context.
 * @return array|bool
 */
function filter_allowed_block_types( $allowed_blocks, $editor_context ) {
	if ( ! $editor_context || ! isset( $editor_context->post ) ) {
		return $allowed_blocks;
	}

	$block_map = \HKFuneralSuite\PostTypes\get_post_type_block_map();
	$post_type = $editor_context->post->post_type;

	if ( ! array_key_exists( $post_type, $block_map ) ) {
		return $allowed_blocks;
	}

	$common_blocks = [
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/separator',
		'core/button',
		'core/buttons',
		'core/shortcode',
		'core/html',
	];

	return array_merge( [ $block_map[ $post_type ] ], $common_blocks );
}

/**
 * Map block binding capability to standard edit_posts.
 *
 * @param string[] $caps    Required capabilities.
 * @param string   $cap     Capability being checked.
 * @param int      $user_id User ID.
 * @param array    $args    Additional arguments.
 * @return string[]
 */
function add_block_binding_caps( array $caps, string $cap, int $user_id, array $args ): array {
	if ( $cap === 'edit_block_binding' ) {
		return [ 'edit_posts' ];
	}
	return $caps;
}

/**
 * Add template lock settings for our post types.
 *
 * @param array                     $editor_settings Editor settings.
 * @param \WP_Block_Editor_Context $editor_context  Editor context.
 * @return array
 */
function add_template_lock_settings( array $editor_settings, $editor_context ): array {
	if ( ! $editor_context || ! isset( $editor_context->post ) ) {
		return $editor_settings;
	}

	$block_map = \HKFuneralSuite\PostTypes\get_post_type_block_map();

	if ( array_key_exists( $editor_context->post->post_type, $block_map ) ) {
		$editor_settings['templateLock'] = false;
	}

	return $editor_settings;
}

