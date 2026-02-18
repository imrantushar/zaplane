<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Essentialaddons extends IntegrationBase {

    public static function get_slug(): string { return 'essentialaddons'; }

    public static function get_triggers(): array {
        return [
            'eael/login-register/after-login' => ['label'=>'User Login','hook'=>'eael/login-register/after-login'],
            'eael/login-register/after-insert-user' => ['label'=>'User Registration','hook'=>'eael/login-register/after-insert-user'],
    
        ];
    }


    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'eael/login-register/after-login':
                $user = $args[0] ?? null;
              ray($args);
                return [
                    'user_id' => $user->ID ?? 0,
                    'user_login' => $user->user_login ?? '',
                    'user_email' => $user->user_email ?? '',
                    'display_name' => $user->display_name ?? '',
                ];

            case 'eael/login-register/after-insert-user':
                $user_id = $args[0] ?? 0;

                 ray($args);

                return [
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'display_name' => $user->display_name,
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}