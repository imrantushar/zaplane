<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;
use SureMembers\Inc\Access_Groups;
use SureMembers\Inc\Access;

class Suremembers extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'suremembers';
	}

	public static function get_name(): string {
		return 'SureMembers';
	}

	public static function get_icon(): string {
		return 'suremembers.svg';
	}

	public static function get_triggers(): array {
		return [
			'access_group' => [
				'label' => 'User Added To Access Group',
				'hook'  => 'suremembers_after_access_grant'
			],
			'remove_group' => [
				'label' => 'User Removed From Access Group',
				'hook'  => 'suremembers_after_access_revoke'
			],
			'updated_group' => [
				'label' => 'Access Group Updated',
				'hook'  => 'suremembers_after_submit_form'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'access_group', 'remove_group', 'updated_group' ], true ) ) {
			return [
				[
					'key'   => 'member_id',
					'label' => 'Group',
					'type'  => 'select',
					'dynamic' => [
						'integration' => 'suremembers',
						'query'       => 'group_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true
				],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'access_group':
			case 'remove_group':
				$user_id   = $args[0] ?? 0;
				$group_id = $args[1] ?? [];

				if ( ! $user_id || empty( $group_id ) ) {
					return false;
				}

				$group_id       = is_array( $group_id ) ? $group_id : [ $group_id ];
				$selected_group = $node['data']['config']['member_id'] ?? 'any';
				$matched        = false;

				foreach ( $group_id as $group ) {
					if ( 'any' !== $selected_group || (string) $selected_group !== (string) $group ) {
						$matched = true;
						break;
					}
				}

				if ( ! $matched ) {
					return false;
				}

				$user = self::resolve_user_payload( $user_id );
				if ( ! $user ) {
					return false;
				}

				$group_data = get_post( $group_id[0] );
				if ( ! $group_data ) {
					return false;
				}

				return [
					'success' => true,
					'user' => $user,
					'group' => self::resolve_group_payload( $group_data ),
				];

			case 'updated_group':
				$group_id = $args[0] ?? 0;

				if ( ! $group_id ) {
					return false;
				}

				$selected_group = $node['data']['config']['member_id'] ?? 'any';

				if ( 'any' !== $selected_group && (string) $selected_group !== (string) $group_id ) {
					return false;
				}

				$group = get_post( $group_id );

				if ( ! $group ) {
					return false;
				}

				return [
					'success' => true,
					'data' => self::resolve_group_payload( $group ),
				];

		}//end switch
		return false;
	}

	public static function get_actions(): array {
		return [
			'add_user'    => [ 'label' => 'Add User To Access Group' ],
			'remove_user' => [ 'label' => 'Remove User From Access Group' ],
		];
	}

	public static function action_fields(): array {
		return [
			[
				'key'      => 'email',
				'label'    => 'User Email',
				'type'     => 'email',
				'required' => true,
			],
			[
				'key'   => 'member_id',
				'label' => 'Group',
				'type'  => 'select',
				'dynamic' => [
					'integration' => 'suremembers',
					'query'       => 'group_query',
					'select'      => [ 'value', 'label' ],
				],
				'required' => true
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'add_user'    => self::action_fields(),
			'remove_user' => self::action_fields(),
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		switch ( $event ) {

			case 'add_user':
			case 'remove_user':
				$email    = sanitize_email( $config['email'] ?? '' );
				$group_id = $config['member_id'] ?? '';

				if ( empty( $email ) || empty( $group_id ) ) {
					return self::error( __( 'User email and group are required.', 'zaplane' ), $input );
				}

				$user = get_user_by( 'email', $email );

				if ( ! $user ) {
					return self::error( __( 'User not found with the provided email.', 'zaplane' ), $input );
				}

				if ( 'add_user' === $event ) {
					Access::grant( $user->ID, [ $group_id ] );
				} else {
					Access::revoke( $user->ID, [ $group_id ] );
				}

				$user_payload  = self::resolve_user_payload( $user->ID );
				$group_payload = get_post( $group_id ) ? self::resolve_group_payload( get_post( $group_id ) ) : [];

				return self::success( array_merge( $input, [
					'user' => $user_payload,
					'data' => $group_payload,
				] ) );
		}//end switch

		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'group_query'      => [ self::class, 'query_group' ],
		];
	}

	public static function query_group( $query ) {
		$all_group = [
			[
				'value' => 'any',
				'label' => 'Any Group'
			],
		];

		$access_groups = Access_Groups::get_active();

		foreach ( $access_groups as $key => $access_group ) {
			$all_group[] = [
				'value' => $key,
				'label' => $access_group,
			];
		}

		return $all_group;
	}

	public static function resolve_user_payload( int $user_id ): array|false {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		return [
			'user_id'      => $user->ID,
			'first_name'   => $user->first_name ?? '',
			'last_name'    => $user->last_name ?? '',
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'nickname'     => $user->nickname,
			'display_name' => $user->display_name,
			'avatar_url'   => get_avatar_url( $user_id ),
			'user_roles'   => $user->roles,
			'completed_at' => current_time( 'mysql' ),
		];
	}

	public static function resolve_group_payload( $group ): array {
		return [
			'ID'                    => $group->ID,
			'post_author'           => $group->post_author,
			'post_date'             => $group->post_date,
			'post_date_gmt'         => $group->post_date_gmt,
			'post_content'          => $group->post_content,
			'post_title'            => $group->post_title,
			'post_excerpt'          => $group->post_excerpt,
			'post_status'           => $group->post_status,
			'comment_status'        => $group->comment_status,
			'ping_status'           => $group->ping_status,
			'post_password'         => $group->post_password,
			'post_name'             => $group->post_name,
			'to_ping'               => $group->to_ping,
			'pinged'                => $group->pinged,
			'post_modified'         => $group->post_modified,
			'post_modified_gmt'     => $group->post_modified_gmt,
			'post_content_filtered' => $group->post_content_filtered,
			'post_parent'           => $group->post_parent,
			'guid'                  => $group->guid,
			'menu_order'            => $group->menu_order,
			'post_type'             => $group->post_type,
			'post_mime_type'        => $group->post_mime_type,
			'comment_count'         => $group->comment_count,
			'filter'                => 'raw',
		];
	}
}
