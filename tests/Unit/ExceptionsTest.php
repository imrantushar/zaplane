<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Exceptions\ZaplaneException;
use Zaplane\Exceptions\ValidationException;
use Zaplane\Exceptions\IntegrationException;
use Zaplane\Exceptions\OAuthException;
use Zaplane\Exceptions\WorkflowException;
use Zaplane\Exceptions\EncryptionException;
use Zaplane\Exceptions\DatabaseException;
use Zaplane\Exceptions\ConnectionException;

class ExceptionsTest extends TestCase
{
    public function testZaplaneExceptionBasics(): void
    {
        $exception = new ZaplaneException('Test message', ['key' => 'value']);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(['key' => 'value'], $exception->getContext());
        $this->assertEquals('zaplane_error', $exception->getErrorCode());
        $this->assertEquals(500, $exception->getHttpStatusCode());
    }

    public function testZaplaneExceptionToArray(): void
    {
        $exception = new ZaplaneException('Test', ['ctx' => 'data']);
        $array = $exception->toArray();

        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
    }

    public function testZaplaneExceptionToWpError(): void
    {
        $exception = new ZaplaneException('Test error', ['detail' => 'info']);
        $wpError = $exception->toWpError();

        $this->assertInstanceOf(\WP_Error::class, $wpError);
        $this->assertEquals('zaplane_error', $wpError->get_error_code());
        $this->assertEquals('Test error', $wpError->get_error_message());
    }

    public function testValidationExceptionWithErrors(): void
    {
        $errors = [
            'email' => ['Invalid email format'],
            'name' => ['Name is required', 'Name too short'],
        ];

        $exception = ValidationException::withErrors($errors);

        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals(400, $exception->getHttpStatusCode());
        $this->assertEquals('validation_error', $exception->getErrorCode());
    }

    public function testValidationExceptionForField(): void
    {
        $exception = ValidationException::forField('email', 'Invalid format');

        $this->assertStringContainsString('email', $exception->getMessage());
        $this->assertArrayHasKey('email', $exception->getErrors());
    }

    public function testValidationExceptionRequired(): void
    {
        $exception = ValidationException::required('username');

        $this->assertStringContainsString('username', $exception->getMessage());
        $this->assertStringContainsString('required', $exception->getMessage());
    }

    public function testIntegrationExceptionNotFound(): void
    {
        $exception = IntegrationException::notFound('slack');

        $this->assertStringContainsString('slack', $exception->getMessage());
        $this->assertEquals('slack', $exception->getIntegration());
        $this->assertEquals(502, $exception->getHttpStatusCode());
    }

    public function testIntegrationExceptionActionFailed(): void
    {
        $exception = IntegrationException::actionFailed('slack', 'send_message', 'Rate limited');

        $this->assertEquals('slack', $exception->getIntegration());
        $this->assertEquals('send_message', $exception->getAction());
        $this->assertStringContainsString('Rate limited', $exception->getMessage());
    }

    public function testIntegrationExceptionApiError(): void
    {
        $exception = IntegrationException::apiError('gmail', 'send', 'Unauthorized', 401);

        $context = $exception->getContext();
        $this->assertEquals(401, $context['api_status_code']);
    }

    public function testOAuthExceptionInvalidState(): void
    {
        $exception = OAuthException::invalidState();

        $this->assertStringContainsString('state', strtolower($exception->getMessage()));
        $this->assertEquals(401, $exception->getHttpStatusCode());
    }

    public function testOAuthExceptionTokenExchangeFailed(): void
    {
        $exception = OAuthException::tokenExchangeFailed('slack', 'Invalid code');

        $this->assertEquals('slack', $exception->getIntegration());
        $this->assertEquals('Invalid code', $exception->getOAuthError());
    }

    public function testOAuthExceptionNotSupported(): void
    {
        $exception = OAuthException::notSupported('webhook');

        $this->assertStringContainsString('OAuth2', $exception->getMessage());
    }

    public function testWorkflowExceptionNotFound(): void
    {
        $exception = WorkflowException::notFound(123);

        $this->assertEquals(123, $exception->getWorkflowId());
        $this->assertEquals(404, $exception->getHttpStatusCode());
    }

    public function testWorkflowExceptionNodeNotFound(): void
    {
        $exception = WorkflowException::nodeNotFound(456, 'node_abc');

        $this->assertEquals(456, $exception->getRunId());
        $this->assertEquals('node_abc', $exception->getNodeKey());
    }

    public function testWorkflowExceptionMaxRetriesExceeded(): void
    {
        $exception = WorkflowException::maxRetriesExceeded(789, 'node_xyz', 5);

        $context = $exception->getContext();
        $this->assertEquals(5, $context['attempts']);
    }

    public function testEncryptionExceptionMethods(): void
    {
        $e1 = EncryptionException::encryptionFailed('test reason');
        $this->assertStringContainsString('test reason', $e1->getMessage());

        $e2 = EncryptionException::decryptionFailed();
        $this->assertStringContainsString('Decryption', $e2->getMessage());

        $e3 = EncryptionException::invalidFormat();
        $this->assertStringContainsString('format', $e3->getMessage());

        $e4 = EncryptionException::dataCorrupted();
        $this->assertStringContainsString('corrupted', $e4->getMessage());

        $e5 = EncryptionException::invalidJson();
        $this->assertStringContainsString('JSON', $e5->getMessage());
    }

    public function testDatabaseExceptionInsertFailed(): void
    {
        $exception = DatabaseException::insertFailed('users', 'Duplicate key');

        $this->assertEquals('users', $exception->getTable());
        $this->assertEquals('insert', $exception->getOperation());
        $this->assertStringContainsString('Duplicate key', $exception->getMessage());
    }

    public function testDatabaseExceptionRecordNotFound(): void
    {
        $exception = DatabaseException::recordNotFound('workflows', 42);

        $this->assertEquals(404, $exception->getHttpStatusCode());
    }

    public function testConnectionExceptionNotFound(): void
    {
        $exception = ConnectionException::notFound(99);

        $this->assertEquals(99, $exception->getConnectionId());
        $this->assertEquals(404, $exception->getHttpStatusCode());
    }

    public function testConnectionExceptionNotOwned(): void
    {
        $exception = ConnectionException::notOwned(10, 5);

        $this->assertEquals(403, $exception->getHttpStatusCode());
        $context = $exception->getContext();
        $this->assertEquals(5, $context['user_id']);
    }

    public function testConnectionExceptionInvalidCredentials(): void
    {
        $exception = ConnectionException::invalidCredentials('slack', 'Token expired');

        $this->assertEquals('slack', $exception->getApp());
        $this->assertEquals(401, $exception->getHttpStatusCode());
    }
}
