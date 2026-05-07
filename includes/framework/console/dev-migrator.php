<?php

namespace Zaplane\Framework\Console;

use Zaplane\Framework\Database\ORM\Migrator;
use Zaplane\Framework\Database\ORM\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DevMigrator extends Migrator {

	protected static ?self $instance = null;

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function rollback( int $steps = 1 ): array {
		$this->ensureMigrationsTableExists();

		$migrations = $this->getLastBatchMigrations( $steps );
		$rolledBack = [];

		foreach ( $migrations as $migration ) {
			$this->rollbackMigration( $migration );
			$rolledBack[] = $migration;
		}

		return $rolledBack;
	}

	public function reset(): array {
		$this->ensureMigrationsTableExists();

		$migrations = $this->getAllRanMigrations();
		$rolledBack = [];

		foreach ( array_reverse( $migrations ) as $migration ) {
			$this->rollbackMigration( $migration );
			$rolledBack[] = $migration;
		}

		return $rolledBack;
	}

	public function refresh(): void {
		$this->reset();
		$this->run();
	}

	public function status(): array {
		$this->ensureMigrationsTableExists();

		$files = $this->getMigrationFiles();
		$ran = $this->getRanMigrations();

		$status = [];

		foreach ( $files as $file ) {
			$status[] = [
				'migration' => $file,
				'status'    => in_array( $file, $ran ) ? 'Ran' : 'Pending',
			];
		}

		return $status;
	}

	public function make( string $name ): string {
		$timestamp = gmdate( 'Y_m_d_His' );
		$filename  = $timestamp . '_' . $name . '.php';
		$filepath  = $this->migrationsPath . $filename;

		$className = '';
		$parts     = explode( '_', $name );
		foreach ( $parts as $part ) {
			$className .= ucfirst( $part );
		}

		$content = implode( "\n", [
			'<?php',
			'',
			'namespace Zaplane\Database\Migrations;',
			'',
			'use Zaplane\Framework\Database\ORM\Migration;',
			'use Zaplane\Framework\Database\ORM\Schema;',
			'use Zaplane\Framework\Database\ORM\Blueprint;',
			'',
			"if (!defined('ABSPATH')) exit;",
			'',
			"class {$className} extends Migration",
			'{',
			'	public function up(): void',
			'	{',
			"		Schema::create('table_name', function (Blueprint \$table) {",
			'			$table->id();',
			'			$table->timestamps();',
			'		});',
			'	}',
			'',
			'	public function down(): void',
			'	{',
			"		Schema::drop('table_name');",
			'	}',
			'}',
			'',
		] );

		if ( ! is_dir( $this->migrationsPath ) ) {
			wp_mkdir_p( $this->migrationsPath );
		}

		file_put_contents( $filepath, $content );

		return $filename;
	}

	protected function getAllRanMigrations(): array {
		global $wpdb;

		$results = $wpdb->get_col(
			"SELECT migration FROM {$this->migrationsTable} ORDER BY batch DESC, migration DESC"
		);

		return $results ?: [];
	}

	protected function getLastBatchMigrations( int $steps ): array {
		global $wpdb;

		$batch = $wpdb->get_var(
			"SELECT MAX(batch) FROM {$this->migrationsTable}"
		);

		if ( ! $batch ) {
			return [];
		}

		$minBatch = max( 1, $batch - $steps + 1 );

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT migration FROM {$this->migrationsTable} WHERE batch >= %d ORDER BY batch DESC, migration DESC",
			$minBatch
		) );

		return $results ?: [];
	}

	protected function rollbackMigration( string $name ): void {
		global $wpdb;

		$class = $this->resolveMigrationClass( $name );

		if ( $class ) {
			$migration = new $class();
			$migration->down();
		}

		$wpdb->delete( $this->migrationsTable, [ 'migration' => $name ] );
	}
}
