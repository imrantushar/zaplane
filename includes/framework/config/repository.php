<?php

namespace Zaplane\Framework\Config;

if (!defined('ABSPATH')) exit;

/**
 * Configuration Repository
 *
 * Provides a scoped view of configuration for a specific namespace.
 * Useful for modules that need their own configuration scope.
 */
class Repository
{
    protected Config $config;
    protected string $namespace;

    public function __construct(Config $config, string $namespace)
    {
        $this->config = $config;
        $this->namespace = rtrim($namespace, '.') . '.';
    }

    /**
     * Get a configuration value.
     */
    public function get(string $key, $default = null)
    {
        return $this->config->get($this->namespace . $key, $default);
    }

    /**
     * Set a configuration value.
     */
    public function set(string $key, $value): self
    {
        $this->config->set($this->namespace . $key, $value);
        return $this;
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        return $this->config->has($this->namespace . $key);
    }

    /**
     * Remove a configuration key.
     */
    public function forget(string $key): self
    {
        $this->config->forget($this->namespace . $key);
        return $this;
    }

    /**
     * Get all configuration items in this namespace.
     */
    public function all(): array
    {
        return $this->config->get(rtrim($this->namespace, '.'), []);
    }

    /**
     * Merge configuration values.
     */
    public function merge(array $config): self
    {
        $existing = $this->all();
        $merged = array_replace_recursive($existing, $config);
        $this->config->set(rtrim($this->namespace, '.'), $merged);
        return $this;
    }

    /**
     * Get the namespace.
     */
    public function getNamespace(): string
    {
        return rtrim($this->namespace, '.');
    }

    /**
     * Create a child repository with nested namespace.
     */
    public function child(string $key): self
    {
        return new self($this->config, $this->namespace . $key);
    }
}
