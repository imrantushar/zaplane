<?php

namespace Zaplane\Framework\Config;

use ArrayAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config implements ArrayAccess {

	private static ?self $instance = null;



	protected array $items = [];



	protected array $loadedFiles = [];



	protected string $optionPrefix = 'zaplane_config_';



	public static function getInstance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}



	public static function resetInstance(): void {
		self::$instance = null;
	}

	private function __construct() {
		$this->loadDefaults();
		$this->loadConfigFiles();
	}



	protected function loadConfigFiles(): void {
		if ( ! defined( 'ZAPLANE_INCLUDES_DIR_PATH' ) ) {
			return;
		}

		$configPath = ZAPLANE_INCLUDES_DIR_PATH . 'config';
		$this->loadDirectory( $configPath );
	}



	protected function loadDefaults(): void {
		$this->items = [
			'app' => [
				'name' => 'Zaplane',
				'version' => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : '1.0.0',
				'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'timezone' => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			],
			'database' => [
				'prefix' => 'zaplane_',
				'charset' => defined( 'DB_CHARSET' ) ? DB_CHARSET : 'utf8mb4',
				'collate' => defined( 'DB_COLLATE' ) ? DB_COLLATE : 'utf8mb4_unicode_ci',
			],
			'cache' => [
				'enabled' => true,
				'driver' => 'transient',
				'prefix' => 'zaplane_cache_',
				'ttl' => 3600,
			],

			'integrations' => [
				'auto_discover' => true,
				'cache_enabled' => true,
			],
			'api' => [
				'namespace' => 'zaplane/v1',
				'rate_limit' => 100,
				'rate_limit_window' => 60,
			],
		];
	}



	public function loadFile( string $path, ?string $namespace = null ): self {
		if ( ! file_exists( $path ) ) {
			return $this;
		}

		if ( in_array( $path, $this->loadedFiles, true ) ) {
			return $this;
		}

		try {
			$config = require $path;
		} catch ( \Throwable $e ) {
			return $this;
		}

		if ( ! is_array( $config ) ) {
			return $this;
		}

		$this->loadedFiles[] = $path;

		if ( $namespace ) {
			$this->set($namespace, array_merge(
				$this->get( $namespace, [] ),
				$config
			));
		} else {
			$this->items = array_replace_recursive( $this->items, $config );
		}

		return $this;
	}



	public function loadDirectory( string $directory ): self {
		if ( ! is_dir( $directory ) ) {
			return $this;
		}

		$files = glob( $directory . '/*.php' );

		foreach ( $files as $file ) {
			$namespace = pathinfo( $file, PATHINFO_FILENAME );
			$this->loadFile( $file, $namespace );
		}

		return $this;
	}



	public function loadFromOptions( string $optionName ): self {
		$value = get_option( $optionName );

		if ( false === $value ) {
			return $this;
		}

		$config = is_string( $value ) ? json_decode( $value, true ) : $value;

		if ( is_array( $config ) ) {
			$this->items = array_replace_recursive( $this->items, $config );
		}

		return $this;
	}



	public function get( string $key, $default = null ) {
		$keys = explode( '.', $key );
		$value = $this->items;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}



	public function set( string $key, $value ): self {
		$keys = explode( '.', $key );
		$current = &$this->items;

		foreach ( $keys as $i => $segment ) {
			if ( count( $keys ) - 1 === $i ) {
				$current[ $segment ] = $value;
			} else {
				if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
					$current[ $segment ] = [];
				}
				$current = &$current[ $segment ];
			}
		}

		return $this;
	}



	public function has( string $key ): bool {
		$keys = explode( '.', $key );
		$value = $this->items;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return false;
			}
			$value = $value[ $segment ];
		}

		return true;
	}



	public function forget( string $key ): self {
		$keys = explode( '.', $key );
		$current = &$this->items;

		foreach ( $keys as $i => $segment ) {
			if ( count( $keys ) - 1 === $i ) {
				unset( $current[ $segment ] );
				return $this;
			}

			if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
				return $this;
			}

			$current = &$current[ $segment ];
		}

		return $this;
	}



	public function all(): array {
		return $this->items;
	}



	public function merge( array $config ): self {
		$this->items = array_replace_recursive( $this->items, $config );
		return $this;
	}



	public function push( string $key, $value ): self {
		$array = $this->get( $key, [] );

		if ( ! is_array( $array ) ) {
			$array = [];
		}

		$array[] = $value;

		return $this->set( $key, $array );
	}



	public function prepend( string $key, $value ): self {
		$array = $this->get( $key, [] );

		if ( ! is_array( $array ) ) {
			$array = [];
		}

		array_unshift( $array, $value );

		return $this->set( $key, $array );
	}



	public function saveToOption( string $optionName, ?string $key = null ): bool {
		$data = $key ? $this->get( $key ) : $this->items;
		return update_option( $optionName, wp_json_encode( $data ) );
	}



	public function environment( string $env, string $key, $default = null ) {
		$envKey = "{$key}.{$env}";

		if ( $this->has( $envKey ) ) {
			return $this->get( $envKey );
		}

		return $this->get( $key, $default );
	}



	public function getEnvironment(): string {
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			return WP_ENVIRONMENT_TYPE;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return 'development';
		}

		return 'production';
	}



	public function isEnvironment( string $env ): bool {
		return $this->getEnvironment() === $env;
	}


	public function offsetExists( $offset ): bool {
		return $this->has( $offset );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->get( $offset );
	}

	public function offsetSet( $offset, $value ): void {
		$this->set( $offset, $value );
	}

	public function offsetUnset( $offset ): void {
		$this->forget( $offset );
	}
}
