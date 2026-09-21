<?php

namespace Zaplane\Modules\Inbox\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Contact extends Model {

	protected static string $table = 'inbox_contacts';

	protected static array $fillable = [ 'name', 'email', 'phone', 'avatar_url', 'wp_user_id', 'meta' ];

	protected static array $casts = [ 'id' => 'integer', 'wp_user_id' => 'integer', 'meta' => 'json' ];
}
