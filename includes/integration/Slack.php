<?php
namespace Zaplane\Integration;

use Zaplane\Classes\IntegrationBase;

class Slack extends IntegrationBase {

    public static function get_slug(): string { return 'slack'; }

    public static function get_triggers(): array {
        return [
            'message_received' => ['label'=>'Message Received','hook'=>'slack_webhook_message'],
        ];
    }

    public static function get_actions(): array {
        return [
            'send_message' => ['label'=>'Send Message'],
        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        return ['message'=>$args[0] ?? ''];
    }

    public static function execute_node(array $node, array $input): array {
        if(($action = $node['config']['action'] ?? '') === 'send_message') {
            $text = $node['config']['data']['text'] ?? '';
            // send message via Slack API
        }
        return ['port'=>'main','data'=>$input];
    }
}
