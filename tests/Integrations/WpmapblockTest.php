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

		// Every trigger that diffs or de-duplicates keeps static state across a
		// single request; a test process is one long request unless we empty it.
		self::reset_integration_state();

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

		// WPMocks' `get_posts()` is a no-op unless a test overrides it, but the
		// source index, the map lookups and the location searches all go
		// through it. Filter on `post_type` the way WordPress does, so the
		// integration cannot lean on a list it would never have been handed.
		$GLOBALS['zaplane_get_posts'] = function ( array $args ): array {
			$wanted = array_map( 'strval', (array) ( $args['post_type'] ?? [] ) );
			$posts  = [];

			foreach ( $GLOBALS['zaplane_wp_posts'] ?? [] as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				if ( ! empty( $wanted ) && ! in_array( 'any', $wanted, true ) && ! in_array( $post->post_type, $wanted, true ) ) {
					continue;
				}
				$posts[] = $post;
			}

			return $posts;
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['zaplane_wp_posts'], $GLOBALS['zaplane_wp_posts_strict'], $GLOBALS['zaplane_post_meta'], $GLOBALS['zaplane_get_posts'] );
		self::reset_integration_state();
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
			// Post 42 (`product`) is only a store while some map reads it
			// through the store locator — WP Map Block has no store entity.
			'storeLocator' => [ 'enabled' => true, 'placeholder' => 'Search', 'radius' => 25, 'unit' => 'km' ],
			'dataSources'  => [
				[
					'id'       => 'ds_4b9c1f02',
					'type'     => 'cpt',
					'label'    => 'Stores',
					'url'      => '',
					'source'   => '',
					'postType' => 'product',
					'latMeta'  => '_wpmb_lat',
					'lngMeta'  => '_wpmb_lng',
					'limit'    => 100,
				],
			],
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
		$map  = $GLOBALS['zaplane_wp_posts'][101];
		$post = $GLOBALS['zaplane_wp_posts'][42];

		return [
			// added_post_meta / updated_post_meta: ( $meta_id, $object_id, $meta_key, $value )
			'map_created'            => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'map_updated'            => [ 7, 101, '_wpmb_config', '{"provider":"google"}' ],
			'map_status_changed'     => [ 'publish', 'draft', $map ],
			'map_deleted'            => [ 101, $map ],
			'marker_added'           => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'marker_updated'         => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'marker_moved'           => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'marker_removed'         => [ 7, 101, '_wpmb_config', '{"provider":"openstreetmap"}' ],
			'map_viewed'             => [ [ 'provider' => 'openstreetmap' ], 'wpmb-map-101' ],
			'map_searched'           => [],
			// The REST-backed triggers need a `WP_REST_Request`, which the
			// harness has no way to hand them; `false` is the honest answer.
			'map_refreshed'          => [],
			'map_data_imported'      => [],
			'rest_map_updated'       => [],
			'location_created'       => [ 7, 42, '_wpmb_lat', '23.780573' ],
			'location_updated'       => [ 7, 42, '_wpmb_lat', '23.780573' ],
			'location_deleted'       => [ 42, $post ],
			'location_published'     => [ 'publish', 'draft', $post ],
			'location_status_changed'=> [ 'publish', 'draft', $post ],
			'store_created'          => [ 7, 42, '_wpmb_lat', '23.780573' ],
			'store_updated'          => [ 7, 42, '_wpmb_lat', '23.780573' ],
			'store_deleted'          => [ 42, $post ],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_map'          => [ 'map_id' => 101 ],
			'export_map'       => [ 'map_id' => 101 ],
			'search_markers'   => [ 'map_id' => 101, 'query' => 'flagship' ],
			'set_map_status'   => [ 'map_id' => 102, 'status' => 'active' ],
			'refresh_map_data' => [ 'map_id' => 101 ],
			// `WPMapBlock\Config` is not loaded in the harness, so only the
			// actions that can finish without saving a document are listed.
			'get_rest_map_data' => [ 'map_id' => 101 ],
			'sync_dynamic_data' => [ 'map_id' => 101 ],
			'get_location'      => [ 'post_id' => 42 ],
			'search_locations'  => [ 'post_type' => 'product', 'query' => 'Flagship' ],
			'filter_locations'  => [ 'post_type' => 'product', 'near_lat' => '23.8103', 'near_lng' => '90.4125', 'radius' => 50 ],
			// Reads the WPMocks store, so it runs last — nothing after it needs post 101.
			'delete_map'        => [ 'map_id' => 101 ],
		];
	}

	/** @test */
	public function test_trigger_registry_matches_the_real_hooks(): void {
		$this->assertSame( [
			'map_created', 'map_updated', 'map_status_changed', 'map_deleted',
			'marker_added', 'marker_updated', 'marker_moved', 'marker_removed',
			'map_viewed', 'map_searched', 'map_refreshed', 'map_data_imported', 'rest_map_updated',
			'location_created', 'location_updated', 'location_deleted',
			'location_published', 'location_status_changed',
			'store_created', 'store_updated', 'store_deleted',
		], array_keys( Wpmapblock::get_triggers() ) );

		$triggers = Wpmapblock::get_triggers();

		// Maps are `wpmb_map` posts whose document lives in `_wpmb_config`.
		$this->assertSame( 'added_post_meta', $triggers['map_created']['hook'] );
		$this->assertSame( 'updated_post_meta', $triggers['map_updated']['hook'] );
		$this->assertSame( 'transition_post_status', $triggers['map_status_changed']['hook'] );
		$this->assertSame( 'before_delete_post', $triggers['map_deleted']['hook'] );

		// A marker diff needs both the outgoing and the incoming document.
		$this->assertSame( [ 'update_post_meta', 'updated_post_meta' ], $triggers['marker_moved']['hook'] );

		// So does a location: the pre-write hook is where the outgoing pair is
		// read, and it is the only way to tell a real edit from WordPress
		// running `updated_post_meta` for a float it wrote straight back.
		$this->assertSame( [ 'update_post_meta', 'updated_post_meta' ], $triggers['location_updated']['hook'] );
		$this->assertSame( [ 'update_post_meta', 'updated_post_meta' ], $triggers['store_updated']['hook'] );

		// A location born live never crosses a status transition, so
		// `location_published` has to watch the coordinates too.
		$this->assertSame( [ 'transition_post_status', 'added_post_meta' ], $triggers['location_published']['hook'] );

		// WP Map Block's refresh endpoint reports after its permission check,
		// and the import needs the marker count from before it.
		$this->assertSame( 'rest_request_after_callbacks', $triggers['map_refreshed']['hook'] );
		$this->assertSame( [ 'rest_request_before_callbacks', 'rest_request_after_callbacks' ], $triggers['map_data_imported']['hook'] );
		$this->assertSame( 'rest_request_after_callbacks', $triggers['rest_map_updated']['hook'] );

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
		$this->assertNotContains( 'wpmb/pro/dynamic_data/config', $hooks, 'that filter fires on every render — it is the view event, not a refresh' );
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
	 * `trigger_location_updated` and friends are only reachable from a paired
	 * meta hook the harness cannot fire, and `current_filter()` is hardwired to
	 * an empty string there — so the rules are driven directly.
	 */
	private static function call_rule( string $method, array $args ) {
		$method = new \ReflectionMethod( Wpmapblock::class, $method );
		$method->setAccessible( true );
		return $method->invokeArgs( null, $args );
	}

	private static function trigger_state( string $property ) {
		$property = new \ReflectionProperty( Wpmapblock::class, $property );
		$property->setAccessible( true );
		return $property->getValue( null );
	}

	private static function write_trigger_state( string $property, $value ): void {
		$property = new \ReflectionProperty( Wpmapblock::class, $property );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	/** Trigger state is per-request; a test process has to be told to start over. */
	private static function reset_integration_state(): void {
		foreach ( [ 'baseline', 'location_writes', 'location_prev', 'location_seen', 'import_baseline' ] as $name ) {
			self::write_trigger_state( $name, [] );
		}
		self::write_trigger_state( 'source_index', null );
	}

	/** @test */
	public function test_a_location_reports_once_with_both_coordinates_final(): void {
		$post_id  = 42;
		$lat_args = [ 7, $post_id, '_wpmb_lat', '23.810300' ];
		$filter   = [];

		// The pre-write hook reads the pair before anything is touched. It
		// never reports — without an outgoing pair there is no diff.
		$this->assertFalse( self::call_rule( 'trigger_location_updated', [ $filter, $lat_args, 'location', 'update_post_meta' ] ) );
		$this->assertSame(
			[ 'lat' => 23.780573, 'lng' => 90.407067 ],
			self::trigger_state( 'location_prev' )[ self::call_rule( 'location_state_key', [ $filter, 'location', $post_id ] ) ],
			'the pair the save started from is kept under this consumer\'s own key'
		);
		$this->assertArrayNotHasKey(
			self::call_rule( 'location_state_key', [ $filter, 'store', $post_id ] ),
			self::trigger_state( 'location_prev' )
		);

		// Our own actions publish the pair they are writing before either half
		// lands: `Location_Meta::save()` writes latitude first, so a
		// latitude-only edit would otherwise stay invisible until a longitude
		// hook that never comes.
		self::write_trigger_state( 'location_writes', [ $post_id => [ 'lat' => 23.8103, 'lng' => 90.407067 ] ] );

		$payload = self::call_rule( 'trigger_location_updated', [ $filter, $lat_args, 'location', 'updated_post_meta' ] );

		$this->assertIsArray( $payload );
		$this->assertSame( 23.8103, $payload['lat'], 'the payload carries the pair the write is heading for, not the half the database has reached' );
		$this->assertSame( 90.407067, $payload['lng'] );
		$this->assertSame( '_wpmb_lat', $payload['changed_field'] );

		// The second half of the same save must not report a second time.
		$this->assertFalse( self::call_rule( 'trigger_location_updated', [ $filter, [ 8, $post_id, '_wpmb_lng', '90.407067' ], 'location', 'updated_post_meta' ] ) );
	}

	/** @test */
	public function test_a_store_update_is_not_swallowed_by_the_location_watcher(): void {
		$post_id  = 42;
		$lat_args = [ 7, $post_id, '_wpmb_lat', '23.810300' ];
		$filter   = [];

		// Both triggers watch the same two hooks, so on a real save each one
		// records where the pair started. Shared, the first consumer would
		// also consume the record and the store's only update would arrive
		// as somebody else's.
		foreach ( [ 'location', 'store' ] as $scope ) {
			$this->assertFalse(
				self::call_rule( 'trigger_location_updated', [ $filter, $lat_args, $scope, 'update_post_meta' ] )
			);
		}

		self::write_trigger_state( 'location_writes', [ $post_id => [ 'lat' => 23.8103, 'lng' => 90.407067 ] ] );

		$location = self::call_rule( 'trigger_location_updated', [ $filter, $lat_args, 'location', 'updated_post_meta' ] );
		$this->assertIsArray( $location );

		$store = self::call_rule( 'trigger_location_updated', [ $filter, $lat_args, 'store', 'updated_post_meta' ] );
		$this->assertIsArray( $store, 'the store watcher still compares against its own copy of where the save started' );
		$this->assertSame( 23.8103, $store['lat'] );
		$this->assertSame( '_wpmb_lat', $store['changed_field'] );
	}

	/** @test */
	public function test_a_save_that_moves_nothing_never_reports(): void {
		$lng_args = [ 8, 42, '_wpmb_lng', '90.407067' ];
		$filter   = [];

		self::call_rule( 'trigger_location_updated', [ $filter, $lng_args, 'location', 'update_post_meta' ] );

		// No Zaplane write is in flight and no metabox nonce is present, so
		// the finished pair can only be the one already stored — which is
		// exactly where this save started.
		$this->assertFalse(
			self::call_rule( 'trigger_location_updated', [ $filter, $lng_args, 'location', 'updated_post_meta' ] ),
			'`Location_Meta::save()` writes (float) against a string, so `update_metadata()` never short-circuits — only comparing the pairs can'
		);
	}

	/** @test */
	public function test_a_longitude_only_write_is_reported_with_the_pair_it_built(): void {
		$lng_args = [ 8, 42, '_wpmb_lng', '90.407067' ];
		$filter   = [];

		self::call_rule( 'trigger_location_updated', [ $filter, $lng_args, 'location', 'update_post_meta' ] );

		// A third party moved only the longitude and published no pair.
		$GLOBALS['zaplane_post_meta'][42]['_wpmb_lng'] = [ '95.123456' ];

		$payload = self::call_rule( 'trigger_location_updated', [ $filter, $lng_args, 'location', 'updated_post_meta' ] );

		$this->assertIsArray( $payload );
		$this->assertSame( 23.780573, $payload['lat'] );
		$this->assertSame( 95.123456, $payload['lng'] );
	}

	/** @test */
	public function test_location_created_waits_until_both_coordinates_exist(): void {
		// Half a coordinate is not a location — WP Map Block cannot plot it,
		// so creation has to be counted from the second half arriving.
		$GLOBALS['zaplane_post_meta'][1] = [ '_wpmb_lat' => [ '10.5' ] ];
		$this->assertFalse(
			Wpmapblock::resolve_trigger( $this->makeTriggerNode( 'location_created' ), [ 7, 1, '_wpmb_lat', '10.5' ] )
		);

		$GLOBALS['zaplane_post_meta'][1]['_wpmb_lng'] = [ '20.25' ];
		$payload = Wpmapblock::resolve_trigger(
			$this->makeTriggerNode( 'location_created' ),
			[ 7, 1, '_wpmb_lng', '20.25' ]
		);

		$this->assertIsArray( $payload );
		$this->assertSame( 10.5, $payload['lat'] );
		$this->assertSame( 20.25, $payload['lng'] );
	}

	/** @test */
	public function test_store_triggers_only_accept_post_types_a_store_locator_reads(): void {
		$listed = [ 7, 42, '_wpmb_lat', '23.780573' ];

		// `product` is read by a store-locator map, so it counts as a store.
		$this->assertIsArray( Wpmapblock::resolve_trigger( $this->makeTriggerNode( 'store_created' ), $listed ) );
		// The same post under `location_created` never needs a map at all.
		$this->assertIsArray( Wpmapblock::resolve_trigger( $this->makeTriggerNode( 'location_created' ), $listed ) );

		// A post type no map reads is a location, but never a store.
		$GLOBALS['zaplane_wp_posts'][43] = new \WP_Post( [
			'ID'          => 43,
			'post_type'   => 'place',
			'post_title'  => 'Untethered',
			'post_status' => 'publish',
		] );
		$GLOBALS['zaplane_post_meta'][43] = [ '_wpmb_lat' => [ '11' ], '_wpmb_lng' => [ '22' ] ];
		$unlisted = [ 7, 43, '_wpmb_lat', '11' ];

		$this->assertIsArray( Wpmapblock::resolve_trigger( $this->makeTriggerNode( 'location_created' ), $unlisted ) );
		$this->assertFalse( Wpmapblock::resolve_trigger( $this->makeTriggerNode( 'store_created' ), $unlisted ) );

		// The post type filter narrows the location side too.
		$node = $this->makeTriggerNode( 'location_created', [ 'post_type' => 'place' ] );
		$this->assertIsArray( Wpmapblock::resolve_trigger( $node, $unlisted ) );
		$this->assertFalse( Wpmapblock::resolve_trigger( $node, $listed ) );
	}

	/** @test */
	public function test_location_published_covers_a_location_born_live(): void {
		$post = $GLOBALS['zaplane_wp_posts'][42];
		$call = function ( string $mode, array $args, string $hook ) {
			return self::call_rule( 'trigger_location_transition', [ $mode, [], $args, 'location', $hook ] );
		};

		$payload = $call( 'published', [ 'publish', 'draft', $post ], 'transition_post_status' );
		$this->assertIsArray( $payload );
		$this->assertSame( 'draft', $payload['previous_status'] );
		$this->assertSame( 'publish', $payload['new_status'] );

		// Creation never reaches it — WordPress reports `new` → `publish`
		// before a coordinate exists, which is `location_created`'s moment.
		$this->assertFalse( $call( 'published', [ 'publish', 'new', $post ], 'transition_post_status' ) );

		// Coordinates arriving on a post that is already published is the same
		// event from the other side: the location became visible.
		$payload = $call( 'published', [ 7, 42, '_wpmb_lat', '23.780573' ], 'added_post_meta' );
		$this->assertIsArray( $payload );
		$this->assertSame( '', $payload['previous_status'], 'no status was crossed to get here' );

		$draft = clone $post;
		$draft->post_status = 'draft';

		// `status` mode reports the status it landed on, whatever it is.
		$payload = $call( 'status', [ 'draft', 'publish', $draft ], 'transition_post_status' );
		$this->assertIsArray( $payload );
		$this->assertSame( 'publish', $payload['previous_status'] );
		$this->assertSame( 'draft', $payload['new_status'] );
		$this->assertSame( 'draft', $payload['status'] );

		// Published means visitors can see it, so a draft never qualifies.
		$this->assertFalse( $call( 'published', [ 'publish', 'draft', $draft ], 'transition_post_status' ) );

		// The half of the save that is not a status change says so.
		$this->assertFalse( $call( 'status', [ 7, 42, '_wpmb_lat', '23.780573' ], 'added_post_meta' ) );
	}

	/** @test */
	public function test_deep_map_settings_reach_only_the_keys_they_claim(): void {
		$settings = Wpmapblock::get_action_config_schema( 'update_map_settings' );
		$keys     = array_column( $settings, 'key' );

		$this->assertContains( 'clustering_radius', $keys );
		$this->assertContains( 'popup_preset', $keys );
		// Title and provider live on `update_map`; marker options live on
		// `update_marker_settings`. Nothing may belong to two actions.
		$this->assertNotContains( 'title', $keys );
		$this->assertNotContains( 'provider', $keys );
		$this->assertNotContains( 'animation', $keys );

		$marker = array_column( Wpmapblock::get_action_config_schema( 'update_marker_settings' ), 'key' );
		$this->assertSame( [ 'map_id', 'animation', 'draggable' ], $marker );
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
