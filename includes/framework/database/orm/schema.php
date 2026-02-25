<?php

namespace Zaplane\Framework\Database\ORM;

if (!defined('ABSPATH')) exit;

class Schema
{
    protected static ?string $prefix = null;

    public static function getPrefix(): string
    {
        if (self::$prefix === null) {
            global $wpdb;
            self::$prefix = ($wpdb->prefix ?? 'wp_') . 'zaplane_';
        }
        return self::$prefix;
    }

    public static function resetPrefix(): void
    {
        self::$prefix = null;
    }

    public static function getTable(string $table): string
    {
        return self::getPrefix() . $table;
    }

    public static function create(string $table, callable $callback): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $fullTable = self::getTable($table);
        $blueprint = new Blueprint($fullTable);
        $callback($blueprint);

        $sql = $blueprint->toSql();
        dbDelta($sql);

        self::runCommands($blueprint);
    }

    public static function table(string $table, callable $callback): void
    {
        $fullTable = self::getTable($table);
        $blueprint = new Blueprint($fullTable);
        $callback($blueprint);

        self::runAlterCommands($fullTable, $blueprint);
    }

    public static function drop(string $table): void
    {
        global $wpdb;
        $fullTable = self::getTable($table);
        $wpdb->query("DROP TABLE IF EXISTS {$fullTable}");
    }

    public static function dropIfExists(string $table): void
    {
        self::drop($table);
    }

    public static function rename(string $from, string $to): void
    {
        global $wpdb;
        $fromTable = self::getTable($from);
        $toTable = self::getTable($to);
        $wpdb->query("RENAME TABLE {$fromTable} TO {$toTable}");
    }

    public static function hasTable(string $table): bool
    {
        global $wpdb;
        $fullTable = self::getTable($table);
        $result = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $fullTable)
        );
        return $result === $fullTable;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        global $wpdb;
        $fullTable = self::getTable($table);
        $result = $wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM {$fullTable} LIKE %s", $column)
        );
        return count($result) > 0;
    }

    public static function getColumnListing(string $table): array
    {
        global $wpdb;
        $fullTable = self::getTable($table);
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$fullTable}");
        return array_map(fn($col) => $col->Field, $columns);
    }

    protected static function runCommands(Blueprint $blueprint): void
    {
        global $wpdb;
        $table = $blueprint->getTable();

        foreach ($blueprint->getCommands() as $command) {
            switch ($command['type']) {
                case 'foreign':
                    $foreignSql = $command['definition']->toSql($table);
                    if ($foreignSql) {
                        $wpdb->query("ALTER TABLE {$table} ADD {$foreignSql}");
                    }
                    break;
            }
        }
    }

    protected static function runAlterCommands(string $table, Blueprint $blueprint): void
    {
        global $wpdb;

        foreach ($blueprint->getColumns() as $column) {
            $columnName = $column->getName();
            if (self::columnExists($table, $columnName)) {
                $sql = "ALTER TABLE {$table} MODIFY COLUMN " . $column->toSql();
            } else {
                $sql = "ALTER TABLE {$table} ADD COLUMN " . $column->toSql();
            }
            $wpdb->query($sql);
        }

        foreach ($blueprint->getIndexes() as $index) {
            $cols = implode(', ', $index['columns']);
            switch ($index['type']) {
                case 'unique':
                    $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY {$index['name']} ({$cols})");
                    break;
                case 'index':
                    $wpdb->query("ALTER TABLE {$table} ADD KEY {$index['name']} ({$cols})");
                    break;
            }
        }

        foreach ($blueprint->getCommands() as $command) {
            switch ($command['type']) {
                case 'dropColumn':
                    $wpdb->query("ALTER TABLE {$table} DROP COLUMN {$command['column']}");
                    break;
                case 'renameColumn':
                    $colInfo = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE '{$command['from']}'");
                    if ($colInfo) {
                        $wpdb->query("ALTER TABLE {$table} CHANGE {$command['from']} {$command['to']} {$colInfo->Type}");
                    }
                    break;
                case 'dropIndex':
                    $wpdb->query("ALTER TABLE {$table} DROP INDEX {$command['name']}");
                    break;
                case 'foreign':
                    $foreignSql = $command['definition']->toSql($table);
                    if ($foreignSql) {
                        $wpdb->query("ALTER TABLE {$table} ADD {$foreignSql}");
                    }
                    break;
            }
        }
    }

    protected static function columnExists(string $table, string $column): bool
    {
        global $wpdb;
        $result = $wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column)
        );
        return count($result) > 0;
    }
}
