<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IncomingWebhookController extends WP_REST_Controller {


	protected Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'incoming';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_incoming' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9_-]+)/url',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_webhook_url' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	public function handle_incoming( \WP_REST_Request $request ): mixed {
		$slug = $request->get_param( 'slug' );

		$integration = IntegrationLoader::get( $slug );
		if ( ! $integration ) {
			return new WP_Error(
				'not_found',
				'Integration not found: ' . $slug,
				[ 'status' => 404 ]
			);
		}

		if ( ! $integration::supports_webhook() ) {
			return new WP_Error(
				'webhook_not_supported',
				'This integration does not support incoming webhooks.',
				[ 'status' => 400 ]
			);
		}

		if ( ! $integration::verify_webhook_signature( $request ) ) {
			return new WP_Error(
				'invalid_signature',
				'Webhook signature verification failed.',
				[ 'status' => 401 ]
			);
		}

		$parsed = $integration::parse_webhook_event( $request );

		if ( null === $parsed ) {
			return rest_ensure_response([
				'received' => true,
				'action'   => 'skipped',
			]);
		}

		$event   = $parsed['event'] ?? '';
		$payload = $parsed['payload'] ?? [];

		$triggers = $integration::get_triggers();
		if ( empty( $triggers[ $event ]['hook'] ) ) {
			return new WP_Error(
				'unknown_event',
				'Unknown event type: ' . $event,
				[ 'status' => 400 ]
			);
		}

		$hook = $triggers[ $event ]['hook'];

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook dispatched from integration config.
		do_action( $hook, $payload );

		return rest_ensure_response([
			'received' => true,
			'action'   => 'triggered',
			'event'    => $event,
		]);
	}

	public function get_webhook_url( \WP_REST_Request $request ): mixed {
		$slug = $request->get_param( 'slug' );

		return rest_ensure_response([
			'integration' => $slug,
			'url'         => rest_url( 'zaplane/v1/incoming/' . $slug ),
		]);
	}
}
