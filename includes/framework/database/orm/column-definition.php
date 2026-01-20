<?php

namespace Zaplane\Framework\Database\ORM;

if (!defined('ABSPATH')) exit;

class ColumnDefinition
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = array_merge([
            'nullable' => false,
            'default' => null,
            'unsigned' => false,
            'autoIncrement' => false,
            'primary' => false,
            'unique' => false,
            'after' => null,
            'useCurrent' => false,
            'useCurrentOnUpdate' => false,
        ], $attributes);
    }

    public function nullable(bool $value = true): self
    {
        $this->attributes['nullable'] = $value;
        return $this;
    }

    public function default($value): self
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    public function unsigned(): self
    {
        $this->attributes['unsigned'] = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->attributes['autoIncrement'] = true;
        return $this;
    }

    public function primary(): self
    {
        $this->attributes['primary'] = true;
        return $this;
    }

    public function unique(): self
    {
        $this->attributes['unique'] = true;
        return $this;
    }

    public function after(string $column): self
    {
        $this->attributes['after'] = $column;
        return $this;
    }

    public function useCurrent(): self
    {
        $this->attributes['useCurrent'] = true;
        return $this;
    }

    public function useCurrentOnUpdate(): self
    {
        $this->attributes['useCurrent'] = true;
        $this->attributes['useCurrentOnUpdate'] = true;
        return $this;
    }

    public function index(): self
    {
        $this->attributes['index'] = true;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->attributes['primary'] ?? false;
    }

    public function getName(): string
    {
        return $this->attributes['name'];
    }

    public function getType(): string
    {
        return $this->attributes['type'];
    }

    public function toSql(): string
    {
        $sql = $this->attributes['name'] . ' ' . $this->getTypeSql();

        if ($this->attributes['unsigned']) {
            $sql .= ' UNSIGNED';
        }

        if ($this->attributes['autoIncrement']) {
            $sql .= ' AUTO_INCREMENT';
        }

        if (!$this->attributes['nullable']) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if ($this->attributes['useCurrent']) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            if ($this->attributes['useCurrentOnUpdate']) {
                $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
            }
        } elseif ($this->attributes['default'] !== null) {
            $default = $this->attributes['default'];
            if (is_string($default)) {
                $sql .= " DEFAULT '{$default}'";
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } else {
                $sql .= " DEFAULT {$default}";
            }
        } elseif ($this->attributes['nullable'] && !$this->attributes['useCurrent']) {
            $sql .= ' DEFAULT NULL';
        }

        if ($this->attributes['unique']) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    protected function getTypeSql(): string
    {
        $type = $this->attributes['type'];

        switch ($type) {
            case 'varchar':
            case 'char':
                $length = $this->attributes['length'] ?? 255;
                return "{$type}({$length})";

            case 'tinyint':
                $length = $this->attributes['length'] ?? null;
                return $length ? "tinyint({$length})" : 'tinyint';

            case 'enum':
                $allowed = $this->attributes['allowed'] ?? [];
                $values = "'" . implode("','", $allowed) . "'";
                return "enum({$values})";

            case 'decimal':
                $precision = $this->attributes['precision'] ?? 8;
                $scale = $this->attributes['scale'] ?? 2;
                return "decimal({$precision},{$scale})";

            default:
                return $type;
        }
    }
}
