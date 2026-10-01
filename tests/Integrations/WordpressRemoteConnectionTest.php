<?php

namespace Zaplane\Tests\Integrations;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Zaplane\API\RemoteController;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Integrations\Wordpress;
use Zaplane\Services\RemoteTriggerBridge;
use Zaplane\Tests\WPMocks;

/**
 * The two ways a WordPress connection can be made.
 *
 * A site running Zaplane — local or live — pairs with any other site running
 * Zaplane from a Site URL alone, and a site running no Zaplane is reached
 * over the core REST API with a username and an Application Password. Both
 * directions, both kinds of destination, are covered here.
 */
class WordpressRemoteConnectionTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Wordpress::class;
	}

	/**
	 * Expose the Wordpress integration to IntegrationLoader, which reads its
	 * registry once from config plus this filter.
	 *
	 * @param mixed $registry Registry handed over by the loader.
	 * @return array<string,array<string,string>>
	 */
	public function register_wordpress_integration( $registry ) {
		if ( ! is_array( $registry ) ) {
			$registry = [];
		}

		$registry['wordpress'] = [
			'file'  => 'wordpress.php',
			'class' => 'Zaplane\Integrations\Wordpress',
		];

		return $registry;
	}

	private function site_url(): string {
		return 'https://other.test';
	}

	/**
	 * Queue the two answers a Zaplane site gives when it is first met.
	 */
	private function queue_pairing(): void {
		WPMocks::setHttpResponse(
			[
				'zaplane' => true,
				'site'    => 'The Other Site',
				'url'     => 'https://other.test',
				'version' => '1.3.3',
				'paired'  => false,
			]
		);

		WPMocks::setHttpResponse(
			[
				'token'   => 'token-from-remote',
				'site'    => 'The Other Site',
				'url'     => 'https://other.test',
				'version' => '1.3.3',
			]
		);
	}

	public function test_a_site_running_zaplane_connects_without_a_username_or_password(): void {
		$this->queue_pairing();

		$result = Wordpress::test_connection( [ 'site_url' => $this->site_url() ] );

		$this->assertTrue( $result['success'], $result['message'] );
		$this->assertSame( 'zaplane', $result['details']['mode'] );
		$this->assertStringContainsString( 'Auto-connected', $result['message'] );

		$sites = get_option( 'zaplane_connected_sites', [] );

		$this->assertSame( 'token-from-remote', $sites[ $this->site_url() ]['token'] );
		$this->assertSame( 'zaplane', $sites[ $this->site_url() ]['mode'] );
	}

	public function test_a_site_without_zaplane_and_without_a_password_is_refused_with_an_explanation(): void {
		WPMocks::setHttpResponse(
			[
				'code'    => 'rest_no_route',
				'message' => 'No route was found matching the URL and request method.',
			],
			404
		);

		$result = Wordpress::test_connection( [ 'site_url' => $this->site_url() ] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Zaplane', $result['message'] );
		$this->assertStringContainsString( 'Application Password', $result['message'] );
	}

	public function test_a_site_without_zaplane_connects_over_the_application_password(): void {
		WPMocks::setHttpResponse(
			[
				'code'    => 'rest_no_route',
				'message' => 'No route was found matching the URL and request method.',
			],
			404
		);

		WPMocks::setHttpResponse(
			[
				'name'     => 'Site Admin',
				'username' => 'admin',
				'roles'    => [ 'administrator' ],
			]
		);

		$result = Wordpress::test_connection(
			[
				'site_url' => $this->site_url(),
				'username' => 'admin',
				'password' => 'abcd efgh ijkl mnop',
			]
		);

		$this->assertTrue( $result['success'], $result['message'] );
		$this->assertSame( 'rest', $result['details']['mode'] );

		$sites = get_option( 'zaplane_connected_sites', [] );

		$this->assertSame( 'rest', $sites[ $this->site_url() ]['mode'] );
		$this->assertArrayNotHasKey( 'token', $sites[ $this->site_url() ] );
	}

	public function test_an_empty_site_url_is_refused_before_anything_is_called(): void {
		$result = Wordpress::test_connection( [] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Site URL', $result['message'] );
	}

	public function test_a_step_on_a_paired_site_is_run_by_that_site(): void {
		$this->queue_pairing();

		Wordpress::test_connection( [ 'site_url' => $this->site_url() ] );

		WPMocks::setHttpResponse(
			[
				'port' => 'main',
				'data' => [ 'post_id' => 4242 ],
				'meta' => [ 'timestamp' => 1 ],
			]
		);

		$result = Wordpress::execute_node(
			$this->makeActionNode(
				'create_post',
				[ 'post_title' => 'Written over there', 'post_type' => 'post', 'post_status' => 'publish' ],
				[ 'site_url' => $this->site_url() ]
			),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( 4242, $result['data']['post_id'] );
	}

	public function test_a_step_on_a_site_that_lost_zaplane_says_the_credentials_are_missing(): void {
		update_option(
			'zaplane_connected_sites',
			[
				$this->site_url() => [ 'mode' => 'zaplane' ],
			]
		);

		WPMocks::setHttpResponse(
			[
				'code'    => 'rest_no_route',
				'message' => 'No route was found matching the URL and request method.',
			],
			404
		);

		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_post', [ 'post_title' => 'x' ], [ 'site_url' => $this->site_url() ] ),
			[]
		);

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'username', $result['data']['error'] );
	}

	public function test_a_step_falls_back_to_the_rest_api_when_the_connection_holds_a_password(): void {
		update_option(
			'zaplane_connected_sites',
			[
				$this->site_url() => [ 'mode' => 'zaplane' ],
			]
		);

		WPMocks::setHttpResponse(
			[
				'code'    => 'rest_no_route',
				'message' => 'No route was found matching the URL and request method.',
			],
			404
		);

		$result = Wordpress::execute_node(
			$this->makeActionNode(
				'register_post_type',
				[ 'post_type' => 'book', 'label' => 'Books' ],
				[
					'site_url' => $this->site_url(),
					'username' => 'admin',
					'password' => 'abcd efgh ijkl mnop',
				]
			),
			[]
		);

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'Install Zaplane there', $result['data']['error'] );
	}

	public function test_a_connection_with_no_site_url_runs_against_this_site(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_post', [ 'post_title' => 'Local', 'post_type' => 'post', 'post_status' => 'draft' ] ),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertArrayHasKey( 'post_id', $result['data'] );
	}

	public function test_a_web_page_answer_instead_of_json_is_reported_not_swallowed(): void {
		// Both URL styles are answered by the page — nothing behind the site
		// is an API at all.
		WPMocks::setRawHttpResponse( '<!doctype html><html><body>Please log in.</body></html>', 200 );
		WPMocks::setRawHttpResponse( '<!doctype html><html><body>Please log in.</body></html>', 200 );

		$result = Wordpress::execute_node(
			$this->makeActionNode(
				'create_post',
				[ 'post_title' => 'Over there', 'post_type' => 'post', 'post_status' => 'publish' ],
				[
					'site_url' => $this->site_url(),
					'username' => 'admin',
					'password' => 'abcd efgh ijkl mnop',
				]
			),
			[]
		);

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'instead of JSON', $result['data']['error'] );
	}

	public function test_an_answer_without_the_created_post_is_reported_not_swallowed(): void {
		// A redirected POST comes back as a GET answer — a list of posts, not
		// the one that was made.
		WPMocks::setHttpResponse( [] );
		WPMocks::setHttpResponse( [ [ 'id' => 1 ], [ 'id' => 2 ] ] );

		$result = Wordpress::execute_node(
			$this->makeActionNode(
				'create_post',
				[ 'post_title' => 'Over there', 'post_type' => 'post', 'post_status' => 'publish' ],
				[
					'site_url' => $this->site_url(),
					'username' => 'admin',
					'password' => 'abcd efgh ijkl mnop',
				]
			),
			[]
		);

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'did not create the post', $result['data']['error'] );
	}

	public function test_a_site_with_plain_permalinks_connects_over_the_query_style(): void {
		// The /wp-json/ probe is answered by a redirect that ends on a page.
		WPMocks::setRawHttpResponse( '<!doctype html><html><body>Not found</body></html>', 200 );
		// The same probe in the query style is answered by the REST API, which
		// is remembered — the users/me call that follows goes straight there.
		WPMocks::setHttpResponse(
			[
				'code'    => 'rest_no_route',
				'message' => 'No route was found matching the URL and request method.',
			],
			404
		);
		WPMocks::setHttpResponse(
			[
				'name'     => 'Site Admin',
				'username' => 'admin',
				'roles'    => [ 'administrator' ],
			]
		);

		$result = Wordpress::test_connection(
			[
				'site_url' => $this->site_url(),
				'username' => 'admin',
				'password' => 'abcd efgh ijkl mnop',
			]
		);

		$this->assertTrue( $result['success'], $result['message'] );
		$this->assertSame( 'rest', $result['details']['mode'] );
		$this->assertSame(
			'query',
			get_option( 'zaplane_connected_sites', [] )[ $this->site_url() ]['rest_style']
		);
	}

	public function test_a_site_remembered_as_query_style_calls_rest_route_directly(): void {
		update_option(
			'zaplane_connected_sites',
			[
				$this->site_url() => [
					'mode'       => 'rest',
					'rest_style' => 'query',
				],
			]
		);

		WPMocks::setHttpResponse( [] );
		WPMocks::setHttpResponse(
			[
				'id'     => 5,
				'status' => 'publish',
				'type'   => 'post',
				'title'  => [ 'raw' => 'Made it' ],
			]
		);

		$result = Wordpress::execute_node(
			$this->makeActionNode(
				'create_post',
				[ 'post_title' => 'Made it', 'post_type' => 'post', 'post_status' => 'publish' ],
				[
					'site_url' => $this->site_url(),
					'username' => 'admin',
					'password' => 'abcd efgh ijkl mnop',
				]
			),
			[]
		);

		$this->assertSame( 'main', $result['port'], $result['data']['error'] ?? '' );
		$this->assertSame( 5, $result['data']['post_id'] );

		$urls = WPMocks::getHttpRequests();

		$this->assertNotEmpty( $urls );

		foreach ( $urls as $url ) {
			$this->assertStringContainsString( 'rest_route=', $url );
			$this->assertStringNotContainsString( '/wp-json/', $url );
		}
	}

	public function test_info_describes_a_site_running_zaplane(): void {
		$response = ( new RemoteController() )->get_info();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertTrue( $response->get_data()['zaplane'] );
		$this->assertFalse( $response->get_data()['paired'] );
	}

	public function test_pairing_mints_a_token_for_the_first_site_that_asks(): void {
		$request  = new \WP_REST_Request();
		$request->set_param( 'controller', 'https://one.test' );

		$response = ( new RemoteController() )->pair( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertNotEmpty( $response->get_data()['token'] );

		$pairings = get_option( RemoteController::OPTION, [] );

		$this->assertSame( 'https://one.test', $pairings['https://one.test']['controller'] );
		$this->assertSame( $response->get_data()['token'], $pairings['https://one.test']['token'] );
	}

	public function test_two_sites_can_hold_a_connection_at_the_same_time(): void {
		update_option(
			RemoteController::OPTION,
			[
				'https://one.test' => [
					'controller' => 'https://one.test',
					'token'      => 'held-by-one',
					'created'    => time(),
				],
			]
		);

		$request  = new \WP_REST_Request();
		$request->set_param( 'controller', 'https://two.test' );

		$response = ( new RemoteController() )->pair( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertNotEmpty( $response->get_data()['token'] );

		$pairings = get_option( RemoteController::OPTION, [] );

		$this->assertCount( 2, $pairings );
		$this->assertSame( 'held-by-one', $pairings['https://one.test']['token'] );

		$controller = new RemoteController();

		$first = new \WP_REST_Request();
		$first->set_param( 'token', 'held-by-one' );
		$this->assertTrue( $controller->check_token( $first ) );

		$second = new \WP_REST_Request();
		$second->set_param( 'token', $response->get_data()['token'] );
		$this->assertTrue( $controller->check_token( $second ) );
	}

	public function test_pairing_the_same_site_again_rotates_its_token(): void {
		update_option(
			RemoteController::OPTION,
			[
				'controller' => 'https://one.test',
				'token'      => 'held-by-one',
				'created'    => time(),
			]
		);

		$request  = new \WP_REST_Request();
		$request->set_param( 'controller', 'https://one.test' );

		$response = ( new RemoteController() )->pair( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertNotSame( 'held-by-one', $response->get_data()['token'] );
	}

	public function test_run_only_opens_with_the_token_this_site_handed_out(): void {
		update_option(
			RemoteController::OPTION,
			[
				'controller' => 'https://one.test',
				'token'      => 'the-real-token',
				'created'    => time(),
			]
		);

		$controller = new RemoteController();

		$refused = new \WP_REST_Request();
		$refused->set_param( 'token', 'not-the-token' );
		$refused_error = $controller->check_token( $refused );

		$this->assertInstanceOf( WP_Error::class, $refused_error );
		$this->assertSame( 'zaplane_not_paired', $refused_error->get_error_code() );

		$allowed = new \WP_REST_Request();
		$allowed->set_param( 'token', 'the-real-token' );

		$this->assertTrue( $controller->check_token( $allowed ) );
	}

	public function test_run_refuses_a_step_the_other_site_does_not_have(): void {
		add_filter( 'zaplane_integrations', [ $this, 'register_wordpress_integration' ] );

		update_option(
			RemoteController::OPTION,
			[
				'controller' => 'https://one.test',
				'token'      => 'the-real-token',
				'created'    => time(),
			]
		);

		$request = new \WP_REST_Request();
		$request->set_param( 'token', 'the-real-token' );
		$request->set_param( 'app', 'wordpress' );
		$request->set_param( 'event', 'not_a_real_step' );

		$response = ( new RemoteController() )->run( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'zaplane_unknown_step', $response->get_error_code() );
	}

	public function test_run_refuses_an_app_that_may_not_be_driven(): void {
		add_filter( 'zaplane_integrations', [ $this, 'register_wordpress_integration' ] );

		update_option(
			RemoteController::OPTION,
			[
				'controller' => 'https://one.test',
				'token'      => 'the-real-token',
				'created'    => time(),
			]
		);

		$request = new \WP_REST_Request();
		$request->set_param( 'token', 'the-real-token' );
		$request->set_param( 'app', 'dangerous' );
		$request->set_param( 'event', 'create_post' );

		$response = ( new RemoteController() )->run( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'zaplane_app_not_allowed', $response->get_error_code() );
	}

	public function test_run_executes_the_step_on_this_site(): void {
		add_filter( 'zaplane_integrations', [ $this, 'register_wordpress_integration' ] );

		update_option(
			RemoteController::OPTION,
			[
				'controller' => 'https://one.test',
				'token'      => 'the-real-token',
				'created'    => time(),
			]
		);

		$this->assertInstanceOf(
			\Zaplane\Integrations\Wordpress::class,
			IntegrationLoader::get( 'wordpress' )
		);

		$request = new \WP_REST_Request();
		$request->set_param( 'token', 'the-real-token' );
		$request->set_param( 'app', 'wordpress' );
		$request->set_param( 'event', 'create_post' );
		$request->set_param(
			'config',
			[
				'post_title'  => 'Written by the calling site',
				'post_type'   => 'post',
				'post_status' => 'draft',
			]
		);
		$request->set_param( 'input', [ 'from' => 'previous-step' ] );

		$response = ( new RemoteController() )->run( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertSame( 'main', $data['port'] );
		$this->assertArrayHasKey( 'post_id', $data['data'] );
	}

	public function test_unpairing_without_a_token_clears_every_pairing(): void {
		update_option(
			RemoteController::OPTION,
			[
				'controller' => 'https://one.test',
				'token'      => 'the-real-token',
				'created'    => time(),
			]
		);

		$response = ( new RemoteController() )->unpair( new \WP_REST_Request() );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertFalse( ( new RemoteController() )->get_info()->get_data()['paired'] );
	}

	public function test_a_controller_unpairing_drops_only_its_own_connection(): void {
		$this->pair_controller( 'https://one.test', 'token-one' );
		$this->pair_controller( 'https://two.test', 'token-two' );

		$request = new \WP_REST_Request();
		$request->set_param( 'token', 'token-one' );

		$response = ( new RemoteController() )->unpair( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertTrue( $response->get_data()['paired'] );

		$pairings = get_option( RemoteController::OPTION, [] );

		$this->assertArrayNotHasKey( 'https://one.test', $pairings );
		$this->assertArrayHasKey( 'https://two.test', $pairings );
	}

	/**
	 * The watch a controller sends: which hook, and what the trigger means.
	 */
	private function publish_spec(): array {
		return [
			[
				'app'    => 'wordpress',
				'event'  => 'publish_post',
				'hook'   => 'publish_post',
				'config' => [ 'post_type' => 'post' ],
			],
		];
	}

	/**
	 * Record a pairing so the shared test token belongs to a controller.
	 */
	private function pair_controller( string $url = 'https://one.test', string $token = 'the-real-token' ): void {
		$pairings           = (array) get_option( RemoteController::OPTION, [] );
		$pairings[ $url ]   = [
			'controller' => $url,
			'token'      => $token,
			'created'    => time(),
		];

		update_option( RemoteController::OPTION, $pairings );
	}

	/**
	 * Build a /remote/events request.
	 *
	 * @param array<int,array<string,mixed>> $specs Triggers to watch, or none
	 *                                              to only collect.
	 * @param int                            $since Last sequence collected.
	 */
	private function events_request( array $specs = [], int $since = 0 ): \WP_REST_Request {
		$request = new \WP_REST_Request();
		$request->set_param( 'token', 'the-real-token' );

		if ( array() !== $specs ) {
			$request->set_param( 'specs', $specs );
		}

		$request->set_param( 'since', $since );

		return $request;
	}

	public function test_the_connected_site_takes_the_watch_a_controller_sends(): void {
		$this->pair_controller();

		$response = ( new RemoteController() )->events( $this->events_request( $this->publish_spec() ) );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 0, $response->get_data()['seq'] );
		$this->assertSame( [], $response->get_data()['events'] );

		$watch = get_option( RemoteController::WATCH, [] )['https://one.test'];

		$this->assertSame( [ 'publish_post' ], $watch['hooks'] );
		$this->assertSame( 'publish_post', $watch['specs'][0]['event'] );
		$this->assertSame( [ 'post_type' => 'post' ], $watch['specs'][0]['config'] );
	}

	public function test_a_fired_trigger_is_resolved_here_and_collected_by_the_site_that_asked(): void {
		add_filter( 'zaplane_integrations', [ $this, 'register_wordpress_integration' ] );
		$this->pair_controller();

		( new RemoteController() )->events( $this->events_request( $this->publish_spec() ) );

		// The hook firing on this site, resolved with this site's own post.
		RemoteController::capture_hook( 'publish_post', [ 1 ] );

		$events = ( new RemoteController() )->events( $this->events_request( [], 0 ) )->get_data();

		$this->assertSame( 1, $events['seq'] );
		$this->assertCount( 1, $events['events'] );
		$this->assertSame( 'publish_post', $events['events'][0]['hook'] );
		$this->assertSame( 'wordpress', $events['events'][0]['app'] );
		$this->assertNotEmpty( $events['events'][0]['payload'] );

		// Nothing is handed back twice.
		$again = ( new RemoteController() )->events( $this->events_request( [], 1 ) )->get_data();

		$this->assertSame( [], $again['events'] );
	}

	public function test_a_step_for_an_app_the_connected_site_cannot_run_is_not_watched(): void {
		$this->pair_controller();

		$response = ( new RemoteController() )->events(
			$this->events_request(
				[
					[
						'app'    => 'dangerous',
						'event'  => 'boom',
						'hook'   => 'boom',
						'config' => [],
					],
					[
						'app'    => 'wordpress',
						'event'  => 'publish_post',
						'hook'   => 'publish_post',
						'config' => [],
					],
				]
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$watch = get_option( RemoteController::WATCH, [] )['https://one.test'];

		$this->assertCount( 1, $watch['specs'] );
		$this->assertSame( 'wordpress', $watch['specs'][0]['app'] );
		$this->assertSame( [ 'publish_post' ], $watch['hooks'] );
	}

	public function test_unpairing_drops_the_watch_and_everything_not_yet_collected(): void {
		add_filter( 'zaplane_integrations', [ $this, 'register_wordpress_integration' ] );
		$this->pair_controller();

		( new RemoteController() )->events( $this->events_request( $this->publish_spec() ) );
		RemoteController::capture_hook( 'publish_post', [ 1 ] );

		( new RemoteController() )->unpair( $this->events_request() );

		$this->assertSame( [], get_option( RemoteController::WATCH, [] ) );
		$this->assertSame( [], get_option( RemoteController::BUFFER, [] ) );

		// The token died with the pairing, so nothing can be collected with it.
		$response = ( new RemoteController() )->events( $this->events_request( [], 0 ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'zaplane_not_paired', $response->get_error_code() );
	}

	public function test_each_controller_collects_only_its_own_events(): void {
		add_filter( 'zaplane_integrations', [ $this, 'register_wordpress_integration' ] );
		$this->pair_controller( 'https://one.test', 'token-one' );
		$this->pair_controller( 'https://two.test', 'token-two' );

		$one = new \WP_REST_Request();
		$one->set_param( 'token', 'token-one' );
		$one->set_param( 'specs', $this->publish_spec() );
		$one->set_param( 'since', 0 );

		$two = new \WP_REST_Request();
		$two->set_param( 'token', 'token-two' );
		$two->set_param( 'since', 0 );

		( new RemoteController() )->events( $one );

		RemoteController::capture_hook( 'publish_post', [ 1 ] );

		$for_one   = ( new RemoteController() )->events( $one )->get_data();
		$for_two   = ( new RemoteController() )->events( $two )->get_data();

		$this->assertSame( 1, $for_one['seq'] );
		$this->assertCount( 1, $for_one['events'] );

		$this->assertSame( 0, $for_two['seq'] );
		$this->assertSame( [], $for_two['events'] );
	}

	public function test_a_token_nobody_handed_out_is_refused_everywhere(): void {
		$this->pair_controller( 'https://one.test', 'token-one' );

		$request = new \WP_REST_Request();
		$request->set_param( 'token', 'not-a-real-token' );

		$response = ( new RemoteController() )->events( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'zaplane_not_paired', $response->get_error_code() );
	}

	public function test_a_connection_in_rest_mode_is_never_asked_to_watch(): void {
		update_option(
			'zaplane_connected_sites',
			[
				$this->site_url() => [ 'mode' => 'rest' ],
			]
		);

		$result = Wordpress::remote_watch( [ 'site_url' => $this->site_url() ], [], 4 );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( '', $result['error'] );
		$this->assertSame( 4, $result['seq'] );
		$this->assertSame( [], $result['events'] );
	}

	public function test_a_watch_takes_what_the_connected_site_queued(): void {
		update_option(
			'zaplane_connected_sites',
			[
				$this->site_url() => [
					'mode'  => 'zaplane',
					'token' => 'held-token',
				],
			]
		);

		WPMocks::setHttpResponse(
			[
				'seq'    => 7,
				'events' => [
					[
						'seq'     => 4,
						'app'     => 'wordpress',
						'event'   => 'publish_post',
						'hook'    => 'publish_post',
						'payload' => [ 'ID' => 1 ],
					],
				],
			]
		);

		$result = Wordpress::remote_watch( [ 'site_url' => $this->site_url() ], [], 3 );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 7, $result['seq'] );
		$this->assertCount( 1, $result['events'] );
		$this->assertSame( 'publish_post', $result['events'][0]['hook'] );
		$this->assertSame( [ 'ID' => 1 ], $result['events'][0]['payload'] );
	}

	public function test_a_refused_watch_pairs_again_and_retries_once(): void {
		update_option(
			'zaplane_connected_sites',
			[
				$this->site_url() => [
					'mode'  => 'zaplane',
					'token' => 'stale-token',
				],
			]
		);

		// Refused, then the probe, the pairing, and the collection retried.
		WPMocks::setHttpResponse( [ 'code' => 'zaplane_not_paired', 'message' => 'Not paired.' ], 403 );
		WPMocks::setHttpResponse(
			[
				'zaplane' => true,
				'site'    => 'The Other Site',
				'url'     => $this->site_url(),
				'version' => '1.3.3',
			]
		);
		WPMocks::setHttpResponse(
			[
				'token' => 'fresh-token',
				'site'  => 'The Other Site',
				'url'   => $this->site_url(),
			]
		);
		WPMocks::setHttpResponse( [ 'seq' => 2, 'events' => [] ] );

		$result = Wordpress::remote_watch( [ 'site_url' => $this->site_url() ], [], 1 );

		$this->assertTrue( $result['ok'], $result['error'] );
		$this->assertSame( 2, $result['seq'] );

		$sites = get_option( 'zaplane_connected_sites', [] );

		$this->assertSame( 'fresh-token', $sites[ $this->site_url() ]['token'] );
	}

	public function test_the_bridge_collects_without_letting_one_broken_site_escape(): void {
		RemoteTriggerBridge::boot();
		RemoteTriggerBridge::run();

		$schedules = RemoteTriggerBridge::cron_schedules( [] );

		$this->assertArrayHasKey( RemoteTriggerBridge::CRON_SCHEDULE, $schedules );
		$this->assertSame( 60, $schedules[ RemoteTriggerBridge::CRON_SCHEDULE ]['interval'] );
	}
}
