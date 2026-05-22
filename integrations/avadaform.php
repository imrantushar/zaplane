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
                'hook'  => 'fusion_form_submission_data',
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
        if ( ( $node['event'] ?? '' ) !== 'submit_form' ) {
            return null;
        }

        if (count($args) < 2) {
            return null;
        }

        $formSubmission = $args[0];
        $formId         = absint($args[1]);

        if ($formId === 0 || ! is_array($formSubmission)) {
            return null;
        }

        $formData = $formSubmission['data'] ?? [];
        if (! is_array($formData)) {
            $formData = [];
        }

        $config            = $node['config'] ?? [];
        $configuredFormId  = $config['form_id'] ?? 'any';

        if ($configuredFormId !== 'any' && absint($configuredFormId) !== $formId) {
            return null;
        }

        return array_merge(['form_id' => $formId], $formData);
    }

    public static function get_dynamic_queries(): array
    {
        return [
            'forms' => [self::class, 'query_forms'],
        ];
    }

    public static function query_forms(): array
    {
        $options = [];

        if (! self::is_avada_active()) {
            return $options;
        }

        $options[] = [
            'label' => 'Any form',
            'value' => 'any',
        ];

        $forms = get_posts([
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_type'      => 'fusion_form',
            'post_status'    => 'publish',
        ]);

        if (is_array($forms)) {
            foreach ($forms as $form) {
                $options[] = [
                    'label' => $form->post_title ?: '(no title)',
                    'value' => $form->ID,
                ];
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

    private static function is_avada_active(): bool
    {
        return class_exists('Fusion_Builder_Form_Helper');
    }
}
