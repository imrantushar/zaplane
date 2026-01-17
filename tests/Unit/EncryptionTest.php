<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Classes\Encryption;
use Zaplane\Exceptions\EncryptionException;

class EncryptionTest extends TestCase
{
    public function testEncryptReturnsBase64String(): void
    {
        $data = ['api_key' => 'test_key_123', 'secret' => 'my_secret'];

        $encrypted = Encryption::encrypt($data);

        $this->assertIsString($encrypted);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals(base64_decode($encrypted, true), false);
    }

    public function testDecryptReturnsOriginalData(): void
    {
        $data = ['api_key' => 'test_key_123', 'secret' => 'my_secret'];

        $encrypted = Encryption::encrypt($data);
        $decrypted = Encryption::decrypt($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptDecryptWithComplexData(): void
    {
        $data = [
            'access_token' => 'xoxb-123456789-abcdefg',
            'refresh_token' => 'refresh_token_value',
            'expires_in' => 3600,
            'team' => [
                'id' => 'T12345',
                'name' => 'Test Team',
            ],
            'nested' => [
                'level1' => [
                    'level2' => [
                        'value' => 'deep_value',
                    ],
                ],
            ],
        ];

        $encrypted = Encryption::encrypt($data);
        $decrypted = Encryption::decrypt($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    public function testDecryptThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(EncryptionException::class);

        Encryption::decrypt('invalid_base64_data!@#$');
    }

    public function testDecryptThrowsExceptionForTooShortData(): void
    {
        $this->expectException(EncryptionException::class);

        Encryption::decrypt(base64_encode('short'));
    }

    public function testDecryptThrowsExceptionForTamperedData(): void
    {
        $data = ['key' => 'value'];
        $encrypted = Encryption::encrypt($data);

        $decoded = base64_decode($encrypted);
        $tampered = $decoded;
        $tampered[20] = chr(ord($tampered[20]) ^ 0xFF);
        $tamperedEncrypted = base64_encode($tampered);

        $this->expectException(EncryptionException::class);

        Encryption::decrypt($tamperedEncrypted);
    }

    public function testEncryptProducesDifferentOutputsForSameInput(): void
    {
        $data = ['key' => 'value'];

        $encrypted1 = Encryption::encrypt($data);
        $encrypted2 = Encryption::encrypt($data);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function testBothEncryptedValuesDecryptToSameData(): void
    {
        $data = ['key' => 'value'];

        $encrypted1 = Encryption::encrypt($data);
        $encrypted2 = Encryption::encrypt($data);

        $decrypted1 = Encryption::decrypt($encrypted1);
        $decrypted2 = Encryption::decrypt($encrypted2);

        $this->assertEquals($decrypted1, $decrypted2);
        $this->assertEquals($data, $decrypted1);
    }

    public function testIsAvailableReturnsBoolean(): void
    {
        $result = Encryption::is_available();

        $this->assertIsBool($result);
    }

    public function testEncryptWithEmptyArray(): void
    {
        $data = [];

        $encrypted = Encryption::encrypt($data);
        $decrypted = Encryption::decrypt($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptWithSpecialCharacters(): void
    {
        $data = [
            'unicode' => 'こんにちは世界',
            'emoji' => '🚀💻🔐',
            'special' => "line1\nline2\ttab",
            'quotes' => '"double" and \'single\'',
        ];

        $encrypted = Encryption::encrypt($data);
        $decrypted = Encryption::decrypt($encrypted);

        $this->assertEquals($data, $decrypted);
    }
}
