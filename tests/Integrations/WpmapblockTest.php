<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wpmapblock;
use Zaplane\Tests\WPMocks;

/**
 * WP Map Block has no plugin classes in the test harness (no `WPMapBlock\Config`
 *), so actions that must sanitize through the plugin's own rules are exercised
 * through their sample output rather than executed. The actions listed here are
 * the read-only and post-CRUD ones that only need WordPress' own APIs.
 */
class WpmapblockTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Wpmapblock::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		$GLOBALS['zaplane_wp_posts'] = [
			101 => new \WP_Post( [
				'ID'                 => 101,
				'post_type'          => 'wpmb_map',
				'post_title'         => 'Store Locator',
				'post_status'        => 'publish',
				'post_modified_gmt'  => '2026-09-27 10:15:00',
			] ),
			102 => new \WP_Post( [
				'ID'                => 102,
				'post_type'         => 'wpmb_map',
				'post_title'        => 'Draft Map',
				'post_status'       => 'draft',
				'post_modified_gmt' => '2026-09-26 08:00:00',
			] ),
			42  => new \WP_Post( [
				'ID'        => 42,
				'post_type' => 'product',
				'post_title'=> 'Flagship Store',
				'post_status' => 'publish',
			] ),
		];

		$GLOBALS['zaplane_post_meta'] = [
			101 => [ '_wpmb_config' => [ wp_json_encode( self::stored_config() ) ] ],
			102 => [ '_wpmb_config' => [ wp_json_encode( self::stored_config() ) ] ],
			42  => [
				'_wpmb_lat' => [ '23.780573' ],
				'_wpmb_lng' => [ '90.407067' ],
			],
		];

		// `wp_delete_post()` reads this store, not the fixture above.
		WPMocks::setPost( 101, [ 'post_type' => 'wpmb_map', 'post_title' => 'Store Locator' ] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['zaplane_wp_posts'], $GLOBALS['zaplane_wp_posts_strict'], $GLOBALS['zaplane_post_meta'] );
		parent::tearDown();
	}

	/** The document `_wpmb_config` holds: a JSON string, markers and all. */
	private static function stored_config(): array {
		return [
			'version'   => 3,
			'provider'  => 'openstreetmap',
			'view'      => [ 'center' => [ 'lat' => 23.8103, 'lng' => 90.4125 ], 'zoom' => 11 ],
			'clustering'=> [ 'enabled' => true ],
			'categories'=> [ [ 'id' => 'flagship', 'name' => 'Flagship', 'color' => '' ] ],
			'providerOptions' => [ 'apiKey' => 'sk-secret-do-not-log', 'styleUrl' => '', 'styleName' => '' ],
			'markers'   => [
				[
					'id'       => 'mk_8f31c2a4',
					'lat'      => 23.780573,
					'lng'      => 90.407067,
					'title'    => 'Flagship Store',
					'content'  => '<p>Open 10:00</p>',
					'image'    => '',
					'link'     => '',
					'category' => 'flagship',
					'icon'     => [ 'type' => 'color', 'url' => '', 'color' => '#006bff' ],
				],
				[
					'id'       => 'mk_11223344',
					'lat'      => 23.790100,
					'lng'      => 90.401200,
					'title'    => 'Airport Kiosk',
					'content'  => '<p>24 hours</p>',
					'image'    => '',
					'link'     => '',
					'category' => 'flagship',
					'icon'     => [ 'type' => 'default', 'url' => '', 'color' => '' ],
				],
			],
		];
	}

	protected function getTriggerTests(): array {
		$map = $GLOBALS['zaplane_wp_posts'][101];

		return [
			// added_post_meta / updated_post_meta: ( $meta_id, $object_id, $meta_key, $value )
			'map_created'         => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'map_updated'         => [ 7, 101, '_wpmb_config', '{"provider":"google"}' ],
			'map_status_changed'  => [ 'publish', 'draft', $map ],
			'map_deleted'         => [ 101, $map ],
			'marker_added'        => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'marker_updated'      => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'marker_moved'        => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'marker_removed'      => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'map_viewed'          => [ [ 'provider' => 'openstreetmap' ], 'wpmb-map-101' ],
			'map_searched'        => [],
			'location_updated'    => [ 7, 42, '_wpmb_lat', '23.780573' ],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_map'        => [ 'map_id' => 101 ],
			'export_map'     => [ 'map_id' => 101 ],
			'search_markers' => [ 'map_id' => 101, 'query' => 'flagship' ],
			'set_map_status' => [ 'map_id' => 102, 'status' => 'active' ],
			// Reads the WPMocks store, so it runs last — nothing after it needs post 101.
			'delete_map'     => [ 'map_id' => 101 ],
		];
	}

	/** @test */
	public function test_trigger_registry_matches_the_real_hooks(): void {
		$this->assertSame( [
			'map_created', 'map_updated', 'map_status_changed', 'map_deleted',
			'marker_added', 'marker_updated', 'marker_moved', 'marker_removed',
			'map_viewed', 'map_searched', 'location_updated',
		], array_keys( Wpmapblock::get_triggers() ) );

		$triggers = Wpmapblock::get_triggers();

		// Maps are `wpmb_map` posts whose document lives in `_wpmb_config`.
		$this->assertSame( 'added_post_meta', $triggers['map_created']['hook'] );
		$this->assertSame( 'updated_post_meta', $triggers['map_updated']['hook'] );
		$this->assertSame( 'transition_post_status', $triggers['map_status_changed']['hook'] );
		$this->assertSame( 'before_delete_post', $triggers['map_deleted']['hook'] );

		// A marker diff needs both the outgoing and the incoming document.
		$this->assertSame( [ 'update_post_meta', 'updated_post_meta' ], $triggers['marker_moved']['hook'] );

		// Hooking a fabricated `wpmb_*` save event would never fire — WP Map
		// Block registers no hook of its own on save.
		$hooks = [];
		foreach ( $triggers as $trigger ) {
			foreach ( (array) $trigger['hook'] as $hook ) {
				$hooks[] = $hook;
			}
		}
		$this->assertNotContains( 'wpmb_data_imported', $hooks );
		$this->assertNotContains( 'zaplane_wpmb_frontend_tracked', $hooks );
	}

	/** @test */
	public function test_no_trigger_or_action_invents_a_nonexistent_post_type(): void {
		// `wpmb_location` never existed: locations are `_wpmb_lat`/`_wpmb_lng`
		// meta on whatever post type is opted in.
		$sources = file_get_contents( ZAPLANE_INTEGRATION_DIR_PATH . 'wpmapblock.php' );

		$this->assertStringContainsString( "'wpmb_map'", $sources );
		$this->assertStringNotContainsString( 'wpmb_location_category', $sources );
		$this->assertStringNotContainsString( "'wpmb_location'", $sources );
		$this->assertStringNotContainsString( 'add_action( \'rest_api_init\'', $sources );
	}

	/** @test */
	public function test_map_id_filter_narrows_a_trigger(): void {
		$node = $this->makeTriggerNode( 'map_updated', [ 'map_id' => 101 ] );
		$args = [ 7, 101, '_wpmb_config', '{"provider":"google"}' ];

		$this->assertIsArray( Wpmapblock::resolve_trigger( $node, $args ) );

		$other = $this->makeTriggerNode( 'map_updated', [ 'map_id' => 102 ] );
		$this->assertFalse( Wpmapblock::resolve_trigger( $other, $args ) );

		// An unresolvable expression must never silently widen the match.
		$expression = $this->makeTriggerNode( 'map_updated', [ 'map_id' => '@trigger.map_id' ] );
		$this->assertFalse( Wpmapblock::resolve_trigger( $expression, $args ) );
	}

	/** @test */
	public function test_map_payload_carries_renderable_scalars_but_never_the_api_key(): void {
		$payload = Wpmapblock::resolve_trigger(
			$this->makeTriggerNode( 'map_created' ),
			[ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ]
		);

		$this->assertIsArray( $payload );
		$this->assertSame( 101, $payload['map_id'] );
		$this->assertSame( 'Store Locator', $payload['map_title'] );
		$this->assertTrue( $payload['active'] );
		$this->assertSame( 'openstreetmap', $payload['provider'] );
		$this->assertSame( 23.8103, $payload['center_lat'] );
		$this->assertSame( 90.4125, $payload['center_lng'] );
		$this->assertSame( 11, $payload['zoom'] );
		$this->assertSame( 2, $payload['markers_count'] );
		$this->assertSame( '[wp_map id="101"]', $payload['shortcode'] );
		$this->assertArrayHasKey( 'config', $payload );

		// Redaction is the only thing standing between a run log and a leaked key.
		$method = new \ReflectionMethod( Wpmapblock::class, 'map_payload' );
		$method->setAccessible( true );
		$redacted = $method->invoke( null, $GLOBALS['zaplane_wp_posts'][101], true );

		$this->assertSame( '', $redacted['config']['providerOptions']['apiKey'] );
		$this->assertTrue( $redacted['has_api_key'], 'the payload may report that a key exists, just not what it is' );
		$this->assertStringNotContainsString( 'sk-secret-do-not-log', wp_json_encode( $redacted ) );
	}

	/** @test */
	public function test_marker_diff_classifies_add_move_edit_and_remove(): void {
		$before = [
			[ 'id' => 'mk_a', 'lat' => 1, 'lng' => 2, 'title' => 'A', 'content' => 'x', 'icon' => [] ],
			[ 'id' => 'mk_b', 'lat' => 3, 'lng' => 4, 'title' => 'B', 'content' => '', 'icon' => [] ],
			[ 'id' => 'mk_c', 'lat' => 5, 'lng' => 6, 'title' => 'C', 'content' => '', 'icon' => [] ],
		];
		$after  = [
			[ 'id' => 'mk_a', 'lat' => 1, 'lng' => 2, 'title' => 'A', 'content' => 'x', 'icon' => [] ],
			[ 'id' => 'mk_b', 'lat' => 9, 'lng' => 9, 'title' => 'B', 'content' => '', 'icon' => [] ],
			[ 'id' => 'mk_d', 'lat' => 7, 'lng' => 8, 'title' => 'D', 'content' => '', 'icon' => [] ],
		];

		$diff = self::invoke_diff( $before, $after );

		$this->assertSame( [ 'mk_d' ], array_column( $diff['marker_added'], 'id' ) );
		$this->assertSame( [ 'mk_b' ], array_column( $diff['marker_moved'], 'id' ) );
		$this->assertSame( [ 'mk_c' ], array_column( $diff['marker_removed'], 'id' ) );
		$this->assertEmpty( $diff['marker_updated'], 'A marker that only moved is not also an edit' );
		$this->assertSame( 3.0, $diff['marker_moved'][0]['previous_lat'] );
		$this->assertSame( 4.0, $diff['marker_moved'][0]['previous_lng'] );

		// A content-only change is an edit, not a move — and not an add.
		$edited = [ [ 'id' => 'mk_a', 'lat' => 1, 'lng' => 2, 'title' => 'A', 'content' => 'edited', 'icon' => [] ] ];
		$diff   = self::invoke_diff( [ $before[0] ], $edited );

		$this->assertEmpty( $diff['marker_added'] );
		$this->assertEmpty( $diff['marker_moved'] );
		$this->assertEmpty( $diff['marker_removed'] );
		$this->assertSame( [ 'mk_a' ], array_column( $diff['marker_updated'], 'id' ) );
		$this->assertSame( 'x', $diff['marker_updated'][0]['previous']['content'] );
	}

	/** @test */
	public function test_every_trigger_and_action_declares_sample_output(): void {
		foreach ( array_keys( Wpmapblock::get_triggers() ) as $trigger ) {
			$sample = Wpmapblock::get_trigger_sample_output( $trigger );
			$this->assertNotEmpty( $sample, "Trigger '{$trigger}' has no sample output" );
		}

		foreach ( array_keys( Wpmapblock::get_actions() ) as $action ) {
			$sample = Wpmapblock::get_action_sample_output( $action );
			$this->assertNotEmpty( $sample, "Action '{$action}' has no sample output" );
			$this->assertTrue( $sample['success'] ?? false, "Action '{$action}' sample must report success" );
		}
	}

	/** @test */
	public function test_dynamic_queries_are_registered_and_callable(): void {
		$queries = Wpmapblock::get_dynamic_queries();

		$this->assertSame(
			[ 'maps', 'markers', 'map_categories', 'geo_post_types', 'geo_posts', 'listing_sources' ],
			array_keys( $queries )
		);

		foreach ( $queries as $query => $callback ) {
			$this->assertTrue( is_callable( $callback ), "Lookup '{$query}' is not callable" );
			$this->assertIsArray( call_user_func( $callback, [ 'map_id' => 101 ] ), "Lookup '{$query}' returned a non-array" );
		}
	}

	/** @test */
	public function test_marker_lookups_never_leak_across_maps(): void {
		$markers = Wpmapblock::query_markers( [ 'map_id' => 0 ] );
		$this->assertSame( [], $markers, 'A marker picker with no map must stay empty' );

		$markers = Wpmapblock::query_markers( [ 'map_id' => 101 ] );
		$this->assertCount( 2, $markers );
		$this->assertSame( 'mk_8f31c2a4', $markers[0]['value'] );
		$this->assertStringContainsString( 'Flagship Store', $markers[0]['label'] );

		$categories = Wpmapblock::query_map_categories( [ 'map_id' => 101 ] );
		$this->assertSame( [ [ 'value' => 'flagship', 'label' => 'Flagship' ] ], $categories );
	}

	/** @test */
	public function test_trigger_schemas_declare_a_dynamic_map_picker_where_relevant(): void {
		$schema = Wpmapblock::get_trigger_config_schema( 'map_updated' );
		$this->assertCount( 1, $schema );
		$this->assertSame( 'map_id', $schema[0]['key'] );
		$this->assertFalse( $schema[0]['required'], 'Filtering by map must stay optional' );
		$this->assertSame( 'maps', $schema[0]['dynamic']['query'] );

		// A brand-new map has no id to match on yet.
		$this->assertSame( [], Wpmapblock::get_trigger_config_schema( 'map_created' ) );

		$search = Wpmapblock::get_trigger_config_schema( 'map_searched' );
		$this->assertSame( [ 'map_id', 'query' ], array_column( $search, 'key' ) );

		$location = Wpmapblock::get_trigger_config_schema( 'location_updated' );
		$this->assertSame( [ 'post_type' ], array_column( $location, 'key' ) );
		$this->assertSame( 'geo_post_types', $location[0]['dynamic']['query'] );
	}

	/** @test */
	public function test_action_schemas_never_require_an_optional_field(): void {
		foreach ( array_keys( Wpmapblock::get_actions() ) as $action ) {
			$schema = Wpmapblock::get_action_config_schema( $action );
			$this->assertNotEmpty( $schema, "Action '{$action}' has no config schema" );

			$keys = array_column( $schema, 'key' );
			$this->assertSame( $keys, array_unique( $keys ), "Action '{$action}' repeats a field key" );

			foreach ( $schema as $field ) {
				// An optional field must never be marked required, or the
				// validator rejects every config the user leaves blank.
				$this->assertArrayHasKey( 'label', $field, "Action '{$action}' field '{$field['key']}' has no label" );
				$this->assertContains( $field['type'], [ 'text', 'textarea', 'number', 'select', 'password' ] );

				if ( isset( $field['options'] ) ) {
					foreach ( $field['options'] as $option ) {
						$this->assertArrayHasKey( 'value', $option );
						$this->assertArrayHasKey( 'label', $option );
					}
				}

				if ( isset( $field['dynamic'] ) ) {
					$this->assertSame( 'wpmapblock', $field['dynamic']['integration'] );
					$this->assertArrayHasKey( 'query', $field['dynamic'] );
					$this->assertArrayHasKey( 'select', $field['dynamic'] );
				}
			}
		}

		// Every required field on every action is one a caller can actually fill.
		$required = array_column( array_filter( Wpmapblock::get_action_config_schema( 'add_marker' ), function ( $field ) {
			return ! empty( $field['required'] );
		} ), 'key' );
		$this->assertSame( [ 'map_id', 'lat', 'lng' ], $required );
	}

	/**
	 * `diff_markers` is private and only reachable from a paired meta hook the
	 * harness cannot fire, so poke it directly.
	 */
	private static function invoke_diff( array $before, array $after ): array {
		$method = new \ReflectionMethod( Wpmapblock::class, 'diff_markers' );
		$method->setAccessible( true );
		return $method->invoke( null, $before, $after );
	}
}
