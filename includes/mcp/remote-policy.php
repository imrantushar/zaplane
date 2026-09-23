<?php

namespace Zaplane\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What an MCP client may never reach, whatever scopes its token holds.
 *
 * The MCP endpoint lets an external AI client build and start workflows. The
 * actions below administer the site itself — plugins, users, roles,
 * capabilities, options, network sites, post types and taxonomies. A site
 * owner can still use every one of them in a workflow they build in the
 * Zaplane dashboard; what they cannot do is hand them to a remote party. So an
 * MCP client cannot see them, put them in a graph, or read, edit, activate,
 * test or run a workflow that contains one, even a workflow the owner built.
 *
 * The `zaplane_mcp_restricted_actions` filter can only add to this list.
 */
class RemotePolicy {

	/**
	 * app slug => action keys.
	 */
	private const RESTRICTED = [
		'wordpress'           => [
			// Plugins.
			'activate_plugin',
			'deactivate_plugin',
			// Users.
			'create_user',
			'update_user',
			'delete_user',
			'update_user_meta',
			'send_password_reset_email',
			'logout_user',
			'activate_user',
			'deactivate_user',
			// Roles and capabilities.
			'create_role',
			'delete_role',
			'add_user_role',
			'remove_user_role',
			'update_user_role',
			'add_role_caps',
			'remove_role_caps',
			'add_user_caps',
			'remove_user_caps',
			// Settings.
			'update_option',
			'add_plugin_theme_option',
			'update_option_advanced',
			'delete_option',
			// Multisite.
			'create_site',
			'delete_site',
			'add_user_to_site',
			'remove_user_from_site',
			// Site structure.
			'register_post_type',
			'unregister_post_type',
			'add_post_type_support',
			'register_taxonomy',
			'unregister_taxonomy',
		],
		'advancecustomfields' => [
			'update_user_field',
			'update_options_field',
		],
		'ultimatemember'      => [
			'um_set_user_role',
		],
		'storeengine'         => [
			// These add and remove WordPress roles.
			'grant_membership',
			'revoke_membership',
		],
		'woocommerce'         => [
			// Creates a WordPress user.
			'create_customer',
		],
	];

	/**
	 * @return array<string,array<int,string>>
	 */
	public static function restricted(): array {
		$list  = self::RESTRICTED;
		$extra = apply_filters( 'zaplane_mcp_restricted_actions', [] );

		if ( is_array( $extra ) ) {
			foreach ( $extra as $app => $events ) {
				if ( ! is_string( $app ) || ! is_array( $events ) ) {
					continue;
				}
				$list[ $app ] = array_values( array_unique( array_merge( $list[ $app ] ?? [], array_map( 'strval', $events ) ) ) );
			}
		}

		return $list;
	}

	public static function is_restricted( string $app, string $event ): bool {
		$list = self::restricted();

		return isset( $list[ $app ] ) && in_array( $event, $list[ $app ], true );
	}

	/**
	 * Every restricted "app/event" found anywhere in a graph or blueprint.
	 *
	 * Walks the whole structure rather than only `nodes`, so a recipe blueprint
	 * holding several workflows is covered by the same check.
	 *
	 * @param mixed $data
	 * @return array<int,string>
	 */
	public static function violations( $data ): array {
		$found = [];
		self::walk( $data, $found, 0 );

		return array_values( array_unique( $found ) );
	}

	/**
	 * @param mixed $data
	 * @throws \InvalidArgumentException When the graph contains a restricted action.
	 */
	public static function assert_allowed( $data ): void {
		$found = self::violations( $data );

		if ( ! empty( $found ) ) {
			throw new \InvalidArgumentException(
				esc_html(
					sprintf(
						'Refused: %s administers this site (plugins, users, roles, capabilities or settings). MCP clients cannot add these actions to a workflow, or read, edit, activate, test or run a workflow that uses them. A site administrator can build that workflow in the Zaplane dashboard.',
						implode( ', ', $found )
					)
				)
			);
		}
	}

	/**
	 * @param mixed             $data
	 * @param array<int,string> $found
	 */
	private static function walk( $data, array &$found, int $depth ): void {
		if ( ! is_array( $data ) || $depth > 12 ) {
			return;
		}

		if ( isset( $data['app'], $data['event'] ) && is_string( $data['app'] ) && is_string( $data['event'] )
			&& self::is_restricted( $data['app'], $data['event'] ) ) {
			$found[] = $data['app'] . '/' . $data['event'];
		}

		// Recipe steps name their capability as "app.event".
		foreach ( [ 'trigger', 'action' ] as $key ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) && false !== strpos( $data[ $key ], '.' ) ) {
				list( $app, $event ) = explode( '.', $data[ $key ], 2 );
				if ( self::is_restricted( $app, $event ) ) {
					$found[] = $app . '/' . $event;
				}
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				self::walk( $value, $found, $depth + 1 );
			}
		}
	}
}
