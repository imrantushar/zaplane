<?php
/**
 * AI Messenger Chat Agent (Knowledge + Memory): a plain Messenger support agent for
 * businesses with no store plugin (no WooCommerce/StoreEngine) — e.g. an FB Page
 * seller running purely on Business Knowledge (FAQ entries, synced posts/pages, or a
 * manually maintained catalogue). No live product lookup tool; see the
 * WooCommerce/StoreEngine variant of this recipe for that.
 *
 * Answers from Business Knowledge as a tool it calls on demand, remembers the
 * conversation via a Memory node keyed by Messenger sender, and replies inline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ShippedRecipes::register() requires this file inside a method, so its variables are local.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$conversation_key = 'messenger:{{trigger.sender_id}}';

return [
	'title'       => 'AI Messenger Chat Agent (Knowledge + Memory)',
	'description' => "A Messenger support agent for businesses without a store plugin — it answers from your Business Knowledge (FAQs, synced content, or manually added entries) and remembers the conversation per sender. No live product lookup; use the WooCommerce/StoreEngine Product Agent recipe instead if you sell through one of those. Connect your Messenger page and AI connection while setting it up, set the Business Knowledge node's Business Key to your bucket, then activate.",
	'steps'       => [
		[
			'trigger' => 'messenger.message_received',
			'name'    => 'Message Received',
		],
		[
			'action' => 'ai-agent.run_agent',
			'name'   => 'Answer with AI',
			'config' => [
				'system_prompt'   => "You are a helpful, concise support assistant for this business's Facebook page. Before answering anything about the business — products, prices, policies — use the Business Knowledge tool to look up accurate information. If you find nothing relevant, say you're not certain and offer to connect the customer with a human. Use the conversation history for context.",
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
