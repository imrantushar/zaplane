<?php

namespace Zaplane\Modules\Inbox\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Identity extends Model {

	protected static string $table = 'inbox_identities';

	protected static array $fillable = [ 'contact_id', 'channel', 'account_id', 'external_id' ];

	protected static array $casts = [ 'id' => 'integer', 'contact_id' => 'integer' ];
}
