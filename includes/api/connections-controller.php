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
		// List user's connections
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'app' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_create_args(),
				),
			)
		);

		// Single connection operations
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => $this->get_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
			)
		);

		// Test connection
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'item_permissions_check' ),
			)
		);

		// OAuth: Initialize flow
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/oauth/init',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'init_oauth' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'app'         => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'name'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'credentials' => array(
						'type'        => 'object',
						'description' => 'OAuth credentials (client_id, client_secret) if user-provided',
					),
				),
			)
		);

		// OAuth: Callback (handles the redirect from provider)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/oauth/callback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'oauth_callback' ),
				'permission_callback' => '__return_true', // Public for OAuth redirect
				'args'                => array(
					'code'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'state' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'error' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get auth fields for an integration
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/auth-fields/(?P<app>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_auth_fields' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'auth_type' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function permissions_check( $request ) {
		return is_user_logged_in();
	}

	public function item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				'You must be logged in.',
				array( 'status' => 401 )
			);
		}

		$connection_id = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$manager = $this->get_connection_manager();

		if ( ! $manager->user_owns_connection( $connection_id, $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				'You do not own this connection.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public function get_items( $request ) {
		$user_id = get_current_user_id();
		$app = $request->get_param( 'app' );

		$manager = $this->get_connection_manager();
		$connections = $manager->get_user_connections( $user_id, $app );

		return rest_ensure_response(
			array(
				'connections' => $connections,
			)
		);
	}

	public function create_item( $request ) {
		$user_id = get_current_user_id();
		$app = $request->get_param( 'app' );
		$name = $request->get_param( 'name' );
		$auth_type = $request->get_param( 'auth_type' );
		$credentials = $request->get_param( 'credentials' );

		if ( ! is_array( $credentials ) ) {
			return new WP_Error(
				'invalid_credentials',
				'Credentials must be an object',
				array( 'status' => 400 )
			);
		}

		$manager = $this->get_connection_manager();
		$connection_id = $manager->create( $user_id, $app, $name, $auth_type, $credentials );

		if ( is_wp_error( $connection_id ) ) {
			return $connection_id;
		}

		// Auto-test the connection
		$test_result = $manager->test( $connection_id );

		$connection = $manager->get( $connection_id );

		return rest_ensure_response(
			array(
				'id'          => $connection_id,
				'app'         => $connection['app'],
				'name'        => $connection['name'],
				'status'      => $connection['status'],
				'test_result' => $test_result,
			)
		);
	}

	public function get_item( $request ) {
		$connection_id = (int) $request->get_param( 'id' );

		$manager = $this->get_connection_manager();
		$connection = $manager->get( $connection_id );

		if ( ! $connection ) {
			return new WP_Error(
				'not_found',
				'Connection not found',
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $connection );
	}

	public function update_item( $request ) {
		$connection_id = (int) $request->get_param( 'id' );
		$manager = $this->get_connection_manager();

		$update_data = array();

		$name = $request->get_param( 'name' );
		if ( $name !== null ) {
			$update_data['name'] = $name;
		}

		$status = $request->get_param( 'status' );
		if ( $status !== null ) {
			$update_data['status'] = $status;
		}

		// Update metadata
		if ( ! empty( $update_data ) ) {
			$manager->update( $connection_id, $update_data );
		}

		// Update credentials if provided
		$credentials = $request->get_param( 'credentials' );
		if ( is_array( $credentials ) && ! empty( $credentials ) ) {
			$manager->update_credentials( $connection_id, $credentials );
		}

		$connection = $manager->get( $connection_id );

		return rest_ensure_response( $connection );
	}

	public function delete_item( $request ) {
		$connection_id = (int) $request->get_param( 'id' );

		$manager = $this->get_connection_manager();
		$deleted = $manager->delete( $connection_id );

		if ( ! $deleted ) {
			return new WP_Error(
				'delete_failed',
				'Failed to delete connection',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $connection_id,
			)
		);
	}

	public function test_connection( $request ) {
		$connection_id = (int) $request->get_param( 'id' );

		$manager = $this->get_connection_manager();
		$result = $manager->test( $connection_id );

		return rest_ensure_response( $result );
	}

	public function init_oauth( $request ) {
		$user_id = get_current_user_id();
		$app = $request->get_param( 'app' );
		$name = $request->get_param( 'name' );
		$credentials = $request->get_param( 'credentials' ) ?? array();

		$oauth = $this->get_oauth_handler();

		try {
			$result = $oauth->init_flow( $app, $user_id, $name, $credentials );
			return rest_ensure_response( $result );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'oauth_init_failed',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		}
	}

	public function oauth_callback( $request ) {
		// Check for OAuth error from provider
		$error = $request->get_param( 'error' );
		if ( $error ) {
			$error_description = $request->get_param( 'error_description' ) ?? 'OAuth authorization was denied';
			return $this->oauth_redirect_response( false, $error_description );
		}

		$state = $request->get_param( 'state' );
		$code = $request->get_param( 'code' );

		if ( ! $code ) {
			return $this->oauth_redirect_response( false, 'No authorization code received' );
		}

		$oauth = $this->get_oauth_handler();
		$result = $oauth->handle_callback( $state, $code );

		if ( is_wp_error( $result ) ) {
			return $this->oauth_redirect_response( false, $result->get_error_message() );
		}

		return $this->oauth_redirect_response( true, 'Connection created successfully', $result );
	}

	public function get_auth_fields( $request ) {
		$app = $request->get_param( 'app' );
		$auth_type = $request->get_param( 'auth_type' );

		$integration = IntegrationLoader::get( $app );

		if ( ! $integration ) {
			return new WP_Error(
				'not_found',
				'Integration not found',
				array( 'status' => 404 )
			);
		}

		$main_auth_type = $integration::get_auth_type();
		$response = array(
			'app'                 => $app,
			'auth_type'           => $main_auth_type,
			'requires_connection' => $integration::requires_connection(),
		);

		// If integration supports multiple auth types
		if ( $main_auth_type === 'both' ) {
			$response['available_auth_types'] = $integration::get_available_auth_types();
			$response['auth_fields'] = $integration::get_auth_fields( $auth_type );
		} else {
			$response['auth_fields'] = $integration::get_auth_fields();
		}

		return rest_ensure_response( $response );
	}

	private function oauth_redirect_response( bool $success, string $message, ?int $connection_id = null ): WP_REST_Response {
		$data = array(
			'success'       => $success,
			'message'       => $message,
			'connection_id' => $connection_id,
		);

		$json_data = wp_json_encode( $data );

		// Return HTML that communicates with opener window
		$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>OAuth Callback</title>
</head>
<body>
    <p>{$message}</p>
    <script>
        (function() {
            var data = {$json_data};
            if (window.opener) {
                window.opener.postMessage({ type: 'zaplane_oauth_callback', data: data }, '*');
                window.close();
            } else {
                // Fallback: redirect to admin with query params
                var adminUrl = '/wp-admin/admin.php?page=zaplane';
                adminUrl += '&oauth_success=' + (data.success ? '1' : '0');
                adminUrl += '&oauth_message=' + encodeURIComponent(data.message);
                if (data.connection_id) {
                    adminUrl += '&connection_id=' + data.connection_id;
                }
                window.location.href = adminUrl;
            }
        })();
    </script>
</body>
</html>
HTML;

		$response = new WP_REST_Response( $html );
		$response->set_headers( array( 'Content-Type' => 'text/html; charset=utf-8' ) );

		return $response;
	}

	private function get_connection_manager(): ConnectionManager {
		return $this->container->get( 'connections' );
	}

	private function get_oauth_handler(): OAuthHandler {
		return $this->container->get( 'oauth' );
	}

	private function get_create_args(): array {
		return array(
			'app'         => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'name'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'auth_type'   => array(
				'required'          => true,
				'type'              => 'string',
				'enum'              => array( 'api_key', 'oauth2', 'basic' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'credentials' => array(
				'required' => true,
				'type'     => 'object',
			),
		);
	}

	private function get_update_args(): array {
		return array(
			'name'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'      => array(
				'type'              => 'string',
				'enum'              => array( 'active', 'inactive' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'credentials' => array(
				'type' => 'object',
			),
		);
	}
}
