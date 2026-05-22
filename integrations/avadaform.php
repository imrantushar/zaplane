<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Avadaform extends IntegrationBase
{
    /**
     * Unique slug for this integration.
     */
    public static function get_slug(): string
    {
        return 'avadaform';
    }

    /**
     * Display name of the integration.
     */
    public static function get_name(): string
    {
        return 'Avada Form';
    }

    /**
     * Icon file name (assumed to be in assets).
     */
    public static function get_icon(): string
    {
        return 'avadaform.svg';
    }

    /**
     * Define the triggers provided by this integration.
     */
    public static function get_triggers(): array
    {
        return [
            'submit_form' => [
                'label'         => 'Form Submit',
                'hook'          => 'fusion_form_submission_data',
                'priority'      => 10,
                'accepted_args' => 2,
            ],
        ];
    }

    /**
     * Configuration schema for a given trigger.
     */
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

    /**
     * Resolve the trigger when the hook fires.
     *
     * @param array $node The workflow node (contains event and config).
     * @param array $args Arguments passed by the WordPress hook.
     * @return array|null Data to pass into the workflow, or null if not matched.
     */
    public static function resolve_trigger(array $node, array $args): ?array
    {
        if (($node['event'] ?? '') !== 'submit_form') {
            return null;
        }

        // Hook passes: (array $formSubmission, int $formId)
        if (count($args) < 2) {
            return null;
        }

        $formSubmission = $args[0];
        $formId         = absint($args[1]);

        if ($formId === 0 || ! is_array($formSubmission)) {
            return null;
        }

        // Extract submitted form data
        $formData = $formSubmission['data'] ?? [];
        if (! is_array($formData)) {
            $formData = [];
        }

        // Apply form filter from node configuration
        $config            = $node['config'] ?? [];
        $configuredFormId  = $config['form_id'] ?? 'any';

        if ($configuredFormId !== 'any' && absint($configuredFormId) !== $formId) {
            return null;
        }

        // Merge the form ID with the submitted fields
        return array_merge(['form_id' => $formId], $formData);
    }

    /**
     * No actions are provided by this integration.
     */
    public static function get_actions(): array
    {
        return [];
    }

    /**
     * No action configuration schemas.
     */
    public static function get_action_config_schema(string $action): array
    {
        return [];
    }

    /**
     * Execute a node (only used for actions; here it’s a pass-through).
     */
    public static function execute_node(array $node, array $input): array
    {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }

    /**
     * Dynamic queries for select fields (e.g., list of Avada forms).
     */
    public static function get_dynamic_queries(): array
    {
        return [
            'forms' => [self::class, 'query_forms'],
        ];
    }

    /**
     * Query all published Avada (Fusion) forms.
     *
     * @return array List of options with 'label' and 'value'.
     */
    public static function query_forms(): array
    {
        $options = [];

        // Check if Avada (Fusion Builder) is active
        if (! self::is_avada_active()) {
            return $options;
        }

        // Add the "Any form" wildcard option
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

    /**
     * Output ports for the integration (only a main output port).
     */
    public static function get_output_ports(): array
    {
        return [
            'main' => 'Main output',
        ];
    }

    /**
     * Check if Avada / Fusion Builder is available.
     *
     * @return bool
     */
    private static function is_avada_active(): bool
    {
        return class_exists('Fusion_Builder_Form_Helper');
    }
}
