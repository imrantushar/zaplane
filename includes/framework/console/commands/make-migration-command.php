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
        $create = isset($assoc_args['create']) ? $assoc_args['create'] : $table;

        $migrator = Migrator::getInstance();
        $filename = $migrator->make($name);

        if ($create || $table) {
            $this->updateMigrationContent($filename, $create ?: $table, (bool) $create);
        }

        $this->success("Created migration: {$filename}");
        $this->info("Location: includes/database/migrations/{$filename}");
    }

    protected function sanitizeName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
    }

    protected function updateMigrationContent(string $filename, string $table, bool $isCreate): void
    {
        $path = ZAPLANE_ROOT_DIR_PATH . 'includes/database/migrations/' . $filename;

        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        if ($isCreate) {
            $content = str_replace(
                "Schema::create('table_name'",
                "Schema::create('{$table}'",
                $content
            );
            $content = str_replace(
                "Schema::drop('table_name')",
                "Schema::drop('{$table}')",
                $content
            );
        } else {
            $content = str_replace(
                "Schema::create('table_name', function (Blueprint \$table) {\n            \$table->id();\n            \$table->timestamps();\n        });",
                "Schema::table('{$table}', function (Blueprint \$table) {\n            //\n        });",
                $content
            );
            $content = str_replace(
                "Schema::drop('table_name');",
                "Schema::table('{$table}', function (Blueprint \$table) {\n            //\n        });",
                $content
            );
        }

        file_put_contents($path, $content);
    }
}
