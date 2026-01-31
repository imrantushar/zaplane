<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BaseTrigger
 *
 * Abstract base class for modular trigger definitions.
 * Each trigger lives in its own file with all related code in one place:
 * - Definition (key, label, hook)
 * - Configuration schema
 * - Output schema
 * - Resolution logic
 *
 * Usage:
 * ```php
 * // integrations/wordpress/triggers/PublishPost.php
 * namespace Zaplane\Integrations\WordPress\Triggers;
 *
 * use Zaplane\Framework\Classes\BaseTrigger;
 *
 * class PublishPost extends BaseTrigger {
 *     public static function get_label(): string {
 *         return 'Post Published';
 *     }
 *
 *     public static function get_hook(): string {
 *         return 'publish_post';
 *     }
 *
 *     public static function get_config_schema(): array {
 *         return [
 *             ['key' => 'post_type', 'type' => 'select', 'label' => 'Post Type', 'required' => true],
 *         ];
 *     }
 *
 *     public static function resolve(array $node, array $hook_args) {
 *         $post_id = $hook_args[0] ?? 0;
 *         $post = get_post($post_id);
 *         if (!$post) return false;
 *
 *         return [
 *             'post_id' => $post->ID,
 *             'post_title' => $post->post_title,
 *         ];
 *     }
 * }
 * ```
 */
abstract class BaseTrigger {

    /**
     * Get the unique identifier for this trigger.
     *
     * By default, auto-derived from class name:
     * - PublishPost -> publish_post
     * - UserRegistered -> user_registered
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
     * Get the human-readable label for this trigger.
     *
     * Displayed in the UI when selecting triggers.
     *
     * @return string
     */
    abstract public static function get_label(): string;

    /**
     * Get the WordPress hook this trigger listens to.
     *
     * This is the action/filter name that WordPress fires.
     * Examples: 'publish_post', 'user_register', 'woocommerce_order_status_completed'
     *
     * @return string
     */
    abstract public static function get_hook(): string;

    /**
     * Get the trigger description.
     *
     * Optional. Used for documentation and UI help text.
     *
     * @return string
     */
    public static function get_description(): string {
        return '';
    }

    /**
     * Get the configuration schema for this trigger.
     *
     * Defines the UI fields shown when configuring this trigger.
     *
     * Field types: text, textarea, select, multiselect, number, boolean, expression, datetime, password
     *
     * Example:
     * ```php
     * return [
     *     [
     *         'key' => 'post_type',
     *         'type' => 'select',
     *         'label' => 'Post Type',
     *         'required' => true,
     *         'dynamic' => [
     *             'integration' => 'wordpress',
     *             'query' => 'post_types',
     *             'select' => ['name', 'label'],
     *         ],
     *     ],
     *     [
     *         'key' => 'post_status',
     *         'type' => 'select',
     *         'label' => 'Post Status',
     *         'options' => [
     *             ['label' => 'Published', 'value' => 'publish'],
     *             ['label' => 'Draft', 'value' => 'draft'],
     *         ],
     *     ],
     * ];
     * ```
     *
     * @return array
     */
    public static function get_config_schema(): array {
        return [];
    }

    /**
     * Get the output schema for this trigger.
     *
     * Describes what data this trigger produces.
     * Used for variable mapping in the UI.
     *
     * Example:
     * ```php
     * return [
     *     'post_id' => 'integer',
     *     'post_title' => 'string',
     *     'post_content' => 'string',
     *     'author_id' => 'integer',
     * ];
     * ```
     *
     * @return array Key => type map
     */
    public static function get_output_schema(): array {
        return [];
    }

    /**
     * Check if this trigger matches the given node config and hook arguments.
     *
     * Called before resolve() to filter triggers based on configuration.
     * Return false to skip this trigger for the current event.
     *
     * Example: Only match if post_type in config matches the actual post type:
     * ```php
     * public static function matches(array $node, array $hook_args): bool {
     *     $config = $node['data']['config'] ?? [];
     *     $post = get_post($hook_args[0] ?? 0);
     *
     *     if (!$post) return false;
     *
     *     if (!empty($config['post_type']) && $post->post_type !== $config['post_type']) {
     *         return false;
     *     }
     *
     *     return true;
     * }
     * ```
     *
     * @param array $node      The trigger node configuration
     * @param array $hook_args Arguments passed to the WordPress hook
     * @return bool
     */
    public static function matches( array $node, array $hook_args ): bool {
        return true;
    }

    /**
     * Resolve the trigger payload from WordPress hook arguments.
     *
     * Called when the WordPress hook fires to extract meaningful data.
     * Return false to skip (trigger doesn't apply).
     * Return array to continue with the workflow.
     *
     * The returned array becomes the initial data available to all nodes.
     *
     * Example:
     * ```php
     * public static function resolve(array $node, array $hook_args) {
     *     $post_id = $hook_args[0] ?? 0;
     *     $post = get_post($post_id);
     *
     *     if (!$post) return false;
     *
     *     return [
     *         'post_id' => $post->ID,
     *         'post_title' => $post->post_title,
     *         'post_content' => $post->post_content,
     *         'post_type' => $post->post_type,
     *         'post_status' => $post->post_status,
     *         'author_id' => $post->post_author,
     *     ];
     * }
     * ```
     *
     * @param array $node      The trigger node configuration
     * @param array $hook_args Arguments passed to the WordPress hook
     * @return array|false Payload data or false to skip
     */
    abstract public static function resolve( array $node, array $hook_args );

    /**
     * Build the complete trigger definition array.
     *
     * Used by IntegrationLoader to compile all trigger metadata.
     * You typically don't need to override this.
     *
     * @return array
     */
    public static function build(): array {
        $definition = [
            'label' => static::get_label(),
            'hook'  => static::get_hook(),
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

        // Mark as modular for the loader
        $definition['_class'] = static::class;

        return $definition;
    }
}
