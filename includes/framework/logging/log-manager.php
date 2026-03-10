<?php

namespace Zaplane\Framework\Logging;

use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Logging\Handlers\FileHandler;
use Zaplane\Framework\Logging\Handlers\ErrorLogHandler;
use Zaplane\Framework\Logging\Handlers\DatabaseHandler;
use Zaplane\Framework\Logging\Handlers\MemoryHandler;
use Zaplane\Framework\Logging\Handlers\HandlerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LogManager {

	private static ?self $instance = null;

	protected Config $config;
	protected array $loggers = [];
	protected array $customDrivers = [];



	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}



	public static function resetInstance(): void {
		self::$instance = null;
	}

	private function __construct() {
		$this->config = Config::getInstance();
	}



	public function logger(): Logger {
		return $this->channel();
	}



	public function channel( ?string $channel = null ): Logger {
		$channel = $channel ?? $this->config->get( 'logging.channel', 'file' );

		if ( ! isset( $this->loggers[ $channel ] ) ) {
			$this->loggers[ $channel ] = $this->createLogger( $channel );
		}

		return $this->loggers[ $channel ];
	}



	protected function createLogger( string $channel ): Logger {
		$logger = Logger::getInstance()->channel( $channel );

		$handler = $this->createHandler( $channel );
		if ( $handler ) {
			$logger->pushHandler( $handler );
		}

		return $logger;
	}



	protected function createHandler( string $channel ): ?HandlerInterface {
		$channelConfig = $this->config->get( "logging.channels.{$channel}", [] );
		$driver = $channelConfig['driver'] ?? $channel;

		if ( isset( $this->customDrivers[ $driver ] ) ) {
			return ( $this->customDrivers[ $driver ] )( $channelConfig );
		}

		return match ($driver) {
			'file' => $this->createFileHandler( $channelConfig ),
			'errorlog' => $this->createErrorLogHandler( $channelConfig ),
			'database' => $this->createDatabaseHandler( $channelConfig ),
			'memory' => $this->createMemoryHandler( $channelConfig ),
			'null' => null,
			default => $this->createFileHandler( $channelConfig ),
		};
	}



	protected function createFileHandler( array $config ): FileHandler {
		$path = $config['path'] ?? WP_CONTENT_DIR . '/zaplane-logs';
		$level = $config['level'] ?? $this->config->get( 'logging.level', LogLevel::DEBUG );
		$days = $config['days'] ?? 14;

		return new FileHandler( $path, $level, true, $days );
	}



	protected function createErrorLogHandler( array $config ): ErrorLogHandler {
		$level = $config['level'] ?? $this->config->get( 'logging.level', LogLevel::DEBUG );
		$prefix = $config['prefix'] ?? '[Zaplane]';

		return new ErrorLogHandler( $level, $prefix );
	}



	protected function createDatabaseHandler( array $config ): DatabaseHandler {
		$level = $config['level'] ?? $this->config->get( 'logging.level', LogLevel::DEBUG );
		$table = $config['table'] ?? 'zaplane_logs';
		$days = $config['days'] ?? 30;

		return new DatabaseHandler( $level, $table, $days );
	}



	protected function createMemoryHandler( array $config ): MemoryHandler {
		$level = $config['level'] ?? LogLevel::DEBUG;
		$maxEntries = $config['max_entries'] ?? 1000;

		return new MemoryHandler( $level, $maxEntries );
	}



	public function stack( array $channels, ?string $name = null ): Logger {
		$name = $name ?? implode( '_', $channels );

		if ( ! isset( $this->loggers[ $name ] ) ) {
			$logger = Logger::getInstance()->channel( $name );

			foreach ( $channels as $channel ) {
				$handler = $this->createHandler( $channel );
				if ( $handler ) {
					$logger->pushHandler( $handler );
				}
			}

			$this->loggers[ $name ] = $logger;
		}

		return $this->loggers[ $name ];
	}



	public function extend( string $driver, callable $factory ): self {
		$this->customDrivers[ $driver ] = $factory;
		return $this;
	}



	public function getAvailableChannels(): array {
		return array_keys( $this->config->get( 'logging.channels', [] ) );
	}



	public function emergency( string $message, array $context = [] ): void {
		$this->logger()->emergency( $message, $context );
	}



	public function alert( string $message, array $context = [] ): void {
		$this->logger()->alert( $message, $context );
	}



	public function critical( string $message, array $context = [] ): void {
		$this->logger()->critical( $message, $context );
	}



	public function error( string $message, array $context = [] ): void {
		$this->logger()->error( $message, $context );
	}



	public function warning( string $message, array $context = [] ): void {
		$this->logger()->warning( $message, $context );
	}



	public function notice( string $message, array $context = [] ): void {
		$this->logger()->notice( $message, $context );
	}



	public function info( string $message, array $context = [] ): void {
		$this->logger()->info( $message, $context );
	}



	public function debug( string $message, array $context = [] ): void {
		$this->logger()->debug( $message, $context );
	}
}
