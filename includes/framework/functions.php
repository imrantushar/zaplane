<?php

use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Config\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'zaplane_config' ) ) {


	function zaplane_config( ?string $key = null, $default = null ) {
		$config = Config::getInstance();

		if ( null === $key ) {
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
