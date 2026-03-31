<?php
namespace Zaplane\Framework\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IntegrationLoader {


	protected static array $registry = [];
	protected static array $instances = [];
	protected static bool $initialized = false;
	protected static array $legacySlugs = [
		'activehosted' => 'activecampaign',
	];



	protected static function ensureInitialized(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$registry = zaplane_config( 'integrations.registry', [] );
		self::$registry = apply_filters( 'zaplane_integrations', self::$registry );
		self::$initialized = true;
	}



	public static function get( string $slug ): ?object {
		self::ensureInitialized();
		$slug = self::normalizeSlug( $slug );

		if ( ! empty( self::$instances[ $slug ] ) ) {
			return self::$instances[ $slug ];
		}

		if ( empty( self::$registry[ $slug ] ) ) {
			return null;
		}

		$meta  = self::$registry[ $slug ];
		$file  = ( ! empty( $meta['path'] ) )
			? $meta['path']
			: ZAPLANE_INTEGRATION_DIR_PATH . '/' . basename( $meta['file'] );
		$class = $meta['class'];

		if ( ! class_exists( $class ) && file_exists( $file ) ) {
			require_once $file;
		}

		if ( ! class_exists( $class ) ) {
			return null;
		}

		$instance = new $class();
		self::$instances[ $slug ] = $instance;

		return $instance;
	}



	public static function getAllSlugs(): array {
		self::ensureInitialized();
		return array_keys( self::$registry );
	}



	public static function getRegistry(): array {
		self::ensureInitialized();
		return self::$registry;
	}



	public static function all(): array {
		self::ensureInitialized();

		$all = [];
		foreach ( self::$registry as $slug => $meta ) {
			$instance = self::get( $slug );
			if ( $instance ) {
				$all[ $slug ] = $instance;
			}
		}
		return $all;
	}



	public static function has( string $slug ): bool {
		self::ensureInitialized();
		$slug = self::normalizeSlug( $slug );
		return isset( self::$registry[ $slug ] );
	}

	protected static function normalizeSlug( string $slug ): string {
		$slug = strtolower( trim( $slug ) );
		return self::$legacySlugs[ $slug ] ?? $slug;
	}



	public static function init(): self {
		self::ensureInitialized();
		return new self();
	}
}
