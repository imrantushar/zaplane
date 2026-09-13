<?php

namespace Zaplane\Database\Seeders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BirthdayRecipeSeeder {

	public function run(): void {
		$this->seed_birthday_discount();
	}

	private function seed_birthday_discount(): void {
		$title = 'Birthday Discount';

		$graph = [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [
						'x' => 250,
						'y' => 50
					],
					'data'     => [
						'app'    => 'gemcrm',
						'event'  => 'contact_birthday',
						'hook'   => 'zaplane_gemcrm_contact_birthday',
						'label'  => 'GemCRM',
						'icon'   => 'crm.svg',
						'name'   => 'Contact Birthday',
						'config' => [
							'purchase_tag_id' => '',
						],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [
						'x' => 250,
						'y' => 200
					],
					'data'     => [
						'app'    => 'woocommerce',
						'event'  => 'create_coupon',
						'label'  => 'WooCommerce',
						'icon'   => 'woo.svg',
						'name'   => 'Create Birthday Coupon',
						'config' => [
							'code'               => 'BDAY-{{1.id}}',
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
					'position' => [
						'x' => 250,
						'y' => 350
					],
					'data'     => [
						'app'    => 'gemcrm',
						'event'  => 'send_email',
						'label'  => 'GemCRM',
						'icon'   => 'crm.svg',
						'name'   => 'Send Birthday Discount Email',
						'config' => [
							'recipient_type' => 'contact',
							'contact_id'     => '{{1.id}}',
							'subject'        => '🎂 Happy Birthday {{1.first_name}} — Here\'s Your Exclusive Discount!',
							'content_source' => 'custom',
							'body'           => '<p>Hi {{1.first_name}},</p><p>Wishing you a wonderful birthday! As a special gift, here is your exclusive discount code:</p><p><strong>{{2.coupon.code}}</strong></p><p>Use it at checkout to get 20% off your next purchase. This code is valid for one use only, so treat yourself!</p><p>Happy Birthday! 🎉</p>',
						],
					],
				],
				[
					'id'       => '4',
					'type'     => 'action',
					'position' => [
						'x' => 250,
						'y' => 500
					],
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
					'id'       => '5',
					'type'     => 'action',
					'position' => [
						'x' => 250,
						'y' => 650
					],
					'data'     => [
						'app'    => 'gemcrm',
						'event'  => 'send_email',
						'label'  => 'GemCRM',
						'icon'   => 'crm.svg',
						'name'   => 'Send Birthday Reminder Email',
						'config' => [
							'recipient_type' => 'contact',
							'contact_id'     => '{{1.id}}',
							'subject'        => 'Don\'t forget your birthday discount, {{1.first_name}}!',
							'content_source' => 'custom',
							'body'           => '<p>Hi {{1.first_name}},</p><p>Just a friendly reminder that your birthday discount is still waiting for you!</p><p><strong>{{2.coupon.code}}</strong></p><p>Use it before it expires. We\'d love to celebrate your special day with you.</p>',
						],
					],
				],
			],
			'edges' => [
				[
					'id' => 'e1-2',
					'source' => '1',
					'target' => '2'
				],
				[
					'id' => 'e2-3',
					'source' => '2',
					'target' => '3'
				],
				[
					'id' => 'e3-4',
					'source' => '3',
					'target' => '4'
				],
				[
					'id' => 'e4-5',
					'source' => '4',
					'target' => '5'
				],
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

		RecipeSeeding::save( 'birthday-discount', [
			'title'             => $title,
			'description'       => 'Automatically reward GemCRM contacts on their birthday. Sends a personalized discount coupon, waits 7 days, then sends a reminder email. Optionally applies a tag when the contact makes a purchase.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'crm.svg', 'woo.svg' ] ),
			'created_by'        => 0,
		] );
	}
}
