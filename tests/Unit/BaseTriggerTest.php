<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Classes\BaseTrigger;

/**
 * Test implementation of BaseTrigger
 */
class TestPublishPostTrigger extends BaseTrigger
{
    public static function get_label(): string
    {
        return 'Test Post Published';
    }

    public static function get_hook(): string
    {
        return 'publish_post';
    }

    public static function get_description(): string
    {
        return 'Fires when a post is published';
    }

    public static function get_config_schema(): array
    {
        return [
            [
                'key'      => 'post_type',
                'label'    => 'Post Type',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['label' => 'Post', 'value' => 'post'],
                    ['label' => 'Page', 'value' => 'page'],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array
    {
        return [
            'post_id'    => 'integer',
            'post_title' => 'string',
            'post_type'  => 'string',
            'status'     => 'string',
        ];
    }

    public static function matches(array $node, array $hook_args): bool
    {
        $config    = $node['data']['config'] ?? [];
        $post_type = $config['post_type'] ?? null;
        $post      = get_post($hook_args[0] ?? 0);

        if (!$post) {
            return false;
        }

        if ($post_type && $post->post_type !== $post_type) {
            return false;
        }

        return true;
    }

    public static function resolve(array $node, array $hook_args)
    {
        $post = get_post($hook_args[0] ?? 0);
        if (!$post) {
            return false;
        }

        return [
            'post_id'    => $post->ID,
            'post_title' => $post->post_title,
            'post_type'  => $post->post_type,
            'status'     => $post->post_status,
        ];
    }
}

/**
 * Minimal trigger for testing defaults
 */
class MinimalTrigger extends BaseTrigger
{
    public static function get_label(): string
    {
        return 'Minimal Trigger';
    }

    public static function get_hook(): string
    {
        return 'minimal_hook';
    }

    public static function resolve(array $node, array $hook_args)
    {
        return ['data' => 'minimal'];
    }
}

class BaseTriggerTest extends TestCase
{
    /**
     * Test that get_key() auto-generates from class name
     */
    public function testGetKeyAutoGeneratesFromClassName(): void
    {
        // TestPublishPostTrigger -> test_publish_post_trigger
        $this->assertEquals('test_publish_post_trigger', TestPublishPostTrigger::get_key());

        // MinimalTrigger -> minimal_trigger
        $this->assertEquals('minimal_trigger', MinimalTrigger::get_key());
    }

    /**
     * Test that get_label() returns correct value
     */
    public function testGetLabelReturnsCorrectValue(): void
    {
        $this->assertEquals('Test Post Published', TestPublishPostTrigger::get_label());
        $this->assertEquals('Minimal Trigger', MinimalTrigger::get_label());
    }

    /**
     * Test that get_hook() returns correct hook name
     */
    public function testGetHookReturnsCorrectHook(): void
    {
        $this->assertEquals('publish_post', TestPublishPostTrigger::get_hook());
        $this->assertEquals('minimal_hook', MinimalTrigger::get_hook());
    }

    /**
     * Test that get_description() has a default empty implementation
     */
    public function testGetDescriptionDefaultsToEmpty(): void
    {
        $this->assertEquals('', MinimalTrigger::get_description());
        $this->assertEquals('Fires when a post is published', TestPublishPostTrigger::get_description());
    }

    /**
     * Test that get_config_schema() has a default empty implementation
     */
    public function testGetConfigSchemaDefaultsToEmpty(): void
    {
        $this->assertEmpty(MinimalTrigger::get_config_schema());
        $this->assertNotEmpty(TestPublishPostTrigger::get_config_schema());
    }

    /**
     * Test that get_output_schema() has a default empty implementation
     */
    public function testGetOutputSchemaDefaultsToEmpty(): void
    {
        $this->assertEmpty(MinimalTrigger::get_output_schema());
        $this->assertNotEmpty(TestPublishPostTrigger::get_output_schema());
    }

    /**
     * Test that matches() defaults to true
     */
    public function testMatchesDefaultsToTrue(): void
    {
        $this->assertTrue(MinimalTrigger::matches([], []));
    }

    /**
     * Test custom matches() implementation
     */
    public function testCustomMatchesImplementation(): void
    {
        // Test with matching post type
        $node = [
            'data' => [
                'config' => ['post_type' => 'post'],
            ],
        ];
        $this->assertTrue(TestPublishPostTrigger::matches($node, [1]));

        // Test with non-matching post type (post mock returns 'post' type)
        $node = [
            'data' => [
                'config' => ['post_type' => 'page'],
            ],
        ];
        $this->assertFalse(TestPublishPostTrigger::matches($node, [1]));

        // Test with no post type filter
        $node = ['data' => ['config' => []]];
        $this->assertTrue(TestPublishPostTrigger::matches($node, [1]));
    }

    /**
     * Test resolve() returns correct payload
     */
    public function testResolveReturnsCorrectPayload(): void
    {
        $result = TestPublishPostTrigger::resolve([], [42]);

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['post_id']);
        $this->assertEquals('Test Post 42', $result['post_title']);
        $this->assertEquals('post', $result['post_type']);
        $this->assertEquals('publish', $result['status']);
    }

    /**
     * Test resolve() returns false for invalid data
     */
    public function testResolveReturnsFalseForInvalidData(): void
    {
        $result = TestPublishPostTrigger::resolve([], [0]);
        $this->assertFalse($result);
    }

    /**
     * Test build() returns complete trigger definition
     */
    public function testBuildReturnsCompleteTriggerDefinition(): void
    {
        $definition = TestPublishPostTrigger::build();

        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('hook', $definition);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('config_schema', $definition);
        $this->assertArrayHasKey('output_schema', $definition);
        $this->assertArrayHasKey('_class', $definition);

        $this->assertEquals('Test Post Published', $definition['label']);
        $this->assertEquals('publish_post', $definition['hook']);
        $this->assertEquals(TestPublishPostTrigger::class, $definition['_class']);
    }

    /**
     * Test build() omits empty optional fields
     */
    public function testBuildOmitsEmptyOptionalFields(): void
    {
        $definition = MinimalTrigger::build();

        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('hook', $definition);
        $this->assertArrayNotHasKey('description', $definition);
        $this->assertArrayNotHasKey('config_schema', $definition);
        $this->assertArrayNotHasKey('output_schema', $definition);
    }

    /**
     * Test config_schema has required structure
     */
    public function testConfigSchemaHasRequiredStructure(): void
    {
        $schema = TestPublishPostTrigger::get_config_schema();

        $this->assertIsArray($schema);
        $this->assertCount(1, $schema);

        $field = $schema[0];
        $this->assertArrayHasKey('key', $field);
        $this->assertArrayHasKey('label', $field);
        $this->assertArrayHasKey('type', $field);
        $this->assertArrayHasKey('required', $field);
        $this->assertArrayHasKey('options', $field);
    }

    /**
     * Test output_schema has correct types
     */
    public function testOutputSchemaHasCorrectTypes(): void
    {
        $schema = TestPublishPostTrigger::get_output_schema();

        $this->assertArrayHasKey('post_id', $schema);
        $this->assertArrayHasKey('post_title', $schema);
        $this->assertArrayHasKey('post_type', $schema);
        $this->assertArrayHasKey('status', $schema);

        $this->assertEquals('integer', $schema['post_id']);
        $this->assertEquals('string', $schema['post_title']);
    }
}
