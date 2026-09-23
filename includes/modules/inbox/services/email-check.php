<?php

namespace Zaplane\Modules\Inbox\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this an address a reply could actually reach?
 *
 * Without emailing anyone: the format, a typo in a well-known provider
 * ("gmial.com"), a throwaway inbox, and whether the domain takes mail at all
 * (an MX record, or an A record as mail servers fall back to). That catches
 * nearly every wrong address a visitor types; only a one-time code proves the
 * mailbox is theirs.
 *
 * We never talk SMTP to the visitor's mail server to ask whether the mailbox
 * exists: the big providers answer "yes" to everything, and probing gets a
 * site's IP blocklisted.
 */
class EmailCheck {

	/** Domains we looked up, remembered this long. */
	private const DNS_TTL = DAY_IN_SECONDS;

	/** Common providers, and the typos people make of them. */
	private const TYPOS = [
		'gmail.com'   => [ 'gmial.com', 'gmai.com', 'gmal.com', 'gamil.com', 'gnail.com', 'gmail.co', 'gmail.cm', 'gmail.om', 'gmail.con', 'gmail.cmo', 'gmaill.com', 'gmali.com', 'gmil.com', 'gmsil.com', 'gmail.comm', 'gmail.cim', 'gmail.vom', 'gmail.xom', 'googlemail.co' ],
		'yahoo.com'   => [ 'yaho.com', 'yahooo.com', 'yahoo.co', 'yahoo.cm', 'yahoo.con', 'yhaoo.com', 'yahho.com', 'yahoo.om' ],
		'hotmail.com' => [ 'hotmial.com', 'hotmal.com', 'hotmai.com', 'hotmail.co', 'hotmail.cm', 'hotmail.con', 'hotmil.com', 'hotmaill.com', 'htomail.com' ],
		'outlook.com' => [ 'outlok.com', 'outlook.co', 'outlook.cm', 'outlook.con', 'outloo.com', 'outllook.com', 'otulook.com' ],
		'icloud.com'  => [ 'iclod.com', 'icloud.co', 'icloud.cm', 'icloud.con', 'icoud.com', 'iclould.com' ],
		'live.com'    => [ 'live.co', 'live.cm', 'live.con', 'liv.com' ],
		'aol.com'     => [ 'aol.co', 'aol.cm', 'aol.con', 'aoll.com' ],
		'proton.me'   => [ 'proton.m', 'protonn.me', 'proto.me' ],
	];

	/** Throwaway inboxes: an address that is gone by the time we reply. */
	private const DISPOSABLE = [
		'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', 'grr.la', '10minutemail.com',
		'10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmail.net', 'tempmailo.com',
		'throwawaymail.com', 'yopmail.com', 'yopmail.net', 'getnada.com', 'nada.email', 'trashmail.com',
		'trashmail.de', 'dispostable.com', 'maildrop.cc', 'mailnesia.com', 'mintemail.com', 'mohmal.com',
		'fakeinbox.com', 'emailondeck.com', 'moakt.com', 'spamgourmet.com', 'mytemp.email', 'tempinbox.com',
		'burnermail.io', 'mailpoof.com', 'discard.email', 'inboxkitten.com', 'tempr.email', 'emailfake.com',
		'33mail.com', 'mail.tm', 'minuteinbox.com', 'luxusmail.org', 'spam4.me', 'harakirimail.com',
	];

	/**
	 * @return array{ok:bool,email:string,code?:string,message?:string,suggestion?:string}
	 */
	public static function check( string $email ): array {
		$email = strtolower( trim( sanitize_email( $email ) ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return self::fail( $email, 'format', __( 'Please enter a valid email address.', 'zaplane' ) );
		}

		[ $local, $domain ] = explode( '@', $email, 2 );

		$fixed = self::typo_fix( $domain );
		if ( null !== $fixed ) {
			$suggestion = $local . '@' . $fixed;
			return self::fail(
				$email,
				'typo',
				/* translators: %s: the corrected email address. */
				sprintf( __( 'Did you mean %s?', 'zaplane' ), $suggestion ),
				$suggestion
			);
		}

		/**
		 * Throwaway-inbox domains to refuse.
		 *
		 * @param string[] $domains
		 */
		$disposable = (array) apply_filters( 'zaplane/inbox/disposable_email_domains', self::DISPOSABLE );
		if ( self::in_domains( $domain, $disposable ) ) {
			return self::fail( $email, 'disposable', __( "We can't reply to a temporary inbox. Please use an email you'll keep.", 'zaplane' ) );
		}

		if ( ! self::takes_mail( $domain ) ) {
			return self::fail( $email, 'domain', __( "That email domain doesn't receive mail. Please check the address.", 'zaplane' ) );
		}

		return [
			'ok'    => true,
			'email' => $email,
		];
	}

	/**
	 * The provider a mistyped domain meant, or null.
	 */
	private static function typo_fix( string $domain ): ?string {
		foreach ( self::TYPOS as $right => $wrong ) {
			if ( in_array( $domain, $wrong, true ) ) {
				return $right;
			}
		}
		return null;
	}

	/**
	 * The domain, or a parent of it, is on the list.
	 *
	 * @param string[] $list
	 */
	private static function in_domains( string $domain, array $list ): bool {
		$list  = array_map( 'strtolower', $list );
		$parts = explode( '.', $domain );
		while ( count( $parts ) >= 2 ) {
			if ( in_array( implode( '.', $parts ), $list, true ) ) {
				return true;
			}
			array_shift( $parts );
		}
		return false;
	}

	/**
	 * The domain has somewhere to deliver mail. When DNS can't be asked (no
	 * lookup functions, a sandboxed host), the address gets the benefit of the
	 * doubt: a wrong "no" would lock a real customer out of the chat.
	 */
	private static function takes_mail( string $domain ): bool {
		/**
		 * Short-circuit the DNS lookup: return a bool to decide, null to look.
		 *
		 * @param bool|null $takes_mail
		 * @param string    $domain
		 */
		$pre = apply_filters( 'zaplane/inbox/email_domain_takes_mail', null, $domain );
		if ( null !== $pre ) {
			return (bool) $pre;
		}

		if ( ! function_exists( 'checkdnsrr' ) ) {
			return true;
		}

		$key    = 'zaplane_inbox_mx_' . md5( $domain );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		// Trailing dot: the name as given, not searched under the host's own domain.
		$ok = checkdnsrr( $domain . '.', 'MX' ) || checkdnsrr( $domain . '.', 'A' ) || checkdnsrr( $domain . '.', 'AAAA' );
		set_transient( $key, $ok ? 'yes' : 'no', $ok ? self::DNS_TTL : HOUR_IN_SECONDS );

		return $ok;
	}

	/**
	 * @return array{ok:bool,email:string,code:string,message:string,suggestion?:string}
	 */
	private static function fail( string $email, string $code, string $message, string $suggestion = '' ): array {
		$out = [
			'ok'      => false,
			'email'   => $email,
			'code'    => $code,
			'message' => $message,
		];
		if ( '' !== $suggestion ) {
			$out['suggestion'] = $suggestion;
		}
		return $out;
	}
}
