<?php

namespace Zaplane\Exceptions;

if( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}
class EncryptionException extends ZaplaneException
{
    protected string $errorCode = 'encryption_error';

    public static function encryptionFailed(string $reason = ''): self
    {
        $message = 'Encryption failed';
        if ($reason) {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    public static function decryptionFailed(string $reason = ''): self
    {
        $message = 'Decryption failed';
        if ($reason) {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    public static function invalidFormat(): self
    {
        return new self('Invalid encrypted data format');
    }

    public static function dataCorrupted(): self
    {
        return new self('Decryption failed - data may be corrupted or tampered');
    }

    public static function invalidJson(): self
    {
        return new self('Decrypted data is not valid JSON');
    }

    public static function notAvailable(): self
    {
        return new self('Encryption is not available - OpenSSL extension missing or cipher not supported');
    }

    public static function keyDerivationFailed(): self
    {
        return new self('Failed to derive encryption key');
    }

    public function getHttpStatusCode(): int
    {
        return 500;
    }
}
