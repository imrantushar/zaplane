<?php
/**
 * WooCommerce Customer Lifecycle: a group recipe that sets up a store's customer
 * emails in one go.
 *
 * Each automation becomes its own workflow, in a new folder. The setup switches
 * them on or off, along with a few of their steps, and asks for the coupon
 * discount and how long a customer can go without ordering. Steps read those as
 * {{setup.coupon_percent}} and {{setup.inactive_days}}, which are filled in when
 * the workflows are created. See RecipeGroupBuilder.
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

// The email address the trigger reports, for triggers that aren't a GemCRM contact.
$to_email = [
	'recipient_type' => 'custom',
	'custom_email'   => '{{trigger.email}}',
];

$to_contact = [
	'recipient_type' => 'contact',
	'contact_id'     => '{{trigger.id}}',
];

$wait = static function ( int $days, array $step = [] ): array {
	return array_merge(
		[
			'action' => 'delay.wait',
			'name'   => 'Wait ' . $days . ' Days',
			'config' => [
				'unit'   => 'days',
				'amount' => $days,
			],
		],
		$step
	);
};

$coupon = static function ( string $name, string $code, array $step = [] ): array {
	return array_merge(
		[
			'action' => 'woocommerce.create_coupon',
			'name'   => $name,
			'config' => [
				'code'               => $code,
				'discount_type'      => 'percent',
				'amount'             => '{{setup.coupon_percent}}',
				'usage_limit'        => '1',
				'expiry_date'        => '',
				'email_restrictions' => '{{trigger.email}}',
			],
		],
		$step
	);
};

return [
	'title'       => 'WooCommerce Customer Lifecycle',
	'description' => 'Set up your store\'s customer emails in one go: recover abandoned carts, thank buyers with a coupon, ask for feedback, win back customers who stopped ordering, and send birthday coupons. Pick the ones you want; each becomes its own workflow in a new folder.',
	'values'      => [
		[
			'key'         => 'coupon_percent',
			'type'        => 'number',
			'label'       => 'Coupon discount',
			'description' => 'The discount on every coupon these emails send.',
			'default'     => 15,
			'min'         => 1,
			'max'         => 100,
			'suffix'      => '%',
		],
		[
			'key'         => 'inactive_days',
			'type'        => 'number',
			'label'       => 'Days without an order',
			'description' => 'How long a customer goes without ordering before the win-back email is sent.',
			'default'     => 60,
			'min'         => 7,
			'max'         => 365,
			'suffix'      => 'days',
		],
	],
	'workflows'   => [
		[
			'key'         => 'abandoned_cart',
			'title'       => 'Recover abandoned carts',
			'description' => 'Emails shoppers who leave items in their cart, with a link back to it.',
			'options'     => [
				[
					'key'   => 'last_chance',
					'label' => 'Send a last-chance email 3 days later',
				],
			],
			'steps'       => [
				[
					'trigger' => 'woocommerce.cart_abandoned',
					'name'    => 'Abandoned Cart',
				],
				$email(
					'Send Cart Reminder',
					$to_email,
					'You left something in your cart',
					'<p>Hi {{trigger.full_name}},</p><p>It looks like you left some items in your cart. We\'ve saved them for you.</p><p><a href="{{trigger.recovery_link}}">Complete your purchase</a></p><p>If you have any questions, just reply to this email.</p>'
				),
				$wait( 3, [ 'option' => 'last_chance' ] ),
				$email(
					'Send Last-chance Email',
					$to_email,
					'Your cart is still saved',
					'<p>Hi {{trigger.full_name}},</p><p>Your cart is still saved, but items can sell out.</p><p><a href="{{trigger.recovery_link}}">Go back to your cart</a></p><p>If you\'ve already completed your purchase, please ignore this email.</p>',
					[ 'option' => 'last_chance' ]
				),
			],
		],
		[
			'key'         => 'thank_you_coupon',
			'title'       => 'Thank buyers with a coupon',
			'description' => 'Sends a one-time coupon for the next order as soon as an order is placed.',
			// A coupon on every order is a business decision, so it starts off.
			'default'     => false,
			'steps'       => [
				[
					'trigger' => 'woocommerce.order_status_processing',
					'name'    => 'Order Placed',
				],
				$coupon( 'Create Thank-you Coupon', 'THANKS-{{trigger.order_id}}' ),
				$email(
					'Send Thank-you Coupon',
					$to_email,
					'Thank you for your order, {{trigger.first_name}}',
					'<p>Hi {{trigger.first_name}},</p><p>Thank you for your order #{{trigger.order_number}}. As a thank-you, here is {{setup.coupon_percent}}% off your next order:</p><p><strong>{{coupon.code}}</strong></p><p>The code works once, at checkout.</p>'
				),
			],
		],
		[
			'key'         => 'feedback_request',
			'title'       => 'Ask for feedback',
			'description' => 'Two days after an order is completed, asks the customer how it went.',
			'options'     => [
				[
					'key'   => 'order_note',
					'label' => 'Add a private note to the order when the request is sent',
				],
			],
			'steps'       => [
				[
					'trigger' => 'woocommerce.order_status_completed',
					'name'    => 'Order Completed',
				],
				$wait( 2 ),
				$email(
					'Send Feedback Request',
					$to_email,
					'How was your order, {{trigger.first_name}}?',
					'<p>Hi {{trigger.first_name}},</p><p>Thank you for your order #{{trigger.order_number}}. We hope you\'re enjoying it.</p><p>Would you tell us how it went? It only takes a minute.</p><p><a href="{{trigger.feedback_page_url}}">Leave your feedback</a></p>'
				),
				[
					'action' => 'woocommerce.add_order_note',
					'name'   => 'Note the Request on the Order',
					'option' => 'order_note',
					'config' => [
						'order_id'         => '{{trigger.order_id}}',
						'note'             => 'Feedback request sent to {{trigger.email}}.',
						'is_customer_note' => false,
					],
				],
			],
		],
		[
			'key'         => 'win_back',
			'title'       => 'Win back inactive customers',
			'description' => 'Reaches out to customers who haven\'t ordered for a while.',
			'options'     => [
				[
					'key'   => 'coupon',
					'label' => 'Follow up with a personal coupon a week later',
				],
			],
			'steps'       => [
				[
					'trigger' => 'woocommerce.inactive_customer',
					'name'    => 'Inactive Customer',
					'config'  => [
						'days'     => '{{setup.inactive_days}}',
						'tag_ids'  => [],
						'list_ids' => [],
					],
				],
				$email(
					'Send We-miss-you Email',
					$to_email,
					'We miss you, {{trigger.first_name}}',
					'<p>Hi {{trigger.first_name}},</p><p>It\'s been a while since your last order, and we\'d love to see you again.</p><p>Come and see what\'s new in the store.</p>'
				),
				$wait( 7, [ 'option' => 'coupon' ] ),
				$coupon( 'Create Win-back Coupon', 'WINBACK-{{trigger.id}}', [ 'option' => 'coupon' ] ),
				$email(
					'Send Win-back Coupon',
					$to_email,
					'A little something to welcome you back',
					'<p>Hi {{trigger.first_name}},</p><p>Here is {{setup.coupon_percent}}% off your next order, just for you:</p><p><strong>{{coupon.code}}</strong></p><p>The code works once, at checkout.</p>',
					[ 'option' => 'coupon' ]
				),
			],
		],
		[
			'key'         => 'birthday_coupon',
			'title'       => 'Send birthday coupons',
			'description' => 'Emails a coupon to GemCRM contacts on their birthday.',
			'options'     => [
				[
					'key'   => 'reminder',
					'label' => 'Send a reminder a week later',
				],
			],
			'steps'       => [
				[
					'trigger' => 'gemcrm.contact_birthday',
					'name'    => 'Contact Birthday',
					'config'  => [ 'purchase_tag_id' => '' ],
				],
				$coupon( 'Create Birthday Coupon', 'BDAY-{{trigger.id}}' ),
				$email(
					'Send Birthday Coupon',
					$to_contact,
					'Happy birthday, {{trigger.first_name}}!',
					'<p>Hi {{trigger.first_name}},</p><p>Happy birthday! Here is {{setup.coupon_percent}}% off, as our gift to you:</p><p><strong>{{coupon.code}}</strong></p><p>The code works once, at checkout.</p>'
				),
				$wait( 7, [ 'option' => 'reminder' ] ),
				$email(
					'Send Birthday Reminder',
					$to_contact,
					'Your birthday coupon is still yours',
					'<p>Hi {{trigger.first_name}},</p><p>A reminder that your birthday coupon is still yours to use:</p><p><strong>{{coupon.code}}</strong></p>',
					[ 'option' => 'reminder' ]
				),
			],
		],
	],
];
