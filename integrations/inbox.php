<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Modules\Inbox\Services\AiResponder;
use Zaplane\Modules\Inbox\Services\Conversations;
use Zaplane\Modules\Inbox\Services\Outbound;

/**
 * Workflow triggers and actions for the Inbox module: react to customer
 * messages and conversation changes, and reply, assign, tag or hand over from
 * a workflow. The assistant uses `forward_to_human` as a tool.
 */
class Inbox extends IntegrationBase {

	public static function get_slug(): string {
		return 'inbox';
	}

	public static function get_name(): string {
		return 'Inbox';
	}

	public static function get_icon(): string {
		return 'inbox.svg';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_triggers(): array {
		return [
			'message_received'     => [
				'label' => 'Customer Message Received',
				'hook'  => 'zaplane/inbox/message_received',
			],
			'conversation_created' => [
				'label' => 'Conversation Started',
				'hook'  => 'zaplane/inbox/conversation_created',
			],
			'conversation_closed'  => [
				'label' => 'Conversation Closed',
				'hook'  => 'zaplane/inbox/conversation_closed',
			],
			'handed_to_human'      => [
				'label' => 'Assistant Handed Over to the Team',
				'hook'  => 'zaplane/inbox/handed_to_human',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return [
			[
				'key'     => 'channel',
				'label'   => 'Channel',
				'type'    => 'select',
				'default' => '',
				'options' => self::channel_options(),
				'help'    => 'Run only for conversations on this channel.',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$payload = $args[0] ?? [];
		if ( ! is_array( $payload ) || empty( $payload['conversation_id'] ) ) {
			return false;
		}

		$channel = (string) ( $node['config']['channel'] ?? '' );
		if ( '' !== $channel && $channel !== ( $payload['channel'] ?? '' ) ) {
			return false;
		}

		return $payload;
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$base = [
			'conversation_id' => 42,
			'channel'         => 'web',
			'status'          => 'open',
			'handler'         => 'bot',
			'assignee_id'     => 0,
			'contact_id'      => 7,
			'contact_name'    => 'Jane Doe',
			'contact_email'   => 'jane@example.com',
			'contact_phone'   => '',
		];

		if ( in_array( $trigger, [ 'message_received', 'conversation_created' ], true ) ) {
			return $base + [
				'message_id' => 314,
				'text'       => 'Hi, is the blue one in stock?',
				'sender_id'  => 'v_3f2a9c',
			];
		}

		if ( 'handed_to_human' === $trigger ) {
			return $base + [ 'reason' => 'The customer asked for a refund.' ];
		}

		return $base;
	}

	public static function get_actions(): array {
		return [
			'send_reply'       => [ 'label' => 'Send Reply' ],
			'add_note'         => [ 'label' => 'Add Private Note' ],
			'assign'           => [ 'label' => 'Assign Conversation' ],
			'add_tag'          => [ 'label' => 'Add Tag' ],
			'set_status'       => [ 'label' => 'Change Status' ],
			'set_ai'           => [ 'label' => 'Turn Assistant On or Off' ],
			'forward_to_human' => [ 'label' => 'Hand Over to the Team' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$conversation = [
			'key'         => 'conversation_id',
			'label'       => 'Conversation ID',
			'type'        => 'expression',
			'required'    => true,
			'placeholder' => '{{trigger.conversation_id}}',
		];

		switch ( $action ) {
			case 'send_reply':
			case 'add_note':
				return [
					$conversation,
					[
						'key'      => 'message',
						'label'    => 'send_reply' === $action ? 'Reply' : 'Note',
						'type'     => 'textarea',
						'required' => true,
					],
				];
			case 'assign':
				return [
					$conversation,
					[
						'key'      => 'user_id',
						'label'    => 'Team member (WordPress user ID)',
						'type'     => 'expression',
						'required' => true,
						'help'     => 'Use 0 to unassign.',
					],
				];
			case 'add_tag':
				return [
					$conversation,
					[
						'key'      => 'tag',
						'label'    => 'Tag',
						'type'     => 'expression',
						'required' => true,
						'help'     => 'Separate several tags with commas.',
					],
				];
			case 'set_status':
				return [
					$conversation,
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => true,
						'default'  => 'closed',
						'options'  => [
							[
								'value' => 'open',
								'label' => 'Open',
							],
							[
								'value' => 'pending',
								'label' => 'Pending',
							],
							[
								'value' => 'snoozed',
								'label' => 'Snoozed',
							],
							[
								'value' => 'closed',
								'label' => 'Closed',
							],
						],
					],
				];
			case 'set_ai':
				return [
					$conversation,
					[
						'key'      => 'enabled',
						'label'    => 'Assistant',
						'type'     => 'select',
						'required' => true,
						'default'  => 'no',
						'options'  => [
							[
								'value' => 'yes',
								'label' => 'On — the assistant answers',
							],
							[
								'value' => 'no',
								'label' => 'Off — the team answers',
							],
							[
								'value' => 'workflow',
								'label' => 'Off — workflows answer',
							],
						],
					],
				];
			case 'forward_to_human':
				return [
					$conversation,
					[
						'key'      => 'reason',
						'label'    => 'Reason',
						'type'     => 'text',
						'required' => false,
					],
				];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = (string) ( $node['data']['event'] ?? '' );
		$config = (array) ( $node['data']['config'] ?? [] );

		if ( ! class_exists( Conversations::class ) ) {
			return self::err( 'The Inbox module is not available.', $input );
		}

		$conversation = Conversations::find( (int) ( $config['conversation_id'] ?? 0 ) );
		if ( ! $conversation ) {
			return self::err( 'Conversation not found.', $input );
		}

		switch ( $event ) {
			case 'send_reply':
			case 'add_note':
				$text = trim( (string) ( $config['message'] ?? '' ) );
				if ( '' === $text ) {
					return self::err( 'A message is required.', $input );
				}
				$message = Outbound::send( $conversation, $text, [
					'sender_type' => 'workflow',
					'is_note'     => 'add_note' === $event,
				] );
				return self::ok( $input, [
					'message_id' => (int) $message->id,
					'status'     => (string) $message->delivery_status,
					'error'      => (string) $message->error,
				] );

			case 'assign':
				Conversations::assign( $conversation, (int) ( $config['user_id'] ?? 0 ) );
				return self::ok( $input, [ 'assignee_id' => (int) $conversation->assignee_id ] );

			case 'add_tag':
				Conversations::add_tags( $conversation, explode( ',', (string) ( $config['tag'] ?? '' ) ) );
				return self::ok( $input, [ 'tags' => Conversations::tags( (int) $conversation->id ) ] );

			case 'set_status':
				Conversations::set_status( $conversation, (string) ( $config['status'] ?? '' ) );
				return self::ok( $input, [ 'status' => (string) $conversation->status ] );

			case 'set_ai':
				$mode = (string) ( $config['enabled'] ?? 'no' );
				Conversations::set_handler( $conversation, 'yes' === $mode ? 'bot' : ( 'workflow' === $mode ? 'workflow' : 'human' ) );
				return self::ok( $input, [ 'handler' => (string) $conversation->handler ] );

			case 'forward_to_human':
				AiResponder::note_handover( (int) $conversation->id );
				Conversations::hand_to_human( $conversation, sanitize_text_field( (string) ( $config['reason'] ?? '' ) ) );
				return self::ok( $input, [ 'handler' => 'human' ] );
		}

		return self::err( 'Unknown action: ' . $event, $input );
	}

	/**
	 * @return array<int,array{value:string,label:string}>
	 */
	private static function channel_options(): array {
		$options = [
			[
				'value' => '',
				'label' => 'Any channel',
			],
		];
		if ( class_exists( '\Zaplane\Modules\Inbox\Channels\Registry' ) ) {
			foreach ( \Zaplane\Modules\Inbox\Channels\Registry::all() as $slug => $class ) {
				$options[] = [
					'value' => $slug,
					'label' => $class::label(),
				];
			}
		}
		return $options;
	}

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed> $data
	 */
	private static function ok( array $input, array $data ): array {
		return [
			'port' => 'main',
			'data' => array_merge( $input, [ 'success' => true ], $data ),
		];
	}

	/**
	 * @param array<string,mixed> $input
	 */
	private static function err( string $message, array $input ): array {
		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'success' => false,
				'error'   => $message,
			] ),
		];
	}
}
