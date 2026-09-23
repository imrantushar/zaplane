<?php

namespace Zaplane\Socket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The wire format of a WebSocket, as far as this needs it (RFC 6455).
 *
 * Only what a chat notification channel uses: text, close, ping and pong,
 * with continuation frames reassembled. No extensions are negotiated, so no
 * compression to undo.
 */
class Frames {

	public const OP_CONTINUE = 0x0;
	public const OP_TEXT     = 0x1;
	public const OP_BINARY   = 0x2;
	public const OP_CLOSE    = 0x8;
	public const OP_PING     = 0x9;
	public const OP_PONG     = 0xA;

	/** Refuse anything larger than this in one frame (1 MiB). */
	public const MAX_PAYLOAD = 1048576;

	/**
	 * Build one frame.
	 *
	 * @param bool $mask Clients must mask what they send; servers must not.
	 */
	public static function encode( string $payload, int $opcode = self::OP_TEXT, bool $mask = false ): string {
		$length = strlen( $payload );
		$head   = chr( 0x80 | $opcode );

		if ( $length < 126 ) {
			$head .= chr( ( $mask ? 0x80 : 0 ) | $length );
		} elseif ( $length <= 0xFFFF ) {
			$head .= chr( ( $mask ? 0x80 : 0 ) | 126 ) . pack( 'n', $length );
		} else {
			$head .= chr( ( $mask ? 0x80 : 0 ) | 127 ) . pack( 'J', $length );
		}

		if ( ! $mask ) {
			return $head . $payload;
		}

		$key = random_bytes( 4 );

		return $head . $key . ( $key === '' ? $payload : self::apply_mask( $payload, $key ) );
	}

	/**
	 * Read whole frames out of a buffer.
	 *
	 * Returns the frames it could read and what is left of the buffer — a
	 * stream hands over however many bytes it feels like, so a frame often
	 * arrives in pieces, and several can arrive at once.
	 *
	 * @return array{frames:array<int,array{opcode:int,payload:string,fin:bool}>,rest:string,error:string}
	 */
	public static function decode( string $buffer ): array {
		$frames = [];

		while ( strlen( $buffer ) >= 2 ) {
			$first  = ord( $buffer[0] );
			$second = ord( $buffer[1] );
			$fin    = (bool) ( $first & 0x80 );
			$opcode = $first & 0x0F;
			$masked = (bool) ( $second & 0x80 );
			$length = $second & 0x7F;
			$offset = 2;

			if ( 126 === $length ) {
				if ( strlen( $buffer ) < 4 ) {
					break;
				}
				$length = unpack( 'n', substr( $buffer, 2, 2 ) )[1];
				$offset = 4;
			} elseif ( 127 === $length ) {
				if ( strlen( $buffer ) < 10 ) {
					break;
				}
				$length = unpack( 'J', substr( $buffer, 2, 8 ) )[1];
				$offset = 10;
			}

			if ( $length > self::MAX_PAYLOAD ) {
				return [
					'frames' => $frames,
					'rest'   => '',
					'error'  => 'frame too large',
				];
			}

			$key = '';
			if ( $masked ) {
				if ( strlen( $buffer ) < $offset + 4 ) {
					break;
				}
				$key     = substr( $buffer, $offset, 4 );
				$offset += 4;
			}

			if ( strlen( $buffer ) < $offset + $length ) {
				break;
			}

			$payload = substr( $buffer, $offset, $length );
			$buffer  = substr( $buffer, $offset + $length );

			$frames[] = [
				'opcode'  => $opcode,
				'payload' => $masked ? self::apply_mask( $payload, $key ) : $payload,
				'fin'     => $fin,
			];
		}

		return [
			'frames' => $frames,
			'rest'   => $buffer,
			'error'  => '',
		];
	}

	private static function apply_mask( string $payload, string $key ): string {
		$out = '';
		for ( $i = 0, $len = strlen( $payload ); $i < $len; $i++ ) {
			$out .= $payload[ $i ] ^ $key[ $i % 4 ];
		}

		return $out;
	}

	/** The value a server answers a client's Sec-WebSocket-Key with. */
	public static function accept_key( string $client_key ): string {
		return base64_encode( sha1( trim( $client_key ) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true ) );
	}
}
