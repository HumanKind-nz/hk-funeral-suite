<?php
/**
 * Hooks — Activation, Deactivation, Compatibility, Admin Tweaks
 *
 * Combines logic from class-capabilities, class-admin, class-post-mods,
 * class-hk-funeral-cpt-compatibility, and main plugin bootstrap hooks.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\Hooks;

defined( 'WPINC' ) || exit;

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	// Activation / deactivation.
	register_activation_hook( HK_FS_PLUGIN_FILE, __NAMESPACE__ . '\\activate' );
	register_deactivation_hook( HK_FS_PLUGIN_FILE, __NAMESPACE__ . '\\deactivate' );

	// Capabilities — simplified to manage_options.
	add_action( 'admin_init', __NAMESPACE__ . '\\maybe_migrate_capabilities' );

	// Rewrite rule flushing.
	add_action( 'admin_init', __NAMESPACE__ . '\\maybe_flush_rules' );
	add_action( 'update_option_hk_fs_enable_public_staff', __NAMESPACE__ . '\\schedule_flush', 10, 2 );
	add_action( 'update_option_hk_fs_enable_public_packages', __NAMESPACE__ . '\\schedule_flush', 10, 2 );

	// Admin bar tweaks — remove "View" link for non-public CPTs.
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\remove_admin_bar_view_link', 999 );

	// Admin footer text.
	add_filter( 'admin_footer_text', __NAMESPACE__ . '\\admin_footer_text' );

	// Admin styles.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_styles' );

	// Theme/plugin compatibility.
	add_action( 'admin_init', __NAMESPACE__ . '\\load_compatibility_filters' );

	// Cache purging on REST meta updates.
	add_action( 'updated_post_meta', __NAMESPACE__ . '\\maybe_clear_cache_on_rest_update', 10, 4 );
}

// ─── Activation / Deactivation ──────────────────────────────────────────────

/**
 * Plugin activation.
 */
function activate(): void {
	$defaults = [
		'staff'     => true,
		'caskets'   => true,
		'urns'      => true,
		'packages'  => false,
		'monuments' => false,
		'keepsakes' => false,
	];

	if ( ! get_option( 'hk_fs_enabled_cpts' ) ) {
		update_option( 'hk_fs_enabled_cpts', $defaults );
	}

	// Ensure admin has manage_options (standard WP cap).
	// Clean up old custom roles if present.
	cleanup_legacy_roles();

	flush_rewrite_rules();
}

/**
 * Plugin deactivation.
 */
function deactivate(): void {
	flush_rewrite_rules();
}

// ─── Capabilities Migration ─────────────────────────────────────────────────

/**
 * One-time migration: remove custom roles and caps, simplify to standard WP caps.
 */
function maybe_migrate_capabilities(): void {
	if ( get_option( 'hk_fs_caps_migrated_v2' ) ) {
		return;
	}

	cleanup_legacy_roles();
	update_option( 'hk_fs_caps_migrated_v2', true );
}

/**
 * Remove legacy custom roles and capabilities.
 */
function cleanup_legacy_roles(): void {
	// Remove custom roles.
	remove_role( 'funeral_staff' );
	remove_role( 'funeral_manager' );

	// Remove custom caps from administrator.
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->remove_cap( 'manage_funeral_content' );
		$admin->remove_cap( 'manage_funeral_settings' );
	}

	// Remove custom caps from editor.
	$editor = get_role( 'editor' );
	if ( $editor ) {
		$editor->remove_cap( 'manage_funeral_content' );
		$editor->remove_cap( 'manage_funeral_settings' );
	}

	// Clean up transients.
	delete_transient( 'hk_fs_roles_version' );
	delete_option( 'hk_fs_roles_updated' );
}

// ─── Rewrite Rules ──────────────────────────────────────────────────────────

/**
 * Flush rewrite rules if flagged.
 */
function maybe_flush_rules(): void {
	if ( get_option( 'hk_fs_flush_rewrite_rules' ) === 'yes' ) {
		flush_rewrite_rules();
		delete_option( 'hk_fs_flush_rewrite_rules' );
	}
}

/**
 * Schedule a rewrite rule flush on next load.
 *
 * @param mixed $old_value Old option value.
 * @param mixed $value     New option value.
 */
function schedule_flush( $old_value, $value ): void {
	if ( $old_value !== $value ) {
		update_option( 'hk_fs_flush_rewrite_rules', 'yes' );
	}
}

// ─── Admin Bar ──────────────────────────────────────────────────────────────

/**
 * Remove "View" link from admin bar for non-public CPTs.
 *
 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
 */
function remove_admin_bar_view_link( $wp_admin_bar ): void {
	if ( ! is_admin() || ! isset( $_GET['post'] ) ) {
		return;
	}

	$post_id   = absint( $_GET['post'] );
	$post_type = get_post_type( $post_id );

	$cpt_settings = [
		'hk_fs_staff'    => 'hk_fs_enable_public_staff',
		'hk_fs_casket'   => 'hk_fs_enable_public_caskets',
		'hk_fs_urn'      => 'hk_fs_enable_public_urns',
		'hk_fs_package'  => 'hk_fs_enable_public_packages',
		'hk_fs_monument' => 'hk_fs_enable_public_monuments',
		'hk_fs_keepsake' => 'hk_fs_enable_public_keepsakes',
	];

	if ( ! isset( $cpt_settings[ $post_type ] ) ) {
		return;
	}

	if ( ! get_option( $cpt_settings[ $post_type ], false ) ) {
		$wp_admin_bar->remove_node( 'view' );
	}
}

// ─── Admin Footer / Styles ──────────────────────────────────────────────────

/**
 * Custom admin footer text on our screens.
 *
 * @param string $footer_text Default footer text.
 * @return string
 */
function admin_footer_text( string $footer_text ): string {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return $footer_text;
	}

	$cpt_slugs = \HKFuneralSuite\PostTypes\get_all_cpt_slugs();
	$is_our_screen = in_array( $screen->post_type, $cpt_slugs, true )
		|| ( isset( $_GET['page'] ) && $_GET['page'] === 'hk-funeral-suite-settings' );

	if ( $is_our_screen ) {
		return sprintf(
			/* translators: %s: Plugin URL */
			__( 'Thank you for using <a href="%s" target="_blank">HK Funeral Suite</a>.', 'hk-funeral-suite' ),
			'https://github.com/HumanKind-nz/hk-funeral-suite/'
		);
	}

	return $footer_text;
}

/**
 * Enqueue admin styles for our CPT screens.
 *
 * @param string $hook Current admin page.
 */
function enqueue_admin_styles( string $hook ): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$cpt_slugs = \HKFuneralSuite\PostTypes\get_all_cpt_slugs();
	if ( in_array( $screen->post_type, $cpt_slugs, true ) ) {
		$css_path = HK_FS_PLUGIN_DIR . 'assets/css/admin-style.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'hk-funeral-admin-style',
				HK_FS_PLUGIN_URL . 'assets/css/admin-style.css',
				[],
				HK_FS_VERSION
			);
		}
	}
}

// ─── Theme / Plugin Compatibility ───────────────────────────────────────────

/**
 * Load compatibility filters based on settings.
 */
function load_compatibility_filters(): void {
	$cpt_slugs = \HKFuneralSuite\PostTypes\get_all_cpt_slugs();

	// HappyFiles — auto-enable if installed.
	if ( class_exists( 'HappyFiles\\Pro' ) ) {
		if ( get_option( 'hk_fs_happyfiles_compatibility', null ) === null ) {
			update_option( 'hk_fs_happyfiles_compatibility', true );
		}
	}

	if ( get_option( 'hk_fs_happyfiles_compatibility', false ) ) {
		foreach ( $cpt_slugs as $slug ) {
			add_filter( "manage_{$slug}_posts_columns", function ( $columns ) {
				unset( $columns['hf_featured_image'], $columns['hf_featured_image hide'] );
				return $columns;
			}, 1000 );
		}
	}

	// SEOPress — auto-enable if installed.
	if ( function_exists( 'seopress_init' ) || class_exists( '\\SEOPRESS\\Core\\Kernel' ) ) {
		if ( get_option( 'hk_fs_seopress_metabox_compatibility', null ) === null ) {
			update_option( 'hk_fs_seopress_metabox_compatibility', true );
		}
	}

	if ( get_option( 'hk_fs_seopress_metabox_compatibility', false ) ) {
		$seopress_filters = [
			'seopress_metaboxe_term_seo',
			'seopress_metaboxe_content_analysis',
			'seopress_metaboxe_titles',
			'seopress_metaboxe_social',
			'seopress_metaboxe_advanced',
		];
		foreach ( $seopress_filters as $filter ) {
			add_filter( $filter, function ( $enabled ) use ( $cpt_slugs ) {
				return remove_cpt_from_seopress( $enabled, $cpt_slugs );
			} );
		}
	}

	// GeneratePress.
	if ( get_option( 'hk_fs_generatepress_compatibility', false ) && is_theme_active( 'generatepress' ) ) {
		add_action( 'add_meta_boxes', function () use ( $cpt_slugs ) {
			foreach ( $cpt_slugs as $slug ) {
				remove_meta_box( 'generate_layout_options_meta_box', $slug, 'normal' );
				remove_meta_box( 'generate_layout_options_meta_box', $slug, 'side' );
				remove_meta_box( '_generate_use_sections_metabox', $slug, 'side' );
			}
		}, 100 );
	}

	// Page Builder Framework.
	if ( get_option( 'hk_fs_wpbf_compatibility', false ) && is_theme_active( 'page-builder-framework' ) ) {
		add_action( 'add_meta_boxes', function () use ( $cpt_slugs ) {
			foreach ( $cpt_slugs as $slug ) {
				remove_meta_box( 'wpbf', $slug, 'side' );
				remove_meta_box( 'wpbf_header', $slug, 'side' );
				remove_meta_box( 'wpbf_sidebar', $slug, 'side' );
			}
		}, 100 );
	}
}

/**
 * Check if a theme is active.
 *
 * @param string $theme_slug Theme slug.
 * @return bool
 */
function is_theme_active( string $theme_slug ): bool {
	$theme = wp_get_theme();
	return $theme->get( 'Template' ) === $theme_slug || $theme->get_stylesheet() === $theme_slug;
}

/**
 * Remove our CPTs from SEOPress-enabled post types.
 *
 * @param array|mixed $enabled_post_types Post types where SEOPress is enabled.
 * @param string[]    $cpt_slugs          Our CPT slugs.
 * @return array
 */
function remove_cpt_from_seopress( $enabled_post_types, array $cpt_slugs ): array {
	if ( ! is_array( $enabled_post_types ) ) {
		$enabled_post_types = [];
	}

	if ( empty( $enabled_post_types ) ) {
		$all = get_post_types( [ 'public' => true ], 'names' );
		foreach ( $all as $pt ) {
			$enabled_post_types[ $pt ] = $pt;
		}
	}

	foreach ( $cpt_slugs as $slug ) {
		unset( $enabled_post_types[ $slug ] );
	}

	return $enabled_post_types;
}

// ─── Cache Purging ──────────────────────────────────────────────────────────

/**
 * Clear caches when specific meta fields are updated via REST API.
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 */
function maybe_clear_cache_on_rest_update( $meta_id, $post_id, $meta_key, $meta_value ): void {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return;
	}

	// Only trigger on our meta keys.
	if ( strpos( $meta_key, '_hk_fs_' ) !== 0 ) {
		return;
	}

	$cpt_slugs = \HKFuneralSuite\PostTypes\get_all_cpt_slugs();
	if ( ! in_array( get_post_type( $post_id ), $cpt_slugs, true ) ) {
		return;
	}

	optimised_cache_purge( $post_id, 'REST API meta update' );
}

/**
 * Optimised cache purging — debounced, runs once at shutdown.
 *
 * @param int|null $post_id Post ID.
 * @param string   $context Context for logging.
 */
function optimised_cache_purge( ?int $post_id = null, string $context = 'unknown' ): void {
	static $purge_requested = false;
	static $posts_to_purge  = [];

	if ( $post_id ) {
		$posts_to_purge[ $post_id ] = true;
	}

	if ( ! $purge_requested ) {
		$purge_requested = true;

		add_action( 'shutdown', function () use ( &$posts_to_purge ) {
			// Beaver Builder per-post cache.
			if ( class_exists( 'FLBuilderModel' ) ) {
				foreach ( array_keys( $posts_to_purge ) as $pid ) {
					\FLBuilderModel::delete_asset_cache_for_post( $pid );
				}
			}

			// Weave Cache Purge Helper.
			if ( function_exists( 'wcph_purge' ) ) {
				wcph_purge();
			}
		}, 999 );
	}
}
