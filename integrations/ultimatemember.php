<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;
class Ultimatemember extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'ultimatemember';
	}

	public static function get_name(): string {
		return 'Ultimate Member';
	}

	public static function get_icon(): string {
		return 'ultimatemember.svg';
	}

	/** @inheritDoc */
	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/ultimate-member/',
			'action'  => 'https://zaplane.app/docs/action-ultimate-member/',
		];
	}

	public static function get_triggers(): array {
		return [
			'user_login' => [
				'label' => 'User Login',
				'hook'  => 'wp_login'
			],
			'user_registration' => [
				'label' => 'User Registration',
				'hook'  => 'um_registration_complete'
			],
			'inactive_user' => [
				'label' => 'Inactive User',
				'hook'  => 'um_after_user_is_inactive'
			],
			'change_user_role' => [
				'label' => 'Change User Role',
				'hook'  => 'set_user_role'
			],
		];
	}

	private static function get_user_data( $user_id ) {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return [];
		}

		return [
			'user_id'      => (string) $user_id,
			'first_name'   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'last_name', true ),
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'nickname'     => $user->nickname,
			'avatar_url'   => get_avatar_url( $user_id ),
			'display_name' => $user->display_name,
			'user_roles'   => $user->roles,
			'role'         => $user->roles[0] ?? '',
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'user_login':
				$username = $args[0] ?? '';
				$user     = $args[1] ?? null;

				if ( ! $user || ! isset( $user->ID ) ) {
					return false;
				}

				$data = self::get_user_data( $user->ID );
				$data['form_username'] = $username;

				return [
					'success' => true,
					'data'    => $data,
				];

			case 'user_registration':
				$user_id = $args[0] ?? 0;
				$um_args = $args[1] ?? [];

				if ( ! $user_id ) {
					return false;
				}

				$data = self::get_user_data( $user_id );

				if ( ! empty( $um_args['submitted'] ) ) {
					$data['form_data'] = $um_args['submitted'];
				}

				return [
					'success' => true,
					'data'    => $data,
				];

			case 'inactive_user':
				$user_id = $args[0] ?? 0;

				if ( ! $user_id ) {
					return false;
				}

				$data = self::get_user_data( $user_id );
				$data['status'] = 'inactive';

				return [
					'success' => true,
					'data'    => $data,
				];

			case 'change_user_role':
				$user_id = $args[0] ?? 0;
				$new_role = $args[1] ?? '';

				if ( ! $user_id ) {
					return false;
				}

				$data = self::get_user_data( $user_id );
				$data['role'] = $new_role;

				return [
					'success'   => true,
					'data'   => $data,
				];

		}//end switch
		return false;
	}

	public static function get_trigger_sample_output( string $event ): array {
		$user = [
			'user_id'      => '1',
			'first_name'   => 'John',
			'last_name'    => 'Doe',
			'user_login'   => 'johndoe',
			'user_email'   => 'john@example.com',
			'nickname'     => 'johndoe',
			'avatar_url'   => 'https://example.com/wp-content/uploads/avatar.png',
			'display_name' => 'John Doe',
			'user_roles'   => [ 'subscriber' ],
			'role'         => 'subscriber',
		];

		$samples = [
			'user_login' => [
				'success' => true,
				'data'    => array_merge(
					$user,
					[ 'form_username' => 'johndoe' ]
				),
			],
			'user_registration' => [
				'success' => true,
				'data'    => array_merge(
					$user,
					[
						'form_data' => [
							'first_name' => 'John',
							'last_name'  => 'Doe',
							'user_email' => 'john@example.com',
						],
					]
				),
			],
			'inactive_user' => [
				'success' => true,
				'data'    => array_merge(
					$user,
					[ 'status' => 'inactive' ]
				),
			],
			'change_user_role' => [
				'success' => true,
				'data'    => array_merge(
					$user,
					[ 'role' => 'editor' ]
				),
			],
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		// Prefix / keyword fallbacks so no trigger returns [].
		if ( false !== strpos( $event, 'role' ) ) {
			return $samples['change_user_role'];
		}
		if ( false !== strpos( $event, 'registration' ) ) {
			return $samples['user_registration'];
		}
		if ( false !== strpos( $event, 'inactive' ) ) {
			return $samples['inactive_user'];
		}

		// Catch-all: always non-empty.
		return [
			'success' => true,
			'data'    => $user,
		];
	}

	public static function get_actions(): array {
		return [
			'um_set_user_role' => [ 'label' => 'Change User Role' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'um_set_user_role' => [
				[
					'key'     => 'user_id',
					'label'   => 'User',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'ultimatemember',
						'query'       => 'user_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true,
				],
				[
					'key'     => 'role',
					'label'   => 'Role',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'ultimatemember',
						'query'       => 'user_role_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true,
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];

		switch ( $node['data']['event'] ?? '' ) {

			case 'um_set_user_role':
				$role = $config['role'] ?? '';
				$user_id = $config['user_id'] ?? $input['user_id'] ?? 0;

				if ( $user_id && $role ) {
					$user = new \WP_User( $user_id );
					$user->set_role( $role );
				}

				return static::success([
					'user_id' => $user_id,
					'role'    => $role,
				]);

		}
		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'user_query'      => [ self::class, 'query_user' ],
			'user_role_query' => [ self::class, 'query_user_role' ],
		];
	}

	public static function query_user( $query ) {
		$all_user = [];
		$users   = get_users();

		foreach ( $users as $user ) {
			$all_user[] = [
				'value' => $user->ID,
				'label' => $user->display_name . ' (' . $user->user_email . ')',
			];
		}

		return $all_user;
	}

	public static function query_user_role( $query ) {
		$all_role = [];

		foreach ( wp_roles()->roles as $role_key => $role ) {
			$all_role[] = [
				'value' => $role_key,
				'label' => $role['name'],
			];
		}

		return $all_role;
	}
}
