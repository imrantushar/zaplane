<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait OrderActionsTrait {
    private static function action_create_order(array $config, array $input): array {
        $args = [];
        if (!empty($config['customer_id'])) {
            $args['customer_id'] = (int) $config['customer_id'];
        }
        $order = wc_create_order($args);
        if (is_wp_error($order)) {
            return self::error($order->get_error_message());
        }

        if (!empty($config['currency'])) {
            $order->set_currency($config['currency']);
        }
        if (!empty($config['status'])) {
            $order->set_status(self::normalize_order_status($config['status']));
        }
        if (!empty($config['note'])) {
            $order->add_order_note($config['note'], self::parse_bool($config['is_customer_note'] ?? false));
        }

        $order->save();

        return self::respond([
            'order' => self::build_order_payload($order),
        ]);
    }

private static function action_update_order(array $config, array $input): array {
        $order_id = (int) ($config['order_id'] ?? 0);
        if (!$order_id) {
            return self::error('Order ID is required');
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return self::error('Order not found', ['order_id' => $order_id]);
        }

        $data = self::parse_json_array($config['data'] ?? []);
        if (!empty($data) && method_exists($order, 'set_props')) {
            $order->set_props($data);
        }

        if (!empty($config['status'])) {
            $order->set_status(self::normalize_order_status($config['status']));
        }
        if (!empty($config['customer_id'])) {
            $order->set_customer_id((int) $config['customer_id']);
        }
        if (isset($config['total']) && $config['total'] !== '') {
            $order->set_total((float) $config['total']);
        }
        if (!empty($config['currency'])) {
            $order->set_currency($config['currency']);
        }

        $billing = self::parse_json_array($config['billing'] ?? []);
        if (!empty($billing)) {
            $order->set_address($billing, 'billing');
        }
        $shipping = self::parse_json_array($config['shipping'] ?? []);
        if (!empty($shipping)) {
            $order->set_address($shipping, 'shipping');
        }

        $meta = self::parse_json_array($config['meta'] ?? []);
        foreach ($meta as $key => $value) {
            $order->update_meta_data($key, $value);
        }

        $order->save();

        return self::respond([
            'order' => self::build_order_payload($order),
        ]);
    }

private static function action_update_order_status(array $config, array $input): array {
        $order_id = (int) ($config['order_id'] ?? 0);
        $status = $config['status'] ?? '';
        if (!$order_id || $status === '') {
            return self::error('Order ID and status are required');
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return self::error('Order not found', ['order_id' => $order_id]);
        }

        $order->set_status(self::normalize_order_status($status));
        $order->save();

        return self::respond([
            'order' => self::build_order_payload($order),
        ]);
    }

private static function action_add_or_update_order_meta(array $config, array $input): array {
        $order_id = (int) ($config['order_id'] ?? 0);
        $meta_key = $config['meta_key'] ?? '';
        if (!$order_id || $meta_key === '') {
            return self::error('Order ID and meta key are required');
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return self::error('Order not found', ['order_id' => $order_id]);
        }

        $order->update_meta_data($meta_key, $config['meta_value'] ?? '');
        $order->save();

        return self::respond([
            'order_id' => $order_id,
            'meta_key' => $meta_key,
            'meta_value' => $config['meta_value'] ?? '',
        ]);
    }

private static function action_get_total_orders_count(array $config, array $input): array {
        $status = $config['status'] ?? '';
        $args = [
            'limit' => 1,
            'paginate' => true,
            'status' => $status ? self::normalize_order_status($status) : 'any',
        ];
        $result = self::query_orders($args);

        return self::respond(['count' => $result['total']]);
    }

private static function action_get_refunded_orders(array $config, array $input): array {
        $pagination = self::get_pagination_args($config);
        $result = self::query_orders([
            'limit' => $pagination['limit'],
            'page' => $pagination['page'],
            'status' => 'refunded',
            'paginate' => true,
        ]);

        $items = array_map(function($order) { return self::build_order_payload($order); }, $result['items']);
        return self::respond(['count' => $result['total'], 'items' => $items]);
    }

private static function action_get_orders_all(array $config, array $input): array {
        $pagination = self::get_pagination_args($config);
        $result = self::query_orders([
            'limit' => $pagination['limit'],
            'page' => $pagination['page'],
            'status' => 'any',
            'paginate' => true,
        ]);

        $items = array_map(function($order) { return self::build_order_payload($order); }, $result['items']);
        return self::respond(['count' => $result['total'], 'items' => $items]);
    }

private static function action_get_orders_by_status(array $config, array $input): array {
        $status = $config['status'] ?? '';
        if ($status === '') {
            return self::error('Status is required');
        }
        $pagination = self::get_pagination_args($config);
        $result = self::query_orders([
            'limit' => $pagination['limit'],
            'page' => $pagination['page'],
            'status' => self::normalize_order_status($status),
            'paginate' => true,
        ]);

        $items = array_map(function($order) { return self::build_order_payload($order); }, $result['items']);
        return self::respond(['count' => $result['total'], 'items' => $items]);
    }

private static function action_get_orders_by_billing_email(array $config, array $input): array {
        $billing_email = $config['billing_email'] ?? '';
        if ($billing_email === '') {
            return self::error('Billing email is required');
        }
        $pagination = self::get_pagination_args($config);
        $result = self::query_orders([
            'limit' => $pagination['limit'],
            'page' => $pagination['page'],
            'billing_email' => $billing_email,
            'paginate' => true,
        ]);

        $items = array_map(function($order) { return self::build_order_payload($order); }, $result['items']);
        return self::respond(['count' => $result['total'], 'items' => $items]);
    }

private static function action_get_orders_by_customer_id(array $config, array $input): array {
        $customer_id = (int) ($config['customer_id'] ?? 0);
        if (!$customer_id) {
            return self::error('Customer ID is required');
        }
        $pagination = self::get_pagination_args($config);
        $result = self::query_orders([
            'limit' => $pagination['limit'],
            'page' => $pagination['page'],
            'customer_id' => $customer_id,
            'paginate' => true,
        ]);

        $items = array_map(function($order) { return self::build_order_payload($order); }, $result['items']);
        return self::respond(['count' => $result['total'], 'items' => $items]);
    }

private static function action_get_order_single(array $config, array $input): array {
        $order_id = (int) ($config['order_id'] ?? 0);
        if (!$order_id) {
            return self::error('Order ID is required');
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return self::error('Order not found', ['order_id' => $order_id]);
        }
        return self::respond([
            'order' => self::build_order_payload($order),
        ]);
    }

private static function action_get_customer_total_spent(array $config, array $input): array {
        $customer_id = (int) ($config['customer_id'] ?? 0);
        if (!$customer_id && !empty($config['email'])) {
            $customer_id = self::get_customer_id_by_email($config['email']);
        }
        if (!$customer_id) {
            return self::error('Customer ID or email is required');
        }

        if (function_exists('wc_get_customer_total_spent')) {
            $total = wc_get_customer_total_spent($customer_id);
        } else {
            $result = self::query_orders([
                'customer_id' => $customer_id,
                'status' => 'completed',
                'limit' => -1,
            ]);
            $total = array_sum(array_map(function($order) { return (float) $order->get_total(); }, $result['items']));
        }

        return self::respond([
            'customer_id' => $customer_id,
            'total_spent' => $total,
        ]);
    }

private static function action_get_customer_last_order(array $config, array $input): array {
        $customer_id = (int) ($config['customer_id'] ?? 0);
        if (!$customer_id && !empty($config['email'])) {
            $customer_id = self::get_customer_id_by_email($config['email']);
        }
        if (!$customer_id) {
            return self::error('Customer ID or email is required');
        }

        $result = self::query_orders([
            'customer_id' => $customer_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $order = $result['items'][0] ?? null;
        if (!$order) {
            return self::error('No orders found', ['customer_id' => $customer_id]);
        }

        return self::respond([
            'order' => self::build_order_payload($order),
        ]);
    }

private static function action_add_order_note(array $config, array $input): array {
        $order_id = (int) ($config['order_id'] ?? 0);
        $note = $config['note'] ?? '';
        if (!$order_id || $note === '') {
            return self::error('Order ID and note are required');
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return self::error('Order not found', ['order_id' => $order_id]);
        }

        $is_customer_note = self::parse_bool($config['is_customer_note'] ?? false);
        $note_id = $order->add_order_note($note, $is_customer_note);

        return self::respond([
            'order_id' => $order_id,
            'note_id' => $note_id,
        ]);
    }

}
