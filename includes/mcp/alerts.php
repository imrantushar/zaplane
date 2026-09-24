<?php

namespace Zaplane\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells someone when an AI client does something worth knowing about.
 *
 * The audit log answers "what happened" for anyone who thinks to look. These
 * are the two cases where waiting to be asked is too late: the first time a
 * credential actually spends money, and a client repeatedly reaching for
 * something it was never given.
 *
 * Deliberately only two. A notice that arrives constantly is a notice nobody
 * reads, and the log is there for everything else.
 */
class Alerts {

	private const OPTION = 'zaplane_mcp_alerts';

	/** Refusals from one token within this window before it is worth saying so. */
	private const REFUSAL_WINDOW = 10 * MINUTE_IN_SECONDS;
	private const REFUSAL_LIMIT  = 5;

	/** At most one refusal notice per token per hour, however bad it gets. */
	private const REFUSAL_QUIET = HOUR_IN_SECONDS;

	/**
	 * Stored as '1'/'0' rather than a boolean on purpose.
	 *
	 * WordPress writes nothing when update_option() is handed a value matching
	 * the current one — and for an option that does not exist yet, get_option()
	 * answers false. Storing false is therefore a no-op: the option is never
	 * created, reading it keeps returning the default, and switching this off
	 * silently did nothing at all.
	 */
	public static function enabled(): bool {
		return '0' !== (string) get_option( self::OPTION, '1' );
	}

	public static function set_enabled( bool $on ): void {
		update_option( self::OPTION, $on ? '1' : '0', false );
	}

	/**
	 * Decide whether this call is worth an email. Called after it is recorded.
	 *
	 * Never throws: an alert failing must not fail the call it describes.
	 *
	 * @param array<string,mixed> $token   The resolved token.
	 * @param string              $tool    Tool name.
	 * @param string              $outcome One of the AuditLog constants.
	 */
	public static function consider( array $token, string $tool, string $outcome ): void {
		if ( ! self::enabled() ) {
			return;
		}

		try {
			if ( AuditLog::OK === $outcome && 'run_workflow' === $tool ) {
				self::first_run( $token );
				return;
			}

			if ( AuditLog::REFUSED === $outcome ) {
				self::repeated_refusals( $token );
			}
		} catch ( \Throwable $e ) {
			return;
		}//end try
	}

	/**
	 * The first time a token actually starts a workflow for real.
	 *
	 * Issuing a run-scoped token is a decision someone made once, possibly weeks
	 * ago and possibly without reading the third checkbox. The moment it is used
	 * is when they would want to know.
	 *
	 * @param array<string,mixed> $token
	 */
	private static function first_run( array $token ): void {
		$key = 'zaplane_mcp_ran_' . md5( (string) $token['id'] );

		if ( get_transient( $key ) ) {
			return;
		}

		// A year, so "first" means first, not first this week.
		set_transient( $key, 1, YEAR_IN_SECONDS );

		self::send(
			__( 'An AI client ran a workflow on your site', 'zaplane' ),
			sprintf(
				/* translators: 1: token name, 2: site name. */
				__( '"%1$s" just started a workflow on %2$s for real — that means any mail, payments or posts in it have happened.', 'zaplane' ),
				(string) $token['name'],
				get_bloginfo( 'name' )
			)
		);
	}

	/**
	 * A client reaching for something it was never granted, repeatedly.
	 *
	 * One refusal is a client discovering its limits. Five in ten minutes is
	 * either something misconfigured or something trying.
	 *
	 * @param array<string,mixed> $token
	 */
	private static function repeated_refusals( array $token ): void {
		$id    = md5( (string) $token['id'] );
		$count = 'zaplane_mcp_refused_' . $id;
		$quiet = 'zaplane_mcp_refused_said_' . $id;

		$seen = (int) get_transient( $count ) + 1;
		set_transient( $count, $seen, self::REFUSAL_WINDOW );

		if ( $seen < self::REFUSAL_LIMIT || get_transient( $quiet ) ) {
			return;
		}

		set_transient( $quiet, 1, self::REFUSAL_QUIET );

		self::send(
			__( 'An AI client is being refused repeatedly', 'zaplane' ),
			sprintf(
				/* translators: 1: number of refusals, 2: token name, 3: site name. */
				__( '"%2$s" has been refused %1$d times in ten minutes on %3$s — asking for a scope or a workflow it was not given. Zaplane → Logs → AI access has the detail.', 'zaplane' ),
				$seen,
				(string) $token['name'],
				get_bloginfo( 'name' )
			)
		);
	}

	private static function send( string $subject, string $body ): void {
		$to = get_option( 'admin_email' );

		if ( ! is_email( $to ) ) {
			return;
		}

		wp_mail(
			$to,
			'[' . get_bloginfo( 'name' ) . '] ' . $subject,
			$body . "\n\n" . admin_url( 'admin.php?page=' . ZAPLANE_PLUGIN_SLUG . '-logs' )
		);
	}
}
