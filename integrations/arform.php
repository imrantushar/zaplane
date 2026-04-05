<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class ARForm extends IntegrationBase
{

    private const ARFORMS_PLUGIN_INDEX = 'arforms/arforms.php';

    private const ARFORMS_FORM_BUILDER_PLUGIN_INDEX = 'arforms-form-builder/arforms-form-builder.php';

    public static function get_slug(): string
    {
        return 'arform';
    }

    public static function get_name(): string
    {
        return 'AR Form';
    }

    public static function get_icon(): string
    {
        return 'arform.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'submit_form' => [
                'label' => 'Form Submit',
                'hook'  => 'arfliteentryexecute',
            ],

        ];
    }

    public static function get_trigger_config_schema(string $trigger): array
    {
        if ('submit_form' !== $trigger) {
            return [];
        }

        return [
            [
                'key'      => 'form_id',
                'label'    => 'Form',
                'type'     => 'select',
                'dynamic' => [
                    'integration' => 'arform',
                    'query'       => 'forms',
                    'select'      => ['value', 'label'],
                ],
                'required' => true,
            ],
        ];
    }

    private static function resolve_form_payload($form): array
    {
        if (! $form) {
            return [];
        }

        $id    = method_exists($form, 'get_id') ? (int) $form->get_id() : 0;
        $title = method_exists($form, 'get_setting') ? (string) $form->get_setting('title') : '';

        return [
            'id'    => $id,
            'title' => $title,
        ];
    }

    public static function resolve_trigger(array $node, array $args)
    {
        // Check that this is the correct event
        $event = $node['event'] ?? '';
        if ('submit_form' !== $event) {
            return null;
        }

        // Retrieve the configured form ID from the node's configuration
        $config = $node['config'] ?? [];
        $configured_form_id = $config['form_id'] ?? 'any';

        // The hook 'arfliteentryexecute' passes 4 arguments: $params, $arflite_errors, $form, $item_meta_values
        if (count($args) < 4) {
            return null;
        }

        $params = $args[0];
        $arflite_errors = $args[1];
        $form = $args[2];
        $item_meta_values = $args[3];

        // Extract the actual form ID from the $form object
        $actual_form_id = 0;
        if (is_object($form) && method_exists($form, 'get_id')) {
            $actual_form_id = (int) $form->get_id();
        } elseif (is_numeric($form)) {
            $actual_form_id = (int) $form;
        } else {
            // Unable to determine form ID – cannot validate trigger
            return null;
        }

        // Check if the trigger should run for this specific form
        if ($configured_form_id !== 'any' && (int) $configured_form_id !== $actual_form_id) {
            return null;
        }

        // Build the payload that will be passed to the automation workflow
        return [
            'form_id'           => $actual_form_id,
            'params'            => $params,
            'item_meta_values'  => $item_meta_values,
            'arflite_errors'    => $arflite_errors,
        ];
    }

    public static function get_actions(): array
    {
        return [];
    }

    public static function get_action_config_schema(string $action): array
    {

        $schemas = [];

        return $schemas[$action] ?? [];
    }

    public static function execute_node(array $node, array $input): array
    {
        return [
            'port' => 'main',
            'data' => $input
        ];
    }

    public static function get_dynamic_queries(): array
    {
        return [
            'forms' => [self::class, 'query_forms'],
        ];
    }

    public static function query_forms()
    {

        $options = [
            [
                'label' => 'Any Form',
                'value' => 'any',
            ],
        ];

        // Check if ARForms plugin is active
        if (is_plugin_active(self::ARFORMS_FORM_BUILDER_PLUGIN_INDEX) || is_plugin_active(self::ARFORMS_PLUGIN_INDEX)) {
            global $wpdb;
            $forms = $wpdb->get_results($wpdb->prepare("SELECT id,name FROM {$wpdb->prefix}arf_forms WHERE is_template = 0 AND status = 'published'"));

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
