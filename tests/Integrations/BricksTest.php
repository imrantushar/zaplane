<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Bricks;
use Zaplane\Tests\WPMocks;

/**
 * Test suite for the Bricks integration.
 *
 * Follows the Integration Testing Guide:
 * - Extends IntegrationTestCase
 * - Implements getIntegrationClass()
 * - Uses makeTriggerNode() for trigger tests
 * - Provides getTriggerTests() for bulk testing
 * - Mocks the Bricks form object
 * - Includes hand-written tests for success/failure paths
 * - Auto contract tests are inherited
 */
class BricksTest extends IntegrationTestCase {

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    protected function getIntegrationClass(): string {
        return Bricks::class;
    }

    // -------------------------------------------------------------------------
    // Bulk Test Data (for auto contract tests)
    // -------------------------------------------------------------------------

    protected function getTriggerTests(): array {
        return [
            // For bulk testing, we pass null as the form argument.
            // The trigger will return false (acceptable per contract).
            'bricks_form_submit' => [ null ],
        ];
    }

    protected function getActionTests(): array {
        return []; // No actions
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Creates a mock Bricks form object with configurable methods.
     *
     * @param array $uploaded_files
     * @param array $fields
     * @param array $settings
     * @return object
     */
    private function makeMockForm(array $uploaded_files = [], array $fields = [], array $settings = []): object {
        return new class($uploaded_files, $fields, $settings) {
            private $uploaded_files;
            private $fields;
            private $settings;

            public function __construct($uploaded_files, $fields, $settings) {
                $this->uploaded_files = $uploaded_files;
                $this->fields = $fields;
                $this->settings = $settings;
            }

            public function get_uploaded_files() {
                return $this->uploaded_files;
            }

            public function get_fields() {
                return $this->fields;
            }

            public function get_settings() {
                return $this->settings;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Hand-Written Tests
    // -------------------------------------------------------------------------

    /**
     * Test bricks_form_submit trigger – success path with no config filtering.
     */
    public function test_trigger_form_submit_success_no_filter(): void {
        $form = $this->makeMockForm(
            ['file1.jpg', 'file2.pdf'],
            ['name' => 'John', 'email' => 'john@example.com'],
            ['actions' => ['custom_action' => 'contact_form']]
        );

        $node = $this->makeTriggerNode('bricks_form_submit'); // no config
        $result = Bricks::resolve_trigger($node, [$form]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('form', $result);
        $payload = $result['form'];
        $this->assertArrayHasKey('uploaded_files', $payload);
        $this->assertArrayHasKey('form_fields', $payload);
        $this->assertArrayHasKey('form_settings', $payload);
        $this->assertEquals(['file1.jpg', 'file2.pdf'], $payload['uploaded_files']);
        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com'], $payload['form_fields']);
        $this->assertEquals(['actions' => ['custom_action' => 'contact_form']], $payload['form_settings']);
    }

    /**
     * Test bricks_form_submit trigger – success path with matching config filter.
     */
    public function test_trigger_form_submit_success_with_matching_config(): void {
        $form = $this->makeMockForm(
            [],
            [],
            ['actions' => ['custom_action' => 'newsletter']]
        );

        $node = $this->makeTriggerNode('bricks_form_submit', ['form_action' => 'newsletter']);
        $result = Bricks::resolve_trigger($node, [$form]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test bricks_form_submit trigger – failure when config does not match.
     */
    public function test_trigger_form_submit_fails_on_action_mismatch(): void {
        $form = $this->makeMockForm(
            [],
            [],
            ['actions' => ['custom_action' => 'contact']]
        );

        $node = $this->makeTriggerNode('bricks_form_submit', ['form_action' => 'newsletter']);
        $result = Bricks::resolve_trigger($node, [$form]);

        $this->assertFalse($result);
    }

    /**
     * Test bricks_form_submit trigger – failure when no form provided.
     */
    public function test_trigger_form_submit_returns_false_without_form(): void {
        $node = $this->makeTriggerNode('bricks_form_submit');
        $result = Bricks::resolve_trigger($node, [null]);

        $this->assertFalse($result);
    }

    /**
     * Test bricks_form_submit trigger – config with empty string should pass any form.
     */
    public function test_trigger_form_submit_empty_config_passes_all(): void {
        $form1 = $this->makeMockForm([], [], ['actions' => ['custom_action' => 'a']]);
        $form2 = $this->makeMockForm([], [], ['actions' => ['custom_action' => 'b']]);

        $node = $this->makeTriggerNode('bricks_form_submit', ['form_action' => '']); // empty string

        $result1 = Bricks::resolve_trigger($node, [$form1]);
        $result2 = Bricks::resolve_trigger($node, [$form2]);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
    }

    // -------------------------------------------------------------------------
    // Action Passthrough Test (since no actions)
    // -------------------------------------------------------------------------

    public function test_execute_node_passthrough(): void {
        $input = ['some' => 'data'];

        $result = Bricks::execute_node(
            $this->makeActionNode('__any__'),
            $input
        );

        $this->assertEquals('main', $result['port']);
        $this->assertEquals($input, $result['data']);
    }

    // -------------------------------------------------------------------------
    // Schema & Contract Tests
    // -------------------------------------------------------------------------

    public function test_get_slug(): void {
        $this->assertEquals('bricks', Bricks::get_slug());
    }

    public function test_get_name(): void {
        $this->assertEquals('Bricks', Bricks::get_name());
    }

    public function test_get_icon(): void {
        $this->assertEquals('bricksb-builder.svg', Bricks::get_icon());
    }

    public function test_get_actions_empty(): void {
        $this->assertSame([], Bricks::get_actions());
    }

    public function test_get_action_config_schema_returns_empty(): void {
        $this->assertSame([], Bricks::get_action_config_schema('any_action'));
    }

    public function test_trigger_config_schema_is_valid(): void {
        $schema = Bricks::get_trigger_config_schema('bricks_form_submit');
        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);
        $this->assertEquals('form_action', $schema[0]['key']);
        $this->assertEquals('text', $schema[0]['type']);
        $this->assertTrue($schema[0]['required']);
    }

    public function test_trigger_config_schema_unknown_returns_empty(): void {
        $this->assertSame([], Bricks::get_trigger_config_schema('__unknown__'));
    }

    /**
     * Test that get_output_ports returns an array with at least 'main'.
     * This is required by the auto-contract test.
     */
    public function test_get_output_ports_returns_array(): void {
        $ports = Bricks::get_output_ports();
        $this->assertIsArray($ports);
        $this->assertArrayHasKey('main', $ports);
    }

    // -------------------------------------------------------------------------
    // Contract: All triggers must return array|false (additional explicit test)
    // -------------------------------------------------------------------------

    public function test_trigger_follows_contract(): void {
        $form = $this->makeMockForm();

        // Success case
        $result = Bricks::resolve_trigger(
            $this->makeTriggerNode('bricks_form_submit'),
            [$form]
        );
        $this->assertThat($result, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));

        // Failure case
        $result = Bricks::resolve_trigger(
            $this->makeTriggerNode('bricks_form_submit'),
            [null]
        );
        $this->assertThat($result, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));
    }
}
