<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;

if (!defined('ABSPATH')) exit;

/**
 * WP-CLI command: wp zaplane make:integration
 *
 * Generates a scaffolded integration file from user input.
 *
 * Usage:
 *   wp zaplane make:integration
 *   wp zaplane make:integration --slug=mailchimp --type=external --auth=api_key
 */
class MakeIntegrationCommand extends Command
{
    protected string $signature = 'make:integration';
    protected string $description = 'Scaffold a new integration file with boilerplate code';

    public function handle(array $args, array $assoc_args): void
    {
        $this->info('Zaplane Integration Scaffolder');
        $this->line('');

        // Gather info
        $slug = $assoc_args['slug'] ?? $this->ask('Integration slug (e.g. mailchimp):');
        if (empty($slug)) {
            $this->error('Slug is required');
            return;
        }

        $slug = sanitize_title($slug);
        $class_name = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));

        $type = $assoc_args['type'] ?? $this->ask('Type (external/wordpress) [external]:');
        $type = $type ?: 'external';

        $auth = 'none';
        if ($type === 'external') {
            $auth = $assoc_args['auth'] ?? $this->ask('Auth type (api_key/oauth2/basic/both) [api_key]:');
            $auth = $auth ?: 'api_key';
        }

        $name = $assoc_args['name'] ?? $this->ask("Display name [{$class_name}]:");
        $name = $name ?: $class_name;

        // Check if file exists
        $file_path = ZAPLANE_ROOT_DIR_PATH . 'integrations/' . $slug . '.php';
        if (file_exists($file_path)) {
            $this->error("Integration file already exists: integrations/{$slug}.php");
            return;
        }

        // Generate content
        $content = $type === 'external'
            ? $this->generate_external($slug, $class_name, $name, $auth)
            : $this->generate_wordpress($slug, $class_name, $name);

        // Write file
        if (file_put_contents($file_path, $content) === false) {
            $this->error('Failed to write file: ' . $file_path);
            return;
        }

        $this->line('');
        $this->success("Integration created: integrations/{$slug}.php");
        $this->line('');
        $this->line("  Class:    Zaplane\\Integrations\\{$class_name}");
        $this->line("  Type:     {$type}");
        if ($type === 'external') {
            $this->line("  Auth:     {$auth}");
        }
        $this->line("  Base:     " . ($type === 'external' ? 'ExternalAppIntegration' : 'WordPressPluginIntegration'));
        $this->line('');
        $this->line('  The integration will be auto-discovered — no registry editing needed.');
        $this->line('');
        $this->line('  Next steps:');
        $this->line('  1. Define triggers and actions in get_triggers() / get_actions()');
        $this->line('  2. Implement resolve_trigger() for triggers');
        $this->line('  3. Implement execute_node() for actions');
        if ($type === 'external') {
            $this->line('  4. Implement test_connection() to verify credentials');
        }
        $this->line('  5. Run: wp zaplane test:integration ' . $slug);
        $this->line('  6. Run: wp zaplane build:integration  (to rebuild manifest)');
        $this->line('');
    }

    private function generate_external(string $slug, string $class_name, string $name, string $auth): string
    {
        $auth_fields = $this->auth_fields_template($auth);
        $auth_type_line = "        return '{$auth}';";

        return <<<PHP
<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\ExternalAppIntegration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * {$name} Integration
 *
 * External app integration using {$auth} authentication.
 * Auto-discovered by the integration loader — no registry editing needed.
 */
class {$class_name} extends ExternalAppIntegration {

    /**
     * Base URL for the {$name} API.
     * Update this with the actual API endpoint.
     */
    private const API_BASE_URL = 'https://api.example.com';

    /* ---------------------------------------------------------
     * Identity
     * --------------------------------------------------------- */

    public static function get_slug(): string {
        return '{$slug}';
    }

    public static function get_name(): string {
        return '{$name}';
    }

    public static function get_icon(): string {
        return '{$slug}';
    }

    /* ---------------------------------------------------------
     * Authentication
     * --------------------------------------------------------- */

    public static function get_auth_type(): string {
{$auth_type_line}
    }

{$auth_fields}

    public static function test_connection( array \$credentials ): array {
        // TODO: Make a lightweight API call to verify credentials
        // Example:
        // \$response = self::api_request('GET', self::API_BASE_URL . '/me', [], \$credentials);
        // return ['success' => true, 'message' => 'Connected as ' . \$response['name'], 'details' => \$response];

        return [
            'success' => false,
            'message' => 'test_connection() not yet implemented',
            'details' => [],
        ];
    }

    /* ---------------------------------------------------------
     * Triggers
     * --------------------------------------------------------- */

    public static function get_triggers(): array {
        return [
            // Example:
            // 'event_received' => [
            //     'label' => 'Event Received',
            //     'hook'  => '{$slug}_webhook_event',
            // ],
        ];
    }

    public static function resolve_trigger( array \$node, array \$args ) {
        // Extract and return payload from hook arguments.
        // Return false to skip (e.g. if event doesn't match filter).
        //
        // Example:
        // return ['event_id' => \$args[0] ?? '', 'data' => \$args[1] ?? []];

        return false;
    }

    /* ---------------------------------------------------------
     * Actions
     * --------------------------------------------------------- */

    public static function get_actions(): array {
        return [
            // Example:
            // 'create_record' => ['label' => 'Create Record'],
            // 'update_record' => ['label' => 'Update Record'],
        ];
    }

    public static function get_action_config_schema( string \$action ): array {
        // Return UI field definitions for each action.
        //
        // Example for 'create_record':
        // if (\$action === 'create_record') {
        //     return [
        //         'name'  => ['type' => 'text', 'label' => 'Name', 'required' => true],
        //         'email' => ['type' => 'text', 'label' => 'Email', 'required' => true],
        //     ];
        // }

        return [];
    }

    public static function execute_node( array \$node, array \$input ): array {
        \$action = \$node['config']['action'] ?? \$node['data']['event'] ?? '';
        \$config = \$node['config']['data'] ?? \$node['data']['config'] ?? [];
        \$creds  = \$node['_connection_credentials'] ?? [];

        // Optional: use ExecutionContext for structured access
        // \$context = \$node['_context'] ?? null;

        // TODO: Implement action logic
        // Example:
        // if (\$action === 'create_record') {
        //     \$result = self::api_request('POST', self::API_BASE_URL . '/records', [
        //         'name'  => \$config['name'] ?? '',
        //         'email' => \$config['email'] ?? '',
        //     ], \$creds);
        //     return ['port' => 'main', 'data' => array_merge(\$input, \$result)];
        // }

        return ['port' => 'main', 'data' => \$input];
    }
}

PHP;
    }

    private function generate_wordpress(string $slug, string $class_name, string $name): string
    {
        return <<<PHP
<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\WordPressPluginIntegration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * {$name} Integration
 *
 * WordPress plugin integration — no external connection needed.
 * Auto-discovered by the integration loader — no registry editing needed.
 */
class {$class_name} extends WordPressPluginIntegration {

    /* ---------------------------------------------------------
     * Identity
     * --------------------------------------------------------- */

    public static function get_slug(): string {
        return '{$slug}';
    }

    public static function get_name(): string {
        return '{$name}';
    }

    public static function get_icon(): string {
        return '{$slug}';
    }

    /* ---------------------------------------------------------
     * Triggers
     * --------------------------------------------------------- */

    public static function get_triggers(): array {
        return [
            // Example:
            // 'form_submitted' => [
            //     'label' => 'Form Submitted',
            //     'hook'  => '{$slug}_form_submitted',
            // ],
        ];
    }

    public static function resolve_trigger( array \$node, array \$args ) {
        // Extract payload from WordPress hook arguments.
        // Use the inherited helpers: self::resolve_post(), resolve_user(), etc.
        //
        // Example:
        // \$event = \$node['event'] ?? '';
        // if (\$event === 'form_submitted') {
        //     return ['form_id' => \$args[0] ?? 0, 'data' => \$args[1] ?? []];
        // }

        return false;
    }

    /* ---------------------------------------------------------
     * Actions
     * --------------------------------------------------------- */

    public static function get_actions(): array {
        return [
            // Example:
            // 'create_entry' => ['label' => 'Create Entry'],
        ];
    }

    public static function get_action_config_schema( string \$action ): array {
        // Return field definitions for the action UI.
        //
        // Example:
        // if (\$action === 'create_entry') {
        //     return [
        //         ['key' => 'form_id', 'label' => 'Form ID', 'type' => 'expression', 'required' => true],
        //         ['key' => 'data', 'label' => 'Entry Data', 'type' => 'textarea', 'required' => true],
        //     ];
        // }

        return [];
    }

    public static function execute_node( array \$node, array \$input ): array {
        \$config = \$node['data']['config'] ?? [];
        \$event  = \$node['data']['event'] ?? '';

        // Route to action methods
        \$method = 'action_' . \$event;
        if (method_exists(static::class, \$method)) {
            return static::\$method(\$config, \$input);
        }

        return ['port' => 'main', 'data' => \$input];
    }
}

PHP;
    }

    private function auth_fields_template(string $auth): string
    {
        if ($auth === 'api_key') {
            return <<<'PHP'
    public static function get_auth_fields( ?string $auth_type = null ): array {
        return [
            'api_key' => [
                'type'        => 'password',
                'label'       => 'API Key',
                'placeholder' => 'Enter your API key',
                'required'    => true,
                'help'        => 'Find this in your account settings',
            ],
        ];
    }
PHP;
        }

        if ($auth === 'oauth2') {
            return <<<'PHP'
    public static function get_auth_fields( ?string $auth_type = null ): array {
        return [
            'client_id' => [
                'type'        => 'text',
                'label'       => 'Client ID',
                'placeholder' => 'Your app Client ID',
                'required'    => true,
            ],
            'client_secret' => [
                'type'        => 'password',
                'label'       => 'Client Secret',
                'placeholder' => 'Your app Client Secret',
                'required'    => true,
            ],
        ];
    }

    public static function get_oauth_scopes(): array {
        // TODO: Define required OAuth scopes
        return [];
    }

    public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
        $client_id = $credentials['client_id'] ?? '';
        if ( empty( $client_id ) ) return null;

        // TODO: Update with actual OAuth authorization URL
        $params = [
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => implode( ' ', static::get_oauth_scopes() ),
        ];

        return 'https://example.com/oauth/authorize?' . http_build_query( $params );
    }

    public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
        // TODO: Implement token exchange
        return [];
    }
PHP;
        }

        if ($auth === 'basic') {
            return <<<'PHP'
    public static function get_auth_fields( ?string $auth_type = null ): array {
        return [
            'username' => [
                'type'        => 'text',
                'label'       => 'Username',
                'required'    => true,
            ],
            'password' => [
                'type'        => 'password',
                'label'       => 'Password',
                'required'    => true,
            ],
        ];
    }
PHP;
        }

        // 'both' — API key + OAuth
        return <<<'PHP'
    public static function get_available_auth_types(): array {
        return [
            'oauth2'  => [
                'label'       => 'OAuth 2.0',
                'description' => 'Connect securely using OAuth.',
            ],
            'api_key' => [
                'label'       => 'API Key',
                'description' => 'Use an API key directly.',
            ],
        ];
    }

    public static function get_auth_fields( ?string $auth_type = null ): array {
        $oauth_fields = [
            'client_id'     => ['type' => 'text', 'label' => 'Client ID', 'required' => true],
            'client_secret' => ['type' => 'password', 'label' => 'Client Secret', 'required' => true],
        ];

        $token_fields = [
            'api_key' => ['type' => 'password', 'label' => 'API Key', 'required' => true],
        ];

        if ( $auth_type === 'oauth2' ) return $oauth_fields;
        if ( $auth_type === 'api_key' ) return $token_fields;

        return ['oauth2' => $oauth_fields, 'api_key' => $token_fields];
    }
PHP;
    }
}
