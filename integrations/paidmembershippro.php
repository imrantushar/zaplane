<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;

class Paidmembershippro extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'paidmembershippro';
	}

	public static function get_name(): string {
		return 'Paid Memberships Pro';
	}

	public static function get_icon(): string {
		return 'paidmembershippro.svg';
	}

	public static function get_triggers(): array {
		return [
			'admin_assigns_membership' => [
				'label'         => 'Admin Assigns a Membership Level to a User',
				'hook'          => 'pmpro_after_change_membership_level',
			],
			'user_cancels_membership' => [
				'label'         => 'User Cancels a Membership',
				'hook'          => 'pmpro_after_change_membership_level',
			],
			'user_purchases_membership' => [
				'label'         => 'User Purchases a Membership',
				'hook'          => 'pmpro_after_checkout',
			],
			'user_membership_expires' => [
				'label'         => 'User Membership Expires',
				'hook'          => 'pmpro_membership_post_membership_expiry',
			],
			'membership_level_changed' => [
				'label'         => 'Any Membership Level Changed',
				'hook'          => 'pmpro_after_all_membership_level_changes',
			],
			'user_renews_expired_membership' => [
				'label'         => 'User Renews an Expired Membership',
				'hook'          => 'pmpro_before_change_membership_level',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$membership_level_field = [
			'key'      => 'membership_id',
			'label'    => 'Membership Level',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'paidmembershippro',
				'query'       => 'membership_level_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => true,
		];

		$label_value = [
			'admin_assigns_membership',
			'user_cancels_membership',
			'user_purchases_membership',
			'user_membership_expires'

		];

		if ( in_array( $trigger, $label_value, true ) ) {
			return [
				$membership_level_field,
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {

			case 'admin_assigns_membership':
				$level_id = absint( $args[0] ?? 0 );
				$user_id  = absint( $args[1] ?? 0 );

				if ( empty( $level_id ) || empty( $user_id ) ) {
					return false;
				}

				return [
					'success' => true,
					'data'    => array_merge(
						self::get_user_data( $user_id ),
						self::get_level_data( $level_id ),
						[ 'membership_id' => $level_id ]
					),
				];

			case 'user_cancels_membership':
				$level_id     = absint( $args[0] ?? -1 );
				$user_id      = absint( $args[1] ?? 0 );
				$cancel_level = absint( $args[2] ?? 0 );

				if ( 0 !== $level_id || empty( $user_id ) ) {
					return false;
				}

				return [
					'success' => true,
					'data'    => array_merge(
						self::get_user_data( $user_id ),
						self::get_level_data( $cancel_level ),
						[ 'membership_id' => $cancel_level ]
					),
				];

			case 'user_purchases_membership':
				$user_id = absint( $args[0] ?? 0 );
				$morder  = $args[1] ?? null;

				if ( empty( $user_id ) || ! is_object( $morder ) ) {
					return false;
				}

				if ( ! method_exists( $morder, 'getMembershipLevel' ) ) {
					return false;
				}

				if ( method_exists( $morder, 'getUser' ) ) {
					$order_user = $morder->getUser();
					$user_id    = absint( $order_user->ID ?? $user_id );
				}

				$membership    = $morder->getMembershipLevel();
				$membership_id = absint( $membership->id ?? 0 );

				return [
					'success' => true,
					'data'    => array_merge(
						self::get_user_data( $user_id ),
						[
							'membership_id' => $membership_id,
							'membership'    => (array) $membership,
						]
					),
				];

			case 'user_membership_expires':
				$user_id       = absint( $args[0] ?? 0 );
				$membership_id = absint( $args[1] ?? 0 );

				if ( empty( $user_id ) || empty( $membership_id ) ) {
					return false;
				}

				return [
					'success' => true,
					'data'    => array_merge(
						self::get_user_data( $user_id ),
						self::get_level_data( $membership_id ),
						[ 'membership_id' => $membership_id ]
					),
				];

			case 'membership_level_changed':
				$old_level_data = $args[0] ?? [];

				if ( empty( $old_level_data )
					|| ! function_exists( 'pmpro_getMembershipLevelsForUser' )
				) {
					return false;
				}

				$user_id = absint( key( $old_level_data ) );

				return [
					'success' => true,
					'data'    => array_merge(
						self::get_user_data( $user_id ),
						[
							'old_level_data' => $old_level_data[ $user_id ],
							'new_level_data' => pmpro_getMembershipLevelsForUser( $user_id ),
						]
					),
				];

			case 'user_renews_expired_membership':
				$level_id   = absint( $args[0] ?? 0 );
				$user_id    = absint( $args[1] ?? 0 );
				$old_levels = $args[2] ?? [];

				if ( empty( $level_id ) || empty( $user_id ) ) {
					return false;
				}

				$today             = strtotime( current_time( 'mysql' ) );
				$expired_level_ids = [];

				foreach ( $old_levels as $old_level ) {
					if ( ! empty( $old_level->enddate )
						&& ( $old_level->enddate - $today ) <= 0
						&& property_exists( $old_level, 'ID' )
					) {
						$expired_level_ids[] = absint( $old_level->ID );
					}
				}

				if ( ! in_array( $level_id, $expired_level_ids, true ) ) {
					return false;
				}

				return [
					'success' => true,
					'data'    => array_merge(
						self::get_user_data( $user_id ),
						self::get_level_data( $level_id ),
						[ 'membership_id' => $level_id ]
					),
				];

		}//end switch

		return false;
	}

	public static function get_trigger_sample_output( string $event ): array {

		$user_data = [
			'user_id'      => '42',
			'first_name'   => 'Jane',
			'last_name'    => 'Doe',
			'user_login'   => 'janedoe',
			'user_email'   => 'jane@example.com',
			'display_name' => 'Jane Doe',
			'nickname'     => 'janedoe',
			'avatar_url'   => 'https://example.com/avatar.png',
			'user_roles'   => [ 'subscriber' ],
			'role'         => 'subscriber',
		];

		$level_data = [
			'id'                => '2',
			'name'              => 'Gold',
			'description'       => 'Gold membership level',
			'confirmation'      => '',
			'initial_payment'   => '99.00',
			'billing_amount'    => '29.00',
			'cycle_number'      => '1',
			'cycle_period'      => 'Month',
			'billing_limit'     => '0',
			'trial_amount'      => '0.00',
			'trial_limit'       => '0',
			'allow_signups'     => '1',
			'expiration_number' => '1',
			'expiration_period' => 'Year',
		];

		$level_object = [
			'id'                => 2,
			'name'              => 'Gold',
			'description'       => 'Gold membership level',
			'initial_payment'   => '99.00',
			'billing_amount'    => '29.00',
			'cycle_number'      => 1,
			'cycle_period'      => 'Month',
			'billing_limit'     => 0,
			'trial_amount'      => '0.00',
			'trial_limit'       => 0,
			'expiration_number' => 1,
			'expiration_period' => 'Year',
			'startdate'         => '2026-07-09 10:15:00',
			'enddate'           => '2027-07-09 10:15:00',
		];

		$samples = [
			'admin_assigns_membership' => [
				'success' => true,
				'data'    => array_merge(
					$user_data,
					$level_data,
					[ 'membership_id' => 2 ]
				),
			],
			'user_cancels_membership' => [
				'success' => true,
				'data'    => array_merge(
					$user_data,
					$level_data,
					[ 'membership_id' => 2 ]
				),
			],
			'user_purchases_membership' => [
				'success' => true,
				'data'    => array_merge(
					$user_data,
					[
						'membership_id' => 2,
						'membership'    => $level_object,
					]
				),
			],
			'user_membership_expires' => [
				'success' => true,
				'data'    => array_merge(
					$user_data,
					$level_data,
					[ 'membership_id' => 2 ]
				),
			],
			'membership_level_changed' => [
				'success' => true,
				'data'    => array_merge(
					$user_data,
					[
						'old_level_data' => [ $level_object ],
						'new_level_data' => [
							array_merge( $level_object, [
								'id' => 3,
								'name' => 'Platinum'
							] ),
						],
					]
				),
			],
			'user_renews_expired_membership' => [
				'success' => true,
				'data'    => array_merge(
					$user_data,
					$level_data,
					[ 'membership_id' => 2 ]
				),
			],
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( false !== strpos( $event, 'purchase' ) ) {
			return $samples['user_purchases_membership'];
		}

		if ( false !== strpos( $event, 'level_changed' ) ) {
			return $samples['membership_level_changed'];
		}

		return [
			'success' => true,
			'data'    => array_merge(
				$user_data,
				$level_data,
				[ 'membership_id' => 2 ]
			),
		];
	}

	public static function get_actions(): array {
		return [
			'get_all_membership_levels' => [
				'label' => 'Get All Membership Levels'
			],
			'get_membership_level' => [
				'label' => 'Get a Membership Level by ID'
			],
			'add_user_to_membership_level' => [
				'label' => 'Add User to a Membership Level'
			],
			'remove_user_from_membership_level' => [
				'label' => 'Remove User from a Membership Level'
			],
			'list_members_by_membership_level'  => [
				'label' => 'List Members by Membership Level'
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {

		$membership_level_field = [
			'key'      => 'membership_id',
			'label'    => 'Membership Level',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'paidmembershippro',
				'query'       => 'membership_level_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => true,
		];

		$user_email_field = [
			'key'      => 'user_email',
			'label'    => 'User Email',
			'type'     => 'email',
			'required' => true,
		];

		$schemas = [

			'get_membership_level' => [
				[
					'key'      => 'level_id',
					'label'    => 'Membership Level ID',
					'type'     => 'number',
					'required' => true,
				],
			],

			'add_user_to_membership_level' => [
				$user_email_field,
				$membership_level_field,
			],

			'remove_user_from_membership_level' => [
				$user_email_field,
				array_merge( $membership_level_field, [ 'required' => true ] ),
			],

			'list_members_by_membership_level' => [
				array_merge( $membership_level_field, [
					'required'    => false,
					'placeholder' => 'Leave empty to list all active members',
				] ),
			],

		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		global $wpdb;

		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		switch ( $event ) {

			case 'get_all_membership_levels':
				$levels = $wpdb->get_results(
					"SELECT * FROM {$wpdb->pmpro_membership_levels} ORDER BY id ASC"
				);

				if ( $wpdb->last_error ) {
					return static::error( 'Database error: ' . $wpdb->last_error );
				}

				return static::success( [
					'total'  => count( $levels ),
					'levels' => $levels,
				] );

			case 'get_membership_level':
				$level_id = absint( $config['level_id'] ?? $input['level_id'] ?? 0 );

				if ( empty( $level_id ) ) {
					return static::error( 'A valid level_id is required.' );
				}

				$level = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->pmpro_membership_levels} WHERE id = %d LIMIT 1",
						$level_id
					)
				);

				if ( empty( $level ) ) {
					return static::error( 'Membership level not found.' );
				}

				return static::success( [ 'level' => $level ] );

			case 'add_user_to_membership_level':
				$email         = sanitize_email( $config['user_email'] ?? $input['user_email'] ?? '' );
				$membership_id = absint( $config['membership_id'] ?? $input['membership_id'] ?? 0 );

				if ( empty( $email ) || empty( $membership_id ) ) {
					return static::error( 'user_email and membership_id are both required.' );
				}

				if ( ! function_exists( 'pmpro_getMembershipLevelForUser' )
					|| ! function_exists( 'pmpro_changeMembershipLevel' )
				) {
					return static::error( 'Paid Memberships Pro plugin is not active.' );
				}

				$user = get_user_by( 'email', $email );

				if ( ! $user ) {
					return static::error( "No user found with the e-mail address \"{$email}\"." );
				}

				$user_id       = absint( $user->ID );
				$current_level = pmpro_getMembershipLevelForUser( $user_id );

				if ( isset( $current_level->ID )
					&& absint( $current_level->ID ) === $membership_id
				) {
					return static::error( 'User is already a member of this level.' );
				}

				$level = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->pmpro_membership_levels} WHERE id = %d LIMIT 1",
						$membership_id
					)
				);

				if ( empty( $level ) ) {
					return static::error( 'Membership level not found.' );
				}

				if ( ! empty( $level->expiration_number ) && ! empty( $level->expiration_period ) ) {

					$start_date = current_time( 'mysql' );
					$end_date   = date_i18n(
						'Y-m-d',
						strtotime( "+{$level->expiration_number} {$level->expiration_period}" )
					);

					$level_args = [
						'user_id'         => $user_id,
						'membership_id'   => $level->id,
						'code_id'         => 0,
						'initial_payment' => 0,
						'billing_amount'  => 0,
						'cycle_number'    => 0,
						'cycle_period'    => 0,
						'billing_limit'   => 0,
						'trial_amount'    => 0,
						'trial_limit'     => 0,
						'startdate'       => $start_date,
						'enddate'         => $end_date,
					];

					$result = pmpro_changeMembershipLevel( $level_args, $user_id );

				} else {
					$result = pmpro_changeMembershipLevel( $membership_id, $user_id );
				}//end if

				if ( empty( $result ) ) {
					return static::error( 'Failed to add user to the membership level.' );
				}

				return static::success( [
					'message'       => 'User successfully added to the membership level.',
					'user_id'       => $user_id,
					'user_email'    => $email,
					'membership_id' => $membership_id,
				] );

			case 'remove_user_from_membership_level':
				$email         = sanitize_email( $config['user_email'] ?? $input['user_email'] ?? '' );
				$membership_id = absint( $config['membership_id'] ?? $input['membership_id'] ?? 0 );

				if ( empty( $email ) || empty( $membership_id ) ) {
					return static::error( 'user_email and membership_id are both required.' );
				}

				if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' )
					|| ! function_exists( 'pmpro_cancelMembershipLevel' )
				) {
					return static::error( 'Paid Memberships Pro plugin is not active.' );
				}

				$user = get_user_by( 'email', $email );

				if ( ! $user ) {
					return static::error( "No user found with the e-mail address \"{$email}\"." );
				}

				$user_id     = absint( $user->ID );
				$user_levels = wp_list_pluck(
					(array) pmpro_getMembershipLevelsForUser( $user_id ),
					'ID'
				);
                // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				if ( ! in_array( $membership_id, $user_levels ) ) {
					return static::error( 'User does not belong to the specified membership level.' );
				}

				$result = pmpro_cancelMembershipLevel( $membership_id, $user_id );

				if ( empty( $result ) ) {
					return static::error( 'Failed to remove user from the membership level.' );
				}

				return static::success( [
					'message'       => 'User successfully removed from the membership level.',
					'user_id'       => $user_id,
					'user_email'    => $email,
					'membership_id' => $membership_id,
				] );

			case 'list_members_by_membership_level':
				$membership_id = isset( $config['membership_id'] )
					? absint( $config['membership_id'] )
					: null;

				$base_sql = "
					SELECT   mu.user_id,
					         mu.membership_id,
					         ml.name AS membership_name
					FROM     {$wpdb->prefix}pmpro_memberships_users AS mu
					LEFT JOIN {$wpdb->prefix}pmpro_membership_levels AS ml
					       ON mu.membership_id = ml.id
					WHERE    mu.status = 'active'
				";

				if ( $membership_id ) {
					$rows = $wpdb->get_results(
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$wpdb->prepare( $base_sql . ' AND mu.membership_id = %d', $membership_id )
					);
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$rows = $wpdb->get_results( $base_sql );
				}

				if ( empty( $rows ) ) {
					return static::success( [
						'message' => 'No active members found.',
						'total'   => 0,
						'members' => [],
					] );
				}

				$members = array_values(
					array_filter(
						array_map(
							static function ( $row ) {
								$user = get_userdata( (int) $row->user_id );

								if ( ! $user ) {
									return null;
								}

								return [
									'user_id'         => $user->ID,
									'display_name'    => $user->display_name,
									'user_email'      => $user->user_email,
									'membership_id'   => (int) $row->membership_id,
									'membership_name' => $row->membership_name,
								];
							},
							$rows
						)
					)
				);

				return static::success( [
					'total'   => count( $members ),
					'members' => $members,
				] );

		}//end switch

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'membership_level_query' => [ self::class, 'query_membership_levels' ],
		];
	}

	public static function query_membership_levels( $query ): array {
		global $wpdb;

		$levels = $wpdb->get_results(
			"SELECT id, name FROM {$wpdb->pmpro_membership_levels} ORDER BY id ASC"
		);

		$options = [
			[
				'label' => 'Any MemberShip Level',
				'value' => 'any'
			],
		];

		foreach ( (array) $levels as $level ) {
			$options[] = [
				'value' => (int) $level->id,
				'label' => $level->name,
			];
		}

		return $options;
	}

	private static function get_user_data( int $user_id ): array {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return [ 'user_id' => $user_id ];
		}

		return [
			'user_id'      => (string) $user_id,
			'first_name'   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'last_name', true ),
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'nickname'     => $user->nickname,
			'avatar_url'   => get_avatar_url( $user_id ),
			'user_roles'   => $user->roles,
			'role'         => $user->roles[0] ?? '',
		];
	}

	private static function get_level_data( int $level_id ): array {
		global $wpdb;

		$level = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->pmpro_membership_levels} WHERE id = %d LIMIT 1",
				$level_id
			),
			ARRAY_A
		);

		return $level ?: [ 'level_id' => $level_id ];
	}
}
