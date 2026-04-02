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
}
