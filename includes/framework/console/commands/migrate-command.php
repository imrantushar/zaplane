<?php

namespace Zaplane\Framework\Console\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Database\ORM\Migrator;

if (!defined('ABSPATH')) exit;

class MigrateCommand extends Command
{
    protected string $signature = 'migrate';
    protected string $description = 'Run database migrations';

    public function handle(array $args, array $assoc_args): void
    {
        $this->info('Running migrations...');

        $migrator = Migrator::getInstance();
        $migrated = $migrator->run();

        if (empty($migrated)) {
            $this->info('Nothing to migrate.');
            return;
        }

        foreach ($migrated as $migration) {
            $this->info("Migrated: {$migration}");
        }

        $this->success('Migrations completed successfully.');
    }
}
