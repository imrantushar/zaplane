<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Ultimatemember;

class UltimatememberTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Ultimatemember::class;
    }

    public function test_user_login_trigger() {
        $node = ['event' => 'user_login'];
        $args = ['testuser', (object)['ID' => 1]];

        $result = Ultimatemember::resolve_trigger($node, $args);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('1', $result['data']['user_id']); // string now
        $this->assertEquals('subscriber', $result['data']['role']); // default role
    }

    public function test_user_registration_trigger() {
        $node = ['event' => 'user_registration'];
        $args = [1, ['submitted' => ['field' => 'value']]];

        $result = Ultimatemember::resolve_trigger($node, $args);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('1', $result['data']['user_id']);
        $this->assertEquals('subscriber', $result['data']['role']);
        $this->assertEquals(['field' => 'value'], $result['data']['form_data']);
    }

    public function test_inactive_user_trigger() {
        $node = ['event' => 'inactive_user'];
        $args = [1];

        $result = Ultimatemember::resolve_trigger($node, $args);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('inactive', $result['data']['status']);
    }

    public function test_change_user_role_trigger() {
        $node = ['event' => 'change_user_role'];
        $args = [1, 'administrator'];

        $result = Ultimatemember::resolve_trigger($node, $args);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('administrator', $result['data']['role']);
    }

    public function test_execute_node_change_role() {
        $node = [
            'data' => [
                'event' => 'um_set_user_role',
                'config' => [
                    'user_id' => 1,
                    'role' => 'administrator'
                ]
            ]
        ];

        $input = ['user_id' => 1];

        $result = Ultimatemember::execute_node($node, $input);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(1, $result['data']['user_id']);
        $this->assertEquals('administrator', $result['data']['role']);
    }
}