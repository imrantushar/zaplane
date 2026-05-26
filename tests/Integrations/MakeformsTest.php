<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Makeforms;
use WP_REST_Request;

class MakeformsTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Makeforms::class;
    }

    public function test_webhook_event_with_form_response()
    {
        $request = new WP_REST_Request('POST', '/webhook');

        $request->set_header('Content-Type', 'application/json');

        $request->set_body(json_encode([
            'form_response' => [
                'form_id' => '123',
                'token'   => 'abc',
            ]
        ]));

        $result = Makeforms::parse_webhook_event($request);

        $this->assertNotNull($result);
        $this->assertEquals('form_submitted', $result['event']);
        $this->assertArrayHasKey('payload', $result);
    }

    public function test_webhook_event_with_event_type()
    {
        $request = new WP_REST_Request('POST', '/webhook');

        $request->set_header('Content-Type', 'application/json');

        $request->set_body(json_encode([
            'event_type' => 'submit',
            'data' => [
                'form_id' => '999'
            ]
        ]));

        $result = Makeforms::parse_webhook_event($request);

        $this->assertNotNull($result);
        $this->assertEquals('form_submitted', $result['event']);
        $this->assertArrayHasKey('payload', $result);
    }

    public function test_resolve_trigger_success()
    {
        $node = [
            'event' => 'form_submitted',
        ];

        $args = [[
            'form_response' => [
                'form_id' => '777',
                'token'   => 'xyz',
                'answers' => [
                    [
                        'type' => 'choice',
                        'choice' => [
                            'label' => 'Yes'
                        ],
                        'field' => [
                            'ref' => 'agree'
                        ]
                    ]
                ]
            ]
        ]];

        $result = Makeforms::resolve_trigger($node, $args);

        $this->assertIsArray($result);
        $this->assertEquals('777', $result['makeforms_form_id']);
        $this->assertEquals('xyz', $result['makeforms_entry_token']);
        $this->assertEquals('Yes', $result['makeforms_answers']['agree']);
    }

    public function test_resolve_trigger_fail()
    {
        $node = [
            'event' => 'form_submitted',
        ];

        $args = [[
            'form_response' => []
        ]];

        $result = Makeforms::resolve_trigger($node, $args);

        $this->assertFalse($result);
    }
}