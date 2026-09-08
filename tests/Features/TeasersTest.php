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
	public function an_enabled_but_unused_module_is_still_advertised(): void {
		// custom_apps ships enabled, so an off-switch gate would never fire; what
		// makes it worth mentioning is that nobody has built one.
		$this->assertTrue( Settings::feature_enabled( 'custom_apps' ) );
		$this->assertNotNull( Teasers::for_screen( 'connections' ) );
	}

	/**
	 * @test
	 */
	public function using_the_module_retires_its_teaser(): void {
		$this->assertNotNull( Teasers::for_screen( 'connections' ) );

		$this->addCustomApp();

		$this->assertNull(
			Teasers::for_screen( 'connections' ),
			'Once a custom app exists there is nothing left to suggest.'
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

		// Turning MCP on, and giving the other two something to show for
		// themselves, should leave nothing to advertise.
		Settings::save( [ 'features' => [ 'mcp_server' => true ] ] );
		$this->addCustomApp();

		$remaining = array_keys( Teasers::all_visible() );

		$this->assertNotContains( 'workflows', $remaining );
		$this->assertNotContains( 'connections', $remaining );
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
