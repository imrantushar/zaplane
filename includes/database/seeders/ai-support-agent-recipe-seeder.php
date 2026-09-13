<?php

namespace Zaplane\Database\Seeders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "AI Support Agent (Knowledge + Memory)" — the full agentic pattern, showing how
 * the AI Agent composes its three sub-input handles:
 *
 *   Catch Webhook  →  AI Agent  →  Save turn (user + assistant)  →  Send Webhook
 *                        │
 *          ┌─────────────┼──────────────┐
 *      Chat Model     Memory         Business Knowledge   (sub-nodes on the agent)
 *      (ai_model)    (ai_memory)        (ai_tool)
 *
 * The agent picks its model from the wired Chat Model node, loads prior turns from
 * the Memory node (keyed per conversation), and answers using the Business
 * Knowledge node as a callable tool — deciding for itself when to search. After it
 * replies, the user message and the answer are appended to Memory so the next turn
 * has context.
 *
 * POST { "message": "...", "reply_url": "https://...", "session_id": "..." }.
 * `session_id` keys the conversation memory (one thread per user/channel).
 */
class AiSupportAgentRecipeSeeder {

	public function run(): void {
		$title = 'AI Support Agent (Knowledge + Memory)';

		$conversation_key = 'chat:{{1.session_id}}';

		$graph = [
			'nodes' => [
				// ---- Main flow ------------------------------------------------
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [
						'x' => 80,
						'y' => 180,
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
						'x' => 420,
						'y' => 180,
					],
					'data'     => [
						'app'           => 'ai-agent',
						'event'         => 'run_agent',
						'label'         => 'AI Agent',
						'icon'          => 'ai-agent.svg',
						'name'          => 'AI Agent',
						// Nulled on import; the user links their AI Agent connection.
						'connection_id' => null,
						'config'        => [
							'system_prompt'   => "You are a helpful, concise support assistant. Before answering anything about the business — products, pricing, policies — use the Business Knowledge tool to look up accurate information (pass business_key exactly \"default\" and a short search query). If you find nothing relevant, say you're not certain and offer to connect the customer with a human. Use the conversation history for context.",
							'task'            => '{{1.message}}',
							'max_steps'       => 5,
							'response_format' => 'text',
						],
					],
				],
				[
					'id'       => '3',
					'type'     => 'action',
					'position' => [
						'x' => 760,
						'y' => 180,
					],
					'data'     => [
						'app'    => 'memory',
						'event'  => 'append',
						'label'  => 'Save User Message',
						'icon'   => 'memory.svg',
						'name'   => 'Conversation Memory',
						'config' => [
							'conversation_key' => $conversation_key,
							'role'             => 'user',
							'content'          => '{{1.message}}',
						],
					],
				],
				[
					'id'       => '4',
					'type'     => 'action',
					'position' => [
						'x' => 1100,
						'y' => 180,
					],
					'data'     => [
						'app'    => 'memory',
						'event'  => 'append',
						'label'  => 'Save AI Reply',
						'icon'   => 'memory.svg',
						'name'   => 'Conversation Memory',
						'config' => [
							'conversation_key' => $conversation_key,
							'role'             => 'assistant',
							'content'          => '{{2.reply}}',
						],
					],
				],
				[
					'id'       => '5',
					'type'     => 'action',
					'position' => [
						'x' => 1440,
						'y' => 180,
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
							'payload_fields' => [
								[
									'key'   => 'answer',
									'value' => '{{2.reply}}',
								],
							],
							'sign_type'      => 'none',
							'timeout'        => 15,
						],
					],
				],
				// ---- Agent sub-nodes (wired into the agent's bottom handles) --
				[
					'id'       => '6',
					'type'     => 'action',
					'position' => [
						'x' => 300,
						'y' => 420,
					],
					'data'     => [
						'app'           => 'ai',
						'event'         => 'generate_response',
						'label'         => 'Chat Model',
						'icon'          => 'ai.svg',
						'name'          => 'AI',
						// The agent reads only this node's model; its own connection is
						// optional (link one to change the model from the dropdown).
						'connection_id' => null,
						'config'        => [
							'model' => 'claude-sonnet-4-6',
						],
					],
				],
				[
					'id'       => '7',
					'type'     => 'action',
					'position' => [
						'x' => 460,
						'y' => 420,
					],
					'data'     => [
						'app'    => 'memory',
						'event'  => 'get_history',
						'label'  => 'Memory',
						'icon'   => 'memory.svg',
						'name'   => 'Conversation Memory',
						'config' => [
							'conversation_key' => $conversation_key,
							'limit'            => 10,
						],
					],
				],
				[
					'id'       => '8',
					'type'     => 'action',
					'position' => [
						'x' => 620,
						'y' => 420,
					],
					'data'     => [
						'app'    => 'knowledge',
						'event'  => 'retrieve',
						'label'  => 'Business Knowledge',
						'icon'   => 'knowledge.svg',
						'name'   => 'Business Knowledge',
						'config' => [
							// The bucket the agent searches. Add entries under this key
							// via Business Knowledge → Add Entry, FAQ Builder, or Sync.
							'business_key' => 'default',
						],
					],
				],
			],
			'edges' => [
				// Main flow.
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
				[
					'id'     => 'e4-5',
					'source' => '4',
					'target' => '5',
				],
				// Sub-nodes → the agent's bottom handles. Sub-nodes emit from their
				// top (`sub_out`) into the agent's ai_model / ai_memory / ai_tool.
				[
					'id'           => 'e6-2-model',
					'source'       => '6',
					'sourceHandle' => 'sub_out',
					'target'       => '2',
					'targetHandle' => 'ai_model',
				],
				[
					'id'           => 'e7-2-memory',
					'source'       => '7',
					'sourceHandle' => 'sub_out',
					'target'       => '2',
					'targetHandle' => 'ai_memory',
				],
				[
					'id'           => 'e8-2-tool',
					'source'       => '8',
					'sourceHandle' => 'sub_out',
					'target'       => '2',
					'targetHandle' => 'ai_tool',
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

		RecipeSeeding::save( 'ai-support-agent', [
			'title'             => $title,
			'description'       => 'A full AI support agent: it selects its model from a Chat Model node, remembers the conversation via a Memory node (keyed by session_id), and answers from your Business Knowledge — wired as a tool it calls on demand. The user message and reply are saved to memory for the next turn. After importing: open the AI Agent node and link your AI connection, set the Business Knowledge node\'s Business Key to your bucket, then activate. POST { "message": "...", "reply_url": "https://...", "session_id": "..." }.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'webhook', 'ai-agent', 'ai', 'memory', 'knowledge' ] ),
			'created_by'        => 0,
		] );
	}
}
