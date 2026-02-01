<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Bricks extends IntegrationBase {

    public static function get_slug(): string {
        return 'bricks_builder';
    }

    public static function get_triggers(): array {
        return [
            'bricks_form_submit' => [
                'label' => 'Bricks Form Submitted',
                // OFFICIAL custom action hook from Bricks docs
                'hook'  => 'bricks/form/custom_action',
            ],
        ];
    }

    public static function get_trigger_config_schema( string $trigger ): array {

        $schemas = [
            'bricks_form_submit' => [
                [
                    'key'         => 'form_action',
                    'label'       => 'Form action (optional)',
                    'type'        => 'text',
                    'required'    => false,
                    'placeholder' => 'Action name',
                ],
            ],
        ];

        return $schemas[$trigger] ?? [];
    }

    private static function resolve_form_payload( $form, array $extra = [] ) {

        if ( ! is_object($form) ) {
            return false;
        }

        $settings = method_exists($form, 'get_settings') ? (array) $form->get_settings() : [];
        $fields   = method_exists($form, 'get_fields') ? (array) $form->get_fields() : [];
        $files    = method_exists($form, 'get_uploaded_files') ? (array) $form->get_uploaded_files() : [];

        $form_action = isset($fields['action']) ? (string) $fields['action'] : '';
        $post_id = isset($fields['postId']) ? (int) $fields['postId'] : 0;

        return array_merge([
            'form_action'        => $form_action,
            'post_id'        => $post_id,
            'referrer'       => $fields['referrer'] ?? '',
            'form_fields'    => $fields,
            'form_settings'  => $settings,
            'uploaded_files' => $files,
        ], $extra);
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ?? '' ) {

            case 'bricks_form_submit':

                // bricks/form/custom_action passes ($form)
                $form = $args[0] ?? null;
                if ( ! $form ) return false;

                $payload = self::resolve_form_payload($form);
                if ( ! $payload ) return false;

                $config = $node['config'] ?? [];

                $wanted_form_action = isset($config['form_action']) ? trim((string)$config['form_action']) : '';

                if ( $wanted_form_action !== '' && ($payload['form_action'] ?? '') !== $wanted_form_action ) {
                    return false;
                }

                return $payload;
        }

        return false;
    }

    public static function get_actions(): array {
        return [];
    }

    public static function get_action_config_schema( string $action ): array {
        return [];
    }

    public static function execute_node( array $node, array $input ): array {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }
}
