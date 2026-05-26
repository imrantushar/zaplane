<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Bitform extends IntegrationBase
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
        return 'bit-form-new-icon.svg';
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

    public static function resolve_trigger(array $node, array $args)
    {
        $event = $node['event'] ?? '';
        if ('submit_form' !== $event) {
            return false;
        }

        if (count($args) < 4) {
            return false;
        }

        $formId   = (int) $args[0];
        $entryId  = (int) $args[1];
        $formData = $args[2];
        $files    = $args[3];

        if ($formId === 0) {
            return false;
        }

        $config            = $node['config'] ?? ($node['data']['config'] ?? []);
        $configured_form_id = $config['form_id'] ?? 'any';

        if ($configured_form_id !== 'any' && (int) $configured_form_id !== $formId) {
            return false;
        }

        return [
            'form_id'   => $formId,
            'entry_id'  => $entryId,
            'files'     => $files, // Files array structure depends on BitForm
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
                'label' => 'Any form',
                'value' => 'any',
            ],
        ];

        if (! class_exists('BitCode\BitForm\API\BitForm_Public\BitForm_Public')) {
            return $options;
        }

        foreach (\BitCode\BitForm\API\BitForm_Public\BitForm_Public::getForms() as $form) {
            $options[] = [
                'label' => $form->form_name,
                'value' => (string) $form->id,
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
