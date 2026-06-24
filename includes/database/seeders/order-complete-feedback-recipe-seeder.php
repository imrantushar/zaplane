<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderCompleteFeedbackRecipeSeeder {

	public function run(): void {
		$this->seed_order_complete_feedback();
	}

	private function seed_order_complete_feedback(): void {
		$title = 'Order Complete Feedback Request';

		if ( Recipe::where( 'title', $title )->first() ) {
			return;
		}

		$graph = [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [ 'x' => 250, 'y' => 50 ],
					'data'     => [
						'app'    => 'woocommerce',
						'event'  => 'order_status_completed',
						'hook'   => 'woocommerce_order_status_completed',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Order Status Set to Completed',
						'config' => [],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 200 ],
					'data'     => [
						'app'    => 'delay',
						'event'  => 'wait',
						'label'  => 'Wait 2 Days',
						'icon'   => 'delay',
						'name'   => 'Delay',
						'config' => [
							'unit'   => 'days',
							'amount' => 2,
						],
					],
				],
				[
					'id'       => '3',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 350 ],
					'data'     => [
						'app'    => 'gemcrm',
						'event'  => 'send_email',
						'label'  => 'GemCRM',
						'icon'   => 'crm.svg',
						'name'   => 'Send Feedback Request Email',
						'config' => [
							'recipient_type' => 'custom',
							'custom_email'   => '{{1.email}}',
							'subject'        => 'How was your order, {{1.first_name}}? Share your feedback!',
							'body'           => '<p>Hi {{1.first_name}},</p><p>Thank you for your order #{{1.order_number}}! We hope you\'re enjoying your purchase.</p><p>We\'d love to hear what you think. It only takes a minute:</p><p><a href="{{1.feedback_page_url}}">Leave Your Feedback</a></p><p>Your feedback helps us improve and serve you better.</p><p>Thank you for shopping with us!</p>',
						],
					],
				],
				[
					'id'       => '4',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 500 ],
					'data'     => [
						'app'    => 'woocommerce',
						'event'  => 'add_order_note',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Add Feedback Request Note to Order',
						'config' => [
							'order_id'         => '{{1.order_id}}',
							'note'             => 'Feedback request email sent to {{1.email}}. Feedback link: {{1.feedback_page_url}}',
							'is_customer_note' => false,
						],
					],
				],
			],
			'edges' => [
				[ 'id' => 'e1-2', 'source' => '1', 'target' => '2' ],
				[ 'id' => 'e2-3', 'source' => '2', 'target' => '3' ],
				[ 'id' => 'e3-4', 'source' => '3', 'target' => '4' ],
			],
		];

		$blueprint = [
			'title'       => $title,
			'status'      => 'draft',
			'layout'      => 'LR',
			'versions'    => [
				[
					'graph_json'     => $graph,
					'graph_hash'     => hash( 'sha256', wp_json_encode( $graph ) ),
					'is_active'      => true,
					'version_number' => 1,
				],
			],
			'connections' => [],
		];

		Recipe::create( [
			'title'             => $title,
			'description'       => 'Automatically request customer feedback after a WooCommerce order is completed. Waits 2 days, sends a personalised feedback request email with a unique link, then adds an internal note to the order recording that the request was sent.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'woocommerce', 'gemcrm' ] ),
			'created_by'        => 0,
		] );
	}
}
