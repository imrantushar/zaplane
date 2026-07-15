<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "AI Reply with Business Knowledge" — the retrieve-then-answer pattern:
 *
 *   Catch Webhook (incoming)  →  Business Knowledge (Retrieve)  →
 *   AI Agent (answers from the retrieved context)  →  Send Webhook (posts reply)
 *
 * Unlike the built-in knowledge tool, this wires an explicit Retrieve step before
 * the agent: the incoming message is used to pull the most relevant knowledge
 * entries, and that context is injected into the agent's instructions. The lookup
 * always runs exactly once, which makes the flow deterministic and easy to debug.
 *
 * POST { "message": "...", "reply_url": "https://..." } to the workflow's webhook
 * URL; the agent answers grounded in your Business Knowledge and the reply is
 * POSTed back to reply_url.
 */
class AiKnowledgeReplyRecipeSeeder {

	public function run(): void {
		$title = 'AI Reply with Business Knowledge';

		if ( Recipe::where( 'title', $title )->first() ) {
			return;
		}

		$graph = [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [
						'x' => 80,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'webhook',
						'event'  => 'catch_hook',
						'hook'   => 'zaplane/webhook/catch',
						'label'  => 'Catch Webhook',
						'icon'   => 'webhook.svg',
						'name'   => 'Webhook',
						'config' => [],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [
						'x' => 400,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'knowledge',
						'event'  => 'retrieve',
						'label'  => 'Business Knowledge',
						'icon'   => 'knowledge.svg',
						'name'   => 'Business Knowledge',
						'config' => [
							// The bucket to read from. Add entries under this key via
							// Business Knowledge → Add Entry, FAQ Builder, or Sync Content.
							'business_key' => 'default',
							// The incoming message drives which entries are retrieved.
							'query'        => '{{1.message}}',
							'limit'        => 5,
						],
					],
				],
				[
					'id'       => '3',
					'type'     => 'action',
					'position' => [
						'x' => 720,
						'y' => 200,
					],
					'data'     => [
						'app'           => 'ai-agent',
						'event'         => 'run_agent',
						'label'         => 'AI Agent',
						'icon'          => 'ai-agent.svg',
						'name'          => 'AI Agent',
						// Nulled on import; the user links their AI Agent connection in
						// the editor. Declared in blueprint.connections below.
						'connection_id' => null,
						'config'        => [
							'system_prompt'   => "You are a helpful, concise support assistant. Answer the customer's question using the business context below. If the context doesn't contain the answer, say you're not certain and offer to connect them with a human — do not invent details.\n\nBusiness context:\n{{2.context}}",
							'task'            => '{{1.message}}',
							'max_steps'       => 3,
							'response_format' => 'text',
							// Sensible default model; wire a Chat Model sub-node to change it.
							'model'           => 'claude-sonnet-4-6',
						],
					],
				],
				[
					'id'       => '4',
					'type'     => 'action',
					'position' => [
						'x' => 1040,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'webhook',
						'event'  => 'send_hook',
						'label'  => 'Send Webhook',
						'icon'   => 'webhook.svg',
						'name'   => 'Webhook',
						'config' => [
							'url'            => '{{1.reply_url}}',
							'method'         => 'POST',
							'payload_type'   => 'json',
							// Structured body — the AI reply is safely JSON-encoded.
							'payload_fields' => [
								[
									'key'   => 'answer',
									'value' => '{{3.reply}}',
								],
							],
							'sign_type'      => 'none',
							'timeout'        => 15,
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
			'connections' => [
				[
					'original_id' => 0,
					'app'         => 'ai-agent',
					'name'        => 'AI Agent',
					'auth_type'   => 'api_key',
					'status'      => 'active',
				],
			],
		];

		Recipe::create( [
			'title'             => $title,
			'description'       => 'Receive a question by webhook, retrieve the most relevant Business Knowledge entries, answer with an AI Agent grounded in that context, then POST the reply back to a URL from the request. After importing: set the Business Key on the Retrieve step to match your knowledge bucket, open the AI Agent node and pick your AI connection, then activate. Send a test POST with { "message": "...", "reply_url": "https://..." }.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'webhook', 'knowledge', 'ai-agent' ] ),
			'created_by'        => 0,
		] );
	}
}
