<?php

namespace Zaplane\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credentials people paste into a client, kept where WordPress already keeps
 * them.
 *
 * WordPress has issued application passwords since 5.6: a consent screen, a
 * hashed store, an authentication path on every REST request, and a revoke
 * button on a profile page people already know. Zaplane had a second one of
 * each. This is the thin layer that lets the AI access screen drive core's,
 * rather than a store of its own.
 *
 * Nothing here holds a credential. Core creates it, core hashes it, core hands
 * it to the browser once, and core forgets it — the same as for any other
 * application.
 *
 * OAuth is untouched. A hosted connector has no field for a username and a
 * password, which is the whole reason that flow exists; its grants still live
 * in TokenStore.
 *
 * @see \Zaplane\Mcp\TokenStore for OAuth-issued grants.
 */
class Connections {

	/**
	 * Stamped on every credential made through this screen, so Zaplane's own
	 * connections can be told apart from the ones a person made by hand.
	 *
	 * A fixed value, not a generated one: it identifies the application, not the
	 * install.
	 */
	private const APP_ID = 'b3f7c4de-6f2f-4a1b-9d6e-7a5c1f0e2d84';

	/**
	 * Whether this person can be issued one.
	 *
	 * Core asks the same question of its own screen, per user rather than per
	 * site: a site can allow application passwords generally and still withhold
	 * them from a role. Asking only the site-wide question would offer a button
	 * whose destination refuses.
	 */
	public static function boot(): void {
		add_action( 'wp_authorize_application_password_form', [ self::class, 'say_what_it_is_for' ], 10, 1 );
	}

	/**
	 * Say what this credential is for, on the screen where somebody decides.
	 *
	 * Core's consent screen asks for "access to your account", which is accurate
	 * and tells you nothing: it is one screen serving every application, and it
	 * cannot know what any of them intends. Zaplane does, and this is the only
	 * moment the answer is worth anything.
	 *
	 * Carefully worded as what Zaplane will do with it, never as what the
	 * credential is limited to. An application password authenticates every REST
	 * route on the site, not only this plugin's — capping MCP at read and write
	 * caps Zaplane, not the password. Saying otherwise here would be a promise
	 * this code is in no position to keep.
	 *
	 * @param array<string,mixed> $request The application's request.
	 */
	public static function say_what_it_is_for( $request ): void {
		if ( self::APP_ID !== ( is_array( $request ) ? ( $request['app_id'] ?? '' ) : '' ) ) {
			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__(
				'Zaplane will use this to let an AI client read your workflows and build new ones. It will not start one for real — that needs a separate approval. Like any application password, this is a sign-in to your whole account, so treat it as you would your own password.',
				'zaplane'
			)
		);
	}

	public static function available(): bool {
		return function_exists( 'wp_is_application_passwords_available_for_user' )
			&& wp_is_application_passwords_available_for_user( get_current_user_id() );
	}

	/**
	 * Core's own consent screen, asked to come back here afterwards.
	 *
	 * Core appends site_url, user_login and password to the success URL, so the
	 * screen that returns has to strip them before anything writes them down.
	 *
	 * The two return addresses are marked differently, because core says nothing
	 * about which one it took: approving arrives with a credential, declining
	 * arrives with the address unchanged, and without a marker the screen cannot
	 * tell somebody who changed their mind from somebody who just opened it.
	 *
	 * Both are held to this site first. Core sends to any domain on purpose —
	 * an application password is often for something elsewhere, and it says so
	 * in a comment where it declines to use wp_safe_redirect. This screen has no
	 * such reason: it always comes back to wp-admin, so a return address that
	 * points anywhere else is not one this panel asked for, and a freshly minted
	 * password is what would follow it.
	 *
	 * @param string $label      What the connection will be called.
	 * @param string $return_url Where to send the browser once approved.
	 */
	public static function authorize_url( string $label, string $return_url ): string {
		$return_url = wp_validate_redirect( $return_url, self::panel_url() );

		return add_query_arg(
			[
				'app_name'    => rawurlencode( $label ),
				'app_id'      => self::APP_ID,
				'success_url' => rawurlencode( add_query_arg( 'zaplane_connect', 'done', $return_url ) ),
				'reject_url'  => rawurlencode( add_query_arg( 'zaplane_connect', 'cancelled', $return_url ) ),
			],
			admin_url( 'authorize-application.php' )
		);
	}

	/**
	 * The screen this flow belongs to, for when the caller names somewhere else.
	 */
	private static function panel_url(): string {
		return admin_url( 'admin.php?page=' . ZAPLANE_PLUGIN_SLUG . '-settings&tab=mcp' );
	}

	/**
	 * The connections made through this screen, for whoever is looking.
	 *
	 * Core keeps application passwords per user, so this is one person's list —
	 * the same scope as their profile page. A colleague's connection is theirs
	 * to see and to revoke.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		if ( ! class_exists( '\WP_Application_Passwords' ) ) {
			return [];
		}

		$user_id = get_current_user_id();
		$rows    = \WP_Application_Passwords::get_user_application_passwords( $user_id );

		$ours = array_filter( (array) $rows, fn( $r ) => self::APP_ID === ( $r['app_id'] ?? '' ) );

		return array_values(
			array_map(
				fn( $r ) => [
					'uuid'      => (string) $r['uuid'],
					'name'      => (string) $r['name'],
					'created'   => (int) ( $r['created'] ?? 0 ),
					'last_used' => (int) ( $r['last_used'] ?? 0 ),
					// An application password is the whole user, so it cannot carry
					// less than the user has — but it is never given `run`.
					'scopes'    => TokenStore::DEFAULT_SCOPES,
				],
				$ours
			)
		);
	}

	/**
	 * Revoke one, through core.
	 *
	 * @param string $uuid The application password's identifier.
	 */
	public static function forget( string $uuid ): bool {
		if ( ! class_exists( '\WP_Application_Passwords' ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		$record  = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );

		// Only the ones made here, so this screen cannot delete a credential
		// somebody created for something else entirely.
		if ( ! $record || self::APP_ID !== ( $record['app_id'] ?? '' ) ) {
			return false;
		}

		return true === \WP_Application_Passwords::delete_application_password( $user_id, $uuid );
	}
}
