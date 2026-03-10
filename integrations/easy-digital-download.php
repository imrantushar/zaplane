<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Easydigitaldownload\ActionsResponseTrait;
use Zaplane\Integrations\Easydigitaldownload\CustomerActionsTrait;
use Zaplane\Integrations\Easydigitaldownload\DiscountActionsTrait;
use Zaplane\Integrations\Easydigitaldownload\PaymentActionsTrait;
use Zaplane\Integrations\Easydigitaldownload\DownloadActionsTrait;
use Zaplane\Integrations\Easydigitaldownload\HelperTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EasyDigitalDownload extends IntegrationBase {

	use ActionsResponseTrait;
	use CustomerActionsTrait;
	use DiscountActionsTrait;
	use PaymentActionsTrait;
	use DownloadActionsTrait;
	use HelperTrait;

	public static function get_slug(): string {
		return 'easydigitaldownload';
	}

	public static function get_triggers(): array {
		return [
			'purchase_product' => [
				'label' => 'Product Purchased',
				'hook' => 'edd_complete_purchase'
			],
			'payment_status_changed' => [
				'label' => 'Payment Status Changed',
				'hook' => 'edd_before_payment_status_change'
			],
			'customer_created' => [
				'label' => 'Customer Created',
				'hook' => 'edd_customer_post_create'
			],
			'customer_updated' => [
				'label' => 'Customer Updated',
				'hook' => 'edd_customer_post_update'
			],
			'customer_deleted' => [
				'label' => 'Customer Deleted',
				'hook' => 'edd_customer_destroyed'
			],
			'discount_created' => [
				'label' => 'Discount Created',
				'hook' => 'edd_post_insert_discount'
			],
			'discount_updated' => [
				'label' => 'Discount Updated',
				'hook' => 'edd_post_update_discount'
			],
			'discount_deleted' => [
				'label' => 'Discount Deleted',
				'hook' => 'edd_post_delete_discount'
			],
			'download_created' => [
				'label' => 'Product Created',
				'hook' => 'wp_insert_post'
			],
			'download_updated' => [
				'label' => 'Product Updated',
				'hook' => 'save_post_download'
			],
			'download_deleted' => [
				'label' => 'Product Deleted',
				'hook' => 'before_delete_post'
			],
			'download_purchased' => [
				'label' => 'Product Purchased (Item)',
				'hook' => 'edd_complete_download_purchase'
			],
		];
	}
	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'purchase_product':
				return self::payload_with_id('payment_id', $args[0] ?? 0, [
					'customer_id' => self::extract_id( $args[2] ?? null ),
				]);

			case 'payment_status_changed':
				return self::build_payment_status_payload(
					$args[0] ?? 0,
					$args[1] ?? '',
					$args[2] ?? ''
				);

			case 'customer_created':
				return self::payload_with_id('customer_id', $args[0] ?? 0, [
					'data' => $args[1] ?? [],
				]);

			case 'customer_updated':
				$updated = (bool) ( $args[0] ?? false );
				return self::payload_with_id('customer_id', $args[1] ?? 0, [
					'updated' => $updated,
					'data' => $args[2] ?? [],
				]);

			case 'customer_deleted':
				return self::payload_with_id( 'customer_id', $args[0] ?? 0 );

			case 'discount_created':
				return self::payload_with_id('discount_id', $args[1] ?? ( $args[0] ?? 0 ), [
					'data' => $args[0] ?? [],
				]);

			case 'discount_updated':
				return self::payload_with_id('discount_id', $args[1] ?? 0, [
					'data' => $args[0] ?? [],
				]);

			case 'discount_deleted':
				return self::payload_with_id( 'discount_id', $args[0] ?? 0 );

			case 'download_created':
				return self::build_download_created_payload(
					$args[0] ?? 0,
					$args[1] ?? null,
					$args[2] ?? null
				);

			case 'download_updated':
				return self::build_download_updated_payload(
					$args[0] ?? 0,
					$args[1] ?? null,
					$args[2] ?? null
				);

			case 'download_deleted':
				return self::build_download_deleted_payload( $args[0] ?? 0 );

			case 'download_purchased':
				$download_id = self::extract_id( $args[0] ?? 0 );
				$order_id = self::extract_id( $args[1] ?? 0 );
				if ( ! $download_id || ! $order_id ) {
					return false;
				}
				return [
					'download_id' => $download_id,
					'order_id' => $order_id,
					'download_type' => $args[2] ?? '',
					'cart_details' => $args[3] ?? [],
					'cart_index' => $args[4] ?? null,
				];
		}//end switch

		return false;
	}


	public static function get_actions(): array {
		return [
			'create_customer' => [ 'label' => 'Create Customer' ],
			'create_discount' => [ 'label' => 'Create Discount' ],
			'update_payment_status' => [ 'label' => 'Update Payment Status' ],
			'add_payment_note' => [ 'label' => 'Add Payment Note' ],
			'create_download' => [ 'label' => 'Create Product' ],
			'update_download' => [ 'label' => 'Update Product' ],
			'delete_download' => [ 'label' => 'Delete Product' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_customer' => [
				[
					'key' => 'email',
					'label' => 'Customer Email',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'name',
					'label' => 'Customer Name',
					'type' => 'text'
				],
				[
					'key' => 'user_id',
					'label' => 'User ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'easydigitaldownload',
						'query' => 'users',
						'select' => [ 'id', 'label' ],
					]
				],
				[
					'key' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Active',
							'value' => 'active'
						],
						[
							'label' => 'Inactive',
							'value' => 'inactive'
						],
					]
				],
			],
			'create_discount' => [
				[
					'key' => 'name',
					'label' => 'Discount Name',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'code',
					'label' => 'Discount Code',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'amount',
					'label' => 'Amount',
					'type' => 'number',
					'required' => true
				],
				[
					'key' => 'type',
					'label' => 'Type',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Percent',
							'value' => 'percent'
						],
						[
							'label' => 'Flat',
							'value' => 'flat'
						],
					]
				],
				[
					'key' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Active',
							'value' => 'active'
						],
						[
							'label' => 'Inactive',
							'value' => 'inactive'
						],
					]
				],
				[
					'key' => 'start_date',
					'label' => 'Start Date (YYYY-MM-DD)',
					'type' => 'text'
				],
				[
					'key' => 'end_date',
					'label' => 'End Date (YYYY-MM-DD)',
					'type' => 'text'
				],
			],
			'update_payment_status' => [
				[
					'key' => 'payment_id',
					'label' => 'Payment ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'easydigitaldownload',
						'query' => 'payments',
						'select' => [ 'id', 'label' ],
					],
					'required' => true
				],
				[
					'key' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Pending',
							'value' => 'pending'
						],
						[
							'label' => 'Processing',
							'value' => 'processing'
						],
						[
							'label' => 'Completed',
							'value' => 'complete'
						],
						[
							'label' => 'Refunded',
							'value' => 'refunded'
						],
						[
							'label' => 'Partially Refunded',
							'value' => 'partially_refunded'
						],
						[
							'label' => 'Revoked',
							'value' => 'revoked'
						],
						[
							'label' => 'Failed',
							'value' => 'failed'
						],
						[
							'label' => 'Abandoned',
							'value' => 'abandoned'
						],
						[
							'label' => 'On Hold',
							'value' => 'on_hold'
						],
					]
				],
			],
			'add_payment_note' => [
				[
					'key' => 'payment_id',
					'label' => 'Payment ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'easydigitaldownload',
						'query' => 'payments',
						'select' => [ 'id', 'label' ],
					],
					'required' => true
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'textarea',
					'required' => true
				],
			],
			'create_download' => [
				[
					'key' => 'name',
					'label' => 'Product Name',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'description',
					'label' => 'Description',
					'type' => 'textarea'
				],
				[
					'key' => 'price',
					'label' => 'Price',
					'type' => 'number'
				],
				[
					'key' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Draft',
							'value' => 'draft'
						],
						[
							'label' => 'Publish',
							'value' => 'publish'
						],
					]
				],
			],
			'update_download' => [
				[
					'key' => 'download_id',
					'label' => 'Product ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'easydigitaldownload',
						'query' => 'downloads',
						'select' => [ 'id', 'name' ],
					],
					'required' => true
				],
				[
					'key' => 'name',
					'label' => 'Product Name',
					'type' => 'text'
				],
				[
					'key' => 'description',
					'label' => 'Description',
					'type' => 'textarea'
				],
				[
					'key' => 'price',
					'label' => 'Price',
					'type' => 'number'
				],
				[
					'key' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Draft',
							'value' => 'draft'
						],
						[
							'label' => 'Publish',
							'value' => 'publish'
						],
					]
				],
			],
			'delete_download' => [
				[
					'key' => 'download_id',
					'label' => 'Product ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'easydigitaldownload',
						'query' => 'downloads',
						'select' => [ 'id', 'name' ],
					],
					'required' => true
				],
				[
					'key' => 'force_delete',
					'label' => 'Force Delete',
					'type' => 'select',
					'options' => [
						[
							'label' => 'No',
							'value' => '0'
						],
						[
							'label' => 'Yes',
							'value' => '1'
						],
					]
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	/**
	 * =====================================================
	 * DYNAMIC DATA QUERIES (API)
	 * =====================================================
	 */
	public static function get_dynamic_queries(): array {
		return [
			'downloads' => [ self::class, 'query_downloads' ],
			'payments' => [ self::class, 'query_payments' ],
			'customers' => [ self::class, 'query_customers' ],
			'discounts' => [ self::class, 'query_discounts' ],
			'users' => [ self::class, 'query_users' ],
		];
	}



	public static function execute_node( array $node, array $input ): array {
		$event = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$method = 'action_' . $event;

		if ( method_exists( static::class, $method ) ) {
			return static::$method( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input
		];
	}
}
