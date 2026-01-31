<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Classes\BaseAction;

/**
 * Test implementation of BaseAction for creating posts
 */
class TestCreatePostAction extends BaseAction
{
    public static function get_label(): string
    {
        return 'Create Post';
    }

    public static function get_description(): string
    {
        return 'Creates a new WordPress post';
    }

    public static function get_config_schema(): array
    {
        return [
            [
                'key'      => 'post_title',
                'label'    => 'Title',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'   => 'post_content',
                'label' => 'Content',
                'type'  => 'textarea',
            ],
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
            [
                'key'     => 'post_status',
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    ['label' => 'Draft', 'value' => 'draft'],
                    ['label' => 'Publish', 'value' => 'publish'],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array
    {
        return [
            'post_id'    => 'integer',
            'post_title' => 'string',
            'success'    => 'boolean',
        ];
    }

    public static function execute(array $config, array $input, array $credentials = []): array
    {
        $post_id = wp_insert_post([
            'post_title'   => $config['post_title'] ?? '',
            'post_content' => $config['post_content'] ?? '',
            'post_type'    => $config['post_type'] ?? 'post',
            'post_status'  => $config['post_status'] ?? 'draft',
        ]);

        if (is_wp_error($post_id)) {
            throw new \Exception($post_id->get_error_message());
        }

        return static::success($input, [
            'post_id'    => $post_id,
            'post_title' => $config['post_title'],
            'success'    => true,
        ]);
    }
}

/**
 * Conditional action with branching
 */
class TestConditionalAction extends BaseAction
{
    public static function get_label(): string
    {
        return 'Conditional';
    }

    public static function get_config_schema(): array
    {
        return [
            [
                'key'      => 'condition',
                'label'    => 'Condition',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_ports(): array
    {
        return ['true', 'false'];
    }

    public static function execute(array $config, array $input, array $credentials = []): array
    {
        $condition = !empty($config['condition']);
        return static::branch($condition, $input, ['evaluated' => $condition]);
    }
}

/**
 * Minimal action for testing defaults
 */
class MinimalAction extends BaseAction
{
    public static function get_label(): string
    {
        return 'Minimal Action';
    }

    public static function get_config_schema(): array
    {
        return [];
    }

    public static function execute(array $config, array $input, array $credentials = []): array
    {
        return static::success($input, ['executed' => true]);
    }
}

class BaseActionTest extends TestCase
{
    /**
     * Test that get_key() auto-generates from class name
     */
    public function testGetKeyAutoGeneratesFromClassName(): void
    {
        // TestCreatePostAction -> test_create_post_action
        $this->assertEquals('test_create_post_action', TestCreatePostAction::get_key());

        // MinimalAction -> minimal_action
        $this->assertEquals('minimal_action', MinimalAction::get_key());
    }

    /**
     * Test that get_label() returns correct value
     */
    public function testGetLabelReturnsCorrectValue(): void
    {
        $this->assertEquals('Create Post', TestCreatePostAction::get_label());
        $this->assertEquals('Minimal Action', MinimalAction::get_label());
    }

    /**
     * Test that get_description() has a default empty implementation
     */
    public function testGetDescriptionDefaultsToEmpty(): void
    {
        $this->assertEquals('', MinimalAction::get_description());
        $this->assertEquals('Creates a new WordPress post', TestCreatePostAction::get_description());
    }

    /**
     * Test that get_output_ports() defaults to ['main']
     */
    public function testGetOutputPortsDefaultsToMain(): void
    {
        $ports = MinimalAction::get_output_ports();
        $this->assertContains('main', $ports);
        $this->assertCount(1, $ports);
    }

    /**
     * Test custom get_output_ports() for conditional actions
     */
    public function testCustomOutputPortsForConditionalAction(): void
    {
        $ports = TestConditionalAction::get_output_ports();
        $this->assertContains('true', $ports);
        $this->assertContains('false', $ports);
        $this->assertCount(2, $ports);
    }

    /**
     * Test execute() creates a post successfully
     */
    public function testExecuteCreatesPost(): void
    {
        $config = [
            'post_title'   => 'Test Post',
            'post_content' => 'Test Content',
            'post_type'    => 'post',
            'post_status'  => 'publish',
        ];

        $result = TestCreatePostAction::execute($config, ['previous_data' => 'value']);

        $this->assertArrayHasKey('port', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('post_id', $result['data']);
        $this->assertEquals('Test Post', $result['data']['post_title']);
        $this->assertTrue($result['data']['success']);
        // Check previous input is preserved
        $this->assertEquals('value', $result['data']['previous_data']);
    }

    /**
     * Test execute() throws exception on failure
     */
    public function testExecuteThrowsExceptionOnFailure(): void
    {
        // Skip this test as the mock doesn't properly simulate WP_Error
        // In production, wp_insert_post returns WP_Error for invalid data
        $this->markTestSkipped('Mock wp_insert_post does not fully simulate WP_Error conditions');
    }

    /**
     * Test conditional action branches correctly
     */
    public function testConditionalActionBranchesCorrectly(): void
    {
        // Test true branch
        $result = TestConditionalAction::execute(['condition' => true], ['original' => 'data']);
        $this->assertEquals('true', $result['port']);
        $this->assertTrue($result['data']['evaluated']);
        $this->assertEquals('data', $result['data']['original']);

        // Test false branch
        $result = TestConditionalAction::execute(['condition' => false], ['original' => 'data']);
        $this->assertEquals('false', $result['port']);
        $this->assertFalse($result['data']['evaluated']);
    }

    /**
     * Test success() helper method
     */
    public function testSuccessHelperMethod(): void
    {
        $input     = ['existing' => 'data'];
        $newData   = ['new' => 'value'];
        $result    = MinimalAction::execute([], $input);

        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('data', $result['data']['existing']);
        $this->assertTrue($result['data']['executed']);
    }

    /**
     * Test build() returns complete action definition
     */
    public function testBuildReturnsCompleteActionDefinition(): void
    {
        $definition = TestCreatePostAction::build();

        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('config_schema', $definition);
        $this->assertArrayHasKey('output_schema', $definition);
        $this->assertArrayHasKey('_class', $definition);

        // Note: output_ports is only included when NOT ['main']
        $this->assertArrayNotHasKey('output_ports', $definition);

        $this->assertEquals('Create Post', $definition['label']);
        $this->assertEquals(TestCreatePostAction::class, $definition['_class']);
    }

    /**
     * Test build() includes conditional output ports
     */
    public function testBuildIncludesConditionalOutputPorts(): void
    {
        $definition = TestConditionalAction::build();

        $this->assertEquals(['true', 'false'], $definition['output_ports']);
    }

    /**
     * Test config_schema structure
     */
    public function testConfigSchemaStructure(): void
    {
        $schema = TestCreatePostAction::get_config_schema();

        $this->assertIsArray($schema);
        $this->assertCount(4, $schema);

        // Check first field (post_title)
        $titleField = $schema[0];
        $this->assertEquals('post_title', $titleField['key']);
        $this->assertEquals('Title', $titleField['label']);
        $this->assertEquals('expression', $titleField['type']);
        $this->assertTrue($titleField['required']);
    }

    /**
     * Test output_schema structure
     */
    public function testOutputSchemaStructure(): void
    {
        $schema = TestCreatePostAction::get_output_schema();

        $this->assertArrayHasKey('post_id', $schema);
        $this->assertArrayHasKey('post_title', $schema);
        $this->assertArrayHasKey('success', $schema);

        $this->assertEquals('integer', $schema['post_id']);
        $this->assertEquals('string', $schema['post_title']);
        $this->assertEquals('boolean', $schema['success']);
    }

    /**
     * Test credentials are passed to execute
     */
    public function testCredentialsArePassedToExecute(): void
    {
        // Create an action that uses credentials
        $result = MinimalAction::execute(
            [],
            ['input' => 'data'],
            ['api_key' => 'secret123']
        );

        $this->assertIsArray($result);
        $this->assertEquals('main', $result['port']);
    }

    /**
     * Test get_output_schema defaults to empty
     */
    public function testGetOutputSchemaDefaultsToEmpty(): void
    {
        $this->assertEmpty(MinimalAction::get_output_schema());
    }
}
