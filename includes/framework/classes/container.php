<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Exceptions\ZaplaneException;

class Container {


	protected array $services = [];
	protected array $instances = [];

	public function set( string $name, callable $factory ): void {
		$this->services[ $name ] = $factory;
	}

	public function get( string $name ) {
		if ( isset( $this->instances[ $name ] ) ) {
			return $this->instances[ $name ];
		}

		if ( ! isset( $this->services[ $name ] ) ) {
			throw new ZaplaneException( "Service {$name} not registered.", [ 'service' => $name ] );
		}

		$this->instances[ $name ] = ( $this->services[ $name ] )( $this );

		return $this->instances[ $name ];
	}

	public function has( string $name ): bool {
		return isset( $this->services[ $name ] );
	}
}
