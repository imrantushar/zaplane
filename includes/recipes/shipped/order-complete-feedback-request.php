<?php
/**
 * Order Complete Feedback Request: two days after an order is completed, asks the
 * customer for feedback and notes it on the order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'Order Complete Feedback Request',
	'description' => 'Automatically request customer feedback after a WooCommerce order is completed. Waits 2 days, sends a personalised feedback request email with a unique link, then adds an internal note to the order recording that the request was sent.',
	'steps'       => [
		[
			'trigger' => 'woocommerce.order_status_completed',
			'name'    => 'Order Status Set to Completed',
		],
		[
			'action' => 'delay.wait',
			'name'   => 'Wait 2 Days',
			'config' => [
				'unit'   => 'days',
				'amount' => 2,
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Feedback Request Email',
			'config' => [
				'recipient_type' => 'custom',
				'custom_email'   => '{{trigger.email}}',
				'subject'        => 'How was your order, {{trigger.first_name}}? Share your feedback!',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.first_name}},</p><p>Thank you for your order #{{trigger.order_number}}! We hope you\'re enjoying your purchase.</p><p>We\'d love to hear what you think. It only takes a minute:</p><p><a href="{{trigger.feedback_page_url}}">Leave Your Feedback</a></p><p>Your feedback helps us improve and serve you better.</p><p>Thank you for shopping with us!</p>',
			],
		],
		[
			'action' => 'woocommerce.add_order_note',
			'name'   => 'Add Feedback Request Note to Order',
			'config' => [
				'order_id'         => '{{trigger.order_id}}',
				'note'             => 'Feedback request email sent to {{trigger.email}}. Feedback link: {{trigger.feedback_page_url}}',
				'is_customer_note' => false,
			],
		],
	],
];
