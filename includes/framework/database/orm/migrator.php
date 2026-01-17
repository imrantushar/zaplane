<?php

namespace Zaplane\Framework\Database\ORM;

if (!defined('ABSPATH')) exit;

class Migrator
{
    protected static ?self $instance = null;
    protected string $migrationsPath;
    protected string $migrationsTable;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        $this->migrationsPath = ZAPLANE_ROOT_DIR_PATH . 'includes/database/migrations/';
        $this->migrationsTable = Schema::getTable('migrations');
    }

    public function setMigrationsPath(string $path): self
    {
        $this->migrationsPath = rtrim($path, '/') . '/';
        return $this;
    }

    public function run(): array
    {
        $this->ensureMigrationsTableExists();

        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();
        $pending = array_diff($files, $ran);

        $migrated = [];

        foreach ($pending as $file) {
            $this->runMigration($file);
            $migrated[] = $file;
        }

        return $migrated;
    }

    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationsTableExists();

        $migrations = $this->getLastBatchMigrations($steps);
        $rolledBack = [];

        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    public function reset(): array
    {
        $this->ensureMigrationsTableExists();

        $migrations = $this->getAllRanMigrations();
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    public function refresh(): void
    {
        $this->reset();
        $this->run();
    }

    public function status(): array
    {
        $this->ensureMigrationsTableExists();

        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();

        $status = [];

        foreach ($files as $file) {
            $status[] = [
                'migration' => $file,
                'status' => in_array($file, $ran) ? 'Ran' : 'Pending',
            ];
        }

        return $status;
    }

    protected function ensureMigrationsTableExists(): void
    {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) {$charset}";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    protected function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '*.php');
        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }

        sort($migrations);

        return $migrations;
    }

    protected function getRanMigrations(): array
    {
        global $wpdb;

        $results = $wpdb->get_col("SELECT migration FROM {$this->migrationsTable}");

        return $results ?: [];
    }

    protected function getAllRanMigrations(): array
    {
        global $wpdb;

        $results = $wpdb->get_col(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY batch DESC, migration DESC"
        );

        return $results ?: [];
    }

    protected function getLastBatchMigrations(int $steps): array
    {
        global $wpdb;

        $batch = $wpdb->get_var(
            "SELECT MAX(batch) FROM {$this->migrationsTable}"
        );

        if (!$batch) {
            return [];
        }

        $minBatch = max(1, $batch - $steps + 1);

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch >= %d ORDER BY batch DESC, migration DESC",
            $minBatch
        ));

        return $results ?: [];
    }

    protected function getNextBatchNumber(): int
    {
        global $wpdb;

        $batch = $wpdb->get_var(
            "SELECT MAX(batch) FROM {$this->migrationsTable}"
        );

        return ($batch ?? 0) + 1;
    }

    protected function runMigration(string $name): void
    {
        global $wpdb;

        $class = $this->resolveMigrationClass($name);

        if (!$class) {
            return;
        }

        $migration = new $class();
        $migration->up();

        $batch = $this->getNextBatchNumber();

        $wpdb->insert($this->migrationsTable, [
            'migration' => $name,
            'batch' => $batch,
        ]);
    }

    protected function rollbackMigration(string $name): void
    {
        global $wpdb;

        $class = $this->resolveMigrationClass($name);

        if ($class) {
            $migration = new $class();
            $migration->down();
        }

        $wpdb->delete($this->migrationsTable, ['migration' => $name]);
    }

    protected function resolveMigrationClass(string $name): ?string
    {
        $file = $this->migrationsPath . $name . '.php';

        if (!file_exists($file)) {
            return null;
        }

        require_once $file;

        $className = $this->getClassNameFromFile($name);

        if (!class_exists($className)) {
            return null;
        }

        return $className;
    }

    protected function getClassNameFromFile(string $name): string
    {
        $parts = explode('_', $name);
        array_shift($parts);

        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return 'Zaplane\\Database\\Migrations\\' . $className;
    }

    public function make(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $name . '.php';
        $filepath = $this->migrationsPath . $filename;

        $className = '';
        $parts = explode('_', $name);
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        $content = <<<PHP
<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if (!defined('ABSPATH')) exit;

class {$className} extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('table_name');
    }
}
PHP;

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        file_put_contents($filepath, $content);

        return $filename;
    }
}
