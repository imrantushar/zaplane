<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\CustomApps\ManifestStore;
use Zaplane\CustomApps\ManifestValidator;
use Zaplane\CustomApps\Template;
use Zaplane\CustomApps\RequestBuilder;
use Zaplane\CustomApps\HttpClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + tooling for user-defined Custom Apps.
 *
 * Everything here is gated on `manage_options`: a custom app can issue arbitrary
 * outbound HTTP, so authoring one is an admin-level capability.
 */
class CustomAppsController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'custom-apps';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/test-request', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'test_request' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/import', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'import_item' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-z0-9_]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-z0-9_]+)/test-connection', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'test_connection' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/* --------------------------------------------------------------------- */

	public function get_items( $request ) {
		return rest_ensure_response( array_values( ManifestStore::all() ) );
	}

	public function get_item( $request ) {
		$manifest = ManifestStore::get( (string) $request['slug'] );
		if ( null === $manifest ) {
			return new WP_Error( 'not_found', 'Custom app not found.', [ 'status' => 404 ] );
		}
		return rest_ensure_response( $manifest );
	}

	public function create_item( $request ) {
		$manifest = $this->manifest_from_request( $request );
		$manifest['slug'] = ManifestValidator::sanitize_slug( (string) ( $manifest['slug'] ?? '' ) );

		if ( '' !== $manifest['slug'] && ManifestStore::exists( $manifest['slug'] ) ) {
			return new WP_Error( 'exists', 'A custom app with that slug already exists.', [ 'status' => 409 ] );
		}

		return $this->save( $manifest );
	}

	public function update_item( $request ) {
		$slug = (string) $request['slug'];
		if ( ! ManifestStore::exists( $slug ) ) {
			return new WP_Error( 'not_found', 'Custom app not found.', [ 'status' => 404 ] );
		}

		$manifest         = $this->manifest_from_request( $request );
		$manifest['slug'] = $slug; // Slug is immutable on update.

		return $this->save( $manifest );
	}

	public function delete_item( $request ) {
		$slug = (string) $request['slug'];
		if ( ! ManifestStore::delete( $slug ) ) {
			return new WP_Error( 'not_found', 'Custom app not found.', [ 'status' => 404 ] );
		}
		return rest_ensure_response( [ 'deleted' => true, 'slug' => $slug ] );
	}

	public function import_item( WP_REST_Request $request ) {
		$manifest = $this->manifest_from_request( $request );
		$manifest['slug'] = ManifestValidator::sanitize_slug( (string) ( $manifest['slug'] ?? '' ) );

		// Importing over an existing slug replaces it (an explicit user action).
		return $this->save( $manifest );
	}

	/**
	 * Run a single request template exactly as the runtime would, for the
	 * builder's "Send test request" preview. Accepts an inline (possibly
	 * incomplete) manifest plus sample config/credentials so nothing needs to be
	 * saved first.
	 */
	public function test_request( WP_REST_Request $request ) {
		$body     = $request->get_json_params();
		$manifest = isset( $body['manifest'] ) && is_array( $body['manifest'] ) ? $body['manifest'] : [];
		$template = isset( $body['request'] ) && is_array( $body['request'] ) ? $body['request'] : [];
		$config   = isset( $body['config'] ) && is_array( $body['config'] ) ? $body['config'] : [];
		$creds    = isset( $body['credentials'] ) && is_array( $body['credentials'] ) ? $body['credentials'] : [];

		if ( empty( $template ) ) {
			return new WP_Error( 'bad_request', 'A request definition is required.', [ 'status' => 400 ] );
		}

		$context  = Template::build_context( $config, $creds );
		$built    = RequestBuilder::build( $template, $manifest, $context );
		$response = HttpClient::request( $built['method'], $built['url'], $built['headers'], $built['body'] );

		return rest_ensure_response( [
			'request'  => [ 'method' => $built['method'], 'url' => $built['url'] ],
			'status'   => $response['status'],
			'body'     => $response['body'],
			'headers'  => $response['headers'],
			'error'    => $response['error'],
		] );
	}

	/**
	 * Test a connection's credentials against a saved app's auth.test endpoint.
	 */
	public function test_connection( WP_REST_Request $request ) {
		$slug        = (string) $request['slug'];
		$integration = $this->container ? $this->container->get( 'integrations' )->get( $slug ) : null;

		if ( ! $integration ) {
			return new WP_Error( 'not_found', 'Custom app not found.', [ 'status' => 404 ] );
		}

		$body  = $request->get_json_params();
		$creds = isset( $body['credentials'] ) && is_array( $body['credentials'] ) ? $body['credentials'] : [];

		return rest_ensure_response( $integration::test_connection( $creds ) );
	}

	/* --------------------------------------------------------------------- */

	/**
	 * Validate and persist a manifest, returning it or a WP_Error.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	protected function save( array $manifest ) {
		$errors = ManifestValidator::validate( $manifest, $this->reserved_slugs() );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_manifest', 'The custom app is invalid.', [
				'status' => 422,
				'errors' => $errors,
			] );
		}

		if ( ! ManifestStore::save( $manifest ) ) {
			return new WP_Error( 'save_failed', 'Could not save the custom app.', [ 'status' => 500 ] );
		}

		return rest_ensure_response( $manifest );
	}

	/**
	 * Pull the manifest payload from the request body, tolerating both a bare
	 * manifest object and a { "manifest": {...} } envelope.
	 *
	 * @return array<string,mixed>
	 */
	protected function manifest_from_request( WP_REST_Request $request ): array {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return [];
		}
		if ( isset( $body['manifest'] ) && is_array( $body['manifest'] ) ) {
			return $body['manifest'];
		}
		return $body;
	}

	/**
	 * Slugs already claimed by built-in integrations, which a custom app must not
	 * shadow.
	 *
	 * @return string[]
	 */
	protected function reserved_slugs(): array {
		if ( ! $this->container ) {
			return [];
		}
		$loader = $this->container->get( 'integrations' );

		// The custom apps themselves are in the registry too; exclude the slug
		// being saved so updates don't self-collide.
		return array_values( array_diff( $loader->getAllSlugs(), array_keys( ManifestStore::all() ) ) );
	}
}
