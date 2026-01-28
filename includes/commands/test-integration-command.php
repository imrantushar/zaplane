<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ExternalAppIntegration;
use Zaplane\Framework\Classes\WordPressPluginIntegration;

if (!defined('ABSPATH')) exit;

/**
 * WP-CLI command: wp zaplane test:integration <slug>
 *
 * Validates an integration's structure, checks for common mistakes,
 * and optionally tests the connection.
 *
 * Usage:
 *   wp zaplane test:integration slack
 *   wp zaplane test:integration slack --test-connection=1
 *   wp zaplane test:integration --all
 */
class TestIntegrationCommand extends Command
{
    protected string $signature = 'test:integration';
    protected string $description = 'Validate an integration structure and optionally test its connection';

    public function handle(array $args, array $assoc_args): void
    {
        $test_all = isset($assoc_args['all']);

        if ($test_all) {
            $this->test_all();
            return;
        }

        $slug = $args[0] ?? null;
        if (!$slug) {
            $this->error('Usage: wp zaplane test:integration <slug> [--test-connection=<id>] [--all]');
            return;
        }

        $this->test_single($slug, $assoc_args);
    }

    private function test_all(): void
    {
        $this->info('Validating all registered integrations...');
        $this->line('');

        $slugs = IntegrationLoader::getAllSlugs();
        $passed = 0;
        $failed = 0;

        foreach ($slugs as $slug) {
            $errors = $this->validate($slug);
            if (empty($errors)) {
                $this->line("  PASS  {$slug}");
                $passed++;
            } else {
                $this->line("  FAIL  {$slug}");
                foreach ($errors as $err) {
                    $this->line("        - {$err}");
                }
                $failed++;
            }
        }

        $this->line('');
        $this->line("Results: {$passed} passed, {$failed} failed out of " . count($slugs) . " integrations");

        if ($failed === 0) {
            $this->success('All integrations passed validation');
        } else {
            $this->warning("{$failed} integration(s) have issues");
        }
    }

    private function test_single(string $slug, array $assoc_args): void
    {
        $this->info("Testing integration: {$slug}");
        $this->line('');

        $instance = IntegrationLoader::get($slug);
        if (!$instance) {
            $this->error("Integration '{$slug}' not found in registry");
            return;
        }

        $class = get_class($instance);

        // Basic info
        $this->line('  Identity:');
        $this->line("    Slug:     " . $class::get_slug());
        $this->line("    Name:     " . $class::get_name());
        $this->line("    Icon:     " . ($class::get_icon() ?: '(none)'));
        $this->line("    Category: " . $class::get_category());

        if ($instance instanceof ExternalAppIntegration) {
            $this->line("    Base:     ExternalAppIntegration");
        } elseif ($instance instanceof WordPressPluginIntegration) {
            $this->line("    Base:     WordPressPluginIntegration");
        } else {
            $this->line("    Base:     IntegrationBase");
        }

        $this->line('');

        // Auth info
        $this->line('  Authentication:');
        $this->line("    Requires: " . ($class::requires_connection() ? 'Yes' : 'No'));
        $this->line("    Type:     " . $class::get_auth_type());
        $auth_fields = $class::get_auth_fields();
        $this->line("    Fields:   " . (empty($auth_fields) ? '(none)' : count($auth_fields) . ' field(s)'));
        $this->line('');

        // Triggers
        $triggers = $class::get_triggers();
        $this->line("  Triggers: " . count($triggers));
        foreach ($triggers as $key => $trigger) {
            $hook = $trigger['hook'] ?? '(missing!)';
            $this->line("    - {$key}: {$trigger['label']} [hook: {$hook}]");
        }
        $this->line('');

        // Actions
        $actions = $class::get_actions();
        $this->line("  Actions: " . count($actions));
        foreach ($actions as $key => $action) {
            $schema = $class::get_action_config_schema($key);
            $field_count = count($schema);
            $this->line("    - {$key}: {$action['label']} [{$field_count} fields]");
        }
        $this->line('');

        // Validation
        $errors = $this->validate($slug);
        if (empty($errors)) {
            $this->success('Validation passed - no issues found');
        } else {
            $this->warning('Validation found ' . count($errors) . ' issue(s):');
            foreach ($errors as $err) {
                $this->line("    - {$err}");
            }
        }

        // Connection test
        $connection_id = $assoc_args['test-connection'] ?? null;
        if ($connection_id && $class::requires_connection()) {
            $this->line('');
            $this->info('Testing connection #' . $connection_id . '...');

            try {
                $manager = new \Zaplane\Framework\Classes\ConnectionManager();
                $result = $manager->test((int) $connection_id);

                if ($result['success'] ?? false) {
                    $this->success('Connection test passed: ' . ($result['message'] ?? ''));
                } else {
                    $this->error('Connection test failed: ' . ($result['message'] ?? 'Unknown error'));
                }
            } catch (\Throwable $e) {
                $this->error('Connection test error: ' . $e->getMessage());
            }
        }
    }

    private function validate(string $slug): array
    {
        $errors = [];

        $instance = IntegrationLoader::get($slug);
        if (!$instance) {
            return ["Integration not found"];
        }

        $class = get_class($instance);

        // Check slug consistency
        if ($class::get_slug() !== $slug) {
            $errors[] = "get_slug() returns '{$class::get_slug()}' but registered as '{$slug}'";
        }

        // Check name
        if (empty($class::get_name())) {
            $errors[] = "get_name() returns empty string";
        }

        // If requires connection, verify auth methods exist
        if ($class::requires_connection()) {
            $auth_type = $class::get_auth_type();
            if ($auth_type === 'none') {
                $errors[] = "requires_connection() is true but get_auth_type() returns 'none'";
            }

            $auth_fields = $class::get_auth_fields();
            if (empty($auth_fields)) {
                $errors[] = "requires_connection() is true but get_auth_fields() returns empty";
            }
        }

        // Check triggers have hooks
        foreach ($class::get_triggers() as $key => $trigger) {
            if (empty($trigger['hook'])) {
                $errors[] = "Trigger '{$key}' is missing 'hook' field";
            }
            if (empty($trigger['label'])) {
                $errors[] = "Trigger '{$key}' is missing 'label' field";
            }
        }

        // Check actions have labels
        foreach ($class::get_actions() as $key => $action) {
            if (empty($action['label'])) {
                $errors[] = "Action '{$key}' is missing 'label' field";
            }
        }

        // Verify triggers exist but resolve_trigger is implemented
        $triggers = $class::get_triggers();
        if (!empty($triggers)) {
            $ref = new \ReflectionMethod($class, 'resolve_trigger');
            if ($ref->getDeclaringClass()->getName() === IntegrationBase::class) {
                $errors[] = "Has triggers but resolve_trigger() is not overridden";
            }
        }

        // Verify actions exist but execute_node is implemented
        $actions = $class::get_actions();
        if (!empty($actions)) {
            $ref = new \ReflectionMethod($class, 'execute_node');
            if ($ref->getDeclaringClass()->getName() === IntegrationBase::class) {
                $errors[] = "Has actions but execute_node() is not overridden";
            }
        }

        return $errors;
    }
}
