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
                // Args: [0] = user_login (string), [1] = WP_User object, [2] = settings
                
                $user = $args[1] ?? null;
                if (!$user instanceof \WP_User) return false;
                
                return [
                    'user_id' => $user->ID ?? '',
                    'user_login' => $user->user_login ?? '',
                    'user_email' => $user->user_email ?? '',
                    'display_name' => $user->display_name ?? '',
                ];

            case 'eael/login-register/after-insert-user':
                // Args: [0] = user_id, [1] = user_data array, [2] = settings
                $user_id = $args[0] ?? 0;
                if (!$user_id) return false;
                
                $user = get_user_by('id', $user_id);
                if (!$user) return false;
                
                return [
                    'user_id' => $user->ID ?? '',
                    'user_login' => $user->user_login ?? '',
                    'user_email' => $user->user_email ?? '',
                    'display_name' => $user->display_name ?? '',
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}