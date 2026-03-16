<?php
use Zaplane\Integrations\Gravityforms;

class GravityFormsTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_form_query_types() {
        $results = Gravityforms::form_query_types([]);
        $this->assertContains( ['name' => 'any', 'label' => 'Any Form'], $results );
        $this->assertContains( ['name' => 10,    'label' => 'Test Form 1'], $results );
        $this->assertContains( ['name' => 11,    'label' => 'Test Form 2'], $results );
    }

    public function test_resolve_trigger_form_submitted() {
        $entry = ['id'=>5,'1'=>'John','2'=>'Doe'];
        $form  = ['id'=>10,'title'=>'Test Form 1'];
        $node  = ['event'=>'form_submitted','form_id'=>10];

        $result = Gravityforms::resolve_trigger( $node, [ $entry, $form ] );

        $this->assertTrue( $result['success'] );
        $this->assertEquals( 10,$result['form_id'] );
        $this->assertEquals( 5,$result['entry_id'] );
        $this->assertEquals( 'Test Form 1',$result['data']['title'] );
        $this->assertEquals( 'John',$result['data']['1'] );
    }

    public function test_resolve_trigger_any_form() {
        $entry = ['id'=>6,'1'=>'Alice'];
        $form  = ['id'=>99,'title'=>'Random Form'];
        $node  = ['event'=>'form_submitted','form_id'=>'any'];

        $result = Gravityforms::resolve_trigger( $node, [ $entry, $form ] );

        $this->assertTrue( $result['success'] );
        $this->assertEquals( 99,$result['form_id'] );
        $this->assertEquals( 6,$result['entry_id'] );
        $this->assertEquals( 'Random Form',$result['data']['title'] );
        $this->assertEquals( 'Alice',$result['data']['1'] );
    }

    public function test_resolve_trigger_non_matching_form() {
        $entry = ['id'=>7];
        $form  = ['id'=>50,'title'=>'Form 50'];
        $node  = ['event'=>'form_submitted','form_id'=>99];

        $result = Gravityforms::resolve_trigger( $node, [ $entry, $form ] );

        $this->assertFalse( $result );
    }
}