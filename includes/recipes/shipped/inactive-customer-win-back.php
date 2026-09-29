<?php
/**
 * Inactive Customer Win-back: reminds a customer who stopped ordering, then sends
 * them a personal coupon a week later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'Inactive Customer Win-back',
	'description' => 'Re-engage WooCommerce customers who have not ordered in a configurable number of days. Sends a reminder email, waits 7 days, creates a personal discount coupon, then sends the coupon by email. Tags are automatically removed from the GemCRM contact as soon as the customer places a new order.',
	'steps'       => [
		[
			'trigger' => 'woocommerce.inactive_customer',
			'name'    => 'Inactive Customer',
			'config'  => [
				'days'     => '30',
				'tag_ids'  => [],
				'list_ids' => [],
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Win-back Reminder',
			'config' => [
				'recipient_type' => 'custom',
				'custom_email'   => '{{trigger.email}}',
				'subject'        => 'We miss you, {{trigger.first_name}}! Come back for something special',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.first_name}},</p><p>It\'s been a while since your last order and we\'ve been thinking about you. We\'d love to have you back!</p><p>Stay tuned — an exclusive discount is on its way to thank you for being a valued customer.</p><p>If you have any questions, feel free to reply to this email.</p>',
			],
		],
		[
			'action' => 'delay.wait',
			'name'   => 'Wait 7 Days',
			'config' => [
				'unit'   => 'days',
				'amount' => 7,
			],
		],
		[
			'action' => 'woocommerce.create_coupon',
			'name'   => 'Create Win-back Coupon',
			'config' => [
				'code'               => 'WINBACK-{{trigger.id}}',
				'discount_type'      => 'percent',
				'amount'             => '15',
				'usage_limit'        => '1',
				'expiry_date'        => '',
				'email_restrictions' => '{{trigger.email}}',
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Coupon Email',
			'config' => [
				'recipient_type' => 'custom',
				'custom_email'   => '{{trigger.email}}',
				'subject'        => 'Your exclusive win-back offer is here, {{trigger.first_name}}!',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.first_name}},</p><p>As a thank-you for being a loyal customer, here is a special discount just for you:</p><p><strong>{{coupon.code}}</strong></p><p>Use it at checkout to get 15% off your next purchase. This code is valid for one use only, so grab it before it\'s gone!</p><p>We can\'t wait to see you back.</p>',
			],
		],
	],
];
