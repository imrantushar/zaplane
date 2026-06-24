<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;


class Storeengine extends IntegrationBase {

	private const ENABLE_PURCHASE_SIMULATION = false;
	private static ?string $last_order_error = null;


	public static function get_slug(): string {
		return 'storeengine';
	}

	public static function get_name(): string {
		return 'StoreEngine';
	}

	public static function get_icon(): string {
		return 'storeengine.svg';
	}

	public static function get_triggers(): array {
		return [
			'product_purchased' => [
				'label' => 'Product Purchased',
				'hook'  => 'storeengine/checkout/after_place_order',
			],
			'order_status_update' => [
				'label' => 'Order Status Updated',
				'hook'  => 'storeengine/order/status_changed',
			],
			'order_status_on_hold' => [
				'label' => 'Order Status Set To On Hold',
				'hook'  => 'storeengine/order_status_on_hold',
			],
			'order_status_pending_payment' => [
				'label' => 'Order Status Set To Pending Payment',
				'hook'  => 'storeengine/order_status_pending_payment',
			],
			'order_status_processing' => [
				'label' => 'Order Status Set To Processing',
				'hook'  => 'storeengine/order_status_processing',
			],
			'order_status_completed' => [
				'label' => 'Order Status Set To Completed',
				'hook'  => 'storeengine/order_status_completed',
			],
			'order_status_cancelled' => [
				'label' => 'Order Status Set To Cancelled',
				'hook'  => 'storeengine/order_status_cancelled',
			],
			'order_status_draft' => [
				'label' => 'Order Status Set To Draft',
				'hook'  => 'storeengine/order_status_auto-draft',
			],
			'order_status_trash' => [
				'label' => 'Order Status Set To Trash',
				'hook'  => 'storeengine/order_status_trash',
			],
			'order_restored' => [
				'label' => 'Order Restored',
				'hook'  => 'storeengine/order/status_changed',
			],
			'payment_refunded' => [
				'label' => 'Payment Refunded',
				'hook'  => 'storeengine/subscription/payment_refunded',
			],
			'order_customer_note_added' => [
				'label' => 'Customer Note Added to Order',
				'hook'  => 'storeengine/order/new_customer_note',
			],
			'order_customer_note_deleted' => [
				'label' => 'Customer Note Deleted From Order',
				'hook'  => 'storeengine/order/note_deleted',
			],
			// 'create_access_group' => [
			// 	'label' => 'Create Access Group',
			// 	'hook'  => 'save_post_storeengine_groups',
			// ],
			// 'update_access_group' => [
			// 	'label' => 'Update Access Group',
			// 	'hook'  => 'save_post_storeengine_groups',
			// ],
			// 'delete_access_group' => [
			// 	'label' => 'Delete Access Group',
			// 	'hook'  => 'delete_post_storeengine_groups',
			// ],
			// 'add_user_to_access_group' => [
			// 	'label' => 'Add User to Access Group',
			// 	'hook'  => 'storeengine/membership/user_added_to_group',
			// ],
			// 'remove_user_from_access_group' => [
			// 	'label' => 'Remove User from Access Group',
			// 	'hook'  => 'storeengine/membership/user_removed_from_group',
			// ],
		];
	}

	private static function resolve_order_payload( $order, array $extra = [] ) {
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			$order_id = (int) $order->get_id();
		} elseif ( is_int( $order ) ) {
			$order_id = $order;
		} else {
			return false;
		}

		if ( ! $order_id || ! function_exists( 'storeengine_get_order' ) ) {
			return false;
		}

		try {
			$order_obj = storeengine_get_order( $order_id );
		} catch ( \Throwable $e ) {
			return false;
		}

		if ( ! $order_obj ) {
			return false;
		}

		$items = array_map( function ( $item ) {
			if ( is_array( $item ) ) {
				return [
					'product_id' => $item['product_id'] ?? '',
					'name'       => $item['name'] ?? '',
					'quantity'   => $item['quantity'] ?? '',
					'total'      => $item['total'] ?? '',
				];
			}

			if ( is_object( $item ) ) {
				return [
					'product_id' => method_exists( $item, 'get_product_id' ) ? $item->get_product_id() : '',
					'name'       => method_exists( $item, 'get_name' ) ? $item->get_name() : '',
					'quantity'   => method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : '',
					'total'      => method_exists( $item, 'get_total' ) ? $item->get_total() : '',
				];
			}

			return [];
		}, array_values( $order_obj->get_items() ) );

		return array_merge( [
			'order_id'       => $order_id,
			'order_number'   => $order_obj->get_order_number(),
			'order_status'   => $order_obj->get_status(),
			'total'          => $order_obj->get_total(),
			'currency'       => $order_obj->get_currency(),
			'payment_method' => $order_obj->get_payment_method(),
			'customer_email' => method_exists( $order_obj, 'get_billing_email' )
				? $order_obj->get_billing_email() : '',
			'customer_name'  => method_exists( $order_obj, 'get_billing_first_name' )
				? trim(
					$order_obj->get_billing_first_name() . ' ' .
					$order_obj->get_billing_last_name()
				)
				: '',
			'items'          => $items,
		], $extra );
	}

	private static function resolve_access_group_payload( int $post_id, array $extra = [] ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		$user_roles = get_post_meta( $post_id, '_storeengine_membership_user_roles', true );
		$expiration = get_post_meta( $post_id, '_storeengine_membership_expiration', true );

		return array_merge( [
			'group_id'    => $post_id,
			'group_title' => $post->post_title,
			'group_slug'  => $post->post_name,
			'status'      => $post->post_status,
			'user_roles'  => is_array( $user_roles ) ? wp_list_pluck( $user_roles, 'value' ) : [],
			'expiration'  => is_array( $expiration ) ? $expiration : [],
		], $extra );
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'product_purchased':
				$order_id = $args[0] ?? 0;
				if ( ! $order_id ) {
					return false;
				}
				return self::resolve_order_payload( $order_id );

			case 'order_status_update':
			case 'order_status_on_hold':
			case 'order_status_pending_payment':
			case 'order_status_processing':
			case 'order_status_completed':
			case 'order_status_cancelled':
			case 'order_status_draft':
			case 'order_status_trash':
				$order      = $args[1] ?? null;
				$transition = $args[2] ?? [];
				if ( ! $order || ! is_array( $transition ) ) {
					return false;
				}
				return self::resolve_order_payload( $order, [
					'old_status' => $transition['from'] ?? '',
					'new_status' => $transition['to'] ?? '',
				] );

			case 'order_restored':
				$order_id        = $args[0] ?? 0;
				$old_status      = $args[1] ?? '';
				$restored_status = $args[2] ?? '';
				if ( ! $order_id || 'trash' !== $old_status ) {
					return false;
				}
				return self::resolve_order_payload( $order_id, [
					'old_status'      => $old_status,
					'restored_status' => $restored_status,
				] );

			case 'payment_refunded':
				$order_id        = $args[0] ?? 0;
				$refunded_amount = $args[1] ?? 0;
				$refunded_reason = $args[2] ?? '';
				if ( ! $order_id ) {
					return false;
				}
				return self::resolve_order_payload( $order_id, [
					'refunded_amount' => $refunded_amount,
					'refunded_reason' => $refunded_reason,
				] );

			case 'order_customer_note_added':
				$note  = $args[0] ?? '';
				$order = $args[1] ?? null;
				if ( ! $order || ! is_object( $order ) ) {
					return false;
				}
				return self::resolve_order_payload( $order, [ 'note' => $note ] );

			case 'order_customer_note_deleted':
				$note_id  = $args[0] ?? 0;
				$note_obj = $args[1] ?? null;
				if ( ! $note_obj || ! is_object( $note_obj ) ) {
					return false;
				}
				$order_id = $note_obj->order_id ?? 0;
				if ( ! $order_id ) {
					return false;
				}
				return self::resolve_order_payload( $order_id, [
					'deleted_note_id' => $note_id,
					'deleted_note'    => $note_obj->content ?? '',
				] );

			// case 'create_access_group':
			// case 'update_access_group':
			// 	$post_id = $args[0] ?? 0;
			// 	$post    = $args[1] ?? null;
			// 	$update  = $args[2] ?? false;

			// 	if ( ! $post_id || ! $post || wp_is_post_revision( $post_id ) ) {
			// 		return false;
			// 	}
			// 	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// 		return false;
			// 	}

			// 	$is_create = ! $update;
			// 	if ( 'create_access_group' === $node['event'] && ! $is_create ) {
			// 		return false;
			// 	}
			// 	if ( 'update_access_group' === $node['event'] && $is_create ) {
			// 		return false;
			// 	}

			// 	return self::resolve_access_group_payload( $post_id );

			// case 'delete_access_group':
			// 	$post_id = $args[0] ?? 0;
			// 	if ( ! $post_id ) {
			// 		return false;
			// 	}
			// 	return self::resolve_access_group_payload( $post_id, [ 'deleted' => true ] );

			// case 'add_user_to_access_group':
			// case 'remove_user_from_access_group':
			// 	$user_id  = $args[0] ?? 0;
			// 	$group_id = $args[1] ?? 0;
			// 	if ( ! $user_id ) {
			// 		return false;
			// 	}
			// 	$user = get_userdata( $user_id );

			// 	return [
			// 		'user_id'    => $user_id,
			// 		'user_email' => $user ? $user->user_email : '',
			// 		'user_login' => $user ? $user->user_login : '',
			// 		'group_id'   => $group_id,
			// 	];
		}

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_product'                => [ 'label' => 'Create Product' ],
			'update_product'                => [ 'label' => 'Update Product' ],
			'delete_product'                => [ 'label' => 'Delete Product' ],
			'create_order'                  => [ 'label' => 'Create Order' ],
			'update_order'                  => [ 'label' => 'Update Order' ],
			'delete_order'                  => [ 'label' => 'Delete Order' ],
			'active_membership_addons'      => [ 'label' => 'Active Membership Add-ons' ],
			'create_access_group'           => [ 'label' => 'Create Access Group' ],
			'update_access_group'           => [ 'label' => 'Update Access Group' ],
			'delete_access_group'           => [ 'label' => 'Delete Access Group' ],
			'add_user_to_access_group'      => [ 'label' => 'Add User to Access Group' ],
			'remove_user_from_access_group' => [ 'label' => 'Remove User from Access Group' ],
		];
	}

	private static function product_select(): array {
		return [
			[
				'key'      => 'product_id',
				'label'    => 'Product',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'storeengine',
					'query'       => 'products',
					'select'      => [ 'name', 'label' ],
				],
				'required' => true,
			]
		];

	}

	private static function price_select(): array {
		return [
			[
				'key'      => 'price_id',
				'label'    => 'Price',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'storeengine',
					'query'       => 'product_prices',
					'select'      => [ 'name', 'label' ],
					'depends_on'  => [ 'product_id' ],
				],
				'required' => true,
			]
		];
	}

	private static function order_select(): array {
		return [
			[
				'key'      => 'order_id',
				'label'    => 'Order',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'storeengine',
					'query'       => 'orders',
					'select'      => [ 'name', 'label' ],
				],
				'required' => true,
			]
		];
	}

	private static function access_group_select( string $key = 'access_group_id', string $label = 'Access Group' ): array {
		return [
			[
				'key'      => $key,
				'label'    => $label,
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'storeengine',
					'query'       => 'access_groups_query',
					'select'      => [ 'name', 'label' ],
				],
				'required' => true,
			]
		];
	}

	private static function user_role_select(): array {
		return [
			[
				'key'     => 'user_roles',
				'label'   => 'User Roles (granted access)',
				'type'    => 'multi-select',
				'dynamic' => [
					'integration' => 'storeengine',
					'query'       => 'user_roles',
					'select'      => [ 'name', 'label' ],
				],
			]
		];
	}

	private static function user_select(): array {
		return [
			[
				'key'      => 'user_id',
				'label'    => 'User',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'storeengine',
					'query'       => 'users_query',
					'select'      => [ 'name', 'label' ],
				],
				'required' => true,
			]
		];
	}

	public static function get_action_config_schema( string $action ): array {

		$schemas = [
			'create_product' => [
				[
					'key'      => 'name',
					'label'    => 'Product Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'description',
					'label' => 'Description',
					'type'  => 'textarea',
				],
				[
					'key'     => 'status',
					'label'   => 'Status',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'product_statuses',
						'select'      => [ 'name', 'label' ],
					],
					'default' => 'publish',
				],
				[
					'key'     => 'shipping_type',
					'label'   => 'Shipping Type',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'shipping_types',
						'select'      => [ 'name', 'label' ],
					],
					'default' => 'digital',
				],
				[
					'key'   => 'slug',
					'label' => 'Slug',
					'type'  => 'text',
				],
				[
					'key'   => 'price_name',
					'label' => 'Price Name',
					'type'  => 'text',
				],
				[
					'key'     => 'price_type',
					'label'   => 'Price Type',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'price_types',
						'select'      => [ 'name', 'label' ],
					],
					'default' => 'onetime',
				],
				[
					'key'   => 'price',
					'label' => 'Price Amount',
					'type'  => 'number',
				],
				[
					'key'   => 'compare_price',
					'label' => 'Compare Price',
					'type'  => 'number',
				],
			],
			'update_product' => [
				...self::product_select(),
				[
					'key'   => 'name',
					'label' => 'Product Name',
					'type'  => 'text',
				],
				[
					'key'     => 'status',
					'label'   => 'Status',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'product_statuses',
						'select'      => [ 'name', 'label' ],
					],
				],
				[
					'key'     => 'shipping_type',
					'label'   => 'Shipping Type',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'shipping_types',
						'select'      => [ 'name', 'label' ],
					],
				],
				[
					'key'   => 'description',
					'label' => 'Description',
					'type'  => 'textarea',
				],
				[
					'key'   => 'slug',
					'label' => 'Slug',
					'type'  => 'text',
				],
				...self::price_select(),
				[
					'key'   => 'price_name',
					'label' => 'Price Name',
					'type'  => 'text',
				],
				[
					'key'   => 'price',
					'label' => 'Price Amount',
					'type'  => 'number',
				],
			],
			'delete_product' => [
				...self::product_select(),
				[
					'key'     => 'force_delete',
					'label'   => 'Force Delete',
					'type'    => 'select',
					'options' => [
						[ 'label' => 'Yes', 'value' => '1' ],
						[ 'label' => 'No', 'value' => '0' ],
					],
					'default' => '1',
				],
			],
			'create_order' => [
				[
					'key'     => 'status',
					'label'   => 'Order Status',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'order_statuses',
						'select'      => [ 'name', 'label' ],
					],
					'default' => 'pending_payment',
				],
				[
					'key'   => 'customer_id',
					'label' => 'Customer ID',
					'type'  => 'number',
				],
				[
					'key'   => 'billing_email',
					'label' => 'Billing Email',
					'type'  => 'text',
				],
				[
					'key'   => 'billing_first_name',
					'label' => 'Billing First Name',
					'type'  => 'text',
				],
				[
					'key'   => 'billing_last_name',
					'label' => 'Billing Last Name',
					'type'  => 'text',
				],
				[
					'key'     => 'product_id',
					'label'   => 'Product (for price lookup)',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'products',
						'select'      => [ 'name', 'label' ],
					],
				],
				...self::price_select(),
				[
					'key'     => 'quantity',
					'label'   => 'Quantity',
					'type'    => 'number',
					'default' => 1,
				],
			],
			'update_order' => [
				...self::order_select(),
				[
					'key'     => 'status',
					'label'   => 'Order Status',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'order_statuses',
						'select'      => [ 'name', 'label' ],
					],
				],
				[
					'key'   => 'customer_id',
					'label' => 'Customer ID',
					'type'  => 'number',
				],
				[
					'key'   => 'billing_email',
					'label' => 'Billing Email',
					'type'  => 'text',
				],
				[
					'key'   => 'billing_first_name',
					'label' => 'Billing First Name',
					'type'  => 'text',
				],
				[
					'key'   => 'billing_last_name',
					'label' => 'Billing Last Name',
					'type'  => 'text',
				],
				[
					'key'     => 'product_id',
					'label'   => 'Product (for line item)',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'storeengine',
						'query'       => 'products',
						'select'      => [ 'name', 'label' ],
					],
				],
				...self::price_select(),
				[
					'key'     => 'quantity',
					'label'   => 'Line Item Quantity',
					'type'    => 'number',
					'default' => 1,
				],
			],
			'delete_order' => [
				...self::order_select(),
				[
					'key'     => 'force_delete',
					'label'   => 'Force Delete',
					'type'    => 'select',
					'options' => [
						[ 'label' => 'No (move to trash)', 'value' => '0' ],
						[ 'label' => 'Yes (permanent)', 'value' => '1' ],
					],
					'default' => '0',
				],
			],

			'create_access_group' => [
				[
					'key'      => 'group_title',
					'label'    => 'Access Group Title',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'group_content',
					'label' => 'Description',
					'type'  => 'textarea',
				],
				[
					'key'     => 'status',
					'label'   => 'Status',
					'type'    => 'select',
					'options' => [
						[ 'label' => 'Publish', 'value' => 'publish' ],
						[ 'label' => 'Draft', 'value' => 'draft' ],
					],
					'default' => 'publish',
				],
				...self::user_role_select(),
				[
					'key'   => 'priority',
					'label' => 'Priority',
					'type'  => 'number',
				],
			],

			'update_access_group' => [
				...self::access_group_select(),
				[
					'key'   => 'group_title',
					'label' => 'Access Group Title',
					'type'  => 'text',
				],
				[
					'key'   => 'group_content',
					'label' => 'Description',
					'type'  => 'textarea',
				],
				[
					'key'     => 'status',
					'label'   => 'Status',
					'type'    => 'select',
					'options' => [
						[ 'label' => 'Publish', 'value' => 'publish' ],
						[ 'label' => 'Draft', 'value' => 'draft' ],
					],
				],
				...self::user_role_select(),
				[
					'key'   => 'priority',
					'label' => 'Priority',
					'type'  => 'number',
				],
			],

			'delete_access_group' => [
				...self::access_group_select(),
				[
					'key'     => 'force_delete',
					'label'   => 'Force Delete',
					'type'    => 'select',
					'options' => [
						[ 'label' => 'No (move to trash)', 'value' => '0' ],
						[ 'label' => 'Yes (permanent)', 'value' => '1' ],
					],
					'default' => '1',
				],
			],

			'add_user_to_access_group' => [
				...self::user_select(),
				...self::access_group_select(),
			],

			'remove_user_from_access_group' => [
				...self::user_select(),
				...self::access_group_select(),
			],
		];
		return $schemas[ $action ] ?? [];
	}

	private static function resolve_linked_price( int $group_id ) {
		if ( ! class_exists( '\StoreEngine\Utils\Helper' ) ) {
			return [ null, 'helper_class_missing' ];
		}

		$integrations = \StoreEngine\Utils\Helper::get_integration_repository_by_id( 'storeengine/membership-addon', $group_id );

		if ( empty( $integrations ) ) {
			return [ null, 'no_linked_product' ];
		}

		return [ current( $integrations ), 'ok' ];
	}

	private static function grant_user_access( int $user_id, int $group_id ) {
		if ( ! $user_id || ! $group_id ) {
			return [ 'method' => 'invalid' ];
		}

		[ $linked_price, $reason ] = self::resolve_linked_price( $group_id );

		if ( self::ENABLE_PURCHASE_SIMULATION && $linked_price && ! empty( $linked_price->price ) ) {
			$order_id = self::create_and_complete_order( $user_id, $group_id, $linked_price );

			if ( $order_id ) {
				return [
					'method'   => 'purchase',
					'order_id' => $order_id,
				];
			}
			$reason = 'order_save_failed: ' . ( self::$last_order_error ?? 'unknown error' );
		}

		self::write_purchased_membership_meta( $user_id, $group_id, true );

		return [ 'method' => 'direct_meta', 'reason' => $reason ];
	}

	private static function revoke_user_access( int $user_id, int $group_id ): bool {
		if ( ! $user_id || ! $group_id ) {
			return false;
		}

		self::write_purchased_membership_meta( $user_id, $group_id, false );

		return true;
	}

	private static function create_and_complete_order( int $user_id, int $group_id, $linked_price ) {
		if ( ! class_exists( '\StoreEngine\Classes\Order' ) || ! class_exists( '\StoreEngine\Classes\Order\OrderItemProduct' ) ) {
			return false;
		}

		try {
			$user = get_userdata( $user_id );
			$order = new \StoreEngine\Classes\Order();
			$order->set_customer_id( $user_id );

			if ( $user && method_exists( $order, 'set_billing_email' ) ) {
				$order->set_billing_email( $user->user_email );
				$order->set_billing_first_name( $user->first_name ?: $user->display_name );
				$order->set_billing_last_name( $user->last_name );
			}

			$price_id = method_exists( $linked_price->price, 'get_id' ) ? $linked_price->price->get_id() : ( $linked_price->price->id ?? 0 );
			$item_id  = method_exists( $linked_price->integration, 'get_item_id' ) ? $linked_price->integration->get_item_id() : $group_id;
			$amount   = method_exists( $linked_price->price, 'get_price' ) ? $linked_price->price->get_price() : ( $linked_price->price->price ?? 0 );

			$item = new \StoreEngine\Classes\Order\OrderItemProduct();

			if ( method_exists( $item, 'set_product_id' ) ) {
				$item->set_product_id( $item_id );
			}
			if ( method_exists( $item, 'set_price_id' ) ) {
				$item->set_price_id( $price_id );
			}
			$item->set_quantity( 1 );
			$item->set_total( $amount );
			if ( method_exists( $item, 'set_subtotal' ) ) {
				$item->set_subtotal( $amount );
			}

			$order->add_item( $item );

			if ( method_exists( $order, 'calculate_totals' ) ) {
				$order->calculate_totals( false );
			}

			if ( method_exists( $order, 'update_status' ) ) {
				$order->update_status( \StoreEngine\Classes\OrderStatus\OrderStatus::COMPLETED );
			} elseif ( method_exists( $order, 'set_status' ) ) {
				$order->set_status( \StoreEngine\Classes\OrderStatus\OrderStatus::COMPLETED );
			}

			$saved = $order->save();

			if ( ! $saved ) {
				return false;
			}

			return $order->get_id() ?: false;
		} catch ( \Throwable $e ) {
			self::$last_order_error = $e->getMessage();
			return false;
		}
	}

	private static function write_purchased_membership_meta( int $user_id, int $group_id, bool $add ) {
		$purchased_meta_key = '_storeengine_purchased_membership_ids';
		$purchased          = get_user_meta( $user_id, $purchased_meta_key, true );
		$purchased          = is_array( $purchased ) ? $purchased : [];

		if ( $add ) {
			if ( ! in_array( $group_id, $purchased, true ) ) {
				$purchased[] = $group_id;
			}
		} else {
			$purchased = array_values( array_diff( $purchased, [ $group_id ] ) );
		}
		update_user_meta( $user_id, $purchased_meta_key, $purchased );

		$display_meta_key = '_storeengine_user_membership_data';
		$display_data      = get_user_meta( $user_id, $display_meta_key, true );
		$display_data      = is_array( $display_data ) ? $display_data : [];

		if ( $add ) {
			$display_data[ $group_id ] = [
				'content_protect_types' => get_post_meta( $group_id, '_storeengine_membership_content_protect_types', true ),
				'expiration_date'       => get_post_meta( $group_id, '_storeengine_membership_expiration', true ),
			];
		} else {
			unset( $display_data[ $group_id ] );
		}
		update_user_meta( $user_id, $display_meta_key, $display_data );

		do_action(
			$add ? 'storeengine/membership/user_added_to_group' : 'storeengine/membership/user_removed_from_group',
			$user_id,
			$group_id
		);
	}


	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		switch ( $event ) {

			case 'create_product':
			case 'update_product':
			case 'delete_product':
			case 'create_order':
			case 'update_order':
			case 'delete_order':
			case 'active_membership_addons':
			case 'create_access_group':
				$post_id = wp_insert_post( [
					'post_type'    => 'storeengine_groups',
					'post_title'   => $config['group_title'] ?? '',
					'post_content' => $config['group_content'] ?? '',
					'post_status'  => $config['status'] ?? 'publish',
				], true );

				if ( is_wp_error( $post_id ) || ! $post_id ) {
					return [
						'port' => 'error',
						'data' => [ 'message' => is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Failed to create access group.' ],
					];
				}

				if ( ! empty( $config['user_roles'] ) ) {
					$roles = is_array( $config['user_roles'] ) ? $config['user_roles'] : [ $config['user_roles'] ];
					$role_meta = array_map( function ( $role ) {
						return [ 'value' => $role, 'label' => $role ];
					}, $roles );
					update_post_meta( $post_id, '_storeengine_membership_user_roles', $role_meta );
				}

				if ( isset( $config['priority'] ) && '' !== $config['priority'] ) {
					update_post_meta( $post_id, '_storeengine_membership_priority', (string) $config['priority'] );
				}

				wp_cache_flush_group( 'se_membership_plans' );

				return [
					'port' => 'main',
					'data' => self::resolve_access_group_payload( $post_id ) ?: [ 'group_id' => $post_id ],
				];

			case 'update_access_group':
				$post_id = (int) ( $config['access_group_id'] ?? 0 );

				if ( ! $post_id || 'storeengine_groups' !== get_post_type( $post_id ) ) {
					return [
						'port' => 'error',
						'data' => [ 'message' => 'Access group not found.' ],
					];
				}

				$update_args = [ 'ID' => $post_id ];
				if ( ! empty( $config['group_title'] ) ) {
					$update_args['post_title'] = $config['group_title'];
				}
				if ( isset( $config['group_content'] ) ) {
					$update_args['post_content'] = $config['group_content'];
				}
				if ( ! empty( $config['status'] ) ) {
					$update_args['post_status'] = $config['status'];
				}
				if ( count( $update_args ) > 1 ) {
					wp_update_post( $update_args );
				}

				if ( isset( $config['user_roles'] ) ) {
					$roles     = is_array( $config['user_roles'] ) ? $config['user_roles'] : [ $config['user_roles'] ];
					$role_meta = array_map( function ( $role ) {
						return [ 'value' => $role, 'label' => $role ];
					}, $roles );
					update_post_meta( $post_id, '_storeengine_membership_user_roles', $role_meta );
				}

				if ( isset( $config['priority'] ) && '' !== $config['priority'] ) {
					update_post_meta( $post_id, '_storeengine_membership_priority', (string) $config['priority'] );
				}

				wp_cache_delete( $post_id, 'post_meta' );
				wp_cache_flush_group( 'se_membership_plans' );

				return [
					'port' => 'main',
					'data' => self::resolve_access_group_payload( $post_id ) ?: [ 'group_id' => $post_id ],
				];

			case 'delete_access_group':
				$post_id = (int) ( $config['access_group_id'] ?? 0 );

				if ( ! $post_id || 'storeengine_groups' !== get_post_type( $post_id ) ) {
					return [
						'port' => 'error',
						'data' => [ 'message' => 'Access group not found.' ],
					];
				}

				$force_delete = ! empty( $config['force_delete'] ) && '1' === (string) $config['force_delete'];
				$payload      = self::resolve_access_group_payload( $post_id, [ 'deleted' => true ] );

				$result = wp_delete_post( $post_id, $force_delete );

				if ( ! $result ) {
					return [
						'port' => 'error',
						'data' => [ 'message' => 'Failed to delete access group.' ],
					];
				}

				return [
					'port' => 'main',
					'data' => $payload,
				];

			case 'add_user_to_access_group':
				$user_id  = (int) ( $config['user_id'] ?? 0 );
				$group_id = (int) ( $config['access_group_id'] ?? 0 );

				if ( ! get_userdata( $user_id ) || 'storeengine_groups' !== get_post_type( $group_id ) ) {
					return [
						'port' => 'error',
						'data' => [ 'message' => 'Invalid user or access group.' ],
					];
				}

				$grant_result = self::grant_user_access( $user_id, $group_id );

				return [
					'port' => 'main',
					'data' => [
						'user_id'  => $user_id,
						'group_id' => $group_id,
						'status'   => 'added',
						'method'   => $grant_result['method'] ?? 'unknown',
						'order_id' => $grant_result['order_id'] ?? null,
						'reason'   => $grant_result['reason'] ?? null,
					],
				];

			case 'remove_user_from_access_group':
				$user_id  = (int) ( $config['user_id'] ?? 0 );
				$group_id = (int) ( $config['access_group_id'] ?? 0 );

				if ( ! get_userdata( $user_id ) || 'storeengine_groups' !== get_post_type( $group_id ) ) {
					return [
						'port' => 'error',
						'data' => [ 'message' => 'Invalid user or access group.' ],
					];
				}

				self::revoke_user_access( $user_id, $group_id );

				return [
					'port' => 'main',
					'data' => [
						'user_id'  => $user_id,
						'group_id' => $group_id,
						'status'   => 'removed',
					],
				];
		}

		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'access_groups_query' => [ self::class, 'query_access_groups' ],
			'users_query'          => [ self::class, 'query_users' ],
		];
	}

	public static function query_access_groups( $query ) {
		$keyword = $query['keyword'] ?? '';

		$args = [
			'post_type'      => 'storeengine_groups',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
		];
		if ( ! empty( $keyword ) ) {
			$args['s'] = $keyword;
		}

		$groups  = get_posts( $args );
		$results = [];

		foreach ( $groups as $group ) {
			$results[] = [
				'name'  => $group->ID,
				'label' => $group->post_title,
			];
		}

		return $results;
	}

	public static function query_users( $query ) {
		$keyword = $query['keyword'] ?? '';

		$args = [ 'number' => 50 ];
		if ( ! empty( $keyword ) ) {
			$args['search'] = '*' . $keyword . '*';
		}

		$users   = get_users( $args );
		$results = [];

		foreach ( $users as $user ) {
			$results[] = [
				'name'  => $user->ID,
				'label' => $user->display_name . ' (' . $user->user_email . ')',
			];
		}

		return $results;
	}
}
