<?php
namespace Zaplane\Integration\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait OptionActionsTrait
{
    use ActionResponseTrait;

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
