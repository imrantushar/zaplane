<?php

namespace Zaplane\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a remote party may never reach.
 *
 * The actions below administer the site itself — plugins, users, roles,
 * capabilities, options, network sites, post types and taxonomies. A site
 * owner can still use every one of them in a workflow that starts from
 * something happening on this site (a form submitted, an order placed, a post
 * published, a schedule, a manual run). What they cannot do is hand them to a
 * party outside the site:
 *
 * - An MCP client cannot see them, put them in a graph, or read, edit,
 *   activate, test or run a workflow that contains one, even a workflow the
 *   owner built.
 * - A workflow started by another service — a provider webhook
 *   (zaplane/v1/incoming/...), a Catch Webhook URL (zaplane/v1/hook/...), or a
 *   trigger that polls or connects to an outside account — cannot run one. The
 *   dashboard refuses to activate such a workflow, and the step runner refuses
 *   the step if one is there anyway (imported, or saved while live).
 * - An AI Agent is never given one as a tool, because what the model asks for
 *   is shaped by text from outside the site.
 *
 * The `zaplane_mcp_restricted_actions` and `zaplane_remote_trigger_apps`
 * filters can only add to these lists.
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
		'memberpress'         => [
			// Creates a WordPress user.
			'create_member',
		],
		'buddyboss'           => [
			// Suspends or unsuspends any account, administrators included.
			'update_user_status',
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
	 * Whether this app's triggers are fired by another service rather than by
	 * something happening on this site.
	 *
	 * The Catch Webhook trigger, and any integration that receives provider
	 * webhooks, polls an outside API or signs in to an outside account.
	 */
	public static function is_remote_trigger_app( string $app ): bool {
		$app = strtolower( $app );

		if ( '' === $app ) {
			return false;
		}

		if ( 'webhook' === $app ) {
			return true;
		}

		$extra = apply_filters( 'zaplane_remote_trigger_apps', [] );
		if ( is_array( $extra ) && in_array( $app, array_map( 'strval', $extra ), true ) ) {
			return true;
		}

		if ( ! class_exists( '\\Zaplane\\Framework\\Core\\IntegrationLoader' ) ) {
			return false;
		}

		$integration = \Zaplane\Framework\Core\IntegrationLoader::get( $app );

		if ( ! $integration ) {
			return false;
		}

		return $integration::supports_webhook() || $integration::supports_polling() || $integration::requires_connection();
	}

	/**
	 * The "app/event" of every trigger in a graph that another service fires.
	 *
	 * @param mixed $graph
	 * @return array<int,string>
	 */
	public static function remote_triggers( $graph ): array {
		$found = [];

		foreach ( self::nodes( $graph ) as $node ) {
			if ( 'trigger' !== ( $node['type'] ?? '' ) ) {
				continue;
			}

			$app = (string) ( $node['data']['app'] ?? '' );
			if ( self::is_remote_trigger_app( $app ) ) {
				$found[] = $app . '/' . (string) ( $node['data']['event'] ?? '' );
			}
		}

		return array_values( array_unique( $found ) );
	}

	/**
	 * Restricted actions in a graph that another service can start.
	 *
	 * Empty when the graph has no remote trigger, or no restricted action.
	 *
	 * @param mixed $graph
	 * @return array<int,string>
	 */
	public static function remote_violations( $graph ): array {
		if ( empty( self::remote_triggers( $graph ) ) ) {
			return [];
		}

		return self::violations( $graph );
	}

	/**
	 * Explains why a remotely-started workflow may not use these actions.
	 *
	 * @param array<int,string> $actions
	 */
	public static function remote_message( array $actions ): string {
		return sprintf(
			/* translators: %s: comma-separated list of actions, e.g. "wordpress/create_user" */
			__( '%s administers this site (plugins, users, roles, capabilities or settings), so it cannot run in a workflow that another service starts — a webhook, or a trigger from a connected outside account. Start this workflow from something that happens on this site instead.', 'zaplane' ),
			implode( ', ', $actions )
		);
	}

	/**
	 * @param mixed $graph
	 * @return array<int,array<string,mixed>>
	 */
	private static function nodes( $graph ): array {
		if ( ! is_array( $graph ) || ! is_array( $graph['nodes'] ?? null ) ) {
			return [];
		}

		return array_values( array_filter( $graph['nodes'], 'is_array' ) );
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

		// A trigger only listens. "User deleted" or "Plugin deactivated" share
		// their key with the action that does it, and must not count as one.
		if ( 'trigger' === ( $data['type'] ?? null ) && isset( $data['data'] ) ) {
			return;
		}

		if ( isset( $data['app'], $data['event'] ) && is_string( $data['app'] ) && is_string( $data['event'] )
			&& self::is_restricted( $data['app'], $data['event'] ) ) {
			$found[] = $data['app'] . '/' . $data['event'];
		}

		// Recipe steps name their action as "app.event".
		if ( isset( $data['action'] ) && is_string( $data['action'] ) && false !== strpos( $data['action'], '.' ) ) {
			list( $app, $event ) = explode( '.', $data['action'], 2 );
			if ( self::is_restricted( $app, $event ) ) {
				$found[] = $app . '/' . $event;
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				self::walk( $value, $found, $depth + 1 );
			}
		}
	}
}
