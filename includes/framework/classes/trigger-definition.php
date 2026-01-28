<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TriggerDefinition (Builder)
 *
 * Fluent builder for defining integration triggers with IDE auto-complete
 * and self-documenting structure.
 *
 * Usage:
 * ```php
 * public static function get_triggers(): array {
 *     return [
 *         TriggerDefinition::make('post_published', 'Post Published')
 *             ->hook('publish_post')
 *             ->description('Fires when a post is published')
 *             ->output([
 *                 'post_id'    => 'integer',
 *                 'post_title' => 'string',
 *             ])
 *             ->configField('post_type', 'select', 'Post Type', required: true, dynamic: [
 *                 'integration' => 'wordpress',
 *                 'query'       => 'post_types',
 *                 'select'      => ['name', 'label'],
 *             ])
 *             ->build(),
 *     ];
 * }
 * ```
 */
class TriggerDefinition {

    private string $key;
    private string $label;
    private string $hook = '';
    private string $description = '';
    private array $output_schema = [];
    private array $config_fields = [];

    private function __construct( string $key, string $label ) {
        $this->key   = $key;
        $this->label = $label;
    }

    /**
     * Create a new trigger definition.
     *
     * @param string $key   Internal identifier (e.g. 'post_published').
     * @param string $label Human-readable label (e.g. 'Post Published').
     * @return self
     */
    public static function make( string $key, string $label ): self {
        return new self( $key, $label );
    }

    /**
     * Set the WordPress hook this trigger listens on.
     *
     * @param string $hook WordPress action/filter name.
     * @return self
     */
    public function hook( string $hook ): self {
        $this->hook = $hook;
        return $this;
    }

    /**
     * Set a human-readable description for the trigger.
     *
     * @param string $description
     * @return self
     */
    public function description( string $description ): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Declare the output data shape this trigger produces.
     *
     * Used for documentation and frontend field mapping.
     *
     * @param array $schema Key => type map, e.g. ['post_id' => 'integer', 'title' => 'string'].
     * @return self
     */
    public function output( array $schema ): self {
        $this->output_schema = $schema;
        return $this;
    }

    /**
     * Add a configuration field for the trigger UI.
     *
     * @param string $key       Field key.
     * @param string $type      Field type: text, select, textarea, number, boolean, expression, multiselect, datetime.
     * @param string $label     Human-readable label.
     * @param bool   $required  Whether the field is required.
     * @param array  $options   Static options for select fields: [['label' => '', 'value' => ''], ...].
     * @param array  $dynamic   Dynamic options config: ['integration' => '', 'query' => '', 'select' => []].
     * @param mixed  $default   Default value.
     * @param string $placeholder Placeholder text.
     * @param string $help      Help text.
     * @return self
     */
    public function configField(
        string $key,
        string $type,
        string $label,
        bool $required = false,
        array $options = [],
        array $dynamic = [],
        $default = null,
        string $placeholder = '',
        string $help = ''
    ): self {
        $field = [
            'key'      => $key,
            'type'     => $type,
            'label'    => $label,
            'required' => $required,
        ];

        if ( ! empty( $options ) ) {
            $field['options'] = $options;
        }
        if ( ! empty( $dynamic ) ) {
            $field['dynamic'] = $dynamic;
        }
        if ( $default !== null ) {
            $field['default'] = $default;
        }
        if ( $placeholder !== '' ) {
            $field['placeholder'] = $placeholder;
        }
        if ( $help !== '' ) {
            $field['help'] = $help;
        }

        $this->config_fields[] = $field;
        return $this;
    }

    /**
     * Build the trigger definition array.
     *
     * Returns the key and definition separately so it can be used as:
     *   `TriggerDefinition::make(...)->build()`
     * and collected into the triggers array.
     *
     * @return array Keyed array: ['key' => string, 'definition' => array]
     */
    public function build(): array {
        $definition = [
            'label' => $this->label,
        ];

        if ( $this->hook ) {
            $definition['hook'] = $this->hook;
        }

        if ( $this->description ) {
            $definition['description'] = $this->description;
        }

        if ( ! empty( $this->output_schema ) ) {
            $definition['output_schema'] = $this->output_schema;
        }

        if ( ! empty( $this->config_fields ) ) {
            $definition['config_schema'] = $this->config_fields;
        }

        return [
            'key'        => $this->key,
            'definition' => $definition,
        ];
    }

    /**
     * Build and return as a key => value pair for direct use in get_triggers().
     *
     * Usage:
     * ```php
     * return array_merge(
     *     TriggerDefinition::make('post_published', 'Post Published')
     *         ->hook('publish_post')
     *         ->toArray(),
     *     // ... more triggers
     * );
     * ```
     *
     * @return array Single-element associative array: [key => definition].
     */
    public function toArray(): array {
        $built = $this->build();
        return [ $built['key'] => $built['definition'] ];
    }
}
