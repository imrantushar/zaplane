<?php
// File: PaidmembershipproTest.php
namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Paidmembershippro;
use PHPUnit\Framework\TestCase;

class PaidmembershipproTest extends TestCase
{


    // ---------- TRIGGERS ----------

    public function test_admin_assigns_membership_trigger()
    {
        $args = [1, 1]; // level_id, user_id
        $node = ['event' => 'admin_assigns_membership'];

        $result = Paidmembershippro::resolve_trigger($node, $args);
        $this->assertTrue($result['success']);
        $this->assertEquals('1', $result['data']['user_id']);
        $this->assertEquals(1, $result['data']['membership_id']);
    }

    public function test_user_cancels_membership_trigger()
    {
        $args = [0, 1, 1]; // level_id=0, user_id=1, cancel_level
        $node = ['event' => 'user_cancels_membership'];

        $result = Paidmembershippro::resolve_trigger($node, $args);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['membership_id']);
    }

    public function test_user_purchases_membership_trigger()
    {
        $morder = new class {
            public function getMembershipLevel() {
                return (object)['id' => 2, 'name' => 'Level 2'];
            }
            public function getUser() {
                return (object)['ID' => 1];
            }
        };
        $args = [1, $morder];
        $node = ['event' => 'user_purchases_membership'];

        $result = Paidmembershippro::resolve_trigger($node, $args);
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['data']['membership_id']);
    }

    public function test_user_membership_expires_trigger()
    {
        $args = [1, 1]; // user_id, membership_id
        $node = ['event' => 'user_membership_expires'];

        $result = Paidmembershippro::resolve_trigger($node, $args);
        $this->assertTrue($result['success']);
    }

    public function test_membership_level_changed_trigger()
    {
        $args = [ [1 => ['ID'=>1,'name'=>'Level 1']] ]; // old levels
        $node = ['event' => 'membership_level_changed'];

        $result = Paidmembershippro::resolve_trigger($node, $args);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('old_level_data', $result['data']);
        $this->assertArrayHasKey('new_level_data', $result['data']);
    }

    public function test_user_renews_expired_membership_trigger()
    {
        $old_levels = [(object)['ID'=>1,'enddate'=>strtotime('-1 day')]];
        $args = [1, 1, $old_levels]; // level_id, user_id, old_levels
        $node = ['event' => 'user_renews_expired_membership'];

        $result = Paidmembershippro::resolve_trigger($node, $args);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['membership_id']);
    }

    // ---------- ACTIONS ----------

    public function test_get_all_membership_levels_action()
    {
        $node = ['data'=>['event'=>'get_all_membership_levels']];
        $result = Paidmembershippro::execute_node($node, []);
        $this->assertArrayHasKey('levels', $result['data']);
    }

    public function test_get_membership_level_action()
    {
        $node = ['data'=>['event'=>'get_membership_level','config'=>['level_id'=>1]]];
        $result = Paidmembershippro::execute_node($node, []);
        $this->assertArrayHasKey('level', $result['data']);
        $this->assertEquals(1, $result['data']['level']->id);
    }

    public function test_add_user_to_membership_level_action()
    {
        $node = ['data'=>['event'=>'add_user_to_membership_level','config'=>[
            'user_email'=>'exists@example.com',
            'membership_id'=>2
        ]]];
        $result = Paidmembershippro::execute_node($node, []);
        $this->assertEquals('User successfully added to the membership level.', $result['data']['message']);
    }

    public function test_remove_user_from_membership_level_action()
    {
        $node = ['data'=>['event'=>'remove_user_from_membership_level','config'=>[
            'user_email'=>'exists@example.com',
            'membership_id'=>1
        ]]];
        $result = Paidmembershippro::execute_node($node, []);
        $this->assertEquals('User successfully removed from the membership level.', $result['data']['message']);
    }

    public function test_list_members_by_membership_level_action()
    {
        $node = ['data'=>['event'=>'list_members_by_membership_level','config'=>[]]];
        $result = Paidmembershippro::execute_node($node, []);
        $this->assertArrayHasKey('members', $result['data']);
        $this->assertGreaterThanOrEqual(1, $result['data']['total']);
    }
}