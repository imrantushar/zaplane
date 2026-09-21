<?php

namespace Zaplane\Modules\Inbox\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Conversation extends Model {

	protected static string $table = 'inbox_conversations';

	protected static array $fillable = [ 'contact_id', 'identity_id', 'channel', 'account_id', 'status', 'handler', 'ai_enabled', 'assignee_id', 'unread_count', 'last_message_preview', 'last_message_at', 'last_customer_at', 'closed_at', 'meta' ];

	protected static array $casts = [ 'id' => 'integer', 'contact_id' => 'integer', 'identity_id' => 'integer', 'ai_enabled' => 'boolean', 'assignee_id' => 'integer', 'unread_count' => 'integer', 'meta' => 'json' ];
}
