<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeFolder extends Model {

	protected static string $table = 'recipe_folders';

	protected static array $fillable = [
		'title',
		'parent_id',
		'created_by',
	];

	protected static array $casts = [
		'id'         => 'integer',
		'parent_id'  => 'integer',
		'created_by' => 'integer',
	];

	// -------------------------------------------------------------------------
	// Relationships
	// -------------------------------------------------------------------------

	public function children(): Collection {
		return static::where( 'parent_id', $this->id )->orderBy( 'title', 'asc' )->get();
	}

	public function parent(): ?self {
		if ( ! $this->parent_id ) {
			return null;
		}
		return static::find( $this->parent_id );
	}

	public function recipes(): Collection {
		return Recipe::where( 'folder_id', $this->id )->orderBy( 'title', 'asc' )->get();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public function hasChildren(): bool {
		return static::where( 'parent_id', $this->id )->count() > 0;
	}

	public function hasRecipes(): bool {
		return Recipe::where( 'folder_id', $this->id )->count() > 0;
	}

	/**
	 * A folder can only be deleted when it is completely empty
	 * (no recipes and no sub-folders).
	 */
	public function isDeletable(): bool {
		return ! $this->hasChildren() && ! $this->hasRecipes();
	}

	// -------------------------------------------------------------------------
	// Tree builder
	// -------------------------------------------------------------------------

	/**
	 * Returns all root folders as a nested tree.
	 * Each node has a 'children' key with its sub-folders (recursive).
	 */
	public static function tree(): array {
		// fresh() bypasses the in-process query cache so we always see the
		// current database state (important in long-lived FPM workers).
		$all = static::orderBy( 'title', 'asc' )->fresh()->get()->toArray();
		return self::build_tree( $all, null );
	}

	private static function build_tree( array $all, ?int $parentId ): array {
		$branch = [];
		foreach ( $all as $item ) {
			// Normalize parent_id: treat null, "", "0", 0 all as "no parent".
			$raw        = $item['parent_id'] ?? null;
			$itemParent = ( $raw !== null && $raw !== '' && (int) $raw !== 0 )
				? (int) $raw
				: null;

			if ( $itemParent === $parentId ) {
				$item['children'] = self::build_tree( $all, (int) $item['id'] );
				$branch[]         = $item;
			}
		}
		return $branch;
	}
}
