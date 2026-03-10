<?php

use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Config\Repository;
use Zaplane\Framework\Logging\Logger;
use Zaplane\Framework\Logging\LogManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'zaplane_config' ) ) {


	function zaplane_config( ?string $key = null, $default = null ) {
		$config = Config::getInstance();

		if ( $key === null ) {
			return $config;
		}

		return $config->get( $key, $default );
	}
}

if ( ! function_exists( 'zaplane_config_set' ) ) {


	function zaplane_config_set( string $key, $value ): Config {
		return Config::getInstance()->set( $key, $value );
	}
}

if ( ! function_exists( 'zaplane_config_repository' ) ) {


	function zaplane_config_repository( string $namespace ): Repository {
		return new Repository( Config::getInstance(), $namespace );
	}
}

if ( ! function_exists( 'zaplane_logger' ) ) {


	function zaplane_logger( ?string $message = null, array $context = [], string $level = 'info' ): ?Logger {
		$logger = Logger::getInstance();

		if ( $message !== null ) {
			$logger->log( $level, $message, $context );
			return null;
		}

		return $logger;
	}
}

if ( ! function_exists( 'zaplane_log' ) ) {


	function zaplane_log( string $level, string $message, array $context = [] ): void {
		Logger::getInstance()->log( $level, $message, $context );
	}
}

if ( ! function_exists( 'zaplane_log_debug' ) ) {


	function zaplane_log_debug( string $message, array $context = [] ): void {
		Logger::getInstance()->debug( $message, $context );
	}
}

if ( ! function_exists( 'zaplane_log_info' ) ) {


	function zaplane_log_info( string $message, array $context = [] ): void {
		Logger::getInstance()->info( $message, $context );
	}
}

if ( ! function_exists( 'zaplane_log_warning' ) ) {


	function zaplane_log_warning( string $message, array $context = [] ): void {
		Logger::getInstance()->warning( $message, $context );
	}
}

if ( ! function_exists( 'zaplane_log_error' ) ) {


	function zaplane_log_error( string $message, array $context = [] ): void {
		Logger::getInstance()->error( $message, $context );
	}
}

if ( ! function_exists( 'zaplane_log_exception' ) ) {


	function zaplane_log_exception( \Throwable $exception, array $context = [] ): void {
		Logger::getInstance()->exception( $exception, 'error', $context );
	}
}

if ( ! function_exists( 'zaplane_log_channel' ) ) {


	function zaplane_log_channel( string $channel ): Logger {
		return LogManager::getInstance()->channel( $channel );
	}
}

if ( ! function_exists( 'zaplane_env' ) ) {


	function zaplane_env(): string {
		return Config::getInstance()->getEnvironment();
	}
}

if ( ! function_exists( 'zaplane_is_debug' ) ) {


	function zaplane_is_debug(): bool {
		return Config::getInstance()->get( 'app.debug', false );
	}
}
