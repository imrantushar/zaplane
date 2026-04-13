<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\ARForm;

class ARFormTest extends IntegrationTestCase {

    protected function getIntegrationClass(): string {
        return ARForm::class;
    }

    // ========== HELPERS ==========

    /**
     * Create a trigger node for ARForm
     */
    private function makeARFormTriggerNode( string $event, string $formId = 'any' ): array {
        return [
            'type'  => 'trigger',
            'event' => $event,
            'data'  => [
                'app'    => 'arform',
                'event'  => $event,
                'config' => [
                    'form_id' => $formId,
                ],
            ],
            'config' => [
                'form_id' => $formId,
            ],
        ];
    }

    /**
     * Create a mock ARForm $form object (as passed by the hook)
     */
    private function makeFormObject( array $overrides = [] ): object {
        return (object) array_merge(
            [ 'id' => 5, 'title' => 'Test Form' ],
            $overrides
        );
    }

    /**
     * Build the 4-arg array the hook passes to resolve_trigger
     * arfliteentryexecute: $params, $arflite_errors, $form, $item_meta_values
     */
    private function makeHookArgs( array $overrides = [] ): array {
        $form = $overrides['form'] ?? $this->makeFormObject();
        return [
            $overrides['params']           ?? [ 'field_1' => 'John', 'field_2' => 'john@example.com' ],
            $overrides['arflite_errors']   ?? [],
            $form,
            $overrides['item_meta_values'] ?? [ 'field_1' => 'John', 'field_2' => 'john@example.com' ],
        ];
    }

    // ========== CONTRACT TESTS ==========

    public function test_integration_has_slug(): void {
        $this->assertEquals( 'arform', ARForm::get_slug() );
    }

    public function test_integration_has_name(): void {
        $this->assertNotEmpty( ARForm::get_name() );
    }

    public function test_triggers_have_labels_and_hooks(): void {
        $triggers = ARForm::get_triggers();
        $this->assertNotEmpty( $triggers );

        foreach ( $triggers as $event => $trigger ) {
            $this->assertArrayHasKey( 'label', $trigger, "Trigger '{$event}' missing label" );
            $this->assertArrayHasKey( 'hook', $trigger, "Trigger '{$event}' missing hook" );
            $this->assertNotEmpty( $trigger['label'], "Trigger '{$event}' label is empty" );
            $this->assertNotEmpty( $trigger['hook'], "Trigger '{$event}' hook is empty" );
        }
    }

    public function test_both_hooks_are_registered(): void {
        $triggers = ARForm::get_triggers();
        $hooks = array_column( $triggers, 'hook' );

        $this->assertContains( 'arfliteentryexecute', $hooks, 'Missing lite hook' );
        $this->assertContains( 'arfentryexecute', $hooks, 'Missing full version hook' );
    }

    public function test_triggers_have_args_count_of_4(): void {
        foreach ( ARForm::get_triggers() as $event => $trigger ) {
            $this->assertEquals( 4, $trigger['args'], "Trigger '{$event}' should have args = 4" );
        }
    }

    public function test_output_ports_are_valid(): void {
        $ports = ARForm::get_output_ports();
        $this->assertIsArray( $ports );
        $this->assertArrayHasKey( 'main', $ports );
    }

    public function test_trigger_config_schema_is_valid(): void {
        foreach ( array_keys( ARForm::get_triggers() ) as $event ) {
            $schema = ARForm::get_trigger_config_schema( $event );
            $this->assertIsArray( $schema );

            foreach ( $schema as $field ) {
                $this->assertArrayHasKey( 'key', $field );
                $this->assertArrayHasKey( 'label', $field );
                $this->assertArrayHasKey( 'type', $field );
            }
        }
    }

    public function test_trigger_config_schema_returns_empty_for_unknown_trigger(): void {
        $schema = ARForm::get_trigger_config_schema( 'unknown_trigger' );
        $this->assertIsArray( $schema );
        $this->assertEmpty( $schema );
    }

    // ========== TRIGGER: submit_form — success paths ==========

    public function test_trigger_returns_payload_for_any_form(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $args   = $this->makeHookArgs( [ 'form' => $this->makeFormObject( [ 'id' => 5 ] ) ] );
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertIsArray( $result );
        $this->assertEquals( 5, $result['form_id'] );
        $this->assertArrayHasKey( 'item_meta_values', $result );
        $this->assertArrayHasKey( 'params', $result );
    }

    public function test_trigger_returns_payload_when_form_id_matches(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', '5' );
        $args   = $this->makeHookArgs( [ 'form' => $this->makeFormObject( [ 'id' => 5 ] ) ] );
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertIsArray( $result );
        $this->assertEquals( 5, $result['form_id'] );
    }

    public function test_trigger_includes_item_meta_values_in_payload(): void {
        $node = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $meta = [ 'field_name' => 'Jane', 'field_email' => 'jane@test.com' ];
        $args = $this->makeHookArgs( [ 'item_meta_values' => $meta ] );

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertEquals( $meta, $result['item_meta_values'] );
    }

    public function test_trigger_includes_params_in_payload(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $params = [ 'field_1' => 'Test Value' ];
        $args   = $this->makeHookArgs( [ 'params' => $params ] );

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertEquals( $params, $result['params'] );
    }

    public function test_trigger_includes_arflite_errors_in_payload(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $errors = [ 'field_1' => 'Required field' ];
        $args   = $this->makeHookArgs( [ 'arflite_errors' => $errors ] );

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertEquals( $errors, $result['arflite_errors'] );
    }

    // ========== TRIGGER: submit_form_full (arfentryexecute) ==========

    public function test_submit_form_full_trigger_returns_payload(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form_full', 'any' );
        $args   = $this->makeHookArgs( [ 'form' => $this->makeFormObject( [ 'id' => 7 ] ) ] );
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertIsArray( $result );
        $this->assertEquals( 7, $result['form_id'] );
    }

    public function test_submit_form_full_respects_form_id_filter(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form_full', '10' );
        $args   = $this->makeHookArgs( [ 'form' => $this->makeFormObject( [ 'id' => 7 ] ) ] );
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertNull( $result );
    }

    // ========== TRIGGER: filter paths (returns null) ==========

    public function test_trigger_returns_null_when_form_id_does_not_match(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', '10' );
        $args   = $this->makeHookArgs( [ 'form' => $this->makeFormObject( [ 'id' => 5 ] ) ] );
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertNull( $result );
    }

    public function test_trigger_returns_null_for_unknown_event(): void {
        $node   = $this->makeARFormTriggerNode( 'unknown_event', 'any' );
        $args   = $this->makeHookArgs();
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertNull( $result );
    }

    public function test_trigger_returns_null_with_fewer_than_4_args(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $result = ARForm::resolve_trigger( $node, [ [], [] ] ); // only 2 args

        $this->assertNull( $result );
    }

    public function test_trigger_returns_null_with_empty_args(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $result = ARForm::resolve_trigger( $node, [] );

        $this->assertNull( $result );
    }

    public function test_trigger_returns_null_when_form_object_has_no_id(): void {
        $node = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $args = $this->makeHookArgs( [ 'form' => (object) [ 'title' => 'No ID Form' ] ] );

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertNull( $result );
    }

    public function test_trigger_returns_null_when_form_id_resolves_to_zero(): void {
        $node = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $args = $this->makeHookArgs( [ 'form' => (object) [ 'id' => 0 ] ] );

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertNull( $result );
    }

    public function test_trigger_returns_null_when_form_is_not_object_or_numeric(): void {
        $node = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $args = [ [], [], 'invalid_form_value', [] ];

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertNull( $result );
    }

    // ========== TRIGGER: form ID extraction fallbacks ==========

    public function test_trigger_extracts_form_id_via_get_id_method(): void {
        $formWithMethod = new class {
            public function get_id(): int { return 99; }
        };

        $node   = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $args   = $this->makeHookArgs( [ 'form' => $formWithMethod ] );
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertIsArray( $result );
        $this->assertEquals( 99, $result['form_id'] );
    }

    public function test_trigger_extracts_form_id_via_id_property(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $args   = $this->makeHookArgs( [ 'form' => (object) [ 'id' => 77 ] ] );
        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertIsArray( $result );
        $this->assertEquals( 77, $result['form_id'] );
    }

    public function test_trigger_extracts_form_id_when_form_is_numeric(): void {
        $node = $this->makeARFormTriggerNode( 'submit_form', 'any' );
        $args = [ [], [], 42, [] ]; // numeric form ID

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertIsArray( $result );
        $this->assertEquals( 42, $result['form_id'] );
    }

    // ========== EDGE CASES ==========

    public function test_trigger_form_id_type_coercion_string_vs_int(): void {
        $node   = $this->makeARFormTriggerNode( 'submit_form', '5' ); // string
        $args   = $this->makeHookArgs( [ 'form' => $this->makeFormObject( [ 'id' => 5 ] ) ] ); // int

        $result = ARForm::resolve_trigger( $node, $args );

        $this->assertIsArray( $result, 'String "5" should match int 5 after casting' );
        $this->assertEquals( 5, $result['form_id'] );
    }

    public function test_execute_node_returns_main_port_with_input(): void {
        $input  = [ 'form_id' => 5, 'item_meta_values' => [ 'name' => 'Test' ] ];
        $result = ARForm::execute_node( [], $input );

        $this->assertEquals( 'main', $result['port'] );
        $this->assertEquals( $input, $result['data'] );
    }

    // ========== DYNAMIC QUERIES ==========

    public function test_get_dynamic_queries_returns_forms_key(): void {
        $queries = ARForm::get_dynamic_queries();

        $this->assertIsArray( $queries );
        $this->assertArrayHasKey( 'forms', $queries );
        $this->assertIsCallable( $queries['forms'] );
    }

    public function test_query_forms_always_includes_any_option_first(): void {
        // query_forms will return at least "Any Form" even without plugin active
        $result = ARForm::query_forms();

        $this->assertIsArray( $result );
        $this->assertNotEmpty( $result );
        $this->assertEquals( 'Any Form', $result[0]['label'] );
        $this->assertEquals( 'any', $result[0]['value'] );
    }

    // ========== BULK TESTS ==========

    protected function getTriggerTests(): array {
        return [
            'submit_form'      => $this->makeHookArgs(),
            'submit_form_full' => $this->makeHookArgs(),
        ];
    }

    protected function getActionTests(): array {
        return [];
    }
}
