<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FileHandler extends AbstractHandler {

	protected string $path;
	protected string $filename;
	protected bool $dateRotate;
	protected int $maxFiles;
	protected ?int $maxSize;
	protected $stream = null;

	public function __construct(
		string $path,
		string $minLevel = LogLevel::DEBUG,
		bool $dateRotate = true,
		int $maxFiles = 14,
		?int $maxSize = null
	) {
		parent::__construct( $minLevel );

		$this->path = rtrim( $path, '/\\' );
		$this->dateRotate = $dateRotate;
		$this->maxFiles = $maxFiles;
		$this->maxSize = $maxSize;
	}



	public function handle( LogEntry $entry ): bool {
		if ( ! $this->isHandling( $entry->getLevel() ) ) {
			return false;
		}

		$this->ensureDirectoryExists();
		$this->rotateIfNeeded();

		$filepath = $this->getFilePath();
		$formatted = $this->formatEntry( $entry ) . PHP_EOL;

		$result = file_put_contents( $filepath, $formatted, FILE_APPEND | LOCK_EX );

		return $result !== false;
	}



	protected function getFilePath(): string {
		if ( $this->dateRotate ) {
			$date = current_time( 'Y-m-d' );
			return $this->path . "/zaplane-{$date}.log";
		}

		return $this->path . '/zaplane.log';
	}



	protected function ensureDirectoryExists(): void {
		if ( ! is_dir( $this->path ) ) {
			wp_mkdir_p( $this->path );

			$htaccess = $this->path . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" );
			}

			$index = $this->path . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php // Silence is golden\n" );
			}
		}
	}



	protected function rotateIfNeeded(): void {
		$this->cleanOldFiles();

		if ( $this->maxSize !== null ) {
			$this->rotateBySize();
		}
	}



	protected function cleanOldFiles(): void {
		if ( ! $this->dateRotate || $this->maxFiles <= 0 ) {
			return;
		}

		$pattern = $this->path . '/zaplane-*.log';
		$files = glob( $pattern );

		if ( $files === false || count( $files ) <= $this->maxFiles ) {
			return;
		}

		usort($files, function ( $a, $b ) {
			return filemtime( $a ) - filemtime( $b );
		});

		$toDelete = array_slice( $files, 0, count( $files ) - $this->maxFiles );

		foreach ( $toDelete as $file ) {
			@unlink( $file );
		}
	}



	protected function rotateBySize(): void {
		$filepath = $this->getFilePath();

		if ( ! file_exists( $filepath ) ) {
			return;
		}

		if ( filesize( $filepath ) < $this->maxSize ) {
			return;
		}

		$rotated = $filepath . '.' . time();
		rename( $filepath, $rotated );
	}



	public function close(): void {
		if ( $this->stream !== null ) {
			fclose( $this->stream );
			$this->stream = null;
		}
	}
}
