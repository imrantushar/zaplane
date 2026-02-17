<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Elementor extends IntegrationBase {

    public static function get_slug(): string { return 'elementor'; }

    public static function get_triggers(): array {
        return [
            'elementor_pro/forms/new_record' => ['label'=>'Form New Record','hook'=>'elementor_pro/forms/new_record'],
        ];
    }


    public static function resolve_trigger(array $node, array $args) {
        ray($args)


    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}
