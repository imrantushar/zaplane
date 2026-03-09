<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface HandlerInterface {



	public function handle( LogEntry $entry): bool;



	public function isHandling( string $level): bool;



	public function close(): void;
}
