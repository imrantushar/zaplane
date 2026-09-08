<?php

namespace Zaplane\Tests\Features;

use Zaplane\CustomApps\ManifestStore;
use Zaplane\Features\Teasers;
use Zaplane\Settings;
use Zaplane\Tests\TestCase;

class TeasersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		ManifestStore::flush_cache();
	}

	private function addCustomApp(): void {
		ManifestStore::save(
			[
				'slug'     => 'acme',
				'name'     => 'Acme',
				'actions'  => [],
				'triggers' => [],
			]
		);
	}

	/** @return array<int,string> */
	private function listed( ?array $spotlight ): array {
		return null === $spotlight ? [] : array_column( $spotlight['modules'], 'key' );
	}

	/* ------------------------------ spotlight ------------------------------ */

	/**
	 * @test
	 */
	public function it_lists_every_module_that_is_off_in_one_card(): void {
		$spotlight = Teasers::spotlight( 'workflows' );

		$this->assertNotNull( $spotlight );
		$this->assertSame( Teasers::SPOTLIGHT_KEY, $spotlight['key'] );

		// Every module ships off, so all of them should be on offer.
		$listed = $this->listed( $spotlight );
		sort( $listed );

		$expected = array_keys( Settings::modules() );
		sort( $expected );

		$this->assertSame( $expected, $listed );
	}

	/**
	 * @test
	 */
	public function each_listed_module_carries_what_the_card_renders(): void {
		foreach ( Teasers::spotlight( 'workflows' )['modules'] as $module ) {
			$this->assertNotEmpty( $module['key'] );
			$this->assertNotEmpty( $module['title'] );
			$this->assertNotEmpty( $module['description'] );
			$this->assertIsBool( $module['relevant_here'] );
		}
	}

	/**
	 * @test
	 */
	public function the_module_the_screen_is_about_is_listed_first_and_marked(): void {
		$workflows = Teasers::spotlight( 'workflows' );
		$this->assertSame( 'mcp_server', $workflows['modules'][0]['key'] );
		$this->assertTrue( $workflows['modules'][0]['relevant_here'] );

		$connections = Teasers::spotlight( 'connections' );
		$this->assertSame( 'custom_apps', $connections['modules'][0]['key'] );

		// Exactly one row may claim relevance.
		$this->assertCount( 1, array_filter( array_column( $workflows['modules'], 'relevant_here' ) ) );
	}

	/**
	 * @test
	 */
	public function an_unknown_screen_still_lists_everything_just_unordered(): void {
		$spotlight = Teasers::spotlight( 'logs' );

		$this->assertNotNull( $spotlight );
		$this->assertCount( count( Settings::modules() ), $spotlight['modules'] );
		$this->assertSame( [], array_filter( array_column( $spotlight['modules'], 'relevant_here' ) ) );
	}

	/**
	 * @test
	 */
	public function a_module_that_is_on_drops_off_the_card(): void {
		Settings::save( [ 'features' => [ 'knowledge' => true ] ] );

		$listed = $this->listed( Teasers::spotlight( 'workflows' ) );

		$this->assertNotContains( 'knowledge', $listed );
		$this->assertContains( 'mcp_server', $listed );
	}

	/**
	 * @test
	 */
	public function the_card_disappears_once_every_module_is_on(): void {
		$features = [];
		foreach ( array_keys( Settings::modules() ) as $key ) {
			$features[ $key ] = true;
		}
		Settings::save( [ 'features' => $features ] );

		$this->assertNull( Teasers::spotlight( 'workflows' ) );
	}

	/* ------------------------------ dismissal ------------------------------ */

	/**
	 * @test
	 */
	public function dismissing_hides_the_card(): void {
		$this->assertNotNull( Teasers::spotlight( 'workflows' ) );

		$this->assertTrue( Teasers::dismiss( Teasers::SPOTLIGHT_KEY ) );

		$this->assertNull( Teasers::spotlight( 'workflows' ) );
		$this->assertNull( Teasers::spotlight( 'dashboard' ) );
	}

	/**
	 * @test
	 */
	public function a_module_added_after_a_dismissal_brings_the_card_back(): void {
		Teasers::dismiss( Teasers::SPOTLIGHT_KEY );
		$this->assertNull( Teasers::spotlight( 'workflows' ) );

		// A later release adds a module the dismissal could not have covered.
		$this->registerLaterModule();

		$spotlight = Teasers::spotlight( 'workflows' );

		$this->assertNotNull( $spotlight, 'A dismissal should not bury modules that did not exist yet.' );
		$this->assertSame( [ 'later_module' ], $this->listed( $spotlight ) );
	}

	/**
	 * @test
	 */
	public function only_the_spotlight_key_can_be_dismissed(): void {
		$this->assertFalse( Teasers::dismiss( 'something_else' ) );
		$this->assertFalse( Teasers::dismiss( '' ) );
	}

	/* -------------------------------- builder ------------------------------ */

	/**
	 * @test
	 */
	public function a_node_from_a_switched_off_module_is_flagged_in_the_builder(): void {
		$teaser = Teasers::for_app( 'knowledge' );

		$this->assertNotNull( $teaser );
		$this->assertSame( 'knowledge', $teaser['module'] );
		$this->assertSame( 'knowledge', $teaser['activates'], 'It should offer to switch the module on inline.' );
		$this->assertStringContainsString( 'Business Knowledge', $teaser['title'] );
	}

	/**
	 * @test
	 */
	public function the_builder_says_nothing_once_the_module_is_on(): void {
		Settings::save( [ 'features' => [ 'knowledge' => true ] ] );

		$this->assertNull( Teasers::for_app( 'knowledge' ) );
	}

	/**
	 * @test
	 */
	public function a_node_belonging_to_no_module_is_never_flagged(): void {
		$this->assertNull( Teasers::for_app( 'woocommerce' ) );
		$this->assertNull( Teasers::for_app( 'slack' ) );
		$this->assertNull( Teasers::for_app( '' ) );
	}

	/**
	 * @test
	 */
	public function a_user_defined_app_is_attributed_to_the_custom_apps_module(): void {
		$this->addCustomApp();

		$teaser = Teasers::for_app( 'acme' );

		$this->assertNotNull( $teaser );
		$this->assertSame( 'custom_apps', $teaser['activates'] );
	}

	/**
	 * @test
	 */
	public function the_builder_prompt_is_not_dismissible(): void {
		// It describes the node in front of you, so there is nothing to file away.
		$this->assertArrayNotHasKey( 'dismissible', Teasers::for_app( 'knowledge' ) );
		$this->assertFalse( Teasers::dismiss( 'app_module_off_knowledge' ) );
	}

	/* ----------------------------------------------------------------------- */

	/** Pretend a later release shipped an extra module. */
	private function registerLaterModule(): void {
		\add_filter(
			'zaplane/modules',
			static function ( $modules ) {
				$modules['later_module'] = [
					'key'         => 'later_module',
					'title'       => 'Later Module',
					'description' => 'Shipped after the user dismissed the card.',
					'default'     => false,
					'menu'        => '',
					'panel'       => '',
					'apps'        => [],
					'since'       => '9.9.9',
				];
				return $modules;
			}
		);
	}
}
