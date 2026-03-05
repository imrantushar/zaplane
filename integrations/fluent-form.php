<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class FluentForm extends IntegrationBase {

    public static function get_slug(): string {
        return 'fluentform';
    }

    public static function get_triggers(): array {
        return [
            'submission_inserted' => [
                'label' => 'Form Submitted', 
                'hook'  => 'fluentform/submission_inserted'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        if ( in_array( $trigger, ['submission_inserted'], true ) ) {
            $options = [ 
                ['label' => 'Any Form', 'value' => 'any'],
            ];
            if ( function_exists( 'wpFluent' ) ) {
                global $wpdb;
                $forms =   wpFluent()
                    ->table($wpdb->prefix . 'fluentform_forms')
                    ->select('id', 'title')
                    ->where('status', 'published')
                    ->get();


                foreach ( $forms as $form ) {
                    $options[] = [
                        'value' => $form->id,
                        'label' => $form->title,
                    ];
                }
            }

            return [
                [
                    'key'      => 'form_id',
                    'label'    => 'Form action',
                    'type'     => 'select',
                    'options'   => $options,
                    'required' => true,
                ],
            ];
        }
        return [];
    }

    private static function resolve_form_payload( $form ): array {
        return [
            'id'    => $form->id,
            'title' => $form->title,
        ];
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            case 'submission_inserted':
                $entryId  = $args[0] ?? null;
                $formData = $args[1] ?? null;
                $form     = $args[2] ?? null;


                if ( ! $entryId || ! $form ) return false;

                    $config       = $node['data']['config'] ?? [];
                    $requiredForm = $config['form_id'] ?? 'any';

                    if ( $requiredForm !== 'any' && (int) $requiredForm !== (int) $form->id ) {
                        return false;
                    }

                
                return [
                    'success' => true,
                    'entry_id'  => $entryId,
                    'form_data' => $formData,
                    'form' => self::resolve_form_payload( $form ),
                ];
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

        $config = $node['data']['config'] ?? [];

        switch ( $node['data']['event'] ?? '' ) {
        }
        return ['port'=>'main','data'=>$input];
    }
}
