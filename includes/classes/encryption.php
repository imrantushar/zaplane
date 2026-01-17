<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Exceptions\EncryptionException;

/**
 * Encryption utility for secure credential storage
 * Uses AES-256-GCM authenticated encryption
 */
class Encryption {

	private const METHOD = 'aes-256-gcm';
	private const TAG_LENGTH = 16;
	private const SALT_OPTION = 'zaplane_encryption_salt';

	/**
	 * Get or generate the encryption key
	 * Derives from WordPress AUTH_KEY + plugin-specific salt
	 *
	 * @return string 32-byte encryption key
	 */
	private static function get_key(): string {
		$salt = get_option( self::SALT_OPTION );

		if ( ! $salt ) {
			$salt = bin2hex( random_bytes( 32 ) );
			update_option( self::SALT_OPTION, $salt, false );
		}

		// Derive key using HKDF-like approach
		$base_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'zaplane_default_key';

		return hash( 'sha256', $base_key . $salt, true );
	}

	/**
	 * Encrypt sensitive data
	 *
	 * @param array $data Plain credentials array
	 * @return string Base64-encoded encrypted string with IV and tag
	 */
	public static function encrypt( array $data ): string {
		$key = self::get_key();
		$iv = random_bytes( 12 ); // GCM recommended IV size
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

		// Combine IV + tag + ciphertext and encode
		$combined = $iv . $tag . $ciphertext;

		return base64_encode( $combined );
	}

	/**
	 * Decrypt sensitive data
	 *
	 * @param string $encrypted Base64-encoded encrypted string
	 * @return array Decrypted credentials array
	 * @throws \Exception on decryption failure
	 */
	public static function decrypt( string $encrypted ): array {
		$key = self::get_key();
		$combined = base64_decode( $encrypted );

		if ( $combined === false || strlen( $combined ) < 28 ) {
			throw EncryptionException::invalidFormat();
		}

		// Extract IV (12 bytes), tag (16 bytes), and ciphertext
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

	/**
	 * Check if encryption is available
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& in_array( self::METHOD, openssl_get_cipher_methods(), true );
	}
}
