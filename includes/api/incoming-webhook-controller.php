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
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_incoming' ],
					'permission_callback' => '__return_true',
				],
				[
					// Meta (WhatsApp/Messenger) subscription verification handshake.
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'verify_subscription' ],
					'permission_callback' => '__return_true',
				],
				'args' => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		// Generic per-workflow catch-all webhook: /wp-json/zaplane/v1/hook/<workflow_id>
		register_rest_route(
			$this->namespace,
			'/hook/(?P<id>\d+)',
			[
				[
					'methods'             => [ WP_REST_Server::CREATABLE, WP_REST_Server::READABLE ],
					'callback'            => [ $this, 'handle_workflow_hook' ],
					'permission_callback' => '__return_true',
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

	public function handle_incoming( \WP_REST_Request $request ) {
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

	/**
	 * Provider webhook-subscription verification (GET handshake).
	 *
	 * Meta calls the callback URL with hub.mode / hub.verify_token /
	 * hub.challenge and expects the raw challenge echoed back (no JSON wrapping)
	 * on success, otherwise a 403.
	 */
	public function verify_subscription( \WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		$integration = IntegrationLoader::get( $slug );
		if ( ! $integration ) {
			return new WP_Error( 'not_found', 'Integration not found: ' . $slug, [ 'status' => 404 ] );
		}

		if ( ! $integration::supports_webhook() ) {
			return new WP_Error(
				'webhook_not_supported',
				'This integration does not support incoming webhooks.',
				[ 'status' => 400 ]
			);
		}

		$challenge = $integration::verify_webhook_challenge( $request );

		if ( null === $challenge ) {
			return new WP_Error(
				'verification_failed',
				'Webhook verification failed.',
				[ 'status' => 403 ]
			);
		}

		// Meta requires the body to equal the challenge verbatim — echo raw,
		// not JSON-encoded, then stop so WP doesn't wrap the response.
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $challenge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Verbatim echo required by Meta handshake.
		exit;
	}

	/**
	 * Generic catch-all webhook for a single workflow. Verifies the workflow is
	 * active and uses the Webhook trigger, checks an optional secret, then runs
	 * it with the request payload (JSON body + query params).
	 */
	public function handle_workflow_hook( \WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$workflow = \Zaplane\Models\Workflow::find( $id );

		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found.', [ 'status' => 404 ] );
		}

		$version = method_exists( $workflow, 'activeVersion' ) ? $workflow->activeVersion() : null;
		if ( ! $version ) {
			return new WP_Error( 'inactive', 'Workflow has no active version.', [ 'status' => 403 ] );
		}

		$graph   = $version->getGraph();
		$trigger = null;
		foreach ( $graph['nodes'] ?? [] as $n ) {
			if ( ( $n['type'] ?? '' ) === 'trigger' ) {
				$trigger = $n;
				break;
			}
		}

		if ( ! $trigger || ( $trigger['data']['app'] ?? '' ) !== 'webhook' ) {
			return new WP_Error( 'no_webhook', 'This workflow does not use the Webhook trigger.', [ 'status' => 400 ] );
		}

		// Optional shared secret.
		$secret = (string) ( $trigger['data']['config']['secret'] ?? '' );
		if ( '' !== $secret ) {
			$provided = (string) ( $request->get_param( 'secret' ) ?: $request->get_header( 'x_zaplane_secret' ) );
			if ( ! hash_equals( $secret, $provided ) ) {
				return new WP_Error( 'forbidden', 'Invalid or missing secret.', [ 'status' => 403 ] );
			}
		}

		// Build payload: JSON body merged over query params (minus secret).
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = json_decode( (string) $request->get_body(), true );
			$body = is_array( $body ) ? $body : [];
		}
		$query = (array) $request->get_query_params();
		unset( $query['secret'], $query['id'] );
		$payload = array_merge( $query, $body );

		if ( ! function_exists( 'zaplane_run_workflow' ) ) {
			return new WP_Error( 'engine', 'Run engine unavailable.', [ 'status' => 500 ] );
		}

		$run_id = zaplane_run_workflow( $id, $payload );

		return rest_ensure_response( [ 'received' => true, 'run_id' => $run_id ] );
	}

	public function get_webhook_url( \WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		return rest_ensure_response([
			'integration' => $slug,
			'url'         => 'zaplane/v1/incoming/' . $slug,
		]);
	}
}
