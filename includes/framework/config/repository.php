<?php

namespace Zaplane\Framework\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Repository {

	protected Config $config;
	protected string $namespace;

	public function __construct( Config $config, string $namespace ) {
		$this->config = $config;
		$this->namespace = rtrim( $namespace, '.' ) . '.';
	}



	public function get( string $key, $default = null ) {
		return $this->config->get( $this->namespace . $key, $default );
	}



	public function set( string $key, $value ): self {
		$this->config->set( $this->namespace . $key, $value );
		return $this;
	}



	public function has( string $key ): bool {
		return $this->config->has( $this->namespace . $key );
	}



	public function forget( string $key ): self {
		$this->config->forget( $this->namespace . $key );
		return $this;
	}



	public function all(): array {
		return $this->config->get( rtrim( $this->namespace, '.' ), [] );
	}



	public function merge( array $config ): self {
		$existing = $this->all();
		$merged = array_replace_recursive( $existing, $config );
		$this->config->set( rtrim( $this->namespace, '.' ), $merged );
		return $this;
	}



	public function getNamespace(): string {
		return rtrim( $this->namespace, '.' );
	}



	public function child( string $key ): self {
		return new self( $this->config, $this->namespace . $key );
	}
}
