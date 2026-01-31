<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * NodeConfig
 *
 * Standardized accessor for node data that handles all the inconsistent
 * data structure variations in the codebase.
 *
 * The problem: Node data can be stored in multiple places:
 * - $node['data']['config']
 * - $node['config']['data']
 * - $node['data']['event']
 * - $node['config']['action']
 * - $node['event']
 *
 * This class provides a unified interface to access node data regardless
 * of where it's stored.
 *
 * Usage:
 * ```php
 * $config = NodeConfig::from($node);
 *
 * // Get all config values
 * $values = $config->config();
 *
 * // Get event/action key
 * $event = $config->event();
 *
 * // Get a specific value with default
 * $channel = $config->get('channel', '#general');
 *
 * // Get credentials
 * $creds = $config->credentials();
 *
 * // Check if has connection
 * if ($config->hasConnection()) {
 *     $connection_id = $config->connectionId();
 * }
 * ```
 */
class NodeConfig {

    /**
     * The raw node array.
     *
     * @var array
     */
    private array $node;

    /**
     * Cached config values.
     *
     * @var array|null
     */
    private ?array $cached_config = null;

    /**
     * Create a new NodeConfig instance.
     *
     * @param array $node The node array
     */
    public function __construct( array $node ) {
        $this->node = $node;
    }

    /**
     * Static factory method.
     *
     * @param array $node The node array
     * @return self
     */
    public static function from( array $node ): self {
        return new self( $node );
    }

    /**
     * Get the raw node array.
     *
     * @return array
     */
    public function raw(): array {
        return $this->node;
    }

    /**
     * Get all configuration values.
     *
     * Checks multiple possible locations and merges them.
     *
     * @return array
     */
    public function config(): array {
        if ( $this->cached_config !== null ) {
            return $this->cached_config;
        }

        // Check all possible config locations
        $config = [];

        // Priority 1: node['data']['config'] (most common in new code)
        if ( isset( $this->node['data']['config'] ) && is_array( $this->node['data']['config'] ) ) {
            $config = array_merge( $config, $this->node['data']['config'] );
        }

        // Priority 2: node['config']['data'] (alternative structure)
        if ( isset( $this->node['config']['data'] ) && is_array( $this->node['config']['data'] ) ) {
            $config = array_merge( $config, $this->node['config']['data'] );
        }

        // Priority 3: node['config'] directly (if it has config-like keys)
        if ( isset( $this->node['config'] ) && is_array( $this->node['config'] ) ) {
            // Only merge if it doesn't have 'data' key (to avoid double-merging)
            if ( ! isset( $this->node['config']['data'] ) ) {
                // Filter out non-config keys
                $filtered = array_filter( $this->node['config'], function ( $key ) {
                    return ! in_array( $key, [ 'action', 'connection_id' ], true );
                }, ARRAY_FILTER_USE_KEY );
                $config = array_merge( $config, $filtered );
            }
        }

        $this->cached_config = $config;
        return $config;
    }

    /**
     * Get the event/action key.
     *
     * Handles multiple possible locations:
     * - node['event']
     * - node['data']['event']
     * - node['config']['action']
     * - node['data']['action']
     *
     * @return string
     */
    public function event(): string {
        return $this->node['event']
            ?? $this->node['data']['event']
            ?? $this->node['config']['action']
            ?? $this->node['data']['action']
            ?? '';
    }

    /**
     * Alias for event() - get the action key.
     *
     * @return string
     */
    public function action(): string {
        return $this->event();
    }

    /**
     * Get the integration/app slug.
     *
     * @return string
     */
    public function app(): string {
        return strtolower( $this->node['data']['app'] ?? $this->node['app'] ?? '' );
    }

    /**
     * Get a specific configuration value.
     *
     * @param string $key     The config key
     * @param mixed  $default Default value if not found
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        $config = $this->config();
        return $config[ $key ] ?? $default;
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key The config key
     * @return bool
     */
    public function has( string $key ): bool {
        $config = $this->config();
        return isset( $config[ $key ] );
    }

    /**
     * Get decrypted connection credentials.
     *
     * These are injected by the automation engine before execution.
     *
     * @return array
     */
    public function credentials(): array {
        return $this->node['_connection_credentials'] ?? [];
    }

    /**
     * Check if credentials are available.
     *
     * @return bool
     */
    public function hasCredentials(): bool {
        return ! empty( $this->credentials() );
    }

    /**
     * Get a specific credential value.
     *
     * @param string $key     The credential key
     * @param mixed  $default Default value if not found
     * @return mixed
     */
    public function credential( string $key, $default = null ) {
        return $this->credentials()[ $key ] ?? $default;
    }

    /**
     * Get the connection ID.
     *
     * @return int|null
     */
    public function connectionId(): ?int {
        $id = $this->node['data']['connection_id']
            ?? $this->node['config']['connection_id']
            ?? null;

        return $id ? (int) $id : null;
    }

    /**
     * Check if this node has a connection configured.
     *
     * @return bool
     */
    public function hasConnection(): bool {
        return $this->connectionId() !== null;
    }

    /**
     * Get the execution context.
     *
     * @return ExecutionContext|null
     */
    public function context(): ?ExecutionContext {
        return $this->node['_context'] ?? null;
    }

    /**
     * Get the node ID.
     *
     * @return string
     */
    public function id(): string {
        return (string) ( $this->node['id'] ?? '' );
    }

    /**
     * Get the node type.
     *
     * @return string 'trigger' | 'action'
     */
    public function type(): string {
        return $this->node['type'] ?? 'action';
    }

    /**
     * Check if this is a trigger node.
     *
     * @return bool
     */
    public function isTrigger(): bool {
        return $this->type() === 'trigger';
    }

    /**
     * Check if this is an action node.
     *
     * @return bool
     */
    public function isAction(): bool {
        return $this->type() === 'action';
    }

    /**
     * Get the node position (for UI).
     *
     * @return array ['x' => int, 'y' => int]
     */
    public function position(): array {
        return $this->node['position'] ?? [ 'x' => 0, 'y' => 0 ];
    }

    /**
     * Convert to array (for debugging).
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id'            => $this->id(),
            'type'          => $this->type(),
            'app'           => $this->app(),
            'event'         => $this->event(),
            'config'        => $this->config(),
            'connection_id' => $this->connectionId(),
            'has_creds'     => $this->hasCredentials(),
        ];
    }
}
