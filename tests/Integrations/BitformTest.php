<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Bitform;

/**
 * Test suite for the Bit Form integration.
 *
 * ── How this file is structured ──────────────────────────────────────────────
 *
 * 1. CONTRACT (inherited)
 *    IntegrationTestCase auto-runs:
 *      - all_tested_triggers_are_registered   — every key in getTriggerTests()
 *                                               must exist in get_triggers()
 *      - triggers_fire_and_return_payload      — bulk: each trigger returns array|false
 *      - actions_execute_and_return_valid_format — bulk: each action returns {port,data}
 *
 * 2. TRIGGER TESTS  (hand-written)
 *    submit_form:
 *      - Happy path: valid args → assert key fields in returned array.
 *      - Sad paths:  invalid/missing args, mismatched form_id → assert false.
 *      - Edge cases: 'any' wildcard, string-typed args from WP hooks.
 *
 * 3. ACTION TESTS
 *    Bit Form has no actions — execute_node() is a passthrough.
 *
 * ── Reading guide ─────────────────────────────────────────────────────────────
 *
 *  makeTriggerNode($event, $config)
 *      Builds the $node array resolve_trigger() receives.
 *      $config maps to $node['data']['config'] — this is how "selected form" is passed in.
 *
 *  getTriggerTests()
 *      Provides minimal valid args for each trigger to satisfy the bulk runner.
 *      The bulk runner only checks array|false — detailed checks are in the
 *      hand-written tests below.
 *
 * ── Triggers covered ──────────────────────────────────────────────────────────
 *
 *   submit_form — fired on bitform_submit_success
 * ─────────────────────────────────────────────────────────────────────────────
 */
class BitformTest extends IntegrationTestCase
{
    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    protected function getIntegrationClass(): string
    {
        return Bitform::class;
    }

    // -------------------------------------------------------------------------
    // Bulk runner data
    // -------------------------------------------------------------------------

    protected function getTriggerTests(): array
    {
        return [
            // submit_form: form_id, entry_id, form_data, files
            'submit_form' => [ 1, 42, [], [] ],
        ];
    }

    // =========================================================================
    // TRIGGER: submit_form
    // =========================================================================

    public function test_trigger_submit_form_returns_form_and_entry_ids(): void
    {
        $files  = [ 'file1.jpg' ];
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form' ),
            [ 5, 42, [ 'field' => 'value' ], $files ]
        );

        $this->assertIsArray( $result );
        $this->assertSame( 5,      $result['form_id'] );
        $this->assertSame( 42,     $result['entry_id'] );
        $this->assertSame( $files, $result['files'] );
    }

    public function test_trigger_submit_form_returns_false_for_wrong_event(): void
    {
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'wrong_event' ),
            [ 1, 2, [], [] ]
        );

        $this->assertFalse( $result );
    }

    public function test_trigger_submit_form_returns_false_when_args_too_few(): void
    {
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form' ),
            [ 1, 2, [] ]   // only 3 args — 4 required
        );

        $this->assertFalse( $result );
    }

    public function test_trigger_submit_form_returns_false_when_form_id_is_zero(): void
    {
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form' ),
            [ 0, 1, [], [] ]
        );

        $this->assertFalse( $result );
    }

    public function test_trigger_submit_form_returns_false_when_form_id_does_not_match_config(): void
    {
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form', [ 'form_id' => '99' ] ),
            [ 1, 42, [], [] ]   // submitted form_id=1, configured for 99
        );

        $this->assertFalse( $result );
    }

    public function test_trigger_submit_form_passes_any_form_when_config_is_any(): void
    {
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form', [ 'form_id' => 'any' ] ),
            [ 7, 42, [], [] ]
        );

        $this->assertIsArray( $result );
        $this->assertSame( 7, $result['form_id'] );
    }

    public function test_trigger_submit_form_passes_when_form_id_matches_config(): void
    {
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form', [ 'form_id' => '4' ] ),
            [ 4, 99, [], [] ]
        );

        $this->assertIsArray( $result );
        $this->assertSame( 4,  $result['form_id'] );
        $this->assertSame( 99, $result['entry_id'] );
    }

    public function test_trigger_submit_form_defaults_to_any_when_config_missing(): void
    {
        // No 'config' key at all — should behave as 'any'.
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form' ),
            [ 3, 10, [], [] ]
        );

        $this->assertIsArray( $result );
        $this->assertSame( 3, $result['form_id'] );
    }

    public function test_trigger_submit_form_casts_string_form_id_arg_to_int(): void
    {
        // WP hooks can fire args as strings.
        $result = Bitform::resolve_trigger(
            $this->makeTriggerNode( 'submit_form', [ 'form_id' => '4' ] ),
            [ '4', 1, [], [] ]
        );

        $this->assertIsArray( $result );
        $this->assertSame( 4, $result['form_id'] );
    }

    // =========================================================================
    // ACTIONS
    // =========================================================================

    /**
     * Bit Form has no actions — execute_node() is a passthrough that returns
     * the input data unchanged on the 'main' port.
     */
    public function test_execute_node_is_passthrough(): void
    {
        $input  = [ 'form_id' => 1, 'entry_id' => 42 ];
        $result = Bitform::execute_node(
            $this->makeActionNode( '__any__', [] ),
            $input
        );

        $this->assertSame( 'main', $result['port'] );
        $this->assertSame( $input, $result['data'] );
    }

    // =========================================================================
    // SCHEMA & CONTRACT
    // =========================================================================

    public function test_get_slug_returns_bitform(): void
    {
        $this->assertSame( 'bitform', Bitform::get_slug() );
    }

    public function test_get_name_returns_bit_form(): void
    {
        $this->assertSame( 'Bit Form', Bitform::get_name() );
    }

    public function test_all_triggers_registered(): void
    {
        $triggers = Bitform::get_triggers();
        foreach ( [ 'submit_form' ] as $event ) {
            $this->assertArrayHasKey( $event, $triggers, "Trigger '$event' missing from get_triggers()" );
        }
    }

    public function test_trigger_config_schema_for_submit_form_has_form_id_field(): void
    {
        $schema = Bitform::get_trigger_config_schema( 'submit_form' );

        $this->assertCount( 1, $schema );
        $this->assertSame( 'form_id', $schema[0]['key'] );
        $this->assertSame( 'select',  $schema[0]['type'] );
        $this->assertTrue( $schema[0]['required'] );
        $this->assertSame( 'bitform', $schema[0]['dynamic']['integration'] );
        $this->assertSame( 'forms',   $schema[0]['dynamic']['query'] );
    }

    public function test_trigger_config_schema_returns_empty_for_unknown_trigger(): void
    {
        $this->assertSame( [], Bitform::get_trigger_config_schema( '__unknown__' ) );
    }

    public function test_get_actions_returns_empty_array(): void
    {
        $this->assertSame( [], Bitform::get_actions() );
    }

    public function test_get_output_ports_contains_main(): void
    {
        $this->assertArrayHasKey( 'main', Bitform::get_output_ports() );
    }

    public function test_get_dynamic_queries_has_forms_key(): void
    {
        $queries = Bitform::get_dynamic_queries();

        $this->assertArrayHasKey( 'forms', $queries );
        $this->assertIsCallable( $queries['forms'] );
    }

    // =========================================================================
    // DYNAMIC QUERIES
    // =========================================================================

    public function test_query_forms_returns_any_option_when_bitform_not_loaded(): void
    {
        // BitCode\BitForm\API\BitForm_Public\BitForm_Public will not exist in the
        // unit test environment, so class_exists() returns false naturally.
        $options = Bitform::query_forms();

        $this->assertIsArray( $options );
        $this->assertSame( 'any', $options[0]['value'] );
        $this->assertSame( 'Any form', $options[0]['label'] );
    }
}
