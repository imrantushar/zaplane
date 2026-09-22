<?php
/**
 * WooCommerce Post Purchase Upsell: as soon as an order is placed, emails the
 * customer a coupon for their next one.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'WooCommerce Post Purchase Upsell',
	'description' => 'Automatically reward customers immediately after a WooCommerce order is placed. Creates a personalised discount coupon and sends it to the customer by email, encouraging a repeat purchase.',
	'steps'       => [
		[
			'trigger' => 'woocommerce.order_status_processing',
			'name'    => 'Order Placed',
		],
		[
			'action' => 'woocommerce.create_coupon',
			'name'   => 'Create Upsell Coupon',
			'config' => [
				'code'               => 'UPSELL-{{trigger.order_id}}',
				'discount_type'      => 'percent',
				'amount'             => '20',
				'usage_limit'        => '1',
				'expiry_date'        => '',
				'email_restrictions' => '{{trigger.email}}',
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Upsell Offer Email',
			'config' => [
				'recipient_type' => 'custom',
				'custom_email'   => '{{trigger.email}}',
				'subject'        => 'Thank you for your order, {{trigger.first_name}}! Here\'s an exclusive offer just for you',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.first_name}},</p><p>Thank you for your order #{{trigger.order_number}}! We really appreciate your purchase.</p><p>As a valued customer, we\'d like to offer you an exclusive discount on your next order:</p><p><strong>{{coupon.code}}</strong></p><p>Use this code at checkout to get 20% off. This offer is valid for one use only, so don\'t miss out!</p><p>We look forward to serving you again.</p>',
			],
		],
	],
];
