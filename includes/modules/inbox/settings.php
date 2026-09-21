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
				'allowed_origins' => [],
			],
			'channels' => [
				'messenger' => [
					'enabled'       => false,
					'connection_id' => 0,
				],
				'whatsapp'  => [
					'enabled'       => false,
					'connection_id' => 0,
				],
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
			if ( array_key_exists( 'ask_email', $w ) ) {
				$c['ask_email'] = (bool) $w['ask_email'];
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
