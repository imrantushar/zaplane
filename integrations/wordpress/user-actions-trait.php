<?php
namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Traits\ActionResponseTrait;

trait UserActionsTrait
{
    use ActionResponseTrait; // inherit success/error helpers

    protected static function action_create_user(array $config): array
    {
        $user_id = wp_insert_user($config);

        if (is_wp_error($user_id)) {
            return static::error($user_id->get_error_message());
        }

        return static::success(['user_id' => $user_id]);
    }

    protected static function action_update_user(array $config): array
    {
        $config['ID'] = $config['user_id'] ?? 0;
        $updated = wp_update_user($config);

        if (is_wp_error($updated)) {
            return static::error($updated->get_error_message());
        }

        return static::success(['updated' => $updated]);
    }

    protected static function action_delete_user(array $config): array
    {
        $deleted = wp_delete_user($config['user_id'], $config['reassign'] ?? null);

        if (!$deleted) {
            return static::error("Failed to delete user ID {$config['user_id']}");
        }

        return static::success(['deleted_user_id' => $config['user_id']]);
    }

    protected static function action_get_users(array $config): array
    {
        $users = get_users($config);
        return static::success([
            'users' => static::query_users(['users' => $users])
        ]);
    }

    protected static function action_get_users_by_role(array $config): array
    {
        // same behavior, reuse action_get_users
        return static::action_get_users($config);
    }
}
