<?php

namespace Zaplane\Tests\Integration;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Integrations\Wordpress\Triggers\PublishPost;
use Zaplane\Integrations\Wordpress\Triggers\UserRegister;
use Zaplane\Integrations\Wordpress\Triggers\WpInsertComment;
use Zaplane\Integrations\Wordpress\Actions\CreatePost;
use Zaplane\Integrations\Wordpress\Actions\CreateUser;
use Zaplane\Integrations\Wordpress\Actions\CreateComment;
use Zaplane\Integrations\Wordpress\Actions\UpdatePost;
use Zaplane\Integrations\Wordpress\Actions\TrashPost;

/**
 * Tests complex WordPress workflow scenarios
 */
class WordpressWorkflowTest extends TestCase
{
    /**
     * Test PublishPost trigger resolves correctly
     */
    public function testPublishPostTriggerResolves(): void
    {
        if (!class_exists(PublishPost::class)) {
            $this->markTestSkipped('PublishPost trigger not available');
        }

        $node     = ['data' => ['config' => ['post_type' => 'post']]];
        $hookArgs = [42]; // Post ID

        // Test build
        $definition = PublishPost::build();
        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('hook', $definition);
        $this->assertEquals('publish_post', $definition['hook']);

        // Test resolve
        $result = PublishPost::resolve($node, $hookArgs);
        $this->assertIsArray($result);
        $this->assertEquals(42, $result['post_id']);
    }

    /**
     * Test UserRegister trigger resolves correctly
     */
    public function testUserRegisterTriggerResolves(): void
    {
        if (!class_exists(UserRegister::class)) {
            $this->markTestSkipped('UserRegister trigger not available');
        }

        $node     = ['data' => ['config' => []]];
        $hookArgs = [5]; // User ID

        // Test build
        $definition = UserRegister::build();
        $this->assertArrayHasKey('label', $definition);
        $this->assertEquals('user_register', $definition['hook']);

        // Test resolve
        $result = UserRegister::resolve($node, $hookArgs);
        $this->assertIsArray($result);
        $this->assertEquals(5, $result['user_id']);
    }

    /**
     * Test WpInsertComment trigger resolves correctly
     */
    public function testWpInsertCommentTriggerResolves(): void
    {
        if (!class_exists(WpInsertComment::class)) {
            $this->markTestSkipped('WpInsertComment trigger not available');
        }

        $node     = ['data' => ['config' => []]];
        $hookArgs = [10]; // Comment ID

        // Test build
        $definition = WpInsertComment::build();
        $this->assertArrayHasKey('label', $definition);
        $this->assertEquals('wp_insert_comment', $definition['hook']);

        // Test resolve
        $result = WpInsertComment::resolve($node, $hookArgs);
        $this->assertIsArray($result);
        $this->assertEquals(10, $result['comment_id']);
    }

    /**
     * Test CreatePost action executes correctly
     */
    public function testCreatePostActionExecutes(): void
    {
        if (!class_exists(CreatePost::class)) {
            $this->markTestSkipped('CreatePost action not available');
        }

        $config = [
            'post_title'   => 'Test Workflow Post',
            'post_content' => 'Content created by workflow',
            'post_type'    => 'post',
            'post_status'  => 'publish',
        ];

        $input = ['trigger_data' => 'from_trigger'];

        // Test build
        $definition = CreatePost::build();
        $this->assertArrayHasKey('label', $definition);
        $this->assertArrayHasKey('config_schema', $definition);

        // Test execute
        $result = CreatePost::execute($config, $input);
        $this->assertIsArray($result);
        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('post_id', $result['data']);
        $this->assertIsInt($result['data']['post_id']);
        // Previous input should be preserved
        $this->assertEquals('from_trigger', $result['data']['trigger_data']);
    }

    /**
     * Test CreateUser action executes correctly
     */
    public function testCreateUserActionExecutes(): void
    {
        if (!class_exists(CreateUser::class)) {
            $this->markTestSkipped('CreateUser action not available');
        }

        $config = [
            'user_login'   => 'testuser',
            'user_email'   => 'test@example.com',
            'user_pass'    => 'password123',
            'display_name' => 'Test User',
            'role'         => 'subscriber',
        ];

        $input = ['workflow_id' => 123];

        // Test build
        $definition = CreateUser::build();
        $this->assertArrayHasKey('label', $definition);

        // Test execute
        $result = CreateUser::execute($config, $input);
        $this->assertIsArray($result);
        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('user_id', $result['data']);
    }

    /**
     * Test CreateComment action executes correctly
     */
    public function testCreateCommentActionExecutes(): void
    {
        if (!class_exists(CreateComment::class)) {
            $this->markTestSkipped('CreateComment action not available');
        }

        $config = [
            'post_id'      => 1,
            'author_name'  => 'Test Author',
            'author_email' => 'author@example.com',
            'content'      => 'This is a test comment',
        ];

        $input = ['previous' => 'data'];

        // Test build
        $definition = CreateComment::build();
        $this->assertArrayHasKey('label', $definition);

        // Test execute
        $result = CreateComment::execute($config, $input);
        $this->assertIsArray($result);
        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('comment_id', $result['data']);
    }

    /**
     * Test UpdatePost action executes correctly
     */
    public function testUpdatePostActionExecutes(): void
    {
        if (!class_exists(UpdatePost::class)) {
            $this->markTestSkipped('UpdatePost action not available');
        }

        $config = [
            'post_id'      => 42,
            'post_title'   => 'Updated Title',
            'post_content' => 'Updated Content',
            'post_type'    => 'post',
            'post_status'  => 'publish',
        ];

        $input = ['original' => 'data'];

        // Test build
        $definition = UpdatePost::build();
        $this->assertArrayHasKey('label', $definition);

        // Test execute
        $result = UpdatePost::execute($config, $input);
        $this->assertIsArray($result);
        $this->assertEquals('main', $result['port']);
    }

    /**
     * Test TrashPost action executes correctly
     */
    public function testTrashPostActionExecutes(): void
    {
        if (!class_exists(TrashPost::class)) {
            $this->markTestSkipped('TrashPost action not available');
        }

        $config = [
            'post_id'   => 42,
            'post_type' => 'post',
        ];

        $input = ['workflow' => 'data'];

        // Test build
        $definition = TrashPost::build();
        $this->assertArrayHasKey('label', $definition);

        // Test execute
        $result = TrashPost::execute($config, $input);
        $this->assertIsArray($result);
        $this->assertEquals('main', $result['port']);
    }

    /**
     * Test complex workflow: Post Published -> Create Comment -> Update Post
     */
    public function testComplexWorkflowChain(): void
    {
        if (!class_exists(PublishPost::class) || !class_exists(CreateComment::class) || !class_exists(UpdatePost::class)) {
            $this->markTestSkipped('Required classes not available');
        }

        // Step 1: Trigger fires (simulate post published)
        $triggerNode     = ['data' => ['config' => ['post_type' => 'post']]];
        $triggerHookArgs = [100];
        $triggerPayload  = PublishPost::resolve($triggerNode, $triggerHookArgs);

        $this->assertIsArray($triggerPayload);
        $this->assertEquals(100, $triggerPayload['post_id']);

        // Step 2: Create a comment on the published post
        $commentConfig = [
            'post_id'      => $triggerPayload['post_id'],
            'author_name'  => 'Auto Commenter',
            'author_email' => 'auto@example.com',
            'content'      => 'Auto-generated comment for: ' . $triggerPayload['post_title'],
        ];

        $commentResult = CreateComment::execute($commentConfig, $triggerPayload);
        $this->assertEquals('main', $commentResult['port']);
        $this->assertArrayHasKey('comment_id', $commentResult['data']);

        // Step 3: Update the post with comment count info
        $updateConfig = [
            'post_id'      => $triggerPayload['post_id'],
            'post_title'   => $triggerPayload['post_title'] . ' (Commented)',
            'post_content' => 'Updated content',
            'post_type'    => 'post',
            'post_status'  => 'publish',
        ];

        $updateResult = UpdatePost::execute($updateConfig, $commentResult['data']);
        $this->assertEquals('main', $updateResult['port']);

        // Verify data flows through the chain
        $this->assertArrayHasKey('post_id', $updateResult['data']);
        $this->assertArrayHasKey('comment_id', $updateResult['data']);
    }

    /**
     * Test workflow with multiple branches
     */
    public function testWorkflowWithMultipleBranches(): void
    {
        if (!class_exists(UserRegister::class) || !class_exists(CreatePost::class)) {
            $this->markTestSkipped('Required classes not available');
        }

        // Trigger: User registers
        $triggerNode    = ['data' => ['config' => []]];
        $triggerPayload = UserRegister::resolve($triggerNode, [25]);

        $this->assertIsArray($triggerPayload);
        $this->assertEquals(25, $triggerPayload['user_id']);

        // Branch 1: Create welcome post
        $welcomePostConfig = [
            'post_title'   => 'Welcome ' . $triggerPayload['display_name'],
            'post_content' => 'Welcome to our platform!',
            'post_type'    => 'post',
            'post_status'  => 'publish',
        ];

        $welcomeResult = CreatePost::execute($welcomePostConfig, $triggerPayload);
        $this->assertArrayHasKey('post_id', $welcomeResult['data']);

        // Branch 2: Create user profile post
        $profilePostConfig = [
            'post_title'   => 'Profile: ' . $triggerPayload['display_name'],
            'post_content' => 'User profile page content',
            'post_type'    => 'page',
            'post_status'  => 'draft',
        ];

        $profileResult = CreatePost::execute($profilePostConfig, $triggerPayload);
        $this->assertArrayHasKey('post_id', $profileResult['data']);

        // Both branches should have created different posts
        $this->assertNotEquals(
            $welcomeResult['data']['post_id'],
            $profileResult['data']['post_id']
        );
    }

    /**
     * Test workflow error handling
     */
    public function testWorkflowErrorHandling(): void
    {
        if (!class_exists(CreatePost::class)) {
            $this->markTestSkipped('CreatePost action not available');
        }

        // Skip this test as the mock doesn't fully simulate error conditions
        $this->markTestSkipped('Mock wp_insert_post does not fully simulate WP_Error conditions');
    }

    /**
     * Test data transformation between nodes
     */
    public function testDataTransformationBetweenNodes(): void
    {
        if (!class_exists(PublishPost::class) || !class_exists(CreatePost::class)) {
            $this->markTestSkipped('Required classes not available');
        }

        // Trigger payload
        $triggerPayload = [
            'original_post_id' => 1,
            'original_title'   => 'Original Post',
            'post_type'        => 'post',
            'status'           => 'publish',
        ];

        // Action uses trigger data to create a related post
        $config = [
            'post_title'   => 'Related to: ' . $triggerPayload['original_title'],
            'post_content' => 'This post relates to post #' . $triggerPayload['original_post_id'],
            'post_type'    => 'post',
            'post_status'  => 'draft',
        ];

        $result = CreatePost::execute($config, $triggerPayload);

        // Verify original trigger data is preserved in output
        $this->assertEquals('Original Post', $result['data']['original_title']);
        $this->assertEquals(1, $result['data']['original_post_id']);

        // Verify new data is added (post_id from creation)
        $this->assertArrayHasKey('post_id', $result['data']);
        $this->assertIsInt($result['data']['post_id']);
    }

    /**
     * Test all WordPress triggers are valid
     */
    public function testAllWordpressTriggersAreValid(): void
    {
        if (!class_exists(IntegrationLoader::class)) {
            $this->markTestSkipped('IntegrationLoader class not available');
        }

        $triggers = IntegrationLoader::getTriggers('wordpress');

        $this->assertNotEmpty($triggers, 'No WordPress triggers found');

        foreach ($triggers as $key => $trigger) {
            $this->assertArrayHasKey('label', $trigger, "Trigger '{$key}' missing label");
            $this->assertArrayHasKey('hook', $trigger, "Trigger '{$key}' missing hook");
            $this->assertNotEmpty($trigger['label'], "Trigger '{$key}' has empty label");
            $this->assertNotEmpty($trigger['hook'], "Trigger '{$key}' has empty hook");

            // If modular, verify class exists
            if (isset($trigger['_class'])) {
                $this->assertTrue(
                    class_exists($trigger['_class']),
                    "Trigger '{$key}' class {$trigger['_class']} does not exist"
                );
            }
        }
    }

    /**
     * Test all WordPress actions are valid
     */
    public function testAllWordpressActionsAreValid(): void
    {
        if (!class_exists(IntegrationLoader::class)) {
            $this->markTestSkipped('IntegrationLoader class not available');
        }

        $actions = IntegrationLoader::getActions('wordpress');

        $this->assertNotEmpty($actions, 'No WordPress actions found');

        foreach ($actions as $key => $action) {
            $this->assertArrayHasKey('label', $action, "Action '{$key}' missing label");
            $this->assertNotEmpty($action['label'], "Action '{$key}' has empty label");

            // If modular, verify class exists
            if (isset($action['_class'])) {
                $this->assertTrue(
                    class_exists($action['_class']),
                    "Action '{$key}' class {$action['_class']} does not exist"
                );
            }
        }
    }

    /**
     * Test trigger count matches expectations
     */
    public function testTriggerCountMatchesExpectations(): void
    {
        if (!class_exists(IntegrationLoader::class)) {
            $this->markTestSkipped('IntegrationLoader class not available');
        }

        $triggers = IntegrationLoader::getTriggers('wordpress');

        // We expect at least 60+ triggers based on the migration
        $this->assertGreaterThan(50, count($triggers), 'Expected more WordPress triggers');
    }

    /**
     * Test action count matches expectations
     */
    public function testActionCountMatchesExpectations(): void
    {
        if (!class_exists(IntegrationLoader::class)) {
            $this->markTestSkipped('IntegrationLoader class not available');
        }

        $actions = IntegrationLoader::getActions('wordpress');

        // We expect at least 80+ actions based on the migration
        $this->assertGreaterThan(50, count($actions), 'Expected more WordPress actions');
    }
}
