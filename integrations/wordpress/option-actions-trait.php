<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait OptionActionsTrait
{
    protected static function action_activate_plugin(array $config): array
    {
        $plugin = $config['plugin'] ?? '';
        $result = activate_plugin($plugin);
        if (is_wp_error($result)) {
            return static::error("Failed to activate plugin {$plugin}: " . $result->get_error_message());
        }

        return static::success([
            'plugin' => $plugin,
            'status' => 'activated',
        ]);
    }

    protected static function action_deactivate_plugin(array $config): array
    {
        $plugin = $config['plugin'] ?? '';
        $result = deactivate_plugins($plugin);
        if (!$result) {
            return static::error("Failed to deactivate plugin {$plugin}");
        }

        return static::success([
            'plugin' => $plugin,
            'status' => 'deactivated',
        ]);
    }

    protected static function action_switch_theme(array $config): array
    {
        $theme = $config['theme'] ?? '';
        $result = switch_theme($theme);
        if (is_wp_error($result) || !$result) {
            return static::error("Failed to switch theme {$theme}");
        }

        return static::success([
            'theme' => $theme,
            'status' => 'switched',
        ]);
    }

    protected static function action_add_plugin_theme_option(array $config): array
    {
        add_option($config['option_name'] ?? '', $config['value'] ?? '');
        return static::success(['option_name' => $config['option_name']]);
    }

    protected static function action_update_option_advanced(array $config): array
    {
        update_option($config['option_name'] ?? '', $config['value'] ?? '');
        return static::success();
    }

    protected static function action_delete_option(array $config): array
    {
        $result = delete_option($config['option_name'] ?? '');
        if (!$result) return static::error("Failed to delete option");
        return static::success(['option_name' => $config['option_name']]);
    }
}
