<?php

namespace Zaplane\Framework\Console\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Database\ORM\Migrator;

if (!defined('ABSPATH')) exit;

class MakeMigrationCommand extends Command
{
    protected string $signature = 'make:migration <name>';
    protected string $description = 'Create a new migration file';

    public function handle(array $args, array $assoc_args): void
    {
        if (empty($args[0])) {
            $this->error('Please provide a migration name.');
            return;
        }

        $name = $this->sanitizeName($args[0]);
        $table = $assoc_args['table'] ?? null;
        $isCreate = isset($assoc_args['create']);

        // If --create flag is used with a value, use it as the table name
        if ($isCreate && !empty($assoc_args['create']) && $assoc_args['create'] !== '1') {
            $table = $assoc_args['create'];
        }

        $migrator = Migrator::getInstance();
        $filename = $migrator->make($name, $table, $isCreate);

        $this->success("Created migration: {$filename}");
        $this->info("Location: includes/database/migrations/{$filename}");

        // Show helpful hint about the migration type
        if ($isCreate || preg_match('/^create_/', $name)) {
            $this->line("  Type: CREATE TABLE");
        } else {
            $this->line("  Type: ALTER TABLE");
        }
    }

    protected function sanitizeName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
    }
}
