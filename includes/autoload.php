<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoload {




	private static ?self $instance = null;



	private array $autoload_directories = [];



	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}



	public function add_namespace_directory( string $namespace, string $directory ): void {
		$ns = rtrim( $namespace, '\\' );
		$dir = rtrim( $directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		if ( ! isset( $this->autoload_directories[ $ns ] ) ) {
			$this->autoload_directories[ $ns ] = [];
		}

		$this->autoload_directories[ $ns ][] = $dir;
	}



	public function autoload( string $class ): void {
		foreach ( $this->autoload_directories as $namespace => $directories ) {
			if ( 0 !== strpos( $class, $namespace ) ) {
				continue;
			}

			$relative_class = substr( $class, strlen( $namespace ) + 1 );

			$relative_path = strtolower(
				preg_replace(
					[ '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
					[ '$1-$2', '-', DIRECTORY_SEPARATOR ],
					$relative_class
				)
			) . '.php';

			foreach ( $directories as $directory ) {
				$file = $directory . $relative_path;
				if ( is_readable( $file ) ) {
					require_once $file;
					return;
				}
			}
		}//end foreach
	}



	private function __construct() {
		spl_autoload_register( [ $this, 'autoload' ] );

		$this->add_namespace_directory( 'Zaplane', ZAPLANE_ROOT_DIR_PATH . 'includes/' );
		$this->add_namespace_directory( 'Zaplane\\Integrations', ZAPLANE_ROOT_DIR_PATH . 'integrations/' );
	}
}

Autoload::get_instance();
