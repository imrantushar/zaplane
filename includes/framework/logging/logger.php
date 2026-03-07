<?php

namespace Zaplane\Framework\Logging;

use Zaplane\Framework\Logging\Handlers\HandlerInterface;
use Zaplane\Framework\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	private static ?self $instance = null;



	protected array $handlers = [];



	protected string $channel = 'default';



	protected ?string $traceId = null;



	protected array $globalContext = [];



	protected bool $enabled = true;



	protected string $minLevel = LogLevel::DEBUG;



	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}



	public static function resetInstance(): void {
		if ( self::$instance !== null ) {
			self::$instance->close();
		}
		self::$instance = null;
	}

	private function __construct() {
		$this->loadConfiguration();
	}



	protected function loadConfiguration(): void {
		$config = Config::getInstance();

		$this->enabled = $config->get( 'logging.enabled', true );
		$this->minLevel = $config->get( 'logging.level', LogLevel::DEBUG );
	}



	public function pushHandler( HandlerInterface $handler ): self {
		array_unshift( $this->handlers, $handler );
		return $this;
	}



	public function popHandler(): ?HandlerInterface {
		return array_shift( $this->handlers );
	}



	public function getHandlers(): array {
		return $this->handlers;
	}



	public function setHandlers( array $handlers ): self {
		$this->handlers = [];
		foreach ( $handlers as $handler ) {
			$this->pushHandler( $handler );
		}
		return $this;
	}



	public function channel( string $channel ): self {
		$logger = clone $this;
		$logger->channel = $channel;
		return $logger;
	}



	public function withTraceId( string $traceId ): self {
		$logger = clone $this;
		$logger->traceId = $traceId;
		return $logger;
	}



	public function generateTraceId(): string {
		return bin2hex( random_bytes( 16 ) );
	}



	public function getTraceId(): ?string {
		return $this->traceId;
	}



	public function withContext( array $context ): self {
		$logger = clone $this;
		$logger->globalContext = array_merge( $logger->globalContext, $context );
		return $logger;
	}



	public function setGlobalContext( array $context ): self {
		$this->globalContext = $context;
		return $this;
	}



	public function enable(): self {
		$this->enabled = true;
		return $this;
	}



	public function disable(): self {
		$this->enabled = false;
		return $this;
	}



	public function isEnabled(): bool {
		return $this->enabled;
	}



	public function setMinLevel( string $level ): self {
		$this->minLevel = $level;
		return $this;
	}



	public function log( string $level, string $message, array $context = [] ): void {
		if ( ! $this->enabled ) {
			return;
		}

		if ( ! LogLevel::meetsThreshold( $level, $this->minLevel ) ) {
			return;
		}

		$context = array_merge( $this->globalContext, $context );

		$entry = new LogEntry(
			$level,
			$message,
			$context,
			$this->channel,
			$this->traceId
		);

		foreach ( $this->handlers as $handler ) {
			if ( $handler->isHandling( $level ) ) {
				$handler->handle( $entry );
			}
		}

		do_action( 'zaplane_log', $entry );
		do_action( "zaplane_log_{$level}", $entry );
	}



	public function emergency( string $message, array $context = [] ): void {
		$this->log( LogLevel::EMERGENCY, $message, $context );
	}



	public function alert( string $message, array $context = [] ): void {
		$this->log( LogLevel::ALERT, $message, $context );
	}



	public function critical( string $message, array $context = [] ): void {
		$this->log( LogLevel::CRITICAL, $message, $context );
	}



	public function error( string $message, array $context = [] ): void {
		$this->log( LogLevel::ERROR, $message, $context );
	}



	public function warning( string $message, array $context = [] ): void {
		$this->log( LogLevel::WARNING, $message, $context );
	}



	public function notice( string $message, array $context = [] ): void {
		$this->log( LogLevel::NOTICE, $message, $context );
	}



	public function info( string $message, array $context = [] ): void {
		$this->log( LogLevel::INFO, $message, $context );
	}



	public function debug( string $message, array $context = [] ): void {
		$this->log( LogLevel::DEBUG, $message, $context );
	}



	public function exception( \Throwable $exception, string $level = LogLevel::ERROR, array $context = [] ): void {
		$context = array_merge($context, [
			'exception' => get_class( $exception ),
			'message' => $exception->getMessage(),
			'code' => $exception->getCode(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		]);

		if ( $exception->getPrevious() ) {
			$context['previous'] = [
				'exception' => get_class( $exception->getPrevious() ),
				'message' => $exception->getPrevious()->getMessage(),
			];
		}

		$this->log( $level, $exception->getMessage(), $context );
	}



	public function workflow( int $workflowId, string $message, array $context = [] ): void {
		$context['workflow_id'] = $workflowId;
		$this->channel( 'workflow' )->info( $message, $context );
	}



	public function node( string $nodeId, string $message, array $context = [] ): void {
		$context['node_id'] = $nodeId;
		$this->channel( 'node' )->debug( $message, $context );
	}



	public function integration( string $integration, string $message, array $context = [] ): void {
		$context['integration'] = $integration;
		$this->channel( 'integration' )->info( $message, $context );
	}



	public function api( string $method, string $endpoint, array $context = [] ): void {
		$context['method'] = $method;
		$context['endpoint'] = $endpoint;
		$this->channel( 'api' )->info( "{$method} {$endpoint}", $context );
	}



	public function startTiming( string $name ): void {
		$this->globalContext[ "_timing_{$name}" ] = microtime( true );
	}



	public function endTiming( string $name, string $message = null, array $context = [] ): float {
		$startKey = "_timing_{$name}";
		$start = $this->globalContext[ $startKey ] ?? microtime( true );
		unset( $this->globalContext[ $startKey ] );

		$duration = microtime( true ) - $start;
		$context['duration_ms'] = round( $duration * 1000, 2 );

		$message = $message ?? "Completed: {$name}";
		$this->debug( $message, $context );

		return $duration;
	}



	public function close(): void {
		foreach ( $this->handlers as $handler ) {
			$handler->close();
		}
	}



	public function __destruct() {
		$this->close();
	}



	public function __clone() {     }
}
