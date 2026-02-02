<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;
use FluentForm\App\Models\Form as FluentFormModel;

class FluentForm extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'fluentform';
    }

    public static function get_name(): string
    {
        return 'Fluent Forms';
    }

    public static function get_triggers(): array
    {
        return [
            'submission_inserted' => [
                'label' => 'Form Submitted (submission_inserted)',
                'hook'  => 'fluentform/submission_inserted',
            ],
        ];
    }


    public static function get_trigger_config_schema(string $trigger): array
    {
        if ($trigger !== 'submission_inserted') {
            return [];
        }

        $options = [
            ['label' => 'Any Form', 'value' => 'any'],
        ];

        if (class_exists('\FluentForm\App\App')) {
            try {
                $forms = FluentFormModel::select(['id', 'title'])
                    ->orderBy('id', 'DESC')
                    ->get();

                foreach ($forms as $form) {
                    $options[] = [
                        'label' => (string) $form->title,
                        'value' => (string) $form->id,
                    ];
                }
            } catch (\Throwable $e) {
                // Keep "Any Form" only if anything fails
            }
        }

        return [
            [
                'key'      => 'form_id',
                'label'    => 'Forms',
                'type'     => 'select',
                'options'  => $options,
                'required' => true,
            ],
        ];
    }

    public static function resolve_form_submit_payload($insertId, $formData, $form)
    {
        if (!class_exists('\FluentForm\App\App')) {
            return false;
        }

        if (!$form || !isset($form->id)) {
            return false;
        }

        $form_id = (int) $form->id;
        if (!$form_id) {
            return false;
        }

        $payload = [
            'entry_id'     => (int) $insertId,
            'form_id'      => $form_id,
            'form_title'   => $form->title ?? '',
            'form_data'    => is_array($formData) ? $formData : (array) $formData,
            'submitted_at' => current_time('mysql'),
            'user_id'      => get_current_user_id(),
        ];

        return $payload;
    }

    protected static function resolve_helper_payload(array $payload): void
    {
        $flows = get_option('wp_contact_form_flows', []);
        if (!is_array($flows)) return;

        foreach ($flows as $flow) {
            if (empty($flow['nodes']) || !is_array($flow['nodes'])) continue;

            $trigger_node = $flow['nodes'][0] ?? null;
            if (!$trigger_node || !is_array($trigger_node)) continue;

            $event = $trigger_node['event'] ?? '';
            if ($event && $event !== 'submission_inserted') {
                continue;
            }

            $config_form_id = $trigger_node['config']['form_id'] ?? 'any';
            if ($config_form_id !== 'any' && (int)$config_form_id !== (int)($payload['form_id'] ?? 0)) {
                continue;
            }

            if (is_callable($trigger_node['callback'] ?? null)) {
                $trigger_node['callback']($payload);
            }
        }
    }


    public static function resolve_trigger(array $node, array $args)
    {
        $event  = $node['event'] ?? ($node['data']['event'] ?? '');
        $config = $node['config'] ?? ($node['data']['config'] ?? []);

        if ($event !== 'submission_inserted') {
            return false;
        }

        $payload = self::resolve_form_submit_payload(
            $args[0] ?? 0,
            $args[1] ?? [],
            $args[2] ?? null
        );

        if (!$payload) {
            return false;
        }

        $config_form_id = $config['form_id'] ?? 'any';
        if ($config_form_id !== 'any' && (int)$config_form_id !== (int)$payload['form_id']) {
            return false;
        }

        self::resolve_helper_payload($payload);

        return $payload;
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
}
