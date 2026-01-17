<?php

namespace Zaplane\Console\Commands;

use Zaplane\Console\Command;
use Zaplane\Database\ORM\Migrator;

if (!defined('ABSPATH')) exit;

class MakeModelCommand extends Command
{
    protected string $signature = 'make:model <name>';
    protected string $description = 'Create a new model class';

    public function handle(array $args, array $assoc_args): void
    {
        if (empty($args[0])) {
            $this->error('Please provide a model name.');
            return;
        }

        $name = $this->formatClassName($args[0]);
        $withMigration = isset($assoc_args['migration']) || isset($assoc_args['m']);

        $this->createModel($name);

        if ($withMigration) {
            $this->createMigration($name);
        }
    }

    protected function formatClassName(string $name): string
    {
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }

    protected function getTableName(string $className): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        return $snake . 's';
    }

    protected function getFileName(string $className): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className)) . '.php';
    }

    protected function createModel(string $name): void
    {
        $table = $this->getTableName($name);
        $filename = $this->getFileName($name);
        $path = ZAPLANE_ROOT_DIR_PATH . 'includes/models/' . $filename;

        if (file_exists($path)) {
            $this->error("Model {$name} already exists.");
            return;
        }

        $content = <<<PHP
<?php

namespace Zaplane\Models;

use Zaplane\Database\ORM\Model;

if (!defined('ABSPATH')) exit;

class {$name} extends Model
{
    protected static string \$table = '{$table}';

    protected static array \$fillable = [
        //
    ];

    protected static array \$casts = [
        'id' => 'integer',
    ];
}
PHP;

        file_put_contents($path, $content);

        $this->success("Model created: includes/models/{$filename}");
    }

    protected function createMigration(string $name): void
    {
        $table = $this->getTableName($name);
        $migrationName = 'create_' . $table . '_table';

        $migrator = Migrator::getInstance();
        $filename = $migrator->make($migrationName);

        $path = ZAPLANE_ROOT_DIR_PATH . 'includes/database/migrations/' . $filename;
        $content = file_get_contents($path);

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

        file_put_contents($path, $content);

        $this->success("Migration created: includes/database/migrations/{$filename}");
    }
}
