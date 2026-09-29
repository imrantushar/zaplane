<?php
/**
 * StoreEngine Store Emails: a group recipe that sends a StoreEngine store's emails
 * from workflows, where each one can do more than send an email.
 *
 * Each of StoreEngine's order and subscription emails becomes a workflow, in a new
 * folder, that sends it through GemCRM, and a few come with a follow-up such as a
 * coupon. There are alerts for the store too, and a review request StoreEngine
 * doesn't have. While one of these workflows is on, StoreEngine's own copy of its
 * email is switched off (see StoreengineEmailHandover), so the customer gets one
 * email, not two.
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ShippedRecipes::register() requires this file inside a method, so its variables are local.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$email = static function ( string $name, array $recipient, string $subject, string $body, array $step = [] ): array {
	return array_merge(
		[
			'action' => 'gemcrm.send_email',
			'name'   => $name,
			'config' => array_merge(
				$recipient,
				[
					'subject'        => $subject,
					'content_source' => 'custom',
					'body'           => $body,
				]
			),
		],
		$step
	);
};

// The customer the trigger names.
$to_customer = [
	'recipient_type' => 'custom',
	'custom_email'   => '{{trigger.customer_email}}',
];

// The store's address from the setup. Replying to an alert reaches the customer.
$to_store = [
	'recipient_type' => 'custom',
	'custom_email'   => '{{setup.store_email}}',
	'reply_to_email' => '{{trigger.customer_email}}',
	'reply_to_name'  => '{{trigger.customer_name}}',
];

// A step that only lets the run go on when every condition holds.
$filter = static function ( string $name, array $conditions ): array {
	return [
		'action' => 'filter.filter',
		'name'   => $name,
		'config' => [
			'conditions' => [
				'logic'      => 'AND',
				'conditions' => $conditions,
			],
		],
	];
};

$condition = static function ( string $left, string $operator, string $right = '' ): array {
	return [
		'left'     => $left,
		'operator' => $operator,
		'right'    => $right,
	];
};

// $days is a number, or the setup value that holds one.
$wait = static function ( $days, array $step = [] ): array {
	return array_merge(
		[
			'action' => 'delay.wait',
			'name'   => 1 === $days ? 'Wait 1 Day' : 'Wait ' . $days . ' Days',
			'config' => [
				'delay_type' => 'for',
				'unit'       => 'days',
				'amount'     => $days,
			],
		],
		$step
	);
};

// A one-use coupon. The step right after it reads the code as {{code}}.
$coupon = static function ( string $name, string $code, array $step = [] ): array {
	return array_merge(
		[
			'action' => 'storeengine.create_coupon',
			'name'   => $name,
			'config' => [
				'code'          => $code,
				// A code made from an order or subscription number could be guessed.
				'random_suffix' => true,
				'coupon_type'   => 'percentage',
				'amount'        => '{{setup.coupon_percent}}',
				'usage_limit'   => '1',
			],
		],
		$step
	);
};

$renewal_skip = $filter( 'Skip Renewals', [ $condition( '{{trigger.is_renewal}}', 'is_false' ) ] );

return [
	'title'       => 'StoreEngine Store Emails',
	'description' => 'Send your StoreEngine store\'s emails from workflows you can build on: order confirmations, status, shipping and refund updates, failed payment recovery, subscription emails, alerts for the store, and a review request after delivery. While one of these workflows is on, StoreEngine\'s own copy of its email is switched off; pause the workflow and StoreEngine sends it again.',
	'icons'       => [ 'storeengine.svg', 'crm.svg', 'delay' ],
	'values'      => [
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
	'workflows'   => [
		[
			'key'         => 'order_confirmation',
			'title'       => 'Order confirmation',
			'description' => 'Emails the customer a receipt as soon as they place an order. Takes over StoreEngine\'s order confirmation email to the customer.',
			'options'     => [
				[
					'key'     => 'next_order_coupon',
					'label'   => 'Send a coupon for the next order a day later',
					// A coupon on every order is a business decision, so it starts off.
					'default' => false,
				],
			],
			'steps'       => [
				[
					'trigger' => 'storeengine.product_purchased',
					'name'    => 'Order Placed',
				],
				$email(
					'Send Order Confirmation',
					$to_customer,
					'Your order #{{trigger.order_number}} has been placed',
					'<p>Hi {{trigger.first_name}},</p><p>Thank you for your order. We\'ve received it, and we\'ll let you know when it\'s on its way.</p><p><strong>Order #{{trigger.order_number}}</strong> ({{trigger.order_date}})<br>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
				),
				$wait( 1, [ 'option' => 'next_order_coupon' ] ),
				$coupon( 'Create Next-order Coupon', 'THANKS-{{trigger.order_id}}', [ 'option' => 'next_order_coupon' ] ),
				$email(
					'Send Next-order Coupon',
					$to_customer,
					'{{setup.coupon_percent}}% off your next order',
					'<p>Hi {{trigger.first_name}},</p><p>Thanks again for your order. Here is {{setup.coupon_percent}}% off the next one:</p><p><strong>{{code}}</strong></p><p>The code works once, at checkout.</p>',
					[ 'option' => 'next_order_coupon' ]
				),
			],
		],
		[
			'key'         => 'new_order_alert',
			'title'       => 'New order alert',
			'description' => 'Tells the store about each new order, with a link to it. Takes over StoreEngine\'s new order email to the admin.',
			'steps'       => [
				[
					'trigger' => 'storeengine.product_purchased',
					'name'    => 'Order Placed',
				],
				$email(
					'Send New Order Alert',
					$to_store,
					'New order #{{trigger.order_number}} from {{trigger.customer_name}}',
					'<p>{{trigger.customer_name}} ({{trigger.customer_email}}) placed order #{{trigger.order_number}}.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}<br>Payment method: {{trigger.payment_method_title}}</p><p><a href="{{trigger.edit_order_url}}">Open the order</a></p>'
				),
			],
		],
		[
			'key'         => 'order_status',
			'title'       => 'Order status updates',
			'description' => 'Emails the customer when their order moves to another status. Takes over StoreEngine\'s order status email.',
			'steps'       => [
				[
					'trigger' => 'storeengine.order_status_update',
					'name'    => 'Order Status Changed',
				],
				// As StoreEngine's own email does, stay quiet while checkout moves the order along.
				$filter(
					'Skip Checkout Changes',
					[
						$condition( '{{trigger.old_status}}', '!=', 'draft' ),
						$condition( '{{trigger.during_checkout}}', 'is_false' ),
					]
				),
				$email(
					'Send Status Update',
					$to_customer,
					'Your order #{{trigger.order_number}} has been updated',
					'<p>Hi {{trigger.first_name}},</p><p>Your order #{{trigger.order_number}} has moved from <strong>{{trigger.old_status_label}}</strong> to <strong>{{trigger.new_status_label}}</strong>.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
				),
			],
		],
		[
			'key'         => 'order_note',
			'title'       => 'Notes to the customer',
			'description' => 'Emails the customer a note you add to their order. Takes over StoreEngine\'s order note email.',
			'steps'       => [
				[
					'trigger' => 'storeengine.order_customer_note_added',
					'name'    => 'Customer Note Added',
				],
				$email(
					'Send Order Note',
					$to_customer,
					'A note about your order #{{trigger.order_number}}',
					'<p>Hi {{trigger.first_name}},</p><p>We\'ve added a note to your order #{{trigger.order_number}}:</p><blockquote>{{trigger.note}}</blockquote><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
				),
			],
		],
		[
			'key'         => 'order_refund',
			'title'       => 'Refund confirmation',
			'description' => 'Emails the customer when their order is refunded, in full or in part. Takes over StoreEngine\'s refund email.',
			'steps'       => [
				[
					'trigger' => 'storeengine.order_fully_refunded',
					'name'    => 'Order Fully Refunded',
				],
				[
					'trigger' => 'storeengine.order_partially_refunded',
					'name'    => 'Order Partially Refunded',
				],
				// Either trigger can start the run, so the email reads whichever did.
				$email(
					'Send Refund Confirmation',
					$to_customer,
					'Your refund for order #{{trigger.order_number}}',
					'<p>Hi {{trigger.first_name}},</p><p>We\'ve refunded {{trigger.refund_amount_formatted}} for your order #{{trigger.order_number}}. Depending on your bank, it can take a few days to show on your statement.</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If you have any questions, just reply to this email.</p>'
				),
			],
		],
		[
			'key'         => 'payment_failed',
			'title'       => 'Failed payment recovery',
			'description' => 'When a payment fails, emails the customer a link to pay again. Takes over StoreEngine\'s failed payment email to the customer. Subscription renewals have a workflow of their own.',
			'options'     => [
				[
					'key'   => 'order_note',
					'label' => 'Add a private note to the order when the link is sent',
				],
			],
			'steps'       => [
				[
					'trigger' => 'storeengine.order_status_failed',
					'name'    => 'Payment Failed',
				],
				$renewal_skip,
				$email(
					'Send Payment Link',
					$to_customer,
					'Your payment for order #{{trigger.order_number}} didn\'t go through',
					'<p>Hi {{trigger.first_name}},</p><p>We couldn\'t take the payment for your order #{{trigger.order_number}}. Your order is saved, and you can try again with the link below.</p><p><a href="{{trigger.payment_url}}">Pay for your order</a></p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p>If you\'ve already paid, or need a hand, just reply to this email.</p>'
				),
				[
					'action' => 'storeengine.add_order_note',
					'name'   => 'Note the Email on the Order',
					'option' => 'order_note',
					'config' => [
						'order_id'  => '{{trigger.order_id}}',
						'note'      => 'Sent the customer a link to pay again.',
						'note_type' => 'private',
					],
				],
			],
		],
		[
			'key'         => 'payment_failed_alert',
			'title'       => 'Failed payment alert',
			'description' => 'Tells the store when a customer\'s payment fails. Takes over StoreEngine\'s failed payment email to the admin.',
			'steps'       => [
				[
					'trigger' => 'storeengine.order_status_failed',
					'name'    => 'Payment Failed',
				],
				$renewal_skip,
				$email(
					'Send Failed Payment Alert',
					$to_store,
					'Payment failed for order #{{trigger.order_number}}',
					'<p>The payment for order #{{trigger.order_number}} from {{trigger.customer_name}} ({{trigger.customer_email}}) failed.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}<br>Payment method: {{trigger.payment_method_title}}</p><p><a href="{{trigger.edit_order_url}}">Open the order</a></p>'
				),
			],
		],
		[
			'key'         => 'item_shipped',
			'title'       => 'Shipping updates',
			'description' => 'Emails the customer the tracking details as each item moves towards them. Takes over StoreEngine\'s item shipped email.',
			'steps'       => [
				[
					'trigger' => 'storeengine.order_item_shipped',
					'name'    => 'Item Shipped',
				],
				// Delivery has a workflow of its own.
				$filter( 'Skip Deliveries', [ $condition( '{{trigger.shipment_status}}', '!=', 'delivered' ) ] ),
				$email(
					'Send Shipping Update',
					$to_customer,
					'Shipping update for your order #{{trigger.order_number}}',
					'<p>Hi {{trigger.first_name}},</p><p>Here\'s the latest on {{trigger.item_name}} from your order #{{trigger.order_number}}: <strong>{{trigger.shipment_status_label}}</strong>.</p><p>Courier: {{trigger.courier}}<br>Tracking number: {{trigger.tracking_number}}<br>Track it: {{trigger.tracking_url}}</p><p><a href="{{trigger.order_url}}">View your order</a></p>'
				),
			],
		],
		[
			'key'         => 'order_delivered',
			'title'       => 'Delivery confirmation',
			'description' => 'Emails the customer when an item is delivered. Takes over StoreEngine\'s delivered email.',
			'steps'       => [
				[
					'trigger' => 'storeengine.order_item_shipped',
					'name'    => 'Item Delivered',
				],
				$filter( 'Only Deliveries', [ $condition( '{{trigger.shipment_status}}', '==', 'delivered' ) ] ),
				$email(
					'Send Delivery Confirmation',
					$to_customer,
					'{{trigger.item_name}} from your order #{{trigger.order_number}} has been delivered',
					'<p>Hi {{trigger.first_name}},</p><p>{{trigger.item_name}} from your order #{{trigger.order_number}} has been delivered. We hope you love it.</p><p><a href="{{trigger.order_url}}">View your order</a></p><p>If anything isn\'t right, just reply to this email.</p>'
				),
			],
		],
		[
			'key'         => 'review_request',
			'title'       => 'Ask for a review',
			'description' => 'Once a whole order is delivered, waits a few days and asks the customer to review it. StoreEngine has no email like this.',
			'steps'       => [
				[
					'trigger' => 'storeengine.order_fully_delivered',
					'name'    => 'Order Delivered',
				],
				$wait( '{{setup.review_days}}' ),
				$email(
					'Send Review Request',
					$to_customer,
					'How was your order, {{trigger.first_name}}?',
					'<p>Hi {{trigger.first_name}},</p><p>We hope you\'re enjoying your order #{{trigger.order_number}}: {{trigger.items_summary}}.</p><p>Would you tell other shoppers what you think? It only takes a minute.</p><p><a href="{{trigger.order_url}}">Review your order</a></p>'
				),
			],
		],
		[
			'key'         => 'order_cancelled',
			'title'       => 'Cancellation notice',
			'description' => 'Emails the customer when their order is cancelled. Takes over StoreEngine\'s cancelled order email.',
			'options'     => [
				[
					'key'     => 'come_back_coupon',
					'label'   => 'Send a coupon to come back 3 days later',
					'default' => false,
				],
			],
			'steps'       => [
				[
					'trigger' => 'storeengine.order_status_cancelled',
					'name'    => 'Order Cancelled',
				],
				// An order that never left checkout wasn't placed, so there is nothing to tell.
				$filter( 'Skip Unplaced Orders', [ $condition( '{{trigger.old_status}}', '!=', 'draft' ) ] ),
				$email(
					'Send Cancellation Notice',
					$to_customer,
					'Your order #{{trigger.order_number}} has been cancelled',
					'<p>Hi {{trigger.first_name}},</p><p>Your order #{{trigger.order_number}} from {{trigger.order_date}} has been cancelled. If you were charged, any refund due goes back to your original payment method.</p><p>{{trigger.items_summary}}<br>Total: {{trigger.total_formatted}}</p><p>If you didn\'t ask for this, or have any questions, just reply to this email.</p>'
				),
				$wait( 3, [ 'option' => 'come_back_coupon' ] ),
				$coupon( 'Create Come-back Coupon', 'COMEBACK-{{trigger.order_id}}', [ 'option' => 'come_back_coupon' ] ),
				$email(
					'Send Come-back Coupon',
					$to_customer,
					'{{setup.coupon_percent}}% off if you\'d like to try again',
					'<p>Hi {{trigger.first_name}},</p><p>We\'re sorry your order didn\'t work out. If you\'d like to try again, here is {{setup.coupon_percent}}% off:</p><p><strong>{{code}}</strong></p><p>The code works once, at checkout.</p>',
					[ 'option' => 'come_back_coupon' ]
				),
			],
		],
		[
			'key'         => 'subscription_renewed',
			'title'       => 'Renewal receipt',
			'description' => 'Emails the customer a receipt each time their subscription renews. Takes over StoreEngine\'s renewal email, which is off unless you turned it on.',
			// Like StoreEngine's own, it starts off: most stores don't send a receipt for every renewal.
			'default'     => false,
			'steps'       => [
				[
					'trigger' => 'storeengine.subscription_renewed',
					'name'    => 'Subscription Renewed',
				],
				$email(
					'Send Renewal Receipt',
					$to_customer,
					'Your subscription has renewed',
					'<p>Hi {{trigger.first_name}},</p><p>Your subscription #{{trigger.subscription_id}} has renewed, and your payment of {{trigger.total_formatted}} went through.</p><p>You don\'t need to do anything. If you have any questions, just reply to this email.</p>'
				),
			],
		],
		[
			'key'         => 'subscription_cancelled',
			'title'       => 'Subscription cancelled',
			'description' => 'Emails the customer when their subscription is cancelled. Takes over StoreEngine\'s cancelled subscription email.',
			'options'     => [
				[
					'key'     => 'win_back',
					'label'   => 'Offer a coupon to come back a week later',
					'default' => false,
				],
			],
			'steps'       => [
				[
					'trigger' => 'storeengine.subscription_cancelled',
					'name'    => 'Subscription Cancelled',
				],
				$email(
					'Send Cancellation Confirmation',
					$to_customer,
					'Your subscription has been cancelled',
					'<p>Hi {{trigger.first_name}},</p><p>Your subscription #{{trigger.subscription_id}} has been cancelled and won\'t renew, so you won\'t be charged again.</p><p>If this was a mistake, you can subscribe again from your account at any time.</p>'
				),
				$wait( 7, [ 'option' => 'win_back' ] ),
				$coupon( 'Create Win-back Coupon', 'WINBACK-{{trigger.subscription_id}}', [ 'option' => 'win_back' ] ),
				$email(
					'Send Win-back Coupon',
					$to_customer,
					'Come back for {{setup.coupon_percent}}% off',
					'<p>Hi {{trigger.first_name}},</p><p>We miss having you. If you\'d like to subscribe again, here is {{setup.coupon_percent}}% off:</p><p><strong>{{code}}</strong></p><p>The code works once, at checkout.</p>',
					[ 'option' => 'win_back' ]
				),
			],
		],
		[
			'key'         => 'subscription_renewal_failed',
			'title'       => 'Failed renewal recovery',
			'description' => 'When a renewal payment fails, emails the customer a link to pay before the subscription lapses. Takes over StoreEngine\'s failed renewal email.',
			'steps'       => [
				[
					'trigger' => 'storeengine.subscription_renewal_payment_failed',
					'name'    => 'Renewal Payment Failed',
				],
				$email(
					'Send Renewal Payment Link',
					$to_customer,
					'Action needed: your subscription payment didn\'t go through',
					'<p>Hi {{trigger.first_name}},</p><p>We tried to renew your subscription #{{trigger.subscription_id}}, but the payment of {{trigger.total_formatted}} didn\'t go through, so your subscription is on hold.</p><p><a href="{{trigger.payment_url}}">Pay now to keep your subscription</a></p><p>If you\'ve already paid, or need a hand, just reply to this email.</p>'
				),
			],
		],
	],
];
