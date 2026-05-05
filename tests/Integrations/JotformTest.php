<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Jotform;
use WP_REST_Request;

class JotformTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Jotform::class;
    }

    public function test_requires_connection()
    {
        $this->assertTrue(Jotform::requires_connection());
    }

    public function test_auth_type()
    {
        $this->assertEquals('token_key', Jotform::get_auth_type());
    }

    public function test_get_auth_fields()
    {
        $fields = Jotform::get_auth_fields();

        $this->assertArrayHasKey('api_key', $fields);
        $this->assertTrue($fields['api_key']['required']);
    }

    public function test_get_triggers()
    {
        $triggers = Jotform::get_triggers();

        $this->assertArrayHasKey('form_submitted', $triggers);
    }

    public function test_get_actions()
    {
        $actions = Jotform::get_actions();

        $this->assertArrayHasKey('create_form', $actions);
        $this->assertArrayHasKey('add_questions_to_form', $actions);
        $this->assertArrayHasKey('delete_form', $actions);
    }

    public function test_get_webhook_url()
    {
        $url = Jotform::get_webhook_url();

        $this->assertStringContainsString(
            '/wp-json/zaplane/v1/incoming/jotform',
            $url
        );
    }

    public function test_parse_webhook_event_valid()
    {
        $request = $this->mockRequest([
            'formID' => 'form_123',
            'formTitle' => 'Test Form',
            'submissionID' => 'sub_1',
            'answers' => []
        ]);

        $result = Jotform::parse_webhook_event($request);

        $this->assertNotNull($result);
        $this->assertEquals('form_submitted', $result['event']);
    }

    public function test_parse_webhook_event_invalid()
    {
        $request = $this->mockRequest([]);

        $result = Jotform::parse_webhook_event($request);

        $this->assertNull($result);
    }

    public function test_resolve_trigger_matches_form()
    {
        $node = [
            'event' => 'form_submitted',
            'data'  => [
                'config' => [
                    'form_id' => 'abc123'
                ]
            ]
        ];

        $payload = [
            'formID' => 'abc123',
            'formTitle' => 'Demo Form',
            'submissionID' => 'sub123',
            'answers' => [
                [
                    'name' => 'q1',
                    'answer' => 'John'
                ]
            ]
        ];

        $result = Jotform::resolve_trigger($node, [$payload]);

        $this->assertNotFalse($result);
        $this->assertEquals('abc123', $result['jotform_form_id']);
        $this->assertEquals('John', $result['jotform_answers']['q1']);
    }

    public function test_resolve_trigger_wrong_form()
    {
        $node = [
            'event' => 'form_submitted',
            'data'  => [
                'config' => [
                    'form_id' => 'abc123'
                ]
            ]
        ];

        $payload = [
            'formID' => 'wrong_id'
        ];

        $result = Jotform::resolve_trigger($node, [$payload]);

        $this->assertFalse($result);
    }

    public function test_execute_node_missing_api_key()
    {
        $node = [
            'data' => [
                'event' => 'create_form',
                'config' => [
                    'form_title' => 'Test Form'
                ],
                'connection_id' => 0
            ]
        ];

        $result = Jotform::execute_node($node, []);

        $this->assertEquals('error', $result['port']);
    }

    public function test_parse_answers_private_method()
    {
        $answers = [
            [
                'name' => 'q1',
                'answer' => 'John'
            ],
            [
                'name' => 'q2',
                'prettyFormat' => 'Yes'
            ]
        ];

        $parsed = $this->invokePrivateMethod('parse_answers', [$answers]);

        $this->assertEquals('John', $parsed['q1']);
        $this->assertEquals('Yes', $parsed['q2']);
    }

    private function mockRequest(array $data): WP_REST_Request
    {
        $request = new \WP_REST_Request(
            'POST',
            '/zaplane/v1/webhook/jotform'
        );

        $request->set_header('content-type', 'application/json');
        $request->set_json_params($data);

        return $request;
    }

    private function invokePrivateMethod(string $method, array $args = [])
    {
        $reflection = new \ReflectionClass(Jotform::class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $args);
    }

    protected function mockWpRemoteGetSuccess(array $body = [])
    {
        return [
            'response' => [
                'code' => 200
            ],
            'body' => json_encode($body)
        ];
    }

    protected function mockWpRemoteGetError()
    {
        return new \WP_Error('api_error', 'Request failed');
    }
}