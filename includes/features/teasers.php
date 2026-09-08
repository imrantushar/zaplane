<?php

namespace Zaplane\Features;

use Zaplane\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * In-product prompts that tell an existing user a module exists.
 *
 * Someone who installed Zaplane for one workflow has no reason to open Settings
 * again, so a module added later is invisible to exactly the people it would
 * help. These put a short, dismissible card on the screen where the module would
 * have been useful — the AI-access prompt on Workflows, Custom Apps on
 * Connections — rather than relying on anyone reading a changelog.
 *
 * Relevance is a predicate, not just "is the module off". Two of the three
 * modules ship enabled, so an off-switch gate would have meant their prompts
 * never appeared; what actually makes them worth advertising is that the module
 * is on and nobody has used it yet. Either way the prompt retires itself once
 * the thing it suggests has happened, so acting on it is also how it goes away.
 *
 * Dismissals are per user — one administrator hiding a card should not hide it
 * for their colleagues. At most one teaser shows per screen.
 */
class Teasers {

	private const USER_META = 'zaplane_dismissed_teasers';

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function registry(): array {
		$teasers = [
			'mcp_on_workflows'           => [
				'key'         => 'mcp_on_workflows',
				'screen'      => 'workflows',
				'module'      => 'mcp_server',
				'title'       => __( 'Describe an automation and let AI build it', 'zaplane' ),
				'body'        => __( 'Connect Claude, Cursor or any Model Context Protocol client and it can search every trigger and action on this site, then hand you a ready-made draft workflow. Useful when you know what you want but not which of the hundreds of steps does it.', 'zaplane' ),
				'cta_label'   => __( 'Turn on AI access', 'zaplane' ),
				// Modules, not the AI-access panel: that panel does not exist until
				// the module is on, which is the thing this is asking for.
				'cta_panel'   => 'modules',
				'relevant'    => static fn(): bool => ! Settings::feature_enabled( 'mcp_server' ),
			],
			'custom_apps_on_connections' => [
				'key'         => 'custom_apps_on_connections',
				'screen'      => 'connections',
				'module'      => 'custom_apps',
				'title'       => __( 'Missing an app you need?', 'zaplane' ),
				'body'        => __( 'Custom Apps lets you add your own integration from the UI — any external REST API, or a hook on this site — without touching plugin files. It then behaves like any other app on the canvas.', 'zaplane' ),
				'cta_label'   => __( 'Build a custom app', 'zaplane' ),
				'cta_panel'   => 'modules',
				// Worth saying while nobody has built one — whether that is because
				// the module is off, or on and untouched.
				'relevant'    => static fn(): bool => ! Settings::feature_enabled( 'custom_apps' ) || ! self::has_custom_apps(),
			],
			'knowledge_on_dashboard'     => [
				'key'         => 'knowledge_on_dashboard',
				'screen'      => 'dashboard',
				'module'      => 'knowledge',
				'title'       => __( 'Teach the AI Agent about your business', 'zaplane' ),
				'body'        => __( 'Business Knowledge syncs your products, docs or policies into a searchable base the AI Agent answers from, so replies quote your catalogue instead of guessing. Nothing has been synced yet.', 'zaplane' ),
				'cta_label'   => __( 'Add business knowledge', 'zaplane' ),
				'cta_panel'   => 'modules',
				'relevant'    => static fn(): bool => ! Settings::feature_enabled( 'knowledge' ) || ! self::has_knowledge(),
			],
		];

		/**
		 * Filter the registered feature teasers.
		 *
		 * A teaser is an array of key, screen, module, title, body, cta_label,
		 * cta_panel and `relevant` — a callable returning whether it is currently
		 * worth showing.
		 *
		 * @param array<string,array<string,mixed>> $teasers
		 */
		return (array) apply_filters( 'zaplane/feature_teasers', $teasers );
	}

	/**
	 * The teaser to show on a screen for the current user, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function for_screen( string $screen ): ?array {
		if ( '' === $screen ) {
			return null;
		}

		$dismissed = self::dismissed();

		foreach ( self::registry() as $key => $teaser ) {
			if ( ( $teaser['screen'] ?? '' ) !== $screen ) {
				continue;
			}

			if ( in_array( (string) $key, $dismissed, true ) ) {
				continue;
			}

			if ( ! self::is_relevant( $teaser ) ) {
				continue;
			}

			// Only the display fields cross the wire; `relevant` is a callable.
			return [
				'key'       => (string) $key,
				'screen'    => (string) $teaser['screen'],
				'module'    => (string) ( $teaser['module'] ?? '' ),
				'title'     => (string) $teaser['title'],
				'body'      => (string) $teaser['body'],
				'cta_label' => (string) ( $teaser['cta_label'] ?? '' ),
				'cta_panel' => (string) ( $teaser['cta_panel'] ?? '' ),
			];
		}

		return null;
	}

	/**
	 * Every teaser currently visible to this user, keyed by screen.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all_visible(): array {
		$out = [];

		foreach ( self::registry() as $teaser ) {
			$screen = (string) ( $teaser['screen'] ?? '' );
			if ( '' === $screen || isset( $out[ $screen ] ) ) {
				continue;
			}

			$visible = self::for_screen( $screen );
			if ( null !== $visible ) {
				$out[ $screen ] = $visible;
			}
		}

		return $out;
	}

	/** Hide one teaser for the current user. False when the key is unknown. */
	public static function dismiss( string $key ): bool {
		if ( ! isset( self::registry()[ $key ] ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$dismissed = self::dismissed();
		if ( in_array( $key, $dismissed, true ) ) {
			return true;
		}

		$dismissed[] = $key;

		update_user_meta( $user_id, self::USER_META, array_values( $dismissed ) );

		return true;
	}

	/* --------------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $teaser
	 */
	private static function is_relevant( array $teaser ): bool {
		$relevant = $teaser['relevant'] ?? null;

		if ( ! is_callable( $relevant ) ) {
			return true;
		}

		// A predicate that reaches for data (a table that has not been created on a
		// half-migrated install, say) must not take the whole screen down with it.
		try {
			return (bool) $relevant();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private static function has_custom_apps(): bool {
		if ( ! class_exists( '\Zaplane\CustomApps\ManifestStore' ) ) {
			return false;
		}

		return ! empty( \Zaplane\CustomApps\ManifestStore::all() );
	}

	private static function has_knowledge(): bool {
		if ( ! class_exists( '\Zaplane\Models\Knowledge' ) ) {
			return false;
		}

		return \Zaplane\Models\Knowledge::count() > 0;
	}

	/**
	 * @return array<int,string>
	 */
	private static function dismissed(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return [];
		}

		$raw = get_user_meta( $user_id, self::USER_META, true );

		return is_array( $raw ) ? array_values( array_filter( array_map( 'strval', $raw ) ) ) : [];
	}
}
