<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait MediaActionsTrait
{
    use ActionResponseTrait;

    protected static function action_generate_attachment_metadata(array $config): array
    {
        $file = get_attached_file($config['attachment_id'] ?? 0);
        $metadata = wp_generate_attachment_metadata($config['attachment_id'] ?? 0, $file);
        wp_update_attachment_metadata($config['attachment_id'] ?? 0, $metadata);
        return static::success(['attachment_id' => $config['attachment_id'], 'metadata' => $metadata]);
    }

    protected static function action_regenerate_image_sizes(array $config): array
    {
        require_once ABSPATH.'wp-admin/includes/image.php';
        $file = get_attached_file($config['attachment_id'] ?? 0);
        $metadata = wp_generate_attachment_metadata($config['attachment_id'] ?? 0, $file);
        wp_update_attachment_metadata($config['attachment_id'] ?? 0, $metadata);
        return static::success([
            'attachment_id' => $config['attachment_id'],
            'metadata' => $metadata,
            'success' => !empty($metadata)
        ]);
    }
}
