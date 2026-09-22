<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Channels\MetaChannel;
use Zaplane\Modules\Inbox\Channels\Registry;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps each social channel's conversation starters (Messenger Ice Breakers,
 * WhatsApp conversation starters) in step with the Inbox's "Common
 * questions". The website chat reads them straight from settings.
 *
 * A channel is only called when its questions or connection changed since
 * the last successful push, so saving unrelated settings costs nothing.
 */
class CommonQuestions {

	public const STATUS_OPTION = 'zaplane_inbox_common_sync';

	/**
	 * @return array<string,array{ok:bool,error:string,at:int,count:int,hash:string}>
	 */
	public static function sync( bool $force = false ): array {
		$settings  = InboxSettings::get();
		$questions = AnswerMenu::starters();
		$status    = self::status();

		MetaChannel::reset();

		foreach ( Registry::all() as $slug => $class ) {
			if ( ! is_subclass_of( $class, MetaChannel::class ) || ! $class::enabled() ) {
				continue;
			}

			$hash = md5( wp_json_encode( [ $questions, (int) ( $settings['channels'][ $slug ]['connection_id'] ?? 0 ) ] ) );
			$last = $status[ $slug ] ?? null;
			if ( ! $force && $last && ! empty( $last['ok'] ) && $hash === ( $last['hash'] ?? '' ) ) {
				continue;
			}
			// Nothing was ever pushed here and there's nothing to push.
			if ( ! $last && empty( $questions ) ) {
				continue;
			}

			$result          = $class::set_starters( $questions );
			$status[ $slug ] = [
				'ok'    => (bool) $result['ok'],
				'error' => (string) $result['error'],
				'at'    => time(),
				'count' => count( $questions ),
				'hash'  => $result['ok'] ? $hash : '',
			];
		}

		update_option( self::STATUS_OPTION, $status, false );
		return $status;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function status(): array {
		$status = get_option( self::STATUS_OPTION, [] );
		return is_array( $status ) ? $status : [];
	}
}
