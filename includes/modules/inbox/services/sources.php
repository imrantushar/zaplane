<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Channels\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sources that reach the inbox through workflows: WordPress comments, a
 * form, a helpdesk… anything a workflow can read. A workflow's "Add Incoming
 * Message" step names the source; the inbox treats it as a channel, and a
 * reply from the team goes back out through the "Reply to Deliver" trigger,
 * so a workflow posts it wherever it belongs.
 *
 * Nothing here knows about any particular source: recipes wire them up.
 */
class Sources {

	public const OPTION = 'zaplane_inbox_sources';

	/** Who answers new conversations from a source: the team unless changed. */
	public const DEFAULT_ANSWERER = 'team';

	/**
	 * @return array<string,array{label:string,answered_by:string,first_at:int}>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, [] );
		return is_array( $saved ) ? $saved : [];
	}

	public static function is( string $slug ): bool {
		return isset( self::all()[ $slug ] );
	}

	public static function label( string $slug ): string {
		return (string) ( self::all()[ $slug ]['label'] ?? $slug );
	}

	public static function answered_by( string $slug ): string {
		return (string) ( self::all()[ $slug ]['answered_by'] ?? self::DEFAULT_ANSWERER );
	}

	/**
	 * A clean source slug, or '' when it can't be one (empty, or the name of
	 * a built-in channel).
	 */
	public static function slug( string $raw ): string {
		$slug = substr( sanitize_key( str_replace( [ ' ', '-' ], '_', strtolower( $raw ) ) ), 0, 30 );
		if ( '' === $slug ) {
			return '';
		}
		$builtin = array_keys( Registry::builtin() );
		return in_array( $slug, $builtin, true ) ? '' : $slug;
	}

	/**
	 * Record a source the first time a workflow uses it, and keep its label
	 * current.
	 */
	public static function remember( string $slug, string $label ): void {
		$all   = self::all();
		$label = mb_substr( sanitize_text_field( '' !== trim( $label ) ? $label : ucwords( str_replace( '_', ' ', $slug ) ) ), 0, 40 );
		if ( isset( $all[ $slug ] ) && $all[ $slug ]['label'] === $label ) {
			return;
		}
		$all[ $slug ] = [
			'label'       => $label,
			'answered_by' => (string) ( $all[ $slug ]['answered_by'] ?? self::DEFAULT_ANSWERER ),
			'first_at'    => (int) ( $all[ $slug ]['first_at'] ?? time() ),
		];
		update_option( self::OPTION, $all, false );
	}

	/**
	 * @param array<string,string> $answerers slug => assistant|workflows|team
	 */
	public static function save_answerers( array $answerers ): void {
		$all = self::all();
		foreach ( $answerers as $slug => $who ) {
			if ( isset( $all[ $slug ] ) && in_array( $who, [ 'assistant', 'workflows', 'team' ], true ) ) {
				$all[ $slug ]['answered_by'] = $who;
			}
		}
		update_option( self::OPTION, $all, false );
	}

	public static function forget( string $slug ): void {
		$all = self::all();
		unset( $all[ $slug ] );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * For the admin: each source with how many conversations it has and
	 * whether a workflow is there to deliver replies.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_admin(): array {
		global $wpdb;
		$out = [];
		foreach ( self::all() as $slug => $source ) {
			$out[] = [
				'slug'          => $slug,
				'label'         => (string) $source['label'],
				'answered_by'   => (string) $source['answered_by'],
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'conversations' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE channel = %s', \Zaplane\Modules\Inbox\Models\Conversation::getTable(), $slug ) ),
				'delivers'      => self::has_delivery(),
			];
		}
		return $out;
	}

	/**
	 * Whether any active workflow starts on "Reply to Deliver". Triggers hook
	 * in only for active workflows, so a listener means one is live.
	 */
	public static function has_delivery(): bool {
		return (bool) has_action( 'zaplane/inbox/reply_requested' );
	}
}
