<?php
namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Metabox;

class MetaboxTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string {
        return Metabox::class;
    }

    public function test_form_submission_trigger() {

		$object = new \stdClass();
		$object->post_id = 123;
		$object->config  = [
			'id' => 'form_1',
		];

		$node = [
			'event'  => 'form_submission',
			'config' => [
				'form_id' => 'form_1',
			],
		];

		$result = Metabox::resolve_trigger( $node, [ $object ] );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'form_1', $result['data']['id'] );
		$this->assertEquals( 'John Doe', $result['data']['your_name'] );
		$this->assertEquals( 123, $result['data']['post_id'] );
	}
}