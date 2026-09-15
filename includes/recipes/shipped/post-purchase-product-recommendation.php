<?php
/**
 * Post Purchase Product Recommendation: after an order is completed, emails the
 * customer a few products from a category.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'Post Purchase Product Recommendation',
	'description' => 'Automatically send personalised product recommendations to customers after their WooCommerce order is completed. Fetches products from a chosen category and emails them directly to the customer to drive repeat purchases.',
	'steps'       => [
		[
			'trigger' => 'woocommerce.order_status_completed',
			'name'    => 'Order Status Set to Completed',
		],
		[
			'action' => 'woocommerce.get_products_by_category',
			'name'   => 'Get Recommended Products',
			'config' => [
				'category_id'   => '',
				'category_slug' => '',
				'limit'         => '3',
				'page'          => '1',
			],
		],
		[
			'action' => 'gemcrm.send_email',
			'name'   => 'Send Product Recommendation Email',
			'config' => [
				'recipient_type' => 'custom',
				'custom_email'   => '{{trigger.email}}',
				'subject'        => 'Customers who bought what you bought also love these, {{trigger.first_name}}!',
				'content_source' => 'custom',
				'body'           => '<p>Hi {{trigger.first_name}},</p><p>Thank you for your recent order #{{trigger.order_number}}. We hope you\'re loving your purchase!</p><p>Based on what you bought, we think you\'ll also enjoy these hand-picked products:</p><p>{{products}}</p><p>Shop now and discover something new. If you have any questions, feel free to reach out — we\'re always happy to help.</p>',
			],
		],
	],
];
