<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Tests\TestCase;

/**
 * Base test case for integration testing.
 *
 * Tests what we can verify without a full WordPress/database environment:
 * - Triggers/actions are registered
 * - Labels exist
 * - Config schemas are valid
 * - Output format is correct (when mocked)
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * Get the integration class to test
     */
    abstract protected function getIntegrationClass(): string;

    /**
     * Define triggers to test (just event names)
     */
    protected function getTriggers(): array
    {
        return [];
    }

    /**
     * Define actions to test (just event names)
     */
    protected function getActions(): array
    {
        return [];
    }

    // ========== REGISTRATION TESTS ==========

    /**
     * @test
     */
    public function integration_has_slug(): void
    {
        $class = $this->getIntegrationClass();
        $slug = $class::get_slug();

        $this->assertNotEmpty($slug);
        $this->assertIsString($slug);
    }

    /**
     * @test
     */
    public function all_defined_triggers_are_registered(): void
    {
        $class = $this->getIntegrationClass();
        $registered = $class::get_triggers();
        $defined = $this->getTriggers();

        if (empty($defined)) {
            $this->assertTrue(true); // Pass if no triggers defined
            return;
        }

        foreach ($defined as $event) {
            $this->assertArrayHasKey($event, $registered, "Trigger '{$event}' not registered");
        }
    }

    /**
     * @test
     */
    public function all_defined_actions_are_registered(): void
    {
        $class = $this->getIntegrationClass();
        $registered = $class::get_actions();
        $defined = $this->getActions();

        if (empty($defined)) {
            $this->assertTrue(true);
            return;
        }

        foreach ($defined as $event) {
            $this->assertArrayHasKey($event, $registered, "Action '{$event}' not registered");
        }
    }

    /**
     * @test
     */
    public function all_triggers_have_labels_and_hooks(): void
    {
        $class = $this->getIntegrationClass();
        $triggers = $class::get_triggers();

        if (empty($triggers)) {
            $this->assertTrue(true);
            return;
        }

        foreach ($triggers as $event => $meta) {
            $this->assertArrayHasKey('label', $meta, "Trigger '{$event}' missing label");
            $this->assertNotEmpty($meta['label'], "Trigger '{$event}' has empty label");
            $this->assertArrayHasKey('hook', $meta, "Trigger '{$event}' missing hook");
        }
    }

    /**
     * @test
     */
    public function all_actions_have_labels(): void
    {
        $class = $this->getIntegrationClass();
        $actions = $class::get_actions();

        if (empty($actions)) {
            $this->assertTrue(true);
            return;
        }

        foreach ($actions as $event => $meta) {
            $this->assertArrayHasKey('label', $meta, "Action '{$event}' missing label");
            $this->assertNotEmpty($meta['label'], "Action '{$event}' has empty label");
        }
    }

    /**
     * @test
     */
    public function trigger_config_schemas_are_valid(): void
    {
        $class = $this->getIntegrationClass();
        $triggers = $class::get_triggers();

        if (empty($triggers)) {
            $this->assertTrue(true);
            return;
        }

        foreach (array_keys($triggers) as $event) {
            $schema = $class::get_trigger_config_schema($event);
            $this->assertIsArray($schema, "Trigger '{$event}' schema must be array");
            $this->validateSchemaFormat($schema, "trigger:{$event}");
        }
    }

    /**
     * @test
     */
    public function action_config_schemas_are_valid(): void
    {
        $class = $this->getIntegrationClass();
        $actions = $class::get_actions();

        if (empty($actions)) {
            $this->assertTrue(true);
            return;
        }

        foreach (array_keys($actions) as $event) {
            $schema = $class::get_action_config_schema($event);
            $this->assertIsArray($schema, "Action '{$event}' schema must be array");
            $this->validateSchemaFormat($schema, "action:{$event}");
        }
    }

    /**
     * @test
     */
    public function output_ports_are_valid(): void
    {
        $class = $this->getIntegrationClass();
        $ports = $class::get_output_ports();

        $this->assertIsArray($ports);
        $this->assertNotEmpty($ports, 'Integration must have at least one output port');
    }

    /**
     * Validate schema format - supports both formats:
     * Format 1: [['key' => 'name', 'type' => 'text'], ...]
     * Format 2: ['name' => ['type' => 'text', 'label' => '...'], ...]
     */
    protected function validateSchemaFormat(array $schema, string $context): void
    {
        if (empty($schema)) {
            return; // Empty schema is valid
        }

        // Check first item to determine format
        $firstKey = array_key_first($schema);
        $firstItem = $schema[$firstKey];

        if (is_int($firstKey)) {
            // Format 1: indexed array with 'key' field
            foreach ($schema as $field) {
                $this->assertArrayHasKey('key', $field, "{$context}: Schema field missing 'key'");
                $this->assertArrayHasKey('type', $field, "{$context}: Schema field missing 'type'");
            }
        } else {
            // Format 2: associative array where key is field name
            foreach ($schema as $fieldName => $field) {
                $this->assertIsString($fieldName, "{$context}: Field name must be string");
                $this->assertArrayHasKey('type', $field, "{$context}: Field '{$fieldName}' missing 'type'");
            }
        }
    }

    // ========== HELPERS ==========

    protected function makeTriggerNode(string $event, array $config = []): array
    {
        $class = $this->getIntegrationClass();
        $triggers = $class::get_triggers();
        $hook = $triggers[$event]['hook'] ?? $event;

        return [
            'type' => 'trigger',
            'event' => $event,
            'data' => [
                'app' => $class::get_slug(),
                'event' => $event,
                'hook' => $hook,
                'config' => $config,
            ],
        ];
    }

    protected function makeActionNode(string $event, array $config = []): array
    {
        $class = $this->getIntegrationClass();

        return [
            'type' => 'action',
            'data' => [
                'app' => $class::get_slug(),
                'event' => $event,
                'config' => $config,
            ],
        ];
    }
}
