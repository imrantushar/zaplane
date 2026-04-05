<?php
namespace Zaplane\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ActionResponseTrait {



	protected static function respond( array $data = [], string $port = 'main' ): array {
		return [
			'port' => $port,
			'data' => $data,
			'meta' => [
				'timestamp' => time(),
				'node' => static::class,
			],
		];
	}



	protected static function success( array $data = [] ): array {
		return static::respond( $data, 'main' );
	}



	protected static function error( string $message, array $data = [] ): array {
		return static::respond(
			array_merge( [ 'error' => $message ], $data ),
			'error'
		);
	}



	protected static function port( string $port, array $data = [] ): array {
		return static::respond( $data, $port );
	}
}
