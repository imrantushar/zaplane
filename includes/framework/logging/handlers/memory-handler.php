<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MemoryHandler extends AbstractHandler {

	protected array $entries = [];
	protected int $maxEntries;

	public function __construct(
		string $minLevel = LogLevel::DEBUG,
		int $maxEntries = 1000
	) {
		parent::__construct( $minLevel );
		$this->maxEntries = $maxEntries;
	}



	public function handle( LogEntry $entry ): bool {
		if ( ! $this->isHandling( $entry->getLevel() ) ) {
			return false;
		}

		$this->entries[] = $entry;

		if ( count( $this->entries ) > $this->maxEntries ) {
			$this->entries = array_slice( $this->entries, -$this->maxEntries );
		}

		return true;
	}



	public function getEntries(): array {
		return $this->entries;
	}



	public function getEntriesByLevel( string $level ): array {
		return array_filter( $this->entries, fn( $entry) => $entry->getLevel() === $level );
	}



	public function getEntriesByChannel( string $channel ): array {
		return array_filter( $this->entries, fn( $entry) => $entry->getChannel() === $channel );
	}



	public function getEntriesByTraceId( string $traceId ): array {
		return array_filter( $this->entries, fn( $entry) => $entry->getTraceId() === $traceId );
	}



	public function search( string $query ): array {
		return array_filter($this->entries, function ( $entry ) use ( $query ) {
			return stripos( $entry->getMessage(), $query ) !== false ||
				   stripos( $entry->getInterpolatedMessage(), $query ) !== false;
		});
	}



	public function clear(): void {
		$this->entries = [];
	}



	public function count(): int {
		return count( $this->entries );
	}



	public function last(): ?LogEntry {
		return $this->entries[ array_key_last( $this->entries ) ] ?? null;
	}



	public function hasErrors(): bool {
		foreach ( $this->entries as $entry ) {
			if ( LogLevel::meetsThreshold( $entry->getLevel(), LogLevel::ERROR ) ) {
				return true;
			}
		}
		return false;
	}



	public function toArray(): array {
		return array_map( fn( $entry) => $entry->toArray(), $this->entries );
	}
}
