<?php

namespace Zaplane\Models;

use Zaplane\Framework\Classes\Encryption;
use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Connection extends Model {

	protected static string $table = 'connections';

	protected static array $fillable = [
		'user_id',
		'app',
		'icon',
		'name',
		'auth_type',
		'encrypted_credentials',
		'status',
		'oauth_expires_at',
		'last_used_at',
		'last_tested_at',
		'last_test_status',
	];

	protected static array $hidden = [
		'encrypted_credentials',
	];

	protected static array $casts = [
		'id' => 'integer',
		'user_id' => 'integer',
	];

	public function getCredentials(): array {
		if ( empty( $this->encrypted_credentials ) ) {
			return [];
		}

		try {
			return Encryption::decrypt( $this->encrypted_credentials );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	public function setCredentials( array $credentials ): bool {
		$this->encrypted_credentials = Encryption::encrypt( $credentials );
		return $this->save();
	}

	public function markAsUsed(): bool {
		$this->last_used_at = current_time( 'mysql' );
		return $this->save();
	}

	public function markAsTested( bool $success ): bool {
		$this->last_tested_at = current_time( 'mysql' );
		$this->last_test_status = $success ? 'success' : 'failed';
		return $this->save();
	}

	public function setOAuthExpiry( int $expiresIn ): bool {
		$this->oauth_expires_at = gmdate( 'Y-m-d H:i:s', time() + $expiresIn );
		return $this->save();
	}

	public function isOAuthExpired(): bool {
		if ( ! $this->oauth_expires_at ) {
			return false;
		}
		return strtotime( $this->oauth_expires_at ) < time();
	}

	public function isOAuthExpiringSoon( int $minutes = 5 ): bool {
		if ( ! $this->oauth_expires_at ) {
			return false;
		}
		return strtotime( $this->oauth_expires_at ) < ( time() + ( $minutes * 60 ) );
	}

	public function isActive(): bool {
		return 'active' === $this->status;
	}

	public function activate(): bool {
		$this->status = 'active';
		return $this->save();
	}

	public function deactivate(): bool {
		$this->status = 'inactive';
		return $this->save();
	}

	public function isOwnedBy( int $userId ): bool {
		return $this->user_id === $userId;
	}

	public static function forUser( int $userId, ?string $app = null ): Collection {
		$query = static::where( 'user_id', $userId );

		if ( null !== $app ) {
			$query->where( 'app', $app );
		}

		return $query->orderBy( 'name', 'asc' )->get();
	}

	public static function forApp( string $app ): Collection {
		return static::where( 'app', $app )->get();
	}

	public static function active(): Collection {
		return static::where( 'status', 'active' )->get();
	}
}
