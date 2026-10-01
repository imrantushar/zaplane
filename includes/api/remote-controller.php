<?php
namespace Zaplane\API;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;
use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The half of a WordPress connection that lives on the connected site.
 *
 * A site running Zaplane answers four questions for any other Zaplane site:
 * whether Zaplane is there (/remote/info), how to start talking to it
 * (/remote/pair, which hands back a token), how to run a step (/remote/run,
 * which carries that token) and which triggers have fired since the caller
 * last asked (/remote/events, which carries that token and the watch). No API
 * key, no Application Password and no plugin beyond Zaplane itself are
 * involved.
 *
 * A site may be spoken for by several controllers at once. Each controller
 * pairs on its own and holds its own token, its own watch and its own event
 * buffer, so two sites can drive the same site side by side without one
 * disconnecting the other. An admin of this site can drop every connection at
 * any time from the notice Zaplane shows in wp-admin, and a controller can
 * drop just its own.
 */
class RemoteController extends WP_REST_Controller {

	/**
	 * Option holding the current pairings, one entry per controller: which
	 * sites asked, their tokens, when.
	 *
	 * @var string
	 */
	public const OPTION = 'zaplane_remote_pairing';

	/**
	 * Option holding the trigger watch each controller holds: which hooks to
	 * listen on, and the step definition behind each one.
	 *
	 * @var string
	 */
	public const WATCH = 'zaplane_remote_watch';

	/**
	 * Option holding trigger events that have fired but not yet been
	 * collected, per controller, as a monotonic sequence number plus the
	 * resolved payloads.
	 *
	 * @var string
	 */
	public const BUFFER = 'zaplane_remote_events';

	/**
	 * The most events kept for a controller that has stopped collecting. Old
	 * ones fall off the front rather than growing this option without limit.
	 *
	 * @var int
	 */
	protected const BUFFER_MAX = 200;

	/**
	 * Hooks registered on this request out of the watch option, so a refreshed
	 * watch replaces them instead of stacking a second copy.
	 *
	 * @var array<int,string>
	 */
	protected static array $registered = [];

	public function __construct() {
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'remote';
	}

	/**
	 * Listen for the triggers a paired controller asked for, on every request
	 * that reaches `init`.
	 */
	public static function boot(): void {
		if ( did_action( 'init' ) ) {
			self::refresh_watch();
			return;
		}

		add_action( 'init', [ self::class, 'refresh_watch' ], 1 );
	}

	/**
	 * Declare the five routes every Zaplane site opens for its siblings.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/info',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_info' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/pair',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'pair' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'controller' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/run',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run' ],
				'permission_callback' => [ $this, 'check_token' ],
				'args'                => [
					'token'  => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'app'    => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'event'  => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'config' => [
						'type' => 'array',
					],
					'input'  => [
						'type' => 'array',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/events',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'events' ],
				'permission_callback' => [ $this, 'check_token' ],
				'args'                => [
					'token' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'specs' => [
						'type' => 'array',
					],
					'since' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unpair',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'unpair' ],
				'permission_callback' => [ $this, 'check_unpair_access' ],
				'args'                => [
					'token' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Say that Zaplane is installed here, whether any controller is paired,
	 * and which sites hold the connections.
	 */
	public function get_info(): WP_REST_Response {
		$controllers = array_keys( self::pairings() );

		return new WP_REST_Response(
			[
				'zaplane'         => true,
				'site'            => (string) get_option( 'blogname', '' ),
				'url'             => home_url(),
				'version'         => self::version(),
				'paired'          => array() !== $controllers,
				'controllers'     => $controllers,
				'pairing_allowed' => (bool) apply_filters( 'zaplane_allow_remote_pairing', true ),
			],
			200
		);
	}

	/**
	 * Hand the calling site the token every later step will carry.
	 *
	 * Several controllers may hold a connection to this site at the same time;
	 * each gets its own token, watch and buffer. The same controller asking
	 * again is answered with a fresh token, which is how a site that lost its
	 * copy reconnects without anyone touching this one.
	 *
	 * @param \WP_REST_Request $request Request carrying the calling site's URL.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function pair( $request ) {
		$controller = esc_url_raw( trim( (string) $request->get_param( 'controller' ) ) );

		if ( '' === $controller ) {
			return new WP_Error(
				'zaplane_missing_controller',
				'The calling site did not say which site it is.',
				[ 'status' => 400 ]
			);
		}

		if ( ! apply_filters( 'zaplane_allow_remote_pairing', true ) ) {
			return new WP_Error(
				'zaplane_pairing_disabled',
				'This site is not accepting connections from other Zaplane sites.',
				[ 'status' => 403 ]
			);
		}

		$token                   = wp_generate_password( 32, false, false );
		$pairings                = self::pairings();
		$pairings[ $controller ] = [
			'controller' => $controller,
			'token'      => $token,
			'created'    => time(),
		];

		update_option( self::OPTION, $pairings, false );

		return new WP_REST_Response(
			[
				'token'   => $token,
				'site'    => (string) get_option( 'blogname', '' ),
				'url'     => home_url(),
				'version' => self::version(),
			],
			200
		);
	}

	/**
	 * Run one step locally, as the site's administrator, and answer with what
	 * the step answered.
	 *
	 * @param \WP_REST_Request $request Request carrying the event, its config
	 *                                  and the payload arriving from the step
	 *                                  before it.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run( $request ) {
		$event = sanitize_key( (string) $request->get_param( 'event' ) );
		$app   = sanitize_key( (string) $request->get_param( 'app' ) );

		if ( '' === $app ) {
			$app = 'wordpress';
		}

		if ( ! in_array( $app, self::runnable_apps(), true ) ) {
			return new WP_Error(
				'zaplane_app_not_allowed',
				sprintf( 'A paired site may not run steps for "%s".', $app ),
				[ 'status' => 400 ]
			);
		}

		$integration = IntegrationLoader::get( $app );

		if ( ! $integration ) {
			return new WP_Error(
				'zaplane_unknown_app',
				sprintf( 'No "%s" integration is installed on this site.', $app ),
				[ 'status' => 404 ]
			);
		}

		if ( '' === $event || ! method_exists( $integration, 'action_' . $event ) ) {
			return new WP_Error(
				'zaplane_unknown_step',
				sprintf(
					'The Zaplane on this site (%s) has no "%s" step. Update Zaplane here, or on the site calling it.',
					self::version(),
					$event
				),
				[ 'status' => 404 ]
			);
		}

		$config = $request->get_param( 'config' );
		$input  = $request->get_param( 'input' );

		$config = is_array( $config ) ? $config : [];
		$input  = is_array( $input ) ? $input : [];

		$previous = get_current_user_id();
		$actor    = self::actor_id();

		if ( $actor > 0 ) {
			wp_set_current_user( $actor );
		}

		try {
			$result = $integration::execute_node(
				[
					'type' => 'action',
					'data' => [
						'app'           => $app,
						'event'         => $event,
						'config'        => $config,
						'connection_id' => 0,
					],
				],
				$input
			);
		} catch ( \Throwable $e ) {
			$result = [
				'port' => 'error',
				'data' => [ 'error' => $e->getMessage() ],
			];
		}

		if ( $actor > 0 ) {
			wp_set_current_user( $previous );
		}

		if ( ! is_array( $result ) || ! isset( $result['port'] ) || ! isset( $result['data'] ) ) {
			return new WP_Error(
				'zaplane_step_failed',
				'The step on this site did not answer with a result.',
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Drop a pairing.
	 *
	 * A controller that carries its token drops only its own connection; an
	 * admin of this site drops every connection at once.
	 *
	 * @param \WP_REST_Request $request Request carrying the token, if any.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function unpair( $request ) {
		$token = trim( (string) $request->get_param( 'token' ) );

		if ( '' !== $token ) {
			$controller = self::controller_for_token( $token );

			if ( '' === $controller ) {
				return new WP_Error(
					'zaplane_not_paired',
					'This site is not paired with the site that called it.',
					[ 'status' => 403 ]
				);
			}

			self::forget_pairing( $controller );

			return new WP_REST_Response( [ 'paired' => array() !== self::pairings() ], 200 );
		}

		self::clear_pairing();

		return new WP_REST_Response( [ 'paired' => false ], 200 );
	}

	/**
	 * Take the triggers this controller wants to watch, and hand back every
	 * one that has fired since it last asked.
	 *
	 * The two halves share one call so a controller that has just saved a
	 * workflow is in sync before its next collection. Each controller's watch
	 * and buffer are its own, so what fires for one is never handed to
	 * another.
	 *
	 * @param \WP_REST_Request $request Request carrying the watch and the last
	 *                                  sequence number collected.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function events( $request ) {
		$controller = self::controller_for_token( (string) $request->get_param( 'token' ) );

		if ( '' === $controller ) {
			return new WP_Error(
				'zaplane_not_paired',
				'This site is not paired with the site that called it.',
				[ 'status' => 403 ]
			);
		}

		$specs = $request->get_param( 'specs' );

		if ( null !== $specs ) {
			self::store_specs( is_array( $specs ) ? $specs : [], $controller );
		}

		$since  = (int) $request->get_param( 'since' );
		$buffer = self::buffers()[ $controller ] ?? [];
		$buffer = is_array( $buffer ) ? $buffer : [];

		$events = [];
		$stored = is_array( $buffer['events'] ?? null ) ? $buffer['events'] : [];

		foreach ( $stored as $event ) {
			if ( ! is_array( $event ) || (int) ( $event['seq'] ?? 0 ) <= $since ) {
				continue;
			}

			$events[] = $event;
		}

		return new WP_REST_Response(
			[
				'seq'    => (int) ( $buffer['seq'] ?? 0 ),
				'events' => array_slice( $events, 0, 100 ),
			],
			200
		);
	}

	/**
	 * Register the hooks the controllers asked for, on this request.
	 *
	 * Called from `init` so a watch survives between requests. Every
	 * controller's watch is merged, since the same hook may serve more than
	 * one of them.
	 */
	public static function refresh_watch(): void {
		foreach ( self::$registered as $hook ) {
			remove_action( $hook, [ self::class, 'capture' ], 10 );
		}

		self::$registered = [];

		$hooks = [];

		foreach ( self::watches() as $watch ) {
			if ( ! is_array( $watch ) ) {
				continue;
			}

			foreach ( (array) ( $watch['hooks'] ?? [] ) as $hook ) {
				$hook = (string) $hook;

				if ( '' === $hook ) {
					continue;
				}

				$hooks[ $hook ] = true;
			}
		}

		foreach ( array_keys( $hooks ) as $hook ) {
			add_action( $hook, [ self::class, 'capture' ], 10, 99 );
			self::$registered[] = $hook;
		}
	}

	/**
	 * Turn one fire of a watched hook into a payload the controller can run a
	 * workflow from.
	 */
	public static function capture(): void {
		self::capture_hook( (string) current_filter(), func_get_args() );
	}

	/**
	 * The body of capture(), with the hook named outright.
	 *
	 * The payload is resolved here, with this site's own objects and the step
	 * definition the controller sent, so nothing but the plain array crosses
	 * the wire. Each resolved payload is queued for the controller whose
	 * watch it belongs to.
	 *
	 * @param string           $hook WordPress hook that fired.
	 * @param array<int,mixed> $args Arguments WordPress passed to it.
	 */
	public static function capture_hook( string $hook, array $args ): void {
		foreach ( self::watches() as $controller => $watch ) {
			if ( ! is_array( $watch ) ) {
				continue;
			}

			foreach ( (array) ( $watch['specs'] ?? [] ) as $spec ) {
				if ( ! is_array( $spec ) || (string) ( $spec['hook'] ?? '' ) !== $hook ) {
					continue;
				}

				$app = sanitize_key( (string) ( $spec['app'] ?? '' ) );

				if ( ! in_array( $app, self::runnable_apps(), true ) ) {
					continue;
				}

				$integration = IntegrationLoader::get( $app );

				if ( ! $integration || ! method_exists( $integration, 'resolve_trigger' ) ) {
					continue;
				}

				try {
					$payload = $integration::resolve_trigger(
						[
							'app'    => $app,
							'event'  => (string) ( $spec['event'] ?? '' ),
							'hook'   => $hook,
							'config' => is_array( $spec['config'] ?? null ) ? $spec['config'] : [],
						],
						$args
					);
				} catch ( \Throwable $e ) {
					// One step whose definition this site cannot resolve must
					// not stop the rest of the watch, or the hook it fired on.
					continue;
				}

				if ( ! is_array( $payload ) || array() === $payload ) {
					continue;
				}

				self::append_event( (string) $controller, $app, (string) ( $spec['event'] ?? '' ), $hook, $payload );
			}//end foreach
		}//end foreach
	}

	/**
	 * Store the watch one controller sent, replacing whatever it held before.
	 *
	 * An empty watch releases the controller's entry — it is no longer
	 * listening for anything.
	 *
	 * @param array<int,mixed> $specs      Steps to watch, one per trigger.
	 * @param string           $controller The site asking.
	 */
	private static function store_specs( array $specs, string $controller ): void {
		$clean = [];
		$hooks = [];

		foreach ( $specs as $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}

			$app   = sanitize_key( (string) ( $spec['app'] ?? '' ) );
			$event = sanitize_key( (string) ( $spec['event'] ?? '' ) );
			$hook  = sanitize_text_field( (string) ( $spec['hook'] ?? '' ) );

			if ( '' === $app || '' === $event || '' === $hook ) {
				continue;
			}

			if ( ! in_array( $app, self::runnable_apps(), true ) ) {
				continue;
			}

			$clean[] = [
				'app'    => $app,
				'event'  => $event,
				'hook'   => $hook,
				'config' => is_array( $spec['config'] ?? null ) ? $spec['config'] : [],
			];

			$hooks[] = $hook;
		}//end foreach

		$watches = self::watches();

		if ( array() === $clean ) {
			unset( $watches[ $controller ] );
		} else {
			$watches[ $controller ] = [
				'specs' => $clean,
				'hooks' => array_values( array_unique( $hooks ) ),
			];
		}

		update_option( self::WATCH, $watches, false );

		self::refresh_watch();
	}

	/**
	 * Queue one resolved payload for the controller to collect.
	 *
	 * @param string              $controller Site the payload belongs to.
	 * @param string              $app        Integration the step belongs to.
	 * @param string              $event      Trigger event that fired.
	 * @param string              $hook       WordPress hook it fired on.
	 * @param array<string,mixed> $payload    Resolved trigger payload.
	 */
	private static function append_event( string $controller, string $app, string $event, string $hook, array $payload ): void {
		$buffers = self::buffers();
		$buffer  = is_array( $buffers[ $controller ] ?? null ) ? $buffers[ $controller ] : [];

		$seq    = (int) ( $buffer['seq'] ?? 0 ) + 1;
		$events = is_array( $buffer['events'] ?? null ) ? $buffer['events'] : [];

		$events[] = [
			'seq'     => $seq,
			'app'     => $app,
			'event'   => $event,
			'hook'    => $hook,
			'payload' => $payload,
		];

		$buffers[ $controller ] = [
			'seq'    => $seq,
			'events' => array_slice( $events, -self::BUFFER_MAX ),
		];

		update_option( self::BUFFER, $buffers, false );
	}

	/**
	 * Forget one controller's pairing, its watch and everything it has not
	 * collected yet.
	 *
	 * @param string $controller Site to release.
	 */
	private static function forget_pairing( string $controller ): void {
		$pairings = self::pairings();
		$watches  = self::watches();
		$buffers  = self::buffers();

		unset(
			$pairings[ $controller ],
			$watches[ $controller ],
			$buffers[ $controller ]
		);

		update_option( self::OPTION, $pairings, false );
		update_option( self::WATCH, $watches, false );
		update_option( self::BUFFER, $buffers, false );

		self::refresh_watch();
	}

	/**
	 * Forget every pairing, every watch and everything waiting to be
	 * collected.
	 */
	private static function clear_pairing(): void {
		delete_option( self::OPTION );
		delete_option( self::WATCH );
		delete_option( self::BUFFER );
		self::refresh_watch();
	}

	/**
	 * The token, and only a token this site handed out, opens /remote/run and
	 * /remote/events. Any of the controllers' tokens works.
	 *
	 * @param \WP_REST_Request $request Request carrying the token.
	 * @return true|\WP_Error
	 */
	public function check_token( $request ) {
		if ( '' !== self::controller_for_token( (string) $request->get_param( 'token' ) ) ) {
			return true;
		}

		return new WP_Error(
			'zaplane_not_paired',
			'This site is not paired with the site that called it.',
			[ 'status' => 403 ]
		);
	}

	/**
	 * Dropping a pairing needs either the token that holds it — which drops
	 * only that connection — or an admin of this site, who may drop them all.
	 *
	 * @param \WP_REST_Request $request Request carrying the token.
	 * @return true|\WP_Error
	 */
	public function check_unpair_access( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return $this->check_token( $request );
	}

	/**
	 * Tell this site's admins which Zaplane sites run steps here, and give
	 * them the one link that stops them all.
	 */
	public static function admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$controllers = array_keys( self::pairings() );

		if ( array() === $controllers ) {
			return;
		}

		$revoke = wp_nonce_url(
			add_query_arg( 'zaplane_revoke_remote', '1', admin_url( 'admin.php?page=zaplane' ) ),
			'zaplane_revoke_remote'
		);

		printf(
			'<div class="notice notice-info is-dismissible"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Zaplane remote connection:', 'zaplane' ),
			esc_html(
				sprintf(
					/* translators: %s: comma-separated URLs of the sites holding connections. */
					__( '%s connected to this site and can run WordPress steps here.', 'zaplane' ),
					implode( ', ', $controllers )
				)
			),
			esc_url( $revoke ),
			esc_html__( 'Disconnect', 'zaplane' )
		);
	}

	/**
	 * Honor the Disconnect link above when an admin follows it.
	 */
	public static function handle_revoke(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only clears the pairings, and the nonce is checked below.
		if ( ! isset( $_GET['zaplane_revoke_remote'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked two lines down; the GET key only selects this branch.
		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['zaplane_revoke_remote'] ) );

		if ( ! wp_verify_nonce( $nonce, 'zaplane_revoke_remote' ) ) {
			return;
		}

		self::clear_pairing();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nothing but the pairings were read from the request.
		$back = remove_query_arg( 'zaplane_revoke_remote', admin_url( 'admin.php?page=zaplane' ) );

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Every pairing this site holds, keyed by the controller's site URL.
	 *
	 * A pairing stored by the one-controller design is read through as a
	 * single-entry list, so an older install upgrades in place.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function pairings(): array {
		$stored = get_option( self::OPTION, [] );

		if ( ! is_array( $stored ) || array() === $stored ) {
			return [];
		}

		if ( isset( $stored['controller'] ) ) {
			$url    = trim( (string) $stored['controller'] );
			$stored = '' !== $url
				? [ $url => [ 'controller' => $url, 'token' => (string) ( $stored['token'] ?? '' ), 'created' => (int) ( $stored['created'] ?? 0 ) ] ]
				: [];
		}

		$pairings = [];

		foreach ( $stored as $pairing ) {
			if ( ! is_array( $pairing ) ) {
				continue;
			}

			$url = trim( (string) ( $pairing['controller'] ?? '' ) );

			if ( '' === $url ) {
				continue;
			}

			$pairings[ $url ] = $pairing;
		}

		return $pairings;
	}

	/**
	 * Every controller's watch, keyed by the controller's site URL.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function watches(): array {
		$stored = get_option( self::WATCH, [] );

		// A watch stored by the one-controller design names no controller and
		// its events belong to no one now; it is dropped.
		if ( ! is_array( $stored ) || isset( $stored['specs'] ) ) {
			return [];
		}

		return $stored;
	}

	/**
	 * Every controller's event buffer, keyed by the controller's site URL.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function buffers(): array {
		$stored = get_option( self::BUFFER, [] );

		if ( ! is_array( $stored ) || isset( $stored['seq'] ) ) {
			return [];
		}

		return $stored;
	}

	/**
	 * The controller a token belongs to, or an empty string when the token is
	 * none this site handed out.
	 *
	 * @param string $token Token presented by the caller.
	 */
	private static function controller_for_token( string $token ): string {
		$token = trim( $token );

		if ( '' === $token ) {
			return '';
		}

		foreach ( self::pairings() as $url => $pairing ) {
			$stored = trim( (string) ( $pairing['token'] ?? '' ) );

			if ( '' !== $stored && hash_equals( $stored, $token ) ) {
				return (string) $url;
			}
		}

		return '';
	}

	/**
	 * Integrations a paired site is allowed to drive.
	 *
	 * @return array<int,string>
	 */
	private static function runnable_apps(): array {
		$apps = apply_filters( 'zaplane_remote_run_apps', [ 'wordpress' ] );

		if ( ! is_array( $apps ) ) {
			return [ 'wordpress' ];
		}

		$allowed = [];

		foreach ( $apps as $app ) {
			if ( is_string( $app ) ) {
				$allowed[] = sanitize_key( $app );
			}
		}

		return $allowed;
	}

	/**
	 * The administrator this site runs a paired step as.
	 *
	 * The caller proves nothing about itself beyond the token, so the step
	 * runs with this site's own most recent administrator rather than as an
	 * unauthenticated visitor, which is how the same step behaves locally.
	 */
	private static function actor_id(): int {
		$found = get_users(
			[
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'DESC',
				'fields'  => 'ID',
			]
		);

		if ( ! is_array( $found ) ) {
			return 0;
		}

		foreach ( $found as $candidate ) {
			$id = is_object( $candidate ) ? (int) ( $candidate->ID ?? 0 ) : (int) $candidate;

			if ( $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * Zaplane's own version, for messages sent to a site on the other end.
	 */
	private static function version(): string {
		return defined( 'ZAPLANE_VERSION' ) ? (string) ZAPLANE_VERSION : '';
	}
}
