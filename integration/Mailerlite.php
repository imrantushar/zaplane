<?php
namespace Zaplane\Integration;

use Zaplane\Classes\IntegrationBase;

class Mailerlite extends IntegrationBase {

    public static function get_slug(): string { return 'mailerlite'; }

    public static function get_triggers(): array {
        return ['subscriber_added'=>['label'=>'Subscriber Added','hook'=>'mailerlite_webhook']];
    }

    public static function get_actions(): array {
        return ['add_subscriber'=>['label'=>'Add Subscriber']];
    }

    public static function resolve_trigger(array $node, array $args) {
        return ['subscriber'=>$args[0] ?? []];
    }

    public static function execute_node(array $node, array $input): array {
        error_log(print_r('Run Mailer Lite', true));
        if(($node['config']['action'] ?? '') === 'add_subscriber') {
            // call MailerLite API to add subscriber
            error_log(print_r('Run Mailer Lite', true));
        }
        return ['port'=>'main','data'=>$input];
    }
}
