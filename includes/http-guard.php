<?php

namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where an outbound request from a workflow is allowed to go.
 *
 * Three places make a request with an address someone configured — the Webhook
 * action, the HTTP Request action, and every Custom App — and all three ask the
 * `zaplane_http_block_request` filter first. Nothing answered it, so the only
 * check any of them actually performed was that the scheme was http or https.
 *
 * That address is rarely a constant. A node's configuration is resolved against
 * the run's data before it executes, so a URL containing a merge tag is chosen
 * at run time by whatever triggered the workflow — and a webhook trigger is a
 * JSON body posted by a stranger. This server would then fetch it and hand the
 * response back into the workflow, which is a way to read whatever the server
 * can reach and the caller cannot: another site on the same host, an admin
 * panel on a private address, the cloud metadata service at 169.254.169.254.
 *
 * So the default answer is now no, for loopback, private, link-local and
 * reserved addresses. The filter is untouched and still has the last word, and
 * a site that genuinely needs to call something on its own network can say so
 * through `zaplane_http_allowed_private_hosts`.
 *
 * One limit worth stating: the name is resolved here and resolved again by
 * WordPress when it connects. A name that answers differently the second time
 * would defeat this. Closing that needs the connection pinned to the address
 * that was checked, which the HTTP API does not offer.
 */
class HttpGuard {

	public static function boot(): void {
		add_filter( 'zaplane_http_block_request', [ self::class, 'block' ], 10, 3 );
	}

	/**
	 * @param bool                     $blocked Whether something already refused it.
	 * @param string                   $url     The address as configured or resolved.
	 * @param array<string,mixed>|null $parsed  wp_parse_url() of that address.
	 */
	public static function block( $blocked, $url = '', $parsed = null ): bool {
		if ( $blocked ) {
			return true;
		}

		$host = is_array( $parsed ) ? (string) ( $parsed['host'] ?? '' ) : '';

		if ( '' === $host ) {
			$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		}

		if ( '' === $host ) {
			// No host to check is not a URL worth fetching.
			return true;
		}

		$host = strtolower( trim( $host, '[]' ) );

		/**
		 * Hosts this site is allowed to reach despite being private.
		 *
		 * For an internal service a workflow legitimately calls — a staging box,
		 * an app on the same network. Compared as whole hostnames, never as a
		 * substring, so `internal.example.com` does not admit
		 * `internal.example.com.evil.test`.
		 *
		 * @param array<int,string> $hosts
		 */
		$allowed = (array) apply_filters( 'zaplane_http_allowed_private_hosts', [] );

		foreach ( $allowed as $one ) {
			if ( strtolower( trim( (string) $one ) ) === $host ) {
				return false;
			}
		}

		foreach ( self::addresses_for( $host ) as $address ) {
			if ( self::is_reachable_only_from_here( $address ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every address the host stands for.
	 *
	 * A literal is itself. A name is looked up, and a name that resolves to
	 * nothing is refused rather than passed on: WordPress would only fail to
	 * connect to it, and a lookup that fails here but succeeds a moment later is
	 * exactly the shape this guard exists to catch.
	 *
	 * @param string $host A hostname or an IP literal.
	 * @return array<int,string>
	 */
	private static function addresses_for( string $host ): array {
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}

		$addresses = [];

		$v4 = gethostbynamel( $host );
		if ( is_array( $v4 ) ) {
			$addresses = $v4;
		}

		if ( defined( 'DNS_AAAA' ) && function_exists( 'dns_get_record' ) ) {
			// A host that answers on both has to pass on both — otherwise the v6
			// address is a way around a v4 check.
			$records = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A lookup that errors is handled below as "no answer".

			foreach ( (array) $records as $record ) {
				if ( ! empty( $record['ipv6'] ) ) {
					$addresses[] = (string) $record['ipv6'];
				}
			}
		}

		// Nothing came back. Treat it as unreachable rather than as allowed.
		return $addresses ? $addresses : [ '127.0.0.1' ];
	}

	/**
	 * Whether an address is one only this machine or this network can reach.
	 *
	 * FILTER_FLAG_NO_PRIV_RANGE covers 10/8, 172.16/12, 192.168/16 and fc00::/7;
	 * NO_RES_RANGE covers 0/8, 127/8, 169.254/16 — the metadata address among
	 * them — 240/4, ::, ::1, ::ffff:0:0/96 and fe80::/10. Carrier-grade NAT is
	 * in neither, so it is named here.
	 *
	 * @param string $address One resolved address.
	 */
	private static function is_reachable_only_from_here( string $address ): bool {
		if ( ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return true;
		}

		// 100.64.0.0/10.
		if ( filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$long = ip2long( $address );

			if ( false !== $long && ( $long & 0xFFC00000 ) === ( ip2long( '100.64.0.0' ) & 0xFFC00000 ) ) {
				return true;
			}
		}

		return false;
	}
}
