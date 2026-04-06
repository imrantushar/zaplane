<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Paidmembershippro;
use Zaplane\Tests\Mocks\WPDBMock;

class PaidmembershipproTest extends IntegrationTestCase
{
    public function getIntegrationClass(): string {
        return Paidmembershippro::class;
    }

    protected function setUp(): void {
        parent::setUp();

        global $wpdb;
        $wpdb = new WPDBMock();
    }

    public function test_user_purchases_membership_returns_array() {

        $mockOrder = new class {
            public function getMembershipLevel() {
                return (object) [ 'id' => 1, 'name' => 'Gold' ];
            }
        };

        $result = Paidmembershippro::resolve_trigger([
            'event' => 'user_purchases_membership'
        ], [1, $mockOrder]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
    }


    public function test_admin_assign_membership_returns_array() {

        $result = Paidmembershippro::resolve_trigger([
            'event' => 'admin_assigns_membership'
        ], [1, 1]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }


    public function test_user_cancel_membership_returns_array() {

        $result = Paidmembershippro::resolve_trigger([
            'event' => 'user_cancels_membership'
        ], [0, 1, 1]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }


    public function test_invalid_purchase_returns_false() {

        $result = Paidmembershippro::resolve_trigger([
            'event' => 'user_purchases_membership'
        ], [0, null]);

        $this->assertFalse($result);
    }

}