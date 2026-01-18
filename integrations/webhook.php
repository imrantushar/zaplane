<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

class Webhook extends IntegrationBase {

    public static function get_slug(): string {
        return 'webhook';
    }

    public static function get_name(): string {
        return 'Webhook';
    }

    public static function supports_webhook(): bool {
        return true;
    }

    public static function get_triggers(): array {
        return [
            'incoming' => ['label'=>'Incoming Webhook']
        ];
    }

    public static function get_trigger_config_schema(string $trigger): array {
        return [
            [
                'key'=>'path',
                'label'=>'Webhook Path',
                'type'=>'text',
                'required'=>true,
                'help'=>'Example: order-created'
            ]
        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        return $args[0] ?? [];
    }
}
