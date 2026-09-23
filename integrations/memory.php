<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Models\Conversation;

/**
 * Conversation Memory — durable, DB-backed chat history keyed by a logical
 * conversation key (e.g. "whatsapp:{{trigger.from}}"). Lets AI workflows hold
 * multi-turn context across messages.
 *
 * Storage is the zaplane_conversations table via the Conversation model. The
 * three actions (get history / append / clear) are the read-write surface the
 * AI node's `history` input plugs into.
 */
class Memory extends IntegrationBase {

	private const DEFAULT_LIMIT = 10;

	public static function get_slug(): string {
		return 'memory';
	}

	public static function get_name(): string {
		return 'Conversation Memory';
	}

	public static function get_icon(): string {
		return 'memory.svg';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_actions(): array {
		return [
			'get_history' => [ 'label' => 'Get Conversation History' ],
			'append'      => [ 'label' => 'Append Message To Conversation' ],
			'clear'       => [ 'label' => 'Clear Conversation' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$key_field = [
			'key'         => 'conversation_key',
			'label'       => 'Conversation Key',
			'type'        => 'expression',
			'required'    => true,
			'placeholder' => 'whatsapp:{{trigger.from}}',
			'help'        => 'Unique per-conversation bucket — typically channel + sender id.',
		];

		switch ( $action ) {
			case 'get_history':
				return [
					$key_field,
					[
						'key'      => 'source',
						'label'    => 'History from',
						'type'     => 'select',
						'required' => false,
						'default'  => 'memory',
						'options'  => [
							[
								'value' => 'memory',
								'label' => 'Saved turns (this Memory)',
							],
							[
								'value' => 'inbox',
								'label' => 'The Inbox conversation (includes your team\'s replies)',
							],
						],
						'help'     => 'With the Inbox, use a key like messenger:{{trigger.sender_id}} or whatsapp:{{trigger.from}}. The history is the real conversation (customer, assistant, team and workflow replies; never private notes), so turns aren\'t saved separately. If the Inbox is off or doesn\'t know this customer yet, saved turns are used.',
					],
					[
						'key'      => 'limit',
						'label'    => 'Max Messages',
						'type'     => 'number',
						'required' => false,
						'default'  => self::DEFAULT_LIMIT,
						'help'     => 'How many recent turns to return (oldest→newest).',
					],
				];

			case 'append':
				return [
					$key_field,
					[
						'key'      => 'role',
						'label'    => 'Role',
						'type'     => 'select',
						'required' => true,
						'default'  => 'user',
						'options'  => [
							[
								'value' => 'user',
								'label' => 'User'
							],
							[
								'value' => 'assistant',
								'label' => 'Assistant'
							],
						],
					],
					[
						'key'      => 'content',
						'label'    => 'Content',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'         => 'channel',
						'label'       => 'Channel (optional)',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'whatsapp',
					],
				];

			case 'clear':
				return [ $key_field ];
		}//end switch

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$key    = trim( (string) ( $config['conversation_key'] ?? '' ) );

		if ( '' === $key ) {
			return self::respond( array_merge( $input, [
				'success' => false,
				'error' => 'conversation_key is required.'
			] ) );
		}

		switch ( $event ) {
			case 'get_history':
				return self::action_get_history( $key, $config, $input );
			case 'append':
				return self::action_append( $key, $config, $input );
			case 'clear':
				return self::action_clear( $key, $input );
		}

		return self::respond( $input );
	}

	private static function action_get_history( string $key, array $config, array $input ): array {
		global $wpdb;

		$limit = (int) ( $config['limit'] ?? self::DEFAULT_LIMIT );
		if ( $limit <= 0 ) {
			$limit = self::DEFAULT_LIMIT;
		}

		$inbox = self::inbox_history( $key, $config, $limit );
		if ( null !== $inbox ) {
			return self::respond( array_merge( $input, [
				'success' => true,
				'history' => $inbox,
				'count'   => count( $inbox ),
				'source'  => 'inbox',
			] ) );
		}

		$table = Conversation::getTable();

		// Newest first for the LIMIT, then reverse to chronological order so the
		// AI node receives oldest→newest as a chat transcript expects.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM %i WHERE conversation_key = %s ORDER BY id DESC LIMIT %d",
				$table,
				$key,
				$limit
			),
			ARRAY_A
		);

		$history = array_reverse( is_array( $rows ) ? $rows : [] );

		return self::respond( array_merge( $input, [
			'success' => true,
			'history' => $history,
			'count'   => count( $history ),
		] ) );
	}

	/**
	 * The Inbox's record of this conversation as chat turns, when the step
	 * reads from the Inbox and the Inbox knows the customer; null otherwise.
	 *
	 * @param array<string,mixed> $config
	 * @return array<int,array{role:string,content:string}>|null
	 */
	private static function inbox_history( string $key, array $config, int $limit ): ?array {
		if ( 'inbox' !== ( $config['source'] ?? '' ) ) {
			return null;
		}

		/**
		 * Supply a conversation's history from the Inbox.
		 *
		 * @param array|null $history Null when the Inbox can't answer.
		 * @param string     $key     "channel:customer id".
		 * @param int        $limit   Most recent turns wanted.
		 */
		$history = apply_filters( 'zaplane/memory/inbox_history', null, $key, $limit );
		return is_array( $history ) ? $history : null;
	}

	private static function action_append( string $key, array $config, array $input ): array {
		// Reading from the Inbox means the Inbox already holds every turn;
		// saving them here too would only drift out of step with it.
		if ( null !== self::inbox_history( $key, $config, 1 ) ) {
			return self::respond( array_merge( $input, [
				'success' => true,
				'skipped' => true,
				'reason'  => 'The Inbox already records this conversation.',
			] ) );
		}

		$role    = ( 'assistant' === ( $config['role'] ?? 'user' ) ) ? 'assistant' : 'user';
		$content = (string) ( $config['content'] ?? '' );

		if ( '' === trim( $content ) ) {
			return self::respond( array_merge( $input, [
				'success' => false,
				'error' => 'content is required.'
			] ) );
		}

		$record = Conversation::create( [
			'conversation_key' => $key,
			'channel'          => sanitize_text_field( (string) ( $config['channel'] ?? '' ) ),
			'role'             => $role,
			'content'          => $content,
			'created_at'       => current_time( 'mysql' ),
		] );

		$id = is_object( $record ) && isset( $record->id ) ? (int) $record->id : 0;

		return self::respond( array_merge( $input, [
			'success' => (bool) $id,
			'message_id' => $id,
		] ) );
	}

	private static function action_clear( string $key, array $input ): array {
		global $wpdb;
		$table = Conversation::getTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE conversation_key = %s",
				$table,
				$key
			)
		);

		return self::respond( array_merge( $input, [
			'success' => true,
			'deleted' => (int) $deleted,
		] ) );
	}

	private static function respond( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data
		];
	}
}
