<?php

namespace Zaplane\Modules\Inbox\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	/**
	 * @return array<string,class-string<ChannelInterface>>
	 */
	/**
	 * The channels that ship with the inbox (their slugs are reserved).
	 *
	 * @return array<string,class-string<ChannelInterface>>
	 */
	public static function builtin(): array {
		return [
			Web::slug()       => Web::class,
			Messenger::slug() => Messenger::class,
			Whatsapp::slug()  => Whatsapp::class,
		];
	}

	public static function all(): array {
		$channels = self::builtin();

		/**
		 * Filter the inbox channels, keyed by slug. Each value is a class
		 * implementing ChannelInterface.
		 *
		 * @param array<string,string> $channels
		 */
		$channels = (array) apply_filters( 'zaplane/inbox/channels', $channels );

		return array_filter(
			$channels,
			static function ( $class ) {
				return is_string( $class ) && is_subclass_of( $class, ChannelInterface::class );
			}
		);
	}

	/**
	 * @return class-string<ChannelInterface>|null
	 */
	public static function get( string $slug ): ?string {
		$all = self::all();
		if ( isset( $all[ $slug ] ) ) {
			return $all[ $slug ];
		}
		// A source a workflow brings in: a workflow delivers its replies.
		return \Zaplane\Modules\Inbox\Services\Sources::is( $slug ) ? SourceChannel::class : null;
	}
}
