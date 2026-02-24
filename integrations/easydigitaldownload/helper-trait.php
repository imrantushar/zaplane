<?php
namespace Zaplane\Integrations\Easydigitaldownload;

trait HelperTrait
{
    protected static function is_valid_download_post($post): bool
    {
        if (!is_object($post) || !property_exists($post, 'ID')) {
            return false;
        }

        if (!property_exists($post, 'post_type') || $post->post_type !== 'download') {
            return false;
        }

        return true;
    }

    protected static function extract_id($value): int
    {
        if (is_object($value)) {
            if (isset($value->ID)) {
                return (int) $value->ID;
            }
            if (isset($value->id)) {
                return (int) $value->id;
            }
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    protected static function payload_with_id(string $key, $id, array $extra = [])
    {
        $id = self::extract_id($id);
        if (!$id) {
            return false;
        }

        return array_merge([$key => $id], $extra);
    }

    protected static function build_payment_status_payload($payment_id, string $new_status, string $old_status = '')
    {
        if ($new_status === '') {
            return false;
        }

        return self::payload_with_id('payment_id', $payment_id, [
            'new_status' => $new_status,
            'old_status' => $old_status,
        ]);
    }

    protected static function matches_download_post($post, $update, bool $expect_update): bool
    {
        if (!self::is_valid_download_post($post)) {
            return false;
        }

        return (bool) $update === $expect_update;
    }

    protected static function build_download_created_payload($download_id, $post, $update)
    {
        if (!self::matches_download_post($post, $update, false)) {
            return false;
        }

        return self::payload_with_id('download_id', $download_id, [
            'data' => $post,
        ]);
    }

    protected static function build_download_updated_payload($download_id, $post, $update)
    {
        if (!self::matches_download_post($post, $update, true)) {
            return false;
        }

        return self::payload_with_id('download_id', $download_id, [
            'post' => $post,
        ]);
    }

    protected static function build_download_deleted_payload($download_id)
    {
        $payload = self::payload_with_id('download_id', $download_id);
        if (!$payload) {
            return false;
        }

        $post = get_post($payload['download_id']);
        if (!$post || !self::is_valid_download_post($post)) {
            return false;
        }

        $payload['post'] = $post;
        return $payload;
    }
}
