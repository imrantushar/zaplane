<?php
namespace Zaplane\Integrations\Telegram;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ActionsTrait{

    private static function action_send_message( array $node, array $input, string $token ): array {
		$chat_id = $node['data']['config']['chat_id'] ?? '';
		$text    = $node['data']['config']['text'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $text ) ) {
			throw new \Exception( 'Telegram: message text is required' );
		}

		$payload = [
			'chat_id' => $chat_id,
			'text'    => $text,
		];

		$parse_mode = $node['data']['config']['parse_mode'] ?? '';
		if ( ! empty( $parse_mode ) ) {
			$payload['parse_mode'] = $parse_mode;
		}

		$disable_notification = $node['data']['config']['disable_notification'] ?? 'false';
		if ( 'true' === $disable_notification ) {
			$payload['disable_notification'] = true;
		}

		$reply_to = $node['data']['config']['reply_to_message_id'] ?? '';
		if ( '' !== $reply_to ) {
			// Telegram deprecated the top-level reply_to_message_id param in
			// favour of reply_parameters as of Bot API 7.0.
			$payload['reply_parameters'] = [ 'message_id' => (int) $reply_to ];
		}

		$body = self::telegram_request( $token, 'sendMessage', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_send_post( array $node, array $input, string $token ): array {
		$chat_id = $node['data']['config']['chat_id'] ?? '';
		$text    = $node['data']['config']['text'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $text ) ) {
			throw new \Exception( 'Telegram: post text is required' );
		}

		$payload = [
			'chat_id' => $chat_id,
			'text'    => $text,
		];

		$parse_mode = $node['data']['config']['parse_mode'] ?? '';
		if ( ! empty( $parse_mode ) ) {
			$payload['parse_mode'] = $parse_mode;
		}

		$disable_notification = $node['data']['config']['disable_notification'] ?? 'false';
		if ( 'true' === $disable_notification ) {
			$payload['disable_notification'] = true;
		}

		$inline_buttons_raw = $node['data']['config']['inline_buttons'] ?? '';
		if ( '' !== trim( $inline_buttons_raw ) ) {
			$keyboard = self::parse_inline_buttons( $inline_buttons_raw );

			if ( ! empty( $keyboard ) ) {
				$payload['reply_markup'] = [ 'inline_keyboard' => $keyboard ];
			}
		}

		$body = self::telegram_request( $token, 'sendMessage', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function parse_inline_buttons( string $raw ): array {
		$rows = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );

		$keyboard = [];

		foreach ( $rows as $row ) {
			$buttons = array_filter( array_map( 'trim', explode( ',', $row ) ) );
			$row_out = [];

			foreach ( $buttons as $button ) {
				$pos = strrpos( $button, ':' );

				if ( false === $pos ) {
					continue;
				}

				$label = trim( substr( $button, 0, $pos ) );
				$data  = trim( substr( $button, $pos + 1 ) );

				if ( '' === $label || '' === $data ) {
					continue;
				}

				$row_out[] = [
					'text'          => $label,
					'callback_data' => $data,
				];
			}

			if ( ! empty( $row_out ) ) {
				$keyboard[] = $row_out;
			}
		}

		return $keyboard;
	}

	private static function action_send_media( array $node, array $input, string $token, string $field, string $method ): array {
		$chat_id   = $node['data']['config']['chat_id'] ?? '';
		$media_url = $node['data']['config'][ $field ] ?? '';
		$caption   = $node['data']['config']['caption'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		$label_map = [
			'photo'    => 'photo URL',
			'document' => 'document URL',
			'video'    => 'video URL',
			'audio'    => 'audio URL',
		];

		if ( empty( $media_url ) ) {
			throw new \Exception( 'Telegram: ' . ( $label_map[ $field ] ?? $field . ' URL' ) . ' is required' );
		}

		$payload = [
			'chat_id' => $chat_id,
			$field    => $media_url,
		];

		if ( ! empty( $caption ) ) {
			$payload['caption'] = $caption;
		}

		$body = self::telegram_request( $token, $method, $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_send_media_unified( array $node, array $input, string $token ): array {
		$media_type = $node['data']['config']['media_type'] ?? '';

		$method_map = [
			'photo'    => 'sendPhoto',
			'document' => 'sendDocument',
			'video'    => 'sendVideo',
			'audio'    => 'sendAudio',
		];

		if ( ! isset( $method_map[ $media_type ] ) ) {
			throw new \Exception( 'Telegram: media_type must be one of photo, document, video, audio' );
		}

		$node['data']['config'][ $media_type ] = $node['data']['config']['media'] ?? '';

		return self::action_send_media( $node, $input, $token, $media_type, $method_map[ $media_type ] );
	}

	private static function action_send_location( array $node, array $input, string $token ): array {
		$chat_id   = $node['data']['config']['chat_id'] ?? '';
		$latitude  = $node['data']['config']['latitude'] ?? '';
		$longitude = $node['data']['config']['longitude'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( $latitude === '' ) {
			throw new \Exception( 'Telegram: latitude is required' );
		}

		if ( $longitude === '' ) {
			throw new \Exception( 'Telegram: longitude is required' );
		}

		$payload = [
			'chat_id'   => $chat_id,
			'latitude'  => (float) $latitude,
			'longitude' => (float) $longitude,
		];

		$body = self::telegram_request( $token, 'sendLocation', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_pin_message( array $node, array $input, string $token ): array {
		$chat_id    = $node['data']['config']['chat_id'] ?? '';
		$message_id = $node['data']['config']['message_id'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $message_id ) ) {
			throw new \Exception( 'Telegram: message_id is required' );
		}

		$payload = [
			'chat_id'    => $chat_id,
			'message_id' => (int) $message_id,
		];

		$disable_notification = $node['data']['config']['disable_notification'] ?? 'false';
		if ( 'true' === $disable_notification ) {
			$payload['disable_notification'] = true;
		}

		self::telegram_request( $token, 'pinChatMessage', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_pinned'  => true,
					'telegram_chat_id' => $chat_id,
				]
			),
		];
	}

	private static function action_send_poll( array $node, array $input, string $token ): array {
		$chat_id      = $node['data']['config']['chat_id'] ?? '';
		$question     = $node['data']['config']['question'] ?? '';
		$options_raw  = $node['data']['config']['options'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $question ) ) {
			throw new \Exception( 'Telegram: poll question is required' );
		}

		if ( empty( $options_raw ) ) {
			throw new \Exception( 'Telegram: poll options are required' );
		}

		$options = array_values(
			array_filter(
				array_map( 'trim', explode( "\n", $options_raw ) )
			)
		);

		$payload = [
			'chat_id'  => $chat_id,
			'question' => $question,
			'options'  => $options,
		];

		$is_anonymous = $node['data']['config']['is_anonymous'] ?? 'true';
		$payload['is_anonymous'] = ( 'true' === $is_anonymous );

		$body = self::telegram_request( $token, 'sendPoll', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_get_updates( array $node, array $input, string $token ): array {
		$offset  = $node['data']['config']['offset'] ?? '';
		$limit   = $node['data']['config']['limit'] ?? '';
		$timeout = $node['data']['config']['timeout'] ?? '';

		$payload = [];

		if ( '' !== $offset ) {
			$payload['offset'] = (int) $offset;
		}

		if ( '' !== $limit ) {
			$payload['limit'] = (int) $limit;
		}

		if ( '' !== $timeout ) {
			$payload['timeout'] = (int) $timeout;
		}

		$body    = self::telegram_request( $token, 'getUpdates', $payload );
		$updates = $body['result'] ?? [];

		$last_update_id = 0;
		foreach ( $updates as $update ) {
			if ( ( $update['update_id'] ?? 0 ) > $last_update_id ) {
				$last_update_id = $update['update_id'];
			}
		}

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_updates'        => $updates,
					'telegram_update_count'   => count( $updates ),
					'telegram_last_update_id' => $last_update_id,
				]
			),
		];
	}

	private static function action_send_contact( array $node, array $input, string $token ): array {
		$chat_id      = $node['data']['config']['chat_id'] ?? '';
		$phone_number = $node['data']['config']['phone_number'] ?? '';
		$first_name   = $node['data']['config']['first_name'] ?? '';
		$last_name    = $node['data']['config']['last_name'] ?? '';
		$vcard        = $node['data']['config']['vcard'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $phone_number ) ) {
			throw new \Exception( 'Telegram: phone_number is required' );
		}

		if ( empty( $first_name ) ) {
			throw new \Exception( 'Telegram: first_name is required' );
		}

		$payload = [
			'chat_id'      => $chat_id,
			'phone_number' => $phone_number,
			'first_name'   => $first_name,
		];

		if ( ! empty( $last_name ) ) {
			$payload['last_name'] = $last_name;
		}

		if ( ! empty( $vcard ) ) {
			$payload['vcard'] = $vcard;
		}

		$body = self::telegram_request( $token, 'sendContact', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_create_invite_link( array $node, array $input, string $token ): array {
		$chat_id              = $node['data']['config']['chat_id'] ?? '';
		$name                 = $node['data']['config']['name'] ?? '';
		$expire_date          = $node['data']['config']['expire_date'] ?? '';
		$member_limit         = $node['data']['config']['member_limit'] ?? '';
		$creates_join_request = $node['data']['config']['creates_join_request'] ?? 'false';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		$payload = [
			'chat_id' => $chat_id,
		];

		if ( ! empty( $name ) ) {
			$payload['name'] = $name;
		}

		if ( '' !== $expire_date ) {
			$payload['expire_date'] = (int) $expire_date;
		}

		if ( 'true' === $creates_join_request ) {
			$payload['creates_join_request'] = true;
		} elseif ( '' !== $member_limit ) {
			$payload['member_limit'] = (int) $member_limit;
		}

		$body   = self::telegram_request( $token, 'createChatInviteLink', $payload );
		$result = $body['result'] ?? [];

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_invite_link'      => $result['invite_link'] ?? '',
					'telegram_invite_name'      => $result['name'] ?? '',
					'telegram_invite_expire_at' => $result['expire_date'] ?? '',
					'telegram_chat_id'          => $chat_id,
				]
			),
		];
	}

	private static function action_revoke_invite_link( array $node, array $input, string $token ): array {
		$chat_id     = $node['data']['config']['chat_id'] ?? '';
		$invite_link = $node['data']['config']['invite_link'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $invite_link ) ) {
			throw new \Exception( 'Telegram: invite_link is required' );
		}

		$payload = [
			'chat_id'     => $chat_id,
			'invite_link' => $invite_link,
		];

		$body   = self::telegram_request( $token, 'revokeChatInviteLink', $payload );
		$result = $body['result'] ?? [];

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_invite_link' => $result['invite_link'] ?? $invite_link,
					'telegram_revoked'     => ! empty( $result['is_revoked'] ),
					'telegram_chat_id'     => $chat_id,
				]
			),
		];
	}

	private static function action_ban_user( array $node, array $input, string $token ): array {
		$chat_id         = $node['data']['config']['chat_id'] ?? '';
		$user_id         = $node['data']['config']['user_id'] ?? '';
		$until_date      = $node['data']['config']['until_date'] ?? '';
		$revoke_messages = $node['data']['config']['revoke_messages'] ?? 'false';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $user_id ) ) {
			throw new \Exception( 'Telegram: user_id is required' );
		}

		$payload = [
			'chat_id' => $chat_id,
			'user_id' => (int) $user_id,
		];

		if ( '' !== $until_date ) {
			$payload['until_date'] = (int) $until_date;
		}

		if ( 'true' === $revoke_messages ) {
			$payload['revoke_messages'] = true;
		}

		self::telegram_request( $token, 'banChatMember', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_banned'  => true,
					'telegram_user_id' => $user_id,
					'telegram_chat_id' => $chat_id,
				]
			),
		];
	}

	private static function action_unban_user( array $node, array $input, string $token ): array {
		$chat_id        = $node['data']['config']['chat_id'] ?? '';
		$user_id        = $node['data']['config']['user_id'] ?? '';
		$only_if_banned = $node['data']['config']['only_if_banned'] ?? 'true';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $user_id ) ) {
			throw new \Exception( 'Telegram: user_id is required' );
		}

		$payload = [
			'chat_id'        => $chat_id,
			'user_id'        => (int) $user_id,
			'only_if_banned' => ( 'false' !== $only_if_banned ),
		];

		self::telegram_request( $token, 'unbanChatMember', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_unbanned' => true,
					'telegram_user_id'  => $user_id,
					'telegram_chat_id'  => $chat_id,
				]
			),
		];
	}
}
