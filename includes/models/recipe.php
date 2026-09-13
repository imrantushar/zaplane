<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recipe extends Model {

	/** A recipe that creates one workflow. Its blueprint is a single workflow's. */
	const TYPE_WORKFLOW = 'workflow';

	/** A recipe that sets up several workflows in a folder. See RecipeGroupBuilder. */
	const TYPE_GROUP = 'group';

	protected static string $table = 'recipes';

	protected static array $fillable = [
		'type',
		'slug',
		'title',
		'description',
		'thumbnail_id',
		'blueprint',
		'integration_icons',
		'created_by',
	];

	protected static array $casts = [
		'id'                => 'integer',
		'thumbnail_id'      => 'integer',
		'created_by'        => 'integer',
		'integration_icons' => 'json',
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

	public function isGroup(): bool {
		return self::TYPE_GROUP === $this->type;
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
		$response = [
			'id'                => $this->id,
			'type'              => $this->isGroup() ? self::TYPE_GROUP : self::TYPE_WORKFLOW,
			'slug'              => $this->slug,
			'title'             => $this->title,
			'description'       => $this->description,
			'thumbnail_id'      => $this->thumbnail_id,
			'thumbnail_url'     => $this->thumbnailUrl(),
			'integration_icons' => $this->integration_icons ?? [],
			'created_by'        => $this->created_by,
			'created_at'        => $this->created_at,
			'updated_at'        => $this->updated_at,
		];

		if ( $this->isGroup() ) {
			$response['workflows'] = [];

			foreach ( (array) ( $this->getBlueprint()['workflows'] ?? [] ) as $workflow ) {
				if ( is_array( $workflow ) && ! empty( $workflow['key'] ) ) {
					$response['workflows'][] = [
						'key'   => (string) $workflow['key'],
						'title' => (string) ( $workflow['title'] ?? $workflow['key'] ),
					];
				}
			}
		}

		return $response;
	}
}
