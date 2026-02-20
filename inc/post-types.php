<?php
/**
 * Custom Post Type Registration
 *
 * Consolidated registration for all CPTs: staff, packages, and product types
 * (caskets, urns, monuments, keepsakes).
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\PostTypes;

defined( 'WPINC' ) || exit;

/**
 * CPT slug definitions — single source of truth.
 *
 * @return array<string, array{slug: string, singular: string, plural: string, type: string}>
 */
function get_cpt_definitions(): array {
	return [
		'staff' => [
			'slug'     => 'hk_fs_staff',
			'singular' => __( 'Team Member', 'hk-funeral-suite' ),
			'plural'   => __( 'Team Members', 'hk-funeral-suite' ),
			'type'     => 'staff',
		],
		'caskets' => [
			'slug'     => 'hk_fs_casket',
			'singular' => __( 'Casket', 'hk-funeral-suite' ),
			'plural'   => __( 'Caskets', 'hk-funeral-suite' ),
			'type'     => 'product',
		],
		'urns' => [
			'slug'     => 'hk_fs_urn',
			'singular' => __( 'Urn', 'hk-funeral-suite' ),
			'plural'   => __( 'Urns', 'hk-funeral-suite' ),
			'type'     => 'product',
		],
		'packages' => [
			'slug'     => 'hk_fs_package',
			'singular' => __( 'Package', 'hk-funeral-suite' ),
			'plural'   => __( 'Pricing Packages', 'hk-funeral-suite' ),
			'type'     => 'package',
		],
		'monuments' => [
			'slug'     => 'hk_fs_monument',
			'singular' => __( 'Monument', 'hk-funeral-suite' ),
			'plural'   => __( 'Monuments', 'hk-funeral-suite' ),
			'type'     => 'product',
		],
		'keepsakes' => [
			'slug'     => 'hk_fs_keepsake',
			'singular' => __( 'Keepsake', 'hk-funeral-suite' ),
			'plural'   => __( 'Keepsakes', 'hk-funeral-suite' ),
			'type'     => 'product',
		],
	];
}

/**
 * Get all CPT slugs.
 *
 * @return string[]
 */
function get_all_cpt_slugs(): array {
	return array_column( get_cpt_definitions(), 'slug' );
}

/**
 * Get the post-type → block-name mapping.
 *
 * @return array<string, string>
 */
function get_post_type_block_map(): array {
	return [
		'hk_fs_staff'    => 'hk-funeral-suite/team-member',
		'hk_fs_casket'   => 'hk-funeral-suite/casket',
		'hk_fs_urn'      => 'hk-funeral-suite/urn',
		'hk_fs_package'  => 'hk-funeral-suite/pricing-package',
		'hk_fs_monument' => 'hk-funeral-suite/monument',
		'hk_fs_keepsake' => 'hk-funeral-suite/keepsake',
	];
}

/**
 * SVG icons for product CPTs (used in admin menu via CSS).
 *
 * @return array<string, string>
 */
function get_product_svg_icons(): array {
	return [
		'casket'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="%23a7aaad" d="M406.5 115.2l-107.8-105.9C292.6 3.375 284.3 0 275.6 0H172.4C163.7 0 155.4 3.375 149.2 9.375L41.46 115.2c-8.002 7.875-11.25 19.38-8.502 30.38l87.14 342.1C123.7 502 136.7 512 151.7 512h144.7c14.88 0 27.88-9.1 31.51-24.25l87.14-342.1C417.8 134.6 414.5 123.1 406.5 115.2zM284.5 464H163.5l-81.64-321.1L178.5 48h91.02l96.64 94.88L284.5 464z"/></svg>',
		'urn'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23a7aaad" d="M73.744,19.624c-0.748-1.007-2.44-1.765-3.691-1.765H29.946c-1.251,0-2.943,0.758-3.692,1.765c-3.211,4.291-8.396,13.58-8.396,30.381c0,13.462,3.378,23.809,7.176,31.244c2.917,5.719,6.092,9.711,8.154,12.069C34.015,94.262,35.73,95,36.981,95h26.036c1.252,0,2.966-0.738,3.792-1.683c2.062-2.358,5.238-6.351,8.154-12.069c3.798-7.435,7.176-17.781,7.176-31.244C82.14,33.204,76.955,23.915,73.744,19.624z M62.857,88.573H37.141c0,0-12.855-12.854-12.855-38.569c0-17.903,6.427-25.718,6.427-25.718h38.573c0,0,6.427,7.815,6.427,25.718C75.713,75.72,62.857,88.573,62.857,88.573z"/></svg>',
		'monument' => '<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.50001 9.5C7.50001 9.08579 7.83579 8.75 8.25001 8.75H15.75C16.1642 8.75 16.5 9.08579 16.5 9.5C16.5 9.91421 16.1642 10.25 15.75 10.25H8.25001C7.83579 10.25 7.50001 9.91421 7.50001 9.5Z" fill="%23a7aaad"/><path d="M9.75001 11.75C9.33579 11.75 9.00001 12.0858 9.00001 12.5C9.00001 12.9142 9.33579 13.25 9.75001 13.25H14.25C14.6642 13.25 15 12.9142 15 12.5C15 12.0858 14.6642 11.75 14.25 11.75H9.75001Z" fill="%23a7aaad"/><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C7.85787 2 4.50001 5.35786 4.50001 9.5V18.8676C3.88244 19.2626 3.29812 19.7049 2.7522 20.1893C2.44239 20.4643 2.41411 20.9383 2.68904 21.2481C2.96397 21.5579 3.438 21.5862 3.74782 21.3113C5.94402 19.3624 8.83299 18.1797 12 18.1797C15.167 18.1797 18.056 19.3624 20.2522 21.3113C20.562 21.5862 21.036 21.5579 21.311 21.2481C21.5859 20.9383 21.5576 20.4643 21.2478 20.1893C20.7019 19.7049 20.1176 19.2626 19.5 18.8676V9.5C19.5 5.35786 16.1421 2 12 2ZM18 18.0338L18 9.5C18 6.18629 15.3137 3.5 12 3.5C8.6863 3.5 6.00001 6.18629 6.00001 9.5V18.0338C7.81735 17.1658 9.85224 16.6797 12 16.6797C14.1478 16.6797 16.1827 17.1658 18 18.0338Z" fill="%23a7aaad"/></svg>',
		'keepsake' => '<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M20.4907 3.87809C20.5607 3.46809 20.2807 3.08809 19.8707 3.01809C19.4707 2.94809 19.0807 3.22809 19.0107 3.63809C18.4507 7.00809 16.0507 15.7581 11.7507 15.7581C7.45073 15.7581 5.05073 7.00809 4.49073 3.63809C4.42073 3.22809 4.03073 2.94809 3.63073 3.00809C3.22073 3.08809 2.94073 3.46809 3.01073 3.87809C3.09073 4.38809 5.02073 15.5581 10.4907 17.0781C10.4407 17.1581 10.4007 17.2281 10.3507 17.3181C10.1607 17.6481 9.95073 18.0281 9.80073 18.3881C9.66073 18.7181 9.50073 19.1281 9.50073 19.5081C9.50073 20.7481 10.5107 21.7581 11.7507 21.7581C12.9907 21.7581 14.0007 20.7481 14.0007 19.5081C14.0007 19.1281 13.8507 18.7181 13.7007 18.3881C13.5407 18.0281 13.3407 17.6581 13.1507 17.3181C13.1007 17.2381 13.0507 17.1581 13.0107 17.0781C18.4807 15.5581 20.4107 4.38809 20.4907 3.87809Z" fill="%23a7aaad"/></svg>',
	];
}

/**
 * Bootstrap — hook everything.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_enabled_cpts', 5 );
	add_action( 'init', __NAMESPACE__ . '\\register_all_meta', 10 );
	add_action( 'init', __NAMESPACE__ . '\\register_block_templates', 11 );
	add_action( 'admin_menu', __NAMESPACE__ . '\\reorder_admin_menu', 999 );
}

/**
 * Reorder admin menu to keep all CPTs grouped between Posts and Media.
 *
 * WordPress casts menu_position to int and resolves collisions by
 * incrementing +1, so with 6 CPTs some cascade past Media (position 10).
 * This hook runs late and manually repositions our CPTs.
 */
function reorder_admin_menu(): void {
	global $menu;

	$cpt_slugs = get_all_cpt_slugs();
	$our_items = [];

	// Extract our CPT menu items.
	foreach ( $menu as $position => $item ) {
		if ( isset( $item[2] ) ) {
			$menu_slug = $item[2];
			// CPT edit screens use "edit.php?post_type={slug}" as the menu slug.
			foreach ( $cpt_slugs as $slug ) {
				if ( $menu_slug === "edit.php?post_type={$slug}" ) {
					$our_items[] = $item;
					unset( $menu[ $position ] );
					break;
				}
			}
		}
	}

	if ( empty( $our_items ) ) {
		return;
	}

	// Re-insert at sequential positions between Posts (5) and Media (10).
	// Using string keys like '5.1', '5.2' avoids integer collision resolution.
	$i = 1;
	foreach ( $our_items as $item ) {
		$menu[ '5.' . $i ] = $item;
		$i++;
	}
}

/**
 * Register all enabled CPTs.
 */
function register_enabled_cpts(): void {
	$settings = \HK_Funeral_Settings::get_instance();

	if ( $settings->is_cpt_enabled( 'staff' ) ) {
		register_staff_cpt();
	}

	if ( $settings->is_cpt_enabled( 'packages' ) ) {
		register_packages_cpt();
	}

	// Product CPTs via shared registration.
	$product_cpts = [ 'caskets', 'urns', 'monuments', 'keepsakes' ];
	foreach ( $product_cpts as $key ) {
		if ( $settings->is_cpt_enabled( $key ) ) {
			register_product_cpt( $key );
		}
	}
}

// ─── Staff CPT ──────────────────────────────────────────────────────────────

/**
 * Register the Staff (Team Member) CPT.
 */
function register_staff_cpt(): void {
	$make_public = (bool) get_option( 'hk_fs_enable_public_staff', false );

	$labels = [
		'name'                  => _x( 'Team Members', 'Post type general name', 'hk-funeral-suite' ),
		'singular_name'         => _x( 'Team Member', 'Post type singular name', 'hk-funeral-suite' ),
		'menu_name'             => _x( 'HK Team Members', 'Admin Menu text', 'hk-funeral-suite' ),
		'name_admin_bar'        => _x( 'Team Member', 'Add New on Toolbar', 'hk-funeral-suite' ),
		'add_new'               => __( 'Add Team Member', 'hk-funeral-suite' ),
		'add_new_item'          => __( 'Add New Team Member', 'hk-funeral-suite' ),
		'new_item'              => __( 'New Team Member', 'hk-funeral-suite' ),
		'edit_item'             => __( 'Edit Team Member', 'hk-funeral-suite' ),
		'view_item'             => __( 'View Team Members', 'hk-funeral-suite' ),
		'all_items'             => __( 'All Team', 'hk-funeral-suite' ),
		'search_items'          => __( 'Search Team', 'hk-funeral-suite' ),
		'not_found'             => __( 'No team members found.', 'hk-funeral-suite' ),
		'not_found_in_trash'    => __( 'No team members found in Trash.', 'hk-funeral-suite' ),
		'featured_image'        => __( 'Team Member Image', 'hk-funeral-suite' ),
		'set_featured_image'    => __( 'Set team member image', 'hk-funeral-suite' ),
		'remove_featured_image' => __( 'Remove team member image', 'hk-funeral-suite' ),
		'use_featured_image'    => __( 'Use as team member image', 'hk-funeral-suite' ),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => $make_public,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 6,
		'query_var'           => $make_public,
		'rewrite'             => $make_public ? [ 'slug' => 'team' ] : false,
		'capability_type'     => 'post',
		'has_archive'         => $make_public,
		'hierarchical'        => false,
		'menu_icon'           => 'dashicons-businesswoman',
		'supports'            => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes', 'revisions' ],
		'show_in_rest'        => true,
		'show_in_nav_menus'   => $make_public,
		'exclude_from_search' => ! $make_public,
		'template'            => [
			[ 'hk-funeral-suite/team-member', [ 'lock' => [ 'move' => true, 'remove' => true ] ] ],
			[ 'core/paragraph' ],
		],
		'template_lock'       => false,
	];

	$args = apply_filters( 'hk_fs_staff_post_type_args', $args );

	register_post_type( 'hk_fs_staff', $args );

	// Location taxonomy.
	register_taxonomy( 'hk_fs_location', [ 'hk_fs_staff' ], [
		'labels'            => taxonomy_labels( __( 'Location', 'hk-funeral-suite' ), __( 'Locations', 'hk-funeral-suite' ) ),
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_nav_menus' => true,
		'show_tagcloud'     => false,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'staff-location' ],
	] );

	// Job Role taxonomy.
	register_taxonomy( 'hk_fs_role', [ 'hk_fs_staff' ], [
		'labels'            => taxonomy_labels( __( 'Job Role', 'hk-funeral-suite' ), __( 'Job Roles', 'hk-funeral-suite' ) ),
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_nav_menus' => true,
		'show_tagcloud'     => false,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'job-role' ],
	] );

	// SVG icon via admin CSS.
	register_svg_icon( 'staff', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23a7aaad" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0-6c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zM12 14c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm6 4H6v-.99c.2-.72 3.3-2.01 6-2.01s5.8 1.29 6 2v1z"/></svg>' );

	// Fire registration hook for other components.
	do_action( 'hk_fs_register_cpt', 'staff', 'staff', [
		'singular' => __( 'Team Member', 'hk-funeral-suite' ),
		'plural'   => __( 'Team Members', 'hk-funeral-suite' ),
	] );
}

// ─── Packages CPT ───────────────────────────────────────────────────────────

/**
 * Register the Pricing Packages CPT.
 */
function register_packages_cpt(): void {
	$make_public = (bool) get_option( 'hk_fs_enable_public_packages', false );

	$labels = [
		'name'                  => _x( 'Pricing Packages', 'Post type general name', 'hk-funeral-suite' ),
		'singular_name'         => _x( 'Package', 'Post type singular name', 'hk-funeral-suite' ),
		'menu_name'             => _x( 'HK Pricing Packages', 'Admin Menu text', 'hk-funeral-suite' ),
		'name_admin_bar'        => _x( 'Pricing Package', 'Add New on Toolbar', 'hk-funeral-suite' ),
		'add_new'               => __( 'Add Pricing Package', 'hk-funeral-suite' ),
		'add_new_item'          => __( 'Add New Pricing Package', 'hk-funeral-suite' ),
		'new_item'              => __( 'New Pricing Package', 'hk-funeral-suite' ),
		'edit_item'             => __( 'Edit Pricing Package', 'hk-funeral-suite' ),
		'view_item'             => __( 'View Pricing Package', 'hk-funeral-suite' ),
		'all_items'             => __( 'Pricing Packages', 'hk-funeral-suite' ),
		'search_items'          => __( 'Search Pricing Packages', 'hk-funeral-suite' ),
		'not_found'             => __( 'No packages found.', 'hk-funeral-suite' ),
		'not_found_in_trash'    => __( 'No packages found in Trash.', 'hk-funeral-suite' ),
		'featured_image'        => __( 'Package Image', 'hk-funeral-suite' ),
		'set_featured_image'    => __( 'Set package image', 'hk-funeral-suite' ),
		'remove_featured_image' => __( 'Remove package image', 'hk-funeral-suite' ),
		'use_featured_image'    => __( 'Use as package image', 'hk-funeral-suite' ),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => $make_public,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 6,
		'query_var'           => $make_public,
		'rewrite'             => $make_public ? [ 'slug' => 'funeral-packages' ] : false,
		'capability_type'     => 'post',
		'has_archive'         => $make_public,
		'hierarchical'        => false,
		'menu_icon'           => 'data:image/svg+xml;base64,' . base64_encode( '<svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.1 5.74988C11.1 5.25283 11.5029 4.84988 12 4.84988C12.4971 4.84988 12.9001 5.25283 12.9001 5.74988C12.9001 6.24694 12.4972 6.64988 12.0001 6.64988C11.503 6.64988 11.1 6.24694 11.1 5.74988Z" fill="#a7aaad"/><path d="M12 8.24988C12.4142 8.24988 12.75 8.58567 12.75 8.99988V9.43766C13.7408 9.5883 14.5 10.4438 14.5 11.4767C14.5 11.8909 14.1642 12.2267 13.75 12.2267C13.3358 12.2267 13 11.8909 13 11.4767C13 11.166 12.7481 10.9141 12.4374 10.9141H11.75C11.3358 10.9141 11 11.2499 11 11.6641V11.9292C11 12.2418 11.1939 12.5217 11.4866 12.6314L13.0401 13.214C13.9182 13.5434 14.5 14.3829 14.5 15.3207V15.5858C14.5 16.6566 13.752 17.5527 12.75 17.7801V18.2499C12.75 18.6641 12.4142 18.9999 12 18.9999C11.5858 18.9999 11.25 18.6641 11.25 18.2499V17.8123C10.2592 17.6616 9.5 16.8061 9.5 15.7732C9.5 15.359 9.83579 15.0232 10.25 15.0232C10.6642 15.0232 11 15.359 11 15.7732C11 16.0839 11.2519 16.3358 11.5626 16.3358H12.25C12.6642 16.3358 13 16 13 15.5858V15.3207C13 15.0081 12.8061 14.7283 12.5134 14.6185L10.9599 14.0359C10.0818 13.7066 9.5 12.8671 9.5 11.9292V11.6641C9.5 10.5933 10.248 9.69724 11.25 9.46988V8.99988C11.25 8.58567 11.5858 8.24988 12 8.24988Z" fill="#a7aaad"/><path fill-rule="evenodd" clip-rule="evenodd" d="M10.9073 2.26459C11.5869 1.88706 12.4131 1.88706 13.0927 2.26459L18.3427 5.18126C19.057 5.57809 19.5 6.33099 19.5 7.14811V19.7499C19.5 20.9925 18.4926 21.9999 17.25 21.9999H6.75C5.50736 21.9999 4.5 20.9925 4.5 19.7499V7.14811C4.5 6.33099 4.94301 5.57809 5.6573 5.18126L10.9073 2.26459ZM12.3642 3.57583C12.1377 3.44998 11.8623 3.44998 11.6358 3.57583L6.38577 6.49249C6.14767 6.62477 6 6.87574 6 7.14811V19.7499C6 20.1641 6.33579 20.4999 6.75 20.4999H17.25C17.6642 20.4999 18 20.1641 18 19.7499V7.14811C18 6.87574 17.8523 6.62477 17.6142 6.49249L12.3642 3.57583Z" fill="#a7aaad"/></svg>' ),
		'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
		'show_in_rest'        => true,
		'exclude_from_search' => ! $make_public,
		'template'            => [
			[ 'hk-funeral-suite/pricing-package', [ 'lock' => [ 'move' => true, 'remove' => true ] ] ],
			[ 'core/paragraph' ],
		],
		'template_lock'       => false,
	];

	$args = apply_filters( 'hk_fs_package_post_type_args', $args );

	register_post_type( 'hk_fs_package', $args );

	// Fire registration hook for other components.
	do_action( 'hk_fs_register_cpt', 'package', 'packages', [
		'singular' => __( 'Package', 'hk-funeral-suite' ),
		'plural'   => __( 'Pricing Packages', 'hk-funeral-suite' ),
	] );
}

// ─── Product CPTs ───────────────────────────────────────────────────────────

/**
 * Register a product-type CPT (caskets, urns, monuments, keepsakes).
 *
 * @param string $key The CPT key from definitions (e.g. 'caskets').
 */
function register_product_cpt( string $key ): void {
	$defs = get_cpt_definitions();
	if ( ! isset( $defs[ $key ] ) ) {
		return;
	}

	$def       = $defs[ $key ];
	$slug      = $def['slug'];
	$singular  = $def['singular'];
	$plural    = $def['plural'];
	// Post type is the slug without prefix (e.g. 'casket').
	$post_type = str_replace( 'hk_fs_', '', $slug );

	$make_public = (bool) get_option( "hk_fs_enable_public_{$key}", false );
	$icons       = get_product_svg_icons();

	$labels = [
		'name'                  => $plural,
		'singular_name'         => $singular,
		'menu_name'             => _x( 'HK ' . $plural, 'Admin Menu text', 'hk-funeral-suite' ),
		'add_new'               => __( 'Add New', 'hk-funeral-suite' ),
		'add_new_item'          => sprintf( __( 'Add New %s', 'hk-funeral-suite' ), $singular ),
		'edit_item'             => sprintf( __( 'Edit %s', 'hk-funeral-suite' ), $singular ),
		'new_item'              => sprintf( __( 'New %s', 'hk-funeral-suite' ), $singular ),
		'view_item'             => sprintf( __( 'View %s', 'hk-funeral-suite' ), $singular ),
		'view_items'            => sprintf( __( 'View %s', 'hk-funeral-suite' ), $plural ),
		'search_items'          => sprintf( __( 'Search %s', 'hk-funeral-suite' ), $plural ),
		'not_found'             => sprintf( __( 'No %s found.', 'hk-funeral-suite' ), strtolower( $plural ) ),
		'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', 'hk-funeral-suite' ), strtolower( $plural ) ),
		'featured_image'        => sprintf( __( '%s Image', 'hk-funeral-suite' ), $singular ),
		'set_featured_image'    => sprintf( __( 'Set %s image', 'hk-funeral-suite' ), strtolower( $singular ) ),
		'remove_featured_image' => sprintf( __( 'Remove %s image', 'hk-funeral-suite' ), strtolower( $singular ) ),
		'use_featured_image'    => sprintf( __( 'Use as %s image', 'hk-funeral-suite' ), strtolower( $singular ) ),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => $make_public,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 6,
		'query_var'           => $make_public,
		'rewrite'             => $make_public ? [ 'slug' => strtolower( $key ) ] : false,
		'capability_type'     => 'post',
		'has_archive'         => $make_public,
		'hierarchical'        => false,
		'supports'            => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes', 'revisions' ],
		'show_in_rest'        => true,
		'exclude_from_search' => ! $make_public,
		'template'            => [
			[ "hk-funeral-suite/{$post_type}", [ 'lock' => [ 'move' => true, 'remove' => true ] ] ],
			[ 'core/paragraph' ],
		],
		'template_lock'       => false,
	];

	$args = apply_filters( "hk_fs_{$post_type}_post_type_args", $args );

	register_post_type( $slug, $args );

	// Category taxonomy.
	register_taxonomy( "hk_fs_{$post_type}_category", [ $slug ], [
		'labels'            => taxonomy_labels(
			sprintf( __( '%s Category', 'hk-funeral-suite' ), $singular ),
			sprintf( __( '%s Categories', 'hk-funeral-suite' ), $singular )
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_nav_menus' => true,
		'show_tagcloud'     => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => strtolower( $post_type ) . '-category' ],
	] );

	// SVG icon.
	if ( isset( $icons[ $post_type ] ) ) {
		register_svg_icon( $post_type, $icons[ $post_type ] );
	}

	// Fire registration hook for other components.
	do_action( 'hk_fs_register_cpt', $post_type, $key, [
		'singular' => $singular,
		'plural'   => $plural,
	] );
}

// ─── Meta Definitions ───────────────────────────────────────────────────────

/**
 * Get all meta field definitions, keyed by post type.
 *
 * Single source of truth for every meta field the plugin registers.
 * Each field maps to args passed to register_post_meta().
 *
 * @return array<string, array<string, array>> Post type → [ meta_key → args ].
 */
function get_meta_fields(): array {
	$string_field = static fn( string $sanitize = 'sanitize_text_field' ): array => [
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => $sanitize,
		'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
	];

	return [
		'hk_fs_staff' => [
			'_hk_fs_staff_position'      => $string_field(),
			'_hk_fs_staff_qualification' => $string_field(),
			'_hk_fs_staff_phone'         => $string_field(),
			'_hk_fs_staff_email'         => $string_field( 'sanitize_email' ),
		],

		'hk_fs_package' => [
			'_hk_fs_package_price' => $string_field(),
			'_hk_fs_package_intro' => $string_field( 'sanitize_textarea_field' ),
			'_hk_fs_package_order' => [
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
				'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
			],
		],

		'hk_fs_casket' => [
			'_hk_fs_casket_price' => $string_field(),
		],

		'hk_fs_urn' => [
			'_hk_fs_urn_price' => $string_field(),
		],

		'hk_fs_monument' => [
			'_hk_fs_monument_price' => $string_field(),
		],

		'hk_fs_keepsake' => [
			'_hk_fs_keepsake_price'        => $string_field(),
			'_hk_fs_keepsake_product_code' => $string_field(),
			'_hk_fs_keepsake_metal'        => $string_field(),
			'_hk_fs_keepsake_stones'       => $string_field(),
		],
	];
}

// ─── Meta Registration ─────────────────────────────────────────────────────

/**
 * Register all meta fields for enabled CPTs.
 *
 * Reads from get_meta_fields() so there's a single place to add/edit fields.
 */
function register_all_meta(): void {
	$settings   = \HK_Funeral_Settings::get_instance();
	$all_fields = get_meta_fields();

	// Build slug → settings key map: 'hk_fs_staff' → 'staff', etc.
	$slug_to_key = [];
	foreach ( get_cpt_definitions() as $key => $def ) {
		$slug_to_key[ $def['slug'] ] = $key;
	}

	foreach ( $all_fields as $post_type => $fields ) {
		$settings_key = $slug_to_key[ $post_type ] ?? null;

		// Skip if CPT is disabled.
		if ( $settings_key && ! $settings->is_cpt_enabled( $settings_key ) ) {
			continue;
		}

		foreach ( $fields as $meta_key => $args ) {
			register_post_meta( $post_type, $meta_key, $args );
		}
	}
}

// ─── Block Templates ────────────────────────────────────────────────────────

/**
 * Register block templates for all enabled CPTs.
 */
function register_block_templates(): void {
	$block_map = get_post_type_block_map();

	foreach ( $block_map as $post_type_slug => $block_name ) {
		$post_type_object = get_post_type_object( $post_type_slug );
		if ( ! $post_type_object ) {
			continue;
		}

		// Only set template if not already set during registration.
		if ( empty( $post_type_object->template ) ) {
			$post_type_object->template = [
				[ $block_name, [ 'lock' => [ 'move' => true, 'remove' => true ] ] ],
				[ 'core/paragraph' ],
			];
			$post_type_object->template_lock = false;
		}
	}
}

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Generate taxonomy labels.
 *
 * @param string $singular Singular label.
 * @param string $plural   Plural label.
 * @return array
 */
function taxonomy_labels( string $singular, string $plural ): array {
	return [
		'name'              => $plural,
		'singular_name'     => $singular,
		'menu_name'         => $plural,
		'all_items'         => sprintf( __( 'All %s', 'hk-funeral-suite' ), $plural ),
		'edit_item'         => sprintf( __( 'Edit %s', 'hk-funeral-suite' ), $singular ),
		'update_item'       => sprintf( __( 'Update %s', 'hk-funeral-suite' ), $singular ),
		'add_new_item'      => sprintf( __( 'Add New %s', 'hk-funeral-suite' ), $singular ),
		'new_item_name'     => sprintf( __( 'New %s Name', 'hk-funeral-suite' ), $singular ),
		'search_items'      => sprintf( __( 'Search %s', 'hk-funeral-suite' ), $plural ),
		'parent_item'       => sprintf( __( 'Parent %s', 'hk-funeral-suite' ), $singular ),
		'parent_item_colon' => sprintf( __( 'Parent %s:', 'hk-funeral-suite' ), $singular ),
	];
}

/**
 * Register SVG icon CSS for a CPT's admin menu.
 *
 * @param string $post_type Post type without 'hk_fs_' prefix.
 * @param string $svg_icon  SVG markup.
 */
function register_svg_icon( string $post_type, string $svg_icon ): void {
	add_action( 'admin_head', function () use ( $post_type, $svg_icon ) {
		$hover_svg = str_replace( '%23a7aaad', '%23ffffff', $svg_icon );
		?>
		<style>
			#adminmenu .menu-icon-hk_fs_<?php echo esc_attr( $post_type ); ?> div.wp-menu-image::before {
				content: '';
				background-image: url('data:image/svg+xml;utf8,<?php echo $svg_icon; ?>');
				background-repeat: no-repeat;
				background-position: center;
				background-size: 20px;
				opacity: 0.6;
			}
			#adminmenu .menu-icon-hk_fs_<?php echo esc_attr( $post_type ); ?>:hover div.wp-menu-image::before,
			#adminmenu .menu-icon-hk_fs_<?php echo esc_attr( $post_type ); ?>.current div.wp-menu-image::before {
				background-image: url('data:image/svg+xml;utf8,<?php echo $hover_svg; ?>');
				opacity: 1;
			}
		</style>
		<?php
	} );
}

/**
 * Add a settings submenu link under a CPT's menu.
 *
 * @param string $post_type Post type without 'hk_fs_' prefix.
 */
function register_settings_submenu( string $post_type ): void {
	add_action( 'admin_menu', function () use ( $post_type ) {
		add_submenu_page(
			"edit.php?post_type=hk_fs_{$post_type}",
			__( 'HK Funeral Suite Settings', 'hk-funeral-suite' ),
			__( 'HK Funeral Suite Settings', 'hk-funeral-suite' ),
			'manage_options',
			'options-general.php?page=hk-funeral-suite-settings'
		);
	}, 100 );
}
