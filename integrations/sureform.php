<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Sureform extends IntegrationBase
{


    public static function get_slug(): string
    {
        return 'sureform';
    }

    public static function get_name(): string
    {
        return 'Sure Form';
    }

    public static function get_icon(): string
    {
        return 'sureform.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'submit_form' => [
                'label' => 'Form Submit',
                'hook'  => 'srfm_form_submit',
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
                    'integration' => 'sureform',
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

        if (count($args) < 1 || !is_array($args[0])) {
            return null;
        }

        $payload = $args[0];
        $formId = (int)($payload['form_id'] ?? 0);
        $formData = $payload['data'] ?? [];

        if ($formId === 0 || empty($formData)) {
            return null;
        }

        $config = $node['config'] ?? [];
        $configuredFormId = $config['form_id'] ?? 'any';

        if ($configuredFormId !== 'any' && (int)$configuredFormId !== $formId) {
            return null;
        }

        return [
            'form_id'   => $formId,
            'form_name' => $payload['form_name'] ?? '',
            'form_data' => $formData,
            'message'   => $payload['message'] ?? '',
            'to_emails' => $payload['to_emails'] ?? [],
            'success'   => $payload['success'] ?? false,
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

    public static function query_forms(): array
    {
        if (!is_plugin_active('sureforms/sureforms.php')) {
            return [];
        }

        $options = [
            [
                'label' => 'Any form',
                'value' => 'any',
            ],
        ];

        $forms = get_posts(
            [
                'posts_per_page' => -1,
                'orderby'        => 'name',
                'order'          => 'asc',
                'post_type'      => 'sureforms_form',
                'post_status'    => 'publish',
            ]
        );

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
