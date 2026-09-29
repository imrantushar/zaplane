<?php
/**
 * AI Reply to Incoming Webhook: an AI Agent answers a message that arrives by
 * webhook, searching Business Knowledge first, and the reply is posted back.
 *
 * Any app can POST { "message": "...", "reply_url": "https://..." } to the
 * workflow's webhook URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'AI Reply to Incoming Webhook',
	'description' => 'Receive a question by webhook, answer it with an AI Agent (searching your Business Knowledge), then POST the reply back to a URL from the request. Pick your AI connection while setting it up, then activate. Send a test POST with { "message": "...", "reply_url": "https://..." }.',
	'steps'       => [
		[
			'trigger' => 'webhook.catch_hook',
			'name'    => 'Incoming Question',
		],
		[
			'action' => 'ai-agent.run_agent',
			'name'   => 'Answer with AI',
			'config' => [
				'system_prompt'   => "You are a helpful, concise support assistant. When the user asks about the business (products, pricing, policies), use the search_knowledge tool to find accurate answers before replying. If you don't find anything relevant, answer helpfully from general knowledge and say so.",
				'task'            => '{{trigger.message}}',
				'max_steps'       => 5,
				'response_format' => 'text',
				// Enables the built-in knowledge tool. Add entries under this key via
				// Business Knowledge → Add Entry (or Sync products).
				'business_key'    => 'default',
				// A sensible default model; wire a Chat Model sub-node to change it.
				'model'           => 'claude-sonnet-4-6',
			],
		],
		[
			'action' => 'webhook.send_hook',
			'name'   => 'Post the Reply',
			'config' => [
				'url'            => '{{trigger.reply_url}}',
				'method'         => 'POST',
				'payload_type'   => 'json',
				// A structured body, so the reply is safely JSON-encoded.
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
