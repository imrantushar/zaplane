<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (! defined('ABSPATH')) {
    exit;
}

class mailerlite extends IntegrationBase
{


    private const API_BASE_URL = 'https://connect.mailerlite.com/api/';

    public static function get_slug(): string
    {
        return 'mailerlite';
    }

    public static function get_name(): string
    {
        return 'Mailer Lite';
    }

    public static function get_icon(): string
    {
        return 'mailerlite.svg';
    }

    public static function get_triggers(): array
    {
        return [];
    }

    public static function get_actions(): array
    {
        return [
            'create_subscriber'             => ['label' => 'Create Subscriber'],
            'update_subscriber'             => ['label' => 'Update Subscriber'],
            'add_subscriber_to_group'       => ['label' => 'Add Subscriber to Group'],
            'remove_subscriber_from_group'  => ['label' => 'Remove Subscriber from Group'],
            'delete_subscriber'             => ['label' => 'Delete Subscriber'],
        ];
    }

    public static function get_action_config_schema(string $action): array
    {
        $email_field = [
            'key'      => 'email',
            'label'    => 'Email',
            'type'     => 'email',
            'required' => true,
        ];

        $group_id_field = [
            'key'      => 'group_id',
            'label'    => 'Group ID',
            'type'     => 'text',
            'required' => true,
        ];

        $common_subscriber_fields = [
            $email_field,
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
            [
                'key'         => 'fields',
                'label'       => 'Custom Fields',
                'type'        => 'key_value',
                'description' => 'Extra subscriber fields (key → value pairs).',
            ],
        ];

        if ('create_subscriber' === $action) {
            return [                [
                    'key'         => 'groups',
                    'label'       => 'Group IDs',
                    'type'        => 'text',
                    'description' => 'Comma-separated group IDs to add the subscriber to.',
                ],
                [
                    'key'     => 'status',
                    'label'   => 'Status',
                    'type'    => 'select',
                    'options' => [
                        'active'       => 'Active',
                        'unsubscribed' => 'Unsubscribed',
                        'unconfirmed'  => 'Unconfirmed',
                    ],
                    'default' => 'active',
                ],
            ];
        }

        if ('update_subscriber' === $action) {
            return [                [
                    'key'     => 'status',
                    'label'   => 'Status',
                    'type'    => 'select',
                    'options' => [
                        'active'       => 'Active',
                        'unsubscribed' => 'Unsubscribed',
                        'unconfirmed'  => 'Unconfirmed',
                    ],
                    'default' => 'active',
                ],
            ];
        }

        if ('add_subscriber_to_group' === $action) {
            return [$email_field, $group_id_field];
        }

        if ('remove_subscriber_from_group' === $action) {
            return [$email_field, $group_id_field];
        }

        if ('delete_subscriber' === $action) {
            return [$email_field];
        }
        return [];
    }

    public static function resolve_trigger(array $node, array $args)
    {
        return ['message' => $args[0] ?? ''];
    }

    public static function execute_node(array $node, array $input): array
    {
        $action = $node['data']['event'] ?? '';
        $credentials = $node['_connection_credentials'] ?? null;

        if (! $credentials) {
            throw new \Exception('No connection credentials available for Slack');
        }

        $token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';

        if (empty($token)) {
            throw new \Exception('Slack access token is missing');
        }

        if ('create_subscriber' === $action) {
            return self::action_create_subscriber($node, $input, $token);
        }

        if ('update_subscriber' === $action) {
            return self::action_update_subscriber($node, $input, $token);
        }

        if ('add_subscriber_to_group' === $action) {
            return self::action_add_subscriber_to_group($node, $input, $token);
        }

        if ('delete_subscriber' === $action) {
            return self::action_delete_subscriber($node, $input, $token);
        }

        return [
            'port' => 'main',
            'data' => $input,
        ];
    }

    private static function action_create_subscriber(array $node, array $input, string $token): array
    {
        $config = $node['data']['config'] ?? [];
        $body   = self::build_subscriber_body($config);

        $groups = self::parse_ids($config['groups'] ?? '');
        if (! empty($groups)) {
            $body['groups'] = $groups;
        }

        [$response_body, $status_code] = self::https_post(
            self::BASE_URL . '/subscribers',
            $body,
            self::auth_headers($api_key)
        );

        // 200 = updated existing, 201 = created new
        if (! in_array($status_code, [200, 201], true)) {
            $message = $response_body['message'] ?? 'Unknown error';
            throw new \Exception('MailerLite create_subscriber failed: ' . esc_html($message));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'mailerlite_subscriber_id' => $response_body['data']['id'] ?? null,
                'mailerlite_email'         => $response_body['data']['email'] ?? $config['email'] ?? '',
                'mailerlite_status'        => $response_body['data']['status'] ?? '',
            ]),
        ];
    }

    private static function action_update_subscriber(array $node, array $input, string $token): array
    {

        $config = $node['data']['config'] ?? [];
        $email  = trim($config['email'] ?? '');

        if (empty($email)) {
            throw new \Exception('MailerLite update_subscriber: email is required.');
        }

        // Resolve email → subscriber ID
        $subscriber_id = self::fetch_subscriber_id($email, $api_key);

        $body = self::build_subscriber_body($config);

        [$response_body, $status_code] = self::https_put(
            self::BASE_URL . '/subscribers/' . rawurlencode($subscriber_id),
            $body,
            self::auth_headers($api_key)
        );

        if (200 !== $status_code) {
            $message = $response_body['message'] ?? 'Unknown error';
            throw new \Exception('MailerLite update_subscriber failed: ' . esc_html($message));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'mailerlite_subscriber_id' => $response_body['data']['id'] ?? $subscriber_id,
                'mailerlite_email'         => $response_body['data']['email'] ?? $email,
                'mailerlite_status'        => $response_body['data']['status'] ?? '',
            ]),
        ];
    }
        private static function action_add_subscriber_to_group(array $node, array $input, string $token): array
    {

        $config   = $node['data']['config'] ?? [];
        $email    = trim($config['email'] ?? '');
        $group_id = trim($config['group_id'] ?? '');

        if (empty($email)) {
            throw new \Exception('MailerLite add_subscriber_to_group: email is required.');
        }

        if (empty($group_id)) {
            throw new \Exception('MailerLite add_subscriber_to_group: group_id is required.');
        }

        $subscriber_id = self::fetch_subscriber_id($email, $api_key);

        [$response_body, $status_code] = self::https_post(
            self::BASE_URL . '/subscribers/' . rawurlencode($subscriber_id) . '/groups/' . rawurlencode($group_id),
            [],
            self::auth_headers($api_key)
        );

        if (200 !== $status_code) {
            $message = $response_body['message'] ?? 'Unknown error';
            throw new \Exception('MailerLite add_subscriber_to_group failed: ' . esc_html($message));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'mailerlite_subscriber_id' => $subscriber_id,
                'mailerlite_group_id'      => $group_id,
            ]),
        ];
    }

        private static function action_remove_subscriber_from_group(array $node, array $input, string $token): array
    {

        $config   = $node['data']['config'] ?? [];
        $email    = trim($config['email'] ?? '');
        $group_id = trim($config['group_id'] ?? '');

        if (empty($email)) {
            throw new \Exception('MailerLite remove_subscriber_from_group: email is required.');
        }

        if (empty($group_id)) {
            throw new \Exception('MailerLite remove_subscriber_from_group: group_id is required.');
        }

        $subscriber_id = self::fetch_subscriber_id($email, $api_key);

        [, $status_code] = self::https_delete(
            self::BASE_URL . '/subscribers/' . rawurlencode($subscriber_id) . '/groups/' . rawurlencode($group_id),
            self::auth_headers($api_key)
        );

        if (204 !== $status_code) {
            throw new \Exception('MailerLite remove_subscriber_from_group failed with HTTP status: ' . esc_html((string) $status_code));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'mailerlite_subscriber_id' => $subscriber_id,
                'mailerlite_group_id'      => $group_id,
            ]),
        ];
    }

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
        return [
            'api_key' => [
                'label'       => 'Bot Token',
                'description' => 'Use a Bot User OAuth Token directly. Requires creating a Slack App.',
            ],
        ];
    }

    public static function get_auth_fields(?string $auth_type = null): array
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
            self::BASE_URL . '/me',
            [
                'headers' => self::auth_headers($api_key),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $response->get_error_message(),
                'details' => [],
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $status) {
            return [
                'success' => false,
                'message' => 'MailerLite API error: ' . ($body['message'] ?? 'Unknown error'),
                'details' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Connected to MailerLite account: ' . ($body['data']['email'] ?? 'Unknown'),
            'details' => [
                'account_id' => $body['data']['id'] ?? '',
                'email'      => $body['data']['email'] ?? '',
                'plan'       => $body['data']['account']['plan']['name'] ?? '',
            ],
        ];
    }


    public static function test_connection(array $credentials): array
    {

        $token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'No access token or bot token provided',
                'details' => [],
            ];
        }

        if (isset($credentials['bot_token']) && strpos($token, 'xoxb-') !== 0) {
            return [
                'success' => false,
                'message' => 'Invalid token format. Bot tokens should start with xoxb-',
                'details' => [],
            ];
        }

        $response = wp_remote_get(
            self::API_BASE_URL . '/auth.test',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $response->get_error_message(),
                'details' => [],
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['ok'])) {
            return [
                'success' => false,
                'message' => 'Slack API error: ' . ($body['error'] ?? 'Unknown error'),
                'details' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Connected to workspace: ' . ($body['team'] ?? 'Unknown'),
            'details' => [
                'team'    => $body['team'] ?? '',
                'user'    => $body['user'] ?? '',
                'user_id' => $body['user_id'] ?? '',
                'team_id' => $body['team_id'] ?? '',
                'url'     => $body['url'] ?? '',
            ],
        ];
    }

    public static function get_oauth_scopes(): array
    {
        return [
            'chat:write',
            'channels:read',
            'users:read',
            'im:write',
        ];
    }

    private static function action_add_subscriber_to_group(array $node, array $input, string $token): array
    {
        $name       = $node['data']['config']['name'] ?? '';
        $is_private = ($node['data']['config']['is_private'] ?? 'false') === 'true';

        [$body, $status] = self::http_post(
            self::API_BASE_URL . '/conversations.create',
            [
                'name'       => $name,
                'is_private' => $is_private,
            ],
            ['Authorization' => 'Bearer ' . $token]
        );

        if (empty($body['ok'])) {
            throw new \Exception('Slack API error: ' . esc_html($body['error'] ?? 'Unknown error'));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'channel_id'   => $body['channel']['id'] ?? '',
                'channel_name' => $body['channel']['name'] ?? '',
            ]),
        ];
    }

    private static function action_delete_subscriber(array $node, array $input, string $token): array
    {
        $channel  = $node['data']['config']['channel'] ?? '';
        $user_ids = $node['data']['config']['user_ids'] ?? '';

        [$body] = self::http_post(
            self::API_BASE_URL . '/conversations.invite',
            [
                'channel' => $channel,
                'users'   => $user_ids,
            ],
            ['Authorization' => 'Bearer ' . $token]
        );

        if (empty($body['ok'])) {
            throw new \Exception('Slack API error: ' . esc_html($body['error'] ?? 'Unknown error'));
        }

        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'channel_id' => $body['channel']['id'] ?? '',
            ]),
        ];
    }

    public static function supports_webhook(): bool
    {
        return true;
    }



    public static function verify_webhook_signature(\WP_REST_Request $request): bool
    {
        $signing_secret = get_option('zaplane_slack_signing_secret', '');

        if (empty($signing_secret)) {
            return true;
        }

        $timestamp = $request->get_header('x-slack-request-timestamp');
        $signature = $request->get_header('x-slack-signature');

        if (! $timestamp || ! $signature) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $base_string    = 'v0:' . $timestamp . ':' . $request->get_body();
        $expected       = 'v0=' . hash_hmac('sha256', $base_string, $signing_secret);

        return hash_equals($expected, $signature);
    }



    public static function parse_webhook_event(\WP_REST_Request $request): ?array
    {
        $body = $request->get_json_params();
        $type = $body['type'] ?? '';

        if ('url_verification' === $type) {
            return null;
        }

        if ('event_callback' !== $type) {
            return null;
        }

        $event      = $body['event'] ?? [];
        $event_type = $event['type'] ?? '';

        $map = [
            'message'         => 'message_received',
            'app_mention'     => 'app_mention',
            'reaction_added'  => 'reaction_added',
            'channel_created' => 'channel_created',
            'file_shared'     => 'file_shared',
        ];

        $normalized = $map[$event_type] ?? null;
        if (! $normalized) {
            return null;
        }

        return [
            'event'   => $normalized,
            'payload' => array_merge($event, [
                'team_id'    => $body['team_id'] ?? '',
                'api_app_id' => $body['api_app_id'] ?? '',
            ]),
        ];
    }
}
