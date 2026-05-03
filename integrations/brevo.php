<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (! defined('ABSPATH')) {
    exit;
}

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

    // -------------------------------------------------------------------------
    // Triggers / Actions
    // -------------------------------------------------------------------------

    public static function get_triggers(): array
    {
        return [];
    }

    public static function get_actions(): array
    {
        return [
            'create_contact'          => ['label' => 'Create Contact'],
            'add_contact_to_list'     => ['label' => 'Add Contact to List'],
            'delete_contact'          => ['label' => 'Delete Contact'],
            'remove_contact_from_list' => ['label' => 'Remove Contact From List'],
        ];
    }

    // -------------------------------------------------------------------------
    // Action config schemas
    // -------------------------------------------------------------------------

    public static function get_action_config_schema(string $action): array
    {
        $list_id_field = [
            'key'      => 'list_id',
            'label'    => 'List ID',
            'type'     => 'number',
            'required' => true,
        ];

        $emails_field = [
            'key'         => 'emails',
            'label'       => 'Email Address(es)',
            'type'        => 'text',
            'required'    => true,
            'description' => 'Comma-separated list for multiple addresses.',
        ];

        if ('create_contact' === $action) {
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
                $list_id_field,
                [
                    'key'         => 'attributes',
                    'label'       => 'Custom Attributes',
                    'type'        => 'key_value',
                    'description' => 'Extra contact attributes sent under the "attributes" key (e.g. COMPANY → Acme).',
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
        }

        if ('add_contact_to_list' === $action) {
            return [$list_id_field, $emails_field];
        }

        if ('delete_contact' === $action) {
            return [
                [
                    'key'      => 'email',
                    'label'    => 'Email',
                    'type'     => 'text',
                    'required' => true,
                ],
            ];
        }

        if ('remove_contact_from_list' === $action) {
            return [
                $list_id_field,
                $emails_field,
                [
                    'key'         => 'remove_all',
                    'label'       => 'Remove All Contacts',
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When enabled, every contact in the list is removed (emails field is ignored).',
                ],
            ];
        }

        return [];
    }

    // -------------------------------------------------------------------------
    // Trigger (no-op – no triggers defined)
    // -------------------------------------------------------------------------

    public static function resolve_trigger(array $node, array $args)
    {
        return ['message' => $args[0] ?? ''];
    }

    // -------------------------------------------------------------------------
    // Action dispatcher
    // -------------------------------------------------------------------------

    public static function execute_node(array $node, array $input): array
    {
        $action      = $node['data']['event'] ?? '';
        $credentials = $node['_connection_credentials'] ?? null;

        if (! $credentials) {
            throw new \Exception('No connection credentials available for Brevo.');
        }

        $api_key = $credentials['api_key'] ?? '';

        if (empty($api_key)) {
            throw new \Exception('Brevo API key is missing.');
        }

        switch ($action) {
            case 'create_contact':
                return self::action_create_contact($node, $input, $api_key);

            case 'add_contact_to_list':
                return self::action_add_contact_to_list($node, $input, $api_key);

            case 'delete_contact':
                return self::action_delete_contact($node, $input, $api_key);

            case 'remove_contact_from_list':
                return self::action_remove_contact_from_list($node, $input, $api_key);

            default:
                return [
                    'port' => 'main',
                    'data' => $input,
                ];
        }
    }

    // -------------------------------------------------------------------------
    // Action implementations
    // -------------------------------------------------------------------------

    /**
     * Create a new contact (or update if already exists).
     * POST /contacts
     */
    private static function action_create_contact(array $node, array $input, string $api_key): array
    {
        $config = $node['data']['config'] ?? [];

        $email      = $config['email'] ?? '';
        $first_name = $config['first_name'] ?? '';
        $last_name  = $config['last_name'] ?? '';
        $list_id    = isset($config['list_id']) ? (int) $config['list_id'] : null;
        $attributes = $config['attributes'] ?? [];
        $email_bl   = (bool) ($config['email_blacklisted'] ?? false);
        $sms_bl     = (bool) ($config['sms_blacklisted'] ?? false);

        if (! empty($first_name)) {
            $attributes['FIRSTNAME'] = $first_name;
        }

        if (! empty($last_name)) {
            $attributes['LASTNAME'] = $last_name;
        }

        $body = [
            'email'            => $email,
            'emailBlacklisted' => $email_bl,
            'smsBlacklisted'   => $sms_bl,
        ];

        if (! empty($attributes)) {
            $body['attributes'] = $attributes;
        }

        if ($list_id) {
            $body['listIds'] = [$list_id];
        }

        [$response_body, $status_code] = self::http_post(
            self::BASE_URL . '/contacts',
            $body,
            self::auth_headers($api_key)
        );

        // 201 = created, 204 = updated (already existed)
        if (! in_array($status_code, [201, 204], true)) {
            $message = $response_body['message'] ?? 'Unknown error';
            throw new \Exception('Brevo create_contact failed: ' . esc_html($message));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'brevo_contact_id' => $response_body['id'] ?? null,
                'brevo_email'      => $email,
            ]),
        ];
    }

    /**
     * Add one or more contacts (by email) to an existing list.
     * POST /contacts/lists/{listId}/contacts/add
     */
    private static function action_add_contact_to_list(array $node, array $input, string $api_key): array
    {
        $config  = $node['data']['config'] ?? [];
        $list_id = (int) ($config['list_id'] ?? 0);
        $emails  = self::parse_emails($config['emails'] ?? '');

        if (! $list_id) {
            throw new \Exception('Brevo add_contact_to_list: list_id is required.');
        }

        if (empty($emails)) {
            throw new \Exception('Brevo add_contact_to_list: at least one email address is required.');
        }

        [$response_body, $status_code] = self::http_post(
            self::BASE_URL . '/contacts/lists/' . $list_id . '/contacts/add',
            ['emails' => $emails],
            self::auth_headers($api_key)
        );

        if (! in_array($status_code, [200, 201, 204], true)) {
            $message = $response_body['message'] ?? 'Unknown error';
            throw new \Exception('Brevo add_contact_to_list failed: ' . esc_html($message));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'brevo_list_id'          => $list_id,
                'brevo_contacts_added'   => $response_body['contacts']['success'] ?? $emails,
                'brevo_contacts_failed'  => $response_body['contacts']['failure'] ?? [],
            ]),
        ];
    }

    /**
     * Delete a contact by email.
     * DELETE /contacts/{identifier}
     */
    private static function action_delete_contact(array $node, array $input, string $api_key): array
    {
        $config = $node['data']['config'] ?? [];
        $email  = trim($config['email'] ?? '');

        if (empty($email)) {
            throw new \Exception('Brevo delete_contact: email is required.');
        }

        [, $status_code] = self::http_delete(
            self::BASE_URL . '/contacts/' . rawurlencode($email),
            self::auth_headers($api_key)
        );

        // 204 = successfully deleted
        if (204 !== $status_code) {
            throw new \Exception('Brevo delete_contact failed with HTTP status: ' . esc_html((string) $status_code));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'brevo_deleted_email' => $email,
            ]),
        ];
    }

    /**
     * Remove contacts from a list, or remove all contacts from a list.
     * POST /contacts/lists/{listId}/contacts/remove
     */
    private static function action_remove_contact_from_list(array $node, array $input, string $api_key): array
    {
        $config     = $node['data']['config'] ?? [];
        $list_id    = (int) ($config['list_id'] ?? 0);
        $remove_all = (bool) ($config['remove_all'] ?? false);

        if (! $list_id) {
            throw new \Exception('Brevo remove_contact_from_list: list_id is required.');
        }

        if ($remove_all) {
            $body = ['all' => true];
        } else {
            $emails = self::parse_emails($config['emails'] ?? '');

            if (empty($emails)) {
                throw new \Exception('Brevo remove_contact_from_list: provide at least one email or enable "Remove All".');
            }

            $body = ['emails' => $emails];
        }

        [$response_body, $status_code] = self::http_post(
            self::BASE_URL . '/contacts/lists/' . $list_id . '/contacts/remove',
            $body,
            self::auth_headers($api_key)
        );

        if (! in_array($status_code, [200, 201, 204], true)) {
            $message = $response_body['message'] ?? 'Unknown error';
            throw new \Exception('Brevo remove_contact_from_list failed: ' . esc_html($message));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'brevo_list_id'           => $list_id,
                'brevo_contacts_removed'  => $response_body['contacts']['success'] ?? [],
                'brevo_contacts_failed'   => $response_body['contacts']['failure'] ?? [],
            ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Connection / auth
    // -------------------------------------------------------------------------

    public static function requires_connection(): bool
    {
        return true;
    }

    /**
     * Brevo authenticates solely via API key – no OAuth flow.
     */
    public static function get_auth_type(): string
    {
        return 'api_key';
    }

    public static function get_available_auth_types(): array
    {
        return [
            'api_key' => [
                'label'       => 'API Key',
                'description' => 'Authenticate using a Brevo API key. Generate one at app.brevo.com → SMTP & API → API Keys.',
            ],
        ];
    }

    public static function get_auth_fields(?string $auth_type = null): array
    {
        return [
            'api_key' => [
                'type'        => 'password',
                'label'       => 'API Key',
                'placeholder' => 'xkeysib-…',
                'required'    => true,
                'help'        => 'Go to app.brevo.com → Account → SMTP & API → API Keys and create or copy a key.',
            ],
        ];
    }

    public static function test_connection(array $credentials): array
    {
        $api_key = $credentials['api_key'] ?? '';

        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => 'No API key provided.',
                'details' => [],
            ];
        }

        $response = wp_remote_get(
            self::BASE_URL . '/account',
            [
                'headers' => self::auth_headers($api_key),
            ]
        );

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $response->get_error_message(),
                'details' => [],
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== (int) $status) {
            return [
                'success' => false,
                'message' => 'Brevo API error: ' . ($body['message'] ?? 'Unknown error'),
                'details' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Connected to Brevo account: ' . ($body['companyName'] ?? 'Unknown'),
            'details' => [
                'company_name' => $body['companyName'] ?? '',
                'email'        => $body['email'] ?? '',
                'plan'         => $body['plan'][0]['type'] ?? '',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Webhook (not supported by Brevo contact API – no-op implementations)
    // -------------------------------------------------------------------------

    public static function supports_webhook(): bool
    {
        return false;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build the common Brevo API request headers.
     */
    private static function auth_headers(string $api_key): array
    {
        return [
            'api-key'      => $api_key,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * Parse a comma-separated string of email addresses into a clean array.
     *
     * @return string[]
     */
    private static function parse_emails(string $raw): array
    {
        return array_values(
            array_filter(
                array_map('trim', explode(',', $raw))
            )
        );
    }

    /**
     * Perform an HTTP POST and return [decoded_body, http_status_code].
     *
     * @return array{ 0: array, 1: int }
     */
    protected static function http_post(string $url, array $body, array $headers): array
    {
        $response = wp_remote_post(
            $url,
            [
                'headers' => $headers,
                'body'    => wp_json_encode($body),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            throw new \Exception('Brevo HTTP request failed: ' . esc_html($response->get_error_message()));
        }

        $status        = (int) wp_remote_retrieve_response_code($response);
        $decoded_body  = json_decode(wp_remote_retrieve_body($response), true) ?? [];

        return [$decoded_body, $status];
    }

    /**
     * Perform an HTTP DELETE and return [decoded_body, http_status_code].
     *
     * @return array{ 0: array, 1: int }
     */
    protected static function http_delete(string $url, array $headers): array
    {
        $response = wp_remote_request(
            $url,
            [
                'method'  => 'DELETE',
                'headers' => $headers,
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            throw new \Exception('Brevo HTTP request failed: ' . esc_html($response->get_error_message()));
        }

        $status       = (int) wp_remote_retrieve_response_code($response);
        $decoded_body = json_decode(wp_remote_retrieve_body($response), true) ?? [];

        return [$decoded_body, $status];
    }
}
