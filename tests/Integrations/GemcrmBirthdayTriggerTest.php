<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Gemcrm;

require_once __DIR__ . '/Support/GemcrmTestStubs.php';

class GemcrmBirthdayTriggerTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Gemcrm::class;
	}

	private function sampleContact( array $overrides = [] ): array {
		return array_merge( [
			'id'         => 5,
			'first_name' => 'Alice',
			'last_name'  => 'Smith',
			'email'      => 'alice@example.com',
			'phone'      => '+1111111111',
			'meta'       => [ 'dob' => '1990-03-15' ],
			'lists'      => [],
			'tags'       => [],
		], $overrides );
	}

	private function triggerNode( array $config = [] ): array {
		return [
			'type'  => 'trigger',
			'event' => 'contact_birthday',
			'data'  => [
				'app'    => 'gemcrm',
				'event'  => 'contact_birthday',
				'hook'   => 'zaplane_gemcrm_contact_birthday',
				'config' => $config,
			],
		];
	}

	// =========================================================================
	// resolve_trigger — contact_birthday
	// =========================================================================

	public function test_trigger_contact_birthday_returns_payload_with_required_keys(): void {
		$contact = $this->sampleContact();

		$result = Gemcrm::resolve_trigger( $this->triggerNode()['data'], [ $contact ] );

		$this->assertIsArray( $result );
		foreach ( [ 'contact_id', 'first_name', 'last_name', 'email', 'phone', 'dob', 'meta', 'lists', 'tags' ] as $key ) {
			$this->assertArrayHasKey( $key, $result, "Missing key: {$key}" );
		}
	}

	public function test_trigger_contact_birthday_maps_dob_from_meta(): void {
		$contact = $this->sampleContact( [ 'meta' => [ 'dob' => '1985-07-04' ] ] );

		$result = Gemcrm::resolve_trigger( $this->triggerNode()['data'], [ $contact ] );

		$this->assertSame( '1985-07-04', $result['dob'] );
	}

	public function test_trigger_contact_birthday_sets_birthday_flag(): void {
		$result = Gemcrm::resolve_trigger( $this->triggerNode()['data'], [ $this->sampleContact() ] );

		$this->assertArrayHasKey( '_zaplane_birthday', $result );
		$this->assertTrue( $result['_zaplane_birthday'] );
	}

	public function test_trigger_contact_birthday_returns_false_for_empty_data(): void {
		$result = Gemcrm::resolve_trigger( $this->triggerNode()['data'], [ [] ] );

		$this->assertFalse( $result );
	}

	public function test_trigger_contact_birthday_returns_false_when_id_missing(): void {
		$contact = $this->sampleContact();
		unset( $contact['id'] );

		$result = Gemcrm::resolve_trigger( $this->triggerNode()['data'], [ $contact ] );

		$this->assertFalse( $result );
	}

	public function test_trigger_contact_birthday_returns_false_for_no_args(): void {
		$result = Gemcrm::resolve_trigger( $this->triggerNode()['data'], [] );

		$this->assertFalse( $result );
	}

	public function test_trigger_contact_birthday_casts_id_to_int(): void {
		$contact = $this->sampleContact( [ 'id' => '99' ] );

		$result = Gemcrm::resolve_trigger( $this->triggerNode()['data'], [ $contact ] );

		$this->assertSame( 99, $result['contact_id'] );
	}

	// =========================================================================
	// get_trigger_config_schema — contact_birthday
	// =========================================================================

	public function test_trigger_config_schema_returns_purchase_tag_field(): void {
		$schema = Gemcrm::get_trigger_config_schema( 'contact_birthday' );

		$this->assertIsArray( $schema );
		$this->assertCount( 1, $schema );
		$this->assertSame( 'purchase_tag_id', $schema[0]['key'] );
		$this->assertSame( 'select', $schema[0]['type'] );
		$this->assertFalse( $schema[0]['required'] );
	}

	public function test_trigger_config_schema_uses_gemcrm_tag_query(): void {
		$schema = Gemcrm::get_trigger_config_schema( 'contact_birthday' );

		$this->assertSame( 'gemcrm_tag_query', $schema[0]['dynamic']['query'] );
	}

	// =========================================================================
	// get_trigger_sample_output — contact_birthday
	// =========================================================================

	public function test_sample_output_includes_birthday_fields(): void {
		$sample = Gemcrm::get_trigger_sample_output( 'contact_birthday' );

		$this->assertNotEmpty( $sample );
		$this->assertArrayHasKey( 'contact_id', $sample );
		$this->assertArrayHasKey( 'email', $sample );
		$this->assertArrayHasKey( 'dob', $sample );
		$this->assertTrue( $sample['_zaplane_birthday'] );
	}

	// =========================================================================
	// get_triggers — contact_birthday registered
	// =========================================================================

	public function test_contact_birthday_is_registered_trigger(): void {
		$triggers = Gemcrm::get_triggers();

		$this->assertArrayHasKey( 'contact_birthday', $triggers );
		$this->assertSame( 'zaplane_gemcrm_contact_birthday', $triggers['contact_birthday']['hook'] );
	}
}
