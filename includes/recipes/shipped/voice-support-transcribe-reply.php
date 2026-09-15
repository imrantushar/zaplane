<?php
/**
 * Voice Support: Transcribe & Reply: turns a customer's voice note into an answer.
 * The audio is transcribed, the transcript retrieves the most relevant knowledge,
 * an AI Agent answers, and the transcript and reply are posted back.
 *
 * POST { "audio_url": "https://…/note.mp3", "reply_url": "https://…" }.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'Voice Support: Transcribe & Reply',
	'description' => 'Transcribe a customer voice note (Whisper), retrieve the most relevant Business Knowledge, answer with an AI Agent, and post the transcript + reply back. Link an OpenAI connection for the Transcribe step and your AI connection for the AI Agent while setting it up, set the Business Key on the Retrieve step, then activate. POST { "audio_url": "https://…/note.mp3", "reply_url": "https://…" }.',
	'steps'       => [
		[
			'trigger' => 'webhook.catch_hook',
			'name'    => 'Incoming Voice Note',
		],
		[
			'action' => 'ai.transcribe',
			'name'   => 'Transcribe Audio',
			'config' => [
				'audio_url' => '{{trigger.audio_url}}',
				'model'     => 'whisper-1',
			],
		],
		[
			'action' => 'knowledge.retrieve',
			'name'   => 'Find Relevant Knowledge',
			'config' => [
				'business_key' => 'default',
				'query'        => '{{text}}',
				'limit'        => 5,
			],
		],
		[
			'action' => 'ai-agent.run_agent',
			'name'   => 'Answer with AI',
			'config' => [
				'system_prompt'   => "You are a helpful, concise support assistant answering a transcribed voice message. Use the business context below; if it doesn't cover the question, say you're not certain and offer a human.\n\nBusiness context:\n{{context}}",
				'task'            => '{{text}}',
				'max_steps'       => 3,
				'response_format' => 'text',
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
						'key'   => 'transcript',
						'value' => '{{text}}',
					],
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
