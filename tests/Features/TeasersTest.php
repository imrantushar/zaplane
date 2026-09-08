<?php

namespace Zaplane\Tests\Features;

use Zaplane\CustomApps\ManifestStore;
use Zaplane\Features\Teasers;
use Zaplane\Settings;
use Zaplane\Tests\TestCase;

class TeasersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// The store memoizes its envelope for the request; drop it so each test
		// starts with no custom apps.
		ManifestStore::flush_cache();
	}

	/** Register a minimal custom app through the store's own API. */
	private function addCustomApp(): void {
		ManifestStore::save(
			[
				'slug'    => 'acme',
				'name'    => 'Acme',
				'actions' => [],
				'triggers' => [],
			]
		);
	}

	/**
	 * @test
	 */
	public function every_teaser_points_at_a_real_module_and_a_real_screen(): void {
		$modules = Settings::modules();

		$this->assertNotEmpty( Teasers::registry() );

		foreach ( Teasers::registry() as $key => $teaser ) {
			$this->assertSame( $key, $teaser['key'] );
			$this->assertNotEmpty( $teaser['screen'], $key . ' has no screen.' );
			$this->assertNotEmpty( $teaser['title'], $key . ' has no title.' );
			$this->assertNotEmpty( $teaser['body'], $key . ' has no body.' );
			$this->assertNotEmpty( $teaser['cta_label'], $key . ' has no call to action.' );

			$module = (string) ( $teaser['module'] ?? '' );
			$this->assertArrayHasKey(
				$module,
				$modules,
				$key . ' names module "' . $module . '", which is not registered.'
			);
			$this->assertIsCallable( $teaser['relevant'], $key . ' has no relevance predicate.' );
		}
	}

	/**
	 * @test
	 */
	public function at_most_one_teaser_is_registered_per_screen(): void {
		$screens = array_column( Teasers::registry(), 'screen' );

		$this->assertSame(
			count( $screens ),
			count( array_unique( $screens ) ),
			'Two teasers share a screen; only one would ever show.'
		);
	}

	/**
	 * @test
	 */
	public function a_teaser_shows_while_its_module_is_off(): void {
		$teaser = Teasers::for_screen( 'workflows' );

		$this->assertNotNull( $teaser );
		$this->assertSame( 'mcp_on_workflows', $teaser['key'] );
		$this->assertSame( 'mcp_server', $teaser['module'] );
		// Modules, not the AI-access panel: that panel is hidden while the
		// module is off, which is exactly what this teaser is asking for.
		$this->assertSame( 'modules', $teaser['cta_panel'] );
	}

	/**
	 * @test
	 */
	public function enabling_the_module_retires_its_teaser(): void {
		$this->assertNotNull( Teasers::for_screen( 'workflows' ) );

		Settings::save( [ 'features' => [ 'mcp_server' => true ] ] );

		$this->assertNull(
			Teasers::for_screen( 'workflows' ),
			'Accepting the suggestion should be enough to stop showing it.'
		);
	}

	/**
	 * @test
	 */
	public function dismissing_a_teaser_hides_it_and_leaves_the_others_alone(): void {
		$this->assertNotNull( Teasers::for_screen( 'workflows' ) );
		$this->assertNotNull( Teasers::for_screen( 'connections' ) );

		$this->assertTrue( Teasers::dismiss( 'mcp_on_workflows' ) );

		$this->assertNull( Teasers::for_screen( 'workflows' ) );
		$this->assertNotNull( Teasers::for_screen( 'connections' ) );
	}

	/**
	 * @test
	 */
	public function every_module_being_opt_in_means_every_teaser_starts_visible(): void {
		$this->assertNotNull( Teasers::for_screen( 'workflows' ) );
		$this->assertNotNull( Teasers::for_screen( 'connections' ) );
		$this->assertNotNull( Teasers::for_screen( 'dashboard' ) );
	}

	/**
	 * @test
	 */
	public function switching_the_module_on_is_all_it_takes_to_retire_a_teaser(): void {
		$this->assertNotNull( Teasers::for_screen( 'connections' ) );

		Settings::save( [ 'features' => [ 'custom_apps' => true ] ] );

		$this->assertNull(
			Teasers::for_screen( 'connections' ),
			'A module that is on has nothing left to advertise.'
		);
	}

	/**
	 * @test
	 */
	public function a_predicate_that_throws_hides_its_teaser_rather_than_the_screen(): void {
		\add_filter(
			'zaplane/feature_teasers',
			function ( $teasers ) {
				$teasers['boom'] = [
					'key'       => 'boom',
					'screen'    => 'logs',
					'module'    => 'knowledge',
					'title'     => 'Boom',
					'body'      => 'Body',
					'cta_label' => 'Go',
					'cta_panel' => 'modules',
					'relevant'  => static function (): bool {
						throw new \RuntimeException( 'table missing' );
					},
				];
				return $teasers;
			}
		);

		$this->assertNull( Teasers::for_screen( 'logs' ) );
	}

	/**
	 * @test
	 */
	public function dismissing_twice_is_harmless_and_an_unknown_key_is_refused(): void {
		$this->assertTrue( Teasers::dismiss( 'mcp_on_workflows' ) );
		$this->assertTrue( Teasers::dismiss( 'mcp_on_workflows' ) );
		$this->assertFalse( Teasers::dismiss( 'not_a_teaser' ) );
	}

	/**
	 * @test
	 */
	public function a_screen_with_no_teaser_returns_nothing(): void {
		$this->assertNull( Teasers::for_screen( 'logs' ) );
		$this->assertNull( Teasers::for_screen( '' ) );
	}

	/**
	 * @test
	 */
	public function all_visible_is_keyed_by_screen_and_shrinks_as_modules_are_enabled(): void {
		$visible = Teasers::all_visible();

		$this->assertArrayHasKey( 'workflows', $visible );
		$this->assertArrayHasKey( 'connections', $visible );
		$this->assertSame( 'mcp_on_workflows', $visible['workflows']['key'] );

		Settings::save(
			[
				'features' => [
					'mcp_server'  => true,
					'custom_apps' => true,
					'knowledge'   => true,
				],
			]
		);

		$this->assertSame( [], Teasers::all_visible(), 'With every module on, nothing is left to advertise.' );
	}

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
	public function the_registry_can_be_extended_by_a_filter(): void {
		\add_filter(
			'zaplane/feature_teasers',
			function ( $teasers ) {
				$teasers['extra'] = [
					'key'       => 'extra',
					'screen'    => 'recipes',
					'module'    => 'knowledge',
					'title'     => 'Extra',
					'body'      => 'Body',
					'cta_label' => 'Go',
					'cta_panel' => 'modules',
					'relevant'  => static fn(): bool => true,
				];
				return $teasers;
			}
		);

		$this->assertSame( 'extra', Teasers::for_screen( 'recipes' )['key'] );
	}
}
