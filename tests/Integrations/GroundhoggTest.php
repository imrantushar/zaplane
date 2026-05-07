<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Groundhogg;

class GroundhoggTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Groundhogg::class;
    }

    public function test_tag_triggers(): void
    {
        foreach ( ['added_tag', 'removed_tag'] as $event ) {
            $node = $this->makeTriggerNode( $event, [
                'config' => [
                    'tag_id' => 1
                ]
            ] );

            $contact = new \Groundhogg\Contact(101);
            $tag_id  = 1;
            $result  = Groundhogg::resolve_trigger( $node, [ $contact, $tag_id ] );

            $this->assertIsArray( $result );
            $this->assertTrue( $result['success'] );
            $this->assertEquals( $contact->get_id(), $result['contact']['id'] );
            $this->assertEquals( $tag_id, $result['object_id'] );
            $this->assertEquals( 'Test Tag', $result['tag']['name'] );
        }
    }
}
