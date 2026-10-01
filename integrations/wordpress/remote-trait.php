<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs WordPress steps on another site, whichever way that site answers.
 *
 * A connection is a Site URL, and nothing else if the site it points at runs
 * Zaplane too: the two installations pair over Zaplane's own routes and this
 * site never has to know a username. When the other site runs no Zaplane, the
 * connection falls back to WordPress's core REST API, which is what the
 * optional username and Application Password are for.
 *
 * Every REST handler below mirrors the behaviour of its local counterpart in
 * the *-actions-trait files: same configuration keys in, same payload keys
 * out, so the rest of a workflow cannot tell which site a step ran on. The
 * paired path needs no mirror of its own — the other site runs the same code.
 */
trait RemoteTrait {

	/**
	 * Credentials of the connection the step being executed points at.
	 *
	 * Held for the duration of one remote_execute() call so the handlers below
	 * can reach them without every call carrying an extra argument.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $remote_credentials = null;

	/**
	 * Cached GET /wp/v2/types payload of the connected site.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $remote_types = null;

	/**
	 * Cached GET /wp/v2/taxonomies payload of the connected site.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $remote_taxonomies = null;

	/**
	 * A step runs on this site by default. When a node points at a
	 * connection, it runs on the connected site instead, so a connection is
	 * offered in the editor but never demanded.
	 */
	public static function requires_connection(): bool {
		return false;
	}

	public static function connection_optional(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'api_key';
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature fixed by IntegrationBase.
	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'site_url'  => [
				'type'        => 'text',
				'label'       => 'Site URL',
				'placeholder' => 'https://example.com',
				'required'    => true,
				'help'        => 'The WordPress site these steps should run on, local or live. https:// and no trailing slash. Install Zaplane there and this is the only field needed.',
			],
			'username'  => [
				'type'        => 'text',
				'label'       => 'Username',
				'placeholder' => 'admin',
				'required'    => false,
				'help'        => 'Only needed when the connected site does not run Zaplane. Leave it out and Zaplane will connect on its own.',
			],
			'password'  => [
				'type'        => 'password',
				'label'       => 'Application Password',
				'placeholder' => 'xxxx xxxx xxxx xxxx xxxx xxxx',
				'required'    => false,
				'help'        => 'Only needed without Zaplane on the other site: Users -> Profile -> Application Passwords there. WordPress refuses a normal login password on its REST API.',
			],
		];
	}

	/**
	 * Decide how to talk to the site, then prove that way works.
	 *
	 * Zaplane on the other site comes first and needs nothing typed but the
	 * URL: /remote/info shows it is installed, /remote/pair mints the token
	 * every later step carries. Only a site running no Zaplane is read over
	 * the core REST API, which is what the username and Application Password
	 * are for — without them there is nothing left to try.
	 *
	 * @param array<string,mixed> $credentials Decrypted connection credentials.
	 * @return array{success:bool,message:string,details:array<string,mixed>}
	 */
	public static function test_connection( array $credentials ): array {
		$site = self::remote_site_url( $credentials );

		if ( '' === $site ) {
			return [
				'success' => false,
				'message' => 'A Site URL is required.',
				'details' => [],
			];
		}

		$probe = self::remote_probe( $credentials );

		if ( ! $probe['reachable'] ) {
			return [
				'success' => false,
				'message' => 'The site could not be reached: ' . $probe['error'],
				'details' => [],
			];
		}

		if ( $probe['zaplane'] ) {
			return self::remote_pair( $credentials, $probe['info'] );
		}

		if ( ! self::remote_has_auth( $credentials ) ) {
			return [
				'success' => false,
				'message' => 'Nothing at that URL says Zaplane is installed, and no username and Application Password were given to fall back on. Install Zaplane there, or add the two fields below.',
				'details' => [],
			];
		}

		$username = trim( (string) $credentials['username'] );

		try {
			[ $me, $status ] = self::remote_request( $credentials, 'GET', 'wp/v2/users/me', null, [ 'context' => 'edit' ], [], true );
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => 'The site could not be reached: ' . $e->getMessage(),
				'details' => [],
			];
		}

		if ( 200 !== $status || ! is_array( $me ) ) {
			if ( 401 === $status || 403 === $status ) {
				return [
					'success' => false,
					'message' => 'WordPress rejected the username or Application Password. Create one on the connected site under Users -> Profile -> Application Passwords; a normal login password is refused on the REST API. Zaplane was also not found at that URL, so the site was not auto-connected either — install Zaplane there to connect with a Site URL alone.',
					'details' => [],
				];
			}

			return [
				'success' => false,
				'message' => 'The credentials were refused with HTTP ' . (int) $status . '.',
				'details' => [],
			];
		}

		self::remote_remember( $site, [
			'mode' => 'rest',
			'token' => ''
		] );

		$display = (string) ( $me['name'] ?? '' );

		return [
			'success' => true,
			'message' => 'Connected to ' . $site . ' as ' . ( '' !== $display ? $display : $username ) . ' over WordPress\'s REST API.',
			'details' => [
				'site'    => (string) ( $me['name'] ?? '' ),
				'url'     => $site,
				'user'    => (string) ( $me['username'] ?? $username ),
				'roles'   => (array) ( $me['roles'] ?? [] ),
				'mode'    => 'rest',
			],
		];
	}

	/**
	 * Run one step against the connected site, whichever way it answers.
	 *
	 * A site holding a pairing token runs the step on its own copy of Zaplane,
	 * which covers every action this integration has and needs nothing but the
	 * URL. A site running no Zaplane falls through to the REST handlers below,
	 * which cover only what WordPress exposes a route for — and a connection
	 * with nothing to fall back on says so rather than running the step here.
	 *
	 * @param string              $event       Step event, e.g. create_post.
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Decrypted connection credentials.
	 * @param array<string,mixed> $input       Payload arriving from the previous step.
	 * @return array<string,mixed>
	 */
	public static function remote_execute( string $event, array $config, array $credentials, array $input = [] ): array {
		$site = self::remote_site_url( $credentials );

		if ( 'rest' !== self::remote_mode( $credentials ) ) {
			$paired = self::remote_zaplane_execute( $event, $config, $credentials, $input );

			if ( null !== $paired ) {
				return $paired;
			}

			if ( ! self::remote_has_auth( $credentials ) ) {
				return static::error(
					'Zaplane stopped answering at ' . $site . ', and this connection holds no username and Application Password to fall back on. Reconnect the site, or add those two fields.'
				);
			}

			self::remote_remember( $site, [ 'mode' => 'rest' ] );
		}

		self::$remote_credentials = $credentials;
		self::$remote_types       = null;
		self::$remote_taxonomies  = null;

		// Several handlers serve more than one event and need to know which
		// one they were called for to answer in the right shape.
		$config['__event'] = $event;

		try {
			$handlers = self::remote_handlers();
			$method   = $handlers[ $event ] ?? '';

			if ( '' === $method || ! method_exists( static::class, $method ) ) {
				$result = static::error(
					sprintf(
						'The "%s" step has no WordPress REST route, so it cannot run on a site that does not run Zaplane. Install Zaplane there, or clear the connection on this step to run it on this site instead.',
						$event
					)
				);
			} else {
				$result = static::$method( $config, $credentials );
			}
		} catch ( \Exception $e ) {
			$result = static::error( $e->getMessage() );
		}

		self::$remote_credentials = null;
		self::$remote_types       = null;
		self::$remote_taxonomies  = null;

		return $result;
	}

	/**
	 * Step event => handler method on this trait.
	 *
	 * Events left out have no REST route in WordPress core, which is what makes
	 * remote_execute() report them as unsupported rather than fail silently.
	 *
	 * @return array<string,string>
	 */
	private static function remote_handlers(): array {
		return [
			// Posts, pages and custom post types.
			'create_post'                   => 'remote_create_post',
			'update_post'                   => 'remote_update_post',
			'update_title'                  => 'remote_update_post',
			'update_status'                 => 'remote_update_post',
			'change_post_author'            => 'remote_update_post',
			'schedule_post'                 => 'remote_schedule_post',
			'unschedule_post'               => 'remote_schedule_post',
			'set_featured_image'            => 'remote_set_featured_image',
			'update_post_feature_image'     => 'remote_set_featured_image',
			'trash_post'                    => 'remote_trash_post',
			'restore_post'                  => 'remote_restore_post',
			'untrash_post'                  => 'remote_restore_post',
			'delete_post'                   => 'remote_delete_post',
			'delete_trash_post'             => 'remote_delete_post',
			'duplicate_post'                => 'remote_duplicate_post',
			'get_posts_all'                 => 'remote_get_posts',
			'get_posts_by_post_type'        => 'remote_get_posts',
			'get_posts_by_metadata'         => 'remote_get_posts_by_metadata',
			'get_post_single'               => 'remote_get_post_single',
			'get_post_content'              => 'remote_get_post_field',
			'get_post_excerpt'              => 'remote_get_post_field',
			'get_post_status'               => 'remote_get_post_field',
			'get_post_permalink'            => 'remote_get_post_field',
			'get_post_metadata_single'      => 'remote_get_post_meta',
			'get_post_type_all'             => 'remote_get_post_types',
			'get_post_type_single'          => 'remote_get_post_type_single',
			'add_taxonomy_to_post'          => 'remote_assign_terms',
			'add_category_to_post'          => 'remote_assign_terms',
			'add_tags_to_post'              => 'remote_assign_terms',
			'remove_taxonomy_from_post'     => 'remote_remove_terms',
			'remove_tags_from_post'         => 'remote_remove_terms',
			'bulk_assign_terms_to_posts'    => 'remote_bulk_assign_terms',
			'bulk_remove_terms_from_posts'  => 'remote_bulk_remove_terms',

			// Users.
			'create_user'                   => 'remote_create_user',
			'update_user'                   => 'remote_update_user',
			'delete_user'                   => 'remote_delete_user',
			'get_users'                     => 'remote_get_users',
			'get_users_by_role'             => 'remote_get_users',
			'get_user_by_id'                => 'remote_get_user',
			'get_user_by_email'             => 'remote_get_user',
			'get_user_by_field'             => 'remote_get_user',
			'get_user_meta_all'             => 'remote_get_user_meta',
			'get_user_meta_single'          => 'remote_get_user_meta',
			'update_user_meta'              => 'remote_update_user_meta',

			// Comments.
			'create_comment'                => 'remote_create_comment',
			'reply_comment'                 => 'remote_create_comment',
			'approve_comment'               => 'remote_comment_status',
			'unapproved_comment'            => 'remote_comment_status',
			'mark_comment_spam'             => 'remote_comment_status',
			'unmark_comment_spam'           => 'remote_comment_status',
			'trash_comment'                 => 'remote_comment_status',
			'restore_comment'               => 'remote_comment_status',
			'untrash_comment'               => 'remote_comment_status',
			'delete_comment'                => 'remote_delete_comment',
			'delete_trash_comment'          => 'remote_delete_comment',
			'get_post_comments_single'      => 'remote_get_comments',
			'get_user_comments_email'       => 'remote_get_comments',

			// Terms and categories.
			'create_term'                   => 'remote_create_term',
			'update_term'                   => 'remote_update_term',
			'delete_term'                   => 'remote_delete_term',
			'get_term'                      => 'remote_get_term',
			'get_term_by_field'             => 'remote_get_term',
			'get_terms_by_taxonomy'         => 'remote_get_terms',
			'create_category'               => 'remote_create_term',
			'update_category'               => 'remote_update_term',
			'delete_category'               => 'remote_delete_term',
			'get_category'                  => 'remote_get_term',
			'get_categories'                => 'remote_get_terms',
			'get_taxonomies'                => 'remote_get_taxonomies',
			'get_taxonomy'                  => 'remote_get_taxonomy',

			// Media.
			'add_media_image'               => 'remote_add_media_image',
			'delete_media'                  => 'remote_delete_media',
			'rename_media'                  => 'remote_rename_media',
			'get_media_all'                 => 'remote_get_media',
			'get_media_by_title'            => 'remote_get_media',
			'get_media_by_id'               => 'remote_get_media',

			// Plugins and options.
			'activate_plugin'               => 'remote_plugin_status',
			'deactivate_plugin'             => 'remote_plugin_status',
			'add_plugin_theme_option'       => 'remote_update_option',
			'update_option_advanced'        => 'remote_update_option',

			// Roles attached to a user. Role and capability editing itself has
			// no REST route in WordPress core.
			'add_user_role'                 => 'remote_user_roles',
			'remove_user_role'              => 'remote_user_roles',
			'update_user_role'              => 'remote_user_roles',
		];
	}

	/**
	 * Ask a site whether Zaplane is installed there.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array{reachable:bool,zaplane:bool,info:array<string,mixed>,error:string}
	 */
	private static function remote_probe( array $credentials ): array {
		try {
			[ $info, $status ] = self::remote_request( $credentials, 'GET', 'zaplane/v1/remote/info', null, [], [], true );
		} catch ( \Exception $e ) {
			return [
				'reachable' => false,
				'zaplane'   => false,
				'info'      => [],
				'error'     => $e->getMessage(),
			];
		}

		if ( 200 !== $status || empty( $info['zaplane'] ) ) {
			return [
				'reachable' => true,
				'zaplane'   => false,
				'info'      => [],
				'error'     => '',
			];
		}

		return [
			'reachable' => true,
			'zaplane'   => true,
			'info'      => $info,
			'error'     => '',
		];
	}

	/**
	 * Ask a site running Zaplane to pair with this one, then remember the
	 * token it hands back.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param array<string,mixed> $info        What its /remote/info answered.
	 * @return array{success:bool,message:string,details:array<string,mixed>}
	 */
	private static function remote_pair( array $credentials, array $info ): array {
		$site = self::remote_site_url( $credentials );

		try {
			[ $pair, $status ] = self::remote_request(
				$credentials,
				'POST',
				'zaplane/v1/remote/pair',
				[ 'controller' => home_url() ],
				[],
				[],
				true
			);
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => 'The site could not be reached: ' . $e->getMessage(),
				'details' => [],
			];
		}

		$token = trim( (string) ( $pair['token'] ?? '' ) );

		if ( 200 !== $status || '' === $token ) {
			return [
				'success' => false,
				'message' => self::remote_error_message( $status, $pair ),
				'details' => [],
			];
		}

		self::remote_remember( $site, [
			'mode' => 'zaplane',
			'token' => $token
		] );

		$named = trim( (string) ( $info['site'] ?? '' ) );

		return [
			'success' => true,
			'message' => 'Auto-connected to ' . ( '' !== $named ? $named : $site ) . '. Zaplane runs there, so no username or password is needed.',
			'details' => [
				'site'    => (string) ( $info['site'] ?? '' ),
				'url'     => (string) ( $info['url'] ?? $site ),
				'mode'    => 'zaplane',
				'version' => (string) ( $info['version'] ?? '' ),
			],
		];
	}

	/**
	 * The token a run carries, pairing on first contact if this site holds
	 * none — a fresh install, a lost option, or a pairing the other side
	 * revoked.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param bool                $refresh     Drop the stored token first, for
	 *                                         a run the other side just refused.
	 * @return array{token:?string,error:string} A null token with no error is
	 *                                           the other site saying it runs
	 *                                           no Zaplane at all.
	 */
	private static function remote_zaplane_token( array $credentials, bool $refresh = false ): array {
		$site = self::remote_site_url( $credentials );

		if ( $refresh ) {
			self::remote_remember( $site, [ 'token' => '' ] );
		}

		$record = self::remote_record( $site );
		$token  = trim( (string) ( $record['token'] ?? '' ) );

		if ( '' !== $token ) {
			return [
				'token' => $token,
				'error' => '',
			];
		}

		$probe = self::remote_probe( $credentials );

		if ( ! $probe['reachable'] ) {
			return [
				'token' => null,
				'error' => 'The connected site could not be reached: ' . $probe['error'],
			];
		}

		if ( ! $probe['zaplane'] ) {
			return [
				'token' => null,
				'error' => '',
			];
		}

		$pair = self::remote_pair( $credentials, $probe['info'] );

		if ( ! $pair['success'] ) {
			return [
				'token' => null,
				'error' => $pair['message'],
			];
		}

		$record = self::remote_record( $site );
		$token  = trim( (string) ( $record['token'] ?? '' ) );

		if ( '' === $token ) {
			return [
				'token' => null,
				'error' => 'The connection with ' . $site . ' could not be established.',
			];
		}

		return [
			'token' => $token,
			'error' => '',
		];
	}

	/**
	 * Hand a step to the other site's own copy of Zaplane.
	 *
	 * @param string              $event       Step event, e.g. create_post.
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param array<string,mixed> $input       Payload arriving from the previous step.
	 * @return array<string,mixed>|null Null when that site runs no Zaplane and
	 *                                  the caller should fall back to the REST
	 *                                  handlers instead.
	 */
	private static function remote_zaplane_execute( string $event, array $config, array $credentials, array $input ): ?array {
		$token = self::remote_zaplane_token( $credentials );

		if ( null === $token['token'] && '' === $token['error'] ) {
			return null;
		}

		if ( null === $token['token'] ) {
			return static::error( $token['error'] );
		}

		$site   = self::remote_site_url( $credentials );
		$answer = self::remote_zaplane_run( $event, $config, $credentials, $input, $token['token'] );

		if ( '' !== $answer['error'] ) {
			return static::error( $answer['error'] );
		}

		if ( 403 === $answer['status'] ) {
			// The other side does not know this site any more: a fresh
			// install, a revoked pairing, or a token rotated under us. Pair
			// again and give the step one more try.
			$retry = self::remote_zaplane_token( $credentials, true );

			if ( null === $retry['token'] ) {
				return static::error( '' !== $retry['error'] ? $retry['error'] : 'The connection with ' . $site . ' was refused.' );
			}

			$answer = self::remote_zaplane_run( $event, $config, $credentials, $input, $retry['token'] );

			if ( '' !== $answer['error'] ) {
				return static::error( $answer['error'] );
			}
		}

		$body = $answer['body'];

		if ( $answer['status'] < 200 || $answer['status'] >= 300 ) {
			return static::error( self::remote_error_message( $answer['status'], $body ) );
		}

		if ( ! is_array( $body ) || ! isset( $body['port'] ) || ! isset( $body['data'] ) ) {
			return static::error(
				'The connected site answered in a shape this site does not understand. Update Zaplane on both sites.'
			);
		}

		return $body;
	}

	/**
	 * One call to the other site's /remote/run.
	 *
	 * @param string              $event       Step event, e.g. create_post.
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param array<string,mixed> $input       Payload arriving from the previous step.
	 * @param string              $token       Pairing token held for that site.
	 * @return array{body:array<string,mixed>,status:int,error:string}
	 */
	private static function remote_zaplane_run( string $event, array $config, array $credentials, array $input, string $token ): array {
		try {
			[ $body, $status ] = self::remote_request(
				$credentials,
				'POST',
				'zaplane/v1/remote/run',
				[
					'token'  => $token,
					'app'    => static::get_slug(),
					'event'  => $event,
					'config' => $config,
					'input'  => $input,
				],
				[],
				[],
				true
			);
		} catch ( \Exception $e ) {
			return [
				'body'   => [],
				'status' => 0,
				'error'  => $e->getMessage(),
			];
		}//end try

		return [
			'body'   => $body,
			'status' => $status,
			'error'  => '',
		];
	}

	/**
	 * Ask the connected site which triggers have fired, handing it the watch
	 * to hold at the same time.
	 *
	 * Triggers cannot be read over the core REST API — WordPress exposes no
	 * route that subscribes to a hook — so this only works against a site
	 * running Zaplane, and says so by answering not ok with no error for a
	 * connection in REST mode.
	 *
	 * @param array<string,mixed>            $credentials Connection credentials.
	 * @param array<int,array<string,mixed>> $specs Triggers to watch: the app,
	 *                                        event, hook and step config.
	 * @param int                            $since       Last sequence number collected.
	 * @return array{ok:bool,seq:int,events:array<int,array<string,mixed>>,error:string}
	 */
	public static function remote_watch( array $credentials, array $specs, int $since ): array {
		$empty = [
			'ok'     => false,
			'seq'    => $since,
			'events' => [],
			'error'  => '',
		];

		if ( 'zaplane' !== self::remote_mode( $credentials ) ) {
			return $empty;
		}

		$token = self::remote_zaplane_token( $credentials );

		if ( null === $token['token'] && '' === $token['error'] ) {
			// The site stopped running Zaplane. Nothing to watch, and no
			// error worth reporting on every tick.
			return $empty;
		}

		if ( null === $token['token'] ) {
			$empty['error'] = $token['error'];
			return $empty;
		}

		$answer = self::remote_watch_call( $credentials, $specs, $since, $token['token'] );

		if ( 403 === $answer['status'] ) {
			// The other side does not know this site any more. Pair again and
			// give the collection one more try.
			$retry = self::remote_zaplane_token( $credentials, true );

			if ( null === $retry['token'] ) {
				$answer['status'] = 0;
				$answer['error']  = '' !== $retry['error']
					? $retry['error']
					: 'The connection with ' . self::remote_site_url( $credentials ) . ' was refused.';
			} else {
				$answer = self::remote_watch_call( $credentials, $specs, $since, $retry['token'] );
			}
		}

		if ( '' !== $answer['error'] ) {
			$empty['error'] = $answer['error'];
			return $empty;
		}

		if ( $answer['status'] < 200 || $answer['status'] >= 300 ) {
			return $empty;
		}

		$body   = $answer['body'];
		$events = is_array( $body['events'] ?? null ) ? array_values( array_filter( $body['events'], 'is_array' ) ) : [];

		return [
			'ok'     => true,
			'seq'    => (int) ( $body['seq'] ?? $since ),
			'events' => $events,
			'error'  => '',
		];
	}

	/**
	 * One call to the other site's /remote/events.
	 *
	 * @param array<string,mixed>            $credentials Connection credentials.
	 * @param array<int,array<string,mixed>> $specs       Triggers to watch.
	 * @param int                            $since       Last sequence collected.
	 * @param string                         $token       Pairing token held for that site.
	 * @return array{body:array<string,mixed>,status:int,error:string}
	 */
	private static function remote_watch_call( array $credentials, array $specs, int $since, string $token ): array {
		try {
			[ $body, $status ] = self::remote_request(
				$credentials,
				'POST',
				'zaplane/v1/remote/events',
				[
					'token' => $token,
					'specs' => $specs,
					'since' => $since,
				],
				[],
				[],
				true
			);
		} catch ( \Exception $e ) {
			return [
				'body'   => [],
				'status' => 0,
				'error'  => $e->getMessage(),
			];
		}//end try

		return [
			'body'   => $body,
			'status' => $status,
			'error'  => '',
		];
	}

	/**
	 * How this connection talks to the site: over a Zaplane pairing, or over
	 * the core REST API with a username and Application Password.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 */
	private static function remote_mode( array $credentials ): string {
		$mode = trim( (string) ( self::remote_record( self::remote_site_url( $credentials ) )['mode'] ?? '' ) );

		if ( 'zaplane' === $mode || 'rest' === $mode ) {
			return $mode;
		}

		return self::remote_has_auth( $credentials ) ? 'rest' : 'zaplane';
	}

	/**
	 * Whether this connection carries anything the REST API could authenticate
	 * with.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 */
	private static function remote_has_auth( array $credentials ): bool {
		return '' !== trim( (string) ( $credentials['username'] ?? '' ) )
			&& '' !== trim( (string) ( $credentials['password'] ?? '' ) );
	}

	/**
	 * Everything this site remembers about a connected site: the pairing token
	 * it handed out and how the connection runs. Kept here rather than in the
	 * connection, because a connection holds only the fields its form was
	 * filled with.
	 *
	 * @return array<string,array<string,string>>
	 */
	private static function remote_sites(): array {
		$sites = get_option( 'zaplane_connected_sites', [] );

		return is_array( $sites ) ? $sites : [];
	}

	/**
	 * What is remembered about one connected site.
	 *
	 * @param string $site Normalised site URL.
	 * @return array<string,string>
	 */
	private static function remote_record( string $site ): array {
		$sites  = self::remote_sites();
		$record = $sites[ $site ] ?? [];

		return is_array( $record ) ? $record : [];
	}

	/**
	 * Merge what was learned about one connected site into the rest.
	 *
	 * @param string              $site   Normalised site URL.
	 * @param array<string,mixed> $record Fields to store; an empty token
	 *                                    is dropped rather than saved.
	 */
	private static function remote_remember( string $site, array $record ): void {
		if ( '' === $site ) {
			return;
		}

		$sites  = self::remote_sites();
		$stored = array_merge( self::remote_record( $site ), $record );

		if ( isset( $stored['token'] ) && '' === trim( (string) $stored['token'] ) ) {
			unset( $stored['token'] );
		}

		$sites[ $site ] = $stored;

		update_option( 'zaplane_connected_sites', $sites, false );
	}

	/**
	 * The site URL a connection points at, without a trailing slash.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 */
	private static function remote_site_url( array $credentials ): string {
		$url = trim( (string) ( $credentials['site_url'] ?? '' ) );

		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		return rtrim( $url, '/' );
	}

	/**
	 * Call the connected site's REST API.
	 *
	 * A string $body is sent as-is so binary uploads reach the media route
	 * unencoded; an array is JSON encoded. With $soft the status is returned
	 * rather than thrown, which is how test_connection() and the lookups that
	 * probe for a route tell "not found" apart from "broken".
	 *
	 * @param array<string,mixed>             $credentials Connection credentials.
	 * @param string                          $method      HTTP method.
	 * @param string                          $route       Route relative to /wp-json/.
	 * @param array<string,mixed>|string|null $body Request body.
	 * @param array<string,mixed>             $query       Query string arguments.
	 * @param array<string,string>            $headers     Extra request headers.
	 * @param bool                            $soft        Return rather than throw on a non-2xx status.
	 * @return array{0:array<string,mixed>,1:int}
	 * @throws \Exception When the request cannot be sent, or fails without $soft.
	 */
	private static function remote_request(
		array $credentials,
		string $method,
		string $route,
		$body = null,
		array $query = [],
		array $headers = [],
		bool $soft = false
	) {
		$site = self::remote_site_url( $credentials );

		if ( '' === $site ) {
			throw new \Exception( 'The connection has no site URL.' );
		}

		$defaults = [
			'Accept' => 'application/json',
		];

		// A paired site needs no credentials at all, so the Basic header is
		// only sent when the connection actually holds a username and an
		// Application Password to send.
		if ( self::remote_has_auth( $credentials ) ) {
			// The header is HTTP Basic authentication, not an attempt to hide code.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$defaults['Authorization'] = 'Basic ' . base64_encode(
				trim( (string) $credentials['username'] ) . ':' . (string) $credentials['password']
			);
		}

		$headers = array_merge( $defaults, $headers );

		if ( is_array( $body ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		$args = [
			'method'  => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => 30,
		];

		if ( is_array( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		} elseif ( null !== $body ) {
			$args['body'] = (string) $body;
		}

		// A site with plain permalinks serves its REST API only under
		// /?rest_route= — there a /wp-json/ call is answered by a redirect that
		// ends on a web page, losing the request. The style that worked last
		// time goes first; a pretty call that comes back as a page or a 404 is
		// retried once in the query style, and a working answer is remembered.
		$style = 'query' === ( self::remote_record( $site )['rest_style'] ?? '' ) ? 'query' : 'pretty';

		[ $decoded, $status, $raw ] = self::remote_call( $site, $style, $route, $query, $args );

		// Only an answer that is not JSON means the REST API was never
		// reached — a page the redirect or server put up instead. A JSON 404
		// is the API itself answering, and is passed on as it is.
		$is_json = null !== json_decode( $raw );
		$broken  = ! $is_json
			&& ( ( $status >= 200 && $status < 300 && '' !== trim( $raw ) ) || 404 === $status );

		if ( 'pretty' === $style && $broken ) {
			[ $retried, $retried_status, $retried_raw ] = self::remote_call( $site, 'query', $route, $query, $args );

			$usable = null !== json_decode( $retried_raw )
				|| ( $retried_status >= 200 && $retried_status < 300 && '' === trim( $retried_raw ) );

			if ( $usable ) {
				self::remote_remember( $site, [ 'rest_style' => 'query' ] );

				$decoded = $retried;
				$status  = $retried_status;
				$raw     = $retried_raw;
			}
		}

		// Whatever style answered: a 2xx answer that is not JSON is a web page,
		// not an API answer — a login or security page that swallowed the
		// request. Answering it as an empty payload would look like a step that
		// succeeded and quietly did nothing.
		if ( $status >= 200 && $status < 300 && '' !== trim( $raw ) && null === json_decode( $raw ) ) {
			throw new \Exception(
				sprintf(
					'The connected site answered HTTP %d with a web page instead of JSON. The request most likely landed on a login or security page — check that the Site URL is the site\'s real address (https, no redirect), the Application Password works, and no firewall or bot protection sits in front of the site.',
					$status
				)
			);
		}

		$decoded = is_array( $decoded ) ? $decoded : [];

		if ( ! $soft && ( $status < 200 || $status >= 300 ) ) {
			throw new \Exception( self::remote_error_message( $status, $decoded ) );
		}

		return [ $decoded, $status ];
	}

	/**
	 * One call to the connected site's REST API, in one of its two URL styles.
	 *
	 * @param string              $site   Site URL, no trailing slash.
	 * @param string              $style  Either pretty (/wp-json/) or query (?rest_route=).
	 * @param string              $route  Route relative to the REST root.
	 * @param array<string,mixed> $query  Query string arguments.
	 * @param array<string,mixed> $args   wp_remote_request arguments.
	 * @return array{0:array<string,mixed>,1:int,2:string} Decoded body, status and raw body.
	 * @throws \Exception When the request cannot be sent.
	 */
	private static function remote_call( string $site, string $style, string $route, array $query, array $args ): array {
		if ( 'query' === $style ) {
			$url = $site . '/?rest_route=/' . ltrim( $route, '/' );

			if ( $query ) {
				$url .= '&' . http_build_query( $query );
			}
		} else {
			$url = $site . '/wp-json/' . ltrim( $route, '/' );

			if ( $query ) {
				$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $query );
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception(
				'The request to ' . $site . ' failed: ' . $response->get_error_message()
			);
		}

		$raw     = (string) wp_remote_retrieve_body( $response );
		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( $raw, true );
		$decoded = is_array( $decoded ) ? $decoded : [];

		return [ $decoded, $status, $raw ];
	}

	/**
	 * A whole collection from the connected site, following pages until the
	 * route runs out. WordPress caps per_page at 100, and a step that reads
	 * every post should not quietly stop at the first hundred of them.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $route       Route relative to /wp-json/.
	 * @param array<string,mixed> $query       Query string arguments.
	 * @return array<int,array<string,mixed>>
	 * @throws \Exception When the route answers with an error.
	 */
	private static function remote_collect( array $credentials, string $route, array $query ): array {
		$items = [];
		$page  = 1;
		$size  = 0;

		do {
			$query['page'] = $page;

			[ $batch ] = self::remote_request( $credentials, 'GET', $route, null, $query );

			if ( ! is_array( $batch ) ) {
				break;
			}

			$items = array_merge( $items, array_values( $batch ) );
			$size  = count( $batch );
			++$page;
		} while ( $size >= 100 && $page <= 10 );

		return $items;
	}

	/**
	 * Turn a failed REST answer into a message worth showing a user.
	 *
	 * @param int                 $status HTTP status.
	 * @param array<string,mixed> $body   Decoded response body.
	 */
	private static function remote_error_message( int $status, array $body ): string {
		$message = trim( (string) ( $body['message'] ?? '' ) );
		$code    = trim( (string) ( $body['code'] ?? '' ) );

		$lead = sprintf( 'The connected site answered HTTP %d.', $status );

		if ( '' !== $message && '' !== $code ) {
			return $lead . ' ' . $message . ' (' . $code . ')';
		}

		if ( '' !== $message ) {
			return $lead . ' ' . $message;
		}

		return $lead;
	}

	/**
	 * REST routes of every post type the connected site exposes.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_types( array $credentials ): array {
		if ( null !== self::$remote_types ) {
			return self::$remote_types;
		}

		[ $items, $status ] = self::remote_request( $credentials, 'GET', 'wp/v2/types', null, [ 'context' => 'edit' ], [], true );

		self::$remote_types = ( 200 === $status && is_array( $items ) ) ? $items : [];

		return self::$remote_types;
	}

	/**
	 * REST routes of every taxonomy the connected site exposes.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_taxonomies( array $credentials ): array {
		if ( null !== self::$remote_taxonomies ) {
			return self::$remote_taxonomies;
		}

		[ $items, $status ] = self::remote_request( $credentials, 'GET', 'wp/v2/taxonomies', null, [ 'context' => 'edit' ], [], true );

		self::$remote_taxonomies = ( 200 === $status && is_array( $items ) ) ? $items : [];

		return self::$remote_taxonomies;
	}

	/**
	 * The route a post type is served on, e.g. post => posts.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $post_type   Post type name.
	 * @throws \Exception When the post type is not exposed over REST.
	 */
	private static function remote_post_base( array $credentials, string $post_type ): string {
		$post_type = '' !== $post_type ? $post_type : 'post';

		$types = self::remote_types( $credentials );
		$base  = (string) ( $types[ $post_type ]['rest_base'] ?? '' );

		if ( '' === $base ) {
			$base = ( 'post' === $post_type || 'page' === $post_type ) ? $post_type . 's' : '';
		}

		if ( '' === $base ) {
			throw new \Exception(
				sprintf( 'The post type "%s" is not exposed by the REST API on the connected site.', $post_type )
			);
		}

		return $base;
	}

	/**
	 * The route a post type is served on, found from its ID when the step does
	 * not say which type it is working with.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param int                 $post_id     Post ID.
	 * @throws \Exception When no exposed post type holds that ID.
	 */
	private static function remote_post_base_for_id( array $credentials, int $post_id ): string {
		$types = self::remote_types( $credentials );

		if ( empty( $types ) ) {
			$types = [
				'post'   => [ 'rest_base' => 'posts' ],
				'page'   => [ 'rest_base' => 'pages' ],
				'attachment' => [ 'rest_base' => 'media' ],
			];
		}

		foreach ( $types as $definition ) {
			$base = (string) ( $definition['rest_base'] ?? '' );

			if ( '' === $base ) {
				continue;
			}

			[ $body, $status ] = self::remote_request( $credentials, 'GET', 'wp/v2/' . $base . '/' . $post_id, null, [ 'context' => 'edit' ], [], true );

			if ( 200 === $status && ! empty( $body['type'] ) ) {
				return (string) $body['type'];
			}
		}

		throw new \Exception(
			sprintf( 'No post with ID %d was found on the connected site.', $post_id )
		);
	}

	/**
	 * The route a taxonomy is served on, e.g. category => categories.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $taxonomy    Taxonomy name.
	 * @throws \Exception When the taxonomy is not exposed over REST.
	 */
	private static function remote_tax_base( array $credentials, string $taxonomy ): string {
		$taxonomy = '' !== $taxonomy ? $taxonomy : 'category';

		if ( 'category' === $taxonomy ) {
			return 'categories';
		}

		if ( 'post_tag' === $taxonomy ) {
			return 'tags';
		}

		$items = self::remote_taxonomies( $credentials );

		if ( ! isset( $items[ $taxonomy ] ) || ! is_array( $items[ $taxonomy ] ) ) {
			throw new \Exception(
				sprintf( 'The taxonomy "%s" is not exposed by the REST API on the connected site.', $taxonomy )
			);
		}

		// WordPress serves a taxonomy on its REST base when it has one, and on
		// its own name otherwise.
		$base = (string) ( $items[ $taxonomy ]['rest_base'] ?? '' );

		return '' !== $base ? $base : $taxonomy;
	}

	/**
	 * The key a taxonomy's terms are posted under on an item, which is its
	 * REST base when it has one and its name otherwise.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $taxonomy    Taxonomy name.
	 */
	private static function remote_tax_field( array $credentials, string $taxonomy ): string {
		if ( 'category' === $taxonomy ) {
			return 'categories';
		}

		if ( 'post_tag' === $taxonomy ) {
			return 'tags';
		}

		return self::remote_tax_base( $credentials, $taxonomy );
	}

	/**
	 * A rendered or raw REST text value as a plain string.
	 *
	 * @param mixed $value Value from a REST payload.
	 */
	private static function remote_text( $value ): string {
		if ( is_array( $value ) ) {
			if ( isset( $value['raw'] ) ) {
				return (string) $value['raw'];
			}

			if ( isset( $value['rendered'] ) ) {
				return (string) $value['rendered'];
			}

			return '';
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A REST post as the database row shape the local steps return.
	 *
	 * @param array<string,mixed> $item REST post payload.
	 * @return array<string,mixed>
	 */
	private static function remote_map_post( array $item ): array {
		return [
			'ID'             => (int) ( $item['id'] ?? 0 ),
			'post_author'    => (int) ( $item['author'] ?? 0 ),
			'post_date'      => (string) ( $item['date'] ?? '' ),
			'post_date_gmt'  => (string) ( $item['date_gmt'] ?? '' ),
			'post_content'   => self::remote_text( $item['content'] ?? '' ),
			'post_title'     => self::remote_text( $item['title'] ?? '' ),
			'post_excerpt'   => self::remote_text( $item['excerpt'] ?? '' ),
			'post_status'    => (string) ( $item['status'] ?? '' ),
			'post_name'      => (string) ( $item['slug'] ?? '' ),
			'post_type'      => (string) ( $item['type'] ?? '' ),
			'post_parent'    => (int) ( $item['parent'] ?? 0 ),
			'menu_order'     => (int) ( $item['menu_order'] ?? 0 ),
			'comment_status' => (string) ( $item['comment_status'] ?? '' ),
			'ping_status'    => (string) ( $item['ping_status'] ?? '' ),
			'comment_count'  => (int) ( $item['comment_count'] ?? 0 ),
			'guid'           => self::remote_text( $item['guid'] ?? '' ),
			'link'           => (string) ( $item['link'] ?? '' ),
			'featured_media' => (int) ( $item['featured_media'] ?? 0 ),
		];
	}

	/**
	 * A REST user as the payload shape query_users() produces locally.
	 *
	 * @param array<string,mixed> $item REST user payload.
	 * @return array<string,mixed>
	 */
	private static function remote_map_user( array $item ): array {
		$first = (string) ( $item['first_name'] ?? '' );
		$last  = (string) ( $item['last_name'] ?? '' );
		$name  = (string) ( $item['name'] ?? '' );
		$label = trim( $first . ' ' . $last );

		$avatars = isset( $item['avatar_urls'] ) && is_array( $item['avatar_urls'] ) ? $item['avatar_urls'] : [];
		$caps    = isset( $item['extra_capabilities'] ) && is_array( $item['extra_capabilities'] ) ? $item['extra_capabilities'] : [];

		return [
			'ID'          => (int) ( $item['id'] ?? 0 ),
			'name'        => $name,
			'label'       => '' !== $label ? $label : ( '' !== $name ? $name : (string) ( $item['username'] ?? '' ) ),
			'email'       => (string) ( $item['email'] ?? '' ),
			'login'       => (string) ( $item['username'] ?? '' ),
			'nicename'    => (string) ( $item['slug'] ?? '' ),
			'url'         => (string) ( $item['url'] ?? '' ),
			'registered'  => (string) ( $item['registered_date'] ?? '' ),
			'roles'       => isset( $item['roles'] ) && is_array( $item['roles'] ) ? $item['roles'] : [],
			'first_name'  => $first,
			'last_name'   => $last,
			'nickname'    => (string) ( $item['nickname'] ?? '' ),
			'description' => (string) ( $item['description'] ?? '' ),
			'locale'      => (string) ( $item['locale'] ?? '' ),
			'avatar'      => (string) ( end( $avatars ) ?? '' ),
			'caps'        => array_keys( $caps ),
			'data'        => $item,
			'meta'        => isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : [],
		];
	}

	/**
	 * A REST term as the row shape get_term_payload() produces locally.
	 *
	 * @param array<string,mixed> $item REST term payload.
	 * @return array<string,mixed>
	 */
	private static function remote_map_term( array $item ): array {
		return [
			'term_id'          => (int) ( $item['id'] ?? 0 ),
			'name'             => (string) ( $item['name'] ?? '' ),
			'slug'             => (string) ( $item['slug'] ?? '' ),
			'term_group'       => 0,
			'term_taxonomy_id' => (int) ( $item['id'] ?? 0 ),
			'taxonomy'         => (string) ( $item['taxonomy'] ?? '' ),
			'description'      => (string) ( $item['description'] ?? '' ),
			'parent'           => (int) ( $item['parent'] ?? 0 ),
			'count'            => (int) ( $item['count'] ?? 0 ),
		];
	}

	/**
	 * A REST attachment as the row format_media_items() produces locally.
	 *
	 * @param array<string,mixed> $item REST media payload.
	 * @return array<string,mixed>
	 */
	private static function remote_map_media( array $item ): array {
		$row     = self::remote_map_post( $item );
		$row['post_mime_type'] = (string) ( $item['mime_type'] ?? '' );

		return array_merge(
			$row,
			[
				'url'      => (string) ( $item['source_url'] ?? '' ),
				'alt_text' => (string) ( $item['alt_text'] ?? '' ),
				'caption'  => self::remote_text( $item['caption'] ?? '' ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_create_post( array $config, array $credentials ): array {
		$base = self::remote_post_base( $credentials, (string) ( $config['post_type'] ?? '' ) );

		$body = [
			'title'   => (string) ( $config['post_title'] ?? '' ),
			'content' => (string) ( $config['post_content'] ?? '' ),
			'status'  => (string) ( $config['post_status'] ?? 'draft' ),
		];

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $base, $body );

		// A redirected request comes back as something else — a list, a page,
		// an empty answer — and none of those created the post.
		if ( empty( $item['id'] ) ) {
			return static::error(
				'The connected site answered but did not create the post. The request was most likely redirected on the way — check that the Site URL matches the site\'s real address exactly (https and www or not, whichever the site uses).'
			);
		}

		return static::success(
			self::remote_map_post( $item ) + [ 'post_id' => (int) ( $item['id'] ?? 0 ) ]
		);
	}

	/**
	 * Covers update_post, update_title, update_status and change_post_author:
	 * each supplies a different subset of the same fields.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_update_post( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$body = [];

		$fields = [
			'post_title'   => 'title',
			'post_content' => 'content',
			'post_status'  => 'status',
			'author_id'    => 'author',
		];

		foreach ( $fields as $from => $to ) {
			if ( ! isset( $config[ $from ] ) || '' === $config[ $from ] ) {
				continue;
			}

			$body[ $to ] = $config[ $from ];
		}

		if ( empty( $body ) ) {
			return static::error( 'Nothing to update' );
		}

		$post_type = (string) ( $config['post_type'] ?? '' );
		$base      = '' !== $post_type
			? self::remote_post_base( $credentials, $post_type )
			: self::remote_post_base_for_id( $credentials, $post_id );

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $base . '/' . $post_id, $body );

		return static::success(
			self::remote_map_post( $item ) + [ 'post_id' => $post_id ]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_schedule_post( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base = self::remote_post_base_for_id( $credentials, $post_id );
		$date = (string) ( $config['schedule_date'] ?? '' );

		if ( '' !== $date ) {
			$body = [
				'status'    => (string) ( $config['status'] ?? 'future' ),
				'date'      => $date,
				'date_gmt'  => get_gmt_from_date( $date ),
			];
		} else {
			$body = [
				'status'   => 'draft',
				'date'     => current_time( 'mysql' ),
				'date_gmt' => current_time( 'mysql', true ),
			];
		}

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $base . '/' . $post_id, $body );

		return static::success(
			self::remote_map_post( $item ) + [ 'post_id' => $post_id ]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_set_featured_image( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );
		$image   = (int) ( $config['image_id'] ?? $config['attachment_id'] ?? 0 );

		if ( ! $post_id || ! $image ) {
			return static::error( 'Post ID and attachment ID are required' );
		}

		$base  = self::remote_post_base_for_id( $credentials, $post_id );
		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $base . '/' . $post_id, [ 'featured_media' => $image ] );

		return static::success(
			self::remote_map_post( $item ) + [
				'post_id' => $post_id,
				'attachment_id' => $image
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_trash_post( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base = self::remote_post_base_for_id( $credentials, $post_id );

		self::remote_request( $credentials, 'DELETE', 'wp/v2/' . $base . '/' . $post_id, null, [ 'force' => 0 ] );

		return static::success( [ 'post_id' => $post_id ] );
	}

	/**
	 * WordPress keeps the status a post was trashed from in post meta, which
	 * the REST API does not read back, so a restored post lands in draft —
	 * the same fallback wp_untrash_post() takes when that meta is missing.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_restore_post( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base  = self::remote_post_base_for_id( $credentials, $post_id );
		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $base . '/' . $post_id, [ 'status' => 'draft' ] );

		return static::success(
			self::remote_map_post( $item ) + [ 'post_id' => $post_id ]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_delete_post( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base = self::remote_post_base_for_id( $credentials, $post_id );

		self::remote_request( $credentials, 'DELETE', 'wp/v2/' . $base . '/' . $post_id, null, [ 'force' => 1 ] );

		return static::success( [ 'post_id' => $post_id ] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_duplicate_post( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base   = self::remote_post_base_for_id( $credentials, $post_id );
		[ $original ] = self::remote_request( $credentials, 'GET', 'wp/v2/' . $base . '/' . $post_id, null, [ 'context' => 'edit' ] );

		$title = (string) ( $config['new_title'] ?? '' );
		$title = '' !== $title ? $title : self::remote_text( $original['title'] ?? '' ) . ' (copy)';

		$body = [
			'title'   => $title,
			'content' => self::remote_text( $original['content'] ?? '' ),
			'status'  => (string) ( $config['status'] ?? 'draft' ),
		];

		if ( ! empty( $original['author'] ) ) {
			$body['author'] = (int) $original['author'];
		}

		[ $copy ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $base, $body );

		return static::success(
			self::remote_map_post( $copy )
			+ [
				'original_id' => $post_id,
				'new_id'      => (int) ( $copy['id'] ?? 0 ),
				'new_title'   => $title,
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_posts( array $config, array $credentials ): array {
		$post_type = (string) ( $config['post_type'] ?? '' );
		$base      = '' !== $post_type
			? self::remote_post_base( $credentials, $post_type )
			: 'posts';

		$query = [
			'context'  => 'edit',
			'per_page' => 100,
		];

		$items = self::remote_collect( $credentials, 'wp/v2/' . $base, $query );

		return static::success(
			array_map( [ self::class, 'remote_map_post' ], $items )
		);
	}

	/**
	 * The REST API has no meta_query, so the connected site's posts are read
	 * and the meta filter is applied here instead.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_posts_by_metadata( array $config, array $credentials ): array {
		$post_type = (string) ( $config['post_type'] ?? 'post' );
		$base      = self::remote_post_base( $credentials, $post_type );
		$key       = (string) ( $config['meta_key'] ?? '' );
		$value     = (string) ( $config['meta_value'] ?? '' );

		[ $items ] = self::remote_request(
			$credentials,
			'GET',
			'wp/v2/' . $base,
			null,
			[
				'context'  => 'edit',
				'per_page' => 100,
			]
		);

		$rows = [];
		foreach ( is_array( $items ) ? $items : [] as $item ) {
			$meta = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : [];
			if ( array_key_exists( $key, $meta ) && (string) $meta[ $key ] === $value ) {
				$rows[] = self::remote_map_post( $item );
			}
		}

		return static::success( $rows );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_post_single( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base  = self::remote_post_base_for_id( $credentials, $post_id );
		[ $item ] = self::remote_request( $credentials, 'GET', 'wp/v2/' . $base . '/' . $post_id, null, [ 'context' => 'edit' ] );

		return static::success( self::remote_map_post( $item ) );
	}

	/**
	 * Covers get_post_content, get_post_excerpt, get_post_status and
	 * get_post_permalink, each of which reads one field of one post.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_post_field( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base = self::remote_post_base_for_id( $credentials, $post_id );

		[ $item, $status ] = self::remote_request(
			$credentials,
			'GET',
			'wp/v2/' . $base . '/' . $post_id,
			null,
			[ 'context' => 'edit' ],
			[],
			true
		);

		if ( 200 !== $status || empty( $item['id'] ) ) {
			return static::error( 'No post found for this ID' );
		}

		$row  = self::remote_map_post( $item );
		$event = (string) ( $config['__event'] ?? '' );

		if ( 'get_post_content' === $event ) {
			return static::success( [
				'post_id' => $post_id,
				'post_content' => $row['post_content']
			] );
		}

		if ( 'get_post_excerpt' === $event ) {
			return static::success( [
				'post_id' => $post_id,
				'post_excerpt' => $row['post_excerpt']
			] );
		}

		if ( 'get_post_status' === $event ) {
			return static::success( [
				'post_id' => $post_id,
				'post_status' => $row['post_status']
			] );
		}

		return static::success( [
			'post_id' => $post_id,
			'permalink' => $row['link']
		] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_post_meta( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );
		$key     = (string) ( $config['meta_key'] ?? '' );

		if ( ! $post_id || '' === $key ) {
			return static::error( 'Post ID and meta key are required' );
		}

		$base  = self::remote_post_base_for_id( $credentials, $post_id );
		[ $item ] = self::remote_request( $credentials, 'GET', 'wp/v2/' . $base . '/' . $post_id, null, [ 'context' => 'edit' ] );

		$meta = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : [];

		if ( ! array_key_exists( $key, $meta ) || '' === (string) $meta[ $key ] ) {
			return static::error( 'No meta value found for this key' );
		}

		return static::success(
			[
				'post_id'    => $post_id,
				'meta_key'   => $key,
				'meta_value' => $meta[ $key ],
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_post_types( array $config, array $credentials ): array {
		$types = self::remote_types( $credentials );
		$out   = [];

		foreach ( $types as $slug => $definition ) {
			$out[ $slug ] = [
				'name'            => $slug,
				'label'           => (string) ( $definition['name'] ?? $slug ),
				'description'     => (string) ( $definition['description'] ?? '' ),
				'hierarchical'    => (bool) ( $definition['hierarchical'] ?? false ),
				'rest_base'       => (string) ( $definition['rest_base'] ?? '' ),
				'show_in_rest'    => true,
				'public'          => (bool) ( $definition['viewable'] ?? true ),
				'capability_type' => 'post',
				'capabilities'    => [],
				'labels'          => [],
				'supports'        => [],
				'menu_icon'       => '',
				'menu_position'   => null,
			];
		}

		return static::success( $out );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_post_type_single( array $config, array $credentials ): array {
		$post_id = absint( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$base  = self::remote_post_base_for_id( $credentials, $post_id );
		$types = self::remote_get_post_types( $config, $credentials );
		$known = $types['data'] ?? [];

		$slug = $base;
		foreach ( $known as $key => $definition ) {
			if ( (string) ( $definition['rest_base'] ?? '' ) === $base ) {
				$slug = $key;
				break;
			}
		}

		$entry = isset( $known[ $slug ] ) ? $known[ $slug ] : [];

		return static::success(
			array_merge( [
				'post_id' => $post_id,
				'post_type' => $slug
			], $entry )
		);
	}

	/**
	 * The taxonomy a term step works in and the values it was given.
	 *
	 * @param string              $event  Step event.
	 * @param array<string,mixed> $config Step configuration.
	 * @return array{0:string,1:array<int,string>}
	 */
	private static function remote_terms_config( string $event, array $config ): array {
		if ( 'add_category_to_post' === $event ) {
			return [ 'category', self::normalize_list( $config['categories'] ?? [] ) ];
		}

		if ( 'add_tags_to_post' === $event ) {
			return [ 'post_tag', self::normalize_list( $config['tags'] ?? [] ) ];
		}

		return [ (string) ( $config['taxonomy'] ?? '' ), self::normalize_list( $config['terms'] ?? [] ) ];
	}

	/**
	 * Turn the term names or IDs a step was given into IDs on the connected
	 * site, where the same name may sit at a different ID.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $taxonomy    Taxonomy name.
	 * @param array<int,string>   $values      Term names or IDs.
	 * @return array<int,int>
	 * @throws \Exception When a named term does not exist there.
	 */
	private static function remote_term_ids( array $credentials, string $taxonomy, array $values ): array {
		$route = self::remote_tax_base( $credentials, $taxonomy );
		$ids   = [];

		foreach ( $values as $value ) {
			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			if ( is_numeric( $value ) ) {
				$ids[] = (int) $value;
				continue;
			}

			$found = self::remote_find_term( $credentials, $route, 'slug', $value );

			if ( empty( $found ) ) {
				$found = self::remote_find_term( $credentials, $route, 'name', $value );
			}

			if ( empty( $found ) ) {
				throw new \Exception(
					sprintf(
						'The term "%s" does not exist in the "%s" taxonomy on the connected site.',
						$value,
						$taxonomy
					)
				);
			}

			$ids[] = (int) $found[0]['id'];
		}//end foreach

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Look a term up by slug, or by exact name through a search.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $route       Taxonomy REST base.
	 * @param string              $by          Either slug or name.
	 * @param string              $value       What to look for.
	 * @return array<int,array<string,mixed>>
	 */
	private static function remote_find_term( array $credentials, string $route, string $by, string $value ): array {
		if ( 'slug' === $by ) {
			[ $items, $status ] = self::remote_request(
				$credentials,
				'GET',
				'wp/v2/' . $route,
				null,
				[
					'slug' => $value,
					'per_page' => 1
				],
				[],
				true
			);

			return 200 === $status && is_array( $items ) ? $items : [];
		}

		[ $items, $status ] = self::remote_request(
			$credentials,
			'GET',
			'wp/v2/' . $route,
			null,
			[
				'search' => $value,
				'per_page' => 20
			],
			[],
			true
		);

		if ( 200 !== $status || ! is_array( $items ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$items,
				static function ( $term ) use ( $value ) {
					return isset( $term['name'] ) && 0 === strcasecmp( (string) $term['name'], $value );
				}
			)
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_assign_terms( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		list( $taxonomy, $values ) = self::remote_terms_config( (string) ( $config['__event'] ?? '' ), $config );

		return self::remote_write_terms( $config, $credentials, $post_id, $taxonomy, $values, 'add' );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_remove_terms( array $config, array $credentials ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		list( $taxonomy, $values ) = self::remote_terms_config( (string) ( $config['__event'] ?? '' ), $config );

		return self::remote_write_terms( $config, $credentials, $post_id, $taxonomy, $values, 'remove' );
	}

	/**
	 * Set or clear a post's terms in one taxonomy.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param int                 $post_id     Post ID.
	 * @param string              $taxonomy    Taxonomy name.
	 * @param array<int,string>   $values      Term names or IDs.
	 * @param string              $mode        Either add or remove.
	 * @return array<string,mixed>
	 */
	private static function remote_write_terms( array $config, array $credentials, int $post_id, string $taxonomy, array $values, string $mode ): array {
		if ( '' === $taxonomy ) {
			return static::error( 'Taxonomy is required' );
		}

		$field = self::remote_tax_field( $credentials, $taxonomy );
		$base  = self::remote_post_base_for_id( $credentials, $post_id );
		$route = 'wp/v2/' . $base . '/' . $post_id;

		[ $current, $status ] = self::remote_request( $credentials, 'GET', $route, null, [ 'context' => 'edit' ], [], true );
		$current_ids = 200 === $status && isset( $current[ $field ] ) && is_array( $current[ $field ] )
			? array_map( 'intval', $current[ $field ] )
			: [];

		$incoming = self::remote_term_ids( $credentials, $taxonomy, $values );

		if ( 'add' === $mode ) {
			$append = ! empty( $config['append'] );
			$next   = $append ? array_values( array_unique( array_merge( $current_ids, $incoming ) ) ) : $incoming;
		} else {
			$next = array_values( array_diff( $current_ids, $incoming ) );
		}

		[ $item ] = self::remote_request( $credentials, 'POST', $route, [ $field => $next ] );

		return static::success(
			[
				'post_id'    => $post_id,
				'taxonomy'   => $taxonomy,
				'field'      => $field,
				'terms'      => $next,
				'post'       => self::remote_map_post( $item ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_bulk_assign_terms( array $config, array $credentials ): array {
		return self::remote_bulk_terms( $config, $credentials, 'add' );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_bulk_remove_terms( array $config, array $credentials ): array {
		return self::remote_bulk_terms( $config, $credentials, 'remove' );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $mode        Either add or remove.
	 * @return array<string,mixed>
	 */
	private static function remote_bulk_terms( array $config, array $credentials, string $mode ): array {
		$taxonomy = (string) ( $config['taxonomy'] ?? '' );
		$values   = self::normalize_list( $config['terms'] ?? [] );
		$results  = [];

		foreach ( self::normalize_list( $config['post_ids'] ?? [] ) as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! $post_id ) {
				continue;
			}

			$results[ $post_id ] = self::remote_write_terms(
				$config,
				$credentials,
				$post_id,
				$taxonomy,
				$values,
				$mode
			);
		}

		return static::success( $results );
	}

	/**
	 * The fields a create or update user step may set, mapped onto the keys
	 * the REST users route accepts.
	 *
	 * @param array<string,mixed> $config Step configuration.
	 * @return array<string,mixed>
	 */
	private static function remote_user_body( array $config ): array {
		$map = [
			'user_login'   => 'username',
			'user_email'   => 'email',
			'user_pass'    => 'password',
			'user_url'     => 'url',
			'display_name' => 'name',
			'nickname'     => 'nickname',
			'first_name'   => 'first_name',
			'last_name'    => 'last_name',
			'description'  => 'description',
		];

		$body = [];

		foreach ( $map as $from => $to ) {
			if ( ! isset( $config[ $from ] ) || '' === $config[ $from ] ) {
				continue;
			}

			$body[ $to ] = $config[ $from ];
		}

		$role = sanitize_key( (string) ( $config['role'] ?? '' ) );

		if ( '' !== $role ) {
			$body['roles'] = [ $role ];
		}

		return $body;
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_create_user( array $config, array $credentials ): array {
		$body = self::remote_user_body( $config );

		if ( empty( $body['username'] ) || empty( $body['email'] ) ) {
			return static::error( 'A username and an email address are required' );
		}

		if ( empty( $body['password'] ) ) {
			$body['password'] = wp_generate_password( 24, true, true );
		}

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/users', $body );

		return static::success( [ 'user_id' => (int) ( $item['id'] ?? 0 ) ] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_update_user( array $config, array $credentials ): array {
		$user_id = (int) ( $config['user_id'] ?? 0 );

		if ( ! $user_id ) {
			return static::error( 'User ID is required' );
		}

		$body = self::remote_user_body( $config );

		// A login cannot be changed after the account exists.
		unset( $body['username'] );

		if ( empty( $body ) ) {
			return static::error( 'Nothing to update' );
		}

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/users/' . $user_id, $body );

		return static::success( [
			'updated' => $user_id,
			'user' => self::remote_map_user( $item )
		] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_delete_user( array $config, array $credentials ): array {
		$user_id = (int) ( $config['user_id'] ?? 0 );

		if ( ! $user_id ) {
			return static::error( 'User ID is required' );
		}

		$query = [ 'force' => 1 ];

		$reassign = isset( $config['reassign'] ) && '' !== (string) $config['reassign'] ? (int) $config['reassign'] : 0;

		if ( $reassign ) {
			$query['reassign'] = $reassign;
		}

		self::remote_request( $credentials, 'DELETE', 'wp/v2/users/' . $user_id, null, $query );

		return static::success( [ 'deleted_user_id' => $user_id ] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_users( array $config, array $credentials ): array {
		$query = [
			'context'  => 'edit',
			'per_page' => 100,
		];

		$role = (string) ( $config['role'] ?? $config['roles'] ?? '' );

		if ( is_array( $config['roles'] ?? null ) ) {
			$role = implode( ',', $config['roles'] );
		}

		if ( '' !== $role ) {
			$query['roles'] = $role;
		}

		$items = self::remote_collect( $credentials, 'wp/v2/users', $query );

		return static::success(
			[
				'users' => array_map( [ self::class, 'remote_map_user' ], $items ),
			]
		);
	}

	/**
	 * Covers get_user_by_id, get_user_by_email and get_user_by_field: each
	 * ends with one account, arrived at by a different lookup.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_user( array $config, array $credentials ): array {
		if ( isset( $config['user_id'] ) && '' !== (string) $config['user_id'] ) {
			$item = self::remote_find_user( $credentials, 'id', (string) $config['user_id'] );
		} elseif ( isset( $config['email'] ) ) {
			$item = self::remote_find_user( $credentials, 'email', (string) $config['email'] );
		} else {
			$field = strtolower( (string) ( $config['field'] ?? '' ) );
			$value = (string) ( $config['value'] ?? '' );

			if ( ! in_array( $field, [ 'id', 'email', 'login', 'slug' ], true ) ) {
				return static::error( 'Unsupported field' );
			}

			$item = self::remote_find_user( $credentials, $field, $value );
		}

		if ( empty( $item['id'] ) ) {
			return static::error( 'User not found' );
		}

		return static::success( [ 'user' => self::remote_map_user( $item ) ] );
	}

	/**
	 * One account from the connected site, or nothing.
	 *
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @param string              $field       One of id, email, login or slug.
	 * @param string              $value       What to look for.
	 * @return array<string,mixed>
	 */
	private static function remote_find_user( array $credentials, string $field, string $value ): array {
		if ( '' === $value ) {
			return [];
		}

		if ( 'id' === $field ) {
			[ $item, $status ] = self::remote_request(
				$credentials,
				'GET',
				'wp/v2/users/' . (int) $value,
				null,
				[ 'context' => 'edit' ],
				[],
				true
			);

			return 200 === $status && is_array( $item ) ? $item : [];
		}

		[ $items, $status ] = self::remote_request(
			$credentials,
			'GET',
			'wp/v2/users',
			null,
			[
				'search' => $value,
				'context' => 'edit',
				'per_page' => 20
			],
			[],
			true
		);

		if ( 200 !== $status || ! is_array( $items ) ) {
			return [];
		}

		foreach ( $items as $item ) {
			$haystack = 'login' === $field
				? (string) ( $item['username'] ?? '' )
				: (string) ( $item['slug'] ?? '' );

			if ( 'email' === $field ) {
				$haystack = (string) ( $item['email'] ?? '' );
			}

			if ( 0 === strcasecmp( $haystack, $value ) ) {
				return $item;
			}
		}

		return [];
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_user_meta( array $config, array $credentials ): array {
		$user_id = (int) ( $config['user_id'] ?? 0 );
		$key     = (string) ( $config['meta_key'] ?? '' );

		if ( ! $user_id ) {
			return static::error( 'User ID is required' );
		}

		[ $item, $status ] = self::remote_request(
			$credentials,
			'GET',
			'wp/v2/users/' . $user_id,
			null,
			[ 'context' => 'edit' ],
			[],
			true
		);

		if ( 200 !== $status || ! is_array( $item ) ) {
			return static::error( 'User not found' );
		}

		$meta = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : [];

		if ( '' === $key ) {
			return static::success( [
				'user_id' => $user_id,
				'meta' => $meta
			] );
		}

		if ( ! array_key_exists( $key, $meta ) ) {
			return static::error( 'No meta value found for this key' );
		}

		return static::success(
			[
				'user_id'    => $user_id,
				'meta_key'   => $key,
				'meta_value' => $meta[ $key ],
			]
		);
	}

	/**
	 * Only meta the connected site registers with the REST API can be written
	 * this way; WordPress refuses anything else.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_update_user_meta( array $config, array $credentials ): array {
		$user_id = (int) ( $config['user_id'] ?? 0 );
		$key     = (string) ( $config['meta_key'] ?? '' );

		if ( ! $user_id || '' === $key ) {
			return static::error( 'User ID and meta key are required' );
		}

		[ $item ] = self::remote_request(
			$credentials,
			'POST',
			'wp/v2/users/' . $user_id,
			[ 'meta' => [ $key => $config['meta_value'] ?? '' ] ]
		);

		return static::success(
			[
				'user_id'  => $user_id,
				'meta_key' => $key,
				'updated'  => true,
				'user'     => self::remote_map_user( $item ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_create_comment( array $config, array $credentials ): array {
		$body = [
			'content'      => (string) ( $config['content'] ?? '' ),
			'author_name'  => (string) ( $config['author_name'] ?? '' ),
			'author_email' => (string) ( $config['author_email'] ?? '' ),
			'status'       => 'approve',
		];

		$parent = (int) ( $config['parent_id'] ?? 0 );

		if ( $parent ) {
			[ $parent_item ] = self::remote_request( $credentials, 'GET', 'wp/v2/comments/' . $parent );

			if ( empty( $parent_item['id'] ) ) {
				return static::error( 'Parent comment ID ' . $parent . ' not found' );
			}

			$body['post']   = (int) ( $parent_item['post'] ?? 0 );
			$body['parent'] = $parent;
		} else {
			$body['post'] = (int) ( $config['post_id'] ?? 0 );
		}

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/comments', $body );

		$payload = [ 'comment_id' => (int) ( $item['id'] ?? 0 ) ];

		if ( $parent ) {
			$payload['parent_id'] = $parent;
		}

		return static::success( $payload );
	}

	/**
	 * Every moderation step maps onto the comment's status field.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_comment_status( array $config, array $credentials ): array {
		$comment_id = (int) ( $config['comment_id'] ?? 0 );

		if ( ! $comment_id ) {
			return static::error( 'Comment ID is required' );
		}

		$statuses = [
			'approve_comment'     => 'approve',
			'unapproved_comment'  => 'hold',
			'mark_comment_spam'   => 'spam',
			'unmark_comment_spam' => 'hold',
			'restore_comment'     => 'hold',
			'untrash_comment'     => 'hold',
		];

		$event = (string) ( $config['__event'] ?? '' );

		if ( 'trash_comment' === $event ) {
			self::remote_request( $credentials, 'DELETE', 'wp/v2/comments/' . $comment_id, null, [ 'force' => 0 ] );

			return static::success( [
				'comment_id' => $comment_id,
				'status' => 'trash'
			] );
		}

		$status = $statuses[ $event ] ?? 'approve';

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/comments/' . $comment_id, [ 'status' => $status ] );

		return static::success(
			[
				'comment_id' => $comment_id,
				'status'     => (string) ( $item['status'] ?? $status ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_delete_comment( array $config, array $credentials ): array {
		$comment_id = (int) ( $config['comment_id'] ?? 0 );

		if ( ! $comment_id ) {
			return static::error( 'Comment ID is required' );
		}

		self::remote_request( $credentials, 'DELETE', 'wp/v2/comments/' . $comment_id, null, [ 'force' => 1 ] );

		return static::success( [ 'comment_id' => $comment_id ] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_comments( array $config, array $credentials ): array {
		$query = [ 'per_page' => 100 ];

		if ( isset( $config['post_id'] ) && '' !== (string) $config['post_id'] ) {
			$query['post'] = (int) $config['post_id'];
		}

		if ( isset( $config['user_email'] ) ) {
			$query['author_email'] = (string) $config['user_email'];
		}

		[ $items ] = self::remote_request( $credentials, 'GET', 'wp/v2/comments', null, $query );
		$items     = is_array( $items ) ? $items : [];

		$payload = [
			'comments' => $items,
			'count'    => count( $items ),
		];

		if ( isset( $query['post'] ) ) {
			$payload['post_id'] = $query['post'];
		}

		if ( isset( $query['author_email'] ) ) {
			$payload['user_email'] = $query['author_email'];
		}

		return static::success( $payload );
	}

	/**
	 * Which route a term step writes to: the taxonomy it names, or the
	 * categories route for the steps that only ever deal in categories.
	 *
	 * @param string              $event  Step event.
	 * @param array<string,mixed> $config Step configuration.
	 */
	private static function remote_term_route( string $event, array $config ): string {
		if ( in_array( $event, [ 'create_category', 'update_category', 'delete_category', 'get_category', 'get_categories' ], true ) ) {
			return 'categories';
		}

		return (string) ( $config['taxonomy'] ?? '' );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_create_term( array $config, array $credentials ): array {
		$taxonomy = self::remote_term_route( (string) ( $config['__event'] ?? '' ), $config );
		$route    = self::remote_tax_base( $credentials, $taxonomy );
		$name     = (string) ( $config['name'] ?? '' );

		if ( '' === $name ) {
			return static::error( 'Term name is required' );
		}

		$body = [
			'name'        => $name,
			'slug'        => (string) ( $config['slug'] ?? '' ),
			'parent'      => (int) ( $config['parent'] ?? 0 ),
			'description' => (string) ( $config['description'] ?? '' ),
		];

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $route, $body );

		$row = self::remote_map_term( $item );

		if ( 'categories' === $route ) {
			return static::success(
				[
					'category_id' => $row['term_id'],
					'category'    => $row,
				]
			);
		}

		return static::success(
			[
				'term_id'          => $row['term_id'],
				'term_taxonomy_id' => $row['term_taxonomy_id'],
				'term'             => $row,
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_update_term( array $config, array $credentials ): array {
		$taxonomy = self::remote_term_route( (string) ( $config['__event'] ?? '' ), $config );
		$route    = self::remote_tax_base( $credentials, $taxonomy );

		$term_id  = (int) ( $config['term_id'] ?? $config['category_id'] ?? 0 );

		if ( ! $term_id ) {
			return static::error( 'Term ID is required' );
		}

		$body = [
			'name'        => (string) ( $config['name'] ?? '' ),
			'slug'        => (string) ( $config['slug'] ?? '' ),
			'parent'      => (int) ( $config['parent'] ?? 0 ),
			'description' => (string) ( $config['description'] ?? '' ),
		];

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/' . $route . '/' . $term_id, $body );

		$row = self::remote_map_term( $item );

		if ( 'categories' === $route ) {
			return static::success(
				[
					'category_id' => $term_id,
					'category'    => $row,
				]
			);
		}

		return static::success(
			[
				'term_id'          => $row['term_id'],
				'term_taxonomy_id' => $row['term_taxonomy_id'],
				'term'             => $row,
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_delete_term( array $config, array $credentials ): array {
		$taxonomy = self::remote_term_route( (string) ( $config['__event'] ?? '' ), $config );
		$route    = self::remote_tax_base( $credentials, $taxonomy );
		$term_id  = (int) ( $config['term_id'] ?? $config['category_id'] ?? 0 );

		if ( ! $term_id ) {
			return static::error( 'Term ID is required' );
		}

		self::remote_request( $credentials, 'DELETE', 'wp/v2/' . $route . '/' . $term_id, null, [ 'force' => 1 ] );

		if ( 'categories' === $route ) {
			return static::success( [
				'category_id' => $term_id,
				'deleted' => true
			] );
		}

		return static::success( [
			'deleted' => true,
			'term_id' => $term_id
		] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_term( array $config, array $credentials ): array {
		$taxonomy = self::remote_term_route( (string) ( $config['__event'] ?? '' ), $config );
		$route    = self::remote_tax_base( $credentials, $taxonomy );

		$term_id  = (int) ( $config['term_id'] ?? $config['category_id'] ?? 0 );

		if ( $term_id ) {
			[ $item, $status ] = self::remote_request(
				$credentials,
				'GET',
				'wp/v2/' . $route . '/' . $term_id,
				null,
				[],
				[],
				true
			);

			if ( 200 === $status && is_array( $item ) && ! empty( $item['id'] ) ) {
				return static::success( self::remote_term_detail( $route, $item ) );
			}

			return static::error( 'Term not found' );
		}

		$field = (string) ( $config['field'] ?? '' );
		$value = (string) ( $config['value'] ?? '' );

		if ( '' === $field || '' === $value ) {
			return static::error( 'Field and value are required' );
		}

		if ( 'id' === $field ) {
			[ $item, $found ] = self::remote_request(
				$credentials,
				'GET',
				'wp/v2/' . $route . '/' . (int) $value,
				null,
				[],
				[],
				true
			);

			if ( 200 !== $found || ! is_array( $item ) || empty( $item['id'] ) ) {
				return static::error( 'Term not found' );
			}

			return static::success( self::remote_term_detail( $route, $item ) );
		}

		if ( 'slug' === $field ) {
			$found = self::remote_find_term( $credentials, $route, 'slug', $value );
		} else {
			$found = self::remote_find_term( $credentials, $route, 'name', $value );
		}

		if ( empty( $found ) ) {
			return static::error( 'Term not found' );
		}

		return static::success( self::remote_term_detail( $route, $found[0] ) );
	}

	/**
	 * The payload a get_term step returns, keyed the way its local
	 * counterpart keys it.
	 *
	 * @param string              $route Taxonomy REST base.
	 * @param array<string,mixed> $item  REST term payload.
	 * @return array<string,mixed>
	 */
	private static function remote_term_detail( string $route, array $item ): array {
		$row = self::remote_map_term( $item );

		if ( 'categories' === $route ) {
			return [ 'category' => $row ];
		}

		return [ 'term' => $row ];
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_terms( array $config, array $credentials ): array {
		$taxonomy = self::remote_term_route( (string) ( $config['__event'] ?? '' ), $config );
		$route    = self::remote_tax_base( $credentials, $taxonomy );

		$query = [
			'per_page' => ! empty( $config['limit'] ) ? (int) $config['limit'] : 100,
			'hide_empty' => ! empty( $config['hide_empty'] ) ? 1 : 0,
		];

		if ( ! empty( $config['search'] ) ) {
			$query['search'] = (string) $config['search'];
		}

		[ $items, $status ] = self::remote_request( $credentials, 'GET', 'wp/v2/' . $route, null, $query, [], true );
		$items = ( 200 === $status && is_array( $items ) ) ? $items : [];

		$rows = array_map( [ self::class, 'remote_map_term' ], $items );

		if ( 'categories' === $route ) {
			return static::success( [
				'count' => count( $rows ),
				'items' => $rows
			] );
		}

		return static::success( [
			'count' => count( $rows ),
			'items' => $rows
		] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_taxonomies( array $config, array $credentials ): array {
		[ $items, $status ] = self::remote_request( $credentials, 'GET', 'wp/v2/taxonomies', null, [], [], true );

		$items = ( 200 === $status && is_array( $items ) ) ? $items : [];

		$out = [];

		foreach ( $items as $slug => $definition ) {
			$labels = isset( $definition['labels'] ) && is_array( $definition['labels'] ) ? $definition['labels'] : [];

			$out[ $slug ] = [
				'name'         => $slug,
				'label'        => (string) ( $labels['name'] ?? $slug ),
				'object_type'  => isset( $definition['types'] ) && is_array( $definition['types'] ) ? $definition['types'] : [],
				'hierarchical' => (bool) ( $definition['hierarchical'] ?? false ),
				'public'       => (bool) ( $definition['public'] ?? true ),
				'show_ui'      => (bool) ( $definition['show_in_rest'] ?? true ),
				'show_in_rest' => (bool) ( $definition['show_in_rest'] ?? true ),
			];
		}

		$search = strtolower( trim( (string) ( $config['search'] ?? '' ) ) );

		if ( '' !== $search ) {
			$out = array_filter(
				$out,
				static function ( $taxonomy ) use ( $search ) {
					return false !== strpos( strtolower( (string) $taxonomy['name'] ), $search )
						|| false !== strpos( strtolower( (string) $taxonomy['label'] ), $search );
				}
			);
		}

		return static::success( $out );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_taxonomy( array $config, array $credentials ): array {
		$taxonomy = (string) ( $config['taxonomy'] ?? '' );

		[ $items, $status ] = self::remote_request(
			$credentials,
			'GET',
			'wp/v2/taxonomies/' . rawurlencode( $taxonomy ),
			null,
			[],
			[],
			true
		);

		if ( 200 !== $status || ! is_array( $items ) || empty( $items['name'] ) ) {
			return static::error( 'Taxonomy not found' );
		}

		$all = self::remote_get_taxonomies( [ 'search' => '' ], $credentials );
		$row = $all['data'][ $taxonomy ] ?? [];

		return static::success( [ 'taxonomy' => $row ] );
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_add_media_image( array $config, array $credentials ): array {
		$image_url = trim( (string) ( $config['image_url'] ?? '' ) );

		if ( '' === $image_url ) {
			return static::error( 'Image URL is required' );
		}

		$download = wp_remote_get( $image_url, [ 'timeout' => 30 ] );

		if ( is_wp_error( $download ) ) {
			return static::error( 'The image could not be downloaded: ' . $download->get_error_message() );
		}

		$binary = wp_remote_retrieve_body( $download );

		if ( '' === $binary ) {
			return static::error( 'The image could not be downloaded from ' . $image_url );
		}

		$type = wp_remote_retrieve_header( $download, 'content-type' );
		$type = is_array( $type ) ? (string) end( $type ) : (string) $type;

		$path = (string) wp_parse_url( $image_url, PHP_URL_PATH );
		$name = basename( $path );
		$name = (string) preg_replace( '/[^A-Za-z0-9._-]/', '_', (string) $name );
		$name = '' !== $name ? $name : 'image.jpg';

		$item = self::remote_upload_media(
			$credentials,
			$binary,
			$name,
			'' !== $type ? $type : 'application/octet-stream',
			(string) ( $config['image_title'] ?? '' ),
			(string) ( $config['alternative_text'] ?? '' ),
			(string) ( $config['caption'] ?? '' ),
			(string) ( $config['description'] ?? '' )
		);

		return static::success(
			[
				'attachment_id'    => (int) ( $item['id'] ?? 0 ),
				'image_url'        => (string) ( $item['source_url'] ?? '' ),
				'image_title'      => self::remote_text( $item['title'] ?? '' ),
				'alternative_text' => (string) ( $item['alt_text'] ?? '' ),
				'caption'          => self::remote_text( $item['caption'] ?? '' ),
				'description'      => self::remote_text( $item['description'] ?? '' ),
			]
		);
	}

	/**
	 * POST the raw bytes to the media route, then patch the text fields the
	 * upload itself cannot carry.
	 *
	 * Alt text is a query argument because WordPress only reads it while the
	 * attachment is being created; title, caption and description are written
	 * with the second request, which is the only place they are accepted on an
	 * update.
	 *
	 * @param array<string,mixed> $credentials   Connection credentials.
	 * @param string              $binary        File bytes.
	 * @param string              $filename      File name to send.
	 * @param string              $content_type  File's content type.
	 * @param string              $title         Attachment title.
	 * @param string              $alt           Attachment alt text.
	 * @param string              $caption       Attachment caption.
	 * @param string              $description   Attachment description.
	 * @return array<string,mixed>
	 * @throws \Exception When the connected site refuses the upload.
	 */
	private static function remote_upload_media( array $credentials, string $binary, string $filename, string $content_type, string $title, string $alt, string $caption, string $description ): array {
		$query = [];

		if ( '' !== $alt ) {
			$query['alt_text'] = $alt;
		}

		if ( '' !== $title ) {
			$query['title'] = $title;
		}

		[ $item ] = self::remote_request(
			$credentials,
			'POST',
			'wp/v2/media',
			$binary,
			$query,
			[
				'Content-Type'        => $content_type,
				'Content-Disposition' => 'attachment; filename="' . $filename . '"',
			]
		);

		$body = [];

		if ( '' !== $title ) {
			$body['title'] = $title;
		}
		if ( '' !== $caption ) {
			$body['excerpt'] = $caption;
		}
		if ( '' !== $description ) {
			$body['content'] = $description;
		}

		if ( $body && ! empty( $item['id'] ) ) {
			[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/media/' . (int) $item['id'], $body );
		}

		return $item;
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_delete_media( array $config, array $credentials ): array {
		$attachment_id = (int) ( $config['attachment_id'] ?? 0 );

		if ( ! $attachment_id ) {
			return static::error( 'Attachment ID is required' );
		}

		$force = ! empty( $config['force_delete'] );

		self::remote_request( $credentials, 'DELETE', 'wp/v2/media/' . $attachment_id, null, [ 'force' => $force ? 1 : 0 ] );

		return static::success(
			[
				'attachment_id' => $attachment_id,
				'deleted'       => true,
				'force_delete'  => $force,
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_rename_media( array $config, array $credentials ): array {
		$media_id = (int) ( $config['media_id'] ?? 0 );
		$title    = (string) ( $config['new_title'] ?? '' );

		if ( ! $media_id ) {
			return static::error( 'Attachment ID is required' );
		}

		[ $item ] = self::remote_request( $credentials, 'POST', 'wp/v2/media/' . $media_id, [ 'title' => $title ] );

		return static::success(
			[
				'attachment_id' => $media_id,
				'post_title'    => self::remote_text( $item['title'] ?? $title ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_get_media( array $config, array $credentials ): array {
		$media_id = (int) ( $config['media_id'] ?? 0 );

		if ( $media_id ) {
			[ $item, $status ] = self::remote_request(
				$credentials,
				'GET',
				'wp/v2/media/' . $media_id,
				null,
				[ 'context' => 'edit' ],
				[],
				true
			);

			if ( 200 !== $status || ! is_array( $item ) ) {
				return static::error( 'Media not found' );
			}

			return static::success( [ 'media_item' => self::remote_map_media( $item ) ] );
		}

		$query = [ 'per_page' => 100 ];

		if ( ! empty( $config['title'] ) ) {
			$query['search'] = (string) $config['title'];
		}

		$items = self::remote_collect( $credentials, 'wp/v2/media', $query );

		return static::success(
			[
				'media_items' => array_map( [ self::class, 'remote_map_media' ], $items ),
			]
		);
	}

	/**
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_plugin_status( array $config, array $credentials ): array {
		$plugin = trim( (string) ( $config['plugin'] ?? '' ) );

		if ( '' === $plugin ) {
			return static::error( 'Plugin is required' );
		}

		$activate = 'activate_plugin' === ( $config['__event'] ?? '' );
		$status   = $activate ? 'active' : 'inactive';

		[ $item ] = self::remote_request(
			$credentials,
			'POST',
			'wp/v2/plugins/' . $plugin,
			[ 'status' => $status ]
		);

		return static::success(
			[
				'plugin' => (string) ( $item['plugin'] ?? $plugin ),
				'status' => $activate ? 'activated' : 'deactivated',
			]
		);
	}

	/**
	 * Only settings the connected site registers with the REST API can be
	 * written; /wp/v2/settings is a closed list, so an option outside it is
	 * reported rather than silently dropped.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_update_option( array $config, array $credentials ): array {
		$name = (string) ( $config['option_name'] ?? '' );

		if ( '' === $name ) {
			return static::error( 'Option name is required' );
		}

		[ $settings, $status ] = self::remote_request( $credentials, 'GET', 'wp/v2/settings', null, [], [], true );

		if ( 200 !== $status || ! is_array( $settings ) || ! array_key_exists( $name, $settings ) ) {
			return static::error(
				sprintf(
					'The setting "%s" is not exposed by the REST API on the connected site, so it cannot be written from here.',
					$name
				)
			);
		}

		self::remote_request( $credentials, 'POST', 'wp/v2/settings', [ $name => $config['value'] ?? '' ] );

		return static::success( [ 'option_name' => $name ] );
	}

	/**
	 * Adding, removing and setting a user's roles all end the same way: the
	 * full list of roles they should keep.
	 *
	 * @param array<string,mixed> $config      Step configuration.
	 * @param array<string,mixed> $credentials Connection credentials.
	 * @return array<string,mixed>
	 */
	private static function remote_user_roles( array $config, array $credentials ): array {
		$user_id = (int) ( $config['user_id'] ?? 0 );
		$role    = sanitize_key( (string) ( $config['role'] ?? '' ) );

		if ( ! $user_id ) {
			return static::error( 'User ID is required' );
		}

		if ( '' === $role ) {
			return static::error( 'Role is required' );
		}

		[ $item, $status ] = self::remote_request(
			$credentials,
			'GET',
			'wp/v2/users/' . $user_id,
			null,
			[ 'context' => 'edit' ],
			[],
			true
		);

		if ( 200 !== $status || ! is_array( $item ) ) {
			return static::error( 'User not found' );
		}

		$event   = (string) ( $config['__event'] ?? '' );
		$current = isset( $item['roles'] ) && is_array( $item['roles'] ) ? $item['roles'] : [];

		if ( 'remove_user_role' === $event ) {
			$next = array_values( array_diff( $current, [ $role ] ) );
		} elseif ( 'add_user_role' === $event ) {
			$next = array_values( array_unique( array_merge( $current, [ $role ] ) ) );
		} else {
			$next = [ $role ];
		}

		if ( $next === $current ) {
			return static::success( [
				'user_id' => $user_id,
				'roles' => $current
			] );
		}

		[ $updated ] = self::remote_request( $credentials, 'POST', 'wp/v2/users/' . $user_id, [ 'roles' => $next ] );

		return static::success(
			[
				'user_id' => $user_id,
				'roles'   => isset( $updated['roles'] ) && is_array( $updated['roles'] ) ? $updated['roles'] : $next,
			]
		);
	}
}
