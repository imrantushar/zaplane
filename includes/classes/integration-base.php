<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class IntegrationBase {

    /* ---------------------------------------------------------
     * Core identity
     * --------------------------------------------------------- */

    abstract public static function get_slug(): string;

    public static function get_name(): string {
        return ucfirst( static::get_slug() );
    }

    public static function get_icon(): string {
        return '';
    }

    /* ---------------------------------------------------------
     * Trigger & Action Definitions
     * --------------------------------------------------------- */

    /**
     * Triggers exposed by this integration
     * [
     *   'publish_post' => [
     *      'label' => 'Post Published',
     *      'hook'  => 'publish_post'
     *   ]
     * ]
     */
    public static function get_triggers(): array {
        return [];
    }

    /**
     * Actions exposed by this integration
     * [
     *   'send_message' => [
     *      'label' => 'Send Message'
     *   ]
     * ]
     */
    public static function get_actions(): array {
        return [];
    }

    /* ---------------------------------------------------------
     * Execution
     * --------------------------------------------------------- */

    /**
     * Trigger resolver
     * Called when WP hook fires
     */
    public static function resolve_trigger( array $node, array $hook_args ) {
        return false;
    }

    /**
     * Execute action / logic node
     */
    public static function execute_node( array $node, array $input ): array {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }

    /* ---------------------------------------------------------
     * Validation & Config
     * --------------------------------------------------------- */

    public static function validate_config( array $config ): bool {
        return true;
    }

    public static function get_config_schema(): array {
        return [];
    }

    /* ---------------------------------------------------------
     * Ports / Branching
     * --------------------------------------------------------- */

    public static function get_output_ports(): array {
        return [ 'main' ];
    }

    /* ---------------------------------------------------------
     * Capabilities
     * --------------------------------------------------------- */

    public static function supports_webhook(): bool {
        return false;
    }

    public static function supports_polling(): bool {
        return false;
    }

    public static function get_rate_limit(): int {
        return 0; // requests per minute, 0 = unlimited
    }


    /**
     * Schema for trigger config UI
     */
    public static function get_trigger_config_schema( string $trigger ): array {
        return [];
    }

    /**
     * Schema for action config UI
     */
    public static function get_action_config_schema( string $action ): array {
        return [];
    }

    /**
     * Dynamic option loaders for UI
     * [
     *   'posts' => callable,
     *   'post_types' => callable
     * ]
     */
    public static function get_dynamic_fields(): array {
        return [];
    }
}
