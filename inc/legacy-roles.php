<?php
/**
 * Legacy Roles — move 1.x custom-role users onto a 2.x role before the
 * custom roles are removed.
 *
 * Versions 1.x registered two roles, funeral_staff and funeral_manager, and
 * gated the suite post types on a custom manage_funeral_content capability.
 * 2.x has no custom roles: the suite post types use capability_type 'post'
 * (inc/post-types.php), so access is the standard post capabilities, and the
 * settings screen needs manage_options. remove_role() does not touch user
 * meta, so removing the roles on their own left anyone whose only role was a
 * legacy one with no role at all.
 *
 * Default mapping (filter: hk_fs_legacy_role_map):
 *
 * - funeral_manager → editor. The manager role was editor-level for posts
 *   (others', published, private, categories, comments) plus the suite
 *   settings screen. Settings are admin-only in 2.x and are deliberately not
 *   carried over: nobody is promoted to administrator.
 * - funeral_staff → editor. The staff role existed to manage every suite
 *   post. In 2.x that needs edit_others_posts and edit_published_posts, which
 *   only editor has among the core roles; author could only edit their own
 *   posts. Editor is broader than the old staff role (pages,
 *   unfiltered_html), which is why the mapping is filterable and the
 *   `wp hk-fs roles-audit` pre-flight shows every planned change.
 *
 * A user whose other roles already grant the suite content capabilities
 * (for example an administrator, or editor + funeral_staff) only loses the
 * legacy role; nothing is added. Other roles are always kept, and a user
 * with no other role always gets the mapped one.
 *
 * This file depends on nothing else in the plugin so the read-only audit in
 * inc/cli.php can be loaded with `wp --require` against a site still on 1.x.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\LegacyRoles;

// WP_CLI as well as ABSPATH: a `wp --require` file is loaded before
// WordPress, when neither ABSPATH nor WPINC is defined yet.
defined( 'ABSPATH' ) || defined( 'WP_CLI' ) || exit;

/**
 * Role used when a mapped role is not registered on the site.
 */
const FALLBACK_ROLE = 'editor';

/**
 * Option recording which version of the role migration has run, and the
 * current version. 2 = users are moved before the roles are removed.
 */
const MIGRATION_OPTION  = 'hk_fs_roles_migration_version';
const MIGRATION_VERSION = 2;

/**
 * Legacy role → 2.x role.
 *
 * Manager is listed first so a user holding both legacy roles gets the
 * manager mapping.
 *
 * @return array<string, string>
 */
function get_role_map(): array {
	$defaults = [
		'funeral_manager' => FALLBACK_ROLE,
		'funeral_staff'   => FALLBACK_ROLE,
	];

	/**
	 * Filters the role each 1.x legacy role is migrated to.
	 *
	 * Only the funeral_manager and funeral_staff keys are read. A role that
	 * is not registered on the site falls back to editor.
	 *
	 * @param array<string, string> $map Legacy role slug → target role slug.
	 */
	$filtered = apply_filters( 'hk_fs_legacy_role_map', $defaults );

	$map = [];
	foreach ( $defaults as $legacy => $default ) {
		$target         = is_array( $filtered ) && isset( $filtered[ $legacy ] ) && is_string( $filtered[ $legacy ] ) ? $filtered[ $legacy ] : $default;
		$map[ $legacy ] = sanitize_key( $target );
	}
	return $map;
}

/**
 * Legacy role slugs.
 *
 * @return string[]
 */
function get_legacy_roles(): array {
	return array_keys( get_role_map() );
}

/**
 * Capabilities needed to manage every suite post in 2.x.
 *
 * The suite post types use capability_type 'post', so these are the
 * standard post capabilities, plus uploads for images.
 *
 * @return string[]
 */
function get_suite_caps(): array {
	return [
		'edit_posts',
		'edit_others_posts',
		'edit_published_posts',
		'publish_posts',
		'delete_posts',
		'delete_others_posts',
		'delete_published_posts',
		'upload_files',
	];
}

/**
 * Resolve the role a legacy role migrates to on this site.
 *
 * @param string $legacy Legacy role slug.
 * @return string Registered role slug, or '' when none is available.
 */
function resolve_target( string $legacy ): string {
	$map    = get_role_map();
	$target = $map[ $legacy ] ?? FALLBACK_ROLE;

	if ( '' !== $target && ! in_array( $target, get_legacy_roles(), true ) && wp_roles()->is_role( $target ) ) {
		return $target;
	}
	return wp_roles()->is_role( FALLBACK_ROLE ) ? FALLBACK_ROLE : '';
}

/**
 * Whether a set of roles and individual caps can manage every suite post.
 *
 * Mirrors WP_User::get_role_caps(): role capabilities first, then the
 * user's individual capabilities on top.
 *
 * @param string[]            $roles      Registered role slugs.
 * @param array<string, bool> $individual Individual (non-role) capabilities.
 * @return bool
 */
function covers_suite_caps( array $roles, array $individual = [] ): bool {
	$caps = [];
	foreach ( $roles as $slug ) {
		$role = wp_roles()->get_role( $slug );
		if ( $role ) {
			$caps = array_merge( $caps, $role->capabilities );
		}
	}
	$caps = array_merge( $caps, $individual );

	foreach ( get_suite_caps() as $cap ) {
		if ( empty( $caps[ $cap ] ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Split a user's raw capabilities meta into roles and individual caps.
 *
 * Legacy role keys are left out of both. They are found whether or not the
 * legacy role is still registered: a 2.0.0 build removed the roles without
 * touching user meta, so orphaned keys can still be there.
 *
 * @param \WP_User $user User.
 * @return array{legacy: string[], roles: string[], individual: array<string, bool>}
 */
function split_user_caps( \WP_User $user ): array {
	$legacy_roles = get_legacy_roles();
	$legacy       = [];
	$roles        = [];
	$individual   = [];

	foreach ( (array) $user->caps as $key => $granted ) {
		$key = (string) $key;
		if ( in_array( $key, $legacy_roles, true ) ) {
			$legacy[] = $key;
		} elseif ( wp_roles()->is_role( $key ) ) {
			$roles[] = $key;
		} else {
			$individual[ $key ] = (bool) $granted;
		}
	}

	// Keep map order (manager first) for the legacy roles.
	$legacy = array_values( array_intersect( $legacy_roles, $legacy ) );

	return [
		'legacy'     => $legacy,
		'roles'      => $roles,
		'individual' => $individual,
	];
}

/**
 * Plan the change for every user holding a legacy role. Read only.
 *
 * Each item:
 * - ID, user_login
 * - legacy:      legacy role keys the user holds
 * - roles:       the user's other registered roles (always kept)
 * - add_role:    role to add, or '' when nothing is added
 * - action:      'add' (add_role, then drop legacy), 'remove' (other roles
 *                already cover suite content, drop legacy only) or
 *                'blocked' (no target role registered, nothing changes)
 * - roles_after: roles the user ends with
 *
 * @return array<int, array<string, mixed>>
 */
function plan(): array {
	$users = get_users(
		[
			'role__in' => get_legacy_roles(),
			'orderby'  => 'ID',
			'order'    => 'ASC',
		]
	);

	$plan = [];
	foreach ( $users as $user ) {
		$split = split_user_caps( $user );
		if ( empty( $split['legacy'] ) ) {
			// role__in is a LIKE match on the serialised meta; confirm the key.
			continue;
		}

		// Someone with no other role always gets one, even if individual
		// caps happen to cover suite content: no role is the bug this fixes.
		$add_role = '';
		if ( ! empty( $split['roles'] ) && covers_suite_caps( $split['roles'], $split['individual'] ) ) {
			$action = 'remove';
		} else {
			$target = resolve_target( $split['legacy'][0] );
			if ( '' === $target ) {
				$action = 'blocked';
			} elseif ( in_array( $target, $split['roles'], true ) ) {
				$action = 'remove';
			} else {
				$action   = 'add';
				$add_role = $target;
			}
		}

		if ( 'blocked' === $action ) {
			$roles_after = array_merge( $split['roles'], $split['legacy'] );
		} else {
			$roles_after = '' === $add_role ? $split['roles'] : array_merge( $split['roles'], [ $add_role ] );
		}

		$plan[] = [
			'ID'          => (int) $user->ID,
			'user_login'  => (string) $user->user_login,
			'legacy'      => $split['legacy'],
			'roles'       => $split['roles'],
			'add_role'    => $add_role,
			'action'      => $action,
			'roles_after' => $roles_after,
		];
	}

	return $plan;
}

/**
 * Apply the plan: add the 2.x role, then drop the legacy role keys.
 *
 * Safe to run more than once. A user already moved no longer holds a legacy
 * key, so a second run finds nobody and changes nothing.
 *
 * @return bool True when every legacy-role user was handled, false when at
 *              least one was blocked (the caller then keeps the legacy roles
 *              registered so nobody loses access).
 */
function reassign_users(): bool {
	$complete = true;

	foreach ( plan() as $item ) {
		if ( 'blocked' === $item['action'] ) {
			$complete = false;
			debug_log(
				sprintf(
					'user #%d keeps %s: no role to move them to (%s is not registered).',
					$item['ID'],
					implode( ', ', $item['legacy'] ),
					FALLBACK_ROLE
				)
			);
			continue;
		}

		$user = get_userdata( $item['ID'] );
		if ( ! $user ) {
			continue;
		}

		// Add first, so the user is never without a role.
		if ( '' !== $item['add_role'] ) {
			$user->add_role( $item['add_role'] );
		}

		foreach ( $item['legacy'] as $legacy ) {
			if ( in_array( $legacy, $user->roles, true ) ) {
				$user->remove_role( $legacy );
			} else {
				// Orphaned key from a role that is no longer registered.
				// remove_role() ignores those, remove_cap() clears the key.
				$user->remove_cap( $legacy );
			}
		}

		debug_log(
			sprintf(
				'user #%d: removed %s%s; roles now: %s.',
				$item['ID'],
				implode( ', ', $item['legacy'] ),
				'' !== $item['add_role'] ? ', added ' . $item['add_role'] : ' (other roles already cover suite content)',
				implode( ', ', $item['roles_after'] )
			)
		);
	}

	return $complete;
}

/**
 * Log to the PHP error log, only when WP_DEBUG is on.
 *
 * @param string $message Message.
 */
function debug_log( string $message ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'HK Funeral Suite roles: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
