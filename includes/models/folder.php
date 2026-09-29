<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Folder extends Model {

	protected static string $table = 'folders';

	protected static array $fillable = [
		'title',
		'created_by',
		'recipe_id',
		'setup',
	];

	protected static array $casts = [
		'id'         => 'integer',
		'created_by' => 'integer',
		'recipe_id'  => 'integer',
		'setup'      => 'json',
	];

	// -------------------------------------------------------------------------
	// Relationships
	// -------------------------------------------------------------------------

	public function workflows(): Collection {
		return Workflow::where( 'folder_id', $this->id )->orderBy( 'id', 'desc' )->get();
	}

	/**
	 * The group recipe this folder was set up from, if it was and the recipe still exists.
	 */
	public function recipe(): ?Recipe {
		return $this->recipe_id ? Recipe::find( (int) $this->recipe_id ) : null;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public function hasWorkflows(): bool {
		return Workflow::where( 'folder_id', $this->id )->count() > 0;
	}

	/**
	 * A folder can only be deleted when it has no workflows assigned.
	 */
	public function isDeletable(): bool {
		return ! $this->hasWorkflows();
	}

	// -------------------------------------------------------------------------
	// Serialization
	// -------------------------------------------------------------------------

	public function toResponse(): array {
		$source = null;

		if ( $this->recipe_id ) {
			$recipe = $this->recipe();
			$setup  = is_array( $this->setup ) ? $this->setup : [];

			$source = [
				'id'    => (int) $this->recipe_id,
				'title' => $recipe ? (string) $recipe->title : (string) ( $setup['recipe_title'] ?? '' ),
			];
		}

		return [
			'id'             => $this->id,
			'title'          => $this->title,
			'workflow_count' => Workflow::where( 'folder_id', $this->id )->count(),
			'recipe'         => $source,
			'created_by'     => $this->created_by,
			'created_at'     => $this->created_at,
			'updated_at'     => $this->updated_at,
		];
	}
}
