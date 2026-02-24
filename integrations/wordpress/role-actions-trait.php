<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait RoleActionsTrait
{
    protected static function action_create_role(array $config): array
    {
        $role_key = sanitize_key($config['role'] ?? '');
        $role = add_role(
            $role_key,
            $config['display_name'] ?? '',
            WordpressHelpers::normalize_caps($config['capabilities'] ?? [])
        );

        return static::success(['role' => WordpressHelpers::format_role_payload($role_key, $role)]);
    }

    protected static function action_delete_role(array $config): array
    {
        return static::success(['deleted' => remove_role($config['role'] ?? '')]);
    }

    protected static function action_add_user_role(array $config): array
    {
        $user = get_userdata($config['user_id'] ?? 0);
        if (!$user) return static::error("User not found");
        $user->add_role($config['role'] ?? '');
        return static::success();
    }

    protected static function action_remove_user_role(array $config): array
    {
        $user = get_userdata($config['user_id'] ?? 0);
        if (!$user) return static::error("User not found");
        $user->remove_role($config['role'] ?? '');
        return static::success();
    }

    protected static function action_update_user_role(array $config): array
    {
        $user = get_userdata($config['user_id'] ?? 0);
        if (!$user) return static::error("User not found");
        $user->set_role($config['role'] ?? '');
        return static::success();
    }

    protected static function action_get_roles(): array
    {
        return static::success(wp_roles()->roles ?? []);
    }

    protected static function action_get_caps(): array
    {
        $roles = wp_roles()->roles ?? [];
        $caps = [];
        foreach ($roles as $role) {
            foreach ($role['capabilities'] ?? [] as $cap => $grant) {
                if ($grant) $caps[$cap] = true;
            }
        }
        return static::success(array_keys($caps));
    }

    protected static function action_get_role_caps(array $config): array
    {
        $role = get_role($config['role'] ?? '');
        return static::success(array_keys($role->capabilities ?? []));
    }

    protected static function action_add_role_caps(array $config): array
    {
        $role = get_role($config['role'] ?? '');
        foreach (WordpressHelpers::normalize_list($config['caps'] ?? []) as $cap) {
            $role->add_cap($cap);
        }
        return static::success();
    }

    protected static function action_remove_role_caps(array $config): array
    {
        $role = get_role($config['role'] ?? '');
        foreach (WordpressHelpers::normalize_list($config['caps'] ?? []) as $cap) {
            $role->remove_cap($cap);
        }
        return static::success();
    }

    protected static function action_get_user_caps(array $config): array
    {
        $user = get_userdata($config['user_id'] ?? 0);
        return static::success(array_keys($user->allcaps ?? []));
    }

    protected static function action_add_user_caps(array $config): array
    {
        $user = get_userdata($config['user_id'] ?? 0);
        foreach (WordpressHelpers::normalize_list($config['caps'] ?? []) as $cap) {
            $user->add_cap($cap);
        }
        return static::success();
    }

    protected static function action_remove_user_caps(array $config): array
    {
        $user = get_userdata($config['user_id'] ?? 0);
        foreach (WordpressHelpers::normalize_list($config['caps'] ?? []) as $cap) {
            $user->remove_cap($cap);
        }
        return static::success();
    }
}
