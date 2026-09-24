<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One place that decides which Graph API version the Meta integrations call.
 *
 * Messenger and WhatsApp both talk to graph.facebook.com, and both used to carry
 * their own copy of the version string — so a Meta release meant remembering every
 * file that pinned one. They ask here instead, and a release is a single edit.
 *
 * Meta supports a version for roughly two years from its release, then serves the
 * call on a newer one; pinning an old version is not an exemption from a change,
 * only a delay. DEFAULT_VERSION tracks the current stable release for that reason.
 *
 * A connection may override the version per site, and the filter below lets a site
 * pin one without touching code. Both go through version(), which falls back to the
 * default for anything that isn't a usable version string — an override saved blank
 * used to reach the URL builder and produce a double slash.
 */
class MetaGraph {

	/** Host every Meta Graph call goes to. */
	public const BASE_URL = 'https://graph.facebook.com';

	/** Current stable Graph API version (v26.0, released 2026-07-29). */
	public const DEFAULT_VERSION = 'v26.0';

	/**
	 * The version to call, from an optional per-connection override.
	 *
	 * Accepts "v26.0" or "26.0"; anything else — blank, malformed, a stray path —
	 * falls back to the default rather than building a URL Meta will reject.
	 *
	 * @param string|null $override Value stored on the connection, if any.
	 */
	public static function version( ?string $override = null ): string {
		$version = strtolower( trim( (string) $override ) );

		if ( '' !== $version && ! preg_match( '/^v/', $version ) ) {
			$version = 'v' . $version;
		}

		if ( ! preg_match( '/^v\d+\.\d+$/', $version ) ) {
			$version = self::DEFAULT_VERSION;
		}

		/**
		 * Filter the Graph API version used for a Meta API call.
		 *
		 * @param string $version  Version about to be called, e.g. "v26.0".
		 * @param string $override The connection's own value, before validation.
		 */
		return (string) apply_filters( 'zaplane_meta_graph_version', $version, (string) $override );
	}

	/**
	 * A full endpoint URL.
	 *
	 * @param string      $path     Path below the version, e.g. "me/messages".
	 * @param string|null $override The connection's api_version, if set.
	 */
	public static function url( string $path, ?string $override = null ): string {
		return self::BASE_URL . '/' . self::version( $override ) . '/' . ltrim( $path, '/' );
	}
}
