<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
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

    /**
     * Fields shown in the connection / credential form.
     */
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

    /**
     * Ping Brevo to confirm the key works.
     *
     * Handles both keyed ['api_key' => '…'] and indexed [0 => '…'] credentials
     * arrays, since the Zaplane ConnectionManager may pass either shape.
     *
     * @param array $credentials
     */
    public static function test_connection(array $credentials): array
    {
        // Normalise: accept ['api_key' => '...'] or [0 => '...'] (indexed).
        $api_key = $credentials['api_key'] ?? (array_values($credentials)[0] ?? '');

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
        return []; // Brevo is action-only in this integration
    }

    public static function get_actions(): array
    {
        return [
            [
                'key'         => 'createContact',
                'label'       => 'Create Contact',
                'description' => 'Create a new contact in Brevo.',
            ],
            [
                'key'         => 'addContactToList',
                'label'       => 'Add Contact to List',
                'description' => 'Add one or more contacts to a specific list.',
            ],
            [
                'key'         => 'deleteContact',
                'label'       => 'Delete Contact',
                'description' => 'Permanently delete a contact by email address.',
            ],
            [
                'key'         => 'removeContactFromList',
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
            case 'createContact':
                return [
                    [
                        'key'      => 'email',
                        'label'    => 'Email',
                        'type'     => 'text',
                        'required' => true,
                    ],
                    [
                        'key'   => 'firstName',
                        'label' => 'First Name',
                        'type'  => 'text',
                    ],
                    [
                        'key'   => 'lastName',
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
                        'key'     => 'emailBlacklisted',
                        'label'   => 'Email Blacklisted',
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                    [
                        'key'     => 'smsBlacklisted',
                        'label'   => 'SMS Blacklisted',
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ];

            case 'addContactToList':
                return [$common_list_field, $email_field];

            case 'deleteContact':
                return [
                    [
                        'key'      => 'email',
                        'label'    => 'Email',
                        'type'     => 'text',
                        'required' => true,
                    ],
                ];

            case 'removeContactFromList':
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

    /**
     * Called by the Zaplane engine for every action node.
     *
     * @param array $node   Node definition – must contain 'action', 'config', and 'credentials'.
     * @param array $input  Runtime data passed from the previous node.
     *
     * @return array  ['port' => 'main'|'error', 'data' => […]]
     */
    public static function execute_node(array $node, array $input): array
    {
        $action      = $node['action']      ?? '';
        $config      = $node['config']      ?? [];
        $credentials = $node['credentials'] ?? [];
        $api_key     = $credentials['api_key'] ?? (array_values($credentials)[0] ?? '');

        // Resolve dynamic placeholders in config values using runtime $input.
        $config = static::resolve_dynamic_values($config, $input);

        try {
            $headers  = static::build_headers($api_key);
            $result   = static::dispatch($action, $config, $headers);

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
     * Route to the correct Brevo API call.
     *
     * @throws \Exception on unexpected action slug.
     */
    private static function dispatch(string $action, array $config, array $headers): array
    {
        switch ($action) {
            case 'createContact':
                return static::create_contact($config, $headers);

            case 'addContactToList':
                return static::add_contact_to_list($config, $headers);

            case 'deleteContact':
                return static::delete_contact($config, $headers);

            case 'removeContactFromList':
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
     */
    private static function create_contact(array $config, array $headers): array
    {
        $body = static::filter_empty([
            'email'            => $config['email']            ?? '',
            'listIds'          => isset($config['list_id']) ? [(int) $config['list_id']] : [],
            'attributes'       => $config['attributes']       ?? [],
            'emailBlacklisted' => isset($config['emailBlacklisted'])
                ? (bool) $config['emailBlacklisted'] : null,
            'smsBlacklisted'   => isset($config['smsBlacklisted'])
                ? (bool) $config['smsBlacklisted'] : null,
        ]);

        // Merge first/last name into the attributes sub-object.
        foreach (['firstName' => 'FIRSTNAME', 'lastName' => 'LASTNAME'] as $cfg_key => $brevo_key) {
            if (! empty($config[$cfg_key])) {
                $body['attributes'][$brevo_key] = $config[$cfg_key];
            }
        }

        if (empty($body['attributes'])) {
            unset($body['attributes']);
        }

        [$response, $status] = static::http_request('POST', self::BASE_URL . '/contacts', [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        return static::build_result($status, $response, $body);
    }

    /**
     * POST /contacts/lists/{listId}/contacts/add
     */
    private static function add_contact_to_list(array $config, array $headers): array
    {
        $list_id = (int) ($config['list_id'] ?? 0);
        $emails  = static::parse_emails($config['emails'] ?? '');
        $body    = ['emails' => $emails];
        $url     = self::BASE_URL . '/contacts/lists/' . $list_id . '/contacts/add';

        [$response, $status] = static::http_request('POST', $url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        return static::build_result($status, $response, $body);
    }

    /**
     * DELETE /contacts/{email}
     */
    private static function delete_contact(array $config, array $headers): array
    {
        $email = sanitize_email($config['email'] ?? '');
        $url   = self::BASE_URL . '/contacts/' . rawurlencode($email);

        [$response, $status] = static::http_request('DELETE', $url, [
            'headers' => $headers,
        ]);

        return static::build_result($status, $response, ['email' => $email]);
    }

    /**
     * POST /contacts/lists/{listId}/contacts/remove
     */
    private static function remove_contact_from_list(array $config, array $headers): array
    {
        $list_id    = (int) ($config['list_id'] ?? 0);
        $remove_all = filter_var($config['remove_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $url        = self::BASE_URL . '/contacts/lists/' . $list_id . '/contacts/remove';

        $body = $remove_all
            ? ['all' => true]
            : ['emails' => static::parse_emails($config['emails'] ?? '')];

        [$response, $status] = static::http_request('POST', $url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        return static::build_result($status, $response, $body);
    }

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

    /**
     * Build the HTTP headers required by every Brevo API call.
     */
    private static function build_headers(string $api_key): array
    {
        return [
            'accept'       => 'application/json',
            'api-key'      => $api_key,
            'content-type' => 'application/json',
        ];
    }

    /**
     * Normalise a comma-separated email string or an already-parsed array.
     *
     * @param string|array $emails
     *
     * @return array
     */
    private static function parse_emails($emails): array
    {
        if (\is_array($emails)) {
            return array_map('sanitize_email', array_filter($emails));
        }

        return array_map(
            'sanitize_email',
            array_filter(array_map('trim', explode(',', (string) $emails)))
        );
    }

    /**
     * Remove null/empty-array keys so we don't send junk to the API.
     */
    private static function filter_empty(array $data): array
    {
        return array_filter($data, function ($v) {
            return $v !== null && $v !== [] && $v !== '';
        });
    }

    /**
     * Build a consistent result array for every action.
     */
    private static function build_result(int $status, $response, $payload): array
    {
        $success = $status >= 200 && $status < 300;

        if (! $success) {
            $message = $response['message'] ?? ('Brevo API error (HTTP ' . $status . ')');
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
            if (\is_string($value)) {
                $value = preg_replace_callback(
                    '/\{\{([\w.]+)\}\}/',
                    function ($matches) use ($input) {
                        $keys   = explode('.', $matches[1]);
                        $result = $input;
                        foreach ($keys as $key) {
                            $result = $result[$key] ?? $matches[0];
                            if (! \is_array($result)) {
                                break;
                            }
                        }

                        return \is_scalar($result) ? (string) $result : $matches[0];
                    },
                    $value
                );
            }
        });

        return $config;
    }
}
