<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Feedback extends Model {

	protected static string $table = 'feedback';

	protected static array $fillable = [
		'order_id',
		'rating',
		'comment',
		'email',
	];

	protected static array $casts = [
		'id'       => 'integer',
		'order_id' => 'integer',
		'rating'   => 'integer',
	];

	public static function for_order( int $order_id ): ?self {
		return static::where( 'order_id', $order_id )->first();
	}
}
