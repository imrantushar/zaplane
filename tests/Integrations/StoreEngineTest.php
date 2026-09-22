<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Storeengine;

class StoreEngineTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Storeengine::class;
    }

    public function test_product_purchased(): void
    {
        $node  = $this->makeTriggerNode('product_purchased');
        $order = new \Storeengine_Order(101, [
            'status' => 'completed',
            'items' => [
                ['product_id' => 1, 'name' => 'Product A', 'quantity' => 2, 'total' => 100],
                ['product_id' => 2, 'name' => 'Product B', 'quantity' => 1, 'total' => 50],
            ],
            'total' => 150,
            'currency' => 'USD',
            'payment_method' => 'paypal',
            'customer_email' => 'john@example.com',
            'customer_name' => 'John Doe',
        ]);

        $result = Storeengine::resolve_trigger( $node, [ $order->get_id() ] );

        $this->assertIsArray( $result );
        $this->assertEquals( 101, $result['order_id'] );
        $this->assertEquals( 'completed', $result['order_status'] );
        $this->assertCount( 2, $result['items'] );
    }

    public function test_order_status_triggers(): void
    {
        $statuses = [
            'order_status_update',
            'order_status_on_hold',
            'order_status_pending_payment',
            'order_status_processing',
            'order_status_completed',
            'order_status_cancelled',
            'order_status_draft',
            'order_status_trash',
        ];

        foreach ( $statuses as $status ) {
            $node       = $this->makeTriggerNode( $status );
            $order      = new \Storeengine_Order(102, ['status' => 'processing'] );
            $transition = ['from' => 'pending', 'to' => 'processing'];
            $result     = Storeengine::resolve_trigger( $node, [ null, $order, $transition ] );

            $this->assertIsArray( $result );
            $this->assertEquals( 'pending', $result['old_status'] );
            $this->assertEquals( 'processing', $result['new_status'] );
        }
    }

    public function test_order_restored(): void
    {
        $node   = $this->makeTriggerNode('order_restored');
        $order  = new \Storeengine_Order(103, ['status' => 'draft']);
        $result = Storeengine::resolve_trigger( $node, [ $order->get_id(), 'trash', 'draft' ] );

        $this->assertIsArray( $result );
        $this->assertEquals( 'trash', $result['old_status'] );
        $this->assertEquals( 'draft', $result['restored_status'] );
    }

    public function test_payment_refunded(): void
    {
        $node   = $this->makeTriggerNode('payment_refunded');
        $order  = new \Storeengine_Order(104);
        $result = Storeengine::resolve_trigger( $node, [ $order->get_id(), 50, 'Customer requested refund' ] );

        $this->assertIsArray( $result );
        $this->assertEquals( 50, $result['refunded_amount'] );
        $this->assertEquals( 'Customer requested refund', $result['refunded_reason'] );
    }

    public function test_order_customer_note_added(): void
    {
        $node   = $this->makeTriggerNode('order_customer_note_added');
        $order  = new \Storeengine_Order(105);
        $note   = 'This is a customer note';
        $result = Storeengine::resolve_trigger( $node, [ $note, $order ] );

        $this->assertIsArray( $result );
        $this->assertEquals( $note, $result['note'] );
    }

    public function test_order_customer_note_deleted(): void
    {
        $node     = $this->makeTriggerNode('order_customer_note_deleted');
        $order    = new \Storeengine_Order(106);
        $note_obj = (object) ['order_id' => $order->get_id(), 'content' => 'Old note content'];
        $result   = Storeengine::resolve_trigger( $node, [ 999, $note_obj ] );

        $this->assertIsArray( $result);
        $this->assertEquals( 999, $result['deleted_note_id'] );
        $this->assertEquals( 'Old note content', $result['deleted_note'] );
    }

    public function test_order_status_update_names_both_statuses(): void
    {
        $node = $this->makeTriggerNode('order_status_update');
        new \Storeengine_Order(302, ['customer_email' => 'john@example.com', 'customer_name' => 'John Doe']);

        $result = Storeengine::resolve_trigger( $node, [ 302, 'on_hold', 'processing', null ] );

        $this->assertSame( 'on_hold', $result['old_status'] );
        $this->assertNotSame( '', $result['old_status_label'] );
        $this->assertNotSame( '', $result['new_status_label'] );
        $this->assertFalse( $result['during_checkout'] );
        $this->assertSame( 'John', $result['first_name'] );
    }

    public function test_order_item_shipped_carries_the_shipment(): void
    {
        $node = $this->makeTriggerNode('order_item_shipped');
        new \Storeengine_Order(301, [
            'customer_email' => 'john@example.com',
            'customer_name' => 'John Doe',
            'total' => 20,
            'currency' => 'USD',
            'items' => [
                ['product_id' => 7, 'name' => 'Mug', 'quantity' => 2, 'total' => 20],
            ],
        ]);

        $shipment = ['courier' => 'Express Courier', 'tracking_number' => 'EC1', 'tracking_url' => 'https://example.com/track/EC1'];
        $result   = Storeengine::resolve_trigger( $node, [ 301, 44, 0, $shipment, 'on_the_way' ] );

        $this->assertSame( 'on_the_way', $result['shipment_status'] );
        $this->assertNotSame( '', $result['shipment_status_label'] );
        $this->assertSame( 'Express Courier', $result['courier'] );
        $this->assertSame( 'EC1', $result['tracking_number'] );
        $this->assertSame( 'https://example.com/track/EC1', $result['tracking_url'] );
        $this->assertSame( 'Mug × 2', $result['items_summary'] );
        $this->assertStringContainsString( '20', $result['total_formatted'] );
    }

    public function test_subscription_renewal_payment_failed_links_to_the_renewal_payment(): void
    {
        $node = $this->makeTriggerNode('subscription_renewal_payment_failed');
        $subscription = new class {
            public function get_id() { return 9; }
            public function get_status() { return 'on_hold'; }
            public function get_total() { return '49.00'; }
            public function get_currency() { return 'USD'; }
            public function get_billing_email() { return 'john@example.com'; }
            public function get_billing_first_name() { return 'John'; }
            public function get_billing_last_name() { return 'Doe'; }
        };
        $renewal = new class {
            public function get_id() { return 120; }
            public function get_checkout_payment_url() { return 'https://example.com/checkout/order-pay/120/?pay_for_order=true&key=abc'; }
        };

        $result = Storeengine::resolve_trigger( $node, [ $subscription, $renewal ] );

        $this->assertSame( 9, $result['subscription_id'] );
        $this->assertSame( 120, $result['renewal_order_id'] );
        $this->assertSame( 'https://example.com/checkout/order-pay/120/?pay_for_order=true&key=abc', $result['payment_url'] );
        $this->assertSame( 'John', $result['first_name'] );
        $this->assertSame( 'John Doe', $result['customer_name'] );
        $this->assertSame( 'john@example.com', $result['customer_email'] );
    }
}
