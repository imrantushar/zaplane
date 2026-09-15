<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Database\Seeders\StoreengineEmailsGroupSeeder;
use Zaplane\Models\Recipe;
use Zaplane\Services\StoreengineEmailHandover;
use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPDBMock;

class StoreengineEmailHandoverTest extends TestCase {

	private const CONFIRMATION = [
		'admin'    => [
			'is_enable'     => true,
			'email_subject' => 'New order #{order_id} placed',
		],
		'customer' => [
			'is_enable'     => true,
			'email_subject' => 'Your order #{order_id} has been placed',
		],
	];

	/** @var mixed */
	private $wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb      = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = new WPDBMock();
		StoreengineEmailHandover::flush();
	}

	protected function tearDown(): void {
		StoreengineEmailHandover::flush();
		$GLOBALS['wpdb'] = $this->wpdb;
		parent::tearDown();
	}

	/**
	 * The workflows the database holds.
	 *
	 * @param array<int,array{id:int,status:string}> $workflows
	 */
	private function workflows( array $workflows ): void {
		$GLOBALS['wpdb']->setTable( 'results', $workflows );
	}

	private function recipe( string $slug ): Recipe {
		$recipe       = new Recipe();
		$recipe->slug = $slug;

		return $recipe;
	}

	public function test_a_live_workflow_switches_off_storeengines_copy_of_its_email(): void {
		update_option( StoreengineEmailHandover::OPTION, [ 12 => 'order_confirmation.customer' ] );
		$this->workflows( [ [ 'id' => 12, 'status' => 'active' ] ] );

		$setting = StoreengineEmailHandover::filter_setting( self::CONFIRMATION, 'order_confirmation' );

		$this->assertFalse( $setting['customer']['is_enable'] );
		$this->assertSame( self::CONFIRMATION['customer']['email_subject'], $setting['customer']['email_subject'] );
		$this->assertTrue( $setting['admin']['is_enable'], 'Only the customer copy was taken over.' );
	}

	public function test_a_paused_or_draft_workflow_leaves_the_email_to_storeengine(): void {
		update_option(
			StoreengineEmailHandover::OPTION,
			[
				12 => 'order_confirmation.customer',
				13 => 'order_confirmation.admin',
			]
		);
		$this->workflows(
			[
				[ 'id' => 12, 'status' => 'paused' ],
				[ 'id' => 13, 'status' => 'draft' ],
			]
		);

		$this->assertSame( self::CONFIRMATION, StoreengineEmailHandover::filter_setting( self::CONFIRMATION, 'order_confirmation' ) );
	}

	public function test_other_settings_pass_through_untouched(): void {
		update_option( StoreengineEmailHandover::OPTION, [ 12 => 'order_payment_failed.admin' ] );
		$this->workflows( [ [ 'id' => 12, 'status' => 'active' ] ] );

		$this->assertSame( self::CONFIRMATION, StoreengineEmailHandover::filter_setting( self::CONFIRMATION, 'order_confirmation' ) );
		$this->assertSame( 'StoreEngine', StoreengineEmailHandover::filter_setting( 'StoreEngine', 'form_name' ) );

		// A setting without that recipient isn't given one.
		$customer_only = [ 'customer' => [ 'is_enable' => true ] ];
		$this->assertSame( $customer_only, StoreengineEmailHandover::filter_setting( $customer_only, 'order_payment_failed' ) );
	}

	public function test_a_site_that_never_set_up_the_group_asks_the_database_nothing(): void {
		$this->workflows( [ [ 'id' => 12, 'status' => 'active' ] ] );

		$this->assertSame( self::CONFIRMATION, StoreengineEmailHandover::filter_setting( self::CONFIRMATION, 'order_confirmation' ) );
		$this->assertSame( [], $GLOBALS['wpdb']->preparedQueries );
	}

	public function test_it_asks_once_a_request(): void {
		update_option( StoreengineEmailHandover::OPTION, [ 12 => 'order_confirmation.customer' ] );
		$this->workflows( [ [ 'id' => 12, 'status' => 'active' ] ] );

		StoreengineEmailHandover::filter_setting( self::CONFIRMATION, 'order_confirmation' );
		$this->workflows( [ [ 'id' => 12, 'status' => 'paused' ] ] );

		$this->assertFalse( StoreengineEmailHandover::filter_setting( self::CONFIRMATION, 'order_confirmation' )['customer']['is_enable'] );
		$this->assertCount( 1, $GLOBALS['wpdb']->preparedQueries );

		StoreengineEmailHandover::flush();

		$this->assertTrue( StoreengineEmailHandover::filter_setting( self::CONFIRMATION, 'order_confirmation' )['customer']['is_enable'] );
	}

	public function test_setting_up_the_group_notes_the_email_each_workflow_took_over(): void {
		update_option( StoreengineEmailHandover::OPTION, [ 3 => 'order_note.customer' ] );

		StoreengineEmailHandover::record(
			[
				'folder'    => [
					'id'    => 7,
					'title' => 'StoreEngine Store Emails',
				],
				'workflows' => [
					[ 'key' => 'order_confirmation', 'id' => 21, 'status' => 'active', 'error' => null ],
					[ 'key' => 'review_request', 'id' => 22, 'status' => 'active', 'error' => null ],
					// Left as a draft, it can still be switched on later.
					[ 'key' => 'new_order_alert', 'id' => 23, 'status' => 'draft', 'error' => 'It needs a connection.' ],
				],
			],
			$this->recipe( StoreengineEmailsGroupSeeder::SLUG )
		);

		$this->assertSame(
			[
				3  => 'order_note.customer',
				21 => 'order_confirmation.customer',
				23 => 'order_confirmation.admin',
			],
			get_option( StoreengineEmailHandover::OPTION )
		);
	}

	public function test_other_group_recipes_are_not_noted(): void {
		StoreengineEmailHandover::record(
			[ 'workflows' => [ [ 'key' => 'order_confirmation', 'id' => 21 ] ] ],
			$this->recipe( 'woocommerce-customer-lifecycle' )
		);

		$this->assertFalse( get_option( StoreengineEmailHandover::OPTION ) );
	}
}
