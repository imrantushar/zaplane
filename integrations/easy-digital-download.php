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

	public static function get_name(): string {
		return 'Easy Digital Downloads';
	}

	public static function get_icon(): string {
		return 'easydigitaldownload.svg';
	}

	/** @inheritDoc */
	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/easy-digital-downloads/',
			'action'  => 'https://zaplane.app/docs/easy-digital-downloads/',
		];
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
				// edd_complete_purchase fires with a single arg: ( $payment_id ).
				// customer_id is not available from the hook args, so we look it
				// up from the payment record itself.
				$payment_id = self::extract_id( $args[0] ?? 0 );
				if ( ! $payment_id ) {
					return false;
				}

				return self::payload_with_id('payment_id', $payment_id, [
					'customer_id' => self::get_customer_id_for_payment( $payment_id ),
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
				// edd_post_insert_discount fires as: ( $discount_details, $discount_id )
				return self::payload_with_id('discount_id', $args[1] ?? 0, [
					'data' => $args[0] ?? [],
				]);

			case 'discount_updated':
				// edd_post_update_discount fires as: ( $discount_details, $discount_id )
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

	/**
	 * Sample output for each trigger so the "@" field picker has fields to
	 * offer before a real capture exists. Keys mirror exactly what
	 * resolve_trigger() emits for the same event.
	 */
	public static function get_trigger_sample_output( string $event ): array {
		// Recurring shapes shared across several triggers.
		$payment_base = [
			'payment_id'  => 101,
			'customer_id' => 5,
		];

		$customer_data = [
			'user_id'        => 9,
			'name'           => 'John Doe',
			'email'          => 'john@example.com',
			'date_created'   => '2026-07-09 12:00:00',
			'purchase_count' => 3,
			'purchase_value' => '147.00',
			'status'         => 'active',
		];

		$customer_base = [
			'customer_id' => 5,
		];

		$discount_data = [
			'name'        => 'Summer Sale',
			'code'        => 'SUMMER25',
			'type'        => 'percent',
			'amount'      => '25.00',
			'status'      => 'active',
			'start_date'  => '2026-07-01 00:00:00',
			'end_date'    => '2026-07-31 23:59:59',
			'use_count'   => 4,
			'max_uses'    => 100,
		];

		$discount_base = [
			'discount_id' => 12,
		];

		$download_post = [
			'ID'          => 21,
			'post_title'  => 'Pro Plan',
			'post_status' => 'publish',
			'post_type'   => 'download',
			'post_author' => 1,
			'post_date'   => '2026-07-09 12:00:00',
		];

		$download_base = [
			'download_id' => 21,
		];

		$samples = [
			'purchase_product'       => $payment_base,
			'payment_status_changed' => array_merge( $payment_base, [
				'new_status' => 'complete',
				'old_status' => 'pending',
			] ),
			'customer_created'       => array_merge( $customer_base, [
				'data' => $customer_data,
			] ),
			'customer_updated'       => array_merge( $customer_base, [
				'updated' => true,
				'data'    => $customer_data,
			] ),
			'customer_deleted'       => $customer_base,
			'discount_created'       => array_merge( $discount_base, [
				'data' => $discount_data,
			] ),
			'discount_updated'       => array_merge( $discount_base, [
				'data' => $discount_data,
			] ),
			'discount_deleted'       => $discount_base,
			'download_created'       => array_merge( $download_base, [
				'data' => $download_post,
			] ),
			'download_updated'       => array_merge( $download_base, [
				'post' => $download_post,
			] ),
			'download_deleted'       => array_merge( $download_base, [
				'post' => $download_post,
			] ),
			'download_purchased'     => [
				'download_id'   => 21,
				'order_id'      => 101,
				'download_type' => 'default',
				'cart_details'  => [
					[
						'name'        => 'Pro Plan',
						'id'          => 21,
						'item_number' => [
							'id'      => 21,
							'options' => [ 'price_id' => 1 ],
						],
						'item_price'  => '49.00',
						'quantity'    => 1,
						'price'       => '49.00',
					],
				],
				'cart_index'    => 0,
			],
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		// Prefix fallbacks so any future trigger still exposes a sensible shape
		// in the "@" picker even before a capture.
		if ( 0 === strpos( $event, 'payment_' ) || 0 === strpos( $event, 'purchase_' ) ) {
			return array_merge( $payment_base, [
				'new_status' => 'complete',
				'old_status' => 'pending',
			] );
		}
		if ( 0 === strpos( $event, 'customer_' ) ) {
			return array_merge( $customer_base, [ 'data' => $customer_data ] );
		}
		if ( 0 === strpos( $event, 'discount_' ) ) {
			return array_merge( $discount_base, [ 'data' => $discount_data ] );
		}
		if ( 0 === strpos( $event, 'download_' ) ) {
			return array_merge( $download_base, [ 'post' => $download_post ] );
		}

		// Final non-empty catch-all: no trigger ever returns [].
		return $payment_base;
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
					'type' => 'email',
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
					'required' => true,
					'dynamic' => [
						'integration' => 'easydigitaldownload',
						'query' => 'users',
						'select' => [ 'id', 'label' ],
					]
				],
				[
					'key' => 'customer_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => [
						[
							'label' => 'Active',
							'value' => 'edd_customer_active'
						],
						[
							// EDD customer statuses are only "active" / "disabled" —
							// there is no "inactive" status in EDD core.
							'label' => 'Disabled',
							'value' => 'edd_customer_disabled'
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
					'required' => true,
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
					'key' => 'discount_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => [
						[
							'label' => 'Active',
							'value' => 'edd_discount_active'
						],
						[
							'label' => 'Inactive',
							'value' => 'edd_discount_inactive'
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
					'key' => 'payment_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => [
						[
							'label' => 'Pending',
							'value' => 'edd_payment_pending'
						],
						[
							'label' => 'Processing',
							'value' => 'edd_payment_processing'
						],
						[
							'label' => 'Completed',
							'value' => 'edd_payment_complete'
						],
						[
							'label' => 'Refunded',
							'value' => 'edd_payment_refunded'
						],
						[
							'label' => 'Partially Refunded',
							'value' => 'edd_payment_partially_refunded'
						],
						[
							'label' => 'Revoked',
							'value' => 'edd_payment_revoked'
						],
						[
							'label' => 'Failed',
							'value' => 'edd_payment_failed'
						],
						[
							'label' => 'Abandoned',
							'value' => 'edd_payment_abandoned'
						],
						[
							// EDD's real order status key is "onhold" (no underscore).
							'label' => 'On Hold',
							'value' => 'edd_payment_onhold'
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
					'key' => 'download_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => [
						[
							'label' => 'Draft',
							'value' => 'edd_download_draft'
						],
						[
							'label' => 'Publish',
							'value' => 'edd_download_publish'
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
					'key' => 'download_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => [
						[
							'label' => 'Draft',
							'value' => 'edd_download_draft'
						],
						[
							'label' => 'Publish',
							'value' => 'edd_download_publish'
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
