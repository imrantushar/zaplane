<?php
namespace Zaplane\Integrations\Easydigitaldownload;

trait TriggersTrait
{
    use HelperTrait;


    public static function resolve_trigger(array $node, array $args)
    {
        switch ($node['event']) {
            case 'purchase_product':
                return self::payload_with_id('payment_id', $args[0] ?? 0, [
                    'customer_id' => self::extract_id($args[2] ?? null),
                ]);

            case 'payment_status_changed':
                return self::build_payment_status_payload(
                    $args[0] ?? 0,
                    $args[1] ?? '',
                    $args[2] ?? ''
                );

            case 'customer_created':
                return self::payload_with_id('customer_id', $args[0] ?? 0, [
                    'data' => $args[1] ?? [],
                ]);

            case 'customer_updated':
                $updated = (bool) ($args[0] ?? false);
                return self::payload_with_id('customer_id', $args[1] ?? 0, [
                    'updated' => $updated,
                    'data' => $args[2] ?? [],
                ]);

            case 'customer_deleted':
                return self::payload_with_id('customer_id', $args[0] ?? 0);

            case 'discount_created':
                return self::payload_with_id('discount_id', $args[1] ?? ($args[0] ?? 0), [
                    'data' => $args[0] ?? [],
                ]);

            case 'discount_updated':
                return self::payload_with_id('discount_id', $args[1] ?? 0, [
                    'data' => $args[0] ?? [],
                ]);

            case 'discount_deleted':
                return self::payload_with_id('discount_id', $args[0] ?? 0);

            case 'download_created':
                return self::build_download_created_payload(
                    $args[0] ?? 0,
                    $args[1] ?? null,
                    $args[2] ?? null
                );

            case 'download_updated':
                return self::build_download_updated_payload(
                    $args[0] ?? 0,
                    $args[1] ?? null,
                    $args[2] ?? null
                );

            case 'download_deleted':
                return self::build_download_deleted_payload($args[0] ?? 0);

            case 'download_purchased':
                $download_id = self::extract_id($args[0] ?? 0);
                $order_id = self::extract_id($args[1] ?? 0);
                if (!$download_id || !$order_id) return false;
                return [
                    'download_id' => $download_id,
                    'order_id' => $order_id,
                    'download_type' => $args[2] ?? '',
                    'cart_details' => $args[3] ?? [],
                    'cart_index' => $args[4] ?? null,
                ];
        }

        return false;
    }
}
