<?php
/**
 * AI Support Agent (Knowledge + Memory): an AI Agent that takes its model from a
 * Chat Model node, remembers the conversation through a Memory node, and searches
 * Business Knowledge as a tool when it needs to. The message and the reply are
 * saved to memory for the next turn.
 *
 * POST { "message": "...", "reply_url": "https://...", "session_id": "..." }.
 * `session_id` keys the conversation, one thread per user or channel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ShippedRecipes::register() requires this file inside a method, so its variables are local.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$conversation_key = 'chat:{{trigger.session_id}}';

return [
	'title'       => 'AI Support Agent (Knowledge + Memory)',
	'description' => 'A full AI support agent: it selects its model from a Chat Model node, remembers the conversation via a Memory node (keyed by session_id), and answers from your Business Knowledge — wired as a tool it calls on demand. The user message and reply are saved to memory for the next turn. Link your AI connection while setting it up, set the Business Knowledge node\'s Business Key to your bucket, then activate. POST { "message": "...", "reply_url": "https://...", "session_id": "..." }.',
	'steps'       => [
		[
			'trigger' => 'webhook.catch_hook',
			'name'    => 'Incoming Message',
		],
		[
			'action' => 'ai-agent.run_agent',
			'name'   => 'Answer with AI',
			'config' => [
				'system_prompt'   => "You are a helpful, concise support assistant. Before answering anything about the business — products, pricing, policies — use the Business Knowledge tool to look up accurate information (pass business_key exactly \"default\" and a short search query). If you find nothing relevant, say you're not certain and offer to connect the customer with a human. Use the conversation history for context.",
				'task'            => '{{trigger.message}}',
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
						// Business Knowledge → Add Entry, FAQ Builder, or Sync.
						'business_key' => 'default',
					],
				],
			],
		],
		[
			'action' => 'memory.append',
			'name'   => 'Save User Message',
			'config' => [
				'conversation_key' => $conversation_key,
				'role'             => 'user',
				'content'          => '{{trigger.message}}',
			],
		],
		[
			'action' => 'memory.append',
			'name'   => 'Save AI Reply',
			'config' => [
				'conversation_key' => $conversation_key,
				'role'             => 'assistant',
				'content'          => '{{reply}}',
			],
		],
		[
			'action' => 'webhook.send_hook',
			'name'   => 'Post the Reply',
			'config' => [
				'url'            => '{{trigger.reply_url}}',
				'method'         => 'POST',
				'payload_type'   => 'json',
				'payload_fields' => [
					[
						'key'   => 'answer',
						'value' => '{{reply}}',
					],
				],
				'sign_type'      => 'none',
				'timeout'        => 15,
			],
		],
	],
];
