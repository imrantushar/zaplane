<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BaseAction
 *
 * Abstract base class for modular action definitions.
 * Each action lives in its own file with all related code in one place:
 * - Definition (key, label)
 * - Configuration schema
 * - Output schema
 * - Execution logic
 *
 * Usage:
 * ```php
 * // integrations/slack/actions/SendMessage.php
 * namespace Zaplane\Integrations\Slack\Actions;
 *
 * use Zaplane\Framework\Classes\BaseAction;
 *
 * class SendMessage extends BaseAction {
 *     public static function get_label(): string {
 *         return 'Send Message';
 *     }
 *
 *     public static function get_config_schema(): array {
 *         return [
 *             ['key' => 'channel', 'type' => 'text', 'label' => 'Channel', 'required' => true],
 *             ['key' => 'text', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
 *         ];
 *     }
 *
 *     public static function execute(array $config, array $input, array $credentials = []): array {
 *         $token = $credentials['access_token'] ?? '';
 *         // ... API call ...
 *         return [
 *             'port' => 'main',
 *             'data' => array_merge($input, ['message_ts' => $response['ts']]),
 *         ];
 *     }
 * }
 * ```
 */
abstract class BaseAction {

    /**
     * Get the unique identifier for this action.
     *
     * By default, auto-derived from class name:
     * - SendMessage -> send_message
     * - CreatePost -> create_post
     *
     * Override if you need a different key.
     *
     * @return string
     */
    public static function get_key(): string {
        $class = ( new \ReflectionClass( static::class ) )->getShortName();
        return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $class ) );
    }

    /**
     * Get the human-readable label for this action.
     *
     * Displayed in the UI when selecting actions.
     *
     * @return string
     */
    abstract public static function get_label(): string;

    /**
     * Get the action description.
     *
     * Optional. Used for documentation and UI help text.
     *
     * @return string
     */
    public static function get_description(): string {
        return '';
    }

    /**
     * Get the configuration schema for this action.
     *
     * Defines the UI fields shown when configuring this action.
     *
     * Field types: text, textarea, select, multiselect, number, boolean, expression, datetime, password
     *
     * Example:
     * ```php
     * return [
     *     [
     *         'key' => 'channel',
     *         'type' => 'text',
     *         'label' => 'Channel',
     *         'placeholder' => '#general',
     *         'required' => true,
     *     ],
     *     [
     *         'key' => 'text',
     *         'type' => 'textarea',
     *         'label' => 'Message',
     *         'required' => true,
     *     ],
     * ];
     * ```
     *
     * @return array
     */
    abstract public static function get_config_schema(): array;

    /**
     * Get the output schema for this action.
     *
     * Describes what additional data this action produces.
     * Used for variable mapping in the UI.
     *
     * Note: Actions always pass through input data merged with new data.
     *
     * Example:
     * ```php
     * return [
     *     'message_ts' => 'string',
     *     'channel_id' => 'string',
     * ];
     * ```
     *
     * @return array Key => type map
     */
    public static function get_output_schema(): array {
        return [];
    }

    /**
     * Get the output ports for this action.
     *
     * Most actions have a single 'main' port.
     * Conditional actions (like 'if') return ['true', 'false'] for branching.
     *
     * @return array
     */
    public static function get_output_ports(): array {
        return [ 'main' ];
    }

    /**
     * Execute the action.
     *
     * This is where the actual work happens.
     * The framework handles credential injection, variable resolution, and rate limiting.
     *
     * @param array $config      Resolved configuration values from user input
     * @param array $input       Data from previous nodes in the workflow
     * @param array $credentials Decrypted connection credentials (for external APIs)
     * @return array Must return ['port' => 'main', 'data' => [...]]
     *
     * Example:
     * ```php
     * public static function execute(array $config, array $input, array $credentials = []): array {
     *     $token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';
     *     if (empty($token)) {
     *         throw new \Exception('Authentication token is missing');
     *     }
     *
     *     $response = SlackApi::call('chat.postMessage', [
     *         'channel' => $config['channel'],
     *         'text' => $config['text'],
     *     ], $token);
     *
     *     return [
     *         'port' => 'main',
     *         'data' => array_merge($input, [
     *             'message_ts' => $response['ts'] ?? '',
     *             'channel_id' => $response['channel'] ?? '',
     *         ]),
     *     ];
     * }
     * ```
     *
     * For conditional actions:
     * ```php
     * public static function execute(array $config, array $input, array $credentials = []): array {
     *     $condition = $this->evaluateCondition($config, $input);
     *     return [
     *         'port' => $condition ? 'true' : 'false',
     *         'data' => $input,
     *     ];
     * }
     * ```
     */
    abstract public static function execute( array $config, array $input, array $credentials = [] ): array;

    /**
     * Helper: Return a successful response.
     *
     * @param array $input Original input data
     * @param array $data  Additional data to merge
     * @return array
     */
    protected static function success( array $input, array $data = [] ): array {
        return [
            'port' => 'main',
            'data' => array_merge( $input, $data ),
        ];
    }

    /**
     * Helper: Return a branching response (for conditional actions).
     *
     * @param bool  $condition The condition result
     * @param array $input     Original input data
     * @param array $data      Additional data to merge
     * @return array
     */
    protected static function branch( bool $condition, array $input, array $data = [] ): array {
        return [
            'port' => $condition ? 'true' : 'false',
            'data' => array_merge( $input, $data ),
        ];
    }

    /**
     * Build the complete action definition array.
     *
     * Used by IntegrationLoader to compile all action metadata.
     * You typically don't need to override this.
     *
     * @return array
     */
    public static function build(): array {
        $definition = [
            'label' => static::get_label(),
        ];

        $description = static::get_description();
        if ( $description ) {
            $definition['description'] = $description;
        }

        $config_schema = static::get_config_schema();
        if ( ! empty( $config_schema ) ) {
            $definition['config_schema'] = $config_schema;
        }

        $output_schema = static::get_output_schema();
        if ( ! empty( $output_schema ) ) {
            $definition['output_schema'] = $output_schema;
        }

        $ports = static::get_output_ports();
        if ( $ports !== [ 'main' ] ) {
            $definition['output_ports'] = $ports;
        }

        // Mark as modular for the loader
        $definition['_class'] = static::class;

        return $definition;
    }
}
