<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductRecommendationRecipeSeeder {

	public function run(): void {
		$this->seed_product_recommendation();
	}

	private function seed_product_recommendation(): void {
		$title = 'Post Purchase Product Recommendation';

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
						'app'    => 'woocommerce',
						'event'  => 'get_products_by_category',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Get Recommended Products',
						'config' => [
							'category_id'   => '',
							'category_slug' => '',
							'limit'         => '3',
							'page'          => '1',
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
						'name'   => 'Send Product Recommendation Email',
						'config' => [
							'recipient_type' => 'custom',
							'custom_email'   => '{{1.email}}',
							'subject'        => 'Customers who bought what you bought also love these, {{1.first_name}}!',
							'body'           => '<p>Hi {{1.first_name}},</p><p>Thank you for your recent order #{{1.order_number}}. We hope you\'re loving your purchase!</p><p>Based on what you bought, we think you\'ll also enjoy these hand-picked products:</p><p>{{2.products}}</p><p>Shop now and discover something new. If you have any questions, feel free to reach out — we\'re always happy to help.</p>',
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
			'description'       => 'Automatically send personalised product recommendations to customers after their WooCommerce order is completed. Fetches products from a chosen category and emails them directly to the customer to drive repeat purchases.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'woo.svg', 'crm.svg' ] ),
			'created_by'        => 0,
		] );
	}
}
