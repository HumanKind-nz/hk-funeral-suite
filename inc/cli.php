<?php
/**
 * WP-CLI — `wp hk-fs roles-audit`.
 *
 * Read-only report on the 1.x funeral_staff / funeral_manager roles and what
 * the 2.x legacy role routine in inc/legacy-roles.php will do: whether each
 * role exists, who holds it, which 1.4.23 capabilities it lacks, which
 * capabilities other plugins added (always kept), and which suite posts each
 * role, user and REST account (application password, such as a Google
 * Sheets price sync) can edit after the update. The routine never removes a
 * role, a capability or a user's role.
 *
 * Registers itself when included, so it can also be loaded from a 2.x copy
 * of the plugin against a site still running 1.x, before updating:
 *
 *     wp --require=/path/to/hk-funeral-suite/inc/cli.php hk-fs roles-audit
 *
 * Do not --require it on a site already running 2.x from another path: the
 * functions would be declared twice. There, `wp hk-fs roles-audit` is enough.
 *
 * @package HKFuneralSuite
 */

declare( strict_types=1 );

namespace HKFuneralSuite\CLI;

use HKFuneralSuite\LegacyRoles;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/legacy-roles.php';

\WP_CLI::add_command(
	'hk-fs roles-audit',
	__NAMESPACE__ . '\\roles_audit',
	[
		'shortdesc' => 'Read-only report on the 1.x funeral_staff / funeral_manager roles and what the 2.x update does to them.',
		'longdesc'  => "Changes nothing. Shows each legacy role (exists, users, 1.4.23 capabilities it lacks, what the update will do, which suite posts it can edit afterwards), capabilities other plugins added to those roles (kept), every user holding a legacy role, users with no role, and users with application passwords (for example a Google Sheets sync account). The update only adds: it never removes a role, a capability or a user's role.\n\n## EXAMPLES\n\n    wp hk-fs roles-audit\n    wp hk-fs roles-audit --format=json\n    wp --require=/path/to/hk-funeral-suite/inc/cli.php hk-fs roles-audit",
		'synopsis'  => [
			[
				'type'        => 'assoc',
				'name'        => 'format',
				'description' => 'Output format.',
				'optional'    => true,
				'default'     => 'table',
				'options'     => [ 'table', 'json' ],
			],
		],
	]
);

/**
 * `wp hk-fs roles-audit` callback.
 *
 * @param array $args       Positional arguments (unused).
 * @param array $assoc_args Associative arguments.
 */
function roles_audit( array $args, array $assoc_args ): void {
	$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

	$plan      = LegacyRoles\plan();
	$role_caps = LegacyRoles\role_caps_after( $plan );
	$restore   = LegacyRoles\admin_caps_to_restore();

	$legacy_roles = [];
	$extra_caps   = [];
	foreach ( $plan as $slug => $item ) {
		$exists_after   = 'recreate' === $item['action'] || $item['registered'];
		$legacy_roles[] = [
			'role'               => $slug,
			'exists'             => $item['registered'] ? 'yes' : 'no',
			'users'              => count( $item['holders'] ),
			'missing_v1423_caps' => $item['registered'] ? list_or_none( $item['missing'] ) : '(role missing)',
			'upgrade_action'     => describe_role_action( $item ),
			'suite_posts_after'  => $exists_after ? LegacyRoles\suite_access( $item['caps_after'] ) : '-',
		];
		if ( ! empty( $item['extra'] ) ) {
			$extra_caps[] = [
				'role' => $slug,
				'caps' => implode( ', ', $item['extra'] ),
			];
		}
	}

	$legacy_slugs   = LegacyRoles\get_legacy_roles();
	$legacy_users   = [];
	$roleless_users = [];
	$app_users      = [];
	$users          = get_users(
		[
			'orderby' => 'ID',
			'order'   => 'ASC',
		]
	);
	foreach ( $users as $user ) {
		$id     = (int) $user->ID;
		$after  = LegacyRoles\user_after( $user, $role_caps );
		$legacy = array_values( array_filter( $legacy_slugs, static fn( string $slug ): bool => ! empty( $user->caps[ $slug ] ) ) );

		if ( ! empty( $legacy ) ) {
			$legacy_users[] = [
				'ID'                => $id,
				'user_login'        => $user->user_login,
				'legacy_roles'      => implode( ', ', $legacy ),
				'other_roles'       => list_or_none( array_values( array_diff( $user->roles, $legacy ) ) ),
				'upgrade_action'    => describe_user_action( $legacy, $plan ),
				'suite_posts_now'   => LegacyRoles\suite_access( (array) $user->allcaps ),
				'suite_posts_after' => LegacyRoles\suite_access( $after['caps'] ),
			];
		}

		if ( empty( $user->roles ) ) {
			$roleless_users[] = [
				'ID'               => $id,
				'user_login'       => $user->user_login,
				'raw_capabilities' => list_or_none( array_keys( (array) $user->caps ) ),
				'roles_after'      => list_or_none( $after['roles'] ),
			];
		}

		$passwords = class_exists( 'WP_Application_Passwords' ) ? \WP_Application_Passwords::get_user_application_passwords( $id ) : [];
		if ( ! empty( $passwords ) ) {
			$last_used   = max( array_map( static fn( array $password ): int => (int) ( $password['last_used'] ?? 0 ), $passwords ) );
			$app_users[] = [
				'ID'                => $id,
				'user_login'        => $user->user_login,
				'app_passwords'     => implode( ', ', array_map( static fn( array $password ): string => (string) $password['name'], $passwords ) ),
				'last_used'         => $last_used > 0 ? gmdate( 'Y-m-d', $last_used ) : 'never',
				'roles_now'         => list_or_none( $user->roles ),
				'roles_after'       => list_or_none( $after['roles'] ),
				'suite_posts_after' => LegacyRoles\suite_access( $after['caps'] ),
			];
		}
	}

	$version = (int) get_option( LegacyRoles\OPTION, 0 );
	if ( LegacyRoles\is_done() ) {
		$state = 'done (version ' . $version . '); the plan below is what a re-run would do';
	} elseif ( LegacyRoles\old_cleanup_ran() ) {
		$state = 'the 2.0.0 cleanup ran here (it removed the legacy roles); this build re-creates them on the next admin page load';
	} else {
		$state = 'not run yet; runs on the first admin page load after updating to 2.x';
	}

	if ( 'json' === $format ) {
		\WP_CLI::line(
			(string) wp_json_encode(
				[
					'legacy_role_routine'           => $state,
					'legacy_roles'                  => $legacy_roles,
					'caps_added_by_others'          => $extra_caps,
					'administrator_caps_to_restore' => $restore,
					'legacy_users'                  => $legacy_users,
					'roleless_users'                => $roleless_users,
					'app_password_users'            => $app_users,
				],
				JSON_PRETTY_PRINT
			)
		);
		return;
	}

	\WP_CLI::line( 'HK Funeral Suite roles audit (read only, nothing is changed)' );
	\WP_CLI::line( 'Legacy role routine: ' . $state );
	\WP_CLI::line( 'The update never removes a role, a capability or a user\'s role. "suite_posts" is what the suite post types (capability_type post) allow: all, own drafts or none.' );

	print_table( 'Legacy roles', $legacy_roles, [ 'role', 'exists', 'users', 'missing_v1423_caps', 'upgrade_action', 'suite_posts_after' ] );
	print_table( 'Capabilities other plugins or admins added to legacy roles (kept)', $extra_caps, [ 'role', 'caps' ] );
	if ( ! empty( $restore ) ) {
		\WP_CLI::line( '' );
		\WP_CLI::line( 'Administrator gets back ' . implode( ', ', $restore ) . ' (removed by the 2.0.0 cleanup).' );
	}
	print_table( 'Users holding a legacy role', $legacy_users, [ 'ID', 'user_login', 'legacy_roles', 'other_roles', 'upgrade_action', 'suite_posts_now', 'suite_posts_after' ] );
	print_table( 'Users with no role now', $roleless_users, [ 'ID', 'user_login', 'raw_capabilities', 'roles_after' ] );
	print_table( 'Users with application passwords (REST accounts, e.g. a Sheets sync)', $app_users, [ 'ID', 'user_login', 'app_passwords', 'last_used', 'roles_now', 'roles_after', 'suite_posts_after' ] );

	$counts = array_count_values( array_column( $plan, 'action' ) );
	\WP_CLI::line( '' );
	\WP_CLI::success(
		sprintf(
			'%d legacy role(s): %d re-created, %d topped up, %d unchanged. %d user(s) hold a legacy role. %d user(s) have no role now.',
			count( $plan ),
			$counts['recreate'] ?? 0,
			$counts['add_caps'] ?? 0,
			$counts['none'] ?? 0,
			count( $legacy_users ),
			count( $roleless_users )
		)
	);
}

/**
 * Plain-English description of what the routine does to a legacy role.
 *
 * @param array $item Plan item from LegacyRoles\plan().
 * @return string
 */
function describe_role_action( array $item ): string {
	switch ( $item['action'] ) {
		case 'recreate':
			return 're-create with its 1.4.23 capabilities';
		case 'add_caps':
			return 'add ' . implode( ', ', $item['missing'] );
		default:
			return $item['registered'] ? 'nothing' : 'nothing (not used on this site)';
	}
}

/**
 * Plain-English description of what the routine does for a user.
 *
 * @param string[] $legacy Legacy roles the user holds.
 * @param array    $plan   From LegacyRoles\plan().
 * @return string
 */
function describe_user_action( array $legacy, array $plan ): string {
	$recreated = array_values( array_filter( $legacy, static fn( string $slug ): bool => 'recreate' === ( $plan[ $slug ]['action'] ?? '' ) ) );
	if ( ! empty( $recreated ) ) {
		return 'regains ' . implode( ', ', $recreated ) . ' (role re-created)';
	}
	return 'keeps their roles';
}

/**
 * Print a titled table, or "None." when there are no rows.
 *
 * @param string $title  Section title.
 * @param array  $rows   Rows.
 * @param array  $fields Columns.
 */
function print_table( string $title, array $rows, array $fields ): void {
	\WP_CLI::line( '' );
	\WP_CLI::line( $title . ':' );
	if ( empty( $rows ) ) {
		\WP_CLI::line( '  None.' );
		return;
	}
	\WP_CLI\Utils\format_items( 'table', $rows, $fields );
}

/**
 * Comma-separated list, or "(none)".
 *
 * @param array $items Items.
 * @return string
 */
function list_or_none( array $items ): string {
	return empty( $items ) ? '(none)' : implode( ', ', array_map( 'strval', $items ) );
}
