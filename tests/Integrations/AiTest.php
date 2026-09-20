<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Ai;

/**
 * The model field is now a connection-aware dynamic select, and provider
 * matching is case-insensitive so the WordPress Core AI provider routes.
 */
class AiTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Ai::class;
	}

	protected function getActionTests(): array {
		return [
			'generate_response' => [ 'user_message' => 'hello' ],
		];
	}

	/** The model field pulls its options dynamically, gated on the chosen connection. */
	public function test_model_field_is_dynamic_and_depends_on_connection() {
		$schema = Ai::get_action_config_schema( 'generate_response' );
		$model  = $schema[0];

		$this->assertSame( 'model', $model['key'] );
		$this->assertArrayNotHasKey( 'options', $model );
		$this->assertSame( 'ai_models', $model['dynamic']['query'] );
		$this->assertSame( [ 'connection_id' ], $model['dynamic']['depends_on'] );
	}

	/** The dynamic query is registered under the name the field references. */
	public function test_ai_models_query_is_registered() {
		$this->assertArrayHasKey( 'ai_models', Ai::get_dynamic_queries() );
	}

	/** With no connection chosen the list is empty — nothing to confuse the user. */
	public function test_no_connection_yields_no_models() {
		$this->assertSame( [], Ai::query_models( [] ) );
	}

	/** Each provider surfaces only its own models. */
	public function test_models_are_scoped_per_provider() {
		$method = new \ReflectionMethod( Ai::class, 'models_for_provider' );
		$method->setAccessible( true );

		$openai = array_column( $method->invoke( null, 'openai' ), 'value' );
		$claude = array_column( $method->invoke( null, 'anthropic' ), 'value' );

		$this->assertSame( [ 'gpt-4o', 'gpt-4o-mini' ], $openai );
		$this->assertContains( 'claude-opus-4-8', $claude );
		$this->assertNotContains( 'gpt-4o', $claude );
	}

	/** A lowercase "wordpress" provider must route to WordPress Core AI, not the no-key error. */
	public function test_lowercase_wordpress_provider_routes_to_core_ai() {
		$node = [
			'_connection_credentials' => [ 'provider' => 'wordpress', 'api_key' => '' ],
			'data'                    => [ 'config' => [ 'user_message' => 'hi' ] ],
		];

		$out = Ai::execute_node( $node, [] );

		$this->assertFalse( $out['data']['success'] );
		// It reached the WordPress branch (Core AI unavailable in tests) rather than
		// bailing out with "No AI connection credentials available."
		$this->assertStringContainsString( 'WordPress Core AI', $out['data']['error'] );
	}
}
