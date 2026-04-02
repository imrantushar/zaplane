<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\FluentForm;
use Zaplane\Tests\WPMocks;

/**
 * Test suite for the Fluent Form integration.
 *
 * Follows the Integration Testing Guide:
 * - Extends IntegrationTestCase
 * - Implements getIntegrationClass()
 * - Uses makeTriggerNode() for trigger tests
 * - Provides getTriggerTests() for bulk testing
 * - Mocks the Fluent Form objects
 * - Includes hand-written tests for success/failure paths
 * - Auto contract tests are inherited
 */
class FluentFormTest extends IntegrationTestCase {

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    protected function getIntegrationClass(): string {
        return FluentForm::class;
    }

    // -------------------------------------------------------------------------
    // Bulk Test Data (for auto contract tests)
    // -------------------------------------------------------------------------

    protected function getTriggerTests(): array {
        return [
            // For bulk testing, we pass null as the form argument.
            // The trigger will return false (acceptable per contract).
            'submission_inserted' => [ null, null, null ],
        ];
    }

    protected function getActionTests(): array {
        return []; // No actions
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Creates a mock Fluent Form object with id and title.
     *
     * @param int $id
     * @param string $title
     * @return object
     */
    private function makeMockForm(int $id = 1, string $title = 'Contact Form'): object {
        return (object) [
            'id'    => $id,
            'title' => $title,
        ];
    }

    /**
     * Mocks the global wpFluent() function to return a fluent query builder mock.
     * This is used for testing the dynamic query.
     */
    private function mockWpFluent(array $forms = []): void {
        if (!function_exists('wpFluent')) {
            eval('
                namespace {
                    function wpFluent() {
                        return \Zaplane\Tests\Integrations\FluentFormTest::getWpFluentMock();
                    }
                }
            ');
        }

        // Create a mock query builder
        $queryBuilderMock = new class($forms) {
            private $forms;
            private $table = '';
            private $select = [];

            public function __construct($forms) {
                $this->forms = $forms;
            }

            public function table($name) {
                $this->table = $name;
                return $this;
            }

            public function select($fields) {
                $this->select = $fields;
                return $this;
            }

            public function get() {
                // Return the predefined forms
                return $this->forms;
            }
        };

        // Store the mock in a static property accessible via getWpFluentMock
        $this->wpFluentMock = $queryBuilderMock;
    }

    private $wpFluentMock;

    public static function getWpFluentMock() {
        $test = null;
        foreach (debug_backtrace() as $trace) {
            if (isset($trace['object']) && $trace['object'] instanceof self) {
                $test = $trace['object'];
                break;
            }
        }
        return $test ? $test->wpFluentMock : null;
    }

    // -------------------------------------------------------------------------
    // Hand-Written Tests
    // -------------------------------------------------------------------------

    /**
     * Test submission_inserted trigger – success path with no config filtering.
     */
    public function test_trigger_submission_inserted_success_no_filter(): void {
        $form = $this->makeMockForm(5, 'Newsletter');
        $entryId = 123;
        $formData = ['name' => 'John', 'email' => 'john@example.com'];

        $node = $this->makeTriggerNode('submission_inserted'); // no config
        $result = FluentForm::resolve_trigger($node, [$entryId, $formData, $form]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals($entryId, $result['entry_id']);
        $this->assertEquals($formData, $result['form_data']);
        $this->assertArrayHasKey('form', $result);
        $payload = $result['form'];
        $this->assertEquals(5, $payload['id']);
        $this->assertEquals('Newsletter', $payload['title']);
    }

    /**
     * Test submission_inserted trigger – success path with matching config filter.
     */
    public function test_trigger_submission_inserted_success_with_matching_config(): void {
        $form = $this->makeMockForm(10, 'Support');
        $entryId = 456;
        $formData = ['subject' => 'Help'];

        $node = $this->makeTriggerNode('submission_inserted', ['form_id' => '10']);
        $result = FluentForm::resolve_trigger($node, [$entryId, $formData, $form]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['form']['id']);
    }

    /**
     * Test submission_inserted trigger – failure when config does not match.
     */
    public function test_trigger_submission_inserted_fails_on_form_mismatch(): void {
        $form = $this->makeMockForm(10, 'Support');
        $entryId = 456;
        $formData = [];

        $node = $this->makeTriggerNode('submission_inserted', ['form_id' => '99']);
        $result = FluentForm::resolve_trigger($node, [$entryId, $formData, $form]);

        $this->assertFalse($result);
    }

    /**
     * Test submission_inserted trigger – failure when entryId is missing.
     */
    public function test_trigger_submission_inserted_returns_false_without_entry_id(): void {
        $form = $this->makeMockForm();
        $node = $this->makeTriggerNode('submission_inserted');
        $result = FluentForm::resolve_trigger($node, [null, [], $form]);

        $this->assertFalse($result);
    }

    /**
     * Test submission_inserted trigger – failure when form is missing.
     */
    public function test_trigger_submission_inserted_returns_false_without_form(): void {
        $entryId = 123;
        $node = $this->makeTriggerNode('submission_inserted');
        $result = FluentForm::resolve_trigger($node, [$entryId, [], null]);

        $this->assertFalse($result);
    }

    /**
     * Test submission_inserted trigger – config 'any' passes any form.
     */
    public function test_trigger_submission_inserted_any_passes_all(): void {
        $form1 = $this->makeMockForm(1, 'Form A');
        $form2 = $this->makeMockForm(2, 'Form B');

        $node = $this->makeTriggerNode('submission_inserted', ['form_id' => 'any']);

        $result1 = FluentForm::resolve_trigger($node, [1, [], $form1]);
        $result2 = FluentForm::resolve_trigger($node, [2, [], $form2]);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
    }

    // -------------------------------------------------------------------------
    // Action Passthrough Test (since no actions)
    // -------------------------------------------------------------------------

    public function test_execute_node_passthrough(): void {
        $input = ['some' => 'data'];

        $result = FluentForm::execute_node(
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
        $this->assertEquals('fluentform', FluentForm::get_slug());
    }

    public function test_get_name(): void {
        $this->assertEquals('Fluent Form', FluentForm::get_name());
    }

    public function test_get_icon(): void {
        $this->assertEquals('fluentform.svg', FluentForm::get_icon());
    }

    public function test_get_actions_empty(): void {
        $this->assertSame([], FluentForm::get_actions());
    }

    public function test_get_action_config_schema_returns_empty(): void {
        $this->assertSame([], FluentForm::get_action_config_schema('any_action'));
    }

    public function test_trigger_config_schema_is_valid(): void {
        $schema = FluentForm::get_trigger_config_schema('submission_inserted');
        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);
        $this->assertEquals('form_id', $schema[0]['key']);
        $this->assertEquals('select', $schema[0]['type']);
        $this->assertTrue($schema[0]['required']);
        $this->assertArrayHasKey('dynamic', $schema[0]);
    }

    public function test_trigger_config_schema_unknown_returns_empty(): void {
        $this->assertSame([], FluentForm::get_trigger_config_schema('__unknown__'));
    }

    /**
     * Test that get_output_ports returns an array with at least 'main'.
     * This is required by the auto-contract test.
     */
    public function test_get_output_ports_returns_array(): void {
        $ports = FluentForm::get_output_ports();
        $this->assertIsArray($ports);
        $this->assertArrayHasKey('main', $ports);
    }

    // -------------------------------------------------------------------------
    // Dynamic Queries
    // -------------------------------------------------------------------------

    public function test_dynamic_queries_registered(): void {
        $queries = FluentForm::get_dynamic_queries();
        $this->assertArrayHasKey('form', $queries);
        $this->assertIsCallable($queries['form']);
    }

    public function test_query_forms_returns_at_least_any_option(): void {
        $options = FluentForm::query_forms();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        $this->assertEquals('any', $options[0]['name']);
        $this->assertEquals('Any Form', $options[0]['label']);
    }

    public function test_query_forms_returns_forms_from_database_when_fluent_active(): void {
        // Mock wpFluent to return sample forms
        $fakeForms = [
            (object) ['id' => 1, 'title' => 'Contact'],
            (object) ['id' => 2, 'title' => 'Newsletter'],
        ];
        $this->mockWpFluent($fakeForms);

        $options = FluentForm::query_forms();
        $this->assertCount(3, $options); // 'any' + 2 forms
        $this->assertEquals('Contact', $options[1]['label']);
        $this->assertEquals(1, $options[1]['name']);
        $this->assertEquals('Newsletter', $options[2]['label']);
        $this->assertEquals(2, $options[2]['name']);
    }

    // -------------------------------------------------------------------------
    // Contract: All triggers must return array|false (additional explicit test)
    // -------------------------------------------------------------------------

    public function test_trigger_follows_contract(): void {
        $form = $this->makeMockForm();
        $entryId = 1;
        $formData = [];

        // Success case
        $result = FluentForm::resolve_trigger(
            $this->makeTriggerNode('submission_inserted'),
            [$entryId, $formData, $form]
        );
        $this->assertThat($result, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));

        // Failure case
        $result = FluentForm::resolve_trigger(
            $this->makeTriggerNode('submission_inserted'),
            [null, null, null]
        );
        $this->assertThat($result, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));
    }
}
