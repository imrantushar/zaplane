<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Ninjaform;
use Zaplane\Tests\WPMocks;

/**
 * Test suite for the Ninja Form integration.
 *
 * Follows the Integration Testing Guide:
 * - Extends IntegrationTestCase
 * - Implements getIntegrationClass()
 * - Uses makeTriggerNode() for trigger tests
 * - Provides getTriggerTests() for bulk testing
 * - Mocks the Ninja_Forms global functions and classes
 * - Includes hand-written tests for success/failure paths
 * - Auto contract tests are inherited
 */
class NinjaformTest extends IntegrationTestCase {

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    protected function getIntegrationClass(): string {
        return Ninjaform::class;
    }

    // -------------------------------------------------------------------------
    // Bulk Test Data (for auto contract tests)
    // -------------------------------------------------------------------------

    protected function getTriggerTests(): array {
        return [
            'process_ninja_form' => [ [] ],
        ];
    }

    protected function getActionTests(): array {
        return [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private $ninjaFormsMock;

    /**
     * Mock Ninja_Forms global function.
     *
     * @param array $forms List of mock form objects for query_forms
     * @param bool $returnNull Whether Ninja_Forms() should return null
     */
    private function mockNinjaForms(array $forms = [], bool $returnNull = false): void {
        if (!function_exists('Ninja_Forms')) {
            eval('
                namespace {
                    function Ninja_Forms() {
                        return \Zaplane\Tests\Integrations\NinjaformTest::getNinjaFormsMock();
                    }
                }
            ');
        }

        if ($returnNull) {
            // When Ninja Forms is not active, the global function returns null.
            $this->ninjaFormsMock = null;
            return;
        }

        // Normal mock with actual forms
        $this->ninjaFormsMock = new class($forms) {
            private $forms;

            public function __construct($forms) {
                $this->forms = $forms;
            }

            public function form($id = null) {
                return new class($id, $this->forms) {
                    private $id;
                    private $forms;

                    public function __construct($id, $forms) {
                        $this->id = $id;
                        $this->forms = $forms;
                    }

                    public function get_forms() {
                        return $this->forms;
                    }

                    public function get_id() {
                        return $this->id;
                    }

                    public function get_setting($key) {
                        if ($key === 'title') {
                            foreach ($this->forms as $form) {
                                if ($form->get_id() == $this->id) {
                                    return $form->get_setting('title');
                                }
                            }
                            return 'Mock Form';
                        }
                        return '';
                    }
                };
            }
        };
    }

    public static function getNinjaFormsMock() {
        foreach (debug_backtrace() as $trace) {
            if (isset($trace['object']) && $trace['object'] instanceof self) {
                return $trace['object']->ninjaFormsMock;
            }
        }
        return null;
    }

    private function makeMockForm(int $id = 1, string $title = 'Contact Form'): object {
        return new class($id, $title) {
            private $id;
            private $title;

            public function __construct($id, $title) {
                $this->id = $id;
                $this->title = $title;
            }

            public function get_id() {
                return $this->id;
            }

            public function get_setting($key) {
                if ($key === 'title') {
                    return $this->title;
                }
                return '';
            }
        };
    }

    protected function setUp(): void {
        parent::setUp();
        $this->mockNinjaForms([]);
    }

    protected function tearDown(): void {
        parent::tearDown();
        $this->ninjaFormsMock = null;
    }

    // -------------------------------------------------------------------------
    // Hand-Written Tests
    // -------------------------------------------------------------------------

    public function test_trigger_process_ninja_form_success_no_filter(): void {
        $mockForm = $this->makeMockForm(5, 'Newsletter');
        $this->mockNinjaForms([$mockForm]);

        $formData = [
            'form_id' => 5,
            'sub_id'  => 123,
            'extra'   => ['some' => 'data'],
        ];

        $node = $this->makeTriggerNode('process_ninja_form');
        $result = Ninjaform::resolve_trigger($node, [$formData]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['entry_id']);
        $this->assertEquals($formData, $result['form_data']);
        $this->assertArrayHasKey('form', $result);
        $payload = $result['form'];
        $this->assertEquals(5, $payload['id']);
        $this->assertEquals('Newsletter', $payload['title']);
    }

    public function test_trigger_process_ninja_form_success_with_matching_config(): void {
        $mockForm = $this->makeMockForm(10, 'Support');
        $this->mockNinjaForms([$mockForm]);

        $formData = [
            'id' => 10,
            'submission' => ['id' => 456],
        ];

        $node = $this->makeTriggerNode('process_ninja_form', ['form_id' => '10']);
        $result = Ninjaform::resolve_trigger($node, [$formData]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(456, $result['entry_id']);
        $this->assertEquals(10, $result['form']['id']);
    }

    public function test_trigger_process_ninja_form_fails_on_form_mismatch(): void {
        $formData = ['form_id' => 10];
        $node = $this->makeTriggerNode('process_ninja_form', ['form_id' => '99']);
        $result = Ninjaform::resolve_trigger($node, [$formData]);
        $this->assertFalse($result);
    }

    public function test_trigger_process_ninja_form_returns_false_without_form_id(): void {
        $formData = ['sub_id' => 123];
        $node = $this->makeTriggerNode('process_ninja_form');
        $result = Ninjaform::resolve_trigger($node, [$formData]);
        $this->assertFalse($result);
    }

    public function test_trigger_process_ninja_form_returns_false_with_empty_data(): void {
        $formData = [];
        $node = $this->makeTriggerNode('process_ninja_form');
        $result = Ninjaform::resolve_trigger($node, [$formData]);
        $this->assertFalse($result);
    }

    public function test_trigger_process_ninja_form_returns_false_with_non_array(): void {
        $node = $this->makeTriggerNode('process_ninja_form');
        $result = Ninjaform::resolve_trigger($node, [null]);
        $this->assertFalse($result);
    }

    public function test_trigger_process_ninja_form_any_passes_all(): void {
        $mockForm1 = $this->makeMockForm(1, 'Form A');
        $mockForm2 = $this->makeMockForm(2, 'Form B');
        $this->mockNinjaForms([$mockForm1, $mockForm2]);

        $formData1 = ['form_id' => 1, 'sub_id' => 11];
        $formData2 = ['form' => ['id' => 2], 'sub_id' => 22];

        $node = $this->makeTriggerNode('process_ninja_form', ['form_id' => 'any']);

        $result1 = Ninjaform::resolve_trigger($node, [$formData1]);
        $result2 = Ninjaform::resolve_trigger($node, [$formData2]);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
    }

    public function test_trigger_process_ninja_form_succeeds_with_null_ninja_forms(): void {
        $this->mockNinjaForms([], true);

        $formData = ['form_id' => 5, 'sub_id' => 123];
        $node = $this->makeTriggerNode('process_ninja_form');
        $result = Ninjaform::resolve_trigger($node, [$formData]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['entry_id']);
        $this->assertNull($result['form']);
    }

    public function test_execute_node_passthrough(): void {
        $input = ['some' => 'data'];
        $result = Ninjaform::execute_node(
            $this->makeActionNode('__any__'),
            $input
        );
        $this->assertEquals('main', $result['port']);
        $this->assertEquals($input, $result['data']);
    }

    public function test_get_slug(): void {
        $this->assertEquals('ninjaform', Ninjaform::get_slug());
    }

    public function test_get_name(): void {
        $this->assertEquals('Ninja Form', Ninjaform::get_name());
    }

    public function test_get_icon(): void {
        $this->assertEquals('ninjaform.svg', Ninjaform::get_icon());
    }

    public function test_get_actions_empty(): void {
        $this->assertSame([], Ninjaform::get_actions());
    }

    public function test_get_action_config_schema_returns_empty(): void {
        $this->assertSame([], Ninjaform::get_action_config_schema('any_action'));
    }

    public function test_trigger_config_schema_is_valid(): void {
        $schema = Ninjaform::get_trigger_config_schema('process_ninja_form');
        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);
        $this->assertEquals('form_id', $schema[0]['key']);
        $this->assertEquals('select', $schema[0]['type']);
        $this->assertTrue($schema[0]['required']);
        $this->assertArrayHasKey('dynamic', $schema[0]);
    }

    public function test_trigger_config_schema_unknown_returns_empty(): void {
        $this->assertSame([], Ninjaform::get_trigger_config_schema('__unknown__'));
    }

    public function test_get_output_ports_returns_array(): void {
        $ports = Ninjaform::get_output_ports();
        $this->assertIsArray($ports);
        $this->assertArrayHasKey('main', $ports);
    }

    public function test_dynamic_queries_registered(): void {
        $queries = Ninjaform::get_dynamic_queries();
        $this->assertArrayHasKey('forms', $queries);
        $this->assertIsCallable($queries['forms']);
    }

    public function test_query_forms_returns_at_least_any_option(): void {
        $this->mockNinjaForms([], true);
        $options = Ninjaform::query_forms();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        $this->assertEquals('any', $options[0]['name']);
        $this->assertEquals('Any Form', $options[0]['label']);
    }

    public function test_query_forms_returns_forms_from_ninja_forms_when_available(): void {
        $form1 = $this->makeMockForm(1, 'Contact');
        $form2 = $this->makeMockForm(2, 'Newsletter');
        $this->mockNinjaForms([$form1, $form2]);

        $options = Ninjaform::query_forms();
        $this->assertCount(3, $options); // 'any' + 2 forms
        $this->assertEquals('Contact', $options[1]['label']);
        $this->assertEquals(1, $options[1]['name']);
        $this->assertEquals('Newsletter', $options[2]['label']);
        $this->assertEquals(2, $options[2]['name']);
    }

    public function test_trigger_follows_contract(): void {
        $formData = ['form_id' => 1, 'sub_id' => 1];
        $result = Ninjaform::resolve_trigger(
            $this->makeTriggerNode('process_ninja_form'),
            [$formData]
        );
        $this->assertThat($result, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));

        $result = Ninjaform::resolve_trigger(
            $this->makeTriggerNode('process_ninja_form'),
            [null]
        );
        $this->assertThat($result, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));
    }
}
