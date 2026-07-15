<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Aiagent;

/**
 * The AI Agent gained a Response Format control (Text | JSON) that parses the
 * model's JSON reply into a `json` output field.
 */
class AiagentTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Aiagent::class;
	}

	protected function getActionTests(): array {
		// No connection credentials in a bare invocation → returns the graceful
		// "no credentials" error, still a valid port/data shape.
		return [
			'run_agent' => [ 'task' => 'hello' ],
		];
	}

	/** The response_format select offers Text and JSON. */
	public function test_response_format_field_exists() {
		$schema = Aiagent::get_action_config_schema( 'run_agent' );
		$field  = null;
		foreach ( $schema as $f ) {
			if ( 'response_format' === $f['key'] ) {
				$field = $f;
			}
		}

		$this->assertNotNull( $field );
		$this->assertSame( [ 'text', 'json' ], array_column( $field['options'], 'value' ) );
	}

	/** A JSON reply (optionally fenced) decodes; prose decodes to null. */
	public function test_decode_json_reply() {
		$method = new \ReflectionMethod( Aiagent::class, 'decode_json_reply' );
		$method->setAccessible( true );

		$this->assertSame( [ 'a' => 1 ], $method->invoke( null, '{"a":1}' ) );
		$this->assertSame( [ 'ok' => true ], $method->invoke( null, "```json\n{\"ok\":true}\n```" ) );
		$this->assertNull( $method->invoke( null, 'just some prose' ) );
	}
}
