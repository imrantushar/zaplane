<?php

namespace Zaplane\Framework\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LogLevel {

	public const EMERGENCY = 'emergency';
	public const ALERT = 'alert';
	public const CRITICAL = 'critical';
	public const ERROR = 'error';
	public const WARNING = 'warning';
	public const NOTICE = 'notice';
	public const INFO = 'info';
	public const DEBUG = 'debug';



	public const LEVELS = [
		self::DEBUG => 0,
		self::INFO => 1,
		self::NOTICE => 2,
		self::WARNING => 3,
		self::ERROR => 4,
		self::CRITICAL => 5,
		self::ALERT => 6,
		self::EMERGENCY => 7,
	];



	public static function all(): array {
		return array_keys( self::LEVELS );
	}



	public static function isValid( string $level ): bool {
		return isset( self::LEVELS[ $level ] );
	}



	public static function priority( string $level ): int {
		return self::LEVELS[ $level ] ?? 0;
	}



	public static function meetsThreshold( string $level, string $threshold ): bool {
		return self::priority( $level ) >= self::priority( $threshold );
	}
}
