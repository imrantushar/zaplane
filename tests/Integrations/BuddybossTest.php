<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Buddyboss;

class BuddyBossActionsTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Buddyboss::class;
    }

    public function test_handle_activity_post_action(): void
    {
        $node = [
            'data' => [
                'event'  => 'create_activity_post',
                'config' => []
            ]
        ];

        $input = [
            'author_email' => 'john@example.com',
            'content'      => 'Hello BuddyBoss',
        ];
        
        $result = Buddyboss::execute_node($node, $input);

        $this->assertIsArray($result);
    }

    public function test_send_friend_request_action(): void
    {
        $node = [
            'data' => [
                'event' => 'send_friend_request',
                'config' => []
            ]
        ];

        $input = [
            'sender_email'   => 'a@example.com',
            'receiver_email' => 'b@example.com',
        ];

        $result = Buddyboss::execute_node($node, $input);

        $this->assertIsArray($result);
    }

    public function test_add_user_to_group_action(): void
    {
        $node = [
            'data' => [
                'event' => 'add_user_to_group',
                'config' => []
            ]
        ];

        $input = [
            'user_email' => 'user@example.com',
            'group_id'   => 5,
        ];

        $result = Buddyboss::execute_node($node, $input);

        $this->assertIsArray($result);
    }
}