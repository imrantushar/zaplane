<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\Query;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Classes\TriggerNodes;

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

		// One Catch Webhook trigger, for a workflow that can have several:
		// /wp-json/zaplane/v1/hook/<workflow_id>/<trigger_node_id>
		register_rest_route(
			$this->namespace,
			'/hook/(?P<id>\d+)/(?P<node>\d+)',
			[
				[
					'methods'             => [ WP_REST_Server::CREATABLE, WP_REST_Server::READABLE ],
					'callback'            => [ $this, 'handle_workflow_hook' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		$slug_arg = [
			'slug' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
		];

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9_-]+)/url',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_webhook_url' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
				'args'                => $slug_arg,
			]
		);

		// Webhook Setup panel: the callback URL plus the secrets the provider
		// handshake needs. Admin-only — these are credentials.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-z0-9_-]+)/config',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_webhook_config' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_webhook_config' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
				],
				'args' => $slug_arg,
			]
		);
	}

	public function admin_permissions_check(): bool {
		return current_user_can( 'manage_options' );
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

		// Some providers verify the callback URL over this same POST endpoint and
		// require their challenge echoed back verbatim (Slack's url_verification).
		// That has to happen before event parsing, and the body must not be wrapped
		// in the usual JSON envelope.
		$handshake = $integration::handle_webhook_handshake( $request );
		if ( null !== $handshake ) {
			$this->send_raw( (string) ( $handshake['body'] ?? '' ), (string) ( $handshake['content_type'] ?? 'text/plain' ) );
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

		// REST requests can reach this endpoint before the normal init hook has
		// registered the active workflow listeners. Refresh the map and register
		// them here so provider webhooks cannot be accepted without starting runs.
		Query::flush_trigger_map();
		$this->container->get( 'automation' )->dispatch_active_triggers();

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
		$this->send_raw( $challenge, 'text/plain' );
	}

	/**
	 * Echo a provider handshake response verbatim and stop.
	 *
	 * Providers compare the body byte-for-byte against the challenge they sent,
	 * so it must not go through WP's REST JSON envelope.
	 */
	private function send_raw( string $body, string $content_type = 'text/plain' ): void {
		// Echoed verbatim, so it is never offered to the browser as markup.
		$content_type = 'application/json' === $content_type ? 'application/json' : 'text/plain';

		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $content_type . '; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Cache-Control: no-store' );
			status_header( 200 );
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Verbatim echo required by the provider handshake.
		exit;
	}

	/**
	 * Generic catch-all webhook for a single workflow. Finds its Catch Webhook
	 * trigger (the one in the URL, or the first one when the URL names none),
	 * checks that trigger's optional secret, then runs the workflow from that
	 * trigger with the request payload (JSON body + query params).
	 */
	public function handle_workflow_hook( \WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$workflow = \Zaplane\Models\Workflow::find( $id );

		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found.', [ 'status' => 404 ] );
		}

		// A paused or draft workflow still has an active version, so that alone does
		// not say its owner wants anyone able to start it from outside.
		if ( ! $workflow->isActive() ) {
			return new WP_Error( 'inactive', 'Workflow is not active.', [ 'status' => 403 ] );
		}

		$version = method_exists( $workflow, 'activeVersion' ) ? $workflow->activeVersion() : null;
		if ( ! $version ) {
			return new WP_Error( 'inactive', 'Workflow has no active version.', [ 'status' => 403 ] );
		}

		$graph   = $version->getGraph();
		$hooks   = TriggerNodes::of_app( $graph, 'webhook' );
		$node_id = (string) ( $request->get_param( 'node' ) ?? '' );
		$trigger = '' === $node_id ? ( $hooks[0] ?? null ) : null;

		foreach ( '' === $node_id ? [] : $hooks as $hook ) {
			if ( (string) $hook['id'] === $node_id ) {
				$trigger = $hook;
				break;
			}
		}

		if ( ! $trigger ) {
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
		unset( $query['secret'], $query['id'], $query['node'], $query['rest_route'] );
		$payload = array_merge( $query, $body );

		if ( ! function_exists( 'zaplane_run_workflow' ) ) {
			return new WP_Error( 'engine', 'Run engine unavailable.', [ 'status' => 500 ] );
		}

		$run_id = zaplane_run_workflow( $id, $payload, (string) $trigger['id'] );

		return rest_ensure_response( [
			'received' => true,
			'run_id' => $run_id
		] );
	}

	public function get_webhook_url( \WP_REST_Request $request ) {
		$slug        = $request->get_param( 'slug' );
		$integration = IntegrationLoader::get( $slug );

		if ( ! $integration ) {
			return new WP_Error( 'not_found', 'Integration not found: ' . $slug, [ 'status' => 404 ] );
		}

		return rest_ensure_response([
			'integration' => $slug,
			'url'         => $integration::get_webhook_url(),
		]);
	}

	/**
	 * The Webhook Setup panel's payload: where the provider should POST, and the
	 * secrets this integration needs before it will accept anything.
	 *
	 * Secret values are never sent back — only whether each one is set — so the
	 * panel can show "configured" without leaking the value to the browser.
	 */
	public function get_webhook_config( \WP_REST_Request $request ) {
		$slug        = $request->get_param( 'slug' );
		$integration = IntegrationLoader::get( $slug );

		if ( ! $integration ) {
			return new WP_Error( 'not_found', 'Integration not found: ' . $slug, [ 'status' => 404 ] );
		}

		$fields = $integration::get_webhook_setup_fields();
		$status = [];

		foreach ( $fields as $field ) {
			$key = $field['key'] ?? '';
			if ( '' === $key ) {
				continue;
			}
			$status[ $key ] = '' !== $integration::get_webhook_setting( $key );
		}

		return rest_ensure_response([
			'integration'      => $slug,
			'supports_webhook' => $integration::supports_webhook(),
			'url'              => $integration::supports_webhook() ? $integration::get_webhook_url() : '',
			'fields'           => array_values( $fields ),
			'configured'       => $status,
		]);
	}

	/**
	 * Save the Webhook Setup panel's values. Only keys the integration declares
	 * are accepted; an empty submitted value clears that key.
	 */
	public function update_webhook_config( \WP_REST_Request $request ) {
		$slug        = $request->get_param( 'slug' );
		$integration = IntegrationLoader::get( $slug );

		if ( ! $integration ) {
			return new WP_Error( 'not_found', 'Integration not found: ' . $slug, [ 'status' => 404 ] );
		}

		$submitted = $request->get_json_params();
		if ( ! is_array( $submitted ) ) {
			$submitted = (array) $request->get_param( 'values' );
		}

		$allowed = [];
		foreach ( $integration::get_webhook_setup_fields() as $field ) {
			if ( ! empty( $field['key'] ) ) {
				$allowed[] = (string) $field['key'];
			}
		}

		$config = get_option( 'zaplane_webhook_config', [] );
		$config = is_array( $config ) ? $config : [];
		$bucket = isset( $config[ $slug ] ) && is_array( $config[ $slug ] ) ? $config[ $slug ] : [];

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $submitted ) ) {
				continue;
			}
			$value = is_scalar( $submitted[ $key ] ) ? trim( (string) $submitted[ $key ] ) : '';
			if ( '' === $value ) {
				unset( $bucket[ $key ] );
			} else {
				$bucket[ $key ] = $value;
			}
		}

		if ( empty( $bucket ) ) {
			unset( $config[ $slug ] );
		} else {
			$config[ $slug ] = $bucket;
		}

		update_option( 'zaplane_webhook_config', $config, false );

		return $this->get_webhook_config( $request );
	}
}
