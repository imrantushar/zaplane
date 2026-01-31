<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Integrations\Slack\SlackIntegration;
use Zaplane\Integrations\Slack\Actions\SendMessage;
use Zaplane\Integrations\Slack\Actions\SendDm;
use Zaplane\Integrations\Slack\Triggers\MessageReceived;

/**
 * Tests for the modular Slack integration
 */
class SlackIntegrationTest extends TestCase
{
    public function testGetSlugReturnsSlack(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $this->assertEquals('slack', SlackIntegration::get_slug());
    }

    public function testGetNameReturnsSlack(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $this->assertEquals('Slack', SlackIntegration::get_name());
    }

    public function testGetIconReturnsSlack(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $this->assertEquals('slack', SlackIntegration::get_icon());
    }

    public function testRequiresConnectionReturnsTrue(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $this->assertTrue(SlackIntegration::requires_connection());
    }

    public function testGetAuthTypeReturnsBoth(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $this->assertEquals('both', SlackIntegration::get_auth_type());
    }

    public function testGetOAuthScopesReturnsArray(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $scopes = SlackIntegration::get_oauth_scopes();

        $this->assertIsArray($scopes);
        $this->assertContains('chat:write', $scopes);
        $this->assertContains('channels:read', $scopes);
        $this->assertContains('users:read', $scopes);
    }

    public function testGetRateLimitReturns50(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $this->assertEquals(50, SlackIntegration::get_rate_limit());
    }

    public function testTestConnectionFailsWithMissingToken(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $credentials = [];

        $result = SlackIntegration::test_connection($credentials);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No access token', $result['message']);
    }

    public function testTestConnectionFailsWithEmptyToken(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $credentials = ['access_token' => ''];

        $result = SlackIntegration::test_connection($credentials);

        $this->assertFalse($result['success']);
    }

    public function testGetOAuthAuthUrlReturnsNullWithoutClientId(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $result = SlackIntegration::get_oauth_auth_url('https://example.com/callback', 'state123');

        $this->assertNull($result);
    }

    public function testGetOAuthAuthUrlReturnsUrlWithClientId(): void
    {
        if (!class_exists(SlackIntegration::class)) {
            $this->markTestSkipped('SlackIntegration class not available');
        }
        $credentials = ['client_id' => 'test_client_id'];
        $result = SlackIntegration::get_oauth_auth_url('https://example.com/callback', 'state123', $credentials);

        $this->assertNotNull($result);
        $this->assertStringContainsString('slack.com/oauth', $result);
        $this->assertStringContainsString('test_client_id', $result);
    }

    /**
     * Test SendMessage action build
     */
    public function testSendMessageActionBuild(): void
    {
        if (!class_exists(SendMessage::class)) {
            $this->markTestSkipped('SendMessage action not available');
        }

        $definition = SendMessage::build();

        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('config_schema', $definition);
        $this->assertArrayHasKey('_class', $definition);
        $this->assertEquals('Send Message', $definition['label']);
    }

    /**
     * Test SendMessage config schema
     */
    public function testSendMessageConfigSchema(): void
    {
        if (!class_exists(SendMessage::class)) {
            $this->markTestSkipped('SendMessage action not available');
        }

        $schema = SendMessage::get_config_schema();

        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);

        // Check for required fields
        $keys = array_column($schema, 'key');
        $this->assertContains('channel', $keys);
        $this->assertContains('text', $keys);
    }

    /**
     * Test SendDm action build
     */
    public function testSendDmActionBuild(): void
    {
        if (!class_exists(SendDm::class)) {
            $this->markTestSkipped('SendDm action not available');
        }

        $definition = SendDm::build();

        $this->assertArrayHasKey('label', $definition);
        $this->assertEquals('Send Direct Message', $definition['label']);
    }

    /**
     * Test MessageReceived trigger build
     */
    public function testMessageReceivedTriggerBuild(): void
    {
        if (!class_exists(MessageReceived::class)) {
            $this->markTestSkipped('MessageReceived trigger not available');
        }

        $definition = MessageReceived::build();

        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('hook', $definition);
        $this->assertEquals('Message Received', $definition['label']);
    }

    /**
     * Test MessageReceived trigger resolve
     */
    public function testMessageReceivedTriggerResolve(): void
    {
        if (!class_exists(MessageReceived::class)) {
            $this->markTestSkipped('MessageReceived trigger not available');
        }

        $node = ['data' => ['config' => []]];
        $args = ['Hello from Slack!'];

        $result = MessageReceived::resolve($node, $args);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Hello from Slack!', $result['message']);
    }
}
