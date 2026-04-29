<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Typeform;
use WP_REST_Request;

class TypeformTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Typeform::class;
    }

    public function test_requires_connection() {
        $this->assertTrue( Typeform::requires_connection() );
    }

    public function test_auth_type() {
        $this->assertEquals( 'token_key', Typeform::get_auth_type() );
    }

    public function test_get_auth_fields() {
        $fields = Typeform::get_auth_fields();

        $this->assertArrayHasKey( 'access_token', $fields );
        $this->assertTrue( $fields['access_token']['required'] );
    }

    public function test_get_webhook_url() {
        $url = Typeform::get_webhook_url();

        $this->assertStringContainsString(
            '/wp-json/zaplane/v1/incoming/typeform',
            $url
        );
    }

    public function test_parse_webhook_event_valid() {

        $request = $this->mockRequest([
            'form_response' => [
                'form_id' => 'abc123',
                'token'   => 'token123',
                'answers' => [],
            ]
        ]);

        $result = Typeform::parse_webhook_event( $request );

        $this->assertNotNull( $result );
        $this->assertEquals( 'form_submitted', $result['event'] );
    }

    public function test_parse_webhook_event_invalid() {

        $request = $this->mockRequest([]);

        $result = Typeform::parse_webhook_event( $request );

        $this->assertNull( $result );
    }

    public function test_resolve_trigger_matches_form() {

        $node = [
            'event' => 'form_submitted',
            'data'  => [
                'config' => [
                    'form_id' => 'abc123'
                ]
            ]
        ];

        $payload = [
            'form_response' => [
                'form_id' => 'abc123',
                'token' => 'xyz',
                'answers' => [],
                'definition' => ['title' => 'Test Form']
            ]
        ];

        $result = Typeform::resolve_trigger( $node, [ $payload ] );

        $this->assertNotFalse( $result );
        $this->assertEquals( 'abc123', $result['typeform_form_id'] );
    }

    public function test_resolve_trigger_wrong_form() {

        $node = [
            'event' => 'form_submitted',
            'data'  => [
                'config' => [
                    'form_id' => 'abc123'
                ]
            ]
        ];

        $payload = [
            'form_response' => [
                'form_id' => 'wrong_id',
            ]
        ];

        $result = Typeform::resolve_trigger( $node, [ $payload ] );

        $this->assertFalse( $result );
    }

    public function test_parse_answers() {

        $answers = [
            [
                'type' => 'text',
                'text' => 'John',
                'field' => ['id' => 'q1']
            ],
            [
                'type' => 'choice',
                'choice' => ['label' => 'Yes'],
                'field' => ['id' => 'q2']
            ]
        ];

        $parsed = $this->invokePrivateMethod('parse_answers', [$answers]);

        $this->assertEquals( 'John', $parsed['q1'] );
        $this->assertEquals( 'Yes', $parsed['q2'] );
    }

    public function test_get_triggers() {
        $triggers = Typeform::get_triggers();

        $this->assertArrayHasKey( 'form_submitted', $triggers );
    }

    public function test_get_actions() {
        $actions = Typeform::get_actions();

        $this->assertArrayHasKey( 'create_form', $actions );
        $this->assertArrayHasKey( 'delete_form', $actions );
    }

    public function test_execute_node_missing_token() {

        $node = [
            'data' => [
                'event' => 'create_form',
                'config' => [
                    'form_title' => 'Test'
                ]
            ]
        ];

        $result = Typeform::execute_node( $node, [] );

        $this->assertEquals( 'error', $result['port'] );
    }

    private function mockRequest( $data ) {
        $request = new \WP_REST_Request('POST', '/zaplane/v1/webhook/typeform');

        $request->set_body( json_encode( $data ) );

        $request->set_header( 'content-type', 'application/json' );

        return $request;
    }

    private function invokePrivateMethod($method, $args = []) {
        $reflection = new \ReflectionClass(Typeform::class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }
}