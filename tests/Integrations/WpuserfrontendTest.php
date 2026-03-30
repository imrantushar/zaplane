<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wpuserfrontend;

class WpuserfrontendTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Wpuserfrontend::class;
    }

    public function test_post_form_submission_trigger(): void
    {
        $node = $this->makeTriggerNode('post_form_submission');
        $result = Wpuserfrontend::resolve_trigger($node, [101, 20, ['setting' => 'value'], ['meta1' => 'val1']]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(101, $result['post_id']);
        $this->assertEquals('Test Post 101', $result['post_data']->post_title);
        $this->assertEquals('John Doe', $result['user_data']['user']['data']['display_name']);
    }

    public function test_registration_form_submission_trigger(): void
    {
        $node = $this->makeTriggerNode('registration_form_submission');
        $result = Wpuserfrontend::resolve_trigger($node, [1, 5, ['setting' => 'value']]);

        $this->assertTrue($result['success']);
        $this->assertEquals('John Doe', $result['user_data']['display_name']);
    }

    public function test_profile_edit_form_submission_trigger(): void
    {
        $node = $this->makeTriggerNode('profile_edit_form_submission');
        $result = Wpuserfrontend::resolve_trigger($node, [1, 5, ['setting' => 'value'], ['meta' => 'data']]);

        $this->assertTrue($result['success']);
        $this->assertEquals(['meta' => 'data'], $result['meta_data']);
    }

    public function test_metadata_update_profile_edit_form_submission_trigger(): void
    {
        $node = $this->makeTriggerNode('metadata_update_profile_edit_form_submission');
        $post_data = ['pass1'=>'123','custom'=>'val'];
        $result = Wpuserfrontend::resolve_trigger($node, [1, $post_data]);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('pass1', $result['post_data']);
        $this->assertEquals('val', $result['post_data']['custom']);
    }

    public function test_subscription_pack_update_trigger(): void
    {
        $node = $this->makeTriggerNode('subscription_pack_update');
        $request = ['subscription'=>['meta_value'=>['pack'=>'gold']]];
        $result = Wpuserfrontend::resolve_trigger($node, [101, $request]);

        $this->assertTrue($result['success']);
        $this->assertEquals('gold', $result['subscription']['meta_value']['pack']);
    }

    public function test_created_coupon_trigger(): void
    {
        $node = $this->makeTriggerNode('created_coupon');
        $result = Wpuserfrontend::resolve_trigger($node, [200]);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['post_data']['ID']);
    }

    public function test_updated_coupon_trigger(): void
    {
        $node = $this->makeTriggerNode('updated_coupon');
        $result = Wpuserfrontend::resolve_trigger($node, [200]);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['post_data']['ID']);
    }
}