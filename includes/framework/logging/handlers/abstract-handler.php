<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractHandler implements HandlerInterface {

	protected string $minLevel;
	protected string $format = '[{timestamp}] {channel}.{level}: {message} {context}';
	protected bool $bubble = true;

	public function __construct( string $minLevel = LogLevel::DEBUG ) {
		$this->minLevel = $minLevel;
	}



	public function isHandling( string $level ): bool {
		return LogLevel::meetsThreshold( $level, $this->minLevel );
	}



	public function setMinLevel( string $level ): self {
		$this->minLevel = $level;
		return $this;
	}



	public function setFormat( string $format ): self {
		$this->format = $format;
		return $this;
	}



	public function setBubble( bool $bubble ): self {
		$this->bubble = $bubble;
		return $this;
	}



	protected function formatEntry( LogEntry $entry ): string {
		return $entry->format( $this->format );
	}



	public function close(): void {
	}
}
