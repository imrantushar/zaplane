<?php

namespace Zaplane\Framework\Console\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Database\ORM\Migrator;

if (!defined('ABSPATH')) exit;

class MigrateStatusCommand extends Command
{
    protected string $signature = 'migrate:status';
    protected string $description = 'Show the status of each migration';

    public function handle(array $args, array $assoc_args): void
    {
        $migrator = Migrator::getInstance();
        $status = $migrator->status();

        if (empty($status)) {
            $this->info('No migrations found.');
            return;
        }

        $this->line('');
        $this->line('+' . str_repeat('-', 60) . '+' . str_repeat('-', 12) . '+');
        $this->line('| ' . str_pad('Migration', 58) . ' | ' . str_pad('Status', 10) . ' |');
        $this->line('+' . str_repeat('-', 60) . '+' . str_repeat('-', 12) . '+');

        foreach ($status as $row) {
            $statusText = $row['status'] === 'Ran' ? 'Ran' : 'Pending';
            $this->line('| ' . str_pad($row['migration'], 58) . ' | ' . str_pad($statusText, 10) . ' |');
        }

        $this->line('+' . str_repeat('-', 60) . '+' . str_repeat('-', 12) . '+');
        $this->line('');
    }
}
