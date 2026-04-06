<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class BitForm extends IntegrationBase
{


    public static function get_slug(): string
    {
        return 'bitform';
    }

    public static function get_name(): string
    {
        return 'Bit Form';
    }

    public static function get_icon(): string
    {
        return 'bitform.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'submit_form' => [
                'label' => 'Form Submit',
                'hook'  => 'bitform_submit_success',
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
                'dynamic'  => [
                    'integration' => 'bitform',
                    'query'       => 'forms',
                    'select'      => ['value', 'label'],
                ],
                'required' => true,
            ],
        ];
    }

    public static function resolve_trigger(array $node, array $args): ?array
    {
        $event = $node['event'] ?? '';
        if ('submit_form' !== $event) {
            return null;
        }

        // Validate required hook arguments (4 parameters: formId, entryId, formData, files)
        if (count($args) < 4) {
            return null;
        }

        $formId   = (int) $args[0];
        $entryId  = (int) $args[1];
        $formData = $args[2];
        $files    = $args[3];

        if ($formId === 0) {
            return null;
        }

        // Check if trigger is configured for a specific form
        $config            = $node['config'] ?? [];
        $configured_form_id = $config['form_id'] ?? 'any';

        if ($configured_form_id !== 'any' && (int) $configured_form_id !== $formId) {
            return null;
        }

        // Build and return the payload
        return [
            'form_id'   => $formId,
            'entry_id'  => $entryId,
            'form_data' => self::sanitizeFormData($formData),
            'files'     => $files, // Files array structure depends on BitForm
        ];
    }

    private static function sanitizeFormData($data)
    {
        if (is_string($data)) {
            return sanitize_text_field($data);
        }
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeFormData'], $data);
        }
        return $data;
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
        // Attempt to retrieve from cache
        $cache_key = self::CACHE_KEY_FORMS;
        $options   = wp_cache_get($cache_key, 'zaplane_integrations');

        if (false !== $options) {
            return $options;
        }

        // Base option: "Any Form"
        $options = [
            [
                'label' => 'Any Form',
                'value' => 'any',
            ],
        ];

        // Check if BitForm is active
        if (self::isBitFormActive()) {
            // Use BitForm's public API to get forms
            if (class_exists('BitCode\\BitForm\\API\\BitForm_Public\\BitForm_Public')) {
                $forms = \BitCode\BitForm\API\BitForm_Public\BitForm_Public::getForms();
                if (!empty($forms) && is_array($forms)) {
                    foreach ($forms as $form) {
                        // Ensure we have stdClass with id and form_name
                        $formId   = isset($form->id) ? (int) $form->id : 0;
                        $formName = isset($form->form_name) ? sanitize_text_field($form->form_name) : 'Untitled';
                        if ($formId) {
                            $options[] = [
                                'label' => esc_html($formName),
                                'value' => $formId,
                            ];
                        }
                    }
                }
            }
        }

        // Store in cache for 1 hour
        wp_cache_set($cache_key, $options, 'zaplane_integrations', self::CACHE_EXPIRATION);

        return $options;
    }

        private static function isBitFormActive(): bool
    {
        return class_exists(self::BITFORM_PLUGIN_CLASS);
    }

    public static function get_output_ports(): array
    {
        return [
            'main' => 'Main output',
        ];
    }
}
