<?php

namespace Zaplane\Database\Seeders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * StoreEngine Store Emails: a group recipe that sends a StoreEngine store's emails
 * from workflows, where each one can do more than send an email.
 *
 * Each of StoreEngine's order and subscription emails becomes a workflow, in a new
 * folder, that sends it through GemCRM, and a few come with a follow-up such as a
 * coupon. There are alerts for the store too, and a review request StoreEngine
 * doesn't have. While one of these workflows is on, StoreEngine's own copy of its
 * email is switched off (see HANDOVER and StoreengineEmailHandover), so the
 * customer gets one email, not two.
 *
 * The setup asks where store alerts go, when to ask for a review, and the discount
 * on the coupons. Steps read those as {{setup.store_email}}, {{setup.review_days}}
 * and {{setup.coupon_percent}}. See RecipeGroupBuilder.
 *
 * Steps read the order or subscription as {{trigger.*}}, which reaches the trigger
 * from any step. A coupon email reads the code as {{code}}: a bare name is a field
 * of the step just before, and Create Coupon passes on only its own fields, so the
 * email has to follow it directly.
 */
class StoreengineEmailsGroupSeeder {

	const SLUG = 'storeengine-store-emails';

	/**
	 * The StoreEngine email each workflow takes over, as "setting key.recipient".
	 * A workflow that isn't here sends an email StoreEngine doesn't have.
	 */
	const HANDOVER = [
		'order_confirmation'          => 'order_confirmation.customer',
		'new_order_alert'             => 'order_confirmation.admin',
		'order_status'                => 'order_status.customer',
		'order_note'                  => 'order_note.customer',
		'order_refund'                => 'order_refund.customer',
		'payment_failed'              => 'order_payment_failed.customer',
		'payment_failed_alert'        => 'order_payment_failed.admin',
		'item_shipped'                => 'order_item_shipped.customer',
		'order_delivered'             => 'order_delivered.customer',
		'order_cancelled'             => 'order_cancelled.customer',
		'subscription_renewed'        => 'subscription_renewed.customer',
		'subscription_cancelled'      => 'subscription_cancelled.customer',
		'subscription_renewal_failed' => 'subscription_renewal_failed.customer',
	];

	private const APPS = [
		'storeengine' => [ 'StoreEngine', 'storeengine.svg' ],
		'gemcrm'      => [ 'GemCRM', 'crm.svg' ],
		'filter'      => [ 'Filter', 'filter' ],
		'delay'       => [ 'Delay', 'delay' ],
	];

	public function run(): void {
		RecipeSeeding::save(
			self::SLUG,
			[
				'type'              => 'group',
				'title'             => 'StoreEngine Store Emails',
				'description'       => 'Send your StoreEngine store\'s emails from workflows you can build on: order confirmations, status, shipping and refund updates, failed payment recovery, subscription emails, alerts for the store, and a review request after delivery. While one of these workflows is on, StoreEngine\'s own copy of its email is switched off; pause the workflow and StoreEngine sends it again.',
				'blueprint'         => wp_json_encode( self::definition() ),
				'integration_icons' => wp_json_encode( [ 'storeengine.svg', 'crm.svg', 'delay' ] ),
			]
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function definition(): array {
		return [
			'folder'    => 'StoreEngine Store Emails',
			'values'    => [
				[
					'key'         => 'store_email',
					'type'        => 'text',
					'label'       => 'Send store alerts to',
					'description' => 'The address that hears about new orders and failed payments.',
					'default'     => (string) get_option( 'admin_email', '' ),
					'required'    => true,
				],
				[
					'key'         => 'review_days',
					'type'        => 'number',
					'label'       => 'Ask for a review after',
					'description' => 'How long after an order is delivered to ask for a review.',
					'default'     => 7,
					'min'         => 1,
					'max'         => 60,
					'suffix'      => 'days',
				],
				[
					'key'         => 'coupon_percent',
					'type'        => 'number',
					'label'       => 'Coupon discount',
					'description' => 'The discount on every coupon these emails send.',
					'default'     => 10,
					'min'         => 1,
					'max'         => 100,
					'suffix'      => '%',
				],
			],
			'workflows' => [
				self::order_confirmation(),
				self::new_order_alert(),
				self::order_status(),
				self::order_note(),
				self::order_refund(),
				self::payment_failed(),
				self::payment_failed_alert(),
				self::item_shipped(),
				self::order_delivered(),
				self::review_request(),
				self::order_cancelled(),
				self::subscription_renewed(),
				self::subscription_cancelled(),
				self::subscription_renewal_failed(),
			],
		];
	}

	private static function order_confirmation(): array {
		return [
			'key'         => 'order_confirmation',
			'title'       => 'Order confirmation',
			'description' => 'Emails the customer a receipt as soon as they place an order. Takes over StoreEngine\'s order confirmation email to the customer.',
			'graph'       => self::chain(
				[
					self::trigger( 'product_purchased', 'storeengine/checkout/after_place_order', 'Order Placed' ),
					self::email(
						'Send Order Confirmation',
						self::to_customer(),
						'Your order #{{trigger.order_number}} has been placed',
						'<p>Hi {{trigger.first_name}},</p><p>Thank you for your order. We\'ve received it, and we\'ll let you know when it\'s on its way.</p><p><strong>Order #{{trigger.order_number}}</strong> ({{trigger.order_date}})<br>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
					),
					self::wait( 1 ),
					self::coupon( 'Create Next-order Coupon', 'THANKS-{{trigger.order_id}}' ),
					self::email(
						'Send Next-order Coupon',
						self::to_customer(),
						'{{setup.coupon_percent}}% off your next order',
						'<p>Hi {{trigger.first_name}},</p><p>Thanks again for your order. Here is {{setup.coupon_percent}}% off the next one:</p><p><strong>{{code}}</strong></p><p>The code works once, at checkout.</p>'
					),
				]
			),
			'options'     => [
				[
					'key'     => 'next_order_coupon',
					'label'   => 'Send a coupon for the next order a day later',
					// A coupon on every order is a business decision, so it starts off.
					'default' => false,
					'nodes'   => [ '3', '4', '5' ],
				],
			],
		];
	}

	private static function new_order_alert(): array {
		return [
			'key'         => 'new_order_alert',
			'title'       => 'New order alert',
			'description' => 'Tells the store about each new order, with a link to it. Takes over StoreEngine\'s new order email to the admin.',
			'graph'       => self::chain(
				[
					self::trigger( 'product_purchased', 'storeengine/checkout/after_place_order', 'Order Placed' ),
					self::email(
						'Send New Order Alert',
						self::to_store(),
						'New order #{{trigger.order_number}} from {{trigger.customer_name}}',
						'<p>{{trigger.customer_name}} ({{trigger.customer_email}}) placed order #{{trigger.order_number}}.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}<br>Payment method: {{trigger.payment_method_title}}</p><p><a href="{{trigger.edit_order_url}}">Open the order</a></p>'
					),
				]
			),
		];
	}

	private static function order_status(): array {
		return [
			'key'         => 'order_status',
			'title'       => 'Order status updates',
			'description' => 'Emails the customer when their order moves to another status. Takes over StoreEngine\'s order status email.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_status_update', 'storeengine/order/status_changed', 'Order Status Changed' ),
					// As StoreEngine's own email does, stay quiet while checkout moves the order along.
					self::filter(
						'Skip Checkout Changes',
						[
							self::condition( '{{trigger.old_status}}', '!=', 'draft' ),
							self::condition( '{{trigger.during_checkout}}', 'is_false' ),
						]
					),
					self::email(
						'Send Status Update',
						self::to_customer(),
						'Your order #{{trigger.order_number}} has been updated',
						'<p>Hi {{trigger.first_name}},</p><p>Your order #{{trigger.order_number}} has moved from <strong>{{trigger.old_status_label}}</strong> to <strong>{{trigger.new_status_label}}</strong>.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
					),
				]
			),
		];
	}

	private static function order_note(): array {
		return [
			'key'         => 'order_note',
			'title'       => 'Notes to the customer',
			'description' => 'Emails the customer a note you add to their order. Takes over StoreEngine\'s order note email.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_customer_note_added', 'storeengine/order/new_customer_note', 'Customer Note Added' ),
					self::email(
						'Send Order Note',
						self::to_customer(),
						'A note about your order #{{trigger.order_number}}',
						'<p>Hi {{trigger.first_name}},</p><p>We\'ve added a note to your order #{{trigger.order_number}}:</p><blockquote>{{trigger.note}}</blockquote><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
					),
				]
			),
		];
	}

	private static function order_refund(): array {
		return [
			'key'         => 'order_refund',
			'title'       => 'Refund confirmation',
			'description' => 'Emails the customer when their order is refunded, in full or in part. Takes over StoreEngine\'s refund email.',
			'graph'       => self::fan_in(
				[
					self::trigger( 'order_fully_refunded', 'storeengine/order/fully_refunded', 'Order Fully Refunded' ),
					self::trigger( 'order_partially_refunded', 'storeengine/order/partially_refunded', 'Order Partially Refunded' ),
				],
				[
					// Either trigger can start the run, so the email reads whichever did.
					self::email(
						'Send Refund Confirmation',
						self::to_customer(),
						'Your refund for order #{{trigger.order_number}}',
						'<p>Hi {{trigger.first_name}},</p><p>We\'ve refunded {{trigger.refund_amount_formatted}} for your order #{{trigger.order_number}}. Depending on your bank, it can take a few days to show on your statement.</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
					),
				]
			),
		];
	}

	private static function payment_failed(): array {
		return [
			'key'         => 'payment_failed',
			'title'       => 'Failed payment recovery',
			'description' => 'When a payment fails, emails the customer a link to pay again. Takes over StoreEngine\'s failed payment email to the customer. Subscription renewals have a workflow of their own.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_status_failed', 'storeengine/order_status_payment_failed', 'Payment Failed' ),
					self::filter( 'Skip Renewals', [ self::condition( '{{trigger.is_renewal}}', 'is_false' ) ] ),
					self::email(
						'Send Payment Link',
						self::to_customer(),
						'Your payment for order #{{trigger.order_number}} didn\'t go through',
						'<p>Hi {{trigger.first_name}},</p><p>We couldn\'t take the payment for your order #{{trigger.order_number}}. Your order is saved, and you can try again with the link below.</p><p><a href="{{trigger.payment_url}}">Pay for your order</a></p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p>If you\'ve already paid, or need a hand, just reply to this email.</p>'
					),
					self::action(
						'storeengine',
						'add_order_note',
						'Note the Email on the Order',
						[
							'order_id'  => '{{trigger.order_id}}',
							'note'      => 'Sent the customer a link to pay again.',
							'note_type' => 'private',
						]
					),
				]
			),
			'options'     => [
				[
					'key'   => 'order_note',
					'label' => 'Add a private note to the order when the link is sent',
					'nodes' => [ '4' ],
				],
			],
		];
	}

	private static function payment_failed_alert(): array {
		return [
			'key'         => 'payment_failed_alert',
			'title'       => 'Failed payment alert',
			'description' => 'Tells the store when a customer\'s payment fails. Takes over StoreEngine\'s failed payment email to the admin.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_status_failed', 'storeengine/order_status_payment_failed', 'Payment Failed' ),
					self::filter( 'Skip Renewals', [ self::condition( '{{trigger.is_renewal}}', 'is_false' ) ] ),
					self::email(
						'Send Failed Payment Alert',
						self::to_store(),
						'Payment failed for order #{{trigger.order_number}}',
						'<p>The payment for order #{{trigger.order_number}} from {{trigger.customer_name}} ({{trigger.customer_email}}) failed.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}<br>Payment method: {{trigger.payment_method_title}}</p><p><a href="{{trigger.edit_order_url}}">Open the order</a></p>'
					),
				]
			),
		];
	}

	private static function item_shipped(): array {
		return [
			'key'         => 'item_shipped',
			'title'       => 'Shipping updates',
			'description' => 'Emails the customer the tracking details as each item moves towards them. Takes over StoreEngine\'s item shipped email.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_item_shipped', 'storeengine/order/item_shipped', 'Item Shipped' ),
					// Delivery has a workflow of its own.
					self::filter( 'Skip Deliveries', [ self::condition( '{{trigger.shipment_status}}', '!=', 'delivered' ) ] ),
					self::email(
						'Send Shipping Update',
						self::to_customer(),
						'Shipping update for your order #{{trigger.order_number}}',
						'<p>Hi {{trigger.first_name}},</p><p>Here\'s the latest on {{trigger.item_name}} from your order #{{trigger.order_number}}: <strong>{{trigger.shipment_status_label}}</strong>.</p><p>Courier: {{trigger.courier}}<br>Tracking number: {{trigger.tracking_number}}<br>Track it: {{trigger.tracking_url}}</p><p><a href="{{trigger.order_url}}">View your order</a></p>'
					),
				]
			),
		];
	}

	private static function order_delivered(): array {
		return [
			'key'         => 'order_delivered',
			'title'       => 'Delivery confirmation',
			'description' => 'Emails the customer when an item is delivered. Takes over StoreEngine\'s delivered email.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_item_shipped', 'storeengine/order/item_shipped', 'Item Delivered' ),
					self::filter( 'Only Deliveries', [ self::condition( '{{trigger.shipment_status}}', '==', 'delivered' ) ] ),
					self::email(
						'Send Delivery Confirmation',
						self::to_customer(),
						'{{trigger.item_name}} from your order #{{trigger.order_number}} has been delivered',
						'<p>Hi {{trigger.first_name}},</p><p>{{trigger.item_name}} from your order #{{trigger.order_number}} has been delivered. We hope you love it.</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If anything isn\'t right, just reply to this email.</p>'
					),
				]
			),
		];
	}

	private static function review_request(): array {
		return [
			'key'         => 'review_request',
			'title'       => 'Ask for a review',
			'description' => 'Once a whole order is delivered, waits a few days and asks the customer to review it. StoreEngine has no email like this.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_fully_delivered', 'storeengine/all_product_delivered', 'Order Delivered' ),
					self::wait( '{{setup.review_days}}' ),
					self::email(
						'Send Review Request',
						self::to_customer(),
						'How was your order, {{trigger.first_name}}?',
						'<p>Hi {{trigger.first_name}},</p><p>We hope you\'re enjoying your order #{{trigger.order_number}}: {{trigger.items_summary}}.</p><p>Would you tell other shoppers what you think? It only takes a minute.</p><p><a href="{{trigger.order_url}}">Review your order</a></p>'
					),
				]
			),
		];
	}

	private static function order_cancelled(): array {
		return [
			'key'         => 'order_cancelled',
			'title'       => 'Cancellation notice',
			'description' => 'Emails the customer when their order is cancelled. Takes over StoreEngine\'s cancelled order email.',
			'graph'       => self::chain(
				[
					self::trigger( 'order_status_cancelled', 'storeengine/order_status_cancelled', 'Order Cancelled' ),
					// An order that never left checkout wasn't placed, so there is nothing to tell.
					self::filter( 'Skip Unplaced Orders', [ self::condition( '{{trigger.old_status}}', '!=', 'draft' ) ] ),
					self::email(
						'Send Cancellation Notice',
						self::to_customer(),
						'Your order #{{trigger.order_number}} has been cancelled',
						'<p>Hi {{trigger.first_name}},</p><p>Your order #{{trigger.order_number}} from {{trigger.order_date}} has been cancelled. If you were charged, any refund due goes back to your original payment method.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p>If you didn\'t ask for this, or have any questions, just reply to this email.</p>'
					),
					self::wait( 3 ),
					self::coupon( 'Create Come-back Coupon', 'COMEBACK-{{trigger.order_id}}' ),
					self::email(
						'Send Come-back Coupon',
						self::to_customer(),
						'{{setup.coupon_percent}}% off if you\'d like to try again',
						'<p>Hi {{trigger.first_name}},</p><p>We\'re sorry your order didn\'t work out. If you\'d like to try again, here is {{setup.coupon_percent}}% off:</p><p><strong>{{code}}</strong></p><p>The code works once, at checkout.</p>'
					),
				]
			),
			'options'     => [
				[
					'key'     => 'come_back_coupon',
					'label'   => 'Send a coupon to come back 3 days later',
					'default' => false,
					'nodes'   => [ '4', '5', '6' ],
				],
			],
		];
	}

	private static function subscription_renewed(): array {
		return [
			'key'         => 'subscription_renewed',
			'title'       => 'Renewal receipt',
			'description' => 'Emails the customer a receipt each time their subscription renews. Takes over StoreEngine\'s renewal email, which is off unless you turned it on.',
			// Like StoreEngine's own, it starts off: most stores don't send a receipt for every renewal.
			'default'     => false,
			'graph'       => self::chain(
				[
					self::trigger( 'subscription_renewed', 'storeengine/subscription/renewal_payment_complete', 'Subscription Renewed' ),
					self::email(
						'Send Renewal Receipt',
						self::to_customer(),
						'Your subscription has renewed',
						'<p>Hi {{trigger.first_name}},</p><p>Your subscription #{{trigger.subscription_id}} has renewed, and your payment of {{trigger.total_formatted}} went through.</p><p>You don\'t need to do anything. If you have any questions, just reply to this email.</p>'
					),
				]
			),
		];
	}

	private static function subscription_cancelled(): array {
		return [
			'key'         => 'subscription_cancelled',
			'title'       => 'Subscription cancelled',
			'description' => 'Emails the customer when their subscription is cancelled. Takes over StoreEngine\'s cancelled subscription email.',
			'graph'       => self::chain(
				[
					self::trigger( 'subscription_cancelled', 'storeengine/subscription/status_cancelled', 'Subscription Cancelled' ),
					self::email(
						'Send Cancellation Confirmation',
						self::to_customer(),
						'Your subscription has been cancelled',
						'<p>Hi {{trigger.first_name}},</p><p>Your subscription #{{trigger.subscription_id}} has been cancelled and won\'t renew, so you won\'t be charged again.</p><p>If this was a mistake, you can subscribe again from your account at any time.</p>'
					),
					self::wait( 7 ),
					self::coupon( 'Create Win-back Coupon', 'WINBACK-{{trigger.subscription_id}}' ),
					self::email(
						'Send Win-back Coupon',
						self::to_customer(),
						'Come back for {{setup.coupon_percent}}% off',
						'<p>Hi {{trigger.first_name}},</p><p>We miss having you. If you\'d like to subscribe again, here is {{setup.coupon_percent}}% off:</p><p><strong>{{code}}</strong></p><p>The code works once, at checkout.</p>'
					),
				]
			),
			'options'     => [
				[
					'key'     => 'win_back',
					'label'   => 'Offer a coupon to come back a week later',
					'default' => false,
					'nodes'   => [ '3', '4', '5' ],
				],
			],
		];
	}

	private static function subscription_renewal_failed(): array {
		return [
			'key'         => 'subscription_renewal_failed',
			'title'       => 'Failed renewal recovery',
			'description' => 'When a renewal payment fails, emails the customer a link to pay before the subscription lapses. Takes over StoreEngine\'s failed renewal email.',
			'graph'       => self::chain(
				[
					self::trigger( 'subscription_renewal_payment_failed', 'storeengine/subscription/renewal_payment_failed', 'Renewal Payment Failed' ),
					self::email(
						'Send Renewal Payment Link',
						self::to_customer(),
						'Action needed: your subscription payment didn\'t go through',
						'<p>Hi {{trigger.first_name}},</p><p>We tried to renew your subscription #{{trigger.subscription_id}}, but the payment of {{trigger.total_formatted}} didn\'t go through, so your subscription is on hold.</p><p><a href="{{trigger.payment_url}}">Pay now to keep your subscription</a></p><p>If you\'ve already paid, or need a hand, just reply to this email.</p>'
					),
				]
			),
		];
	}

	/**
	 * Numbers the steps in order, lays them out left to right, and joins each one to the next.
	 *
	 * @param array<int,array<string,mixed>> $steps
	 * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array<string,string>>}
	 */
	private static function chain( array $steps ): array {
		$nodes = [];
		$edges = [];

		foreach ( array_values( $steps ) as $index => $step ) {
			$id = (string) ( $index + 1 );

			$nodes[] = array_merge(
				[
					'id'       => $id,
					'position' => [
						'x' => 80 + $index * 340,
						'y' => 200,
					],
				],
				$step
			);

			if ( $index > 0 ) {
				$edges[] = [
					'id'     => 'e' . $index . '-' . $id,
					'source' => (string) $index,
					'target' => $id,
				];
			}
		}

		return [
			'nodes' => $nodes,
			'edges' => $edges,
		];
	}

	/**
	 * Several triggers into one line of steps. The triggers are numbered first and
	 * stacked on the left, and each is joined to the first step.
	 *
	 * @param array<int,array<string,mixed>> $triggers
	 * @param array<int,array<string,mixed>> $steps
	 * @return array{nodes: array<int,array<string,mixed>>, edges: array<int,array<string,string>>}
	 */
	private static function fan_in( array $triggers, array $steps ): array {
		$triggers = array_values( $triggers );
		$count    = count( $triggers );
		$nodes    = [];
		$edges    = [];

		foreach ( $triggers as $index => $trigger ) {
			$nodes[] = array_merge(
				[
					'id'       => (string) ( $index + 1 ),
					'position' => [
						'x' => 80,
						'y' => 200 + ( 2 * $index - $count + 1 ) * 90,
					],
				],
				$trigger
			);
		}

		foreach ( array_values( $steps ) as $index => $step ) {
			$id = (string) ( $count + $index + 1 );

			$nodes[] = array_merge(
				[
					'id'       => $id,
					'position' => [
						'x' => 420 + $index * 340,
						'y' => 200,
					],
				],
				$step
			);

			foreach ( 0 === $index ? range( 1, $count ) : [ $count + $index ] as $source ) {
				$edges[] = [
					'id'     => 'e' . $source . '-' . $id,
					'source' => (string) $source,
					'target' => $id,
				];
			}
		}

		return [
			'nodes' => $nodes,
			'edges' => $edges,
		];
	}

	private static function trigger( string $event, string $hook, string $name ): array {
		return [
			'type' => 'trigger',
			'data' => [
				'app'    => 'storeengine',
				'event'  => $event,
				'hook'   => $hook,
				'label'  => self::APPS['storeengine'][0],
				'icon'   => self::APPS['storeengine'][1],
				'name'   => $name,
				'config' => [],
			],
		];
	}

	private static function action( string $app, string $event, string $name, array $config ): array {
		return [
			'type' => 'action',
			'data' => [
				'app'    => $app,
				'event'  => $event,
				'label'  => self::APPS[ $app ][0],
				'icon'   => self::APPS[ $app ][1],
				'name'   => $name,
				'config' => $config,
			],
		];
	}

	private static function email( string $name, array $recipient, string $subject, string $body ): array {
		return self::action(
			'gemcrm',
			'send_email',
			$name,
			array_merge(
				$recipient,
				[
					'subject'        => $subject,
					'content_source' => 'custom',
					'body'           => $body,
				]
			)
		);
	}

	/**
	 * The customer the trigger names.
	 */
	private static function to_customer(): array {
		return [
			'recipient_type' => 'custom',
			'custom_email'   => '{{trigger.customer_email}}',
		];
	}

	/**
	 * The store's address from the setup. Replying to an alert reaches the customer.
	 */
	private static function to_store(): array {
		return [
			'recipient_type' => 'custom',
			'custom_email'   => '{{setup.store_email}}',
			'reply_to_email' => '{{trigger.customer_email}}',
			'reply_to_name'  => '{{trigger.customer_name}}',
		];
	}

	/**
	 * A step that only lets the run go on when every condition holds.
	 *
	 * @param array<int,array<string,string>> $conditions
	 */
	private static function filter( string $name, array $conditions ): array {
		return self::action(
			'filter',
			'filter',
			$name,
			[
				'conditions' => [
					'logic'      => 'AND',
					'conditions' => $conditions,
				],
			]
		);
	}

	private static function condition( string $left, string $operator, string $right = '' ): array {
		return [
			'left'     => $left,
			'operator' => $operator,
			'right'    => $right,
		];
	}

	/**
	 * @param int|string $days A number, or the setup value that holds one.
	 */
	private static function wait( $days ): array {
		return self::action(
			'delay',
			'wait',
			1 === $days ? 'Wait 1 Day' : 'Wait ' . $days . ' Days',
			[
				'delay_type' => 'for',
				'unit'       => 'days',
				'amount'     => $days,
			]
		);
	}

	/**
	 * A one-use coupon. The step right after it reads the code as {{code}}.
	 */
	private static function coupon( string $name, string $code ): array {
		return self::action(
			'storeengine',
			'create_coupon',
			$name,
			[
				'code'          => $code,
				// A code made from an order or subscription number could be guessed.
				'random_suffix' => true,
				'coupon_type'   => 'percentage',
				'amount'        => '{{setup.coupon_percent}}',
				'usage_limit'   => '1',
			]
		);
	}
}
