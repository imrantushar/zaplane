<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ActionDefinition (Builder)
 *
 * Fluent builder for defining integration actions with IDE auto-complete
 * and self-documenting structure.
 *
 * Usage:
 * ```php
 * public static function get_actions(): array {
 *     return [
 *         ActionDefinition::make('send_message', 'Send Message')
 *             ->description('Send a message to a Slack channel')
 *             ->field('channel', 'text', 'Channel', required: true, placeholder: '#general')
 *             ->field('text', 'textarea', 'Message', required: true)
 *             ->output(['message_ts' => 'string', 'channel' => 'string'])
 *             ->build(),
 *     ];
 * }
 * ```
 */
class ActionDefinition {

    private string $key;
    private string $label;
    private string $description = '';
    private array $fields = [];
    private array $output_schema = [];

    private function __construct( string $key, string $label ) {
        $this->key   = $key;
        $this->label = $label;
    }

    /**
     * Create a new action definition.
     *
     * @param string $key   Internal identifier (e.g. 'send_message').
     * @param string $label Human-readable label (e.g. 'Send Message').
     * @return self
     */
    public static function make( string $key, string $label ): self {
        return new self( $key, $label );
    }

    /**
     * Set a human-readable description.
     *
     * @param string $description
     * @return self
     */
    public function description( string $description ): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Add a configuration field for the action UI.
     *
     * @param string $key         Field key.
     * @param string $type        Field type: text, select, textarea, number, boolean, expression, multiselect, datetime, password.
     * @param string $label       Human-readable label.
     * @param bool   $required    Whether the field is required.
     * @param array  $options     Static options for select: [['label' => '', 'value' => ''], ...].
     * @param array  $dynamic     Dynamic options: ['integration' => '', 'query' => '', 'select' => []].
     * @param mixed  $default     Default value.
     * @param string $placeholder Placeholder text.
     * @param string $help        Help text.
     * @return self
     */
    public function field(
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

        $this->fields[] = $field;
        return $this;
    }

    /**
     * Declare the output data shape this action produces.
     *
     * @param array $schema Key => type map.
     * @return self
     */
    public function output( array $schema ): self {
        $this->output_schema = $schema;
        return $this;
    }

    /**
     * Build the action definition array.
     *
     * @return array ['key' => string, 'definition' => array]
     */
    public function build(): array {
        $definition = [
            'label' => $this->label,
        ];

        if ( $this->description ) {
            $definition['description'] = $this->description;
        }

        if ( ! empty( $this->fields ) ) {
            $definition['config_schema'] = $this->fields;
        }

        if ( ! empty( $this->output_schema ) ) {
            $definition['output_schema'] = $this->output_schema;
        }

        return [
            'key'        => $this->key,
            'definition' => $definition,
        ];
    }

    /**
     * Build and return as a key => value pair for direct use in get_actions().
     *
     * @return array Single-element associative array: [key => definition].
     */
    public function toArray(): array {
        $built = $this->build();
        return [ $built['key'] => $built['definition'] ];
    }
}
