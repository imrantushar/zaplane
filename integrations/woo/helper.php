<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Helper {
    protected const ORDER_STATUS_EVENTS = [
        'order_status_pending',
        'order_status_failed',
        'order_status_on_hold',
        'order_status_processing',
        'order_status_completed',
        'order_status_refunded',
        'order_status_cancelled',
    ];
    protected static function build_order_payload(\WC_Order $order, array $extra = []): array {
        return array_merge([
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_id' => $order->get_customer_id(),
        ], $extra);
    }

    protected static function build_product_payload(\WC_Product $product, array $extra = []): array {
        return array_merge([
            'product_id' => $product->get_id(),
            'name' => $product->get_name(),
            'status' => $product->get_status(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'type' => $product->get_type(),
        ], $extra);
    }

    protected static function build_coupon_payload(\WC_Coupon $coupon, array $extra = []): array {
        return array_merge([
            'coupon_id' => $coupon->get_id(),
            'code' => $coupon->get_code(),
            'amount' => $coupon->get_amount(),
            'discount_type' => $coupon->get_discount_type(),
        ], $extra);
    }

    protected static function build_customer_payload(\WC_Customer $customer, array $extra = []): array {
        return array_merge([
            'customer_id' => $customer->get_id(),
            'email' => $customer->get_email(),
            'username' => $customer->get_username(),
        ], $extra);
    }

    protected static function get_order_from_args(array $args, int $id_index = 0, int $object_index = 1): ?\WC_Order {
        $order = $args[$object_index] ?? null;
        if ($order instanceof \WC_Order) return $order;

        $order_id = $args[$id_index] ?? 0;
        return $order_id ? wc_get_order($order_id) : null;
    }

    protected static function order_payload_from_args(array $args, array $extra = [], int $id_index = 0, int $object_index = 1): ?array {
        $order = self::get_order_from_args($args, $id_index, $object_index);
        return $order ? self::build_order_payload($order, $extra) : null;
    }

    protected static function order_status_payload_from_args(array $args): ?array {
        $order = self::get_order_from_args($args);
        if (!$order) return null;
        $transition = $args[2] ?? [];
        $old_status = is_array($transition) ? ($transition['from'] ?? '') : '';
        $new_status = is_array($transition) ? ($transition['to'] ?? $order->get_status()) : $order->get_status();
        return self::build_order_payload($order, [
            'old_status' => $old_status,
            'new_status' => $new_status,
        ]);
    }

    protected static function get_product_from_args(array $args, int $id_index = 0, int $object_index = 1): ?\WC_Product {
        $product = $args[$object_index] ?? null;
        if ($product instanceof \WC_Product) return $product;

        $product_id = $args[$id_index] ?? 0;
        return $product_id ? wc_get_product($product_id) : null;
    }

    protected static function product_payload_from_args(array $args, array $extra = [], int $id_index = 0, int $object_index = 1): ?array {
        $product = self::get_product_from_args($args, $id_index, $object_index);
        return $product ? self::build_product_payload($product, $extra) : null;
    }

    protected static function get_product_from_post($post_ref): ?\WC_Product {
        $post = is_numeric($post_ref) ? get_post((int) $post_ref) : $post_ref;
        if (!$post || ($post->post_type ?? '') !== 'product') return null;

        return wc_get_product($post->ID) ?: null;
    }

    protected static function product_payload_from_post($post_ref, array $extra = []): ?array {
        $product = self::get_product_from_post($post_ref);
        return $product ? self::build_product_payload($product, $extra) : null;
    }

    protected static function get_coupon_from_args(array $args): ?\WC_Coupon {
        $coupon = $args[1] ?? null;
        if ($coupon instanceof \WC_Coupon) return $coupon;

        $coupon_id = $args[0] ?? 0;
        if (!$coupon_id) return null;

        $coupon = new \WC_Coupon($coupon_id);
        return $coupon->get_id() ? $coupon : null;
    }

    protected static function coupon_payload_from_args(array $args, array $extra = []): ?array {
        $coupon = self::get_coupon_from_args($args);
        return $coupon ? self::build_coupon_payload($coupon, $extra) : null;
    }

    protected static function get_customer_from_args(array $args, int $id_index = 0, int $object_index = 1): ?\WC_Customer {
        $customer = $args[$object_index] ?? null;
        if ($customer instanceof \WC_Customer) return $customer;

        $customer_id = $args[$id_index] ?? 0;
        if (!$customer_id) return null;

        $customer = new \WC_Customer($customer_id);
        return $customer->get_id() ? $customer : null;
    }

    protected static function customer_payload_from_args(array $args, array $extra = [], int $id_index = 0, int $object_index = 1): ?array {
        $customer = self::get_customer_from_args($args, $id_index, $object_index);
        return $customer ? self::build_customer_payload($customer, $extra) : null;
    }

    protected static function build_cart_add_payload(array $args): array {
        return [
            'cart_item_key' => $args[0] ?? '',
            'product_id' => $args[1] ?? 0,
            'quantity' => $args[2] ?? 0,
            'variation_id' => $args[3] ?? 0,
        ];
    }

    protected static function build_cart_item_payload(array $args): array {
        $cart_item_key = $args[0] ?? '';
        $cart = $args[1] ?? null;
        $cart_item = $cart instanceof \WC_Cart
            ? ($cart->removed_cart_contents[$cart_item_key] ?? $cart->cart_contents[$cart_item_key] ?? null)
            : null;
        if (!$cart_item) return ['cart_item_key' => $cart_item_key];

        return [
            'cart_item_key' => $cart_item_key,
            'product_id' => $cart_item['product_id'] ?? 0,
            'quantity' => $cart_item['quantity'] ?? 0,
            'variation_id' => $cart_item['variation_id'] ?? 0,
        ];
    }

    protected static function respond(array $data = [], string $port = 'main'): array {
        return ['port' => $port, 'data' => $data];
    }

    protected static function error(string $message, array $data = []): array {
        return self::respond(array_merge(['error' => $message], $data), 'error');
    }

    protected static function get_node_action(array $node): string {
        return $node['data']['event'] ?? $node['config']['action'] ?? $node['data']['action'] ?? '';
    }

    protected static function get_node_config(array $node): array {
        if (!empty($node['data']['config']) && is_array($node['data']['config'])) {
            return $node['data']['config'];
        }
        if (!empty($node['config']['data']) && is_array($node['config']['data'])) {
            return $node['config']['data'];
        }
        if (!empty($node['config']) && is_array($node['config'])) {
            return $node['config'];
        }
        return [];
    }

    protected static function get_customer_id_by_email(string $email): int {
        if ($email === '') {
            return 0;
        }
        if (function_exists('wc_get_customer_id_by_email')) {
            return (int) wc_get_customer_id_by_email($email);
        }
        $user = get_user_by('email', $email);
        return $user ? (int) $user->ID : 0;
    }

    protected static function parse_bool($value, bool $default = false): bool {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return $default;
        }
        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $result === null ? $default : $result;
    }

    protected static function parse_json_array($value): array {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected static function parse_list($value): array {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return [];
        }
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }

    protected static function normalize_order_status(string $status): string {
        $status = sanitize_key($status);
        if (strpos($status, 'wc-') === 0) {
            return substr($status, 3);
        }
        return $status;
    }

    protected static function get_pagination_args(array $config, int $default_limit = 20): array {
        $limit = isset($config['limit']) ? (int) $config['limit'] : $default_limit;
        if ($limit <= 0) {
            $limit = $default_limit;
        }
        $page = isset($config['page']) ? max(1, (int) $config['page']) : 1;
        return ['limit' => $limit, 'page' => $page];
    }

    protected static function query_orders(array $args): array {
        $result = wc_get_orders($args);
        if (is_wp_error($result)) {
            return ['items' => [], 'total' => 0];
        }
        if (is_object($result) && isset($result->orders)) {
            return [
                'items' => $result->orders ?? [],
                'total' => (int) ($result->total ?? count($result->orders ?? [])),
            ];
        }
        if (is_array($result) && isset($result['orders'])) {
            return [
                'items' => $result['orders'] ?? [],
                'total' => (int) ($result['total'] ?? count($result['orders'] ?? [])),
            ];
        }
        $items = is_array($result) ? $result : [];
        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    protected static function query_products(array $args): array {
        $result = wc_get_products($args);
        if (is_wp_error($result)) {
            return ['items' => [], 'total' => 0];
        }
        if (is_object($result) && isset($result->products)) {
            return [
                'items' => $result->products ?? [],
                'total' => (int) ($result->total ?? count($result->products ?? [])),
            ];
        }
        if (is_array($result) && isset($result['products'])) {
            return [
                'items' => $result['products'] ?? [],
                'total' => (int) ($result['total'] ?? count($result['products'] ?? [])),
            ];
        }
        $items = is_array($result) ? $result : [];
        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    protected static function query_customers(array $args): array {
        if (function_exists('wc_get_customers')) {
            $result = wc_get_customers($args);
            return [
                'items' => $result ?: [],
                'total' => is_array($result) ? count($result) : 0,
            ];
        }
        return ['items' => [], 'total' => 0];
    }

    protected static function query_coupons(array $args): array {
        if (function_exists('wc_get_coupons')) {
            $result = wc_get_coupons($args);
            return [
                'items' => $result ?: [],
                'total' => is_array($result) ? count($result) : 0,
            ];
        }
        return ['items' => [], 'total' => 0];
    }

    protected static function query_reviews(array $args): array {
        $query = new \WP_Comment_Query($args);
        $items = $query->comments ?: [];
        $total = (int) ($query->found_comments ?? count($items));
        return ['items' => $items, 'total' => $total];
    }

    protected static function build_term_payload($term): array {
        if ($term instanceof \WP_Term) {
            return [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'parent' => $term->parent,
                'count' => $term->count,
                'taxonomy' => $term->taxonomy,
            ];
        }
        return [];
    }

    protected static function product_status_options(): array {
        return [
            ['label'=>'Publish','value'=>'publish'],
            ['label'=>'Draft','value'=>'draft'],
            ['label'=>'Pending','value'=>'pending'],
            ['label'=>'Private','value'=>'private'],
        ];
    }

    protected static function order_status_options(): array {
        return [
            ['label'=>'Pending','value'=>'pending'],
            ['label'=>'Processing','value'=>'processing'],
            ['label'=>'On-hold','value'=>'on-hold'],
            ['label'=>'Completed','value'=>'completed'],
            ['label'=>'Cancelled','value'=>'cancelled'],
            ['label'=>'Refunded','value'=>'refunded'],
            ['label'=>'Failed','value'=>'failed'],
        ];
    }

    protected static function stock_status_options(): array {
        return [
            ['label'=>'In Stock','value'=>'instock'],
            ['label'=>'Out of Stock','value'=>'outofstock'],
            ['label'=>'On Backorder','value'=>'onbackorder'],
        ];
    }

    protected static function coupon_type_options(): array {
        return [
            ['label'=>'Percentage','value'=>'percent'],
            ['label'=>'Fixed Cart','value'=>'fixed_cart'],
            ['label'=>'Fixed Product','value'=>'fixed_product'],
        ];
    }

    protected static function field_order_id(bool $required = true): array {
        return [[
            'key' => 'order_id',
            'label' => 'Order ID',
            'type' => 'expression',
            'required' => $required,
        ]];
    }

    protected static function field_customer_id(bool $required = true): array {
        return [[
            'key' => 'customer_id',
            'label' => 'Customer ID',
            'type' => 'expression',
            'required' => $required,
        ]];
    }

    protected static function field_product_id(bool $required = true): array {
        return [[
            'key' => 'product_id',
            'label' => 'Product ID',
            'type' => 'expression',
            'required' => $required,
        ]];
    }

    protected static function field_coupon_identifier(): array {
        return [
            ['key'=>'coupon_id','label'=>'Coupon ID','type'=>'expression'],
            ['key'=>'code','label'=>'Coupon Code','type'=>'text'],
        ];
    }

    protected static function field_limit_page(): array {
        return [
            ['key'=>'limit','label'=>'Limit','type'=>'number','default'=>20],
            ['key'=>'page','label'=>'Page','type'=>'number','default'=>1],
        ];
    }

    protected static function field_term_id(): array {
        return [[
            'key'=>'term_id',
            'label'=>'Term ID',
            'type'=>'expression',
            'required'=>true,
        ]];
    }

    protected static function field_term_create(bool $with_parent = true): array {
        $fields = [
            ['key'=>'name','label'=>'Name','type'=>'text','required'=>true],
            ['key'=>'slug','label'=>'Slug','type'=>'text'],
            ['key'=>'description','label'=>'Description','type'=>'textarea'],
        ];
        if ($with_parent) {
            $fields[] = ['key'=>'parent','label'=>'Parent Term ID','type'=>'expression'];
        }
        return $fields;
    }

    protected static function field_term_update(bool $with_parent = true): array {
        $fields = array_merge(self::field_term_id(), [
            ['key'=>'name','label'=>'Name','type'=>'text'],
            ['key'=>'slug','label'=>'Slug','type'=>'text'],
            ['key'=>'description','label'=>'Description','type'=>'textarea'],
        ]);
        if ($with_parent) {
            $fields[] = ['key'=>'parent','label'=>'Parent Term ID','type'=>'expression'];
        }
        return $fields;
    }

    protected static function field_term_delete(): array {
        return self::field_term_id();
    }

    protected static function field_attribute_id(): array {
        return [[
            'key'=>'attribute_id',
            'label'=>'Attribute ID',
            'type'=>'expression',
            'required'=>true,
        ]];
    }

    protected static function field_attribute_create(): array {
        return [
            ['key'=>'name','label'=>'Name','type'=>'text','required'=>true],
            ['key'=>'slug','label'=>'Slug','type'=>'text'],
            ['key'=>'type','label'=>'Type','type'=>'select','options'=>[
                ['label'=>'Select','value'=>'select'],
                ['label'=>'Text','value'=>'text'],
            ]],
            ['key'=>'order_by','label'=>'Order By','type'=>'select','options'=>[
                ['label'=>'Name','value'=>'name'],
                ['label'=>'Name (numeric)','value'=>'name_num'],
                ['label'=>'ID','value'=>'id'],
                ['label'=>'Menu Order','value'=>'menu_order'],
            ]],
            ['key'=>'has_archives','label'=>'Has Archives','type'=>'boolean'],
        ];
    }

    protected static function field_attribute_update(): array {
        return array_merge(self::field_attribute_id(), [
            ['key'=>'name','label'=>'Name','type'=>'text'],
            ['key'=>'slug','label'=>'Slug','type'=>'text'],
            ['key'=>'type','label'=>'Type','type'=>'select','options'=>[
                ['label'=>'Select','value'=>'select'],
                ['label'=>'Text','value'=>'text'],
            ]],
            ['key'=>'order_by','label'=>'Order By','type'=>'select','options'=>[
                ['label'=>'Name','value'=>'name'],
                ['label'=>'Name (numeric)','value'=>'name_num'],
                ['label'=>'ID','value'=>'id'],
                ['label'=>'Menu Order','value'=>'menu_order'],
            ]],
            ['key'=>'has_archives','label'=>'Has Archives','type'=>'boolean'],
        ]);
    }

}
