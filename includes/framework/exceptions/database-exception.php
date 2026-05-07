<?php

namespace Zaplane\Framework\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DatabaseException extends ZaplaneException {

	protected string $errorCode = 'database_error';
	protected ?string $table = null;
	protected ?string $operation = null;

	public function __construct(
		string $message,
		?string $table = null,
		?string $operation = null,
		array $context = []
	) {
		$this->table = $table;
		$this->operation = $operation;

		if ( $table ) {
			$context['table'] = $table;
		}
		if ( $operation ) {
			$context['operation'] = $operation;
		}

		parent::__construct( $message, $context );
	}

	public static function insertFailed( string $table, string $reason = '' ): self {
		$message = "Failed to insert into {$table}";
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, $table, 'insert' );
	}

	public static function updateFailed( string $table, string $reason = '' ): self {
		$message = "Failed to update {$table}";
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, $table, 'update' );
	}

	public static function deleteFailed( string $table, string $reason = '' ): self {
		$message = "Failed to delete from {$table}";
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, $table, 'delete' );
	}

	public static function queryFailed( string $reason = '' ): self {
		$message = 'Database query failed';
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, null, 'query' );
	}

	public static function recordNotFound( string $table, $id ): self {
		return new self(
			"Record not found in {$table} with ID: {$id}",
			$table,
			'select',
			[ 'id' => $id ]
		);
	}

	public static function migrationFailed( string $migration, string $reason ): self {
		return new self(
			"Migration failed: {$migration} - {$reason}",
			null,
			'migration',
			[ 'migration' => $migration ]
		);
	}

	public static function connectionFailed( string $reason = '' ): self {
		$message = 'Database connection failed';
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, null, 'connect' );
	}

	public function getTable(): ?string {
		return $this->table;
	}

	public function getOperation(): ?string {
		return $this->operation;
	}

	public function getHttpStatusCode(): int {
		if ( 'select' === $this->operation && false !== strpos( $this->getMessage(), 'not found' ) ) {
			return 404;
		}
		return 500;
	}
}
