<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One way a conversation reaches a customer.
 *
 * Inbound is not part of the contract: each channel turns its own webhook or
 * request into a call to Ingest::inbound(). Outbound is, so the inbox, the AI
 * and workflows all reply the same way whatever the channel.
 */
interface ChannelInterface {

	public static function slug(): string;

	public static function label(): string;

	/**
	 * Whether a reply can go out right now, and why not when it cannot — a
	 * channel with a messaging window, for example, refuses once it has passed.
	 *
	 * @return array{ok:bool,reason?:string}
	 */
	public static function can_send( Conversation $conversation ): array;

	/**
	 * Deliver a stored outbound message.
	 *
	 * @return array{status:string,external_id?:string,error?:string}
	 *         status is `sent` or `failed`.
	 */
	public static function send( Conversation $conversation, ?Identity $identity, Message $message ): array;
}
