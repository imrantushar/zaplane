<?php
namespace Zaplane\Integration;

use Zaplane\Classes\IntegrationBase;

class Gmail extends IntegrationBase {

    public static function get_slug(): string { return 'gmail'; }

    public static function get_triggers(): array {
        return ['new_email'=>['label'=>'New Email','hook'=>'gmail_new_email']];
    }

    public static function get_actions(): array {
        return ['send_email'=>['label'=>'Send Email']];
    }

    public static function resolve_trigger(array $node, array $args) {
        return ['email'=>$args[0] ?? ''];
    }

    public static function execute_node(array $node, array $input): array {
        if(($node['config']['action'] ?? '') === 'send_email') {
            // send email via Gmail API
        }
        return ['port'=>'main','data'=>$input];
    }
}
