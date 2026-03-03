<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Helper {
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

    protected static function ensure_surecart(): ?array {
        if (!class_exists('SureCart')) {
            return self::error('SureCart plugin is not active');
        }
        return null;
    }

    protected static function create_model(string $class, array $config, string $key): array {
        if ($error = self::ensure_surecart()) return $error;
        if (!class_exists($class)) {
            return self::error('Model class not found', ['class' => $class]);
        }

        $data = self::parse_json_array($config['data'] ?? []);
        $model = new $class();
        self::apply_model_context($model, $config);

        $result = $model->create($data);
        if ($result === false) {
            return self::error('Create failed');
        }
        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), ['code' => $result->get_error_code()]);
        }

        return self::respond([$key => self::model_to_array($result)]);
    }

    protected static function update_model(string $class, array $config, string $id_key, string $key): array {
        if ($error = self::ensure_surecart()) return $error;
        if (!class_exists($class)) {
            return self::error('Model class not found', ['class' => $class]);
        }

        $id = $config[$id_key] ?? '';
        if ($id === '' || $id === null) {
            return self::error('ID is required', ['field' => $id_key]);
        }

        $data = self::parse_json_array($config['data'] ?? []);
        $model = new $class($id);
        self::apply_model_context($model, $config);

        $result = $model->update($data);
        if ($result === false) {
            return self::error('Update failed');
        }
        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), ['code' => $result->get_error_code()]);
        }

        return self::respond([$key => self::model_to_array($result)]);
    }

    protected static function delete_model(string $class, array $config, string $id_key): array {
        if ($error = self::ensure_surecart()) return $error;
        if (!class_exists($class)) {
            return self::error('Model class not found', ['class' => $class]);
        }

        $id = $config[$id_key] ?? '';
        if ($id === '' || $id === null) {
            return self::error('ID is required', ['field' => $id_key]);
        }

        $model = new $class($id);
        self::apply_model_context($model, $config);

        $result = $model->delete($id);
        if ($result === false) {
            return self::error('Delete failed');
        }
        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), ['code' => $result->get_error_code()]);
        }

        return self::respond(['id' => $id, 'deleted' => true]);
    }

    protected static function get_model_single(string $class, array $config, string $id_key, string $key): array {
        if ($error = self::ensure_surecart()) return $error;
        if (!class_exists($class)) {
            return self::error('Model class not found', ['class' => $class]);
        }

        $id = $config[$id_key] ?? '';
        if ($id === '' || $id === null) {
            return self::error('ID is required', ['field' => $id_key]);
        }

        $model = new $class();
        self::apply_model_context($model, $config);

        $result = $model->find($id);
        if ($result === false) {
            return self::error('Not found');
        }
        if (is_wp_error($result)) {
            return self::error($result->get_error_message(), ['code' => $result->get_error_code()]);
        }

        return self::respond([$key => self::model_to_array($result)]);
    }

    protected static function list_models(string $class, array $config): array {
        if ($error = self::ensure_surecart()) return $error;
        if (!class_exists($class)) {
            return self::error('Model class not found', ['class' => $class]);
        }

        $pagination = self::get_pagination_args($config);
        $query = self::parse_json_array($config['query'] ?? []);

        $model = new $class();
        self::apply_model_context($model, $config);
        if (!empty($query)) {
            $model->where($query);
        }

        $collection = $model->paginate([
            'page' => $pagination['page'],
            'per_page' => $pagination['limit'],
        ]);

        if (is_wp_error($collection)) {
            return self::error($collection->get_error_message(), ['code' => $collection->get_error_code()]);
        }

        $items = [];
        $data = $collection->data ?? [];
        if (is_array($data)) {
            foreach ($data as $item) {
                $items[] = self::model_to_array($item);
            }
        }

        return self::respond([
            'count' => (int) ($collection->total() ?? count($items)),
            'items' => $items,
            'page' => $pagination['page'],
            'limit' => $pagination['limit'],
        ]);
    }

    protected static function apply_model_context($model, array $config): void {
        $mode = $config['mode'] ?? '';
        if ($mode !== '') {
            $mode = self::normalize_mode($mode);
            if ($mode !== '') {
                $model->setMode($mode);
            }
        }

        $expand = self::parse_list($config['expand'] ?? []);
        if (!empty($expand)) {
            $model->with($expand);
        }
    }

    protected static function normalize_mode($mode): string {
        $mode = strtolower(trim((string) $mode));
        if ($mode === 'test' || $mode === 'live') {
            return $mode;
        }
        return '';
    }

    protected static function payload_from_model($model, string $key): array {
        return [$key => self::model_to_array($model)];
    }

    protected static function payload_checkout_confirmed(array $args): array {
        $checkout = $args[0] ?? null;
        $request = $args[1] ?? null;

        $payload = ['checkout' => self::model_to_array($checkout)];

        if ($request instanceof \WP_REST_Request) {
            $payload['request'] = [
                'method' => $request->get_method(),
                'route' => $request->get_route(),
                'params' => $request->get_params(),
            ];
        } elseif (is_array($request)) {
            $payload['request'] = $request;
        }

        return $payload;
    }

    protected static function model_to_array($model): array {
        if (is_null($model)) {
            return [];
        }
        if (is_array($model)) {
            return $model;
        }
        if (is_object($model)) {
            if (method_exists($model, 'toArray')) {
                return $model->toArray();
            }
            if ($model instanceof \WP_Post) {
                return self::normalize_post($model);
            }
            return get_object_vars($model);
        }
        return ['value' => $model];
    }

    protected static function normalize_post($post): array {
        if (is_numeric($post)) {
            $post = get_post((int) $post);
        }
        if ($post instanceof \WP_Post) {
            return [
                'ID' => $post->ID,
                'post_title' => $post->post_title,
                'post_name' => $post->post_name,
                'post_status' => $post->post_status,
                'post_type' => $post->post_type,
                'post_date' => $post->post_date,
                'post_modified' => $post->post_modified,
                'post_author' => $post->post_author,
                'guid' => $post->guid,
            ];
        }
        return is_array($post) ? $post : [];
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
            return array_values(array_filter($value, 'strlen'));
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
            return array_values(array_filter($decoded, 'strlen'));
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }

    protected static function build_product_data_from_manual(array $config): array {
        $data = [];

        $name = trim((string) ($config['name'] ?? ''));
        if ($name !== '') {
            $data['name'] = $name;
        }

        $description = $config['description'] ?? '';
        if ($description !== '') {
            $data['description'] = $description;
        }

        $status = strtolower(trim((string) ($config['status'] ?? '')));
        if ($status !== '') {
            $data['status'] = $status;
        }

        $price = [];
        $amount = $config['price_amount'] ?? '';
        if ($amount !== '' && is_numeric($amount)) {
            $price['amount'] = (int) $amount;
        }

        $currency = strtoupper(trim((string) ($config['currency'] ?? '')));
        if ($currency !== '') {
            $price['currency'] = $currency;
        }

        $interval = strtolower(trim((string) ($config['recurring_interval'] ?? '')));
        if ($interval !== '') {
            $price['recurring_interval'] = $interval;
            $count = $config['recurring_interval_count'] ?? 1;
            if ($count !== '' && $count !== null) {
                $price['recurring_interval_count'] = max(1, (int) $count);
            }
        }

        if (!empty($price)) {
            $data['prices'] = [$price];
        }

        return $data;
    }

    protected static function get_pagination_args(array $config, int $default_limit = 20): array {
        $limit = isset($config['limit']) ? (int) $config['limit'] : $default_limit;
        if ($limit <= 0) {
            $limit = $default_limit;
        }
        $page = isset($config['page']) ? max(1, (int) $config['page']) : 1;
        return ['limit' => $limit, 'page' => $page];
    }

    protected static function field_data(string $label): array {
        return [[
            'key' => 'data',
            'label' => $label,
            'type' => 'textarea',
        ]];
    }

    protected static function field_mode(): array {
        return [[
            'key' => 'mode',
            'label' => 'Mode',
            'type' => 'select',
            'options' => [
                ['label' => 'Live', 'value' => 'live'],
                ['label' => 'Test', 'value' => 'test'],
            ],
        ]];
    }

    protected static function field_expand(): array {
        return [[
            'key' => 'expand',
            'label' => 'Expand (CSV/JSON)',
            'type' => 'textarea',
        ]];
    }

    protected static function field_query(): array {
        return [[
            'key' => 'query',
            'label' => 'Query (JSON)',
            'type' => 'textarea',
        ]];
    }

    protected static function field_limit_page(): array {
        return [
            ['key'=>'limit','label'=>'Limit','type'=>'number','default'=>20],
            ['key'=>'page','label'=>'Page','type'=>'number','default'=>1],
        ];
    }

    protected static function field_order_id(): array {
        return [[
            'key' => 'order_id',
            'label' => 'Order ID',
            'type' => 'expression',
            'required' => true,
        ]];
    }

    protected static function field_customer_id(): array {
        return [[
            'key' => 'customer_id',
            'label' => 'Customer ID',
            'type' => 'expression',
            'required' => true,
        ]];
    }

    protected static function field_product_id(): array {
        return [[
            'key' => 'product_id',
            'label' => 'Product ID',
            'type' => 'expression',
            'required' => true,
        ]];
    }

    protected static function field_coupon_id(): array {
        return [[
            'key' => 'coupon_id',
            'label' => 'Coupon ID',
            'type' => 'expression',
            'required' => true,
        ]];
    }

    protected static function field_subscription_id(): array {
        return [[
            'key' => 'subscription_id',
            'label' => 'Subscription ID',
            'type' => 'expression',
            'required' => true,
        ]];
    }
}
