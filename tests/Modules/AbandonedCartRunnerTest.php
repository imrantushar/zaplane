<?php

namespace Zaplane\Tests\Modules;

use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPMocks;
use Zaplane\Modules\AbandonedCart\AbandonedCartRunner;

class AbandonedCartRunnerTest extends TestCase {

	private const SETTINGS_OPTION = 'zaplane_abandoned_cart_settings';

	private array $enabledSettings = [
		'status'                 => true,
		'cart_off_time'          => 30,
		'mark_as_lost_after_minutes' => 10080,
		'cool_off_period'        => 10080,
		'status_of_new_contact'  => 'transactional',
		'mark_as_recovered_when_order_status_changed_to' => [ 'processing', 'completed' ],
		'gdpr_consent_in_woo_checkout_page' => false,
		'gdpr_msg'               => '',
		'disabled_user_roles'    => [],
		'abandoned_list'         => [],
		'abandoned_tags'         => [],
		'lost_list'              => [],
		'lost_tags'              => [],
	];

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->reset();

		$reflector = new \ReflectionClass( \Zaplane\Framework\Database\ORM\QueryBuilder::class );
		$cacheProp = $reflector->getProperty( 'queryCache' );
		$cacheProp->setValue( null, [] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function enableFeature( array $overrides = [] ): void {
		WPMocks::setOption( self::SETTINGS_OPTION, array_merge( $this->enabledSettings, $overrides ) );
	}

	private function setDraftCarts( array $carts ): void {
		global $wpdb;
		$wpdb->tables['results'] = $carts;
	}

	private function setProcessingCarts( array $carts ): void {
		global $wpdb;
		$wpdb->tables['results'] = $carts;
	}

	private function makeGuestCart( array $overrides = [] ): array {
		return array_merge( [
			'id'           => 1,
			'user_id'      => 0,
			'email'        => 'guest@example.com',
			'full_name'    => 'Guest User',
			'status'       => 'draft',
			'contact_id'   => null,
			'updated_at'   => '2026-01-01 00:00:00',
			'abandoned_at' => null,
			'cart'         => json_encode( [] ),
		], $overrides );
	}

	private function makeUserCart( int $user_id = 5, array $overrides = [] ): array {
		return array_merge( [
			'id'           => 2,
			'user_id'      => $user_id,
			'email'        => 'user@example.com',
			'full_name'    => 'Test User',
			'status'       => 'draft',
			'contact_id'   => null,
			'updated_at'   => '2026-01-01 00:00:00',
			'abandoned_at' => null,
			'cart'         => json_encode( [] ),
		], $overrides );
	}

	private function makeProcessingCart( array $overrides = [] ): array {
		return array_merge( [
			'id'           => 3,
			'user_id'      => 0,
			'email'        => 'guest@example.com',
			'full_name'    => 'Guest User',
			'status'       => 'processing',
			'contact_id'   => 42,
			'abandoned_at' => '2026-01-01 00:00:00',
			'cart'         => json_encode( [] ),
		], $overrides );
	}

	private function queriesContain( string $needle ): bool {
		global $wpdb;
		foreach ( $wpdb->preparedQueries as $query ) {
			if ( strpos( $query, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	// ── run_abandoned: feature disabled ───────────────────────────────────────

	public function test_run_abandoned_does_nothing_when_feature_is_disabled(): void {
		// No settings option set → is_enabled() returns false.
		$this->setDraftCarts( [ $this->makeGuestCart() ] );

		AbandonedCartRunner::run_abandoned();

		global $wpdb;
		$this->assertEmpty(
			array_filter( $wpdb->preparedQueries, fn( $q ) => str_contains( $q, 'processing' ) ),
			'Expected no UPDATE to processing when feature is disabled'
		);
	}

	// ── run_abandoned: no draft carts ─────────────────────────────────────────

	public function test_run_abandoned_does_nothing_when_no_draft_carts_exist(): void {
		$this->enableFeature();
		$this->setDraftCarts( [] );

		AbandonedCartRunner::run_abandoned();

		$this->assertFalse(
			$this->queriesContain( 'processing' ),
			'No UPDATE should happen when there are no draft carts'
		);
	}

	// ── run_abandoned: guest cart → set to processing ─────────────────────────

	public function test_run_abandoned_marks_guest_cart_as_processing(): void {
		$this->enableFeature();
		$this->setDraftCarts( [ $this->makeGuestCart() ] );

		AbandonedCartRunner::run_abandoned();

		$this->assertTrue(
			$this->queriesContain( 'processing' ),
			"Expected UPDATE to set status = 'processing' for guest cart"
		);
	}

	public function test_run_abandoned_sets_abandoned_at_timestamp_for_guest_cart(): void {
		$this->enableFeature();
		$this->setDraftCarts( [ $this->makeGuestCart() ] );

		AbandonedCartRunner::run_abandoned();

		$this->assertTrue(
			$this->queriesContain( 'abandoned_at' ),
			'Expected UPDATE to include abandoned_at timestamp'
		);
	}

	// ── run_abandoned: guest cart bypasses cool-off check ────────────────────

	public function test_run_abandoned_guest_cart_bypasses_user_checks_and_gets_processed(): void {
		// user_id=0 skips both cool-off and role checks entirely.
		$this->enableFeature();
		$this->setDraftCarts( [ $this->makeGuestCart() ] );

		AbandonedCartRunner::run_abandoned();

		$this->assertTrue(
			$this->queriesContain( 'processing' ),
			'Guest cart should be marked processing without any user checks'
		);
	}

	// ── run_abandoned: disabled role → skip ───────────────────────────────────
	// Note: get_userdata is mocked by buddyboss.php to always return role='subscriber'.
	// We test role-skipping by disabling 'subscriber' in the settings.

	public function test_run_abandoned_skips_cart_when_user_role_is_disabled(): void {
		// The test mock always returns 'subscriber' for any user (buddyboss.php).
		$this->enableFeature( [ 'disabled_user_roles' => [ 'subscriber' ] ] );
		$this->setDraftCarts( [ $this->makeUserCart( 5 ) ] );

		AbandonedCartRunner::run_abandoned();

		$this->assertTrue(
			$this->queriesContain( 'skipped' ),
			"Expected UPDATE to set status = 'skipped' for disabled role 'subscriber'"
		);
		$this->assertFalse(
			$this->queriesContain( 'processing' ),
			'Cart with disabled role should not be marked processing'
		);
	}

	// ── run_abandoned: multiple carts ─────────────────────────────────────────

	public function test_run_abandoned_processes_multiple_guest_carts(): void {
		$this->enableFeature();
		$this->setDraftCarts( [
			$this->makeGuestCart( [ 'id' => 1 ] ),
			$this->makeGuestCart( [ 'id' => 2, 'email' => 'second@example.com' ] ),
		] );

		AbandonedCartRunner::run_abandoned();

		global $wpdb;
		$processingUpdates = array_filter(
			$wpdb->preparedQueries,
			fn( $q ) => str_contains( $q, 'processing' )
		);
		$this->assertCount( 2, $processingUpdates, 'Expected exactly 2 UPDATE processing queries for 2 carts' );
	}

	// ── run_lost: feature disabled ─────────────────────────────────────────────

	public function test_run_lost_does_nothing_when_feature_is_disabled(): void {
		$this->setProcessingCarts( [ $this->makeProcessingCart() ] );

		AbandonedCartRunner::run_lost();

		$this->assertFalse(
			$this->queriesContain( 'lost' ),
			"No UPDATE to 'lost' when feature is disabled"
		);
	}

	// ── run_lost: no processing carts ─────────────────────────────────────────

	public function test_run_lost_does_nothing_when_no_processing_carts(): void {
		$this->enableFeature();
		$this->setProcessingCarts( [] );

		AbandonedCartRunner::run_lost();

		$this->assertFalse(
			$this->queriesContain( 'lost' ),
			"No UPDATE to 'lost' when there are no processing carts"
		);
	}

	// ── run_lost: cart → set to lost ──────────────────────────────────────────

	public function test_run_lost_marks_processing_cart_as_lost(): void {
		$this->enableFeature();
		$this->setProcessingCarts( [ $this->makeProcessingCart() ] );

		AbandonedCartRunner::run_lost();

		$this->assertTrue(
			$this->queriesContain( 'lost' ),
			"Expected UPDATE to set status = 'lost'"
		);
	}

	// ── schedule_recurring ────────────────────────────────────────────────────

	public function test_schedule_recurring_registers_abandoned_action_when_not_scheduled(): void {
		WPMocks::scheduleAction( 'zaplane_ab_cart_check_abandoned', 0 );

		// First call: both actions not yet scheduled.
		AbandonedCartRunner::schedule_recurring();

		$this->assertNotFalse(
			WPMocks::getScheduledAction( 'zaplane_ab_cart_check_abandoned' ),
			'Expected zaplane_ab_cart_check_abandoned to be scheduled'
		);
	}

	public function test_schedule_recurring_registers_lost_action_when_not_scheduled(): void {
		AbandonedCartRunner::schedule_recurring();

		$this->assertNotFalse(
			WPMocks::getScheduledAction( 'zaplane_ab_cart_check_lost' ),
			'Expected zaplane_ab_cart_check_lost to be scheduled'
		);
	}

	public function test_schedule_recurring_does_not_double_schedule_if_already_registered(): void {
		$ts = time() + 300;
		WPMocks::scheduleAction( 'zaplane_ab_cart_check_abandoned', $ts );
		WPMocks::scheduleAction( 'zaplane_ab_cart_check_lost', $ts );

		AbandonedCartRunner::schedule_recurring();

		// Timestamps should not change since actions were already scheduled.
		$this->assertSame( $ts, WPMocks::getScheduledAction( 'zaplane_ab_cart_check_abandoned' ) );
		$this->assertSame( $ts, WPMocks::getScheduledAction( 'zaplane_ab_cart_check_lost' ) );
	}
}
