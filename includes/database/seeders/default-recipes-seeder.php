<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DefaultRecipesSeeder {

	public function run(): void {
		$this->seed_woocommerce_abandoned_cart();
	}

	private function seed_woocommerce_abandoned_cart(): void {
		$title = 'WooCommerce Abandoned Cart';

		if ( Recipe::where( 'title', $title )->first() ) {
			return;
		}

		// Node IDs must be strings — React Flow requires string IDs for rendering.
		// Edges source/target must also be strings matching node IDs.
		// Action key is stored as data.event (not data.action); mapNodesForBackend removes
		// data.action and mapGraphFromBackend re-adds it from node.type ("trigger"/"action").
		// Expression {{1.email}} resolves because the runner casts node IDs with (int) to
		// produce numeric node_keys, and buildNodeContext stores them as string keys.
		$graph = [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [ 'x' => 250, 'y' => 50 ],
					'data'     => [
						'app'   => 'woocommerce',
						'event' => 'cart_abandoned',
						'hook'  => 'zaplane/abandoned_cart/started',
						'label' => 'Woocommerce',
						'icon'  => 'woo.svg',
						'name'  => 'Abandoned Cart',
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 200 ],
					'data'     => [
						'app'   => 'gemcrm',
						'event' => 'send_email',
						'label' => 'Send Email',
						'icon'  => 'crm.svg',
						'name'  => 'GemCRM',
						'config' => [
							'recipient_type' => 'custom',
							'custom_email'   => '{{1.email}}',
							'subject'        => 'You left something behind — your cart is waiting',
							'body'           => '<p>Hi {{1.full_name}},</p><p>It looks like you left some items in your cart. Don\'t worry — we\'ve saved everything for you.</p><p><a href="{{1.recovery_link}}">Complete your purchase</a></p><p>If you have any questions, feel free to reply to this email.</p>',
						],
					],
				],
				[
					'id'       => '3',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 350 ],
					'data'     => [
						'app'   => 'delay',
						'event' => 'wait',
						'label' => 'Wait 3 Days',
						'icon'  => 'delay',
						'name'  => 'Delay',
						'config' => [
							'unit'   => 'days',
							'amount' => 3,
						],
					],
				],
				[
					'id'       => '4',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 500 ],
					'data'     => [
						'app'   => 'gemcrm',
						'event' => 'send_email',
						'label' => 'Send Follow-up Email',
						'icon'  => 'crm.svg',
						'name'  => 'GemCRM',
						'config' => [
							'recipient_type' => 'custom',
							'custom_email'   => '{{1.email}}',
							'subject'        => 'Last chance — your cart is about to expire',
							'body'           => '<p>Hi {{1.full_name}},</p><p>This is a friendly reminder that the items in your cart are still available, but they may not be for long.</p><p><a href="{{1.recovery_link}}">Claim your cart now</a></p><p>If you\'ve already completed your purchase, please ignore this email.</p>',
						],
					],
				],
			],
			'edges' => [
				[
					'id'     => 'e1-2',
					'source' => '1',
					'target' => '2',
				],
				[
					'id'     => 'e2-3',
					'source' => '2',
					'target' => '3',
				],
				[
					'id'     => 'e3-4',
					'source' => '3',
					'target' => '4',
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

		Recipe::create( [
			'title'             => $title,
			'description'       => 'Automatically follow up with customers who abandoned their WooCommerce cart. Sends an initial email, waits 3 days, then sends a follow-up email.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'abandoned-cart', 'gemcrm' ] ),
			'created_by'        => 0,
		] );
	}
}
