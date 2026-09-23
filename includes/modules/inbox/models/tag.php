<?php

namespace Zaplane\Modules\Inbox\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tag extends Model {

	protected static string $table = 'inbox_tags';

	protected static bool $timestamps = false;

	protected static array $fillable = [ 'conversation_id', 'tag' ];

	protected static array $casts = [ 'id' => 'integer', 'conversation_id' => 'integer' ];
}
