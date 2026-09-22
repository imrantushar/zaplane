<?php

namespace Zaplane\Modules\Inbox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inbox configuration: the website widget and the AI assistant.
 *
 * Kept apart from `zaplane_settings` because that option is sanitised against
 * a fixed tree, and because the widget's public config is read on every page
 * that shows it.
 */
class Settings {

	public const OPTION = 'zaplane_inbox_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			'widget' => [
				'enabled'         => false,
				'title'           => __( 'Chat with us', 'zaplane' ),
				'greeting'        => __( 'Hi! How can we help you today?', 'zaplane' ),
				'color'           => '#006BFF',
				'position'        => 'right',
				'ask_email'       => true,
				// Live visitors list: the widget reports the page it is on.
				'visitors'        => true,
				// A reply from the team opens the chat, with a sound.
				'auto_open'       => true,
				'sound'           => true,
				// Ask for name + email in the chat once a person will answer.
				'ask_contact'     => true,
				// Confirm that email with a one-time code.
				'verify_email'    => false,
				// Email a reply the visitor didn't see in the chat.
				'notify_email'    => true,
				'allowed_origins' => [],
				'answered_by'     => 'assistant',
			],
			'channels' => [
				'messenger' => [
					'enabled'       => false,
					'connection_id' => 0,
					'answered_by'   => 'assistant',
				],
				'whatsapp'  => [
					'enabled'       => false,
					'connection_id' => 0,
					'answered_by'   => 'assistant',
				],
			],
			// Answer from Business Knowledge before (or instead of) the assistant.
			'answers' => [
				'enabled'    => false,
				'strictness' => 'balanced',
				'feedback'   => true,
				// Up to four questions offered before the customer types: buttons
				// in the website chat, Messenger Ice Breakers, WhatsApp starters.
				'common_questions' => [],
				// Quick answers menu, see Services\AnswerMenu.
				'menu'             => [],
			],
			'ai'     => [
				'enabled'       => false,
				'connection_id' => 0,
				'business_key'  => 'default',
				'agent_name'    => 'Ava',
				'business_name' => '',
				'instructions'  => '',
				'max_steps'     => 4,
				'sell'          => true,
				'can_order'     => false,
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	/** Who answers a channel's new conversations. */
	public const ANSWERERS = [ 'assistant', 'workflows', 'team' ];

	/**
	 * Who answers new conversations on a channel ("web" is the widget).
	 */
	public static function answered_by( string $channel ): string {
		$all   = self::get();
		$value = 'web' === $channel ? ( $all['widget']['answered_by'] ?? '' ) : ( $all['channels'][ $channel ]['answered_by'] ?? '' );
		return in_array( $value, self::ANSWERERS, true ) ? $value : 'assistant';
	}

	public static function get(): array {
		$saved = get_option( self::OPTION, [] );
		$saved = is_array( $saved ) ? $saved : [];

		$out = self::defaults();
		foreach ( $out as $section => $values ) {
			if ( isset( $saved[ $section ] ) && is_array( $saved[ $section ] ) ) {
				$out[ $section ] = array_merge( $values, array_intersect_key( $saved[ $section ], $values ) );
			}
		}
		foreach ( self::defaults()['channels'] as $slug => $values ) {
			$stored                     = $saved['channels'][ $slug ] ?? [];
			$out['channels'][ $slug ] = array_merge( $values, is_array( $stored ) ? array_intersect_key( $stored, $values ) : [] );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $input Partial settings; untouched keys are kept.
	 * @return array<string,mixed> What is now stored.
	 */
	public static function save( array $input ): array {
		$current = self::get();

		if ( isset( $input['widget'] ) && is_array( $input['widget'] ) ) {
			$w = $input['widget'];
			$c = &$current['widget'];
			if ( array_key_exists( 'enabled', $w ) ) {
				$c['enabled'] = (bool) $w['enabled'];
			}
			foreach ( [ 'ask_email', 'visitors', 'auto_open', 'sound', 'ask_contact', 'verify_email', 'notify_email' ] as $k ) {
				if ( array_key_exists( $k, $w ) ) {
					$c[ $k ] = (bool) $w[ $k ];
				}
			}
			foreach ( [ 'title', 'greeting' ] as $k ) {
				if ( isset( $w[ $k ] ) ) {
					$c[ $k ] = sanitize_text_field( (string) $w[ $k ] );
				}
			}
			if ( isset( $w['color'] ) ) {
				$c['color'] = sanitize_hex_color( (string) $w['color'] ) ?: $c['color'];
			}
			if ( isset( $w['position'] ) ) {
				$c['position'] = 'left' === $w['position'] ? 'left' : 'right';
			}
			if ( isset( $w['answered_by'] ) && in_array( $w['answered_by'], self::ANSWERERS, true ) ) {
				$c['answered_by'] = $w['answered_by'];
			}
			if ( isset( $w['allowed_origins'] ) ) {
				$c['allowed_origins'] = self::clean_origins( (array) $w['allowed_origins'] );
			}
			unset( $c );
		}

		if ( isset( $input['channels'] ) && is_array( $input['channels'] ) ) {
			foreach ( array_keys( self::defaults()['channels'] ) as $slug ) {
				$in = $input['channels'][ $slug ] ?? null;
				if ( ! is_array( $in ) ) {
					continue;
				}
				if ( array_key_exists( 'enabled', $in ) ) {
					$current['channels'][ $slug ]['enabled'] = (bool) $in['enabled'];
				}
				if ( isset( $in['connection_id'] ) ) {
					$current['channels'][ $slug ]['connection_id'] = absint( $in['connection_id'] );
				}
				if ( isset( $in['answered_by'] ) && in_array( $in['answered_by'], self::ANSWERERS, true ) ) {
					$current['channels'][ $slug ]['answered_by'] = $in['answered_by'];
				}
			}
		}

		if ( isset( $input['answers'] ) && is_array( $input['answers'] ) ) {
			$a = $input['answers'];
			foreach ( [ 'enabled', 'feedback' ] as $k ) {
				if ( array_key_exists( $k, $a ) ) {
					$current['answers'][ $k ] = (bool) $a[ $k ];
				}
			}
			if ( isset( $a['strictness'] ) && in_array( $a['strictness'], [ 'strict', 'balanced', 'broad' ], true ) ) {
				$current['answers']['strictness'] = $a['strictness'];
			}
			if ( isset( $a['common_questions'] ) && is_array( $a['common_questions'] ) ) {
				$current['answers']['common_questions'] = self::clean_questions( $a['common_questions'] );
			}
			if ( isset( $a['menu'] ) && is_array( $a['menu'] ) ) {
				$current['answers']['menu'] = Services\AnswerMenu::sanitize( $a['menu'] );
				// The menu replaces the older flat list.
				$current['answers']['common_questions'] = [];
			}
		}

		if ( isset( $input['ai'] ) && is_array( $input['ai'] ) ) {
			$a = $input['ai'];
			$c = &$current['ai'];
			if ( array_key_exists( 'enabled', $a ) ) {
				$c['enabled'] = (bool) $a['enabled'];
			}
			if ( isset( $a['connection_id'] ) ) {
				$c['connection_id'] = absint( $a['connection_id'] );
			}
			foreach ( [ 'sell', 'can_order' ] as $k ) {
				if ( array_key_exists( $k, $a ) ) {
					$c[ $k ] = (bool) $a[ $k ];
				}
			}
			if ( isset( $a['max_steps'] ) ) {
				$c['max_steps'] = min( 8, max( 1, absint( $a['max_steps'] ) ) );
			}
			if ( isset( $a['business_key'] ) ) {
				$c['business_key'] = sanitize_key( (string) $a['business_key'] ) ?: 'default';
			}
			foreach ( [ 'agent_name', 'business_name' ] as $k ) {
				if ( isset( $a[ $k ] ) ) {
					$c[ $k ] = sanitize_text_field( (string) $a[ $k ] );
				}
			}
			if ( isset( $a['instructions'] ) ) {
				$c['instructions'] = sanitize_textarea_field( (string) $a['instructions'] );
			}
			unset( $c );
		}

		update_option( self::OPTION, $current, false );
		return $current;
	}

	/** Messenger and WhatsApp allow four starters of up to 80 characters. */
	public const MAX_COMMON = 4;
	public const COMMON_LENGTH = 80;

	/**
	 * @param array<int,mixed> $questions
	 * @return array<int,string>
	 */
	private static function clean_questions( array $questions ): array {
		$out = [];
		foreach ( $questions as $q ) {
			$q = trim( sanitize_text_field( (string) $q ) );
			if ( '' !== $q ) {
				$out[] = mb_substr( $q, 0, self::COMMON_LENGTH );
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, self::MAX_COMMON );
	}

	/**
	 * Reduce each entry to scheme://host[:port]; drop anything that is not one.
	 *
	 * @param array<int,mixed> $origins
	 * @return array<int,string>
	 */
	private static function clean_origins( array $origins ): array {
		$out = [];
		foreach ( $origins as $origin ) {
			$parts = wp_parse_url( trim( (string) $origin ) );
			if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'], [ 'http', 'https' ], true ) ) {
				continue;
			}
			$out[] = strtolower( $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) );
		}
		return array_values( array_unique( $out ) );
	}
}
