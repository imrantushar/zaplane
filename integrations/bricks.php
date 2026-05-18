<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Bricks extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'bricks';
    }

    public static function get_name(): string
    {
        return 'Bricks';
    }

    public static function get_icon(): string
    {
        return 'bricks.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'bricks_submit_form' => [
                'label' => 'Bricks Form Submitted',
                'hook'  => 'bricks/form/custom_action',
                'args'  => 4,
            ],
        ];
    }

    public static function get_trigger_config_schema(string $trigger): array
    {
        if (! in_array($trigger, ['bricks_submit_form'], true)) {
            return [];
        }

        return [
            [
                'key'         => 'form_action',
                'label'       => 'Filter by Custom Action Name (leave empty for all)',
                'type'        => 'text',
                'required'    => false,
                'placeholder' => 'e.g. my_custom_action',
            ],
        ];
    }

    public static function resolve_trigger(array $node, array $args)
    {
        // Check that this is the correct event
        $event = $node['event'] ?? '';
        if (! in_array($event, ['bricks_submit_form'], true)) {
            return null;
        }

        // Retrieve the configured form ID from the node's configuration
        $config             = $node['config'] ?? [];
        $configured_form_id = $config['form_id'] ?? 'any';

        // The hook passes 4 arguments: $params, $arflite_errors, $form, $item_meta_values
        if (count($args) < 4) {
            return null;
        }

        $params           = $args[0];
        $arflite_errors   = $args[1];
        $form             = $args[2];
        $item_meta_values = $args[3];

        // Extract the actual form ID — use ->id directly (matches ARForms internals)
        $actual_form_id = 0;
        if (is_object($form) && isset($form->id)) {
            $actual_form_id = (int) $form->id;
        } elseif (is_object($form) && method_exists($form, 'get_id')) {
            $actual_form_id = (int) $form->get_id();
        } elseif (is_numeric($form)) {
            $actual_form_id = (int) $form;
        } else {
            return null;
        }

        if (0 === $actual_form_id) {
            return null;
        }

        // Check if the trigger should run for this specific form
        if ($configured_form_id !== 'any' && (int) $configured_form_id !== $actual_form_id) {
            return null;
        }

        // Build the payload
        return [
            'form_id'          => $actual_form_id,
            'params'           => $params,
            'item_meta_values' => $item_meta_values,
            'arflite_errors'   => $arflite_errors,
        ];
    }

    public static function get_actions(): array
    {
        return [];
    }

    public static function get_action_config_schema(string $action): array
    {
        return [];
    }

    public static function execute_node(array $node, array $input): array
    {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }

    public static function get_dynamic_queries(): array
    {
        return [
            'forms' => [self::class, 'query_forms'],
        ];
    }

    public static function query_forms(): array
    {
        $options = [
            [
                'label' => 'Any Form',
                'value' => 'any',
            ],
        ];

        if (
            is_plugin_active(self::ARFORMS_FORM_BUILDER_PLUGIN_INDEX) ||
            is_plugin_active(self::ARFORMS_PLUGIN_INDEX)
        ) {
            global $wpdb;
            $forms = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, name FROM {$wpdb->prefix}arf_forms WHERE is_template = 0 AND status = 'published'"
                )
            );

            if (! empty($forms)) {
                foreach ($forms as $form) {
                    $options[] = [
                        'label' => $form->name,
                        'value' => $form->id,
                    ];
                }
            }
        }

        return $options;
    }

    public static function get_output_ports(): array
    {
        return [
            'main' => 'Main output',
        ];
    }
}
