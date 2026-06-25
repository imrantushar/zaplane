<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InactiveCustomerRecipeSeeder {

	public function run(): void {
		$this->seed_inactive_customer_winback();
	}

	private function seed_inactive_customer_winback(): void {
		$title = 'Inactive Customer Win-back';

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
						'event'  => 'inactive_customer',
						'hook'   => 'zaplane_woo_inactive_customer',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Inactive Customer',
						'config' => [
							'days'     => '30',
							'tag_ids'  => [],
							'list_ids' => [],
						],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 200 ],
					'data'     => [
						'app'    => 'gemcrm',
						'event'  => 'send_email',
						'label'  => 'GemCRM',
						'icon'   => 'crm.svg',
						'name'   => 'Send Win-back Reminder',
						'config' => [
							'recipient_type' => 'custom',
							'custom_email'   => '{{1.email}}',
							'subject'        => 'We miss you, {{1.first_name}}! Come back for something special',
							'body'           => '<p>Hi {{1.first_name}},</p><p>It\'s been a while since your last order and we\'ve been thinking about you. We\'d love to have you back!</p><p>Stay tuned — an exclusive discount is on its way to thank you for being a valued customer.</p><p>If you have any questions, feel free to reply to this email.</p>',
						],
					],
				],
				[
					'id'       => '3',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 350 ],
					'data'     => [
						'app'    => 'delay',
						'event'  => 'wait',
						'label'  => 'Wait 7 Days',
						'icon'   => 'delay',
						'name'   => 'Delay',
						'config' => [
							'unit'   => 'days',
							'amount' => 7,
						],
					],
				],
				[
					'id'       => '4',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 500 ],
					'data'     => [
						'app'    => 'woocommerce',
						'event'  => 'create_coupon',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Create Win-back Coupon',
						'config' => [
							'code'               => 'WINBACK-{{1.id}}',
							'discount_type'      => 'percent',
							'amount'             => '15',
							'usage_limit'        => '1',
							'expiry_date'        => '',
							'email_restrictions' => '{{1.email}}',
						],
					],
				],
				[
					'id'       => '5',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 650 ],
					'data'     => [
						'app'    => 'gemcrm',
						'event'  => 'send_email',
						'label'  => 'GemCRM',
						'icon'   => 'crm.svg',
						'name'   => 'Send Coupon Email',
						'config' => [
							'recipient_type' => 'custom',
							'custom_email'   => '{{1.email}}',
							'subject'        => 'Your exclusive win-back offer is here, {{1.first_name}}!',
							'body'           => '<p>Hi {{1.first_name}},</p><p>As a thank-you for being a loyal customer, here is a special discount just for you:</p><p><strong>{{4.coupon.code}}</strong></p><p>Use it at checkout to get 15% off your next purchase. This code is valid for one use only, so grab it before it\'s gone!</p><p>We can\'t wait to see you back.</p>',
						],
					],
				],
			],
			'edges' => [
				[ 'id' => 'e1-2', 'source' => '1', 'target' => '2' ],
				[ 'id' => 'e2-3', 'source' => '2', 'target' => '3' ],
				[ 'id' => 'e3-4', 'source' => '3', 'target' => '4' ],
				[ 'id' => 'e4-5', 'source' => '4', 'target' => '5' ],
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
			'description'       => 'Re-engage WooCommerce customers who have not ordered in a configurable number of days. Sends a reminder email, waits 7 days, creates a personal discount coupon, then sends the coupon by email. Tags are automatically removed from the GemCRM contact as soon as the customer places a new order.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'woo.svg', 'crm.svg' ] ),
			'created_by'        => 0,
		] );
	}
}
