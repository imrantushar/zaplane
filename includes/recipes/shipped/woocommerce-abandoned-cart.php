<?php
/**
 * WooCommerce Abandoned Cart: emails a shopper who left items in their cart, and
 * again three days later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'WooCommerce Abandoned Cart',
	'description' => 'Automatically follow up with customers who abandoned their WooCommerce cart. Sends an initial email, waits 3 days, then sends a follow-up email.',
	'steps'       => [
		[
			'trigger' => 'woocommerce.cart_abandoned',
			'name'    => 'Abandoned Cart',
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Cart Reminder',
			'config' => [
				'recipient_type' => 'custom',
				'custom_email'   => '{{trigger.email}}',
				'subject'        => 'You left something behind — your cart is waiting',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.full_name}},</p><p>It looks like you left some items in your cart. Don\'t worry — we\'ve saved everything for you.</p><p><a href="{{trigger.recovery_link}}">Complete your purchase</a></p><p>If you have any questions, feel free to reply to this email.</p>',
			],
		],
		[
			'action' => 'delay.wait',
			'name'   => 'Wait 3 Days',
			'config' => [
				'unit'   => 'days',
				'amount' => 3,
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Follow-up Email',
			'config' => [
				'recipient_type' => 'custom',
				'custom_email'   => '{{trigger.email}}',
				'subject'        => 'Last chance — your cart is about to expire',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.full_name}},</p><p>This is a friendly reminder that the items in your cart are still available, but they may not be for long.</p><p><a href="{{trigger.recovery_link}}">Claim your cart now</a></p><p>If you\'ve already completed your purchase, please ignore this email.</p>',
			],
		],
	],
];
