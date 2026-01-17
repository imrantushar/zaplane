<?php

namespace Zaplane\Classes;

use Exception;
use Zaplane\Exceptions\ZaplaneException;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractRequestHandler
{
    /**
     * Default Nonce Action.
     */
    protected string $nonce_action = 'zaplane_nonce';

    /**
     * Request namespace.
     */
    protected string $namespace = 'zaplane';

    /**
     * Actions to handle.
     */
    protected array $actions = [];

    /**
     * Whether this is an AJAX request.
     */
    protected bool $is_ajax = false;

    /**
     * Constructor - child classes must implement to define actions.
     */
    abstract public function __construct();

    /**
     * Dispatch action hooks - child classes implement for ajax/post.
     */
    abstract public function dispatch_actions();

    /**
     * Check if current request is an AJAX action.
     */
    protected function is_ajax_action(): bool
    {
        return $this->is_ajax || str_starts_with(current_action(), 'wp_ajax_');
    }

    /**
     * Handle action callback.
     */
    final public function handle_request()
    {
        try {
            // No caching
            nocache_headers();

            $response = $this->prepare_response();

            if (is_wp_error($response)) {
                $this->respond_error($response);
            }

            $this->respond_success($response);
        } catch (ZaplaneException $e) {
            $this->respond_error($e->toWpError());
        } catch (Exception $e) {
            $this->respond_error(new WP_Error(
                'something_went_wrong',
                $e->getMessage(),
                ['code' => 500]
            ));
        }
    }

    /**
     * Prepare error response.
     */
    protected function respond_error(WP_Error $response)
    {
        $data = $response->get_error_data();

        if ($this->is_ajax_action()) {
            wp_send_json_error(
                $response->get_error_message(),
                $data['code'] ?? 400
            );
        } else {
            wp_die(
                esc_html($response->get_error_message()),
                esc_html($data['title'] ?? __('Something went wrong!', 'zaplane')),
                ['response' => absint($data['code'] ?? 400)]
            );
        }
    }

    /**
     * Prepare success response.
     */
    protected function respond_success($response)
    {
        if ($response !== null) {
            if ($this->is_ajax_action()) {
                wp_send_json_success($response);
            } elseif (is_string($response) && filter_var($response, FILTER_VALIDATE_URL)) {
                wp_safe_redirect($response);
                die();
            } else {
                wp_die('', '', ['response' => null]);
            }
        }
    }

    /**
     * Prepare response for the request.
     */
    protected function prepare_response()
    {
        $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        $action = explode($this->namespace . '/', $action)[1] ?? '';

        if (!isset($this->actions[$action])) {
            return new WP_Error(
                'invalid_action',
                __('Invalid action.', 'zaplane'),
                ['code' => 400, 'title' => __('Invalid action.', 'zaplane')]
            );
        }

        $details = $this->actions[$action];

        // Verify nonce
        $nonce = isset($_REQUEST['security']) ? sanitize_text_field(wp_unslash($_REQUEST['security'])) : '';
        if (empty($nonce) && isset($_REQUEST['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
        }

        if (!$nonce || !wp_verify_nonce($nonce, $this->nonce_action)) {
            return new WP_Error(
                'invalid_nonce',
                __('Invalid security token.', 'zaplane'),
                ['code' => rest_authorization_required_code(), 'title' => __('Invalid nonce.', 'zaplane')]
            );
        }

        // Check permissions
        if ($this->is_ajax_action()) {
            $allow_visitor = !empty($details['allow_visitor_action']);
            $capability = $details['capability'] ?? '';
            $has_permission = $this->check_ajax_permissions($capability, $allow_visitor);
        } else {
            $allow_visitor = !empty($details['allow_visitor_action']);
            $capability = $details['capability'] ?? 'manage_options';
            $has_permission = $this->check_post_permissions($capability, $allow_visitor);
        }

        if (is_wp_error($has_permission)) {
            return $has_permission;
        }

        // Validate callback
        if (empty($details['callback']) || !is_callable($details['callback'])) {
            return new WP_Error(
                'not_implemented',
                __('Requested method not implemented.', 'zaplane'),
                ['code' => 501, 'title' => __('Not implemented!', 'zaplane')]
            );
        }

        // Sanitize fields
        $payload = $this->sanitize_fields($details['fields'] ?? []);

        return $this->respond($details['callback'], $payload);
    }

    /**
     * Sanitize request fields.
     */
    protected function sanitize_fields(array $fields): array
    {
        $payload = [];

        foreach ($fields as $key => $type) {
            if (!isset($_REQUEST[$key])) {
                continue;
            }

            $payload[$key] = $this->sanitize_value($_REQUEST[$key], $type);
        }

        return $payload;
    }

    /**
     * Sanitize a single value based on type.
     */
    protected function sanitize_value($value, $type)
    {
        // Handle pipe syntax: decode|type
        $decode_type = null;
        if (is_string($type) && str_contains($type, '|')) {
            list($decode_type, $type) = explode('|', $type, 2);
        }

        // Handle nested arrays
        if (is_array($type)) {
            $value = wp_unslash($value);

            // Try to decode JSON if string
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $value = json_decode($value, true);
            }

            $result = [];
            foreach ($type as $subkey => $subtype) {
                if (isset($value[$subkey])) {
                    $result[$subkey] = $this->sanitize_value($value[$subkey], $subtype);
                }
            }
            return $result;
        }

        // Sanitize based on type
        $value = wp_unslash($value);

        switch (strtolower($type)) {
            case 'absint':
            case 'id':
                $sanitized = absint(sanitize_text_field($value));
                break;

            case 'int':
            case 'integer':
                $sanitized = intval(sanitize_text_field($value));
                break;

            case 'float':
            case 'double':
                $sanitized = floatval(sanitize_text_field($value));
                break;

            case 'bool':
            case 'boolean':
                $sanitized = (bool) filter_var(sanitize_text_field($value), FILTER_VALIDATE_BOOLEAN);
                break;

            case 'email':
                $sanitized = sanitize_email($value);
                break;

            case 'url':
                $sanitized = esc_url_raw($value);
                break;

            case 'slug':
                $sanitized = sanitize_title($value);
                break;

            case 'user':
                $sanitized = sanitize_user($value);
                break;

            case 'key':
                $sanitized = sanitize_key($value);
                break;

            case 'textarea':
                $sanitized = sanitize_textarea_field($value);
                break;

            case 'post':
            case 'html':
                $sanitized = wp_kses_post($value);
                break;

            case 'hex_color':
                $sanitized = sanitize_hex_color($value);
                break;

            case 'hex_color_no_hash':
                $sanitized = sanitize_hex_color_no_hash($value);
                break;

            case 'json':
                $sanitized = json_decode($value, true);
                break;

            case 'array':
                $sanitized = is_array($value) ? array_map('sanitize_text_field', $value) : [];
                break;

            case 'text':
            case 'string':
                $sanitized = sanitize_text_field($value);
                break;

            default:
                $sanitized = is_array($value)
                    ? wp_kses_post_deep($value)
                    : wp_kses_post(trim($value));
                break;
        }

        // Apply decode if specified
        if ($decode_type && !empty($sanitized)) {
            $sanitized = $this->maybe_decode($sanitized, $decode_type);
        }

        return $sanitized;
    }

    /**
     * Maybe decode value based on decode type.
     */
    protected function maybe_decode($value, string $type)
    {
        if (in_array($type, ['serialize', 'unserialize', 'php'])) {
            return maybe_unserialize($value);
        }

        if (in_array($type, ['json', 'array', 'object'])) {
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                return json_decode($value, $type === 'array');
            }
        }

        return $value;
    }

    /**
     * Call the action callback.
     */
    final protected function respond($callback, array $payload)
    {
        return call_user_func($callback, $payload);
    }

    /**
     * Check AJAX permissions.
     */
    protected function check_ajax_permissions(string $capability, bool $allow_visitors = false)
    {
        // Special case: only require login
        if ($capability === 'only_logged_in') {
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'forbidden_action',
                    __('You must be logged in to access this.', 'zaplane'),
                    ['code' => rest_authorization_required_code(), 'title' => __('Login required!', 'zaplane')]
                );
            }
            return true;
        }

        // Allow visitors OR check user capability
        if ((!is_user_logged_in() && !$allow_visitors) ||
            (is_user_logged_in() && $capability && !current_user_can($capability))) {
            return new WP_Error(
                'forbidden_action',
                __('You do not have permission to perform this action.', 'zaplane'),
                ['code' => rest_authorization_required_code(), 'title' => __('Insufficient permission!', 'zaplane')]
            );
        }

        return true;
    }

    /**
     * Check POST permissions.
     */
    protected function check_post_permissions(string $capability, bool $allow_visitors = false)
    {
        if ($allow_visitors) {
            return true;
        }

        // Special case: only require login
        if ($capability === 'only_logged_in') {
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'forbidden_action',
                    __('You must be logged in to access this.', 'zaplane'),
                    ['code' => rest_authorization_required_code(), 'title' => __('Login required!', 'zaplane')]
                );
            }
            return true;
        }

        if (!is_user_logged_in() || !current_user_can($capability)) {
            return new WP_Error(
                'forbidden_action',
                __('You do not have permission to perform this action.', 'zaplane'),
                ['code' => rest_authorization_required_code(), 'title' => __('Insufficient permission!', 'zaplane')]
            );
        }

        return true;
    }
}
