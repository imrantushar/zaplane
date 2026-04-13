<?php

namespace Zaplane\Integrations\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Zaplane\Integrations\Sureform;

/**
 * @covers \Zaplane\Integrations\Sureform
 */
class SureformTest extends MockeryTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_correct_slug()
    {
        $this->assertSame('sureform', Sureform::get_slug());
    }

    /** @test */
    public function it_returns_correct_name()
    {
        $this->assertSame('Sure Form', Sureform::get_name());
    }

    /** @test */
    public function it_returns_correct_icon()
    {
        $this->assertSame('sureform.svg', Sureform::get_icon());
    }

    /** @test */
    public function it_returns_triggers()
    {
        $expected = [
            'submit_form' => [
                'label' => 'Form Submit',
                'hook'  => 'srfm_form_submit',
            ],
        ];
        $this->assertSame($expected, Sureform::get_triggers());
    }

    /** @test */
    public function it_returns_trigger_config_schema_for_submit_form()
    {
        $schema = Sureform::get_trigger_config_schema('submit_form');
        $this->assertIsArray($schema);
        $this->assertCount(1, $schema);
        $this->assertArrayHasKey('key', $schema[0]);
        $this->assertSame('form_id', $schema[0]['key']);
    }

    /** @test */
    public function it_returns_empty_trigger_config_schema_for_unknown_trigger()
    {
        $this->assertSame([], Sureform::get_trigger_config_schema('unknown_trigger'));
    }

    /** @test */
    public function resolve_trigger_returns_null_for_wrong_event()
    {
        $node = ['event' => 'other_event'];
        $args = [];

        $this->assertNull(Sureform::resolve_trigger($node, $args));
    }

    /** @test */
    public function resolve_trigger_returns_null_when_args_are_invalid()
    {
        $node = ['event' => 'submit_form'];

        // Empty args
        $this->assertNull(Sureform::resolve_trigger($node, []));
        // First arg not an array
        $this->assertNull(Sureform::resolve_trigger($node, ['string']));
        // Missing form_id
        $this->assertNull(Sureform::resolve_trigger($node, [['data' => ['foo' => 'bar']]]));
        // Empty form_data
        $this->assertNull(Sureform::resolve_trigger($node, [['form_id' => 5, 'data' => []]]));
    }

    /** @test */
    public function resolve_trigger_returns_null_when_configured_form_id_mismatch()
    {
        $node = [
            'event'  => 'submit_form',
            'config' => ['form_id' => 42],
        ];
        $payload = [
            'form_id' => 99,
            'data'    => ['name' => 'John'],
        ];
        $args = [$payload];

        $this->assertNull(Sureform::resolve_trigger($node, $args));
    }

    /** @test */
    public function resolve_trigger_returns_expected_payload_on_success()
    {
        $node = [
            'event'  => 'submit_form',
            'config' => ['form_id' => 'any'], // or specific ID that matches
        ];
        $payload = [
            'success'    => true,
            'form_id'    => 123,
            'form_name'  => 'Contact Form',
            'data'       => ['email' => 'test@example.com', 'name' => 'John'],
            'message'    => 'Thank you!',
            'to_emails'  => ['admin@example.com'],
        ];
        $args = [$payload];

        $result = Sureform::resolve_trigger($node, $args);

        $expected = [
            'form_id'   => 123,
            'form_name' => 'Contact Form',
            'form_data' => ['email' => 'test@example.com', 'name' => 'John'],
            'message'   => 'Thank you!',
            'to_emails' => ['admin@example.com'],
            'success'   => true,
        ];
        $this->assertSame($expected, $result);
    }

    /** @test */
    public function get_actions_returns_empty_array()
    {
        $this->assertSame([], Sureform::get_actions());
    }

    /** @test */
    public function get_action_config_schema_returns_empty_array_for_any_action()
    {
        $this->assertSame([], Sureform::get_action_config_schema('any_action'));
    }

    /** @test */
    public function execute_node_passes_through_input_with_main_port()
    {
        $input = ['some' => 'data'];
        $node = []; // not used in this implementation

        $result = Sureform::execute_node($node, $input);

        $expected = [
            'port' => 'main',
            'data' => $input,
        ];
        $this->assertSame($expected, $result);
    }

    /** @test */
    public function get_dynamic_queries_returns_forms_query_callback()
    {
        $queries = Sureform::get_dynamic_queries();
        $this->assertArrayHasKey('forms', $queries);
        $this->assertIsCallable($queries['forms']);
        // Optionally check that the callback is the expected static method
        $this->assertSame([Sureform::class, 'query_forms'], $queries['forms']);
    }

    /** @test */
    public function query_forms_returns_empty_array_when_plugin_not_active()
    {
        // Mock is_plugin_active to return false
        $mock = Mockery::mock('alias:is_plugin_active');
        $mock->shouldReceive('is_plugin_active')
            ->with('sureforms/sureforms.php')
            ->once()
            ->andReturn(false);

        $result = Sureform::query_forms();
        $this->assertSame([], $result);
    }

    /** @test */
    public function query_forms_returns_only_any_form_when_no_forms_exist()
    {
        // Mock is_plugin_active to return true
        $isActiveMock = Mockery::mock('alias:is_plugin_active');
        $isActiveMock->shouldReceive('is_plugin_active')
            ->with('sureforms/sureforms.php')
            ->andReturn(true);

        // Mock get_posts to return empty array
        $getPostsMock = Mockery::mock('alias:get_posts');
        $getPostsMock->shouldReceive('get_posts')
            ->with(Mockery::on(function ($args) {
                return $args['post_type'] === 'sureforms_form';
            }))
            ->once()
            ->andReturn([]);

        $result = Sureform::query_forms();

        $expected = [['label' => 'Any form', 'value' => 'any']];
        $this->assertSame($expected, $result);
    }

    /** @test */
    public function query_forms_returns_forms_list_including_any_form()
    {
        // Mock is_plugin_active
        $isActiveMock = Mockery::mock('alias:is_plugin_active');
        $isActiveMock->shouldReceive('is_plugin_active')
            ->with('sureforms/sureforms.php')
            ->andReturn(true);

        // Mock get_posts to return two fake form posts
        $form1 = (object) ['ID' => 10, 'post_title' => 'Newsletter'];
        $form2 = (object) ['ID' => 20, 'post_title' => 'Contact'];

        $getPostsMock = Mockery::mock('alias:get_posts');
        $getPostsMock->shouldReceive('get_posts')
            ->once()
            ->andReturn([$form1, $form2]);

        $result = Sureform::query_forms();

        $expected = [
            ['label' => 'Any form', 'value' => 'any'],
            ['label' => 'Newsletter', 'value' => 10],
            ['label' => 'Contact', 'value' => 20],
        ];
        $this->assertSame($expected, $result);
    }

    /** @test */
    public function get_output_ports_returns_main_port()
    {
        $expected = ['main' => 'Main output'];
        $this->assertSame($expected, Sureform::get_output_ports());
    }
}
