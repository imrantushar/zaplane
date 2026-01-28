<?php

namespace Zaplane\Framework\Classes;

use Zaplane\Models\NodeRun;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ExecutionContext
 *
 * Wraps all runtime data available to a node during execution.
 * Passed to `execute_node()` as a structured object instead of raw arrays,
 * giving junior developers clear access to input data, credentials,
 * resolved config, and a logger.
 *
 * Usage in an integration:
 * ```php
 * public static function execute_node(array $node, array $input): array {
 *     // The automation engine also provides an ExecutionContext if available:
 *     // $context = $node['_context'] ?? null;
 *     // if ($context instanceof ExecutionContext) {
 *     //     $context->log('info', 'Starting API call');
 *     //     $creds = $context->credentials();
 *     //     $config = $context->config();
 *     // }
 * }
 * ```
 */
class ExecutionContext {

    private int $run_id;
    private string $node_key;
    private array $input;
    private array $credentials;
    private array $config;
    private array $node;
    private ?NodeRun $node_run;

    public function __construct(
        int $run_id,
        string $node_key,
        array $input,
        array $credentials,
        array $config,
        array $node,
        ?NodeRun $node_run = null
    ) {
        $this->run_id      = $run_id;
        $this->node_key    = $node_key;
        $this->input       = $input;
        $this->credentials = $credentials;
        $this->config      = $config;
        $this->node        = $node;
        $this->node_run    = $node_run;
    }

    /* ---------------------------------------------------------
     * Accessors
     * --------------------------------------------------------- */

    /**
     * The current Run ID.
     */
    public function runId(): int {
        return $this->run_id;
    }

    /**
     * The current node key within the workflow graph.
     */
    public function nodeKey(): string {
        return $this->node_key;
    }

    /**
     * The input data from the parent node (or trigger payload).
     */
    public function input(): array {
        return $this->input;
    }

    /**
     * Get a specific value from input by key (supports dot-notation).
     *
     * @param string $key     Dot-notation key, e.g. 'post.post_title'.
     * @param mixed  $default Default value if key not found.
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        $segments = explode( '.', $key );
        $value    = $this->input;

        foreach ( $segments as $segment ) {
            if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
                $value = $value[ $segment ];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Decrypted connection credentials (empty array if no connection).
     */
    public function credentials(): array {
        return $this->credentials;
    }

    /**
     * The resolved node configuration (variables already substituted).
     */
    public function config(): array {
        return $this->config;
    }

    /**
     * Get a specific config value.
     *
     * @param string $key     Config key.
     * @param mixed  $default Default.
     * @return mixed
     */
    public function configValue( string $key, $default = null ) {
        return $this->config[ $key ] ?? $default;
    }

    /**
     * The full raw node definition from the workflow graph.
     */
    public function node(): array {
        return $this->node;
    }

    /**
     * The NodeRun model instance (if available).
     */
    public function nodeRun(): ?NodeRun {
        return $this->node_run;
    }

    /* ---------------------------------------------------------
     * Logger — writes to node_logs table
     * --------------------------------------------------------- */

    /**
     * Write a log entry for this node execution.
     *
     * @param string $level   Log level: info, warning, error, debug.
     * @param string $message Log message.
     */
    public function log( string $level, string $message ): void {
        if ( $this->node_run ) {
            $this->node_run->log( $level, $message );
        }
    }

    /**
     * Convenience: log info message.
     */
    public function info( string $message ): void {
        $this->log( 'info', $message );
    }

    /**
     * Convenience: log warning message.
     */
    public function warning( string $message ): void {
        $this->log( 'warning', $message );
    }

    /**
     * Convenience: log error message.
     */
    public function error( string $message ): void {
        $this->log( 'error', $message );
    }

    /**
     * Convenience: log debug message.
     */
    public function debug( string $message ): void {
        $this->log( 'debug', $message );
    }
}
