<?php

use PHPUnit\Framework\TestCase;
use Zaplane\Integrations\Buddyboss\Buddyboss;
use Zaplane\Integrations\Buddyboss\BuddybossActionsTrait;

require_once __DIR__ . '/mocks/buddyboss-mocks.php';

class BuddybossTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_update_extended_profile_success()
    {
        $input = [
            'user_email' => 'user@test.com',
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'nick_name'  => 'JD',
            'paragraph'  => 'Hello',
            'number'     => 123,
            'checkbox'   => true,
            'drop_down'  => 'A',
            'radio_buttons' => 'B',
        ];

        $result = Buddyboss::execute_node([
            'data' => [
                'event' => 'update_extended_profile',
                'config' => []
            ]
        ], $input);

        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('user_id', $result['data']);
    }

    public function test_add_user_to_group_success()
    {
        $input = [
            'user_email' => 'user@test.com',
            'group_id'   => 1
        ];

        $result = Buddyboss::execute_node([
            'data' => [
                'event' => 'add_user_to_group'
            ]
        ], $input);

        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('user', $result['data']);
    }

    public function test_create_group_success()
    {
        $input = [
            'group_name'   => 'Test Group',
            'group_status' => 'public',
            'creator_email'=> 'user@test.com'
        ];

        $result = Buddyboss::execute_node([
            'data' => [
                'event' => 'create_group'
            ]
        ], $input);

        $this->assertEquals('main', $result['port']);
        $this->assertArrayHasKey('name', $result['data']);
    }

    public function test_send_private_message_success()
    {
        $input = [
            'sender_email'   => 'user@test.com',
            'receiver_email' => 'user@test.com',
            'message_subject'=> 'Hello',
            'message_content'=> 'Test message'
        ];

        $result = Buddyboss::execute_node([
            'data' => [
                'event' => 'send_private_message'
            ]
        ], $input);

        $this->assertEquals('main', $result['port']);
    }

    public function test_error_when_user_not_found()
    {
        $input = [
            'user_email' => 'notfound@test.com',
            'group_id'   => 1
        ];

        $result = Buddyboss::execute_node([
            'data' => [
                'event' => 'add_user_to_group'
            ]
        ], $input);

        $this->assertArrayHasKey('error', $result['data']);
    }
}