<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Weforms extends IntegrationBase {

    public static function get_slug(): string { return 'weforms'; }

	public static function get_name(): string {
		return 'We Forms';
	}

	public static function get_icon(): string {
		return 'weforms.svg';
	}

    public static function get_triggers(): array {
        return [
            'forms_submission' => [
                'label' => 'Form Submission',
                'hook' => 'weforms_entry_submission'
            ],
        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {

            case 'forms_submission':
                $data = $args[0] ?? [];

                if ( empty( $data ) || empty( $data['form_id'] ) || empty( $data['entry_id'] ) ) {
                    return false;
                }

                return $data;
        }

        return false;
    }
}
