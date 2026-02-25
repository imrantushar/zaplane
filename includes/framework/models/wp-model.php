<?php

namespace Zaplane\Framework\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\QueryBuilder;

if (!defined('ABSPATH')) exit;

abstract class WPModel extends Model
{
    protected static bool $useZaplanePrefix = false;

    public static function getTable(): string
    {
        global $wpdb;

        if (empty(static::$table)) {
            $className = (new \ReflectionClass(static::class))->getShortName();
            $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
            static::$table = $snakeCase . 's';
        }

        return $wpdb->prefix . static::$table;
    }

    public static function query(): QueryBuilder
    {
        $query = new QueryBuilder(static::getTable());
        $query->setModel(static::class);
        return $query;
    }
}
