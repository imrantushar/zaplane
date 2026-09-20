<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Conversation extends Model {

	protected static string $table = 'conversations';

	// No updated_at column — created_at is set explicitly on append.
	protected static bool $timestamps = false;

	protected static array $fillable = [
		'conversation_key',
		'channel',
		'role',
		'content',
		'created_at',
	];

	protected static array $casts = [
		'id'         => 'integer',
		'created_at' => 'string',
	];
}
