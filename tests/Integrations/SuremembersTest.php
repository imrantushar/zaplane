<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Suremembers;
use SureMembers\Inc\Access;
use Zaplane\Tests\WPMocks;

class SuremembersTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Suremembers::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset mocks
        Access::reset();
        WPMocks::reset();

        // Mock user
        WPMocks::setUser(1, [
			'ID'           => 1, // integer
			'user_email'   => 'john@example.com',
			'user_login'   => 'john',
			'display_name' => 'John Doe',
			'first_name'   => 'John',
			'last_name'    => 'Doe',
			'nickname'     => 'johnny',
			'roles'        => ['subscriber'],
		]);

        // Mock group post
        WPMocks::setPost(10, [
            'ID'        => 10, // important for integer group_id
            'post_title'=> 'Test Group',
            'post_type' => 'suremembers_group',
        ]);
    }

    /** @test */
    public function test_add_user_action()
    {
        $node = [
            'data' => [
                'event'  => 'add_user',
                'config' => [
                    'email'     => 'john@example.com',
                    'member_id' => 10,
                ],
            ],
        ];

        $result = Suremembers::execute_node($node, []);

        $this->assertIsArray($result);
		$this->assertEquals('main', $result['port']);

		$this->assertEquals(1, $result['data']['user']['user_id']);

        $this->assertCount(1, Access::$granted);
        $this->assertEquals(1, Access::$granted[0]['user_id']);
    }

    /** @test */
    public function test_remove_user_action()
    {
        $node = [
            'data' => [
                'event'  => 'remove_user',
                'config' => [
                    'email'     => 'john@example.com',
                    'member_id' => 10,
                ],
            ],
        ];

        $result = Suremembers::execute_node($node, []);
		$this->assertIsArray($result);
		$this->assertEquals('main', $result['port']);

		$this->assertEquals(1, $result['data']['user']['user_id']);

        $this->assertCount(1, Access::$revoked);
        $this->assertEquals(1, Access::$revoked[0]['user_id']);
    }

	public function test_access_group_trigger(): void
	{
		$node = $this->makeTriggerNode('access_group', [
			'member_id' => 1,
		]);

		$result = Suremembers::resolve_trigger(
			$node,
			[1, [1]]
		);

		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertEquals(1, $result['user']['user_id']);
	}

	public function test_remove_group_trigger(): void
	{
		$node = $this->makeTriggerNode('remove_group', [
			'member_id' => 1,
		]);

		$result = Suremembers::resolve_trigger(
			$node,
			[1, [1]]
		);

		$this->assertTrue($result['success']);
	}

	public function test_updated_group_trigger(): void
	{
		$node = $this->makeTriggerNode('updated_group', [
			'member_id' => 1,
		]);

		$result = Suremembers::resolve_trigger(
			$node,
			[1]
		);

		$this->assertTrue($result['success']);
		$this->assertEquals(1, $result['data']['ID']);
	}
}