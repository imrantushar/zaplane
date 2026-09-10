<?php

namespace Zaplane\Mcp;

use Zaplane\Framework\Database\ORM\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A record of what each AI client actually did.
 *
 * The endpoint issues credentials that build workflows and fire them for real —
 * sending mail, taking payments. "Some token did it" is not an answer when
 * tokens are revoked and reissued, and a client that misbehaves needs to be
 * identifiable after the fact rather than during.
 *
 * What is deliberately absent: the arguments. A tools/call carries whatever the
 * model was working with — customer records, message bodies, credentials pasted
 * into a field — and copying that into a table nobody remembers to prune turns
 * an audit trail into a second, quieter database of everything. Who, what, and
 * how it went is enough to answer the question this exists to answer.
 */
class AuditLog {

	/** Rows kept. Older ones are dropped as new ones arrive. */
	private const KEEP = 2000;

	/** Prune roughly once in this many writes, rather than on every call. */
	private const PRUNE_EVERY = 50;

	public const OK      = 'ok';
	public const FAILED  = 'failed';
	public const REFUSED = 'refused';

	/**
	 * Record one tool call.
	 *
	 * Never throws: a failure to write history must not fail the thing being
	 * recorded.
	 *
	 * @param array<string,mixed> $token    The resolved token record.
	 * @param string              $tool     Tool name.
	 * @param string              $outcome  One of the class constants.
	 * @param string              $detail   Short reason on failure; never arguments.
	 * @param int                 $duration Milliseconds the call took.
	 */
	public static function record( array $token, string $tool, string $outcome, string $detail = '', int $duration = 0 ): void {
		try {
			DB::table( 'mcp_audit' )->insert(
				[
					'token_id'    => substr( (string) ( $token['id'] ?? '' ), 0, 64 ),
					'token_name'  => substr( (string) ( $token['name'] ?? '' ), 0, 191 ),
					'client_id'   => substr( (string) ( $token['client_id'] ?? '' ), 0, 64 ),
					'user_id'     => (int) ( $token['user_id'] ?? 0 ),
					'tool'        => substr( $tool, 0, 191 ),
					'outcome'     => $outcome,
					// Truncated hard: a message can carry an echo of the input.
					'detail'      => '' === $detail ? null : substr( $detail, 0, 300 ),
					'duration_ms' => $duration > 0 ? $duration : null,
					'created_at'  => current_time( 'mysql' ),
				]
			);

			self::maybe_prune();
		} catch ( \Throwable $e ) {
			// Recording is not the job. Losing a row is better than losing a call.
			return;
		}
	}

	/**
	 * The most recent entries, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50 ): array {
		try {
			return DB::table( 'mcp_audit' )
				->orderBy( 'id', 'DESC' )
				->limit( max( 1, min( 200, $limit ) ) )
				->fresh()
				->get()
				->toArray();
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/** Wipe the trail. Only an administrator asking for it should reach this. */
	public static function clear(): void {
		try {
			DB::table( 'mcp_audit' )->truncate();
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * Keep the table bounded without a scheduled job.
	 *
	 * Pruning on every write would put a delete in front of every tool call for
	 * no benefit; a busy client overshoots the cap briefly instead.
	 */
	private static function maybe_prune(): void {
		if ( 0 !== wp_rand( 0, self::PRUNE_EVERY - 1 ) ) {
			return;
		}

		global $wpdb;

		$table = \Zaplane\Framework\Database\ORM\Schema::getTable( 'mcp_audit' );
		$floor = $wpdb->get_var( "SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET " . (int) self::KEEP ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is internal; the offset is an int constant.

		if ( $floor ) {
			DB::table( 'mcp_audit' )->where( 'id', '<=', (int) $floor )->delete();
		}
	}
}
