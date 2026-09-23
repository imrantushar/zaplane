<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the chat is staffed right now, and what to tell the visitor when
 * it is not.
 *
 * Two things decide it, and both have to agree: hours and presence. Each
 * agent works the company's opening hours or hours of their own; the chat is
 * open while anyone is on duty, and online while someone on duty has the
 * inbox open. Off duty, nobody is online however many inboxes are open — that
 * is the point of having hours.
 */
class Availability {

	public const DAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

	/**
	 * @return array<string,mixed>
	 */
	public static function hours(): array {
		$saved = InboxSettings::get()['hours'] ?? [];
		$saved = is_array( $saved ) ? $saved : [];

		$days = [];
		foreach ( self::DAYS as $day ) {
			$in           = is_array( $saved['days'][ $day ] ?? null ) ? $saved['days'][ $day ] : [];
			$days[ $day ] = [
				'closed' => ! empty( $in['closed'] ),
				'open'   => self::clock( (string) ( $in['open'] ?? '09:00' ), '09:00' ),
				'close'  => self::clock( (string) ( $in['close'] ?? '17:00' ), '17:00' ),
			];
		}

		return [
			'enabled' => ! empty( $saved['enabled'] ),
			'days'    => $days,
			'away_message' => (string) ( $saved['away_message'] ?? '' ),
			'reply_time'   => (string) ( $saved['reply_time'] ?? '' ),
		];
	}

	/** "9:5" or nonsense becomes a real HH:MM. */
	private static function clock( string $value, string $fallback ): string {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
	}

	/**
	 * How the chat stands right now.
	 *
	 * @return array{online:bool,reason:string,message:string,reply_time:string,opens_at:string}
	 */
	public static function state(): array {
		$hours = self::hours();
		$team  = self::visible_agents();
		// Nobody to go by: the company's hours alone decide.
		$open = $team
			? (bool) array_filter( $team, [ self::class, 'on_duty' ] )
			: ( ! $hours['enabled'] || self::within_hours( $hours['days'] ) );

		if ( ! $open ) {
			return [
				'online'     => false,
				'reason'     => 'closed',
				'message'    => '' !== $hours['away_message'] ? $hours['away_message'] : __( "We're closed right now. Leave a message and we'll reply when we're back.", 'zaplane' ),
				'reply_time' => $hours['reply_time'],
				'opens_at'   => self::opens_at( $hours, $team ),
			];
		}

		if ( Agents::anyone_online() ) {
			return [
				'online'     => true,
				'reason'     => 'agents',
				'message'    => '',
				'reply_time' => $hours['reply_time'],
				'opens_at'   => '',
			];
		}

		return [
			'online'     => false,
			'reason'     => 'away',
			'message'    => '' !== $hours['away_message'] ? $hours['away_message'] : __( "We're away at the moment. Leave a message and we'll get back to you.", 'zaplane' ),
			'reply_time' => $hours['reply_time'],
			'opens_at'   => '',
		];
	}

	/**
	 * Within this agent's hours right now: their own, or the company's.
	 */
	public static function on_duty( int $user_id ): bool {
		$schedule = Agents::schedule( $user_id );
		if ( 'custom' === $schedule['mode'] ) {
			return self::within_hours( $schedule['days'] );
		}
		$hours = self::hours();
		return ! $hours['enabled'] || self::within_hours( $hours['days'] );
	}

	/**
	 * Agents a visitor can be answered by (not hidden from the chat).
	 *
	 * @return int[]
	 */
	private static function visible_agents(): array {
		return array_values( array_filter( Agents::member_ids(), static fn( $id ) => ! Agents::identity( $id )['hidden'] ) );
	}

	/**
	 * The soonest anyone comes on duty.
	 *
	 * @param array<string,mixed> $hours
	 * @param int[]               $team
	 */
	private static function opens_at( array $hours, array $team ): string {
		if ( ! $team ) {
			return self::next_opening( $hours['days'] );
		}
		$soonest = '';
		foreach ( $team as $user_id ) {
			$schedule = Agents::schedule( $user_id );
			$days     = 'custom' === $schedule['mode'] ? $schedule['days'] : ( $hours['enabled'] ? $hours['days'] : null );
			if ( null === $days ) {
				continue;
			}
			$at = self::next_opening( $days );
			if ( '' !== $at && ( '' === $soonest || strtotime( $at ) < strtotime( $soonest ) ) ) {
				$soonest = $at;
			}
		}
		return $soonest;
	}

	/**
	 * @param array<string,array{closed:bool,open:string,close:string}> $days
	 */
	private static function within_hours( array $days ): bool {
		$now  = self::now();
		$day  = $days[ self::DAYS[ (int) $now->format( 'N' ) - 1 ] ] ?? [ 'closed' => true, 'open' => '00:00', 'close' => '00:00' ];
		if ( $day['closed'] ) {
			return false;
		}

		$minutes = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );
		$from    = self::minutes( $day['open'] );
		$to      = self::minutes( $day['close'] );

		// A day that closes before it opens runs past midnight (22:00 → 02:00).
		return $from <= $to
			? ( $minutes >= $from && $minutes < $to )
			: ( $minutes >= $from || $minutes < $to );
	}

	/**
	 * When the chat opens again, as an ISO time in the site's zone. Empty when
	 * it is open, or when every day is closed.
	 *
	 * @param array<string,array{closed:bool,open:string,close:string}> $days
	 */
	private static function next_opening( array $days ): string {
		$now = self::now();

		for ( $ahead = 0; $ahead <= 7; $ahead++ ) {
			$day  = $now->modify( sprintf( '+%d days', $ahead ) );
			$spec = $days[ self::DAYS[ (int) $day->format( 'N' ) - 1 ] ] ?? null;
			if ( ! $spec || $spec['closed'] ) {
				continue;
			}
			$opens = $day->setTime( (int) substr( $spec['open'], 0, 2 ), (int) substr( $spec['open'], 3, 2 ) );
			if ( $opens > $now ) {
				return $opens->format( 'c' );
			}
		}

		return '';
	}

	private static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', wp_timezone() );
	}

	private static function minutes( string $clock ): int {
		return (int) substr( $clock, 0, 2 ) * 60 + (int) substr( $clock, 3, 2 );
	}

	/**
	 * Everything the widget needs to show who is there: the state, the agents
	 * to show, and the assistant's identity.
	 *
	 * Cached briefly — every open chat asks for this on a timer, and it only
	 * changes when somebody opens or closes their inbox.
	 *
	 * @return array<string,mixed>
	 */
	public static function team(): array {
		$cached = get_transient( 'zaplane_inbox_team' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$state = self::state();
		$team  = [
			'online'     => $state['online'],
			'reason'     => $state['reason'],
			'message'    => $state['message'],
			'reply_time' => $state['reply_time'],
			'opens_at'   => $state['opens_at'],
			'agents'     => Agents::for_widget(),
			'assistant'  => Agents::assistant(),
		];

		set_transient( 'zaplane_inbox_team', $team, 20 );

		return $team;
	}

	/** Forget the cached team state (an agent came online, settings changed). */
	public static function forget(): void {
		delete_transient( 'zaplane_inbox_team' );
	}
}
