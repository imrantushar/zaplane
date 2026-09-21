<?php

namespace Zaplane\Modules\Inbox\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	/**
	 * @return array<string,class-string<ChannelInterface>>
	 */
	public static function all(): array {
		$channels = [
			Web::slug() => Web::class,
		];

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
		return $all[ $slug ] ?? null;
	}
}
