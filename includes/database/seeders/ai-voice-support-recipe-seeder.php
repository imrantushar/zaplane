<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Voice Support: Transcribe & Reply" — turn a customer voice note into a grounded
 * text answer:
 *
 *   Catch Webhook (audio_url) → Transcribe (Whisper) → Retrieve Business Knowledge
 *   → AI Agent (answers from context) → Send Webhook (posts reply)
 *
 * Great for WhatsApp/Telegram voice messages or a "record a question" widget. POST
 * { "audio_url": "https://…/note.mp3", "reply_url": "https://…" }. The audio is
 * transcribed, the transcript retrieves the most relevant knowledge (semantic when
 * enabled), the agent answers, and the reply is posted back.
 */
class AiVoiceSupportRecipeSeeder {

	public function run(): void {
		$title = 'Voice Support: Transcribe & Reply';

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
						'x' => 380,
						'y' => 200,
					],
					'data'     => [
						'app'           => 'ai',
						'event'         => 'transcribe',
						'label'         => 'Transcribe Audio',
						'icon'          => 'ai.svg',
						'name'          => 'AI',
						// Nulled on import; link an OpenAI connection (Whisper).
						'connection_id' => null,
						'config'        => [
							'audio_url' => '{{1.audio_url}}',
							'model'     => 'whisper-1',
						],
					],
				],
				[
					'id'       => '3',
					'type'     => 'action',
					'position' => [
						'x' => 680,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'knowledge',
						'event'  => 'retrieve',
						'label'  => 'Business Knowledge',
						'icon'   => 'knowledge.svg',
						'name'   => 'Business Knowledge',
						'config' => [
							'business_key' => 'default',
							'query'        => '{{2.text}}',
							'limit'        => 5,
						],
					],
				],
				[
					'id'       => '4',
					'type'     => 'action',
					'position' => [
						'x' => 980,
						'y' => 200,
					],
					'data'     => [
						'app'           => 'ai-agent',
						'event'         => 'run_agent',
						'label'         => 'AI Agent',
						'icon'          => 'ai-agent.svg',
						'name'          => 'AI Agent',
						'connection_id' => null,
						'config'        => [
							'system_prompt'   => "You are a helpful, concise support assistant answering a transcribed voice message. Use the business context below; if it doesn't cover the question, say you're not certain and offer a human.\n\nBusiness context:\n{{3.context}}",
							'task'            => '{{2.text}}',
							'max_steps'       => 3,
							'response_format' => 'text',
						],
					],
				],
				[
					'id'       => '5',
					'type'     => 'action',
					'position' => [
						'x' => 1280,
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
							'payload_fields' => [
								[
									'key'   => 'transcript',
									'value' => '{{2.text}}',
								],
								[
									'key'   => 'answer',
									'value' => '{{4.reply}}',
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
				[
					'id'     => 'e4-5',
					'source' => '4',
					'target' => '5',
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
					'app'         => 'ai',
					'name'        => 'AI (transcription)',
					'auth_type'   => 'api_key',
					'status'      => 'active',
				],
				[
					'original_id' => 1,
					'app'         => 'ai-agent',
					'name'        => 'AI Agent',
					'auth_type'   => 'api_key',
					'status'      => 'active',
				],
			],
		];

		Recipe::create( [
			'title'             => $title,
			'description'       => 'Transcribe a customer voice note (Whisper), retrieve the most relevant Business Knowledge, answer with an AI Agent, and post the transcript + reply back. After importing: link an OpenAI connection on the Transcribe step, link your AI connection on the AI Agent, set the Business Key on the Retrieve step, then activate. POST { "audio_url": "https://…/note.mp3", "reply_url": "https://…" }.',
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'webhook', 'ai', 'knowledge', 'ai-agent' ] ),
			'created_by'        => 0,
		] );
	}
}
