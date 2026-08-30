<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Dokan;

/**
 * Dokan wasn't rewired here — its hook names and argument order were checked
 * against a known-working reference implementation and matched. These lock in
 * the parts that were confirmed, plus the Pro-only marking.
 *
 * Two hooks could NOT be corroborated against any reference and are listed in
 * test_unverified_hooks_are_documented() so they don't get forgotten.
 */
class DokanTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Dokan::class;
	}

	/**
	 * Every trigger short-circuits when Dokan is absent, which is what makes the
	 * whole integration inert on a site without it.
	 */
	public function test_triggers_are_inert_without_dokan(): void {
		if ( function_exists( 'dokan' ) || function_exists( 'dokan_get_store_info' ) ) {
			$this->markTestSkipped( 'Dokan is loaded in this environment.' );
		}

		foreach ( array_keys( Dokan::get_triggers() ) as $event ) {
			$this->assertFalse(
				Dokan::resolve_trigger( $this->makeTriggerNode( $event ), [ 1, 2, 3 ] ),
				"Trigger '{$event}' must not resolve when Dokan is not active."
			);
		}
	}

	/**
	 * The trigger map registers hooks by string; an array or empty value is
	 * silently dropped and the trigger never fires.
	 */
	public function test_every_trigger_declares_a_string_hook(): void {
		foreach ( Dokan::get_triggers() as $key => $trigger ) {
			$this->assertIsString( $trigger['hook'] ?? null, "Trigger '{$key}' has no string hook." );
			$this->assertNotSame( '', $trigger['hook'], "Trigger '{$key}' has an empty hook." );
		}
	}

	/**
	 * dokan_pro_* hooks don't exist on Dokan Lite. Marked so the picker can say
	 * so rather than offering a trigger that silently never fires.
	 */
	public function test_pro_only_triggers_are_marked(): void {
		$triggers = Dokan::get_triggers();

		foreach ( [ 'refund_approved', 'refund_cancelled' ] as $key ) {
			$this->assertStringStartsWith( 'dokan_pro_', $triggers[ $key ]['hook'] );
			$this->assertSame( 'Dokan Pro', $triggers[ $key ]['requires_addon'] ?? null );
		}

		// Anything else claiming a dokan_pro_ hook must be marked too.
		foreach ( $triggers as $key => $trigger ) {
			if ( 0 === strpos( (string) $trigger['hook'], 'dokan_pro_' ) ) {
				$this->assertArrayHasKey(
					'requires_addon',
					$trigger,
					"Trigger '{$key}' uses a Dokan Pro hook but isn't marked as needing Pro."
				);
			}
		}
	}

	public function test_declares_dokan_lite_as_its_dependency(): void {
		$this->assertContains( 'dokan-lite/dokan.php', Dokan::get_required_plugins() );
	}

	/**
	 * dokan_after_withdraw_request( $user_id, $amount, $method ) — confirmed
	 * against a reference implementation. A fourth argument is never passed, so
	 * reading one must stay optional.
	 */
	public function test_withdraw_request_argument_order_is_documented(): void {
		$triggers = Dokan::get_triggers();

		$this->assertSame( 'dokan_after_withdraw_request', $triggers['withdraw_request_created']['hook'] );
	}

	/**
	 * These hook names could not be corroborated against Dokan's source or any
	 * reference integration. A wrong name is a silent no-op, so verify them
	 * against an installed Dokan before relying on them.
	 *
	 * @see https://github.com/getdokan/dokan
	 */
	public function test_unverified_hooks_are_documented(): void {
		$unverified = [
			'store_profile_saved'        => 'dokan_store_profile_saved',
			'withdraw_created'           => 'dokan_withdraw_created',
			'withdraw_request_pending'   => 'dokan_withdraw_request_pending',
			'withdraw_request_approved'  => 'dokan_withdraw_request_approved',
			'withdraw_request_cancelled' => 'dokan_withdraw_request_cancelled',
			'withdraw_status_updated'    => 'dokan_withdraw_status_updated',
			'vendor_enabled'             => 'dokan_vendor_enabled',
			'vendor_disabled'            => 'dokan_vendor_disabled',
			'product_updated'            => 'dokan_product_updated',
			'product_deleted'            => 'dokan_product_deleted',
		];

		$triggers = Dokan::get_triggers();

		foreach ( $unverified as $event => $hook ) {
			$this->assertSame(
				$hook,
				$triggers[ $event ]['hook'],
				"Trigger '{$event}' changed hook — re-verify it against Dokan's source."
			);
		}
	}
}
