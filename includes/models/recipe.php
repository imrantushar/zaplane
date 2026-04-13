<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recipe extends Model {

	protected static string $table = 'recipes';

	protected static array $fillable = [
		'title',
		'description',
		'thumbnail_id',
		'blueprint',
		'created_by',
	];

	protected static array $casts = [
		'id'           => 'integer',
		'thumbnail_id' => 'integer',
		'created_by'   => 'integer',
	];

	// -------------------------------------------------------------------------
	// Blueprint helpers
	// -------------------------------------------------------------------------

	public function getBlueprint(): array {
		if ( empty( $this->blueprint ) ) {
			return [];
		}
		$decoded = json_decode( $this->blueprint, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	// -------------------------------------------------------------------------
	// Thumbnail helpers
	// -------------------------------------------------------------------------

	public function thumbnailUrl(): ?string {
		if ( ! $this->thumbnail_id ) {
			return null;
		}
		$url = wp_get_attachment_url( (int) $this->thumbnail_id );
		return $url ?: null;
	}

	// -------------------------------------------------------------------------
	// Serialization helper (used by controllers)
	// -------------------------------------------------------------------------

	public function toResponse(): array {
		return [
			'id'            => $this->id,
			'title'         => $this->title,
			'description'   => $this->description,
			'thumbnail_id'  => $this->thumbnail_id,
			'thumbnail_url' => $this->thumbnailUrl(),
			'created_by'    => $this->created_by,
			'created_at'    => $this->created_at,
			'updated_at'    => $this->updated_at,
		];
	}
}
