<?php

namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where an outbound request from a workflow is allowed to go.
 *
 * Every step that fetches an address someone configured, or that a run supplied,
 * goes through request() below — HTTP Request, Send Webhook, Custom Apps, the
 * AI Agent's HTTP tool, AI image and audio downloads, CSV from a URL, the MCP
 * client and Human Approval's Slack message — and it asks the
 * `zaplane_http_block_request` filter about every address, redirects included.
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

		// An IPv4 address carried inside an IPv6 one reaches that IPv4 address, so it
		// is judged as one. Before PHP 8.3 the range filter below passes
		// ::ffff:127.0.0.1 as a public address.
		$embedded = self::embedded_ipv4( $address );
		if ( null !== $embedded ) {
			return self::is_reachable_only_from_here( $embedded );
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

	/**
	 * The IPv4 address inside a mapped (::ffff:0:0/96), compatible (::/96), NAT64
	 * (64:ff9b::/96) or 6to4 (2002::/16) IPv6 address, or null for any other.
	 *
	 * @param string $address One resolved address.
	 */
	private static function embedded_ipv4( string $address ): ?string {
		if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return null;
		}

		$bin = inet_pton( $address );
		if ( false === $bin || 16 !== strlen( $bin ) ) {
			return null;
		}

		$prefix = substr( $bin, 0, 12 );
		$v4     = null;

		if ( str_repeat( "\0", 10 ) . "\xff\xff" === $prefix || str_repeat( "\0", 12 ) === $prefix || "\x00\x64\xff\x9b" . str_repeat( "\0", 8 ) === $prefix ) {
			$v4 = substr( $bin, 12, 4 );
		} elseif ( "\x20\x02" === substr( $bin, 0, 2 ) ) {
			$v4 = substr( $bin, 2, 4 );
		}

		if ( null === $v4 ) {
			return null;
		}

		$ip = inet_ntop( $v4 );

		return false === $ip ? null : $ip;
	}

	/**
	 * Make a request, checking every address it goes to.
	 *
	 * The check has to run again on each redirect: a public address answering with
	 * a Location of 127.0.0.1 or the metadata service would otherwise be followed
	 * straight past it. So WordPress is told not to follow redirects, and they are
	 * followed here instead, every hop asked the same question.
	 *
	 * Takes and returns what wp_remote_request() does; `redirection` sets the limit.
	 *
	 * @param string              $url  Absolute http(s) URL.
	 * @param array<string,mixed> $args wp_remote_request() arguments.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function request( string $url, array $args = [] ) {
		$hops                = isset( $args['redirection'] ) ? max( 0, (int) $args['redirection'] ) : 5;
		$args['redirection'] = 0;

		for ( $hop = 0; $hop <= $hops; $hop++ ) {
			$parsed = wp_parse_url( $url );
			$parsed = is_array( $parsed ) ? $parsed : null;
			$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );

			if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
				return new \WP_Error( 'zaplane_http_blocked', sprintf( 'Request refused: the scheme "%s" is not allowed (http and https only).', $scheme ) );
			}

			if ( apply_filters( 'zaplane_http_block_request', false, $url, $parsed ) ) {
				return new \WP_Error( 'zaplane_http_blocked', 'Request refused: that address is not one a workflow may reach.' );
			}

			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code     = (int) wp_remote_retrieve_response_code( $response );
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( is_array( $location ) ) {
				$location = (string) end( $location );
			}

			if ( $code < 300 || $code >= 400 || '' === (string) $location ) {
				return $response;
			}

			if ( $hop === $hops ) {
				return 0 === $hops ? $response : new \WP_Error( 'zaplane_http_too_many_redirects', 'Request refused: too many redirects.' );
			}

			$url = \WP_Http::make_absolute_url( (string) $location, $url );

			// Browsers turn a 303, and a 301 or 302 answering a POST, into a GET.
			if ( 303 === $code || ( in_array( $code, [ 301, 302 ], true ) && 'POST' === strtoupper( (string) ( $args['method'] ?? 'GET' ) ) ) ) {
				$args['method'] = 'GET';
				unset( $args['body'] );
			}
		}//end for

		return new \WP_Error( 'zaplane_http_too_many_redirects', 'Request refused: too many redirects.' );
	}
}
