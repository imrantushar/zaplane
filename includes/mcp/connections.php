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

	public static function available(): bool {
		return function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();
	}

	/**
	 * Core's own consent screen, asked to come back here afterwards.
	 *
	 * Core appends site_url, user_login and password to the success URL, so the
	 * screen that returns has to strip them before anything writes them down.
	 *
	 * @param string $label      What the connection will be called.
	 * @param string $return_url Where to send the browser once approved.
	 */
	public static function authorize_url( string $label, string $return_url ): string {
		return add_query_arg(
			[
				'app_name'    => rawurlencode( $label ),
				'app_id'      => self::APP_ID,
				'success_url' => rawurlencode( $return_url ),
				'reject_url'  => rawurlencode( remove_query_arg( 'zaplane_connect', $return_url ) ),
			],
			admin_url( 'authorize-application.php' )
		);
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
