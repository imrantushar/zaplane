<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Formidable;

class FormidableTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Formidable::class;
    }

    public function test_form_submitted(): void
    {
        $node = $this->makeTriggerNode('form_submitted', [
            'form_id' => 1,
        ] );
        $form   = new \FrmForm(1, 'Contact Form');
        $entry  = ['entry_id' => 101];
        $result = Formidable::resolve_trigger(
            $node,
            [ null, $form, null, null, $entry ]
        );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 1, $result['form_id'] );
        $this->assertEquals( 101, $result['entry_id'] );
        $this->assertArrayHasKey( 'name_1_first', $result['data'] );
        $this->assertEquals( 'John', $result['data']['name_1_first'] );
    }
}