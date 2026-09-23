<?php

namespace Zaplane\Modules\Inbox\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Message extends Model {

	protected static string $table = 'inbox_messages';

	// No updated_at column: the rare edit or delete is recorded in `meta`
	// (edited_at / deleted_at) by MessageActions.
	protected static bool $timestamps = false;

	protected static array $fillable = [ 'conversation_id', 'direction', 'sender_type', 'sender_id', 'body', 'attachments', 'is_note', 'is_ai_generated', 'channel', 'external_id', 'delivery_status', 'error', 'meta', 'created_at' ];

	protected static array $casts = [ 'id' => 'integer', 'conversation_id' => 'integer', 'sender_id' => 'integer', 'attachments' => 'json', 'is_note' => 'boolean', 'is_ai_generated' => 'boolean', 'meta' => 'json' ];
}
