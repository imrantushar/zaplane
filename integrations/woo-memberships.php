<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooMemberships extends IntegrationBase {

	public static function get_slug(): string {
		return 'woomemberships';
	}

	public static function get_name(): string {
		return 'WooCommerce Memberships';
	}

	public static function get_icon(): string {
		return 'woocommerce-membership.svg';
	}

	public static function get_triggers(): array {
		return [
			'membership_saved' => [
				'label' => 'Membership Saved',
				'hook' => 'wc_memberships_user_membership_saved',
			],
			'membership_created' => [
				'label' => 'Membership Created',
				'hook' => 'wc_memberships_user_membership_created',
			],
			'membership_cancelled' => [
				'label' => 'Membership Cancelled',
				'hook' => 'wc_memberships_cancelled_user_membership',
			],
			'membership_status_changed' => [
				'label' => 'Membership Status Updated',
				'hook' => 'wc_memberships_user_membership_status_changed',
			],
			'membership_note_added' => [
				'label' => 'Membership Note Added',
				'hook' => 'wc_memberships_new_user_membership_note',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_memberships_available() ) {
			return false;
		}

		$event = (string) ( $node['event'] ?? ( $node['data']['event'] ?? ( $node['config']['trigger'] ?? '' ) ) );
		if ( '' === $event ) {
			return false;
		}

		switch ( $event ) {
			case 'membership_saved':
			case 'membership_created':
				$trigger_args = is_array( $args[1] ?? null ) ? $args[1] : [];
				$membership = self::resolve_membership_from_event_args( $args, $trigger_args );
				if ( ! $membership ) {
					return false;
				}

				return self::build_user_membership_payload(
					$membership,
					[
						'event' => $event,
						'is_update' => (bool) ( $trigger_args['is_update'] ?? false ),
					]
				);

			case 'membership_cancelled':
				$membership = self::resolve_user_membership( $args[0] ?? 0 );
				if ( ! $membership ) {
					return false;
				}

				return self::build_user_membership_payload(
					$membership,
					[
						'event' => $event,
					]
				);

			case 'membership_status_changed':
				$membership = self::resolve_user_membership( $args[0] ?? null );
				if ( ! $membership ) {
					return false;
				}

				return self::build_user_membership_payload(
					$membership,
					[
						'old_status' => self::normalize_membership_status( (string) ( $args[1] ?? '' ) ),
						'new_status' => self::normalize_membership_status( (string) ( $args[2] ?? '' ) ),
					]
				);

			case 'membership_note_added':
				$data = is_array( $args[0] ?? null ) ? $args[0] : [];
				$membership = self::resolve_user_membership( $data['user_membership_id'] ?? 0 );
				if ( ! $membership ) {
					return false;
				}

				$note = is_object( $data['note'] ?? null ) ? $data['note'] : null;
				$note_content = '';
				if ( $note ) {
					$note_content = (string) ( $note->comment_content ?? ( $note->content ?? '' ) );
				}

				return self::build_user_membership_payload(
					$membership,
					[
						'note' => $note_content,
						'notify' => (bool) ( $data['notify'] ?? false ),
					]
				);
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_membership' => [ 'label' => 'Create Membership' ],
			'get_memberships_all' => [ 'label' => 'Get Memberships (All)' ],
			'get_membership_single' => [ 'label' => 'Get Membership (Single)' ],
			'update_membership_status' => [ 'label' => 'Update Membership Status' ],
			'cancel_membership' => [ 'label' => 'Cancel Membership' ],
			'pause_membership' => [ 'label' => 'Pause Membership' ],
			'activate_membership' => [ 'label' => 'Activate Membership' ],
			'add_membership_note' => [ 'label' => 'Add Membership Note' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$status_options = self::get_membership_status_options();

		$schemas = [
			'create_membership' => [
				[
					'key' => 'plan_id',
					'label' => 'Plan ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'user_id',
					'label' => 'User ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'product_id',
					'label' => 'Product ID',
					'type' => 'expression',
				],
				[
					'key' => 'order_id',
					'label' => 'Order ID',
					'type' => 'expression',
				],
				[
					'key' => 'membership_status',
					'label' => 'Membership Status',
					'type' => 'select',
					'options' => $status_options,
				],
			],
			'get_memberships_all' => [
				[
					'key' => 'user_id',
					'label' => 'User ID',
					'type' => 'expression',
				],
				[
					'key' => 'membership_status',
					'label' => 'Membership Status',
					'type' => 'select',
					'options' => $status_options,
				],
			],
			'get_membership_single' => [
				[
					'key' => 'membership_id',
					'label' => 'Membership ID',
					'type' => 'expression',
					'required' => true,
				],
			],
			'update_membership_status' => [
				[
					'key' => 'membership_id',
					'label' => 'Membership ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'membership_status',
					'label' => 'Membership Status',
					'type' => 'select',
					'options' => $status_options,
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'cancel_membership' => [
				[
					'key' => 'membership_id',
					'label' => 'Membership ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'pause_membership' => [
				[
					'key' => 'membership_id',
					'label' => 'Membership ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'activate_membership' => [
				[
					'key' => 'membership_id',
					'label' => 'Membership ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'add_membership_note' => [
				[
					'key' => 'membership_id',
					'label' => 'Membership ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
					'required' => true,
				],
				[
					'key' => 'notify_member',
					'label' => 'Notify Member',
					'type' => 'boolean',
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ! self::is_memberships_available() ) {
			return self::error_response( 'WooCommerce Memberships is not available', $input );
		}

		$event = (string) ( $node['data']['event'] ?? ( $node['event'] ?? ( $node['config']['action'] ?? '' ) ) );
		$config = $node['data']['config'] ?? ( $node['config']['data'] ?? [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $event ) {
			case 'create_membership':
				return self::action_create_membership( $config, $input );
			case 'get_memberships_all':
				return self::action_get_memberships_all( $config, $input );
			case 'get_membership_single':
				return self::action_get_membership_single( $config, $input );
			case 'update_membership_status':
				return self::action_update_membership_status( $config, $input );
			case 'cancel_membership':
				return self::action_update_membership_status( array_merge( $config, [ 'membership_status' => 'wcm-cancelled' ] ), $input );
			case 'pause_membership':
				return self::action_update_membership_status( array_merge( $config, [ 'membership_status' => 'wcm-paused' ] ), $input );
			case 'activate_membership':
				return self::action_update_membership_status( array_merge( $config, [ 'membership_status' => 'wcm-active' ] ), $input );
			case 'add_membership_note':
				return self::action_add_membership_note( $config, $input );
		}//end switch

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	private static function is_memberships_available(): bool {
		return function_exists( 'wc_memberships_get_user_membership' ) || class_exists( 'WC_Memberships_User_Membership' );
	}

	private static function action_create_membership( array $config, array $input ): array {
		if ( ! function_exists( 'wc_memberships_create_user_membership' ) ) {
			return self::error_response( 'Membership create API is not available', $input );
		}

		$plan_id = (int) ( $config['plan_id'] ?? 0 );
		$user_id = (int) ( $config['user_id'] ?? 0 );
		if ( $plan_id <= 0 || $user_id <= 0 ) {
			return self::error_response( 'Plan ID and User ID are required', $input );
		}

		$args = [
			'plan_id' => $plan_id,
			'user_id' => $user_id,
		];

		$product_id = (int) ( $config['product_id'] ?? 0 );
		$order_id = (int) ( $config['order_id'] ?? 0 );
		if ( $product_id > 0 ) {
			$args['product_id'] = $product_id;
		}
		if ( $order_id > 0 ) {
			$args['order_id'] = $order_id;
		}

		$status = self::normalize_membership_status( (string) ( $config['membership_status'] ?? '' ) );
		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		try {
			$membership = wc_memberships_create_user_membership( $args, 'create' );
		} catch ( \Throwable $e ) {
			return self::error_response( $e->getMessage(), $input );
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $membership ) ) {
			return self::error_response( (string) $membership->get_error_message(), $input );
		}

		$membership = self::resolve_user_membership( $membership );
		if ( ! $membership ) {
			return self::error_response( 'Failed to create membership', $input );
		}

		return self::main_response( array_merge( $input, [
			'membership' => self::build_user_membership_payload( $membership ),
		] ) );
	}

	private static function action_get_memberships_all( array $config, array $input ): array {
		if ( ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
			return self::error_response( 'Membership list API is not available', $input );
		}

		$user_id = (int) ( $config['user_id'] ?? 0 );
		$status = self::normalize_membership_status( (string) ( $config['membership_status'] ?? '' ) );
		$args = [];
		if ( '' !== $status ) {
			$args['status'] = [ $status ];
		}

		$memberships = [];
		try {
			$memberships = wc_memberships_get_user_memberships( $user_id, $args );
		} catch ( \Throwable $e ) {
			return self::error_response( $e->getMessage(), $input );
		}

		$items = [];
		if ( is_array( $memberships ) ) {
			foreach ( $memberships as $membership ) {
				$resolved = self::resolve_user_membership( $membership );
				if ( ! $resolved ) {
					continue;
				}
				$items[] = self::build_user_membership_payload( $resolved );
			}
		}

		return self::main_response( array_merge( $input, [
			'items' => $items,
			'total' => count( $items ),
		] ) );
	}

	private static function action_get_membership_single( array $config, array $input ): array {
		$membership_id = (int) ( $config['membership_id'] ?? 0 );
		if ( $membership_id <= 0 ) {
			return self::error_response( 'Membership ID is required', $input );
		}

		$membership = self::resolve_user_membership( $membership_id );
		if ( ! $membership ) {
			return self::error_response( 'Membership not found', $input );
		}

		return self::main_response( array_merge( $input, [
			'membership' => self::build_user_membership_payload( $membership ),
		] ) );
	}

	private static function action_update_membership_status( array $config, array $input ): array {
		$membership_id = (int) ( $config['membership_id'] ?? 0 );
		if ( $membership_id <= 0 ) {
			return self::error_response( 'Membership ID is required', $input );
		}

		$target_status = self::normalize_membership_status( (string) ( $config['membership_status'] ?? ( $config['status'] ?? '' ) ) );
		if ( '' === $target_status ) {
			return self::error_response( 'Membership status is required', $input );
		}

		$membership = self::resolve_user_membership( $membership_id );
		if ( ! $membership ) {
			return self::error_response( 'Membership not found', $input );
		}

		$old_status = self::resolve_membership_status( $membership );
		if ( '' !== $old_status && $old_status === $target_status ) {
			return self::main_response( array_merge( $input, [
				'membership' => self::build_user_membership_payload( $membership, [
					'old_status' => $old_status,
					'new_status' => $target_status,
				] ),
			] ) );
		}

		$note = (string) ( $config['note'] ?? '' );
		try {
			if ( method_exists( $membership, 'update_status' ) ) {
				$membership->update_status( $target_status, $note );
			} elseif ( method_exists( $membership, 'set_status' ) ) {
				$membership->set_status( $target_status );
				if ( method_exists( $membership, 'save' ) ) {
					$membership->save();
				}
			} else {
				return self::error_response( 'Membership status update API is not available', $input );
			}
		} catch ( \Throwable $e ) {
			return self::error_response( $e->getMessage(), $input );
		}

		return self::main_response( array_merge( $input, [
			'membership' => self::build_user_membership_payload( $membership, [
				'old_status' => $old_status,
				'new_status' => $target_status,
			] ),
		] ) );
	}

	private static function action_add_membership_note( array $config, array $input ): array {
		$membership_id = (int) ( $config['membership_id'] ?? 0 );
		if ( $membership_id <= 0 ) {
			return self::error_response( 'Membership ID is required', $input );
		}

		$note = trim( (string) ( $config['note'] ?? '' ) );
		if ( '' === $note ) {
			return self::error_response( 'Note is required', $input );
		}

		$membership = self::resolve_user_membership( $membership_id );
		if ( ! $membership ) {
			return self::error_response( 'Membership not found', $input );
		}

		$notify = in_array( $config['notify_member'] ?? false, [ true, 1, '1', 'true', 'yes', 'on' ], true );
		if ( method_exists( $membership, 'add_note' ) ) {
			$membership->add_note( $note, $notify );
		} elseif ( method_exists( $membership, 'add_order_note' ) ) {
			$membership->add_order_note( $note, $notify );
		} else {
			return self::error_response( 'Membership note API is not available', $input );
		}

		return self::main_response( array_merge( $input, [
			'membership' => self::build_user_membership_payload( $membership ),
			'note' => $note,
			'notify_member' => $notify,
		] ) );
	}

	private static function resolve_user_membership( $value ) {
		if ( self::is_user_membership_object( $value ) ) {
			return $value;
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$membership_id = (int) $value;
		if ( $membership_id <= 0 || ! function_exists( 'wc_memberships_get_user_membership' ) ) {
			return null;
		}

		$membership = wc_memberships_get_user_membership( $membership_id );
		return self::is_user_membership_object( $membership ) ? $membership : null;
	}

	private static function resolve_membership_from_event_args( array $args, array $trigger_args = [] ) {
		$membership_id = (int) ( $trigger_args['user_membership_id'] ?? 0 );
		if ( $membership_id > 0 ) {
			$membership = self::resolve_user_membership( $membership_id );
			if ( $membership ) {
				return $membership;
			}
		}

		foreach ( $args as $arg ) {
			if ( is_array( $arg ) && isset( $arg['user_membership_id'] ) ) {
				$membership = self::resolve_user_membership( $arg['user_membership_id'] );
				if ( $membership ) {
					return $membership;
				}
			}

			$membership = self::resolve_user_membership( $arg );
			if ( $membership ) {
				return $membership;
			}
		}

		return null;
	}

	private static function is_user_membership_object( $value ): bool {
		return is_object( $value )
			&& method_exists( $value, 'get_id' )
			&& method_exists( $value, 'get_user_id' );
	}

	private static function resolve_membership_status( $membership ): string {
		if ( method_exists( $membership, 'get_status' ) ) {
			return self::normalize_membership_status( (string) $membership->get_status() );
		}

		return '';
	}

	private static function build_user_membership_payload( $membership, array $extra = [] ): array {
		return array_merge(
			[
				'membership_id' => (int) $membership->get_id(),
				'plan_id' => method_exists( $membership, 'get_plan_id' ) ? (int) $membership->get_plan_id() : 0,
				'user_id' => (int) $membership->get_user_id(),
				'order_id' => method_exists( $membership, 'get_order_id' ) ? (int) $membership->get_order_id() : 0,
				'status' => self::resolve_membership_status( $membership ),
				'start_date' => self::resolve_date_value( method_exists( $membership, 'get_start_date' ) ? $membership->get_start_date() : '' ),
				'end_date' => self::resolve_date_value( method_exists( $membership, 'get_end_date' ) ? $membership->get_end_date() : '' ),
			],
			$extra
		);
	}

	private static function resolve_date_value( $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'date' ) ) {
				return (string) $value->date( 'Y-m-d H:i:s' );
			}

			if ( method_exists( $value, 'getTimestamp' ) ) {
				return gmdate( 'Y-m-d H:i:s', (int) $value->getTimestamp() );
			}
		}

		return '';
	}

	private static function normalize_membership_status( string $status ): string {
		$status = sanitize_key( $status );
		if ( 0 === strpos( $status, 'wcm-' ) ) {
			return substr( $status, strlen( 'wcm-' ) );
		}
		if ( 0 === strpos( $status, 'wcm_' ) ) {
			return substr( $status, strlen( 'wcm_' ) );
		}

		return $status;
	}

	private static function get_membership_status_options(): array {
		return [
			[
				'label' => 'Active',
				'value' => 'wcm-active',
			],
			[
				'label' => 'Complimentary',
				'value' => 'wcm-complimentary',
			],
			[
				'label' => 'Pending',
				'value' => 'wcm-pending',
			],
			[
				'label' => 'Paused',
				'value' => 'wcm-paused',
			],
			[
				'label' => 'Cancelled',
				'value' => 'wcm-cancelled',
			],
			[
				'label' => 'Expired',
				'value' => 'wcm-expired',
			],
		];
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
		return [
			'port' => 'error',
			'data' => array_merge( $input, [ 'error' => $message ] ),
		];
	}
}
