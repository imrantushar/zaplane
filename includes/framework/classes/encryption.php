<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Exceptions\EncryptionException;

class Encryption {


	private const METHOD = 'aes-256-gcm';
	private const TAG_LENGTH = 16;
	private const SALT_OPTION = 'zaplane_encryption_salt';



	private static function get_key(): string {
		$salt = get_option( self::SALT_OPTION );

		if ( ! $salt ) {
			$salt = bin2hex( random_bytes( 32 ) );
			update_option( self::SALT_OPTION, $salt, false );
		}

		$base_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'zaplane_default_key';

		return hash( 'sha256', $base_key . $salt, true );
	}



	public static function encrypt( array $data ): string {
		$key = self::get_key();
		$iv = random_bytes( 12 );
		$plaintext = wp_json_encode( $data );

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_LENGTH
		);

		if ( $ciphertext === false ) {
			throw EncryptionException::encryptionFailed();
		}

		$combined = $iv . $tag . $ciphertext;

		return base64_encode( $combined );
	}



	public static function decrypt( string $encrypted ): array {
		$key = self::get_key();
		$combined = base64_decode( $encrypted );

		if ( $combined === false || strlen( $combined ) < 28 ) {
			throw EncryptionException::invalidFormat();
		}

		$iv = substr( $combined, 0, 12 );
		$tag = substr( $combined, 12, self::TAG_LENGTH );
		$ciphertext = substr( $combined, 28 );

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( $plaintext === false ) {
			throw EncryptionException::dataCorrupted();
		}

		$data = json_decode( $plaintext, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw EncryptionException::invalidJson();
		}

		return $data;
	}



	public static function is_available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& in_array( self::METHOD, openssl_get_cipher_methods(), true );
	}
}
