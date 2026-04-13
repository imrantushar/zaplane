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
	];

	protected static array $casts = [
		'id'         => 'integer',
		'created_by' => 'integer',
	];

	// -------------------------------------------------------------------------
	// Relationships
	// -------------------------------------------------------------------------

	public function workflows(): Collection {
		return Workflow::where( 'folder_id', $this->id )->orderBy( 'id', 'desc' )->get();
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
		return [
			'id'             => $this->id,
			'title'          => $this->title,
			'workflow_count' => Workflow::where( 'folder_id', $this->id )->count(),
			'created_by'     => $this->created_by,
			'created_at'     => $this->created_at,
			'updated_at'     => $this->updated_at,
		];
	}
}
