<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "AI Reply to Incoming Webhook" — a ready-made showcase for the AI Agent and the
 * bidirectional Webhook app:
 *
 *   Catch Webhook (incoming)  →  AI Agent (answers, using Business Knowledge)  →
 *   Send Webhook (outgoing, posts the reply back)
 *
 * Any app can POST { "message": "...", "reply_url": "https://..." } to the
 * workflow's webhook URL. The agent answers the message (searching the business
 * knowledge base first), then the reply is POSTed back to reply_url. Great for
 * chatbots, support autoresponders, or wiring an external app up to AI.
 */
class AiWebhookAgentRecipeSeeder {

	public function run(): void {
		$title = 'AI Reply to Incoming Webhook';

		if ( Recipe::where( 'title', $title )->first() ) {
			return;
		}

		$graph = [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [ 'x' => 80, 'y' => 200 ],
					'data'     => [
						'app'   => 'webhook',
						'event' => 'catch_hook',
						'hook'  => 'zaplane/webhook/catch',
						'label' => 'Catch Webhook',
						'icon'  => 'webhook.svg',
						'name'  => 'Webhook',
						'config' => [],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [ 'x' => 420, 'y' => 200 ],
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
							'system_prompt'   => "You are a helpful, concise support assistant. When the user asks about the business (products, pricing, policies), use the search_knowledge tool to find accurate answers before replying. If you don't find anything relevant, answer helpfully from general knowledge and say so.",
							'task'            => '{{1.message}}',
							'max_steps'       => 5,
							'response_format' => 'text',
							// Enables the built-in knowledge tool. Add entries under this
							// key via Business Knowledge → Add Entry (or Sync products).
							'business_key'    => 'default',
							// Sensible default model; wire a Chat Model sub-node to change it.
							'model'           => 'claude-sonnet-4-6',
						],
					],
				],
				[
					'id'       => '3',
					'type'     => 'action',
					'position' => [ 'x' => 760, 'y' => 200 ],
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
								[ 'key' => 'answer', 'value' => '{{2.reply}}' ],
							],
							'sign_type'      => 'none',
							'timeout'        => 15,
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
			'description'       => 'Receive a question by webhook, answer it with an AI Agent (searching your Business Knowledge), then POST the reply back to a URL from the request. After importing: open the AI Agent node, pick your AI connection, then activate. Send a test POST with { "message": "...", "reply_url": "https://..." }.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'webhook', 'ai-agent' ] ),
			'created_by'        => 0,
		] );
	}
}
