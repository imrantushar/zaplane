<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Avadaform extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'avadaform';
    }

    public static function get_name(): string
    {
        return 'Avada Form';
    }

    public static function get_icon(): string
    {
        return 'avadaform.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'submit_form' => [
                'label' => 'Form Submit',
                'hook'  => 'fusion_form_submission', // Adjust to actual hook name if different
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
                    'integration' => 'avadaform',
                    'query'       => 'forms',
                    'select'      => ['value', 'label'],
                ],
                'required' => true,
            ],
        ];
    }

    public static function resolve_trigger(array $node, array $args): ?array
    {
        if (($node['event'] ?? '') !== 'submit_form') {
            return null;
        }

        // The hook passes two arguments: $formSubmission (array) and $formId (int)
        if (count($args) < 2) {
            return null;
        }

        $formSubmission = $args[0]; // array with 'data' key and maybe others
        $formId         = (int) $args[1];

        if ($formId === 0) {
            return null;
        }

        $formData = $formSubmission['data'] ?? [];

        if (empty($formData)) {
            return null;
        }

        $config = $node['config'] ?? [];
        $configuredFormId = $config['form_id'] ?? 'any';

        if ($configuredFormId !== 'any' && (int) $configuredFormId !== $formId) {
            return null;
        }

        return [
            'form_id'    => $formId,
            'form_data'  => $formData,
            'raw_payload' => $formSubmission, // optional, for advanced use
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
        // Check if Avada (Fusion Builder) is active and forms exist
        if (! class_exists('Fusion_Builder_Form_Helper')) {
            return [];
        }

        $options = [
            [
                'label' => 'Any form',
                'value' => 'any',
            ],
        ];

        $forms = get_posts([
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_type'      => 'fusion_form',
            'post_status'    => 'publish',
        ]);

        foreach ($forms as $form) {
            $options[] = [
                'label' => $form->post_title,
                'value' => $form->ID,
            ];
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
