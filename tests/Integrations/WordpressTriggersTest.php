<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wordpress;

/**
 * WordPress Integration - Trigger Tests
 */
class WordpressTriggersTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Wordpress::class;
    }

    /**
     * Triggers to test with mock data
     */
    protected function getTriggerTests(): array
    {
        return [
            'publish_post',
            'post_updated',
            'save_post',
            'wp_trash_post',
            'delete_post',
            'comment_post',
        ];
    }

    /**
     * @test
     */
    public function publish_post_trigger_returns_post_data(): void
    {
        $node = $this->makeTriggerNode('publish_post');
        $result = Wordpress::resolve_trigger($node, [1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ID', $result);
        $this->assertArrayHasKey('post_title', $result);
        $this->assertArrayHasKey('post_type', $result);
        $this->assertEquals(1, $result['ID']);
        $this->assertEquals('Test Post', $result['post_title']);
    }

    /**
     * @test
     */
    public function trigger_payload_contains_full_model_data(): void
    {
        $node = $this->makeTriggerNode('save_post');
        $result = Wordpress::resolve_trigger($node, [1]);

        $this->assertIsArray($result);
        // Check for key model fields
        $this->assertArrayHasKey('post_author', $result);
        $this->assertArrayHasKey('post_date', $result);
        $this->assertArrayHasKey('post_status', $result);
        $this->assertArrayHasKey('post_content', $result);
    }
}
