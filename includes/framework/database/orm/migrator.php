<?php

namespace Zaplane\Framework\Database\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Migrator {

	protected static ?self $instance = null;
	protected string $migrationsPath;
	protected string $migrationsTable;

	public static function getInstance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		$this->migrationsPath = ZAPLANE_ROOT_DIR_PATH . 'includes/database/migrations/';
		$this->migrationsTable = Schema::getTable( 'migrations' );
	}

	public function setMigrationsPath( string $path ): self {
		$this->migrationsPath = rtrim( $path, '/' ) . '/';
		return $this;
	}

	public function run(): array {
		$this->ensureMigrationsTableExists();

		$files = $this->getMigrationFiles();
		$ran = $this->getRanMigrations();
		$pending = array_diff( $files, $ran );

		$migrated = [];

		foreach ( $pending as $file ) {
			$this->runMigration( $file );
			$migrated[] = $file;
		}

		return $migrated;
	}

	protected function ensureMigrationsTableExists(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
		id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		migration VARCHAR(255) NOT NULL,
		batch INT NOT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		) {$charset}";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	protected function getMigrationFiles(): array {
		if ( ! is_dir( $this->migrationsPath ) ) {
			return [];
		}

		$files = glob( $this->migrationsPath . '*.php' );
		$migrations = [];

		foreach ( $files as $file ) {
			$migrations[] = basename( $file, '.php' );
		}

		sort( $migrations );

		return $migrations;
	}

	protected function getRanMigrations(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema bookkeeping; there is nothing to cache and no API for it.
		$results = $wpdb->get_col( $wpdb->prepare( 'SELECT migration FROM %i', $this->migrationsTable ) );

		return $results ? $results : [];
	}

	protected function getNextBatchNumber(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema bookkeeping; there is nothing to cache and no API for it.
		$batch = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(batch) FROM %i', $this->migrationsTable ) );

		return ( null !== $batch ? $batch : 0 ) + 1;
	}

	protected function runMigration( string $name ): void {
		global $wpdb;

		$class = $this->resolveMigrationClass( $name );

		if ( ! $class ) {
			return;
		}

		$migration = new $class();
		$migration->up();

		$batch = $this->getNextBatchNumber();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert($this->migrationsTable, [
			'migration' => $name,
			'batch' => $batch,
		]);
	}

	protected function resolveMigrationClass( string $name ): ?string {
		$file = $this->migrationsPath . $name . '.php';

		if ( ! file_exists( $file ) ) {
			return null;
		}

		require_once $file;

		$className = $this->getClassNameFromFile( $name );

		if ( ! class_exists( $className ) ) {
			return null;
		}

		return $className;
	}

	protected function getClassNameFromFile( string $name ): string {

		$parts = explode( '_', $name );

		array_splice( $parts, 0, 4 );

		$className = '';
		foreach ( $parts as $part ) {
			$className .= ucfirst( $part );
		}

		return 'Zaplane\\Database\\Migrations\\' . $className;
	}
}
