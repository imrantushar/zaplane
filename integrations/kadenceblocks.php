<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Kadenceblocks extends IntegrationBase {

    public static function get_slug(): string {
        return 'kadenceblocks';
    }

	public static function get_name(): string {
		return 'Kadence Blocks';
	}

	public static function get_icon(): string {
		return 'kadence-block.svg';
	}

    public static function get_triggers(): array {
        return [
            'form_submission' => [
                'label' => 'Form Submission',
                'hook' => 'kadence_blocks_advanced_form_submission'
            ],
        ];
    }

    public static function resolve_trigger(array $node, array $args) {

        switch ($node['event']) {

            case 'form_submission':
                $fields = $args[1] ?? [];
                $result = [];
                foreach ($fields as $field) {
                    $label = $field['label'] ?: ($field['name'] ?? '');
                    $result[$label] = $field['value'] ?? '';
                }
                return $result;
        }

        return false;
    }
}
