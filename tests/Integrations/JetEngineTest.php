<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Jetengine;

class JetEngineTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Jetengine::class;
    }

    public function test_post_type_field_update_trigger()
    {
        $node = [
            'event' => 'post_type_field_update',
            'data'  => [
                'config' => [
                    'post_type' => 'post'
                ]
            ]
        ];

        $args = [
            1,
            123,
            'custom_field',
            'Test Value'
        ];

        $result = Jetengine::resolve_trigger( $node, $args );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 123, $result['data']['ID'] );
        $this->assertEquals( 'Test Value', $result['data']['meta_value'] );
    }

    public function test_edit_lock_skipped()
    {
        $node = [
            'event' => 'post_type_field_update',
            'data'  => [
                'config' => [
                    'post_type' => 'post'
                ]
            ]
        ];

        $args = [
            1,
            123,
            '_edit_last',
            'something'
        ];

        $result = Jetengine::resolve_trigger( $node, $args );

        $this->assertFalse( $result );
    }
}