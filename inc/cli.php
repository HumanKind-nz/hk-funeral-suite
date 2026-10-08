<?php
/**
 * WP-CLI — `wp hk-fs roles-audit`.
 *
 * Read-only pre-flight for the role migration in inc/legacy-roles.php. Lists
 * users holding the 1.x funeral_staff / funeral_manager roles and what the
 * upgrade will do to each, users with no role, and users with application
 * passwords (REST accounts such as a Google Sheets price sync) with the
 * roles they will end up with.
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
		'shortdesc' => 'Read-only pre-flight: lists legacy funeral_staff / funeral_manager users and what the 2.x role migration will do.',
		'longdesc'  => "Changes nothing. Shows the legacy roles and their mapping, every user holding one with the planned change, users with no role, and users with application passwords (for example a Google Sheets sync account) with the roles they will end up with.\n\n## EXAMPLES\n\n    wp hk-fs roles-audit\n    wp hk-fs roles-audit --format=json\n    wp --require=/path/to/hk-funeral-suite/inc/cli.php hk-fs roles-audit",
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

	$plan    = LegacyRoles\plan();
	$planned = [];
	foreach ( $plan as $item ) {
		$planned[ $item['ID'] ] = $item;
	}

	$legacy_roles = [];
	foreach ( LegacyRoles\get_legacy_roles() as $legacy ) {
		$holders        = array_filter( $plan, static fn( array $item ): bool => in_array( $legacy, $item['legacy'], true ) );
		$target         = LegacyRoles\resolve_target( $legacy );
		$legacy_roles[] = [
			'role'       => $legacy,
			'registered' => wp_roles()->is_role( $legacy ) ? 'yes' : 'no',
			'users'      => count( $holders ),
			'maps_to'    => '' !== $target ? $target : '(none registered)',
		];
	}

	$legacy_users = [];
	foreach ( $plan as $item ) {
		$legacy_users[] = [
			'ID'             => $item['ID'],
			'user_login'     => $item['user_login'],
			'legacy_roles'   => implode( ', ', $item['legacy'] ),
			'other_roles'    => list_or_none( $item['roles'] ),
			'upgrade_action' => describe_action( $item ),
			'roles_after'    => list_or_none( $item['roles_after'] ),
		];
	}

	$roleless_users     = [];
	$app_password_users = [];
	$users              = get_users(
		[
			'orderby' => 'ID',
			'order'   => 'ASC',
		]
	);
	foreach ( $users as $user ) {
		$id    = (int) $user->ID;
		$item  = $planned[ $id ] ?? null;
		$split = LegacyRoles\split_user_caps( $user );
		$after = null !== $item ? $item['roles_after'] : $split['roles'];

		if ( empty( $user->roles ) ) {
			$roleless_users[] = [
				'ID'               => $id,
				'user_login'       => $user->user_login,
				'raw_capabilities' => list_or_none( array_keys( (array) $user->caps ) ),
				'after_upgrade'    => null !== $item ? list_or_none( $after ) : 'unchanged (no legacy role)',
			];
		}

		$passwords = class_exists( 'WP_Application_Passwords' ) ? \WP_Application_Passwords::get_user_application_passwords( $id ) : [];
		if ( ! empty( $passwords ) ) {
			$last_used            = max( array_map( static fn( array $password ): int => (int) ( $password['last_used'] ?? 0 ), $passwords ) );
			$app_password_users[] = [
				'ID'                     => $id,
				'user_login'             => $user->user_login,
				'app_passwords'          => implode( ', ', array_map( static fn( array $password ): string => (string) $password['name'], $passwords ) ),
				'last_used'              => $last_used > 0 ? gmdate( 'Y-m-d', $last_used ) : 'never',
				'roles_now'              => list_or_none( $user->roles ),
				'roles_after'            => list_or_none( $after ),
				'can_manage_suite_after' => LegacyRoles\covers_suite_caps( $after, $split['individual'] ) ? 'yes' : 'NO',
			];
		}
	}

	$migration = (int) get_option( LegacyRoles\MIGRATION_OPTION, 0 );
	if ( $migration >= LegacyRoles\MIGRATION_VERSION ) {
		$migration_state = 'done (version ' . $migration . ')';
	} elseif ( get_option( 'hk_fs_caps_migrated_v2' ) ) {
		$migration_state = 'the 2.0.0 cleanup ran (roles removed, users not moved); this build re-runs the migration on the next admin page load';
	} else {
		$migration_state = 'not run yet; runs on the first admin page load after updating to 2.x';
	}

	if ( 'json' === $format ) {
		\WP_CLI::line(
			(string) wp_json_encode(
				[
					'migration'          => $migration_state,
					'legacy_roles'       => $legacy_roles,
					'legacy_users'       => $legacy_users,
					'roleless_users'     => $roleless_users,
					'app_password_users' => $app_password_users,
				],
				JSON_PRETTY_PRINT
			)
		);
		return;
	}

	\WP_CLI::line( 'HK Funeral Suite roles audit (read only, nothing is changed)' );
	\WP_CLI::line( 'Role migration: ' . $migration_state );

	print_table( 'Legacy roles', $legacy_roles, [ 'role', 'registered', 'users', 'maps_to' ] );
	print_table( 'Users holding a legacy role', $legacy_users, [ 'ID', 'user_login', 'legacy_roles', 'other_roles', 'upgrade_action', 'roles_after' ] );
	print_table( 'Users with no role now', $roleless_users, [ 'ID', 'user_login', 'raw_capabilities', 'after_upgrade' ] );
	print_table( 'Users with application passwords (REST accounts, e.g. a Sheets sync)', $app_password_users, [ 'ID', 'user_login', 'app_passwords', 'last_used', 'roles_now', 'roles_after', 'can_manage_suite_after' ] );

	$counts = array_count_values( array_column( $plan, 'action' ) );
	\WP_CLI::line( '' );
	if ( ! empty( $counts['blocked'] ) ) {
		\WP_CLI::warning( sprintf( '%d user(s) cannot be moved: no target role is registered. The legacy roles will be kept until that is fixed.', $counts['blocked'] ) );
	}
	\WP_CLI::success(
		sprintf(
			'%d user(s) hold a legacy role: %d get a role added, %d only lose the legacy role, %d blocked. %d user(s) have no role now.',
			count( $plan ),
			$counts['add'] ?? 0,
			$counts['remove'] ?? 0,
			$counts['blocked'] ?? 0,
			count( $roleless_users )
		)
	);
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
 * Plain-English description of a planned change.
 *
 * @param array $item Plan item from LegacyRoles\plan().
 * @return string
 */
function describe_action( array $item ): string {
	$legacy = implode( ', ', $item['legacy'] );

	switch ( $item['action'] ) {
		case 'add':
			return "add {$item['add_role']}, remove {$legacy}";
		case 'remove':
			return "remove {$legacy} only (other roles already cover suite content)";
		default:
			return "none: no target role registered, keeps {$legacy}";
	}
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
