<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Kadenceblocks extends IntegrationBase {

    public static function get_slug(): string { return 'kadenceblocks'; }

	public static function get_name(): string {
		return 'Kadence Block';
	}

	public static function get_icon(): string {
		return 'kadence-block.svg';
	}

    public static function get_triggers(): array {
        return [
            'kadence_blocks_advanced_form_submission' => ['label' => 'Advanced Form Submission',  'hook' => 'kadence_blocks_advanced_form_submission'],
        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'kadence_blocks_advanced_form_submission':
                // $args: ($form_args, $processed_fields, $post_id)
                // $processed_fields is array of ['label','type','value','uniqueID','name',...]
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

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}
