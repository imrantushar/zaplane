<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DataMapper
 *
 * Centralised variable substitution for workflow node configuration.
 * Replaces {{key}} and {{key.nested}} placeholders with values from
 * the execution input data.
 */
class DataMapper {

    /**
     * Recursively resolve variables in a value.
     *
     * - Strings are processed for {{…}} placeholders.
     * - Arrays are walked recursively so every leaf string is resolved.
     * - Other scalar types pass through untouched.
     *
     * @param mixed $value  The value (string, array, or scalar) to resolve.
     * @param array $data   The data context (usually the node input).
     * @return mixed
     */
    public static function resolve( $value, array $data ) {
        if ( is_string( $value ) ) {
            return self::substitute( $value, $data );
        }

        if ( is_array( $value ) ) {
            $resolved = [];
            foreach ( $value as $k => $v ) {
                $resolved[ $k ] = self::resolve( $v, $data );
            }
            return $resolved;
        }

        return $value;
    }

    /**
     * Replace {{placeholder}} tokens in a string.
     *
     * Supports dot-notation for nested access:
     *   {{post.post_title}}  →  $data['post']['post_title']
     *
     * Tokens that cannot be resolved are left as-is so the user can see
     * which variables were unavailable.
     *
     * @param string $text  The template string.
     * @param array  $data  The data context.
     * @return string
     */
    public static function substitute( string $text, array $data ): string {
        return preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function ( $matches ) use ( $data ) {
                $key  = trim( $matches[1] );
                $keys = explode( '.', $key );

                $value = $data;
                foreach ( $keys as $segment ) {
                    if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
                        $value = $value[ $segment ];
                    } else {
                        return $matches[0]; // unresolved — keep original token
                    }
                }

                return is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
            },
            $text
        );
    }

    /**
     * Resolve all config values for a node before execution.
     *
     * Walks `$node['config']['data']` (or `$node['data']['config']`) and
     * replaces every {{…}} token using the provided $input.
     *
     * Returns a new node array with resolved values — the original is not mutated.
     *
     * @param array $node  The full node definition from the workflow graph.
     * @param array $input The runtime input data.
     * @return array       The node with config values resolved.
     */
    public static function resolve_node_config( array $node, array $input ): array {
        // External app integrations store config at node.config.data
        if ( isset( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
            $node['config']['data'] = self::resolve( $node['config']['data'], $input );
        }

        // WordPress integrations store config at node.data.config
        if ( isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
            $node['data']['config'] = self::resolve( $node['data']['config'], $input );
        }

        return $node;
    }
}
