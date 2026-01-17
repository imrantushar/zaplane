<?php

namespace Zaplane\Database\ORM;

if (!defined('ABSPATH')) exit;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $indexes = [];
    protected array $commands = [];
    protected string $charset;
    protected string $engine = 'InnoDB';

    public function __construct(string $table)
    {
        global $wpdb;
        $this->table = $table;
        $this->charset = $wpdb->get_charset_collate();
    }

    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }

    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('bigint', $column, [
            'unsigned' => true,
            'autoIncrement' => true,
            'primary' => true,
        ]);
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigint', $column);
    }

    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigint', $column, ['unsigned' => true]);
    }

    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('int', $column);
    }

    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('int', $column, ['unsigned' => true]);
    }

    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyint', $column);
    }

    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyint', $column, ['unsigned' => true]);
    }

    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallint', $column);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyint', $column, ['length' => 1]);
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('varchar', $column, ['length' => $length]);
    }

    public function char(string $column, int $length = 64): ColumnDefinition
    {
        return $this->addColumn('char', $column, ['length' => $length]);
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumtext', $column);
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longtext', $column);
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->longText($column);
    }

    public function datetime(string $column): ColumnDefinition
    {
        return $this->addColumn('datetime', $column);
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('datetime', $column);
    }

    public function timestamps(): void
    {
        $this->datetime('created_at')->nullable()->useCurrent();
        $this->datetime('updated_at')->nullable()->useCurrentOnUpdate();
    }

    public function softDeletes(): ColumnDefinition
    {
        return $this->datetime('deleted_at')->nullable();
    }

    public function enum(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['allowed' => $allowed]);
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, [
            'precision' => $precision,
            'scale' => $scale,
        ]);
    }

    public function float(string $column): ColumnDefinition
    {
        return $this->addColumn('float', $column);
    }

    public function double(string $column): ColumnDefinition
    {
        return $this->addColumn('double', $column);
    }

    public function index($columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? $this->createIndexName('index', $columns);
        $this->indexes[] = [
            'type' => 'index',
            'columns' => $columns,
            'name' => $name,
        ];
        return $this;
    }

    public function unique($columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? $this->createIndexName('unique', $columns);
        $this->indexes[] = [
            'type' => 'unique',
            'columns' => $columns,
            'name' => $name,
        ];
        return $this;
    }

    public function primary($columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $this->indexes[] = [
            'type' => 'primary',
            'columns' => $columns,
            'name' => $name ?? 'PRIMARY',
        ];
        return $this;
    }

    public function foreignId(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column);
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($column);
        $this->commands[] = ['type' => 'foreign', 'definition' => $foreign];
        return $foreign;
    }

    public function dropColumn(string $column): self
    {
        $this->commands[] = ['type' => 'dropColumn', 'column' => $column];
        return $this;
    }

    public function renameColumn(string $from, string $to): self
    {
        $this->commands[] = ['type' => 'renameColumn', 'from' => $from, 'to' => $to];
        return $this;
    }

    public function dropIndex(string $name): self
    {
        $this->commands[] = ['type' => 'dropIndex', 'name' => $name];
        return $this;
    }

    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $definition = new ColumnDefinition(array_merge([
            'type' => $type,
            'name' => $name,
        ], $parameters));

        $this->columns[] = $definition;
        return $definition;
    }

    protected function createIndexName(string $type, array $columns): string
    {
        return strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
    }

    public function toSql(): string
    {
        $columnDefs = [];
        $primaryKey = null;

        foreach ($this->columns as $column) {
            $columnDefs[] = $column->toSql();
            if ($column->isPrimary()) {
                $primaryKey = $column->getName();
            }
        }

        if ($primaryKey) {
            $columnDefs[] = "PRIMARY KEY ({$primaryKey})";
        }

        foreach ($this->indexes as $index) {
            $cols = implode(', ', $index['columns']);
            switch ($index['type']) {
                case 'unique':
                    $columnDefs[] = "UNIQUE KEY {$index['name']} ({$cols})";
                    break;
                case 'index':
                    $columnDefs[] = "KEY {$index['name']} ({$cols})";
                    break;
            }
        }

        $columnsStr = implode(",\n    ", $columnDefs);

        return "CREATE TABLE IF NOT EXISTS {$this->table} (\n    {$columnsStr}\n) {$this->charset}";
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getTable(): string
    {
        return $this->table;
    }
}
