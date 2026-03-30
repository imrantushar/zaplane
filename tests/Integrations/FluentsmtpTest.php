<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Fluentsmtp;

class FluentsmtpTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Fluentsmtp::class;
    }

    public function test_email_sent_success(): void
    {
        $node = $this->makeTriggerNode('email_sent_success');

        $email_data = [
            'to'      => 'john@example.com',
            'subject' => 'Test Email',
            'body'    => 'This is a test email.',
        ];

        $result = Fluentsmtp::resolve_trigger( $node, [ $email_data ] );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( $email_data, $result['data'] );
    }

    public function test_email_sent_failed(): void
    {
        $node = $this->makeTriggerNode('email_sent_failed');

        $log_id  = 123;
        $handler = new class { public $name = 'SMTPHandler'; };
        $data    = [
            'to'       => 'jane@example.com',
            'subject'  => 'Fail Email',
            'body'     => 'This email failed.',
            'response' => [
                'code'    => 500,
                'message' => 'SMTP Error: Could not connect',
            ],
        ];

        $result = Fluentsmtp::resolve_trigger( $node, [ null, $log_id, $handler, $data ] );

        $this->assertIsArray( $result );
        $this->assertFalse( $result['success'] );
        $this->assertEquals( $log_id, $result['log_id'] );
        $this->assertEquals( get_class($handler), $result['handler'] );
        $this->assertEquals( $data, $result['data'] );
        $this->assertEquals( 'SMTP Error: Could not connect', $result['error'] );
    }
}