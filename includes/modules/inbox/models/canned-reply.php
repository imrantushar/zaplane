<?php

namespace Zaplane\Modules\Inbox\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CannedReply extends Model {

	protected static string $table = 'inbox_canned_replies';

	protected static array $fillable = [ 'title', 'shortcut', 'body' ];

	protected static array $casts = [ 'id' => 'integer' ];
}
