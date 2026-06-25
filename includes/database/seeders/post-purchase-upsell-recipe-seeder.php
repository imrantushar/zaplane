<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostPurchaseUpsellRecipeSeeder {

	public function run(): void {
		$this->seed_post_purchase_upsell();
	}

	private function seed_post_purchase_upsell(): void {
		$title = 'WooCommerce Post Purchase Upsell';

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
						'event'  => 'order_status_processing',
						'hook'   => 'woocommerce_order_status_processing',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Order Placed',
						'config' => [],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 200 ],
					'data'     => [
						'app'    => 'woocommerce',
						'event'  => 'create_coupon',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Create Upsell Coupon',
						'config' => [
							'code'               => 'UPSELL-{{1.order_id}}',
							'discount_type'      => 'percent',
							'amount'             => '20',
							'usage_limit'        => '1',
							'expiry_date'        => '',
							'email_restrictions' => '{{1.email}}',
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
						'name'   => 'Send Upsell Offer Email',
						'config' => [
							'recipient_type' => 'custom',
							'custom_email'   => '{{1.email}}',
							'subject'        => 'Thank you for your order, {{1.first_name}}! Here\'s an exclusive offer just for you',
							'body'           => '<p>Hi {{1.first_name}},</p><p>Thank you for your order #{{1.order_number}}! We really appreciate your purchase.</p><p>As a valued customer, we\'d like to offer you an exclusive discount on your next order:</p><p><strong>{{2.coupon.code}}</strong></p><p>Use this code at checkout to get 20% off. This offer is valid for one use only, so don\'t miss out!</p><p>We look forward to serving you again.</p>',
						],
					],
				],
			],
			'edges' => [
				[ 'id' => 'e1-2', 'source' => '1', 'target' => '2' ],
				[ 'id' => 'e2-3', 'source' => '2', 'target' => '3' ],
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
			'description'       => 'Automatically reward customers immediately after a WooCommerce order is placed. Creates a personalised discount coupon and sends it to the customer by email, encouraging a repeat purchase.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'woo.svg', 'crm.svg' ] ),
			'created_by'        => 0,
		] );
	}
}
