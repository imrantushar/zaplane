<?php

namespace Zaplane\Features;

use Zaplane\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells people what Zaplane can do that they have not switched on.
 *
 * Modules are opt-in, so a fresh site has every optional feature off and no
 * reason to discover any of them: someone who installed Zaplane for one workflow
 * will not reopen Settings and will not read a changelog. Two answers to that,
 * for two different moments.
 *
 * spotlight() is the browsing case — one card listing every module that is off,
 * each with a one-click switch. Showing them together rather than one at a time
 * is the point: the question a new user has is "what does this thing do", not
 * "should I enable this particular feature". The module that matters most on the
 * current screen is ordered first and marked, so context is kept without hiding
 * the rest.
 *
 * for_app() is the acting case, and the more useful of the two, because the user
 * is already trying to use the thing. Nodes stay available when their module is
 * off — hiding them would break workflows already using one — so the builder
 * would otherwise hand over a node from a module the site never enabled and say
 * nothing.
 *
 * Dismissals are per user, and record which modules were off at the time, so a
 * module added in a later release surfaces again rather than being buried by a
 * dismissal that predates it.
 */
class Teasers {

	private const USER_META = 'zaplane_dismissed_modules';

	public const SPOTLIGHT_KEY = 'module_spotlight';

	/**
	 * Which module is most worth leading with on a given screen. Ordering only —
	 * every module that is off is listed either way.
	 *
	 * @return array<string,string>
	 */
	public static function screen_relevance(): array {
		$map = [
			'workflows'   => 'mcp_server',
			'dashboard'   => 'knowledge',
			'connections' => 'custom_apps',
		];

		/**
		 * Filter which module leads the spotlight on each screen.
		 *
		 * @param array<string,string> $map screen => module key.
		 */
		return (array) apply_filters( 'zaplane/module_screen_relevance', $map );
	}

	/**
	 * Every module that is switched off, ordered for this screen, or null when
	 * there is nothing to say.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function spotlight( string $screen = '' ): ?array {
		$off = [];

		foreach ( Settings::modules() as $key => $module ) {
			if ( Settings::feature_enabled( $key ) ) {
				continue;
			}
			$off[ $key ] = $module;
		}

		if ( empty( $off ) ) {
			return null;
		}

		// A dismissal records what was on offer at the time and covers only that,
		// so a module added in a later release surfaces on its own rather than
		// being buried — and without dragging back the ones already waved away.
		$off = array_diff_key( $off, array_flip( self::dismissed() ) );

		if ( empty( $off ) ) {
			return null;
		}

		$lead = self::screen_relevance()[ $screen ] ?? '';

		$modules = [];
		foreach ( $off as $key => $module ) {
			$modules[] = [
				'key'           => (string) $key,
				'title'         => (string) $module['title'],
				'description'   => (string) $module['description'],
				'panel'         => (string) ( $module['panel'] ?? '' ),
				'since'         => (string) ( $module['since'] ?? '' ),
				'relevant_here' => ( '' !== $lead && $key === $lead ),
			];
		}

		// Lead with the module this screen is about; keep registry order otherwise.
		usort(
			$modules,
			static fn( $a, $b ) => ( $b['relevant_here'] <=> $a['relevant_here'] )
		);

		return [
			'key'     => self::SPOTLIGHT_KEY,
			'title'   => __( 'Get more out of Zaplane', 'zaplane' ),
			'body'    => __( 'Modules are off until you turn them on. Here is what this site is not using yet.', 'zaplane' ),
			'modules' => $modules,
		];
	}

	/**
	 * A prompt for an integration whose module is switched off, or null.
	 *
	 * Not dismissible: it is a fact about the node in front of you, not a
	 * suggestion to file away.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function for_app( string $slug ): ?array {
		$module_key = Settings::module_for_app( $slug );

		if ( null === $module_key || Settings::feature_enabled( $module_key ) ) {
			return null;
		}

		$module = Settings::modules()[ $module_key ] ?? null;
		if ( null === $module ) {
			return null;
		}

		return [
			'key'       => 'app_module_off_' . $module_key,
			'module'    => $module_key,
			'title'     => sprintf(
				/* translators: %s: module name, e.g. Business Knowledge. */
				__( '%s is switched off', 'zaplane' ),
				$module['title']
			),
			'body'      => sprintf(
				/* translators: %s: module description. */
				__( 'You can still add this step, but the module it belongs to is not enabled on this site. %s', 'zaplane' ),
				$module['description']
			),
			'cta_label' => __( 'Turn it on', 'zaplane' ),
			// Offered inline so nobody has to leave a half-built workflow.
			'activates' => $module_key,
		];
	}

	/**
	 * Hide the spotlight for the current user, remembering what was on offer.
	 */
	public static function dismiss( string $key ): bool {
		if ( self::SPOTLIGHT_KEY !== $key ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$off = [];
		foreach ( array_keys( Settings::modules() ) as $module_key ) {
			if ( ! Settings::feature_enabled( $module_key ) ) {
				$off[] = (string) $module_key;
			}
		}

		update_user_meta(
			$user_id,
			self::USER_META,
			array_values( array_unique( array_merge( self::dismissed(), $off ) ) )
		);

		return true;
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
