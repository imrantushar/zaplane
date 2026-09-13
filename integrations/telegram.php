<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Telegram\ActionsTrait;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telegram extends IntegrationBase {

	use ActionsTrait;

	private const API_BASE_URL = 'https://api.telegram.org/bot';

	public static function get_slug(): string {
		return 'telegram';
	}

	public static function get_name(): string {
		return 'Telegram';
	}

	public static function get_icon(): string {
		return 'telegram.svg';
	}

	public static function get_triggers(): array {
		return [
			'message_received'             => [
				'label' => 'Message Received',
				'hook'  => 'telegram_webhook_message',
			],
			'command_received'             => [
				'label' => 'Command Received',
				'hook'  => 'telegram_webhook_command',
			],
			'edited_message_received'      => [
				'label' => 'Edited Message',
				'hook'  => 'telegram_webhook_edited_message',
			],
			'channel_post_received'        => [
				'label' => 'Channel Post',
				'hook'  => 'telegram_webhook_channel_post',
			],
			'edited_channel_post_received' => [
				'label' => 'Edited Channel Post',
				'hook'  => 'telegram_webhook_edited_channel_post',
			],
			'callback_query_received'      => [
				'label' => 'Callback Query',
				'hook'  => 'telegram_webhook_callback_query',
			],
			'inline_query_received'        => [
				'label' => 'Inline Query',
				'hook'  => 'telegram_webhook_inline_query',
			],
			'poll_received'                => [
				'label' => 'Poll',
				'hook'  => 'telegram_webhook_poll',
			],
			'pre_checkout_query_received'  => [
				'label' => 'Pre-Checkout Query',
				'hook'  => 'telegram_webhook_pre_checkout_query',
			],
			'shipping_query_received'      => [
				'label' => 'Shipping Query',
				'hook'  => 'telegram_webhook_shipping_query',
			],
			'all_updates'                  => [
				'label' => 'All Updates',
				'hook'  => 'telegram_webhook_all_updates',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'send_message'       => [ 'label' => 'Send Text Message or a Reply' ],
			'send_post'          => [ 'label' => 'Post' ],
			'send_photo'         => [ 'label' => 'Send Photo' ],
			'send_document'      => [ 'label' => 'Send Document' ],
			'send_video'         => [ 'label' => 'Send Video' ],
			'send_audio'         => [ 'label' => 'Send Audio' ],
			'send_media'         => [ 'label' => 'Send Media' ],
			'send_location'      => [ 'label' => 'Send Location' ],
			'pin_message'        => [ 'label' => 'Pin Message' ],
			'send_poll'          => [ 'label' => 'Send Poll' ],
			'get_updates'        => [ 'label' => 'Fetch Updates from Bot' ],
			'send_contact'       => [ 'label' => 'Send Contact' ],
			'create_invite_link' => [ 'label' => 'Create Invite Link' ],
			'revoke_invite_link' => [ 'label' => 'Revoke Invite Link' ],
			'ban_user'           => [ 'label' => 'Ban User' ],
			'unban_user'         => [ 'label' => 'Unban User' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$chat_id_field = [
			'key'         => 'chat_id',
			'type'        => 'text',
			'label'       => 'Chat ID',
			'placeholder' => '987654321 or @channelname',
			'required'    => true,
			'help'        => 'Telegram chat/user ID or @username for public channels.',
		];

		if ( 'send_message' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'text',
					'type'        => 'textarea',
					'label'       => 'Message Text',
					'placeholder' => 'Enter your message... Use {{variable}} for dynamic values',
					'required'    => true,
				],
				[
					'key'         => 'reply_to_message_id',
					'type'        => 'text',
					'label'       => 'Reply To Message ID',
					'placeholder' => '{{telegram_message_id}}',
					'required'    => false,
					'help'        => 'Leave blank to send as a new message, or provide a message_id to send as a reply.',
				],
				[
					'key'      => 'parse_mode',
					'type'     => 'select',
					'label'    => 'Parse Mode',
					'required' => false,
					'options'  => [
						[
							'value' => '',
							'label' => 'None'
						],
						[
							'value' => 'HTML',
							'label' => 'HTML'
						],
						[
							'value' => 'Markdown',
							'label' => 'Markdown'
						],
						[
							'value' => 'MarkdownV2',
							'label' => 'MarkdownV2'
						],
					],
					'help'     => 'Formatting mode for the message text.',
				],
				[
					'key'      => 'disable_notification',
					'type'     => 'select',
					'label'    => 'Silent Message',
					'required' => false,
					'options'  => [
						[
							'value' => 'false',
							'label' => 'No (with notification)'
						],
						[
							'value' => 'true',
							'label' => 'Yes (silent)'
						],
					],
				],
			];
		}//end if

		if ( 'send_post' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'text',
					'type'        => 'textarea',
					'label'       => 'Post Text',
					'placeholder' => 'Enter your post content... Use {{variable}} for dynamic values',
					'required'    => true,
				],
				[
					'key'         => 'inline_buttons',
					'type'        => 'textarea',
					'label'       => 'Inline Buttons',
					'placeholder' => "Approve:approve_order_88, Reject:reject_order_88\nView Details:view_88",
					'required'    => false,
					'help'        => 'Optional. Each line = one row of buttons. Multiple buttons in a row separated by comma. Each button format: Label:callback_data',
				],
				[
					'key'      => 'parse_mode',
					'type'     => 'select',
					'label'    => 'Parse Mode',
					'required' => false,
					'options'  => [
						[ 'value' => '', 'label' => 'None' ],
						[ 'value' => 'HTML', 'label' => 'HTML' ],
						[ 'value' => 'Markdown', 'label' => 'Markdown' ],
						[ 'value' => 'MarkdownV2', 'label' => 'MarkdownV2' ],
					],
					'help'     => 'Formatting mode for the post text.',
				],
				[
					'key'      => 'disable_notification',
					'type'     => 'select',
					'label'    => 'Silent Post',
					'required' => false,
					'options'  => [
						[ 'value' => 'false', 'label' => 'No (with notification)' ],
						[ 'value' => 'true', 'label' => 'Yes (silent)' ],
					],
				],
			];
		}//end if

		if ( 'send_photo' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'photo',
					'type'        => 'text',
					'label'       => 'Photo URL',
					'placeholder' => 'https://example.com/image.jpg',
					'required'    => true,
					'help'        => 'Publicly accessible URL to a JPG, PNG, GIF, BMP, or WEBP image.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_document' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'document',
					'type'        => 'text',
					'label'       => 'Document URL',
					'placeholder' => 'https://example.com/file.pdf',
					'required'    => true,
					'help'        => 'Publicly accessible URL to the document.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_video' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'video',
					'type'        => 'text',
					'label'       => 'Video URL',
					'placeholder' => 'https://example.com/video.mp4',
					'required'    => true,
					'help'        => 'Publicly accessible URL to an MP4 video.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_audio' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'audio',
					'type'        => 'text',
					'label'       => 'Audio URL',
					'placeholder' => 'https://example.com/audio.mp3',
					'required'    => true,
					'help'        => 'Publicly accessible URL to an MP3 or M4A audio file.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_location' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'latitude',
					'type'        => 'text',
					'label'       => 'Latitude',
					'placeholder' => '40.7128',
					'required'    => true,
				],
				[
					'key'         => 'longitude',
					'type'        => 'text',
					'label'       => 'Longitude',
					'placeholder' => '-74.0060',
					'required'    => true,
				],
			];
		}

		if ( 'pin_message' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'message_id',
					'type'        => 'text',
					'label'       => 'Message ID',
					'placeholder' => '{{telegram_message_id}}',
					'required'    => true,
					'help'        => 'ID of the message to pin. Use {{telegram_message_id}} from a previous Send Message node.',
				],
				[
					'key'      => 'disable_notification',
					'type'     => 'select',
					'label'    => 'Silent Pin',
					'required' => false,
					'options'  => [
						[
							'value' => 'false',
							'label' => 'No (with notification)'
						],
						[
							'value' => 'true',
							'label' => 'Yes (silent)'
						],
					],
				],
			];
		}//end if

		if ( 'send_poll' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'question',
					'type'        => 'text',
					'label'       => 'Question',
					'placeholder' => 'What do you prefer?',
					'required'    => true,
					'help'        => 'The poll question (1–300 characters).',
				],
				[
					'key'         => 'options',
					'type'        => 'textarea',
					'label'       => 'Options',
					'placeholder' => "Option A\nOption B\nOption C",
					'required'    => true,
					'help'        => 'One option per line (2–10 options required).',
				],
				[
					'key'      => 'is_anonymous',
					'type'     => 'select',
					'label'    => 'Anonymous Poll',
					'required' => false,
					'options'  => [
						[
							'value' => 'true',
							'label' => 'Yes (anonymous)'
						],
						[
							'value' => 'false',
							'label' => 'No (show voters)'
						],
					],
				],
			];
		}//end if

		if ( 'get_updates' === $action ) {
			return [
				[
					'key'         => 'offset',
					'type'        => 'text',
					'label'       => 'Offset',
					'placeholder' => '{{telegram_last_update_id}}',
					'required'    => false,
					'help'        => 'Identifier of the first update to return. Pass the last processed update_id + 1 to avoid re-fetching old updates.',
				],
				[
					'key'         => 'limit',
					'type'        => 'text',
					'label'       => 'Limit',
					'placeholder' => '100',
					'required'    => false,
					'help'        => 'Number of updates to fetch per call (1–100). Defaults to 100.',
				],
				[
					'key'         => 'timeout',
					'type'        => 'text',
					'label'       => 'Timeout (seconds)',
					'placeholder' => '0',
					'required'    => false,
					'help'        => 'Long-polling timeout in seconds. Leave at 0 for short polling. Note: Telegram rejects getUpdates while a webhook is active — delete the webhook first.',
				],
			];
		}//end if

		if ( 'send_media' === $action ) {
			return [
				$chat_id_field,
				[
					'key'      => 'media_type',
					'type'     => 'select',
					'label'    => 'Media Type',
					'required' => true,
					'options'  => [
						[
							'value' => 'photo',
							'label' => 'Photo'
						],
						[
							'value' => 'document',
							'label' => 'Document'
						],
						[
							'value' => 'video',
							'label' => 'Video'
						],
						[
							'value' => 'audio',
							'label' => 'Audio'
						],
					],
				],
				[
					'key'         => 'media',
					'type'        => 'text',
					'label'       => 'Media URL',
					'placeholder' => 'https://example.com/file.jpg',
					'required'    => true,
					'help'        => 'Publicly accessible URL — must match the selected Media Type.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}//end if

		if ( 'send_contact' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'phone_number',
					'type'        => 'text',
					'label'       => 'Phone Number',
					'placeholder' => '+15551234567',
					'required'    => true,
					'help'        => 'Contact phone number, in international format.',
				],
				[
					'key'         => 'first_name',
					'type'        => 'text',
					'label'       => 'First Name',
					'placeholder' => 'John',
					'required'    => true,
				],
				[
					'key'         => 'last_name',
					'type'        => 'text',
					'label'       => 'Last Name',
					'placeholder' => 'Doe',
					'required'    => false,
				],
				[
					'key'         => 'vcard',
					'type'        => 'textarea',
					'label'       => 'vCard',
					'placeholder' => 'Optional vCard-formatted additional data',
					'required'    => false,
					'help'        => 'Additional contact data in vCard format (0–2048 bytes).',
				],
			];
		}//end if

		if ( 'create_invite_link' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'name',
					'type'        => 'text',
					'label'       => 'Link Name',
					'placeholder' => 'Optional invite link name',
					'required'    => false,
					'help'        => '0–32 characters, shown to admins in the chat — not to invited users.',
				],
				[
					'key'         => 'expire_date',
					'type'        => 'text',
					'label'       => 'Expire Date (Unix timestamp)',
					'placeholder' => '1782633600',
					'required'    => false,
					'help'        => 'When the link stops working. Leave blank for no expiry.',
				],
				[
					'key'         => 'member_limit',
					'type'        => 'text',
					'label'       => 'Member Limit',
					'placeholder' => '50',
					'required'    => false,
					'help'        => 'Max number of users who can join via this link (1–99999). Ignored if join requests are required.',
				],
				[
					'key'      => 'creates_join_request',
					'type'     => 'select',
					'label'    => 'Requires Admin Approval',
					'required' => false,
					'options'  => [
						[
							'value' => 'false',
							'label' => 'No'
						],
						[
							'value' => 'true',
							'label' => 'Yes (join requests)'
						],
					],
					'help'     => 'If enabled, Member Limit cannot also be set.',
				],
			];
		}//end if

		if ( 'revoke_invite_link' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'invite_link',
					'type'        => 'text',
					'label'       => 'Invite Link',
					'placeholder' => 'https://t.me/+AbCdEfGhIjK',
					'required'    => true,
					'help'        => 'The invite link to revoke. Use {{telegram_invite_link}} from a previous Create Invite Link node.',
				],
			];
		}

		if ( 'ban_user' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'user_id',
					'type'        => 'text',
					'label'       => 'User ID',
					'placeholder' => '123456789',
					'required'    => true,
					'help'        => 'Telegram numeric user ID of the member to ban.',
				],
				[
					'key'         => 'until_date',
					'type'        => 'text',
					'label'       => 'Until Date (Unix timestamp)',
					'placeholder' => '1782633600',
					'required'    => false,
					'help'        => 'When the ban lifts. Must be 30 seconds to 366 days out, otherwise treated as a permanent ban. Leave blank to ban permanently.',
				],
				[
					'key'      => 'revoke_messages',
					'type'     => 'select',
					'label'    => 'Delete Their Messages',
					'required' => false,
					'options'  => [
						[
							'value' => 'false',
							'label' => 'No'
						],
						[
							'value' => 'true',
							'label' => 'Yes, delete all recent messages'
						],
					],
				],
			];
		}//end if

		if ( 'unban_user' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'user_id',
					'type'        => 'text',
					'label'       => 'User ID',
					'placeholder' => '123456789',
					'required'    => true,
					'help'        => 'Telegram numeric user ID of the member to unban.',
				],
				[
					'key'      => 'only_if_banned',
					'type'     => 'select',
					'label'    => 'Only If Currently Banned',
					'required' => false,
					'options'  => [
						[
							'value' => 'true',
							'label' => 'Yes'
						],
						[
							'value' => 'false',
							'label' => 'No, always unban'
						],
					],
					'help'     => 'Do nothing if the user is already not banned, instead of erroring.',
				],
			];
		}//end if

		return [];
	}

	private static function detect_update_shape( array $payload ): string {
		if ( isset( $payload['message_id'], $payload['chat'] ) ) {
			$is_channel = ( 'channel' === ( $payload['chat']['type'] ?? '' ) );
			$is_edited  = isset( $payload['edit_date'] );

			if ( $is_channel && $is_edited ) {
				return 'edited_channel_post';
			}

			if ( $is_channel ) {
				return 'channel_post';
			}

			if ( $is_edited ) {
				return 'edited_message';
			}

			return 'message';
		}

		if ( isset( $payload['shipping_address'] ) ) {
			return 'shipping_query';
		}

		if ( isset( $payload['invoice_payload'], $payload['currency'] ) ) {
			return 'pre_checkout_query';
		}

		if ( isset( $payload['options'], $payload['question'] ) ) {
			return 'poll';
		}

		if ( isset( $payload['chat_instance'] ) ) {
			return 'callback_query';
		}

		if ( isset( $payload['query'] ) && isset( $payload['offset'] ) ) {
			return 'inline_query';
		}

		return 'unknown';
	}

	private const ALL_KNOWN_UPDATE_TYPES = [
		'message',
		'edited_message',
		'channel_post',
		'edited_channel_post',
		'callback_query',
		'inline_query',
		'poll',
		'pre_checkout_query',
		'shipping_query',
	];

	public static function get_update_type_for_event( string $event ): string {
		$map = [
			'message_received'             => 'message',
			'command_received'             => 'message',
			'edited_message_received'      => 'edited_message',
			'channel_post_received'        => 'channel_post',
			'edited_channel_post_received' => 'edited_channel_post',
			'callback_query_received'      => 'callback_query',
			'inline_query_received'        => 'inline_query',
			'poll_received'                => 'poll',
			'pre_checkout_query_received'  => 'pre_checkout_query',
			'shipping_query_received'      => 'shipping_query',
		];

		return $map[ $event ] ?? '';
	}

	public static function get_allowed_updates_for_events( array $events ): array {
		if ( in_array( 'all_updates', $events, true ) ) {
			return self::ALL_KNOWN_UPDATE_TYPES;
		}

		$update_types = [];

		foreach ( $events as $event ) {
			$type = self::get_update_type_for_event( $event );

			if ( '' !== $type ) {
				$update_types[ $type ] = true;
			}
		}

		return array_keys( $update_types );
	}

	public static function resolve_trigger( array $node, array $args ) {
		$payload = $args[0] ?? [];

		if ( ! is_array( $payload ) || empty( $payload ) ) {
			return false;
		}

		// When the webhook arrived on a per-connection URL
		// (.../incoming/telegram/{connection_id}), IncomingWebhookController
		// passes that connection's id as the second hook argument. A shared or
		// legacy URL (no id in the path — single-bot sites that haven't
		// re-saved their Webhook Setup yet) passes null, in which case every
		// Telegram trigger node still matches regardless of which connection
		// it's set to, same as before per-connection routing existed. Once a
		// connection id IS present, a mismatch means this update came from a
		// different bot than the one this node is configured for — skip it so
		// multiple bots on the same site don't cross-fire each other's
		// workflows.
		$source_connection_id = $args[1] ?? null;
		$node_connection_id   = $node['connection_id'] ?? null;

		if ( null !== $source_connection_id && null !== $node_connection_id
			&& (int) $source_connection_id !== (int) $node_connection_id
		) {
			return false;
		}

		// automation.php's trigger dispatcher calls resolve_trigger() with the
		// graph node's already-unwrapped `data` object, so the event key lives
		// at $node['event'] here — not $node['data']['event'].
		$event = $node['event'] ?? '';

		if ( 'all_updates' === $event ) {
			$shape = self::detect_update_shape( $payload );

			$message_shapes = [ 'message', 'edited_message', 'channel_post', 'edited_channel_post' ];

			if ( in_array( $shape, $message_shapes, true ) ) {
				return self::resolve_message_trigger( $payload, $shape );
			}

			if ( 'callback_query' === $shape ) {
				return self::resolve_callback_query_trigger( $payload );
			}

			if ( 'inline_query' === $shape ) {
				return self::resolve_inline_query_trigger( $payload );
			}

			if ( 'poll' === $shape ) {
				return self::resolve_poll_trigger( $payload );
			}

			if ( 'pre_checkout_query' === $shape ) {
				return self::resolve_pre_checkout_query_trigger( $payload );
			}

			if ( 'shipping_query' === $shape ) {
				return self::resolve_shipping_query_trigger( $payload );
			}

			return false;
		}

		$update_type = self::get_update_type_for_event( $event );

		if ( in_array( $update_type, [ 'message', 'edited_message', 'channel_post', 'edited_channel_post' ], true ) ) {
			return self::resolve_message_trigger( $payload, $update_type );
		}

		if ( 'callback_query_received' === $event ) {
			return self::resolve_callback_query_trigger( $payload );
		}

		if ( 'inline_query_received' === $event ) {
			return self::resolve_inline_query_trigger( $payload );
		}

		if ( 'poll_received' === $event ) {
			return self::resolve_poll_trigger( $payload );
		}

		if ( 'pre_checkout_query_received' === $event ) {
			return self::resolve_pre_checkout_query_trigger( $payload );
		}

		if ( 'shipping_query_received' === $event ) {
			return self::resolve_shipping_query_trigger( $payload );
		}

		return false;
	}

	private static function resolve_message_trigger( array $message, string $update_type = 'message' ): array {
		$from = $message['from'] ?? [];
		$chat = $message['chat'] ?? [];
		$text = $message['text'] ?? '';

		$base = [
			'telegram_update_type'     => $update_type,
			'telegram_message_id'      => $message['message_id'] ?? '',
			'telegram_text'            => $text,
			'telegram_chat_id'         => $chat['id'] ?? '',
			'telegram_chat_type'       => $chat['type'] ?? '',
			'telegram_from_id'         => $from['id'] ?? '',
			'telegram_from_first_name' => $from['first_name'] ?? '',
			'telegram_from_last_name'  => $from['last_name'] ?? '',
			'telegram_from_username'   => $from['username'] ?? '',
			'telegram_date'            => $message['date'] ?? '',
		];

		if ( isset( $message['edit_date'] ) ) {
			$base['telegram_edit_date'] = $message['edit_date'];
		}

		if ( 0 === strpos( (string) $text, '/' ) ) {
			$parts    = explode( ' ', $text, 2 );
			$command  = $parts[0] ?? '';
			$cmd_args = trim( $parts[1] ?? '' );

			$base['telegram_command']      = $command;
			$base['telegram_command_args'] = $cmd_args;
		}

		return $base;
	}

	private static function resolve_callback_query_trigger( array $query ): array {
		$from    = $query['from'] ?? [];
		$message = $query['message'] ?? [];
		$chat    = $message['chat'] ?? [];

		return [
			'telegram_update_type'     => 'callback_query',
			'telegram_callback_id'     => $query['id'] ?? '',
			'telegram_callback_data'   => $query['data'] ?? '',
			'telegram_chat_instance'   => $query['chat_instance'] ?? '',
			'telegram_message_id'      => $message['message_id'] ?? '',
			'telegram_chat_id'         => $chat['id'] ?? '',
			'telegram_from_id'         => $from['id'] ?? '',
			'telegram_from_first_name' => $from['first_name'] ?? '',
			'telegram_from_last_name'  => $from['last_name'] ?? '',
			'telegram_from_username'   => $from['username'] ?? '',
		];
	}

	private static function resolve_inline_query_trigger( array $query ): array {
		$from = $query['from'] ?? [];

		return [
			'telegram_update_type'     => 'inline_query',
			'telegram_inline_query_id' => $query['id'] ?? '',
			'telegram_query_text'      => $query['query'] ?? '',
			'telegram_offset'          => $query['offset'] ?? '',
			'telegram_from_id'         => $from['id'] ?? '',
			'telegram_from_first_name' => $from['first_name'] ?? '',
			'telegram_from_last_name'  => $from['last_name'] ?? '',
			'telegram_from_username'   => $from['username'] ?? '',
		];
	}

	private static function resolve_poll_trigger( array $poll ): array {
		return [
			'telegram_update_type'      => 'poll',
			'telegram_poll_id'          => $poll['id'] ?? '',
			'telegram_poll_question'    => $poll['question'] ?? '',
			'telegram_poll_options'     => wp_json_encode( $poll['options'] ?? [] ),
			'telegram_poll_total_votes' => $poll['total_voter_count'] ?? 0,
			'telegram_poll_is_closed'   => ! empty( $poll['is_closed'] ),
		];
	}

	private static function resolve_pre_checkout_query_trigger( array $query ): array {
		$from = $query['from'] ?? [];

		return [
			'telegram_update_type'     => 'pre_checkout_query',
			'telegram_pre_checkout_id' => $query['id'] ?? '',
			'telegram_currency'        => $query['currency'] ?? '',
			'telegram_total_amount'    => $query['total_amount'] ?? 0,
			'telegram_invoice_payload' => $query['invoice_payload'] ?? '',
			'telegram_from_id'         => $from['id'] ?? '',
			'telegram_from_username'   => $from['username'] ?? '',
		];
	}

	private static function resolve_shipping_query_trigger( array $query ): array {
		$from    = $query['from'] ?? [];
		$address = $query['shipping_address'] ?? [];

		return [
			'telegram_update_type'      => 'shipping_query',
			'telegram_shipping_id'      => $query['id'] ?? '',
			'telegram_invoice_payload'  => $query['invoice_payload'] ?? '',
			'telegram_from_id'          => $from['id'] ?? '',
			'telegram_from_username'    => $from['username'] ?? '',
			'telegram_shipping_country' => $address['country_code'] ?? '',
			'telegram_shipping_state'   => $address['state'] ?? '',
			'telegram_shipping_city'    => $address['city'] ?? '',
			'telegram_shipping_zip'     => $address['post_code'] ?? '',
		];
	}

	public static function get_trigger_sample_output( string $event ): array {
		$message_base = [
			'telegram_update_type'     => 'message',
			'telegram_message_id'      => 1042,
			'telegram_text'            => 'Hello from Zaplane!',
			'telegram_chat_id'         => 987654321,
			'telegram_chat_type'       => 'private',
			'telegram_from_id'         => 123456789,
			'telegram_from_first_name' => 'Jane',
			'telegram_from_last_name'  => 'Doe',
			'telegram_from_username'   => 'janedoe',
			'telegram_date'            => 1782633600,
		];

		$command = array_merge(
			$message_base,
			[
				'telegram_text'         => '/start welcome',
				'telegram_command'      => '/start',
				'telegram_command_args' => 'welcome',
			]
		);

		$edited_message = array_merge(
			$message_base,
			[
				'telegram_update_type' => 'edited_message',
				'telegram_text'        => 'Hello from Zaplane! (edited)',
				'telegram_edit_date'   => 1782633700,
			]
		);

		$channel_post = [
			'telegram_update_type'     => 'channel_post',
			'telegram_message_id'      => 2001,
			'telegram_text'            => 'New announcement!',
			'telegram_chat_id'         => -1001234567890,
			'telegram_chat_type'       => 'channel',
			'telegram_from_id'         => '',
			'telegram_from_first_name' => '',
			'telegram_from_last_name'  => '',
			'telegram_from_username'   => '',
			'telegram_date'            => 1782633600,
		];

		$edited_channel_post = array_merge(
			$channel_post,
			[
				'telegram_update_type' => 'edited_channel_post',
				'telegram_text'        => 'New announcement! (edited)',
				'telegram_edit_date'   => 1782633700,
			]
		);

		$callback_query = [
			'telegram_update_type'     => 'callback_query',
			'telegram_callback_id'     => '4382bfdwcz12345',
			'telegram_callback_data'   => 'approve_order_88',
			'telegram_chat_instance'   => '1234567890123456789',
			'telegram_message_id'      => 1050,
			'telegram_chat_id'         => 987654321,
			'telegram_from_id'         => 123456789,
			'telegram_from_first_name' => 'Jane',
			'telegram_from_last_name'  => 'Doe',
			'telegram_from_username'   => 'janedoe',
		];

		$inline_query = [
			'telegram_update_type'     => 'inline_query',
			'telegram_inline_query_id' => '134567890098765432',
			'telegram_query_text'      => 'pizza',
			'telegram_offset'          => '',
			'telegram_from_id'         => 123456789,
			'telegram_from_first_name' => 'Jane',
			'telegram_from_last_name'  => 'Doe',
			'telegram_from_username'   => 'janedoe',
		];

		$poll = [
			'telegram_update_type'      => 'poll',
			'telegram_poll_id'          => '5800862329547522049',
			'telegram_poll_question'    => 'What do you prefer?',
			'telegram_poll_options'     => '[{"text":"Option A","voter_count":3},{"text":"Option B","voter_count":1}]',
			'telegram_poll_total_votes' => 4,
			'telegram_poll_is_closed'   => false,
		];

		$pre_checkout_query = [
			'telegram_update_type'     => 'pre_checkout_query',
			'telegram_pre_checkout_id' => '2839471928374',
			'telegram_currency'        => 'USD',
			'telegram_total_amount'    => 2500,
			'telegram_invoice_payload' => 'order_88',
			'telegram_from_id'         => 123456789,
			'telegram_from_username'   => 'janedoe',
		];

		$shipping_query = [
			'telegram_update_type'      => 'shipping_query',
			'telegram_shipping_id'      => '9834712983741',
			'telegram_invoice_payload'  => 'order_88',
			'telegram_from_id'          => 123456789,
			'telegram_from_username'    => 'janedoe',
			'telegram_shipping_country' => 'US',
			'telegram_shipping_state'   => 'NY',
			'telegram_shipping_city'    => 'New York',
			'telegram_shipping_zip'     => '10001',
		];

		$samples = [
			'message_received'             => $message_base,
			'command_received'             => $command,
			'edited_message_received'      => $edited_message,
			'channel_post_received'        => $channel_post,
			'edited_channel_post_received' => $edited_channel_post,
			'callback_query_received'      => $callback_query,
			'inline_query_received'        => $inline_query,
			'poll_received'                => $poll,
			'pre_checkout_query_received'  => $pre_checkout_query,
			'shipping_query_received'      => $shipping_query,
			'all_updates'                  => $message_base,
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( false !== strpos( $event, 'command' ) ) {
			return $command;
		}

		return $message_base;
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for Telegram' );
		}

		$token = $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Telegram credentials (bot_token) are required' );
		}

		if ( 'send_message' === $action ) {
			return self::action_send_message( $node, $input, $token );
		}

		if ( 'send_post' === $action ) {
			return self::action_send_post( $node, $input, $token );
		}

		if ( 'send_photo' === $action ) {
			return self::action_send_media( $node, $input, $token, 'photo', 'sendPhoto' );
		}

		if ( 'send_document' === $action ) {
			return self::action_send_media( $node, $input, $token, 'document', 'sendDocument' );
		}

		if ( 'send_video' === $action ) {
			return self::action_send_media( $node, $input, $token, 'video', 'sendVideo' );
		}

		if ( 'send_audio' === $action ) {
			return self::action_send_media( $node, $input, $token, 'audio', 'sendAudio' );
		}

		if ( 'send_media' === $action ) {
			return self::action_send_media_unified( $node, $input, $token );
		}

		if ( 'send_location' === $action ) {
			return self::action_send_location( $node, $input, $token );
		}

		if ( 'pin_message' === $action ) {
			return self::action_pin_message( $node, $input, $token );
		}

		if ( 'send_poll' === $action ) {
			return self::action_send_poll( $node, $input, $token );
		}

		if ( 'get_updates' === $action ) {
			return self::action_get_updates( $node, $input, $token );
		}

		if ( 'send_contact' === $action ) {
			return self::action_send_contact( $node, $input, $token );
		}

		if ( 'create_invite_link' === $action ) {
			return self::action_create_invite_link( $node, $input, $token );
		}

		if ( 'revoke_invite_link' === $action ) {
			return self::action_revoke_invite_link( $node, $input, $token );
		}

		if ( 'ban_user' === $action ) {
			return self::action_ban_user( $node, $input, $token );
		}

		if ( 'unban_user' === $action ) {
			return self::action_unban_user( $node, $input, $token );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	/**
	 * Telegram pushes updates to a callback URL you register with setWebhook.
	 * Without this the incoming-webhook endpoint rejected every delivery with
	 * "does not support incoming webhooks", so telegram_webhook_message /
	 * telegram_webhook_command could never fire at all.
	 */
	public static function supports_webhook(): bool {
		return true;
	}

	public static function get_webhook_setup_fields(): array {
		return [
			[
				'key'      => 'secret_token',
				'label'    => 'Secret Token',
				'type'     => 'password',
				'generate' => true,
				'help'     => 'Telegram sends this back on every request, which is how this endpoint tells real deliveries from forged ones. Saving this automatically calls setWebhook for every connected bot, each on its own callback URL — no manual API call needed.',
			],
		];
	}

	public static function on_webhook_config_saved( array $config ): void {
		$secret_token = $config['secret_token'] ?? '';

		foreach ( \Zaplane\Models\Connection::forApp( 'telegram' ) as $connection ) {
			if ( ! $connection->isActive() ) {
				continue;
			}

			$token = $connection->getCredentials()['bot_token'] ?? '';

			if ( '' === $token ) {
				continue;
			}

			$payload = [
				'url'             => static::get_webhook_url_for_connection( (int) $connection->id ),
				'allowed_updates' => self::ALL_KNOWN_UPDATE_TYPES,
			];

			if ( '' !== $secret_token ) {
				$payload['secret_token'] = $secret_token;
			}

			try {
				self::telegram_request( $token, 'setWebhook', $payload );
			} catch ( \Exception $e ) {
				// Best-effort — one bad/revoked bot token on the site shouldn't
				// block registering the webhook for the site's other bots.
				continue;
			}
		}
	}

	/**
	 * Telegram echoes the secret_token given to setWebhook in the
	 * X-Telegram-Bot-Api-Secret-Token header. Unset means the site owner hasn't
	 * configured one yet — allowed so first-time setup isn't a chicken-and-egg,
	 * but the setup panel flags it.
	 */
	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$secret = self::get_webhook_setting( 'secret_token' );

		if ( '' === $secret ) {
			return true;
		}

		$provided = (string) $request->get_header( 'x_telegram_bot_api_secret_token' );

		return '' !== $provided && hash_equals( $secret, $provided );
	}

	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$update = $request->get_json_params();

		if ( ! is_array( $update ) ) {
			$decoded = json_decode( (string) $request->get_body(), true );
			$update  = is_array( $decoded ) ? $decoded : [];
		}

		if ( empty( $update ) ) {
			return null;
		}

		$type_keys = [
			'message',
			'edited_message',
			'channel_post',
			'edited_channel_post',
			'callback_query',
			'inline_query',
			'poll',
			'pre_checkout_query',
			'shipping_query',
		];

		$update_key = '';
		$payload    = null;

		foreach ( $type_keys as $key ) {
			if ( isset( $update[ $key ] ) && is_array( $update[ $key ] ) ) {
				$update_key = $key;
				$payload    = $update[ $key ];
				break;
			}
		}

		// Update type we don't watch for (poll_answer, chat_member, my_chat_member,
		// chat_join_request, chosen_inline_result, etc.) — ignore.
		if ( null === $payload ) {
			return null;
		}

		$message_like = [ 'message', 'edited_message', 'channel_post', 'edited_channel_post' ];

		if ( in_array( $update_key, $message_like, true ) ) {
			// Ignore anything with no text — callback-only messages, join/leave
			// notices, stickers/photos with no caption, etc.
			if ( '' === (string) ( $payload['text'] ?? '' ) ) {
				return null;
			}

			// Never react to another bot's messages (or our own) — that loops.
			if ( ! empty( $payload['from']['is_bot'] ) ) {
				return null;
			}
		}

		$event_map = [
			'message'             => 'message_received',
			'edited_message'      => 'edited_message_received',
			'channel_post'        => 'channel_post_received',
			'edited_channel_post' => 'edited_channel_post_received',
			'callback_query'      => 'callback_query_received',
			'inline_query'        => 'inline_query_received',
			'poll'                => 'poll_received',
			'pre_checkout_query'  => 'pre_checkout_query_received',
			'shipping_query'      => 'shipping_query_received',
		];

		$event = $event_map[ $update_key ] ?? '';

		// A plain "message" that starts with "/" is a command, not free text.
		if ( 'message_received' === $event && 0 === strpos( (string) ( $payload['text'] ?? '' ), '/' ) ) {
			$event = 'command_received';
		}

		return [
			'event'       => $event,
			'update_type' => $update_key,
			'payload'     => $payload,
		];
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'api_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'bot_token' => [
				'type'        => 'password',
				'label'       => 'Bot Token',
				'placeholder' => '123456789:AABBCCDDEEFFxxxxxxxxxxxx',
				'required'    => true,
				'help'        => 'The token you received from @BotFather when creating your bot.',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'bot_token is required',
				'details' => [],
			];
		}

		$response = wp_remote_get( self::API_BASE_URL . $token . '/getMe' );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $response->get_error_message(),
				'details' => [],
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			return [
				'success' => false,
				'message' => $body['description'] ?? 'Unknown Telegram API error',
				'details' => [],
			];
		}

		$bot = $body['result'] ?? [];

		return [
			'success' => true,
			'message' => 'Connected as @' . ( $bot['username'] ?? 'unknown' ),
			'details' => [
				'bot_id'    => $bot['id'] ?? '',
				'username'  => $bot['username'] ?? '',
				'name'      => $bot['first_name'] ?? '',
			],
		];
	}

	/**
	 * Call a Telegram Bot API method.
	 *
	 * @throws \Exception on WP_Error or API error.
	 */
	private static function telegram_request( string $token, string $method, array $payload ): array {
		$url = self::API_BASE_URL . $token . '/' . $method;

		$response = wp_remote_post(
			$url,
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Telegram API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['ok'] ) ) {
			$message = $data['description'] ?? 'Unknown Telegram API error';
			throw new \Exception( 'Telegram API error: ' . esc_html( $message ) );
		}

		return $data;
	}
}
