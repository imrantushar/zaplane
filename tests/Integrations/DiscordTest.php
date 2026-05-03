<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Discord;

class DiscordTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Discord::class;
    }

    private function baseNode($event, $config = [])
    {
        return [
            'data' => [
                'event' => $event,
                'config' => array_merge([
                    'guild_id' => 'guild_123',
                    'channel_id' => 'channel_123',
                    'name' => 'test',
                    'content' => 'hello',
                    'message_id' => 'msg_123',
                    'user_id' => 'user_123',
                    'member_id' => 'member_123',
                    'role_id' => 'role_123',
                    'type' => 0
                ], $config),
                'connection_id' => 1
            ]
        ];
    }

    private function executeAction($event, $config = [])
    {
        return Discord::execute_node(
            $this->baseNode($event, $config),
            []
        );
    }

    public function test_create_channel()
    {
        $result = $this->executeAction('create_channel');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_update_channel()
    {
        $result = $this->executeAction('update_channel');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_get_channel_by_name()
    {
        $result = $this->executeAction('get_channel_by_name');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_find_channel()
    {
        $result = $this->executeAction('find_channel', [
            'search_query' => 'general'
        ]);

        $this->assertArrayHasKey('port', $result);
    }

    public function test_create_channel_invite()
    {
        $result = $this->executeAction('create_a_channel_invite');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_send_channel_message()
    {
        $result = $this->executeAction('send_channel_message');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_send_direct_message()
    {
        $result = $this->executeAction('send_direct_message');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_delete_message()
    {
        $result = $this->executeAction('delete_message');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_get_message()
    {
        $result = $this->executeAction('get_message');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_get_many_message()
    {
        $result = $this->executeAction('get_many_message', [
            'limit' => 10
        ]);

        $this->assertArrayHasKey('port', $result);
    }

    public function test_react_with_emoji()
    {
        $result = $this->executeAction('react_with_emoji_to_message', [
            'emoji' => ':100:'
        ]);

        $this->assertArrayHasKey('port', $result);
    }

    public function test_get_many_member()
    {
        $result = $this->executeAction('get_many_member');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_add_role_to_member()
    {
        $result = $this->executeAction('add_role_to_member');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_remove_role_from_member()
    {
        $result = $this->executeAction('remove_role_from_member');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_find_user()
    {
        $result = $this->executeAction('find_user', [
            'search_query' => 'john'
        ]);

        $this->assertArrayHasKey('port', $result);
    }

    public function test_create_forum_post()
    {
        $result = $this->executeAction('create_new_forum_post');
        $this->assertArrayHasKey('port', $result);
    }

    public function test_missing_bot_token()
    {
        $node = [
            'data' => [
                'event' => 'send_channel_message',
                'config' => [],
                'connection_id' => 0
            ]
        ];

        $result = Discord::execute_node($node, []);

        $this->assertArrayHasKey('port', $result);
    }
}