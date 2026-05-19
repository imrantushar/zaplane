<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class BricksBuilder extends IntegrationBase
{
    /**
     * Check if Bricks theme is active.
     */
    public static function is_plugin_installed(): bool
    {
        return wp_get_theme()->get_template() === 'bricks';
    }

    public static function get_slug(): string
    {
        return 'bricks_builder';
    }

    public static function get_name(): string
    {
        return 'Bricks Builder';
    }

    public static function get_icon(): string
    {
        return 'bricks-builder.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'form_submit' => [
                'label' => 'Form Submit',
                'hook'  => 'bricks/form/custom_action',
                'args'  => 1,
            ],
        ];
    }

    /**
     * No configuration needed – triggers on every Bricks form submission.
     */
    public static function get_trigger_config_schema(string $trigger): array
    {
        return [];
    }

    /**
     * Resolve the trigger and return the form data.
     */
    public static function resolve_trigger(array $node, array $args): ?array
    {
        $event = $node['event'] ?? '';
        if ($event !== 'form_submit') {
            return null;
        }

        $form = $args[0] ?? null;
        if (! is_object($form) || ! method_exists($form, 'get_settings')) {
            return null;
        }

        return [
            'uploaded_files' => method_exists($form, 'get_uploaded_files') ? $form->get_uploaded_files() : [],
            'form_fields'    => method_exists($form, 'get_fields') ? $form->get_fields() : [],
            'form_settings'  => $form->get_settings(),
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

    public static function get_output_ports(): array
    {
        return [
            'main' => 'Main output',
        ];
    }
}
