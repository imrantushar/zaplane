<?php

namespace Zaplane\Framework\Console\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Database\ORM\Migrator;
use Zaplane\Framework\Database\ORM\Schema;

if (!defined('ABSPATH')) exit;

class MigrateFreshCommand extends Command
{
    protected string $signature = 'migrate:fresh';
    protected string $description = 'Drop all tables and re-run all migrations';

    protected array $tables = [
        'migrations',
        'workflows',
        'workflow_versions',
        'runs',
        'node_runs',
        'connections',
    ];

    public function handle(array $args, array $assoc_args): void
    {
        $force = isset($assoc_args['force']);

        if (!$force) {
            $this->warning('This will drop all Zaplane tables and re-run migrations.');
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('Dropping all tables...');

        foreach (array_reverse($this->tables) as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
                $this->info("Dropped: {$table}");
            }
        }

        $this->info('Running migrations...');

        $migrator = Migrator::getInstance();
        $migrated = $migrator->run();

        foreach ($migrated as $migration) {
            $this->info("Migrated: {$migration}");
        }

        $this->success('Database refreshed successfully.');
    }
}
