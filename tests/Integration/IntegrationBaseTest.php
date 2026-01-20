<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Classes\IntegrationBase;

class TestIntegration extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'test_integration';
    }
}

class CustomIntegration extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'custom';
    }

    public static function get_name(): string
    {
        return 'Custom Integration';
    }

    public static function get_icon(): string
    {
        return 'custom-icon';
    }

    public static function get_triggers(): array
    {
        return [
            'item_created' => ['label' => 'Item Created', 'hook' => 'custom_item_created'],
        ];
    }

    public static function get_actions(): array
    {
        return [
            'create_item' => ['label' => 'Create Item'],
            'update_item' => ['label' => 'Update Item'],
        ];
    }

    public static function requires_connection(): bool
    {
        return true;
    }

    public static function get_auth_type(): string
    {
        return 'api_key';
    }

    public static function get_auth_fields(?string $auth_type = null): array
    {
        return [
            'api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'required' => true,
            ],
        ];
    }

    public static function resolve_trigger(array $node, array $hook_args)
    {
        return ['item_id' => $hook_args[0] ?? null];
    }

    public static function execute_node(array $node, array $input): array
    {
        return [
            'port' => 'main',
            'data' => array_merge($input, ['executed' => true]),
        ];
    }

    public static function get_output_ports(): array
    {
        return ['main', 'error'];
    }
}

class IntegrationBaseTest extends TestCase
{
    public function testGetNameDefaultsToCapitalizedSlug(): void
    {
        $this->assertEquals('Test_integration', TestIntegration::get_name());
    }

    public function testGetIconDefaultsToEmpty(): void
    {
        $this->assertEquals('', TestIntegration::get_icon());
    }

    public function testGetTriggersDefaultsToEmpty(): void
    {
        $this->assertEmpty(TestIntegration::get_triggers());
    }

    public function testGetActionsDefaultsToEmpty(): void
    {
        $this->assertEmpty(TestIntegration::get_actions());
    }

    public function testResolveTriggerDefaultsToFalse(): void
    {
        $this->assertFalse(TestIntegration::resolve_trigger([], []));
    }

    public function testExecuteNodeDefaultsToPassthrough(): void
    {
        $input = ['key' => 'value'];
        $result = TestIntegration::execute_node([], $input);

        $this->assertEquals('main', $result['port']);
        $this->assertEquals($input, $result['data']);
    }

    public function testValidateConfigDefaultsToTrue(): void
    {
        $this->assertTrue(TestIntegration::validate_config([]));
    }

    public function testGetConfigSchemaDefaultsToEmpty(): void
    {
        $this->assertEmpty(TestIntegration::get_config_schema());
    }

    public function testGetOutputPortsDefaultsToMain(): void
    {
        $ports = TestIntegration::get_output_ports();

        $this->assertContains('main', $ports);
        $this->assertCount(1, $ports);
    }

    public function testSupportsWebhookDefaultsToFalse(): void
    {
        $this->assertFalse(TestIntegration::supports_webhook());
    }

    public function testSupportsPollingDefaultsToFalse(): void
    {
        $this->assertFalse(TestIntegration::supports_polling());
    }

    public function testGetRateLimitDefaultsToZero(): void
    {
        $this->assertEquals(0, TestIntegration::get_rate_limit());
    }

    public function testRequiresConnectionDefaultsToFalse(): void
    {
        $this->assertFalse(TestIntegration::requires_connection());
    }

    public function testGetAuthTypeDefaultsToNone(): void
    {
        $this->assertEquals('none', TestIntegration::get_auth_type());
    }

    public function testGetAuthFieldsDefaultsToEmpty(): void
    {
        $this->assertEmpty(TestIntegration::get_auth_fields());
    }

    public function testTestConnectionDefaultSuccess(): void
    {
        $result = TestIntegration::test_connection([]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('not implemented', $result['message']);
    }

    public function testGetOAuthAuthUrlDefaultsToNull(): void
    {
        $this->assertNull(TestIntegration::get_oauth_auth_url('https://example.com', 'state'));
    }

    public function testExchangeOAuthCodeDefaultsToEmpty(): void
    {
        $this->assertEmpty(TestIntegration::exchange_oauth_code('code', 'https://example.com'));
    }

    public function testRefreshOAuthTokenDefaultsToEmpty(): void
    {
        $this->assertEmpty(TestIntegration::refresh_oauth_token('token'));
    }

    public function testGetOAuthScopesDefaultsToEmpty(): void
    {
        $this->assertEmpty(TestIntegration::get_oauth_scopes());
    }

    public function testCustomIntegrationOverrides(): void
    {
        $this->assertEquals('custom', CustomIntegration::get_slug());
        $this->assertEquals('Custom Integration', CustomIntegration::get_name());
        $this->assertEquals('custom-icon', CustomIntegration::get_icon());
        $this->assertTrue(CustomIntegration::requires_connection());
        $this->assertEquals('api_key', CustomIntegration::get_auth_type());
    }

    public function testCustomIntegrationTriggers(): void
    {
        $triggers = CustomIntegration::get_triggers();

        $this->assertArrayHasKey('item_created', $triggers);
        $this->assertEquals('Item Created', $triggers['item_created']['label']);
    }

    public function testCustomIntegrationActions(): void
    {
        $actions = CustomIntegration::get_actions();

        $this->assertArrayHasKey('create_item', $actions);
        $this->assertArrayHasKey('update_item', $actions);
    }

    public function testCustomIntegrationAuthFields(): void
    {
        $fields = CustomIntegration::get_auth_fields();

        $this->assertArrayHasKey('api_key', $fields);
        $this->assertEquals('password', $fields['api_key']['type']);
        $this->assertTrue($fields['api_key']['required']);
    }

    public function testCustomIntegrationResolveTrigger(): void
    {
        $result = CustomIntegration::resolve_trigger([], [123]);

        $this->assertEquals(['item_id' => 123], $result);
    }

    public function testCustomIntegrationExecuteNode(): void
    {
        $input = ['original' => 'data'];
        $result = CustomIntegration::execute_node([], $input);

        $this->assertEquals('main', $result['port']);
        $this->assertTrue($result['data']['executed']);
        $this->assertEquals('data', $result['data']['original']);
    }

    public function testCustomIntegrationOutputPorts(): void
    {
        $ports = CustomIntegration::get_output_ports();

        $this->assertContains('main', $ports);
        $this->assertContains('error', $ports);
        $this->assertCount(2, $ports);
    }
}
