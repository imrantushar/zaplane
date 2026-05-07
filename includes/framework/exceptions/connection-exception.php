<?php

namespace Zaplane\Framework\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class ConnectionException extends ZaplaneException {

	protected string $errorCode = 'connection_error';
	protected ?int $connectionId = null;
	protected ?string $app = null;

	public function __construct(
		string $message,
		?int $connectionId = null,
		?string $app = null,
		array $context = []
	) {
		$this->connectionId = $connectionId;
		$this->app = $app;

		if ( $connectionId ) {
			$context['connection_id'] = $connectionId;
		}
		if ( $app ) {
			$context['app'] = $app;
		}

		parent::__construct( $message, $context );
	}

	public static function notFound( int $connectionId ): self {
		return new self(
			"Connection not found: {$connectionId}",
			$connectionId
		);
	}

	public static function notOwned( int $connectionId, int $userId ): self {
		return new self(
			'You do not have permission to access this connection',
			$connectionId,
			null,
			[ 'user_id' => $userId ]
		);
	}

	public static function createFailed( string $app, string $reason = '' ): self {
		$message = "Failed to create connection for {$app}";
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, null, $app );
	}

	public static function updateFailed( int $connectionId, string $reason = '' ): self {
		$message = 'Failed to update connection';
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, $connectionId );
	}

	public static function deleteFailed( int $connectionId, string $reason = '' ): self {
		$message = 'Failed to delete connection';
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, $connectionId );
	}

	public static function testFailed( int $connectionId, string $reason ): self {
		return new self(
			"Connection test failed: {$reason}",
			$connectionId
		);
	}

	public static function invalidCredentials( string $app, string $reason = '' ): self {
		$message = 'Invalid credentials';
		if ( $reason ) {
			$message .= ": {$reason}";
		}
		return new self( $message, null, $app );
	}

	public static function expired( int $connectionId, string $app ): self {
		return new self(
			'Connection credentials have expired',
			$connectionId,
			$app
		);
	}

	public static function refreshRequired( int $connectionId, string $app ): self {
		return new self(
			'Connection requires re-authentication',
			$connectionId,
			$app
		);
	}

	public function getConnectionId(): ?int {
		return $this->connectionId;
	}

	public function getApp(): ?string {
		return $this->app;
	}

	public function getHttpStatusCode(): int {
		if ( strpos( $this->getMessage(), 'not found' ) !== false ) {
			return 404;
		}
		if ( strpos( $this->getMessage(), 'permission' ) !== false ) {
			return 403;
		}
		if ( strpos( $this->getMessage(), 'Invalid credentials' ) !== false ) {
			return 401;
		}
		return 500;
	}
}
