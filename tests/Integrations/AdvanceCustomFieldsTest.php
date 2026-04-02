<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\AdvanceCustomFields;

class AdvanceCustomFieldsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return AdvanceCustomFields::class;
	}

	protected function getTriggerTests(): array {
		return [
			'acf_save_post'          => [ 1 ],
			'acf_post_field_updated' => [ 99, 1, 'my_field', 'hello' ],
			'acf_user_field_updated' => [ 99, 1, 'bio', 'some bio' ],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_post_field'       => [ 'post_id' => 1, 'field_name' => 'my_field' ],
			'get_user_field'       => [ 'user_id' => 1, 'field_name' => 'bio' ],
			'get_options_field'    => [ 'field_name' => 'site_color' ],
			'update_post_field'    => [ 'post_id' => 1, 'field_name' => 'my_field', 'value' => 'new_val' ],
			'update_user_field'    => [ 'user_id' => 1, 'field_name' => 'bio', 'value' => 'updated bio' ],
			'update_options_field' => [ 'field_name' => 'site_color', 'value' => 'blue' ],
			'update_repeater_field' => [ 'post_id' => 1, 'field_name' => 'items', 'rows' => [] ],
			'update_group_field'   => [ 'post_id' => 1, 'field_name' => 'address', 'sub_fields' => [] ],
		];
	}

	// =========================================================================
	// TRIGGER: acf_save_post
	// =========================================================================

	public function test_trigger_acf_save_post_returns_post_id_and_fields(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_save_post' ),
			[ 1 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['post_id'] );
		$this->assertArrayHasKey( 'fields', $result );
		$this->assertArrayHasKey( 'saved_at', $result );
	}

	public function test_trigger_acf_save_post_returns_false_without_post_id(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_save_post' ),
			[ 0 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_acf_save_post_filters_by_post_id(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_save_post', [ 'post_id' => '99' ] ),
			[ 1 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_acf_save_post_passes_when_post_id_matches(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_save_post', [ 'post_id' => '1' ] ),
			[ 1 ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['post_id'] );
	}

	// =========================================================================
	// TRIGGER: acf_post_field_updated
	// =========================================================================

	public function test_trigger_post_field_updated_returns_field_data(): void {
		// args: meta_id, object_id, meta_key, meta_value
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_post_field_updated' ),
			[ 99, 1, 'my_field', 'hello' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['post_id'] );
		$this->assertEquals( 'my_field', $result['field_name'] );
		$this->assertEquals( 'hello', $result['value'] );
		$this->assertArrayHasKey( 'updated_at', $result );
	}

	public function test_trigger_post_field_updated_skips_internal_keys(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_post_field_updated' ),
			[ 99, 1, '_my_field', 'field_abc123' ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_post_field_updated_returns_false_without_post_id(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_post_field_updated' ),
			[ 99, 0, 'my_field', 'val' ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_post_field_updated_filters_by_field_name(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_post_field_updated', [ 'field_name' => 'other_field' ] ),
			[ 99, 1, 'my_field', 'val' ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_post_field_updated_passes_when_field_matches(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_post_field_updated', [ 'field_name' => 'my_field' ] ),
			[ 99, 1, 'my_field', 'val' ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 'my_field', $result['field_name'] );
	}

	// =========================================================================
	// TRIGGER: acf_user_field_updated
	// =========================================================================

	public function test_trigger_user_field_updated_returns_user_and_field_data(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_user_field_updated' ),
			[ 99, 1, 'bio', 'some bio' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['user_id'] );
		$this->assertEquals( 'bio', $result['field_name'] );
		$this->assertEquals( 'some bio', $result['value'] );
		$this->assertArrayHasKey( 'updated_at', $result );
	}

	public function test_trigger_user_field_updated_skips_internal_keys(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_user_field_updated' ),
			[ 99, 1, '_bio', 'field_abc' ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_user_field_updated_returns_false_without_user_id(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_user_field_updated' ),
			[ 99, 0, 'bio', 'val' ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_user_field_updated_filters_by_field_name(): void {
		$result = AdvanceCustomFields::resolve_trigger(
			$this->makeTriggerNode( 'acf_user_field_updated', [ 'field_name' => 'other' ] ),
			[ 99, 1, 'bio', 'val' ]
		);
		$this->assertFalse( $result );
	}

	// =========================================================================
	// TRIGGER: unknown event
	// =========================================================================

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'acf_save_post' );
		$node['event'] = '__unknown__';
		$this->assertFalse( AdvanceCustomFields::resolve_trigger( $node, [] ) );
	}

	// =========================================================================
	// ACTIONS
	// =========================================================================

	public function test_action_get_post_field_returns_post_id_and_field_name(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'get_post_field', [ 'post_id' => 1, 'field_name' => 'my_field' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 1, $result['data']['post_id'] );
		$this->assertEquals( 'my_field', $result['data']['field_name'] );
		$this->assertArrayHasKey( 'value', $result['data'] );
	}

	public function test_action_get_user_field_returns_user_id_and_field_name(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'get_user_field', [ 'user_id' => 1, 'field_name' => 'bio' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 1, $result['data']['user_id'] );
		$this->assertEquals( 'bio', $result['data']['field_name'] );
	}

	public function test_action_get_options_field_returns_field_name(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'get_options_field', [ 'field_name' => 'site_color' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'site_color', $result['data']['field_name'] );
	}

	public function test_action_update_post_field_returns_value(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'update_post_field', [ 'post_id' => 1, 'field_name' => 'my_field', 'value' => 'new_val' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'new_val', $result['data']['value'] );
	}

	public function test_action_update_user_field_returns_value(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'update_user_field', [ 'user_id' => 1, 'field_name' => 'bio', 'value' => 'updated bio' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'updated bio', $result['data']['value'] );
	}

	public function test_action_update_options_field_returns_value(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'update_options_field', [ 'field_name' => 'site_color', 'value' => 'blue' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'blue', $result['data']['value'] );
	}

	public function test_action_update_repeater_field_accepts_array_rows(): void {
		$rows   = [ [ 'title' => 'Row 1' ], [ 'title' => 'Row 2' ] ];
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'update_repeater_field', [ 'post_id' => 1, 'field_name' => 'items', 'rows' => $rows ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $rows, $result['data']['rows'] );
	}

	public function test_action_update_repeater_field_decodes_json_string(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'update_repeater_field', [ 'post_id' => 1, 'field_name' => 'items', 'rows' => '[{"title":"Row 1"}]' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertIsArray( $result['data']['rows'] );
		$this->assertEquals( 'Row 1', $result['data']['rows'][0]['title'] );
	}

	public function test_action_update_group_field_accepts_array_sub_fields(): void {
		$sub = [ 'street' => '123 Main St', 'city' => 'Springfield' ];
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'update_group_field', [ 'post_id' => 1, 'field_name' => 'address', 'sub_fields' => $sub ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $sub, $result['data']['sub_fields'] );
	}

	public function test_action_update_group_field_decodes_json_string(): void {
		$result = AdvanceCustomFields::execute_node(
			$this->makeActionNode( 'update_group_field', [ 'post_id' => 1, 'field_name' => 'address', 'sub_fields' => '{"city":"Springfield"}' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'Springfield', $result['data']['sub_fields']['city'] );
	}

	// =========================================================================
	// SCHEMA & CONTRACT
	// =========================================================================

	public function test_get_slug_returns_advancecustomfields(): void {
		$this->assertEquals( 'advancecustomfields', AdvanceCustomFields::get_slug() );
	}

	public function test_all_triggers_registered_with_label_and_hook(): void {
		foreach ( AdvanceCustomFields::get_triggers() as $event => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Trigger '$event' missing label" );
			$this->assertArrayHasKey( 'hook', $def, "Trigger '$event' missing hook" );
			$this->assertNotEmpty( $def['label'] );
			$this->assertNotEmpty( $def['hook'] );
		}
	}

	public function test_all_actions_registered_with_label(): void {
		foreach ( AdvanceCustomFields::get_actions() as $event => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Action '$event' missing label" );
			$this->assertNotEmpty( $def['label'] );
		}
	}

	public function test_user_field_updated_hook_is_array(): void {
		$triggers = AdvanceCustomFields::get_triggers();
		$this->assertIsArray( $triggers['acf_user_field_updated']['hook'] );
		$this->assertContains( 'updated_user_meta', $triggers['acf_user_field_updated']['hook'] );
		$this->assertContains( 'added_user_meta', $triggers['acf_user_field_updated']['hook'] );
	}

	public function test_post_field_updated_hook_is_updated_post_meta(): void {
		$triggers = AdvanceCustomFields::get_triggers();
		$this->assertEquals( 'updated_post_meta', $triggers['acf_post_field_updated']['hook'] );
	}
}
