<?php

namespace Zaplane\Modules\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractAjaxHandler
{
    protected string $action;
    protected bool $requireAuth = true;
    protected string $capability = 'manage_options';
    protected bool $requireNonce = true;

    public function register()
    {
        if (empty($this->action)) {
            throw new \Exception('Ajax action must be defined');
        }

        add_action("wp_ajax_{$this->action}", [$this, 'handleRequest']);

        if (!$this->requireAuth) {
            add_action("wp_ajax_nopriv_{$this->action}", [$this, 'handleRequest']);
        }
    }

    public function handleRequest()
    {
        try {
            if ($this->requireNonce) {
                $this->verifyNonce();
            }

            if ($this->requireAuth) {
                $this->checkPermissions();
            }

            $params = $this->sanitizeAndValidateParams();
            $result = $this->handle($params);

            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ], $this->getHttpStatusCode($e));
        }
    }

    protected function verifyNonce()
    {
        $security = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';

        if (!wp_verify_nonce($security, 'zaplane_nonce')) {
            throw new \Exception('Invalid nonce', 403);
        }
    }

    protected function checkPermissions()
    {
        if (!current_user_can($this->capability)) {
            throw new \Exception('Insufficient permissions', 403);
        }
    }

    protected function sanitizeAndValidateParams(): array
    {
        $params = [];
        $rules = $this->getValidationRules();

        foreach ($rules as $field => $rule) {
            $value = $_POST[$field] ?? null;

            if ($rule['required'] && ($value === null || $value === '')) {
                throw new \Exception(sprintf(__('%s is required', 'zaplane'), $field), 400);
            }

            if ($value !== null && $value !== '') {
                $params[$field] = $this->sanitizeValue($value, $rule['type']);

                if (isset($rule['validate']) && is_callable($rule['validate'])) {
                    if (!$rule['validate']($params[$field])) {
                        throw new \Exception(sprintf(__('Invalid value for %s', 'zaplane'), $field), 400);
                    }
                }
            } elseif (isset($rule['default'])) {
                $params[$field] = $rule['default'];
            }
        }

        return $params;
    }

    protected function sanitizeValue($value, string $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return absint($value);

            case 'float':
            case 'decimal':
                return floatval($value);

            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'email':
                return sanitize_email($value);

            case 'url':
                return esc_url_raw($value);

            case 'text':
            case 'string':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'html':
                return wp_kses_post($value);

            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Invalid JSON format', 400);
                    }
                    return $decoded;
                }
                return $value;

            case 'array':
                if (!is_array($value)) {
                    throw new \Exception('Value must be an array', 400);
                }
                return array_map('sanitize_text_field', $value);

            default:
                return sanitize_text_field($value);
        }
    }

    protected function getHttpStatusCode(\Exception $e): int
    {
        $code = $e->getCode();
        return in_array($code, [400, 401, 403, 404, 500]) ? $code : 500;
    }

    abstract protected function getValidationRules(): array;

    abstract protected function handle(array $params);
}
