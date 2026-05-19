<?php

namespace Zaplane\Tests\Modules;

use Zaplane\Tests\TestCase;
use Zaplane\Integrations\Gemcrm;

require_once __DIR__ . '/../Integrations/Support/GemcrmTestStubs.php';

/**
 * Tests for BirthdayCronTrait (via Gemcrm).
 *
 * Covers:
 *  - register_birthday_cron() is idempotent
 *  - handle_birthday_batch() skips contacts with current-year dedup meta
 *  - handle_birthday_batch() fires trigger for contacts without dedup meta
 */
class BirthdayCronTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->reset();

		$reflector = new \ReflectionClass( \Zaplane\Framework\Database\ORM\QueryBuilder::class );
		$cacheProp = $reflector->getProperty( 'queryCache' );
		$cacheProp->setValue( null, [] );
	}

	// =========================================================================
	// register_birthday_cron — idempotency
	// =========================================================================

	public function test_register_birthday_cron_schedules_action_when_not_already_scheduled(): void {
		// as_next_scheduled_action returns false (not scheduled)
		\Zaplane\Tests\WPMocks::$as_next_scheduled_action_return = false;
		\Zaplane\Tests\WPMocks::$as_schedule_recurring_action_calls = [];

		Gemcrm::register_birthday_cron();

		$this->assertCount(
			1,
			\Zaplane\Tests\WPMocks::$as_schedule_recurring_action_calls,
			'Expected one call to as_schedule_recurring_action'
		);
		$call = \Zaplane\Tests\WPMocks::$as_schedule_recurring_action_calls[0];
		$this->assertSame( 'zaplane_gemcrm_birthday_check', $call['hook'] );
		$this->assertSame( 'zaplane_birthday', $call['group'] );
	}

	public function test_register_birthday_cron_skips_scheduling_when_already_scheduled(): void {
		// as_next_scheduled_action returns a future timestamp (already scheduled)
		\Zaplane\Tests\WPMocks::$as_next_scheduled_action_return = time() + 3600;
		\Zaplane\Tests\WPMocks::$as_schedule_recurring_action_calls = [];

		Gemcrm::register_birthday_cron();

		$this->assertCount(
			0,
			\Zaplane\Tests\WPMocks::$as_schedule_recurring_action_calls,
			'Should not schedule when action is already registered'
		);
	}

	// =========================================================================
	// handle_birthday_batch — deduplication
	// =========================================================================

	public function test_handle_birthday_batch_skips_contact_already_triggered_this_year(): void {
		global $wpdb;
		$current_year = (int) gmdate( 'Y' );

		// Simulate that contact 10 already has the dedup meta for this year.
		$wpdb->set_get_var_result(
			json_encode( [ 'year' => $current_year ] )
		);

		\Zaplane\Tests\WPMocks::$do_action_calls = [];

		Gemcrm::handle_birthday_batch( [ 10 ] );

		$birthday_fires = array_filter(
			\Zaplane\Tests\WPMocks::$do_action_calls,
			fn( $c ) => $c['hook'] === 'zaplane_gemcrm_contact_birthday'
		);

		$this->assertCount( 0, $birthday_fires, 'Should not fire trigger for already-triggered contact' );
	}

	public function test_handle_birthday_batch_fires_trigger_for_new_contact(): void {
		global $wpdb;

		// Simulate no dedup meta exists for this contact.
		$wpdb->set_get_var_result( null );

		// Simulate contact data returned from Contact::by_id().
		\GemCrm\Database\Models\Contact::$by_id_stub = [
			'id'         => 20,
			'first_name' => 'Jane',
			'last_name'  => 'Doe',
			'email'      => 'jane@example.com',
			'phone'      => '',
			'meta'       => [ 'dob' => '1992-05-01' ],
			'lists'      => [],
			'tags'       => [],
		];

		\Zaplane\Tests\WPMocks::$do_action_calls = [];

		Gemcrm::handle_birthday_batch( [ 20 ] );

		$birthday_fires = array_filter(
			\Zaplane\Tests\WPMocks::$do_action_calls,
			fn( $c ) => $c['hook'] === 'zaplane_gemcrm_contact_birthday'
		);

		$this->assertCount( 1, $birthday_fires, 'Should fire trigger for contact without dedup meta' );
	}
}
