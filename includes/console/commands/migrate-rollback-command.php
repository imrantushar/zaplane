<?php

namespace Zaplane\Console\Commands;

use Zaplane\Console\Command;
use Zaplane\Database\ORM\Migrator;

if (!defined('ABSPATH')) exit;

class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback';
    protected string $description = 'Rollback the last database migration batch';

    public function handle(array $args, array $assoc_args): void
    {
        $steps = isset($assoc_args['step']) ? (int) $assoc_args['step'] : 1;

        $this->info("Rolling back {$steps} migration batch(es)...");

        $migrator = Migrator::getInstance();
        $rolledBack = $migrator->rollback($steps);

        if (empty($rolledBack)) {
            $this->info('Nothing to rollback.');
            return;
        }

        foreach ($rolledBack as $migration) {
            $this->info("Rolled back: {$migration}");
        }

        $this->success('Rollback completed successfully.');
    }
}
