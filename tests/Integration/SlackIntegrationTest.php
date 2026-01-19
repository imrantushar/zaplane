<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Integrations\Slack;

class SlackIntegrationTest extends TestCase
{
    public function testGetSlugReturnsSlack(): void
    {
        $this->assertEquals('slack', Slack::get_slug());
    }

    public function testGetNameReturnsSlack(): void
    {
        $this->assertEquals('Slack', Slack::get_name());
    }

    public function testGetIconReturnsSlack(): void
    {
        $this->assertEquals('slack', Slack::get_icon());
    }

    public function testRequiresConnectionReturnsTrue(): void
    {
        $this->assertTrue(Slack::requires_connection());
    }

    public function testGetAuthTypeReturnsBoth(): void
    {
        $this->assertEquals('both', Slack::get_auth_type());
    }

    public function testGetTriggersReturnsExpectedFormat(): void
    {
        $triggers = Slack::get_triggers();

        $this->assertIsArray($triggers);
        $this->assertArrayHasKey('message_received', $triggers);
        $this->assertArrayHasKey('label', $triggers['message_received']);
        $this->assertArrayHasKey('hook', $triggers['message_received']);
    }

    public function testGetActionsReturnsExpectedFormat(): void
    {
        $actions = Slack::get_actions();

        $this->assertIsArray($actions);
        $this->assertArrayHasKey('send_message', $actions);
        $this->assertArrayHasKey('send_dm', $actions);
        $this->assertArrayHasKey('label', $actions['send_message']);
        $this->assertArrayHasKey('label', $actions['send_dm']);
    }

    public function testGetActionConfigSchemaForSendMessage(): void
    {
        $schema = Slack::get_action_config_schema('send_message');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('channel', $schema);
        $this->assertArrayHasKey('text', $schema);
        $this->assertTrue($schema['channel']['required']);
        $this->assertTrue($schema['text']['required']);
    }

    public function testGetActionConfigSchemaForSendDm(): void
    {
        $schema = Slack::get_action_config_schema('send_dm');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('text', $schema);
    }

    public function testGetActionConfigSchemaForUnknownAction(): void
    {
        $schema = Slack::get_action_config_schema('unknown_action');

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function testGetOAuthScopesReturnsArray(): void
    {
        $scopes = Slack::get_oauth_scopes();

        $this->assertIsArray($scopes);
        $this->assertContains('chat:write', $scopes);
        $this->assertContains('channels:read', $scopes);
        $this->assertContains('users:read', $scopes);
    }

    public function testResolveTriggerReturnsMessage(): void
    {
        $node = [];
        $args = ['Hello from Slack!'];

        $result = Slack::resolve_trigger($node, $args);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Hello from Slack!', $result['message']);
    }

    public function testResolveTriggerWithEmptyArgs(): void
    {
        $node = [];
        $args = [];

        $result = Slack::resolve_trigger($node, $args);

        $this->assertEquals('', $result['message']);
    }

    public function testExecuteNodeThrowsExceptionWithoutCredentials(): void
    {
        $node = [
            'config' => ['action' => 'send_message'],
        ];
        $input = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No connection credentials available');

        Slack::execute_node($node, $input);
    }

    public function testExecuteNodeThrowsExceptionWithEmptyToken(): void
    {
        $node = [
            'config' => ['action' => 'send_message'],
            '_connection_credentials' => ['access_token' => ''],
        ];
        $input = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('access token is missing');

        Slack::execute_node($node, $input);
    }

    public function testTestConnectionFailsWithMissingToken(): void
    {
        $credentials = [];

        $result = Slack::test_connection($credentials);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No access token', $result['message']);
    }

    public function testTestConnectionFailsWithEmptyToken(): void
    {
        $credentials = ['access_token' => ''];

        $result = Slack::test_connection($credentials);

        $this->assertFalse($result['success']);
    }

    public function testRefreshOAuthTokenReturnsEmptyArray(): void
    {
        $result = Slack::refresh_oauth_token('some_refresh_token');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetOAuthAuthUrlReturnsNullWithoutClientId(): void
    {
        $result = Slack::get_oauth_auth_url('https://example.com/callback', 'state123');

        $this->assertNull($result);
    }
}
