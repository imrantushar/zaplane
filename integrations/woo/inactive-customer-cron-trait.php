<?php

namespace Zaplane\Integrations\Woo;

use Zaplane\Framework\Classes\Query;
use Zaplane\Framework\Core\Automation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait InactiveCustomerCronTrait {

	public static function register_inactive_customer_cron(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}
		if ( as_next_scheduled_action( 'zaplane_woo_inactive_customer_check', [], 'zaplane_inactive_customer' ) ) {
			return;
		}
		as_schedule_recurring_action(
			strtotime( 'tomorrow midnight' ),
			DAY_IN_SECONDS,
			'zaplane_woo_inactive_customer_check',
			[],
			'zaplane_inactive_customer'
		);
	}

	public static function unschedule_inactive_customer_cron(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( 'zaplane_woo_inactive_customer_check', [], 'zaplane_inactive_customer' );
	}

	public static function handle_inactive_customer_check(): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$automation = Automation::get_instance();
		if ( ! $automation ) {
			return;
		}

		$workflows = Query::get_active_workflows_for_event( 'zaplane_woo_inactive_customer' );
		if ( empty( $workflows ) ) {
			return;
		}

		// Group workflows by days value to avoid redundant queries.
		$grouped = [];
		foreach ( $workflows as $trigger ) {
			$days = (int) ( $trigger['graph_node']['data']['config']['days'] ?? 0 );
			if ( $days <= 0 ) {
				continue;
			}
			$grouped[ $days ][] = $trigger;
		}

		foreach ( $grouped as $days => $day_workflows ) {
			$customers = self::find_customers_inactive_for( $days );

			foreach ( $customers as $customer_data ) {
				foreach ( $day_workflows as $trigger ) {
					$workflow_id = (int) $trigger['workflow_id'];
					$user_id     = (int) ( $customer_data['user_id'] ?? 0 );
					$email       = $customer_data['email'] ?? '';

					if ( ! $email ) {
						continue;
					}

					if ( self::inactive_already_triggered( $user_id, $email, $workflow_id, $days ) ) {
						continue;
					}

					$config   = $trigger['graph_node']['data']['config'] ?? [];
					$tag_ids  = array_values( array_filter( array_map( 'intval', (array) ( $config['tag_ids'] ?? [] ) ) ) );
					$list_ids = array_values( array_filter( array_map( 'intval', (array) ( $config['list_ids'] ?? [] ) ) ) );

					$contact_id = self::get_or_create_gemcrm_contact( $customer_data );

					if ( $contact_id ) {
						self::apply_gemcrm_tags_to_contact( $contact_id, $tag_ids );
						self::apply_gemcrm_lists_to_contact( $contact_id, $list_ids );
					}

					$payload = array_merge( $customer_data, [
						'contact_id' => $contact_id,
						'days'       => $days,
						'tag_ids'    => $tag_ids,
						'list_ids'   => $list_ids,
					] );

					$automation->run_workflow( $workflow_id, $payload );

					self::mark_inactive_triggered( $user_id, $email, $workflow_id );
				}
			}
		}
	}

	/**
	 * When a new order is placed, immediately remove inactive-customer tags
	 * from the matching GemCRM contact.
	 */
	public static function handle_inactive_order_placed( int $order_id ): void {
		if ( ! class_exists( 'WC_Order' ) || ! class_exists( \GemCrm\Database\Models\Tag::class ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( ! $email ) {
			return;
		}

		$workflows = Query::get_active_workflows_for_event( 'zaplane_woo_inactive_customer' );
		if ( empty( $workflows ) ) {
			return;
		}

		$tag_ids = [];
		foreach ( $workflows as $trigger ) {
			foreach ( (array) ( $trigger['graph_node']['data']['config']['tag_ids'] ?? [] ) as $tid ) {
				$tid = (int) $tid;
				if ( $tid > 0 ) {
					$tag_ids[] = $tid;
				}
			}
		}

		$tag_ids = array_unique( $tag_ids );
		if ( empty( $tag_ids ) || ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return;
		}

		$existing   = \GemCrm\Database\Models\Contact::index( [ 'email' => $email, 'per_page' => 1 ], null );
		$contact_id = (int) ( $existing['records'][0]['id'] ?? 0 );
		if ( ! $contact_id ) {
			return;
		}

		foreach ( $tag_ids as $tag_id ) {
			\GemCrm\Database\Models\Tag::detach_single( $contact_id, $tag_id );
		}
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private static function find_customers_inactive_for( int $days ): array {
		$target_start = gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) );
		$target_end   = gmdate( 'Y-m-d 23:59:59' );

		$orders = wc_get_orders( [
			'date_created' => $target_start . '...' . $target_end,
			'status'       => [ 'completed', 'processing' ],
			'limit'        => 500,
			'paginate'     => false,
			'type'         => 'shop_order',
		] );
		
		if ( empty( $orders ) ) {
			return [];
		}

		$seen   = [];
		$result = [];

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			$email = $order->get_billing_email();
			if ( ! $email || isset( $seen[ $email ] ) ) {
				continue;
			}
			$seen[ $email ] = true;

			// Ensure no newer order exists for this customer.
			$newer = wc_get_orders( [
				'billing_email' => $email,
				'date_created'  => '>' . $target_end,
				'status'        => [ 'completed', 'processing' ],
				'limit'         => 1,
				'paginate'      => false,
				'type'          => 'shop_order',
			] );

			if ( ! empty( $newer ) ) {
				continue;
			}

			$result[] = [
				'user_id'         => (int) $order->get_customer_id(),
				'email'           => $email,
				'first_name'      => $order->get_billing_first_name(),
				'last_name'       => $order->get_billing_last_name(),
				'phone'           => $order->get_billing_phone(),
				'last_order_date' => $order->get_date_created()
					? $order->get_date_created()->date( 'Y-m-d' )
					: '',
			];
		}

		return $result;
	}

	private static function inactive_already_triggered( int $user_id, string $email, int $workflow_id, int $days ): bool {
		$meta_key = '_zaplane_inactive_trigger_' . $workflow_id;

		$last_fired = $user_id > 0
			? (int) get_user_meta( $user_id, $meta_key, true )
			: (int) get_option( 'zaplane_inactive_guest_' . md5( $email ) . '_' . $workflow_id, 0 );

		if ( ! $last_fired ) {
			return false;
		}

		return ( time() - $last_fired ) < ( $days * DAY_IN_SECONDS );
	}

	private static function mark_inactive_triggered( int $user_id, string $email, int $workflow_id ): void {
		$meta_key = '_zaplane_inactive_trigger_' . $workflow_id;

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, $meta_key, time() );
		} else {
			update_option( 'zaplane_inactive_guest_' . md5( $email ) . '_' . $workflow_id, time(), 'no' );
		}
	}

	private static function get_or_create_gemcrm_contact( array $customer_data ): int {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return 0;
		}

		$email = $customer_data['email'] ?? '';
		if ( ! $email ) {
			return 0;
		}

		$existing = \GemCrm\Database\Models\Contact::index( [ 'email' => $email, 'per_page' => 1 ], null );
		$contact  = $existing['records'][0] ?? null;

		if ( $contact ) {
			return (int) ( $contact['id'] ?? 0 );
		}

		$new_contact = \GemCrm\Database\Models\Contact::create( [
			'first_name' => sanitize_text_field( $customer_data['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $customer_data['last_name'] ?? '' ),
			'email'      => sanitize_email( $email ),
			'phone'      => sanitize_text_field( $customer_data['phone'] ?? '' ),
			'status'     => 'subscribed',
		] );

		return $new_contact ? (int) ( $new_contact['id'] ?? 0 ) : 0;
	}

	private static function apply_gemcrm_tags_to_contact( int $contact_id, array $tag_ids ): void {
		if ( ! class_exists( \GemCrm\Database\Models\Tag::class ) || empty( $tag_ids ) ) {
			return;
		}
		foreach ( $tag_ids as $tag_id ) {
			if ( $tag_id > 0 ) {
				\GemCrm\Database\Models\Tag::attach_single( $contact_id, $tag_id );
			}
		}
	}

	private static function apply_gemcrm_lists_to_contact( int $contact_id, array $list_ids ): void {
		if ( ! class_exists( \GemCrm\Database\Models\ListModel::class ) || empty( $list_ids ) ) {
			return;
		}
		foreach ( $list_ids as $list_id ) {
			if ( $list_id > 0 ) {
				\GemCrm\Database\Models\ListModel::attach_single( $contact_id, $list_id );
			}
		}
	}
}
