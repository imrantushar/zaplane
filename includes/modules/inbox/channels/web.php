<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The website chat widget. Nothing to deliver: the widget reads its thread
 * from the inbox, so a stored reply is a sent reply.
 */
class Web implements ChannelInterface {

	public static function slug(): string {
		return 'web';
	}

	public static function label(): string {
		return __( 'Website chat', 'zaplane' );
	}

	public static function can_send( Conversation $conversation ): array {
		return [ 'ok' => true ];
	}

	public static function send( Conversation $conversation, ?Identity $identity, Message $message ): array {
		return [ 'status' => 'sent' ];
	}
}
