<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Brevo extends IntegrationBase
{
    private const BASE_URL = 'https://api.brevo.com/v3';

    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    public static function get_slug(): string
    {
        return 'brevo';
    }

    public static function get_name(): string
    {
        return 'Brevo';
    }

    public static function get_icon(): string
    {
        return 'brevo.svg';
    }

    public static function get_category(): string
    {
        return 'email-marketing';
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    public static function requires_connection(): bool
    {
        return true;
    }

    public static function get_auth_type(): string
    {
        return 'api_key';
    }

    public static function get_available_auth_types(): array
    {
        return ['api_key'];
    }

    public static function get_auth_fields(?string $auth_type = null): array
    {
        return [
            [
                'key'         => 'api_key',
                'label'       => 'API Key',
                'type'        => 'password',
                'required'    => true,
                'placeholder' => 'xkeysib-…',
                'description' => 'Found under Settings → SMTP & API in your Brevo account.',
            ],
        ];
    }

    public static function test_connection(array $credentials): array
    {
        $api_key = $credentials['api_key'] ?? (array_values($credentials)[0] ?? '');

        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => 'API key is missing.',
                'details' => [],
            ];
        }

        try {
            [$body, $status] = static::http_get(
                self::BASE_URL . '/account',
                static::build_headers($api_key)
            );

            if ($status === 200) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully as ' . ($body['email'] ?? 'unknown'),
                    'details' => $body,
                ];
            }

            return [
                'success' => false,
                'message' => 'Authentication failed (HTTP ' . $status . ')',
                'details' => $body,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => [],
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Triggers & Actions catalogue
    // -------------------------------------------------------------------------

    public static function get_triggers(): array
    {
        return [];
    }

    public static function get_actions(): array
    {
        return [
            [
                'key'         => 'create_contact',
                'label'       => 'Create Contact',
                'description' => 'Create a new contact in Brevo.',
            ],
            [
                'key'         => 'add_contact_to_list',
                'label'       => 'Add Contact to List',
                'description' => 'Add one or more contacts to a specific list.',
            ],
            [
                'key'         => 'delete_contact',
                'label'       => 'Delete Contact',
                'description' => 'Permanently delete a contact by email address.',
            ],
            [
                'key'         => 'remove_contact_from_list',
                'label'       => 'Remove Contact from List',
                'description' => 'Remove one or more contacts (or all) from a list.',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Config schemas
    // -------------------------------------------------------------------------

    public static function get_action_config_schema(string $action): array
    {
        $common_list_field = [
            'key'      => 'list_id',
            'label'    => 'List ID',
            'type'     => 'number',
            'required' => true,
        ];

        $email_field = [
            'key'         => 'emails',
            'label'       => 'Email Address(es)',
            'type'        => 'text',
            'required'    => true,
            'description' => 'Comma-separated for multiple addresses.',
        ];

        switch ($action) {
            case 'create_contact':
                return [
                    [
                        'key'      => 'email',
                        'label'    => 'Email',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'key'   => 'first_name',
                        'label' => 'First Name',
                        'type'  => 'text',
                    ],
                    [
                        'key'   => 'last_name',
                        'label' => 'Last Name',
                        'type'  => 'text',
                    ],
                    $common_list_field,
                    [
                        'key'         => 'attributes',
                        'label'       => 'Custom Attributes',
                        'type'        => 'key_value',
                        'description' => 'Extra contact attributes (key → value pairs).',
                    ],
                    [
                        'key'     => 'email_blacklisted',
                        'label'   => 'Email Blacklisted',
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                    [
                        'key'     => 'sms_blacklisted',
                        'label'   => 'SMS Blacklisted',
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ];

            case 'add_contact_to_list':
                return [$common_list_field, $email_field];

            case 'delete_contact':
                return [
                    [
                        'key'      => 'email',
                        'label'    => 'Email',
                        'type'     => 'text',
                        'required' => true,
                    ],
                ];

            case 'remove_contact_from_list':
                return [
                    $common_list_field,
                    $email_field,
                    [
                        'key'         => 'remove_all',
                        'label'       => 'Remove All Contacts',
                        'type'        => 'boolean',
                        'default'     => false,
                        'description' => 'When enabled, every contact in the list is removed.',
                    ],
                ];

            default:
                return [];
        }
    }

    // -------------------------------------------------------------------------
    // Node execution
    // -------------------------------------------------------------------------

    public static function execute_node(array $node, array $input): array
    {
        $action      = $node['action'] ?? '';
        $config      = $node['config'] ?? [];
        $credentials = $node['credentials'] ?? [];

        $api_key = $credentials['api_key'] ?? (array_values($credentials)[0] ?? '');
        if (empty($api_key)) {
            return [
                'port' => 'error',
                'data' => array_merge($input, ['error' => 'Missing Brevo API key.']),
            ];
        }

        $config = static::resolve_dynamic_values($config, $input);

        try {
            $headers = static::build_headers($api_key);
            $result  = static::dispatch($action, $config, $headers);

            return [
                'port' => 'main',
                'data' => array_merge($input, ['brevo_result' => $result]),
            ];
        } catch (\Exception $e) {
            return [
                'port' => 'error',
                'data' => array_merge($input, ['error' => $e->getMessage()]),
            ];
        }
    }

    public static function get_output_ports(): array
    {
        return ['main', 'error'];
    }

	// -------------------------------------------------------------------------
	// Action dispatcher
	// -------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    private static function dispatch(string $action, array $config, array $headers): array
    {
        switch ($action) {
            case 'create_contact':
                return static::create_contact($config, $headers);
            case 'add_contact_to_list':
                return static::add_contact_to_list($config, $headers);
            case 'delete_contact':
                return static::delete_contact($config, $headers);
            case 'remove_contact_from_list':
                return static::remove_contact_from_list($config, $headers);
            default:
                throw new \Exception('Unknown Brevo action: ' . esc_html($action));
        }
    }

	// -------------------------------------------------------------------------
	// API methods
	// -------------------------------------------------------------------------

    /**
     * POST /contacts
     *
     * @throws \Exception
     */
    private static function create_contact(array $config, array $headers): array
    {
        $email = trim($config['email'] ?? '');
        if (empty($email)) {
            throw new \Exception('Email is required to create a contact.');
        }

        $body = [
            'email'            => $email,
            'list_ids'          => isset($config['list_id']) ? [(int) $config['list_id']] : [],
            'attributes'       => $config['attributes'] ?? [],
            'email_blacklisted' => isset($config['email_blacklisted']) ? (bool) $config['email_blacklisted'] : null,
            'sms_blacklisted'   => isset($config['sms_blacklisted']) ? (bool) $config['sms_blacklisted'] : null,
        ];

        if (! empty($config['first_name'])) {
            $body['attributes']['first_name'] = $config['first_name'];
        }
        if (! empty($config['last_name'])) {
            $body['attributes']['last_name'] = $config['last_name'];
        }
        if (empty($body['attributes'])) {
            unset($body['attributes']);
        }

        $body = static::filter_empty($body);

        [$response, $status] = static::http_request('POST', self::BASE_URL . '/contacts', [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        return static::build_result($status, $response, $body);
    }

    /**
     * POST /contacts/lists/{listId}/contacts/add
     *
     * @throws \Exception
     */
    private static function add_contact_to_list(array $config, array $headers): array
    {
        $list_id = (int) ($config['list_id'] ?? 0);
        if ($list_id <= 0) {
            throw new \Exception('A valid List ID is required.');
        }

        $emails = static::parse_emails($config['emails'] ?? '');
        if (empty($emails)) {
            throw new \Exception('At least one valid email address is required.');
        }

        $body = ['emails' => $emails];
        $url  = self::BASE_URL . '/contacts/lists/' . $list_id . '/contacts/add';

        [$response, $status] = static::http_request('POST', $url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        return static::build_result($status, $response, $body);
    }

    /**
     * DELETE /contacts/{email}
     *
     * @throws \Exception
     */
    private static function delete_contact(array $config, array $headers): array
    {
        $email = trim($config['email'] ?? '');
        if (empty($email)) {
            throw new \Exception('Email is required to delete a contact.');
        }
        $email = sanitize_email($email);
        if (! is_email($email)) {
            throw new \Exception('Invalid email address provided.');
        }

        $url = self::BASE_URL . '/contacts/' . rawurlencode($email);
        [$response, $status] = static::http_request('DELETE', $url, ['headers' => $headers]);

        return static::build_result($status, $response, ['email' => $email]);
    }

    /**
     * POST /contacts/lists/{listId}/contacts/remove
     *
     * @throws \Exception
     */
    private static function remove_contact_from_list(array $config, array $headers): array
    {
        $list_id = (int) ($config['list_id'] ?? 0);
        if ($list_id <= 0) {
            throw new \Exception('A valid List ID is required.');
        }

        $remove_all = filter_var($config['remove_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $url        = self::BASE_URL . '/contacts/lists/' . $list_id . '/contacts/remove';

        if ($remove_all) {
            $body = ['all' => true];
        } else {
            $emails = static::parse_emails($config['emails'] ?? '');
            if (empty($emails)) {
                throw new \Exception('At least one valid email address is required when "Remove All" is disabled.');
            }
            $body = ['emails' => $emails];
        }

        [$response, $status] = static::http_request('POST', $url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        return static::build_result($status, $response, $body);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function build_headers(string $api_key): array
    {
        return [
            'accept'       => 'application/json',
            'api-key'      => $api_key,
            'content-type' => 'application/json',
        ];
    }

    /**
     * @param string|array $emails
     * @return string[]
     */
    private static function parse_emails($emails): array
    {
        if (is_array($emails)) {
            $email_list = $emails;
        } else {
            $email_list = array_map('trim', explode(',', (string) $emails));
        }

        $valid = [];
        foreach ($email_list as $email) {
            $sanitized = sanitize_email(trim($email));
            if (is_email($sanitized)) {
                $valid[] = $sanitized;
            }
        }
        return $valid;
    }

    /**
     * Remove null, empty string, and empty array values recursively.
     */
    private static function filter_empty(array $data): array
    {
        return array_filter($data, function ($v) {
            if (is_array($v)) {
                $v = static::filter_empty($v);
                return ! empty($v);
            }
            return $v !== null && $v !== '' && $v !== [];
        });
    }

    /**
     * @throws \Exception
     */
    private static function build_result(int $status, $response, array $payload): array
    {
        if ($status < 200 || $status >= 300) {
            $message = $response['message'] ?? sprintf('Brevo API error (HTTP %d)', $status);
            throw new \Exception(esc_html($message));
        }

        return [
            'status_code' => $status,
            'payload'     => $payload,
            'response'    => $response,
        ];
    }

    /**
     * Replace {{placeholder}} tokens in config values with data from $input.
     */
    private static function resolve_dynamic_values(array $config, array $input): array
    {
        array_walk_recursive($config, function (&$value) use ($input) {
            if (is_string($value)) {
                $value = preg_replace_callback(
                    '/\{\{([\w.]+)\}\}/',
                    function ($matches) use ($input) {
                        $path    = explode('.', $matches[1]);
                        $current = $input;
                        foreach ($path as $segment) {
                            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                                return $matches[0];
                            }
                            $current = $current[$segment];
                        }
                        return is_scalar($current) ? (string) $current : $matches[0];
                    },
                    $value
                );
            }
        });
        return $config;
    }
}
