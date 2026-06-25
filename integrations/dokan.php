<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (! defined('ABSPATH')) {
	exit;
}

class Dokan extends IntegrationBase
{

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public static function get_slug(): string
	{
		return 'dokan';
	}

	public static function get_name(): string
	{
		return 'Dokan';
	}

	public static function get_icon(): string
	{
		return 'dokan.svg';
	}

	// -------------------------------------------------------------------------
	// Triggers
	// -------------------------------------------------------------------------

	public static function get_triggers(): array
	{
		return [
			// Existing triggers (kept)
			'new_seller_created'      => ['label' => 'New Seller Created',      'hook' => 'dokan_new_seller_created'],
			'store_profile_saved'     => ['label' => 'Store Profile Saved',     'hook' => 'dokan_store_profile_saved'],
			'new_product_added'       => ['label' => 'New Product Added',       'hook' => 'dokan_new_product_added'],
			'product_updated'         => ['label' => 'Product Updated',         'hook' => 'dokan_product_updated'],
			'product_deleted'         => ['label' => 'Product Deleted',         'hook' => 'dokan_product_deleted'],
			'checkout_update_order_meta' => ['label' => 'Checkout Order Meta Updated', 'hook' => 'dokan_checkout_update_order_meta'],
			'vendor_enabled'          => ['label' => 'Vendor Enabled',          'hook' => 'dokan_vendor_enabled'],
			'vendor_disabled'         => ['label' => 'Vendor Disabled',         'hook' => 'dokan_vendor_disabled'],
			'withdraw_request_created' => ['label' => 'Withdraw Request Created', 'hook' => 'dokan_after_withdraw_request'],
			'withdraw_created'        => ['label' => 'Withdraw Created',        'hook' => 'dokan_withdraw_created'],
			'withdraw_request_pending' => ['label' => 'Withdraw Request Pending', 'hook' => 'dokan_withdraw_request_pending'],
			'withdraw_request_approved' => ['label' => 'Withdraw Request Approved', 'hook' => 'dokan_withdraw_request_approved'],
			'withdraw_request_cancelled' => ['label' => 'Withdraw Request Cancelled', 'hook' => 'dokan_withdraw_request_cancelled'],
			'withdraw_status_updated' => ['label' => 'Withdraw Status Updated', 'hook' => 'dokan_withdraw_status_updated'],

			// --- New triggers (from BitApps PiPro) ---
			'vendor_add'              => ['label' => 'Vendor Added (before create)', 'hook' => 'dokan_before_create_vendor'],
			'vendor_update'           => ['label' => 'Vendor Updated (before update)', 'hook' => 'dokan_before_update_vendor'],
			'vendor_delete'           => ['label' => 'Vendor Deleted',              'hook' => 'delete_user'],
			'refund_request'          => ['label' => 'Refund Requested',            'hook' => 'dokan_refund_request_created'],
			'refund_approved'         => ['label' => 'Refund Approved',             'hook' => 'dokan_pro_refund_approved'],
			'refund_cancelled'        => ['label' => 'Refund Cancelled',            'hook' => 'dokan_pro_refund_cancelled'],
		];
	}

	public static function get_trigger_config_schema(string $trigger): array
	{
		$vendor_selector = [
			[
				'key'      => 'vendor_id',
				'label'    => 'Vendor',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'dokan',
					'query'       => 'vendors',
					'select'      => ['name', 'label'],
				],
				'required' => true,
			],
		];

		$product_selector = [
			[
				'key'      => 'product_id',
				'label'    => 'Product',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'dokan',
					'query'       => 'products',
					'select'      => ['name', 'label'],
				],
				'required' => true,
			],
		];

		if (in_array($trigger, ['new_product_added', 'product_updated', 'product_deleted'], true)) {
			return array_merge($vendor_selector, $product_selector);
		}

		$vendor_triggers = [
			'new_seller_created',
			'store_profile_saved',
			'checkout_update_order_meta',
			'vendor_enabled',
			'vendor_disabled',
			'withdraw_request_created',
			'withdraw_created',
			'withdraw_request_pending',
			'withdraw_request_approved',
			'withdraw_request_cancelled',
			'withdraw_status_updated',
			'vendor_add',
			'vendor_update',
			'vendor_delete',
			'refund_request',
			'refund_approved',
			'refund_cancelled',
		];
		if (in_array($trigger, $vendor_triggers, true)) {
			return $vendor_selector;
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// Resolve Trigger
	// -------------------------------------------------------------------------

	public static function resolve_trigger(array $node, array $args)
	{
		if (! self::is_dokan_available()) {
			return false;
		}

		$event  = self::resolve_node_event($node, 'trigger');
		$config = self::resolve_node_config($node);

		if ('' === $event) {
			return false;
		}

		switch ($event) {

			// ------------------------------
			// Existing triggers
			// ------------------------------

			case 'new_seller_created':
				$vendor_id = self::parse_positive_int($args[0] ?? 0);
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'          => $event,
					'event_time'     => current_time('mysql'),
					'vendor_id'      => $vendor_id,
					'vendor'         => self::build_vendor_payload_from_id($vendor_id),
					'dokan_settings' => is_array($args[1] ?? null) ? $args[1] : [],
				];

			case 'store_profile_saved':
				$store_info     = is_array($args[0] ?? null) ? $args[0] : [];
				$vendor_id      = self::parse_positive_int($args[1] ?? 0);
				$previous_store = is_array($args[2] ?? null) ? $args[2] : [];
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'          => $event,
					'event_time'     => current_time('mysql'),
					'vendor_id'      => $vendor_id,
					'vendor'         => self::build_vendor_payload_from_id($vendor_id),
					'store_info'     => $store_info,
					'previous_store' => $previous_store,
				];

			case 'vendor_enabled':
			case 'vendor_disabled':
				$vendor_id = self::parse_positive_int($args[0] ?? 0);
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'      => $event,
					'event_time' => current_time('mysql'),
					'vendor_id'  => $vendor_id,
					'vendor'     => self::build_vendor_payload_from_id($vendor_id),
				];

				// Products
			case 'new_product_added':
			case 'product_updated':
			case 'product_deleted':
				$product_id = self::parse_positive_int($args[0] ?? 0);
				if ($product_id <= 0 || ! self::matches_id_filter($config, 'product_id', $product_id)) {
					return false;
				}
				$product_data = is_array($args[1] ?? null) ? $args[1] : [];
				$product      = self::build_product_payload($product_id, ['input' => $product_data]);
				$vendor_id    = self::parse_positive_int($product_data['post_author'] ?? ($product['vendor_id'] ?? 0));
				if ($vendor_id > 0) {
					$product['vendor_id'] = $vendor_id;
					$product['vendor']    = self::build_vendor_payload_from_id($vendor_id);
				}
				if ($vendor_id > 0 && ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'      => $event,
					'event_time' => current_time('mysql'),
					'product_id' => $product_id,
					'vendor_id'  => $vendor_id,
					'product'    => $product,
				];

				// Order meta
			case 'checkout_update_order_meta':
				$order_id  = self::parse_positive_int($args[0] ?? 0);
				$vendor_id = self::parse_positive_int($args[1] ?? 0);
				if ($order_id <= 0) {
					return false;
				}
				if ($vendor_id <= 0 && function_exists('dokan_get_seller_id_by_order')) {
					$vendor_id = self::parse_positive_int(dokan_get_seller_id_by_order($order_id));
				}
				if ($vendor_id > 0 && ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'      => $event,
					'event_time' => current_time('mysql'),
					'order_id'   => $order_id,
					'vendor_id'  => $vendor_id,
					'order'      => self::build_order_payload($order_id, $vendor_id),
				];

				// Withdrawals
			case 'withdraw_request_created':
				$vendor_id   = self::parse_positive_int($args[0] ?? 0);
				$amount      = isset($args[1]) ? (float) $args[1] : 0.0;
				$method      = (string) ($args[2] ?? '');
				$withdraw_id = self::parse_positive_int($args[3] ?? 0);
				$withdraw    = self::resolve_withdraw_entity($withdraw_id);
				$withdraw_payload = $withdraw ? self::build_withdraw_payload($withdraw) : [];
				if ($vendor_id <= 0) {
					$vendor_id = self::parse_positive_int($withdraw_payload['vendor_id'] ?? 0);
				}
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'       => $event,
					'event_time'  => current_time('mysql'),
					'vendor_id'   => $vendor_id,
					'withdraw_id' => $withdraw_id,
					'amount'      => $amount,
					'method'      => $method,
					'vendor'      => self::build_vendor_payload_from_id($vendor_id),
					'withdraw'    => $withdraw_payload,
				];

			case 'withdraw_created':
			case 'withdraw_request_pending':
			case 'withdraw_request_approved':
			case 'withdraw_request_cancelled':
				$withdraw = self::resolve_withdraw_entity($args[0] ?? null);
				if (! $withdraw) {
					$withdraw = self::resolve_withdraw_entity($args[2] ?? null);
				}
				if (! $withdraw) {
					$withdraw = self::resolve_withdraw_entity($args[1] ?? null);
				}
				if (! $withdraw) {
					return false;
				}
				$withdraw_payload = self::build_withdraw_payload($withdraw);
				$vendor_id        = (int) ($withdraw_payload['vendor_id'] ?? 0);
				if ($vendor_id > 0 && ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'       => $event,
					'event_time'  => current_time('mysql'),
					'withdraw_id' => (int) ($withdraw_payload['withdraw_id'] ?? 0),
					'vendor_id'   => $vendor_id,
					'withdraw'    => $withdraw_payload,
				];

			case 'withdraw_status_updated':
				$status      = (string) ($args[0] ?? '');
				$vendor_id   = self::parse_positive_int($args[1] ?? 0);
				$withdraw_id = self::parse_positive_int($args[2] ?? 0);
				$withdraw    = self::resolve_withdraw_entity($withdraw_id);
				$withdraw_payload = $withdraw ? self::build_withdraw_payload($withdraw) : [];
				if ($vendor_id <= 0) {
					$vendor_id = self::parse_positive_int($withdraw_payload['vendor_id'] ?? 0);
				}
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return [
					'event'           => $event,
					'event_time'      => current_time('mysql'),
					'vendor_id'       => $vendor_id,
					'withdraw_id'     => $withdraw_id,
					'withdraw_status' => self::normalize_withdraw_status($status),
					'withdraw'        => $withdraw_payload,
				];

				// ------------------------------------------------------------
				// NEW: Vendor Add / Update / Delete
				// ------------------------------------------------------------

			case 'vendor_add':
				$vendor_id = self::parse_positive_int($args[0] ?? 0);
				$data      = is_array($args[1] ?? null) ? $args[1] : [];
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				$payload = self::build_vendor_payload_from_id($vendor_id);
				$extra   = self::extract_vendor_extra_fields($data);
				return array_merge([
					'event'      => $event,
					'event_time' => current_time('mysql'),
					'vendor_id'  => $vendor_id,
					'vendor'     => $payload,
				], $extra);

			case 'vendor_update':
				$vendor_id = self::parse_positive_int($args[0] ?? 0);
				$data      = is_array($args[1] ?? null) ? $args[1] : [];
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				$payload = self::build_vendor_payload_from_id($vendor_id);
				$extra   = self::extract_vendor_extra_fields($data);
				return array_merge([
					'event'      => $event,
					'event_time' => current_time('mysql'),
					'vendor_id'  => $vendor_id,
					'vendor'     => $payload,
				], $extra);

			case 'vendor_delete':
				$user_id = self::parse_positive_int($args[0] ?? 0);
				if ($user_id <= 0 || ! self::is_user_seller($user_id)) {
					return false;
				}
				if (! self::matches_id_filter($config, 'vendor_id', $user_id)) {
					return false;
				}
				$payload = self::build_vendor_payload_from_id($user_id);
				return [
					'event'      => $event,
					'event_time' => current_time('mysql'),
					'vendor_id'  => $user_id,
					'vendor'     => $payload,
				];

				// ------------------------------------------------------------
				// NEW: Refund triggers
				// ------------------------------------------------------------

			case 'refund_request':
			case 'refund_approved':
			case 'refund_cancelled':
				$refund = $args[0] ?? null;
				if (! $refund) {
					return false;
				}
				$refund_data = self::build_refund_payload($refund);
				if (empty($refund_data)) {
					return false;
				}
				$vendor_id = (int) ($refund_data['vendor_id'] ?? 0);
				if ($vendor_id <= 0 || ! self::matches_id_filter($config, 'vendor_id', $vendor_id)) {
					return false;
				}
				return array_merge([
					'event'      => $event,
					'event_time' => current_time('mysql'),
				], $refund_data);
		} //end switch

		return false;
	}

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	public static function get_actions(): array
	{
		return [
			'get_vendor_single'   => ['label' => 'Get Vendor (Single)'],
			'get_vendors_all'     => ['label' => 'Get Vendors (All)'],
			'get_withdraw_single' => ['label' => 'Get Withdraw (Single)'],
			'get_withdraws_all'   => ['label' => 'Get Withdraws (All)'],
			'add_action'          => ['label' => 'Add Action Hook'],
			'do_action'           => ['label' => 'Do Action Hook'],
		];
	}

	public static function get_action_config_schema(string $action): array
	{
		$schemas = [
			'get_vendor_single' => [
				[
					'key'      => 'vendor_id',
					'label'    => 'Vendor',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'dokan',
						'query'       => 'vendors',
						'select'      => ['name', 'label'],
					],
					'required' => true,
				],
			],
			'get_vendors_all' => [
				[
					'key'      => 'limit',
					'label'    => 'Limit',
					'type'     => 'number',
					'required' => true,
				],
				[
					'key'      => 'page',
					'label'    => 'Page',
					'type'     => 'number',
					'required' => true,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
				],
			],
			'get_withdraw_single' => [
				[
					'key'      => 'withdraw_id',
					'label'    => 'Withdraw',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'dokan',
						'query'       => 'withdraws',
						'select'      => ['name', 'label'],
					],
					'required' => true,
				],
			],
			'get_withdraws_all' => [
				[
					'key'      => 'vendor_id',
					'label'    => 'Vendor',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'dokan',
						'query'       => 'vendors',
						'select'      => ['name', 'label'],
					],
					'required' => true,
				],
				[
					'key'     => 'withdraw_status',
					'label'   => 'Withdraw Status',
					'type'    => 'select',
					'options' => [
						['label' => 'Any',       'value' => 'any'],
						['label' => 'Pending',   'value' => 'pending'],
						['label' => 'Approved',  'value' => 'approved'],
						['label' => 'Cancelled', 'value' => 'cancelled'],
					],
				],
				[
					'key'      => 'limit',
					'label'    => 'Limit',
					'type'     => 'number',
					'required' => true,
				],
				[
					'key'      => 'page',
					'label'    => 'Page',
					'type'     => 'number',
					'required' => true,
				],
			],
			'add_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'accepted_args',
					'label' => 'Accepted Args',
					'type'  => 'number',
				],
			],
			'do_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'arg_1',
					'label' => 'Argument 1',
					'type'  => 'expression',
				],
				[
					'key'   => 'arg_2',
					'label' => 'Argument 2',
					'type'  => 'expression',
				],
			],
		];

		return $schemas[$action] ?? [];
	}

	public static function execute_node(array $node, array $input): array
	{
		$event  = self::resolve_node_event($node, 'action');
		$config = self::resolve_node_config($node);

		if (! self::is_dokan_available() && ! in_array($event, ['add_action', 'do_action'], true)) {
			return self::error_response('Dokan is not available', $input);
		}

		switch ($event) {
			case 'get_vendor_single':
				return self::action_get_vendor_single($config, $input);
			case 'get_vendors_all':
				return self::action_get_vendors_all($config, $input);
			case 'get_withdraw_single':
				return self::action_get_withdraw_single($config, $input);
			case 'get_withdraws_all':
				return self::action_get_withdraws_all($config, $input);
			case 'add_action':
				return self::action_add_action($config, $input);
			case 'do_action':
				return self::action_do_action($config, $input);
		}

		return self::main_response($input);
	}

	// -------------------------------------------------------------------------
	// Dynamic queries
	// -------------------------------------------------------------------------

	public static function get_dynamic_queries(): array
	{
		return [
			'vendors'   => [self::class, 'vendors_query'],
			'products'  => [self::class, 'products_query'],
			'withdraws' => [self::class, 'withdraws_query'],
		];
	}

	// -------------------------------------------------------------------------
	// Availability check
	// -------------------------------------------------------------------------

	private static function is_dokan_available(): bool
	{
		return function_exists('dokan') || function_exists('dokan_get_store_info');
	}

	// =========================================================================
	// Helper methods
	// =========================================================================

	// -------------------------------------------------------------------------
	// Node resolution
	// -------------------------------------------------------------------------

	private static function resolve_node_event(array $node, string $type): string
	{
		$candidates = [
			$node['event'] ?? null,
			$node['data']['event'] ?? null,
			$node[$type] ?? null,
			$node['data'][$type] ?? null,
			$node['config']['event'] ?? null,
			$node['config'][$type] ?? null,
		];

		foreach ($candidates as $candidate) {
			if (! is_scalar($candidate)) {
				continue;
			}
			$event = trim((string) $candidate);
			if ('' !== $event) {
				return $event;
			}
		}

		return '';
	}

	private static function resolve_node_config(array $node): array
	{
		if (isset($node['data']['config']) && is_array($node['data']['config'])) {
			return $node['data']['config'];
		}
		if (isset($node['config']['data']) && is_array($node['config']['data'])) {
			return $node['config']['data'];
		}
		if (isset($node['config']['config']) && is_array($node['config']['config'])) {
			return $node['config']['config'];
		}
		if (isset($node['config']) && is_array($node['config'])) {
			return self::strip_structural_node_keys($node['config']);
		}

		return self::strip_structural_node_keys($node);
	}

	private static function strip_structural_node_keys(array $config): array
	{
		unset(
			$config['app'],
			$config['event'],
			$config['action'],
			$config['trigger'],
			$config['type'],
			$config['hook'],
			$config['label'],
			$config['connection_id'],
			$config['data']
		);
		return $config;
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	private static function action_get_vendor_single(array $config, array $input): array
	{
		$vendor_id = self::parse_positive_int($config['vendor_id'] ?? 0);
		if ($vendor_id <= 0) {
			return self::error_response('Vendor ID is required', $input);
		}

		$vendor = self::build_vendor_payload_from_id($vendor_id);
		if (empty($vendor)) {
			return self::error_response('Vendor not found', $input);
		}

		return self::main_response(array_merge($input, ['vendor' => $vendor]));
	}

	private static function action_get_vendors_all(array $config, array $input): array
	{
		$limit  = max(1, (int) ($config['limit'] ?? 20));
		$page   = max(1, (int) ($config['page'] ?? 1));
		$search = trim((string) ($config['search'] ?? ''));

		$items = [];
		$total = 0;

		if (function_exists('dokan_get_sellers')) {
			$result = dokan_get_sellers(
				[
					'number' => $limit,
					'paged'  => $page,
					'search' => $search,
				]
			);

			$users = is_array($result) ? ($result['users'] ?? []) : [];
			foreach ($users as $user) {
				$vendor_id = self::parse_positive_int($user);
				if ($vendor_id <= 0) {
					continue;
				}
				$items[] = self::build_vendor_payload_from_id($vendor_id);
			}

			$total = (int) ($result['count'] ?? count($items));
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'items' => $items,
					'total' => $total,
					'limit' => $limit,
					'page'  => $page,
				]
			)
		);
	}

	private static function action_get_withdraw_single(array $config, array $input): array
	{
		$withdraw_id = self::parse_positive_int($config['withdraw_id'] ?? 0);
		if ($withdraw_id <= 0) {
			return self::error_response('Withdraw ID is required', $input);
		}

		$withdraw = self::resolve_withdraw_entity($withdraw_id);
		if (! $withdraw) {
			return self::error_response('Withdraw not found', $input);
		}

		return self::main_response(array_merge($input, ['withdraw' => self::build_withdraw_payload($withdraw)]));
	}

	private static function action_get_withdraws_all(array $config, array $input): array
	{
		$limit     = max(1, (int) ($config['limit'] ?? 20));
		$page      = max(1, (int) ($config['page'] ?? 1));
		$vendor_id = self::parse_positive_int($config['vendor_id'] ?? 0);
		$status    = self::normalize_withdraw_status($config['withdraw_status'] ?? ($config['status'] ?? 'any'));

		$args = [
			'limit'  => $limit,
			'offset' => ($page - 1) * $limit,
		];

		if ($vendor_id > 0) {
			$args['user_id'] = $vendor_id;
		}
		if ('' !== $status && 'any' !== $status) {
			$args['status'] = self::resolve_withdraw_status_code($status);
		}

		$items = [];
		foreach (self::resolve_withdraw_collection($args) as $withdraw) {
			$payload = self::build_withdraw_payload($withdraw);
			if (empty($payload)) {
				continue;
			}

			$payload_vendor_id = (int) ($payload['vendor_id'] ?? 0);
			if ($vendor_id > 0 && $payload_vendor_id !== $vendor_id) {
				continue;
			}

			$payload_status = (string) ($payload['status'] ?? '');
			if ('' !== $status && 'any' !== $status && $status !== $payload_status) {
				continue;
			}

			$items[] = $payload;
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'items' => $items,
					'total' => count($items),
					'limit' => $limit,
					'page'  => $page,
				]
			)
		);
	}

	private static function action_add_action(array $config, array $input): array
	{
		$hook_name = trim((string) ($config['hook_name'] ?? ''));
		if ('' === $hook_name) {
			return self::error_response('Hook name is required', $input);
		}

		$accepted_args = min(99, max(1, (int) ($config['accepted_args'] ?? 1)));

		add_action($hook_name, static function () {}, 10, $accepted_args);

		return self::main_response(
			array_merge(
				$input,
				[
					'hook'          => $hook_name,
					'accepted_args' => $accepted_args,
					'registered'    => true,
					'event_time'    => current_time('mysql'),
				]
			)
		);
	}

	private static function action_do_action(array $config, array $input): array
	{
		$hook_name = trim((string) ($config['hook_name'] ?? ''));
		if ('' === $hook_name) {
			return self::error_response('Hook name is required', $input);
		}

		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;
		do_action($hook_name, $arg_1, $arg_2);

		return self::main_response(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'arg_1'      => $arg_1,
					'arg_2'      => $arg_2,
					'triggered'  => true,
					'event_time' => current_time('mysql'),
				]
			)
		);
	}

	// -------------------------------------------------------------------------
	// Dynamic query handlers
	// -------------------------------------------------------------------------

	public static function vendors_query($q): array
	{
		$q = is_array($q) ? $q : [];

		$options = [['name' => 'any', 'label' => 'Any Vendor']];

		if (! function_exists('dokan_get_sellers')) {
			return $options;
		}

		$limit  = max(1, (int) ($q['limit'] ?? 50));
		$search = trim((string) ($q['search'] ?? ''));
		$result = dokan_get_sellers(
			[
				'number' => $limit,
				'paged'  => 1,
				'search' => $search,
			]
		);

		$users = is_array($result) ? ($result['users'] ?? []) : [];
		foreach ($users as $user) {
			$vendor_id = self::parse_positive_int($user);
			if ($vendor_id <= 0) {
				continue;
			}
			$vendor  = self::build_vendor_payload_from_id($vendor_id);
			$label   = (string) ($vendor['store_name'] ?? '');
			$label   = '' !== $label ? $label : 'Vendor #' . $vendor_id;
			$options[] = [
				'name'  => (string) $vendor_id,
				'label' => $label . ' (#' . $vendor_id . ')',
			];
		}

		return $options;
	}

	public static function products_query($q): array
	{
		$q = is_array($q) ? $q : [];

		$options = [['name' => 'any', 'label' => 'Any Product']];

		if (! function_exists('get_posts')) {
			return $options;
		}

		$vendor_id = self::parse_positive_int($q['vendor_id'] ?? 0);
		$limit     = max(1, (int) ($q['limit'] ?? 50));
		$search    = trim((string) ($q['search'] ?? ''));

		$args = [
			'post_type'      => 'product',
			'post_status'    => ['publish', 'pending', 'draft'],
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		];

		if ('' !== $search) {
			$args['s'] = $search;
		}
		if ($vendor_id > 0) {
			$args['author'] = $vendor_id;
		}

		foreach (get_posts($args) as $product_id) {
			$product_id = self::parse_positive_int($product_id);
			if ($product_id <= 0) {
				continue;
			}
			$post      = get_post($product_id);
			$title     = $post ? (string) ($post->post_title ?? '') : '';
			$title     = '' !== $title ? $title : 'Product #' . $product_id;
			$options[] = [
				'name'  => (string) $product_id,
				'label' => $title . ' (#' . $product_id . ')',
			];
		}

		return $options;
	}

	public static function withdraws_query($q): array
	{
		$q = is_array($q) ? $q : [];

		$options = [['name' => 'any', 'label' => 'Any Withdraw']];

		$args      = ['limit' => max(1, (int) ($q['limit'] ?? 50)), 'offset' => 0];
		$vendor_id = self::parse_positive_int($q['vendor_id'] ?? 0);
		if ($vendor_id > 0) {
			$args['user_id'] = $vendor_id;
		}

		foreach (self::resolve_withdraw_collection($args) as $withdraw) {
			$payload     = self::build_withdraw_payload($withdraw);
			$withdraw_id = (int) ($payload['withdraw_id'] ?? 0);
			if ($withdraw_id <= 0) {
				continue;
			}
			$options[] = [
				'name'  => (string) $withdraw_id,
				'label' => '#' . $withdraw_id . ' - ' . ucfirst((string) ($payload['status'] ?? 'pending')),
			];
		}

		return $options;
	}

	// -------------------------------------------------------------------------
	// Payload builders
	// -------------------------------------------------------------------------

	private static function build_vendor_payload_from_id(int $vendor_id): array
	{
		if ($vendor_id <= 0) {
			return [];
		}

		$store_info = self::resolve_store_info($vendor_id);
		$user       = function_exists('get_userdata') ? get_userdata($vendor_id) : null;

		$payload = [
			'vendor_id'    => $vendor_id,
			'user_id'      => $vendor_id,
			'store_name'   => self::resolve_store_name($store_info, $vendor_id),
			'store_url'    => self::resolve_store_url($vendor_id, $store_info),
			'display_name' => $user ? (string) ($user->display_name ?? '') : '',
			'user_login'   => $user ? (string) ($user->user_login ?? '') : '',
			'user_email'   => $user ? (string) ($user->user_email ?? '') : '',
			'store_phone'  => (string) ($store_info['phone'] ?? ''),
			'is_enabled'   => self::resolve_vendor_enabled($store_info, $vendor_id),
			'store_info'   => $store_info,
		];

		// Add EU fields if Germanized module is active and enabled
		$eu_fields = self::get_enabled_vendor_eu_fields();
		if (! empty($eu_fields)) {
			foreach ($eu_fields as $field) {
				$field_name = $field['name'];
				if ($field_name === 'eu_bank_name') {
					$payload[$field_name] = isset($store_info['bank_name']) ? $store_info['bank_name'] : '';
				} else {
					$payload[$field_name] = isset($store_info[$field_name]) ? $store_info[$field_name] : '';
				}
			}
		}

		return $payload;
	}

	private static function resolve_store_info(int $vendor_id): array
	{
		if ($vendor_id <= 0 || ! function_exists('dokan_get_store_info')) {
			return [];
		}
		$store_info = dokan_get_store_info($vendor_id);
		return is_array($store_info) ? $store_info : [];
	}

	private static function resolve_store_name(array $store_info, int $vendor_id): string
	{
		$name = (string) ($store_info['store_name'] ?? '');
		if ('' !== $name) {
			return $name;
		}
		$user = function_exists('get_userdata') ? get_userdata($vendor_id) : null;
		if ($user) {
			$name = (string) ($user->display_name ?? '');
		}
		return '' !== $name ? $name : 'Vendor #' . $vendor_id;
	}

	private static function resolve_store_url(int $vendor_id, array $store_info): string
	{
		if (function_exists('dokan_get_store_url')) {
			return (string) dokan_get_store_url($vendor_id);
		}
		return (string) ($store_info['store_url'] ?? '');
	}

	private static function resolve_vendor_enabled(array $store_info, int $vendor_id): bool
	{
		$enabled = $store_info['dokan_enable_selling'] ?? null;
		if (is_string($enabled)) {
			return in_array(strtolower($enabled), ['yes', '1', 'true', 'on'], true);
		}
		if (is_bool($enabled)) {
			return $enabled;
		}
		if (function_exists('dokan_is_seller_enabled')) {
			return (bool) dokan_is_seller_enabled($vendor_id);
		}
		return true;
	}

	private static function build_product_payload(int $product_id, array $extra = []): array
	{
		$post      = function_exists('get_post') ? get_post($product_id) : null;
		$author_id = self::parse_positive_int($post->post_author ?? 0);
		$vendor_id = self::resolve_product_vendor_id($product_id, $author_id);

		return array_merge(
			[
				'product_id'    => $product_id,
				'vendor_id'     => $vendor_id,
				'vendor'        => $vendor_id > 0 ? self::build_vendor_payload_from_id($vendor_id) : [],
				'post_author'   => $author_id,
				'post_title'    => $post ? (string) ($post->post_title ?? '') : '',
				'post_status'   => $post ? (string) ($post->post_status ?? '') : '',
				'post_type'     => $post ? (string) ($post->post_type ?? '') : '',
				'post_date'     => $post ? (string) ($post->post_date ?? '') : '',
				'post_modified' => $post ? (string) ($post->post_modified ?? '') : '',
			],
			$extra
		);
	}

	private static function resolve_product_vendor_id(int $product_id, int $author_id): int
	{
		if ($author_id > 0) {
			return $author_id;
		}
		if (function_exists('dokan_get_vendor_by_product')) {
			return self::parse_positive_int(dokan_get_vendor_by_product($product_id, true));
		}
		return 0;
	}

	private static function build_order_payload(int $order_id, int $vendor_id): array
	{
		if ($vendor_id <= 0 && function_exists('dokan_get_seller_id_by_order')) {
			$vendor_id = self::parse_positive_int(dokan_get_seller_id_by_order($order_id));
		}

		$payload = [
			'order_id'  => $order_id,
			'vendor_id' => $vendor_id,
			'vendor'    => $vendor_id > 0 ? self::build_vendor_payload_from_id($vendor_id) : [],
		];

		if (! function_exists('wc_get_order')) {
			return $payload;
		}

		$order = wc_get_order($order_id);
		if (! $order || ! is_object($order)) {
			return $payload;
		}

		$payload['status']      = method_exists($order, 'get_status')      ? (string) $order->get_status()      : '';
		$payload['total']       = method_exists($order, 'get_total')       ? (float)  $order->get_total()       : 0.0;
		$payload['currency']    = method_exists($order, 'get_currency')    ? (string) $order->get_currency()    : '';
		$payload['customer_id'] = method_exists($order, 'get_customer_id') ? (int)    $order->get_customer_id() : 0;

		return $payload;
	}

	// -------------------------------------------------------------------------
	// Withdraw helpers
	// -------------------------------------------------------------------------

	private static function resolve_withdraw_entity($value)
	{
		if (is_object($value) && method_exists($value, 'get_id')) {
			return $value;
		}

		$withdraw_id = self::parse_positive_int($value);
		if ($withdraw_id <= 0 || ! function_exists('dokan')) {
			return null;
		}

		$app = dokan();
		if (! is_object($app) || ! isset($app->withdraw) || ! is_object($app->withdraw)) {
			return null;
		}

		if (method_exists($app->withdraw, 'get')) {
			return $app->withdraw->get($withdraw_id);
		}
		if (method_exists($app->withdraw, 'find')) {
			return $app->withdraw->find($withdraw_id);
		}
		if (method_exists($app->withdraw, 'get_withdraw')) {
			return $app->withdraw->get_withdraw($withdraw_id);
		}

		return null;
	}

	private static function build_withdraw_payload($withdraw, array $extra = []): array
	{
		if (! is_object($withdraw) && ! is_array($withdraw)) {
			return [];
		}

		$raw_id      = is_object($withdraw) && method_exists($withdraw, 'get_id')
			? $withdraw->get_id()
			: ($withdraw['id'] ?? 0);
		$withdraw_id = self::parse_positive_int($raw_id);
		if ($withdraw_id <= 0) {
			return [];
		}

		$raw_vendor_id = is_object($withdraw) && method_exists($withdraw, 'get_user_id')
			? $withdraw->get_user_id()
			: ($withdraw['user_id'] ?? 0);
		$vendor_id = self::parse_positive_int($raw_vendor_id);

		$raw_status = is_object($withdraw) && method_exists($withdraw, 'get_status')
			? $withdraw->get_status()
			: ($withdraw['status'] ?? '');
		$status      = self::normalize_withdraw_status($raw_status);
		$status_code = is_numeric($raw_status)
			? (int) $raw_status
			: self::resolve_withdraw_status_code($status);

		$amount  = is_object($withdraw) && method_exists($withdraw, 'get_amount')  ? (float)  $withdraw->get_amount()  : (float)  ($withdraw['amount']  ?? 0);
		$method  = is_object($withdraw) && method_exists($withdraw, 'get_method')  ? (string) $withdraw->get_method()  : (string) ($withdraw['method']  ?? '');
		$date    = is_object($withdraw) && method_exists($withdraw, 'get_date')    ? (string) $withdraw->get_date()    : (string) ($withdraw['date']    ?? '');
		$note    = is_object($withdraw) && method_exists($withdraw, 'get_note')    ? (string) $withdraw->get_note()    : (string) ($withdraw['note']    ?? '');
		$details = is_object($withdraw) && method_exists($withdraw, 'get_details') ? $withdraw->get_details()          : ($withdraw['details'] ?? []);

		return array_merge(
			[
				'withdraw_id' => $withdraw_id,
				'vendor_id'   => $vendor_id,
				'vendor'      => $vendor_id > 0 ? self::build_vendor_payload_from_id($vendor_id) : [],
				'status'      => $status,
				'status_code' => $status_code,
				'amount'      => $amount,
				'method'      => $method,
				'date'        => $date,
				'note'        => $note,
				'details'     => is_array($details) ? $details : ['value' => $details],
			],
			$extra
		);
	}

	private static function normalize_withdraw_status($status): string
	{
		if (is_numeric($status)) {
			return self::resolve_withdraw_status_name_by_code((int) $status);
		}
		$status = sanitize_key((string) $status);
		return in_array($status, ['pending', 'approved', 'cancelled', 'any'], true) ? $status : '';
	}

	private static function resolve_withdraw_status_code(string $status): int
	{
		switch ($status) {
			case 'pending':
				return 0;
			case 'approved':
				return 1;
			case 'cancelled':
				return 2;
		}
		return 0;
	}

	private static function resolve_withdraw_status_name_by_code(int $code): string
	{
		if (function_exists('dokan')) {
			$app = dokan();
			if (
				is_object($app) && isset($app->withdraw) && is_object($app->withdraw)
				&& method_exists($app->withdraw, 'get_status_name')
			) {
				$name = (string) $app->withdraw->get_status_name($code);
				if ('' !== $name) {
					return sanitize_key($name);
				}
			}
		}

		switch ($code) {
			case 0:
				return 'pending';
			case 1:
				return 'approved';
			case 2:
				return 'cancelled';
		}
		return '';
	}

	private static function resolve_withdraw_collection(array $args): array
	{
		if (! function_exists('dokan')) {
			return [];
		}

		$app = dokan();
		if (! is_object($app) || ! isset($app->withdraw) || ! is_object($app->withdraw)) {
			return [];
		}

		$result = null;
		if (method_exists($app->withdraw, 'all')) {
			$result = $app->withdraw->all($args);
		} elseif (method_exists($app->withdraw, 'get_withdraw_requests')) {
			$result = $app->withdraw->get_withdraw_requests(
				$args['user_id'] ?? '',
				$args['status'] ?? 0,
				$args['limit'] ?? 20,
				$args['offset'] ?? 0
			);
		}

		if (is_object($result) && isset($result->withdraws) && is_array($result->withdraws)) {
			return $result->withdraws;
		}
		if (is_object($result) && isset($result->items) && is_array($result->items)) {
			return $result->items;
		}

		return is_array($result) ? $result : [];
	}

	// -------------------------------------------------------------------------
	// Filter helpers
	// -------------------------------------------------------------------------

	private static function matches_id_filter(array $config, string $config_key, int $actual_id): bool
	{
		if (! array_key_exists($config_key, $config)) {
			return true;
		}

		$selected = $config[$config_key];
		if (self::is_any_selection($selected)) {
			return true;
		}

		$selected_id = self::parse_positive_int($selected);
		if ($selected_id <= 0) {
			return true;
		}
		if ($actual_id <= 0) {
			return false;
		}

		return $selected_id === $actual_id;
	}

	private static function is_any_selection($value): bool
	{
		if (null === $value || false === $value) {
			return true;
		}

		if (is_string($value)) {
			$normalized = sanitize_key(trim($value));
			return '' === $normalized || 'any' === $normalized;
		}

		if (is_array($value)) {
			foreach (['value', 'name', 'id'] as $key) {
				if (array_key_exists($key, $value) && self::is_any_selection($value[$key])) {
					return true;
				}
			}
			return false;
		}

		if (is_object($value)) {
			foreach (['value', 'name', 'id'] as $key) {
				if (isset($value->{$key}) && self::is_any_selection($value->{$key})) {
					return true;
				}
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Integer parsing
	// -------------------------------------------------------------------------

	private static function parse_positive_int($value): int
	{
		if (is_int($value) || is_float($value)) {
			$parsed = (int) $value;
			return $parsed > 0 ? $parsed : 0;
		}

		if (is_string($value)) {
			$value = trim($value);
			if ('' === $value || 'any' === strtolower($value)) {
				return 0;
			}
			if (is_numeric($value)) {
				$parsed = (int) $value;
				return $parsed > 0 ? $parsed : 0;
			}
			if (preg_match('/\d+/', $value, $matches)) {
				$parsed = (int) $matches[0];
				return $parsed > 0 ? $parsed : 0;
			}
			return 0;
		}

		if (is_array($value)) {
			foreach (['id', 'ID', 'value', 'name', 'vendor_id', 'product_id', 'withdraw_id', 'user_id'] as $key) {
				if (array_key_exists($key, $value)) {
					return self::parse_positive_int($value[$key]);
				}
			}
			return isset($value[0]) ? self::parse_positive_int($value[0]) : 0;
		}

		if (is_object($value)) {
			if (method_exists($value, 'get_id')) {
				return self::parse_positive_int($value->get_id());
			}
			foreach (['ID', 'id', 'value', 'name', 'vendor_id', 'product_id', 'withdraw_id', 'user_id'] as $key) {
				if (isset($value->{$key})) {
					return self::parse_positive_int($value->{$key});
				}
			}
			return 0;
		}

		$parsed = (int) $value;
		return $parsed > 0 ? $parsed : 0;
	}

	// -------------------------------------------------------------------------
	// Response helpers
	// -------------------------------------------------------------------------

	private static function main_response(array $data): array
	{
		return ['port' => 'main', 'data' => $data];
	}

	private static function error_response(string $message, array $input = []): array
	{
		return [
			'port' => 'error',
			'data' => array_merge($input, ['error' => $message]),
		];
	}

    // -------------------------------------------------------------------------
    // NEW: Additional helpers for missing triggers
    // -------------------------------------------------------------------------

	/**
	 * Check if a user has the 'seller' role.
	 */
	private static function is_user_seller(int $user_id): bool
	{
		if (! function_exists('get_userdata')) {
			return false;
		}
		$user = get_userdata($user_id);
		if (! $user) {
			return false;
		}
		return in_array('seller', (array) $user->roles, true);
	}

	/**
	 * Extract extra vendor fields (payment, address, social, etc.) from raw data.
	 * Mimics DokanHelper::formatVendorData()'s extra fields.
	 */
	private static function extract_vendor_extra_fields(array $data): array
	{
		$extra = [];

		// Flatten payment methods
		if (isset($data['payment']) && is_array($data['payment'])) {
			foreach ($data['payment'] as $method => $fields) {
				if (is_array($fields)) {
					foreach ($fields as $key => $value) {
						$extra[$method . '_' . $key] = $value;
					}
				} else {
					$extra['payment_' . $method] = $fields;
				}
			}
		}

		// Flatten address
		if (isset($data['address']) && is_array($data['address'])) {
			foreach ($data['address'] as $key => $value) {
				$extra['address_' . $key] = $value;
			}
		}

		// Copy other scalar fields (skip arrays, social, links, etc.)
		foreach ($data as $key => $value) {
			if (in_array($key, ['payment', 'address', 'social', '_links', 'store_open_close'], true)) {
				continue;
			}
			if (is_scalar($value)) {
				$extra[$key] = $value;
			} elseif (is_array($value)) {
				// If array, implode (like the old helper)
				$extra[$key] = implode(',', $value);
			}
		}

		// Add EU fields if Germanized module is active
		$eu_fields = self::get_enabled_vendor_eu_fields();
		if (! empty($eu_fields)) {
			foreach ($eu_fields as $field) {
				$field_name = $field['name'];
				if ($field_name === 'eu_bank_name') {
					$extra[$field_name] = isset($data['bank_name']) ? $data['bank_name'] : '';
				} else {
					$extra[$field_name] = isset($data[$field_name]) ? $data[$field_name] : '';
				}
			}
		}

		// Add boolean flags
		$extra['enabled']  = isset($data['enabled']) ? (bool) $data['enabled'] : false;
		$extra['trusted']  = isset($data['trusted']) ? (bool) $data['trusted'] : false;
		$extra['featured'] = isset($data['featured']) ? (bool) $data['featured'] : false;

		return $extra;
	}

	/**
	 * Get enabled EU fields from Dokan Pro Germanized module.
	 * Mimics DokanHelper::getEnabledVendorEUFields().
	 */
	private static function get_enabled_vendor_eu_fields(string $type = null): array
	{
		$fields = [];

		// Check if Dokan Pro and Germanized module are active
		if (! function_exists('is_plugin_active')) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if (! is_plugin_active('dokan-pro/dokan-pro.php')) {
			return $fields;
		}
		if (! function_exists('dokan_pro') || ! dokan_pro()->module->is_active('germanized')) {
			return $fields;
		}

		// Use the Helper class from Dokan Pro if available
		if (class_exists('WeDevs\DokanPro\Modules\Germanized\Helper')) {
			$helper = 'WeDevs\DokanPro\Modules\Germanized\Helper';
			if ($type === 'user-to-vendor' && ! $helper::is_enabled_on_registration_form()) {
				return $fields;
			}
			$enabled_fields = $helper::is_fields_enabled_for_seller();
			if (! is_array($enabled_fields)) {
				return $fields;
			}
			// Mapping of field keys
			$field_map = [
				'company_name'       => 'company_name',
				'company_id_number'  => 'company_id_number',
				'vat_number'         => 'vat_number',
				'bank_name'          => 'eu_bank_name', // special mapping
				'bank_iban'          => 'bank_iban',
			];
			foreach ($enabled_fields as $key => $enabled) {
				if (! $enabled) {
					continue;
				}
				$clean_key = str_replace('dokan_', '', $key);
				if (isset($field_map[$clean_key])) {
					$fields[] = [
						'name'  => $field_map[$clean_key],
						'type'  => 'text',
						'label' => ucwords(str_replace('_', ' ', $clean_key)),
					];
				}
			}
		}
		return $fields;
	}

	/**
	 * Build a refund payload similar to DokanHelper::formatRefundData().
	 */
	private static function build_refund_payload($refund): array
	{
		if (! is_object($refund)) {
			return [];
		}

		// Attempt to get refund ID
		$refund_id = 0;
		if (method_exists($refund, 'get_id')) {
			$refund_id = $refund->get_id();
		} elseif (isset($refund->id)) {
			$refund_id = (int) $refund->id;
		}
		if (! $refund_id) {
			return [];
		}

		// Get order ID and vendor ID
		$order_id  = method_exists($refund, 'get_order_id') ? $refund->get_order_id() : 0;
		$vendor_id = method_exists($refund, 'get_seller_id') ? $refund->get_seller_id() : 0;

		// Fetch order and vendor objects for more details
		$order = null;
		if (function_exists('dokan') && $order_id) {
			$order = dokan()->order->get($order_id);
		}
		$vendor = null;
		if (function_exists('dokan') && $vendor_id) {
			$vendor = dokan()->vendor->get($vendor_id)->to_array();
		}

		$payload = [
			'refund_id'           => $refund_id,
			'refund_amount'       => method_exists($refund, 'get_refund_amount') ? (float) $refund->get_refund_amount() : 0.0,
			'refund_reason'       => method_exists($refund, 'get_refund_reason') ? (string) $refund->get_refund_reason() : '',
			'refund_date'         => method_exists($refund, 'get_date') ? (string) $refund->get_date() : '',
			'order_id'            => $order_id,
			'order_status'        => $order ? (string) $order->get_status() : '',
			'order_currency'      => $order ? (string) $order->get_currency() : '',
			'order_subtotal'      => $order ? (float) $order->get_subtotal() : 0.0,
			'order_total'         => $order ? (float) $order->get_total() : 0.0,
			'order_total_tax'     => $order ? (float) $order->get_total_tax() : 0.0,
			'order_payment_method_title' => $order ? (string) $order->get_payment_method_title() : '',
			'order_transaction_id' => $order ? (string) $order->get_transaction_id() : '',
			'order_total_refunded' => $order ? (float) $order->get_total_refunded() : 0.0,
			'vendor_id'           => $vendor_id,
			'vendor_store_name'   => is_array($vendor) ? ($vendor['store_name'] ?? '') : '',
			'vendor_shop_url'     => is_array($vendor) ? ($vendor['shop_url'] ?? '') : '',
			'vendor_first_name'   => is_array($vendor) ? ($vendor['first_name'] ?? '') : '',
			'vendor_last_name'    => is_array($vendor) ? ($vendor['last_name'] ?? '') : '',
			'vendor_email'        => is_array($vendor) ? ($vendor['email'] ?? '') : '',
			'vendor_phone'        => is_array($vendor) ? ($vendor['phone'] ?? '') : '',
		];

		// Add vendor payload if vendor_id is valid
		if ($vendor_id) {
			$payload['vendor'] = self::build_vendor_payload_from_id($vendor_id);
		}

		return $payload;
	}
}
