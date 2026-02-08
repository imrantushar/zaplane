<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wordpress;

/**
 * WordPress Integration - Action Tests
 */
class WordpressActionsTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Wordpress::class;
    }

    /**
     * Actions to test - only those that work with our mocks
     */
    protected function getActionTests(): array
    {
        return [
            'create_post',
        ];
    }

    /**
     * @test
     */
    public function create_post_action_returns_post_id(): void
    {
        $node = $this->makeActionNode('create_post', [
            'post_title' => 'Test Post',
            'post_type' => 'post',
            'post_status' => 'draft',
        ]);

        $result = Wordpress::execute_node($node, []);

        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('post_id', $result['data']);
        $this->assertIsInt($result['data']['post_id']);
    }
}
