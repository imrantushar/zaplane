<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailTemplate extends Model {

	protected static string $table = 'email_templates';

	protected static array $fillable = [
		'title',
		'subject',
		'pre_header',
		'content',
		'created_by',
	];

	protected static array $casts = [
		'id'         => 'integer',
		'created_by' => 'integer',
	];

	/**
	 * The builder's JSON tree, decoded. Empty array when unset/corrupt.
	 */
	public function getTree(): array {
		if ( empty( $this->content ) ) {
			return [];
		}
		$decoded = json_decode( $this->content, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	public function toResponse(): array {
		return [
			'id'         => $this->id,
			'title'      => $this->title,
			'subject'    => $this->subject,
			'pre_header' => $this->pre_header,
			'content'    => $this->content,
			'created_by' => $this->created_by,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
