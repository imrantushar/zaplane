<?php
/**
 * AI Reply with Business Knowledge: the retrieve-then-answer pattern. The incoming
 * message pulls the most relevant knowledge entries, an AI Agent answers from that
 * context, and the reply is posted back.
 *
 * Unlike the agent's built-in knowledge tool, the lookup always runs exactly once,
 * which keeps the flow predictable and easy to debug. POST
 * { "message": "...", "reply_url": "https://..." } to the workflow's webhook URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'AI Reply with Business Knowledge',
	'description' => 'Receive a question by webhook, retrieve the most relevant Business Knowledge entries, answer with an AI Agent grounded in that context, then POST the reply back to a URL from the request. Set the Business Key on the Retrieve step to match your knowledge bucket, pick your AI connection while setting it up, then activate. Send a test POST with { "message": "...", "reply_url": "https://..." }.',
	'steps'       => [
		[
			'trigger' => 'webhook.catch_hook',
			'name'    => 'Incoming Question',
		],
		[
			'action' => 'knowledge.retrieve',
			'name'   => 'Find Relevant Knowledge',
			'config' => [
				// The bucket to read from. Add entries under this key via Business
				// Knowledge → Add Entry, FAQ Builder, or Sync Content.
				'business_key' => 'default',
				// The incoming message decides which entries are retrieved.
				'query'        => '{{trigger.message}}',
				'limit'        => 5,
			],
		],
		[
			'action' => 'ai-agent.run_agent',
			'name'   => 'Answer with AI',
			'config' => [
				'system_prompt'   => "You are a helpful, concise support assistant. Answer the customer's question using the business context below. If the context doesn't contain the answer, say you're not certain and offer to connect them with a human — do not invent details.\n\nBusiness context:\n{{context}}",
				'task'            => '{{trigger.message}}',
				'max_steps'       => 3,
				'response_format' => 'text',
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
