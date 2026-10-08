<?php
/**
 * Legacy Roles — keep the 1.x funeral_staff / funeral_manager roles working.
 *
 * 1.x registered two roles, funeral_staff and funeral_manager. They are not
 * this plugin's alone: other plugins grant their own capabilities to them
 * when they exist (a notices plugin adds its post and taxonomy capabilities,
 * for example), and live sites have users who hold one as their only role,
 * or next to a site role. So 2.x never removes these roles, never moves
 * their users to another role, and never takes a capability off them.
 *
 * 2.x suite post types use capability_type 'post' (inc/post-types.php), so
 * what the roles need is the standard post capabilities. Once per VERSION
 * (admin_init, and on activation) the routine below:
 *
 * - Tops up each legacy role that exists with any capability from its 1.4.23
 *   definition that it lacks. Nothing beyond the 1.4.23 definitions is ever
 *   added: no unfiltered_html, no page capabilities.
 * - Re-creates a legacy role that is missing when users still hold it in
 *   their capabilities meta, or when the 2.0.0 cleanup flag
 *   (hk_fs_caps_migrated_v2) shows that build removed it. Those users get
 *   their access back. A role is not created on a site that never had it.
 * - Where the 2.0.0 cleanup ran, gives administrator back the two 1.x suite
 *   capabilities that cleanup took. 2.x does not check them; this keeps a
 *   repaired site the same as one updated straight from 1.x.
 *
 * 1.x gave anyone with manage_funeral_content (both legacy roles) edit and
 * delete rights on every suite post. map_suite_meta_caps() keeps that rule
 * for suite post types only; see there for why it is not done with role
 * capabilities. `wp hk-fs roles-audit` shows what each role and user can
 * edit after the update.
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
 * Option recording which version of the legacy role routine has run.
 */
const OPTION  = 'hk_fs_legacy_roles_version';
const VERSION = 1;

/**
 * Set by the 2.0.0 cleanup, which removed both legacy roles.
 */
const OLD_CLEANUP_FLAG = 'hk_fs_caps_migrated_v2';

/**
 * Capabilities 1.4.23 granted administrator, which the 2.0.0 cleanup removed.
 */
const ADMIN_CAPS = [ 'manage_funeral_content', 'manage_funeral_settings' ];

/**
 * The legacy roles as 1.4.23 defined them
 * (includes/admin/class-capabilities.php, register_custom_roles()).
 *
 * @return array<string, array{name: string, caps: string[]}>
 */
function get_definitions(): array {
	return [
		'funeral_staff'   => [
			'name' => 'Funeral Staff',
			'caps' => [
				'read',
				'edit_posts',
				'delete_posts',
				'publish_posts',
				'upload_files',
				'manage_funeral_content',
			],
		],
		'funeral_manager' => [
			'name' => 'Funeral Manager',
			'caps' => [
				'read',
				'read_private_posts',
				'edit_posts',
				'edit_others_posts',
				'edit_published_posts',
				'publish_posts',
				'delete_posts',
				'delete_others_posts',
				'delete_published_posts',
				'delete_private_posts',
				'edit_private_posts',
				'manage_categories',
				'moderate_comments',
				'upload_files',
				'manage_funeral_content',
				'manage_funeral_settings',
			],
		],
	];
}

/**
 * Legacy role slugs.
 *
 * @return string[]
 */
function get_legacy_roles(): array {
	return array_keys( get_definitions() );
}

/**
 * Whether the routine has run at the current version.
 *
 * @return bool
 */
function is_done(): bool {
	return (int) get_option( OPTION, 0 ) >= VERSION;
}

/**
 * Whether the 2.0.0 cleanup (which removed the legacy roles) ran here.
 *
 * @return bool
 */
function old_cleanup_ran(): bool {
	return (bool) get_option( OLD_CLEANUP_FLAG );
}

/**
 * IDs of users who hold a legacy role in their capabilities meta, whether or
 * not the role is registered. The 2.0.0 cleanup removed the roles without
 * touching user meta, so the keys can outlive the role.
 *
 * @param string $role Legacy role slug.
 * @return int[]
 */
function get_holders( string $role ): array {
	$users = get_users(
		[
			'role__in' => [ $role ],
			'orderby'  => 'ID',
			'order'    => 'ASC',
		]
	);

	$ids = [];
	foreach ( $users as $user ) {
		// role__in is a LIKE match on the serialised meta; confirm the key.
		if ( ! empty( $user->caps[ $role ] ) ) {
			$ids[] = (int) $user->ID;
		}
	}
	return $ids;
}

/**
 * Plan the routine for each legacy role. Read only.
 *
 * Each item:
 * - registered:  the role exists now
 * - holders:     IDs of users holding it (see get_holders())
 * - missing:     1.4.23 capabilities the role lacks (all of them when the
 *                role is missing)
 * - extra:       capabilities on the role beyond its 1.4.23 definition,
 *                added by other plugins or by hand; always kept
 * - action:      'none', 'add_caps' (role exists, top up the missing caps)
 *                or 'recreate' (role missing but still in use)
 * - caps_after:  the role's capabilities after the routine ([] when the
 *                role is missing and stays missing)
 *
 * @return array<string, array<string, mixed>>
 */
function plan(): array {
	$cleanup_ran = old_cleanup_ran();
	$plan        = [];

	foreach ( get_definitions() as $slug => $definition ) {
		$role    = wp_roles()->get_role( $slug );
		$holders = get_holders( $slug );

		if ( $role ) {
			$caps    = (array) $role->capabilities;
			$missing = array_values( array_filter( $definition['caps'], static fn( string $cap ): bool => empty( $caps[ $cap ] ) ) );
			$extra   = array_values( array_diff( array_keys( array_filter( $caps ) ), $definition['caps'] ) );
			$action  = empty( $missing ) ? 'none' : 'add_caps';
			$after   = array_merge( $caps, array_fill_keys( $missing, true ) );
		} else {
			$missing = $definition['caps'];
			$extra   = [];
			$action  = ( ! empty( $holders ) || $cleanup_ran ) ? 'recreate' : 'none';
			$after   = 'recreate' === $action ? array_fill_keys( $definition['caps'], true ) : [];
		}

		$plan[ $slug ] = [
			'role'       => $slug,
			'registered' => (bool) $role,
			'holders'    => $holders,
			'missing'    => $missing,
			'extra'      => $extra,
			'action'     => $action,
			'caps_after' => $after,
		];
	}

	return $plan;
}

/**
 * Administrator capabilities to give back where the 2.0.0 cleanup ran.
 *
 * @return string[]
 */
function admin_caps_to_restore(): array {
	if ( ! old_cleanup_ran() ) {
		return [];
	}
	$admin = wp_roles()->get_role( 'administrator' );
	if ( ! $admin ) {
		return [];
	}
	return array_values( array_filter( ADMIN_CAPS, static fn( string $cap ): bool => empty( $admin->capabilities[ $cap ] ) ) );
}

/**
 * Apply the plan. Only ever adds roles and capabilities.
 *
 * Safe to run more than once: a second run finds nothing missing.
 */
function run(): void {
	$definitions = get_definitions();

	foreach ( plan() as $slug => $item ) {
		if ( 'recreate' === $item['action'] ) {
			add_role( $slug, $definitions[ $slug ]['name'], $item['caps_after'] );
			debug_log( sprintf( 're-created the %s role with its 1.4.23 capabilities (%d user(s) hold it).', $slug, count( $item['holders'] ) ) );
		} elseif ( 'add_caps' === $item['action'] ) {
			$role = get_role( $slug );
			if ( $role ) {
				foreach ( $item['missing'] as $cap ) {
					$role->add_cap( $cap );
				}
				debug_log( sprintf( 'added %s to the %s role.', implode( ', ', $item['missing'] ), $slug ) );
			}
		}
	}

	$restore = admin_caps_to_restore();
	if ( ! empty( $restore ) ) {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $restore as $cap ) {
				$admin->add_cap( $cap );
			}
			debug_log( sprintf( 'gave administrator back %s.', implode( ', ', $restore ) ) );
		}
	}

	// 1.x role-sync flags. Clearing them means a rollback to 1.x re-syncs
	// its roles on the first request.
	delete_transient( 'hk_fs_roles_version' );
	delete_option( 'hk_fs_roles_updated' );

	update_option( OPTION, VERSION );
	delete_option( OLD_CLEANUP_FLAG );
}

/**
 * Every role's capabilities after run(), keyed by role slug. Read only.
 *
 * @param array<string, array<string, mixed>> $plan From plan().
 * @return array<string, array<string, bool>>
 */
function role_caps_after( array $plan ): array {
	$roles = [];
	foreach ( wp_roles()->role_objects as $slug => $role ) {
		$roles[ (string) $slug ] = (array) $role->capabilities;
	}

	foreach ( $plan as $slug => $item ) {
		if ( 'none' === $item['action'] && ! $item['registered'] ) {
			continue;
		}
		$roles[ $slug ] = $item['caps_after'];
	}

	$restore = admin_caps_to_restore();
	if ( ! empty( $restore ) && isset( $roles['administrator'] ) ) {
		$roles['administrator'] = array_merge( $roles['administrator'], array_fill_keys( $restore, true ) );
	}

	return $roles;
}

/**
 * A user's roles and capabilities after run(), built the way
 * WP_User::get_role_caps() builds them: role capabilities first, then the
 * user's own capabilities meta on top. Read only.
 *
 * @param \WP_User                           $user       User.
 * @param array<string, array<string, bool>> $role_caps  From role_caps_after().
 * @return array{roles: string[], caps: array<string, bool>}
 */
function user_after( \WP_User $user, array $role_caps ): array {
	$roles = [];
	$caps  = [];
	foreach ( (array) $user->caps as $key => $granted ) {
		$key = (string) $key;
		if ( isset( $role_caps[ $key ] ) ) {
			$roles[] = $key;
			$caps    = array_merge( $caps, $role_caps[ $key ] );
		}
	}

	return [
		'roles' => $roles,
		'caps'  => array_merge( $caps, (array) $user->caps ),
	];
}

/**
 * Let 1.x role holders edit and delete every suite post, as they could in 1.x.
 *
 * 1.x registered the suite post types with custom capabilities, which turns
 * off meta capability mapping, so edit_post, delete_post and read_post on
 * any suite post (anyone's, published or not) needed only
 * manage_funeral_content. Both legacy roles have it. 2.x uses the standard
 * post capabilities, where someone else's post needs edit_others_posts and
 * a published one edit_published_posts. funeral_staff's 1.4.23 definition
 * has neither, so without this a staff-only user on a site where no other
 * plugin grants them could edit only their own drafts. Adding those two
 * capabilities to the role instead would open every blog post too, so the
 * 1.x rule is kept for suite post types only.
 *
 * Hooked at priority 9, before the catalogue lock (10), which still denies
 * edits to catalogue-managed types. A check core has already denied is left
 * alone.
 *
 * @param string[]   $caps    Primitive capabilities required.
 * @param string     $cap     Meta capability being checked.
 * @param int|string $user_id User ID (untyped: callers vary under strict_types).
 * @param array      $args    Additional args — [0] is the post or its ID.
 * @return string[]
 */
function map_suite_meta_caps( array $caps, string $cap, $user_id, array $args ): array {
	if ( ! in_array( $cap, [ 'edit_post', 'delete_post', 'read_post' ], true ) || empty( $args[0] ) || in_array( 'do_not_allow', $caps, true ) ) {
		return $caps;
	}

	$post = get_post( $args[0] );
	if ( ! $post || 0 !== strpos( $post->post_type, 'hk_fs_' ) ) {
		return $caps;
	}

	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- The 1.x capability, granted to the legacy roles in get_definitions().
	if ( ! user_can( (int) $user_id, 'manage_funeral_content' ) ) {
		return $caps;
	}

	return [ 'manage_funeral_content' ];
}

/**
 * Which suite posts a set of capabilities can edit: the standard post
 * capabilities (capability_type 'post'), or manage_funeral_content through
 * map_suite_meta_caps().
 *
 * @param array<string, bool> $caps Capabilities.
 * @return string 'all', 'own drafts' or 'none'.
 */
function suite_access( array $caps ): string {
	if ( empty( $caps['edit_posts'] ) ) {
		return 'none';
	}
	if ( ! empty( $caps['manage_funeral_content'] ) || ( ! empty( $caps['edit_others_posts'] ) && ! empty( $caps['edit_published_posts'] ) ) ) {
		return 'all';
	}
	return 'own drafts';
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
