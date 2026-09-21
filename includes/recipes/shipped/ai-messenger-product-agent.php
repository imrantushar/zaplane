<?php
/**
 * AI Messenger Product Agent (WooCommerce/StoreEngine): an AI Agent that answers
 * Facebook Messenger questions using Business Knowledge for general info and a live
 * store lookup for exact, current price/stock — so pricing answers never lag behind
 * a sale or a restock the way a cached knowledge snippet can.
 *
 * Which store platform's "Get Product" tool gets wired in is decided here, each time
 * this recipe is (re-)seeded — see RecipeSeeding: "Its blueprint is written again
 * each time the seeders run" — by checking which of WooCommerce/StoreEngine is
 * active on THIS site. A Sticky Note on the canvas explains the pick so it isn't a
 * silent guess. If neither is active (not set up yet, or a different platform
 * entirely), it falls back to WooCommerce and says so — swap the Get Product tool
 * for StoreEngine's, or for a different integration's equivalent action, once the
 * real store plugin is in place.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ShippedRecipes::register() requires this file inside a method, so its variables are local.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$has_woocommerce = function_exists( 'wc_get_products' );
$has_storeengine  = function_exists( 'storeengine_get_product' );

if ( $has_storeengine && ! $has_woocommerce ) {
	$platform = 'storeengine';
	$why      = 'StoreEngine is active on this site, so its live product lookup was wired in.';
} elseif ( $has_woocommerce && $has_storeengine ) {
	$platform = 'woocommerce';
	$why      = "Both WooCommerce and StoreEngine are active — WooCommerce was picked by default. If StoreEngine is your actual store, swap this Tool node for StoreEngine's Get Product action.";
} elseif ( $has_woocommerce ) {
	$platform = 'woocommerce';
	$why      = 'WooCommerce is active on this site, so its live product lookup was wired in.';
} else {
	$platform = 'woocommerce';
	$why      = "Neither WooCommerce nor StoreEngine was detected active, so WooCommerce was wired in as a fallback. Activate your store plugin, and if it's StoreEngine, swap this Tool node for StoreEngine's Get Product action, then re-save this workflow before going live.";
}

$platform_label = 'storeengine' === $platform ? 'StoreEngine' : 'WooCommerce';
$conversation_key = 'messenger:{{trigger.sender_id}}';

return [
	'title'       => 'AI Messenger Product Agent (WooCommerce/StoreEngine)',
	// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- description is data (dynamic per-site detection), not a translatable UI string.
	'description' => sprintf(
		'A Messenger support agent that answers from your Business Knowledge for general questions and calls a live product lookup for exact price/stock before answering pricing questions — so it never quotes a stale price. On this site: %s Remembers the conversation per sender. Connect your Messenger page and AI connection while setting it up, set the Business Knowledge node\'s Business Key to your bucket, then activate.',
		$why
	),
	'steps'       => [
		[
			'trigger' => 'messenger.message_received',
			'name'    => 'Message Received',
		],
		[
			'action' => 'sticky_note.note',
			'name'   => 'Store Platform Note',
			'config' => [
				'content' => sprintf( "Product lookup: %s\n\n%s", $platform_label, $why ),
				'color'   => 'yellow',
			],
		],
		[
			'action' => 'ai-agent.run_agent',
			'name'   => 'Answer with AI',
			'config' => [
				'system_prompt'   => "You are a helpful, concise support assistant for this store's Facebook page. For general questions (policies, FAQs, product descriptions), use the Business Knowledge tool first. Before quoting a price or stock level, always call the Get Product tool to confirm the current, live value — never answer pricing from memory or from Business Knowledge alone, since it can lag behind a real change. If neither tool finds an answer, say you're not certain and offer to connect the customer with a human.",
				'task'            => '{{trigger.text}}',
				'max_steps'       => 5,
				'response_format' => 'text',
			],
			// The agent reads only this node's model; link a connection to it to change the model.
			'model'  => [
				'action' => 'ai.generate_response',
				'name'   => 'Chat Model',
				'config' => [
					'model' => 'claude-sonnet-4-6',
				],
			],
			'memory' => [
				'action' => 'memory.get_history',
				'name'   => 'Memory',
				'config' => [
					'conversation_key' => $conversation_key,
					'limit'            => 10,
				],
			],
			'tools'  => [
				[
					'action' => 'knowledge.retrieve',
					'name'   => 'Business Knowledge',
					'config' => [
						// The bucket the agent searches. Add entries under this key via
						// Business Knowledge → Add Entry, FAQ Builder, or Sync Content.
						'business_key' => 'default',
					],
				],
				[
					// Picked above from what's actually active on this site: {$platform}.
					'action' => $platform . '.get_product',
					'name'   => 'Get Product',
					'config' => [
						// Fallback only — the agent normally supplies its own search
						// keyword when it calls this tool.
						'query' => '{{trigger.text}}',
						'limit' => 1,
					],
				],
			],
		],
		[
			'action' => 'messenger.send_text',
			'name'   => 'Send Reply',
			'config' => [
				'recipient_id' => '{{trigger.sender_id}}',
				'text'         => '{{reply}}',
			],
		],
	],
];
