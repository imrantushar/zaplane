<?php

namespace Zaplane\Framework\Database\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ForeignKeyDefinition {

	protected string $column;
	protected ?string $referencesTable = null;
	protected ?string $referencesColumn = null;
	protected string $onDelete = 'CASCADE';
	protected string $onUpdate = 'CASCADE';

	public function __construct( string $column ) {
		$this->column = $column;
	}

	public function references( string $column ): self {
		$this->referencesColumn = $column;
		return $this;
	}

	public function on( string $table ): self {
		$this->referencesTable = $table;
		return $this;
	}

	public function onDelete( string $action ): self {
		$this->onDelete = strtoupper( $action );
		return $this;
	}

	public function onUpdate( string $action ): self {
		$this->onUpdate = strtoupper( $action );
		return $this;
	}

	public function cascadeOnDelete(): self {
		return $this->onDelete( 'CASCADE' );
	}

	public function cascadeOnUpdate(): self {
		return $this->onUpdate( 'CASCADE' );
	}

	public function nullOnDelete(): self {
		return $this->onDelete( 'SET NULL' );
	}

	public function restrictOnDelete(): self {
		return $this->onDelete( 'RESTRICT' );
	}

	public function restrictOnUpdate(): self {
		return $this->onUpdate( 'RESTRICT' );
	}

	public function noActionOnDelete(): self {
		return $this->onDelete( 'NO ACTION' );
	}

	public function getColumn(): string {
		return $this->column;
	}

	public function toSql( string $table ): string {
		if ( ! $this->referencesTable || ! $this->referencesColumn ) {
			return '';
		}

		$constraintName = "fk_{$table}_{$this->column}";

		return "CONSTRAINT {$constraintName} FOREIGN KEY ({$this->column}) " .
			"REFERENCES {$this->referencesTable}({$this->referencesColumn}) " .
			"ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
	}
}
