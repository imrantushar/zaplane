<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Workflow extends Model {

	protected static string $table = 'workflows';

	protected static array $fillable = [
		'user_id',
		'folder_id',
		'title',
		'name',
		'status',
		'layout',
	];

	protected static array $casts = [
		'id'        => 'integer',
		'user_id'   => 'integer',
		'folder_id' => 'integer',
	];

	public function versions(): Collection {
		return WorkflowVersion::where( 'workflow_id', $this->id )->orderBy( 'id', 'desc' )->get();
	}

	public function activeVersion(): ?WorkflowVersion {
		return WorkflowVersion::where( 'workflow_id', $this->id )
			->where( 'is_active', 1 )
			->first();
	}

	public function runs() {
		$version = $this->activeVersion();
		if ( ! $version ) {
			return collect( [] );
		}
		return Run::where( 'workflow_version_id', $version->id )
			->orderBy( 'id', 'desc' )
			->get();
	}

	public function activate(): bool {
		$this->status = 'active';
		return $this->save();
	}

	public function pause(): bool {
		$this->status = 'paused';
		return $this->save();
	}

	public function isActive(): bool {
		return 'active' === $this->status;
	}

	public function isPaused(): bool {
		return 'paused' === $this->status;
	}

	public function isDraft(): bool {
		return 'draft' === $this->status;
	}

	public static function forUser( int $userId ): Collection {
		return static::where( 'user_id', $userId )->orderBy( 'id', 'desc' )->get();
	}

	public static function active(): Collection {
		return static::where( 'status', 'active' )->get();
	}
}
