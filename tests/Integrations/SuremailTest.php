<?php

namespace Zaplane\Integrations\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Zaplane\Integrations\Suremail;

/**
 * @covers \Zaplane\Integrations\Suremail
 */
class SuremailTest extends MockeryTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_correct_slug()
    {
        $this->assertSame('suremail', Suremail::get_slug());
    }

    /** @test */
    public function it_returns_correct_name()
    {
        $this->assertSame('SureMail', Suremail::get_name());
    }

    /** @test */
    public function it_returns_correct_icon()
    {
        $this->assertSame('suremail.svg', Suremail::get_icon());
    }

    /** @test */
    public function it_returns_both_triggers()
    {
        $expected = [
            'email_sent_successfully' => [
                'label' => 'Email Sent Successfully',
                'hook'  => 'wp_mail_succeeded',
            ],
            'email_sent_failed' => [
                'label' => 'Email Sent Failed',
                'hook'  => 'wp_mail_failed',
            ],
        ];
        $this->assertSame($expected, Suremail::get_triggers());
    }

    /** @test */
    public function get_trigger_config_schema_returns_empty_array_for_any_trigger()
    {
        $this->assertSame([], Suremail::get_trigger_config_schema('email_sent_successfully'));
        $this->assertSame([], Suremail::get_trigger_config_schema('email_sent_failed'));
        $this->assertSame([], Suremail::get_trigger_config_schema('non_existent_trigger'));
    }

    /** @test */
    public function resolve_trigger_returns_null_for_unknown_event()
    {
        $node = ['event' => 'unknown_event'];
        $args = [];

        $this->assertNull(Suremail::resolve_trigger($node, $args));
    }

    /** @test */
    public function resolve_trigger_returns_null_when_args_are_invalid()
    {
        $node = ['event' => 'email_sent_successfully'];

        // Empty args
        $this->assertNull(Suremail::resolve_trigger($node, []));

        // First arg not an array
        $this->assertNull(Suremail::resolve_trigger($node, ['not_an_array']));

        // Empty mail data array
        $this->assertNull(Suremail::resolve_trigger($node, [[]]));
    }

    /** @test */
    public function resolve_trigger_returns_expected_payload_for_successful_email_event()
    {
        $node = ['event' => 'email_sent_successfully'];

        $mailData = [
            'to'          => 'recipient@example.com',
            'subject'     => 'Test Email Subject',
            'message'     => 'This is the email body content',
            'headers'     => ['Content-Type: text/html'],
            'attachments' => ['/path/to/file.pdf'],
        ];

        $args = [$mailData];

        $result = Suremail::resolve_trigger($node, $args);

        $expected = [
            'mail_data'   => $mailData,
            'event'       => 'email_sent_successfully',
            'to'          => 'recipient@example.com',
            'subject'     => 'Test Email Subject',
            'message'     => 'This is the email body content',
            'headers'     => ['Content-Type: text/html'],
            'attachments' => ['/path/to/file.pdf'],
        ];

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function resolve_trigger_returns_expected_payload_for_failed_email_event()
    {
        $node = ['event' => 'email_sent_failed'];

        $mailData = [
            'to'      => 'failed@example.com',
            'subject' => 'Failed Email',
            'message' => 'This email failed to send',
            'headers' => [],
        ];

        $args = [$mailData];

        $result = Suremail::resolve_trigger($node, $args);

        $expected = [
            'mail_data'   => $mailData,
            'event'       => 'email_sent_failed',
            'to'          => 'failed@example.com',
            'subject'     => 'Failed Email',
            'message'     => 'This email failed to send',
            'headers'     => [],
            'attachments' => [],
        ];

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function resolve_trigger_handles_missing_mail_fields_gracefully()
    {
        $node = ['event' => 'email_sent_successfully'];

        // Mail data with missing optional fields
        $mailData = [
            'to' => 'partial@example.com',
            // subject, message, headers, attachments are missing
        ];

        $args = [$mailData];

        $result = Suremail::resolve_trigger($node, $args);

        $expected = [
            'mail_data'   => $mailData,
            'event'       => 'email_sent_successfully',
            'to'          => 'partial@example.com',
            'subject'     => '',
            'message'     => '',
            'headers'     => [],
            'attachments' => [],
        ];

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function resolve_trigger_handles_to_field_as_array()
    {
        $node = ['event' => 'email_sent_successfully'];

        $mailData = [
            'to'      => ['recipient1@example.com', 'recipient2@example.com'],
            'subject' => 'Multiple Recipients',
            'message' => 'Email content',
        ];

        $args = [$mailData];

        $result = Suremail::resolve_trigger($node, $args);

        $this->assertIsArray($result);
        $this->assertSame(['recipient1@example.com', 'recipient2@example.com'], $result['to']);
        $this->assertSame('Multiple Recipients', $result['subject']);
    }

    /** @test */
    public function get_actions_returns_empty_array()
    {
        $this->assertSame([], Suremail::get_actions());
    }

    /** @test */
    public function get_action_config_schema_returns_empty_array_for_any_action()
    {
        $this->assertSame([], Suremail::get_action_config_schema('any_action'));
        $this->assertSame([], Suremail::get_action_config_schema(''));
    }

    /** @test */
    public function execute_node_passes_through_input_with_main_port()
    {
        $input = ['some_key' => 'some_value', 'another' => 'data'];
        $node = ['some' => 'config']; // Node config is not used in this implementation

        $result = Suremail::execute_node($node, $input);

        $expected = [
            'port' => 'main',
            'data' => $input,
        ];

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function execute_node_preserves_input_types()
    {
        $input = [
            'string' => 'value',
            'integer' => 42,
            'boolean' => true,
            'array' => [1, 2, 3],
            'null' => null,
        ];

        $result = Suremail::execute_node([], $input);

        $this->assertSame($input, $result['data']);
        $this->assertSame('main', $result['port']);
    }

    /** @test */
    public function get_dynamic_queries_returns_empty_array()
    {
        $this->assertSame([], Suremail::get_dynamic_queries());
    }

    /** @test */
    public function get_output_ports_returns_main_port()
    {
        $expected = ['main' => 'Main output'];
        $this->assertSame($expected, Suremail::get_output_ports());
    }

    /** @test */
    public function it_handles_multiple_consecutive_calls()
    {
        // Test that the integration can be called multiple times
        $node = ['event' => 'email_sent_successfully'];

        $mailData1 = ['to' => 'first@example.com', 'subject' => 'First Email'];
        $mailData2 = ['to' => 'second@example.com', 'subject' => 'Second Email'];

        $result1 = Suremail::resolve_trigger($node, [$mailData1]);
        $result2 = Suremail::resolve_trigger($node, [$mailData2]);

        $this->assertSame('first@example.com', $result1['to']);
        $this->assertSame('second@example.com', $result2['to']);
    }

    /** @test */
    public function resolve_trigger_preserves_additional_mail_data_fields()
    {
        $node = ['event' => 'email_sent_successfully'];

        $mailData = [
            'to'      => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Content',
            'custom_field' => 'custom_value',
            'another_field' => 12345,
        ];

        $args = [$mailData];

        $result = Suremail::resolve_trigger($node, $args);

        // The full mail_data should contain all original fields
        $this->assertArrayHasKey('custom_field', $result['mail_data']);
        $this->assertSame('custom_value', $result['mail_data']['custom_field']);
        $this->assertArrayHasKey('another_field', $result['mail_data']);
        $this->assertSame(12345, $result['mail_data']['another_field']);
    }
}
