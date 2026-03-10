<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {


	const ENABLED = true;

	private static int $step = 0;
	private static array $logs = [];



	public static function log( string $message, array $context = [] ): void {
		if ( ! ZAPLANE_ALLOW_LOGS ) {
			return;
		}

		self::$step++;
		$entry = [
			'step' => self::$step,
			'message' => $message,
			'context' => $context,
			'time' => current_time( 'mysql' )
		];

		self::$logs[] = $entry;

		error_log( '[Zaplane Step ' . self::$step . '] ' . $message . ' ' . json_encode( $context ) );
	}



	public static function get_logs(): array {
		return self::$logs;
	}



	public static function reset(): void {
		self::$step = 0;
		self::$logs = [];
	}
}
