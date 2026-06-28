<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Knowledge extends Model {

	protected static string $table = 'knowledge';

	// updated_at is set explicitly; there is no created_at column.
	protected static bool $timestamps = false;

	protected static array $fillable = [
		'business_key',
		'title',
		'content',
		'embedding',
		'source',
		'ref_id',
		'updated_at',
	];

	protected static array $casts = [
		'id' => 'integer',
	];
}
