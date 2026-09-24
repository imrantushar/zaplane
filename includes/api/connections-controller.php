<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Classes\OAuthHandler;
use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConnectionsController extends WP_REST_Controller {

	protected Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'connections';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'app' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_create_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'item_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'item_permissions_check' ],
					'args'                => $this->get_update_args(),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'item_permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/test',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_connection' ],
				'permission_callback' => [ $this, 'item_permissions_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/oauth/init',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'init_oauth' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'app'         => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'name'        => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'credentials' => [
						'type'        => 'object',
						'description' => 'OAuth credentials (client_id, client_secret)',
					],
				],
			]
		);

		// Public by necessity: the provider redirects the admin's browser here
		// without a REST nonce. oauth_callback() refuses anything whose `state`
		// does not match the one issued to that admin when they clicked Connect.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/oauth/callback',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'oauth_callback' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'code'  => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'state' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'error' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/auth-fields/(?P<app>[a-z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_auth_fields' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'auth_type' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	public function item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to manage connections.', [ 'status' => rest_authorization_required_code() ] );
		}

		$connection_id = (int) $request->get_param( 'id' );
		$user_id       = get_current_user_id();
		$manager       = $this->get_connection_manager();

		if ( ! $manager->user_owns_connection( $connection_id, $user_id ) ) {
			return new WP_Error( 'rest_forbidden', 'You do not own this connection.', [ 'status' => 403 ] );
		}

		return true;
	}

	public function get_items( $request ) {
		$user_id  = get_current_user_id();
		$app      = $request->get_param( 'app' );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$manager = $this->get_connection_manager();
		$result  = $manager->get_user_connections( $user_id, $app, $page, $per_page );

		return rest_ensure_response( [
			'data'       => $result['data'],
			'pagination' => $result['pagination'],
		] );
	}

	public function create_item( $request ) {
		$user_id     = get_current_user_id();
		$app         = $request->get_param( 'app' );
		$icon        = $request->get_param( 'icon' );
		$name        = $request->get_param( 'name' );
		$auth_type   = $request->get_param( 'auth_type' );
		$credentials = $request->get_param( 'credentials' );

		if ( ! is_array( $credentials ) ) {
			return new WP_Error( 'invalid_credentials', 'Credentials must be an object', [ 'status' => 400 ] );
		}

		$manager = $this->get_connection_manager();

		try {
			$result = $manager->create( $user_id, $app, $name, $auth_type, $credentials, $icon );
		} catch ( \Zaplane\Framework\Exceptions\ConnectionException $e ) {
			return new WP_Error( 'connection_test_failed', $e->getMessage(), [ 'status' => 400 ] );
		} catch ( \Zaplane\Framework\Exceptions\IntegrationException $e ) {
			return new WP_Error( 'integration_not_found', $e->getMessage(), [ 'status' => 404 ] );
		}

		$connection_id = $result['id'];
		$connection    = $manager->get( $connection_id );

		return rest_ensure_response( [
			'id'          => $connection_id,
			'app'         => $connection['app'],
			'icon'        => $connection['icon'] ?? null,
			'name'        => $connection['name'],
			'status'      => $connection['status'],
			'test_result' => $result['test_result'],
		] );
	}

	public function get_item( $request ) {
		$connection_id = (int) $request->get_param( 'id' );
		$manager       = $this->get_connection_manager();
		$connection    = $manager->get( $connection_id );

		if ( ! $connection ) {
			return new WP_Error( 'not_found', 'Connection not found', [ 'status' => 404 ] );
		}

		return rest_ensure_response( $connection );
	}

	public function update_item( $request ) {
		$connection_id = (int) $request->get_param( 'id' );
		$manager       = $this->get_connection_manager();
		$update_data   = [];

		$name = $request->get_param( 'name' );
		if ( null !== $name ) {
			$update_data['name'] = $name;
		}

		$icon = $request->get_param( 'icon' );
		if ( null !== $icon ) {
			$update_data['icon'] = $icon;
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$update_data['status'] = $status;
		}

		if ( ! empty( $update_data ) ) {
			$manager->update( $connection_id, $update_data );
		}

		$test_result = null;
		$credentials = $request->get_param( 'credentials' );
		if ( is_array( $credentials ) && ! empty( $credentials ) ) {
			try {
				$result      = $manager->update_credentials( $connection_id, $credentials );
				$test_result = $result['test_result'];
			} catch ( \Zaplane\Framework\Exceptions\ConnectionException $e ) {
				return new WP_Error( 'connection_test_failed', $e->getMessage(), [ 'status' => 400 ] );
			}
		}

		$connection                = $manager->get( $connection_id );
		$connection['test_result'] = $test_result;

		return rest_ensure_response( $connection );
	}

	public function delete_item( $request ) {
		$connection_id = (int) $request->get_param( 'id' );
		$manager       = $this->get_connection_manager();
		$deleted       = $manager->delete( $connection_id );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', 'Failed to delete connection', [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'deleted' => true,
			'id' => $connection_id
		] );
	}

	public function test_connection( $request ) {
		$connection_id = (int) $request->get_param( 'id' );
		$manager       = $this->get_connection_manager();
		$result        = $manager->test( $connection_id );

		return rest_ensure_response( $result );
	}

	public function init_oauth( $request ) {
		$user_id = get_current_user_id();
		$app = $request->get_param( 'app' );
		$name = $request->get_param( 'name' );
		$icon = $request->get_param( 'icon' );
		$credentials = $request->get_param( 'credentials' ) ?? [];
		$oauth       = $this->get_oauth_handler();

		try {
			$result = $oauth->init_flow( $app, $user_id, $name, $credentials, $icon );
			return rest_ensure_response( $result );
		} catch ( \Exception $e ) {
			return new WP_Error( 'oauth_init_failed', $e->getMessage(), [ 'status' => 400 ] );
		}
	}

	public function oauth_callback( $request ) {
		$error = $request->get_param( 'error' );
		if ( $error ) {
			$desc = $request->get_param( 'error_description' ) ?? 'OAuth authorization was denied';
			$this->send_oauth_html( false, $desc );
		}

		$state = $request->get_param( 'state' );
		$code  = $request->get_param( 'code' );

		if ( ! $code ) {
			$this->send_oauth_html( false, 'No authorization code received' );
		}

		$oauth = $this->get_oauth_handler();

		try {
			$result = $oauth->handle_callback( $state, $code );
		} catch ( \Throwable $e ) {
			$this->send_oauth_html( false, $e->getMessage() );
			return;
		}

		$this->send_oauth_html( true, 'Connection created successfully', $result );
	}

	public function get_auth_fields( $request ) {
		$app        = $request->get_param( 'app' );
		$auth_type  = $request->get_param( 'auth_type' );
		$integration = IntegrationLoader::get( $app );

		if ( ! $integration ) {
			return new WP_Error( 'not_found', 'Integration not found', [ 'status' => 404 ] );
		}

		$main_auth_type = $integration::get_auth_type();

		$response = [
			'app'                 => $app,
			'auth_type'           => $main_auth_type,
			'requires_connection' => $integration::requires_connection(),
		];

		if ( 'both' === $main_auth_type ) {
			$response['available_auth_types'] = $integration::get_available_auth_types();
			$response['auth_fields']          = $integration::get_auth_fields( $auth_type );
		} else {
			$response['auth_fields'] = $integration::get_auth_fields();
		}

		return rest_ensure_response( $response );
	}

	private function send_oauth_html( bool $success, string $message, ?int $connection_id = null ): void {
		$data = [
			'success'       => $success,
			'message'       => $message,
			'connection_id' => $connection_id,
		];

		// This is interpolated straight into a <script> block on a route that is
		// unauthenticated by necessity, and $data carries the provider's own
		// error_description from the query string. Today the payload cannot break
		// out only because json_encode escapes forward slashes by default, so
		// "</script>" arrives as "<\/script>" — anyone adding
		// JSON_UNESCAPED_SLASHES for readability would silently turn this into
		// reflected XSS. JSON_HEX_TAG makes the guarantee explicit instead.
		$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		$script = '(function () {'
			. 'var d = ' . $json . ';'
			. 'if (window.opener && !window.opener.closed) {'
			// Hand the result to the tab that opened this one.
			. 'window.opener.postMessage({ type: "zaplane_oauth_callback", data: d }, window.location.origin);'
			. 'window.close();'
			. '} else {'
			// No opener: go back to Zaplane with the result in the query string.
			. 'var url = ' . wp_json_encode( admin_url( 'admin.php?page=zaplane' ) ) . ';'
			. 'url += "&oauth_success=" + (d.success ? "1" : "0");'
			. 'url += "&oauth_message=" + encodeURIComponent(d.message);'
			. 'if (d.connection_id) { url += "&connection_id=" + d.connection_id; }'
			. 'window.location.href = url;'
			. '}'
			. '})();';

		// Send raw HTML — do NOT use WP_REST_Response here
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		}

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>OAuth Callback</title></head><body>';
		echo '<p>' . esc_html( $message ) . '</p>';
		wp_print_inline_script_tag( $script );
		echo '</body></html>';
		exit;
	}

	private function get_connection_manager(): ConnectionManager {
		return $this->container->get( 'connections' );
	}

	private function get_oauth_handler(): OAuthHandler {
		return $this->container->get( 'oauth' );
	}


	private function get_create_args(): array {
		return [
			'app'         => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'icon'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'name'        => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'auth_type'   => [
				'required'          => true,
				'type'              => 'string',
				'enum'              => [ 'api_key', 'oauth2', 'basic' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'credentials' => [
				'required' => true,
				'type'     => 'object',
			],
		];
	}

	private function get_update_args(): array {
		return [
			'name'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'icon'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'status'      => [
				'type'              => 'string',
				'enum'              => [ 'active', 'inactive' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'credentials' => [
				'type' => 'object',
			],
		];
	}
}
