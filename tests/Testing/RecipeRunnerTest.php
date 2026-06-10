<?php

namespace Zaplane\Tests\Testing;

use PHPUnit\Framework\TestCase;
use Zaplane\Testing\RecipeRunner;
use Zaplane\Testing\RecipeResult;

/**
 * Exposes RecipeRunner's protected logic (interpolation + assertions) for
 * direct unit testing. The live activate-plugin + fire path is covered by the
 * `wp zaplane recipe run` CLI verification, not the mock suite.
 */
class TestableRecipeRunner extends RecipeRunner {

	public static function pub_interpolate( $value, array $vars ) {
		return self::interpolate( $value, $vars );
	}

	public static function pub_assert( RecipeResult $result, string $kind, array $expect ): void {
		self::assert_expectations( $result, $kind, $expect );
	}
}

/** A stand-in integration that self-seeds one trigger, for resolve_trigger_input tests. */
class SeedingStubIntegration {

	public static function seed_trigger_args( string $event ): ?array {
		return 'thing_created' === $event ? [ 42, 'obj' ] : null;
	}

	public static function get_sample_action_config( string $event ): ?array {
		return 'create_thing' === $event ? [ 'name' => 'X' ] : null;
	}
}

class RecipeRunnerTest extends TestCase {

	/** @test */
	public function whole_token_preserves_native_type(): void {
		$out = TestableRecipeRunner::pub_interpolate( '{{order_id}}', [ 'order_id' => 42 ] );
		$this->assertSame( 42, $out );
	}

	/** @test */
	public function inline_token_is_substituted_as_string(): void {
		$out = TestableRecipeRunner::pub_interpolate( 'Order #{{order_id}}', [ 'order_id' => 42 ] );
		$this->assertSame( 'Order #42', $out );
	}

	/** @test */
	public function interpolation_recurses_into_arrays(): void {
		$out = TestableRecipeRunner::pub_interpolate(
			[ 'input' => [ '{{post_id}}' ], 'config' => [ 'label' => 'p{{post_id}}' ] ],
			[ 'post_id' => 7 ]
		);
		$this->assertSame( [ 'input' => [ 7 ], 'config' => [ 'label' => 'p7' ] ], $out );
	}

	/** @test */
	public function unknown_token_is_left_intact(): void {
		$out = TestableRecipeRunner::pub_interpolate( '{{missing}}', [ 'order_id' => 1 ] );
		$this->assertSame( '{{missing}}', $out );
	}

	/** @test */
	public function dot_path_resolves_nested_vars(): void {
		$vars = [ 'order' => [ 'order_id' => 99, 'status' => 'processing' ] ];
		$this->assertSame( 99, TestableRecipeRunner::pub_interpolate( '{{order.order_id}}', $vars ) );
		$this->assertSame( 'x-processing', TestableRecipeRunner::pub_interpolate( 'x-{{order.status}}', $vars ) );
	}

	/** @test */
	public function not_false_fails_when_trigger_filtered_out(): void {
		$result = new RecipeResult( 'r', 'wordpress' );
		$result->output = false;
		TestableRecipeRunner::pub_assert( $result, 'trigger', [ 'not_false' => true ] );
		$this->assertNotEmpty( $result->failures );
	}

	/** @test */
	public function trigger_has_keys_and_partial_data_pass(): void {
		$result = new RecipeResult( 'r', 'wordpress' );
		$result->output = [ 'ID' => 5, 'post_title' => 'Hi', 'post_status' => 'publish' ];
		TestableRecipeRunner::pub_assert(
			$result,
			'trigger',
			[ 'not_false' => true, 'has_keys' => [ 'ID', 'post_status' ], 'data' => [ 'post_status' => 'publish' ] ]
		);
		$this->assertSame( [], $result->failures );
	}

	/** @test */
	public function missing_key_is_reported(): void {
		$result = new RecipeResult( 'r', 'wordpress' );
		$result->output = [ 'ID' => 5 ];
		TestableRecipeRunner::pub_assert( $result, 'trigger', [ 'has_keys' => [ 'post_status' ] ] );
		$this->assertCount( 1, $result->failures );
	}

	/** @test */
	public function action_port_and_data_keys_are_checked(): void {
		$result = new RecipeResult( 'r', 'wordpress' );
		$result->output = [ 'port' => 'main', 'data' => [ 'post_id' => 99 ] ];
		TestableRecipeRunner::pub_assert(
			$result,
			'action',
			[ 'port' => 'main', 'has_keys' => [ 'post_id' ] ]
		);
		$this->assertSame( [], $result->failures );
	}

	/** @test */
	public function action_wrong_port_fails(): void {
		$result = new RecipeResult( 'r', 'wordpress' );
		$result->output = [ 'port' => 'error', 'data' => [] ];
		TestableRecipeRunner::pub_assert( $result, 'action', [ 'port' => 'main' ] );
		$this->assertNotEmpty( $result->failures );
	}

	/** @test */
	public function scalar_values_compare_loosely_for_json(): void {
		$result = new RecipeResult( 'r', 'woocommerce' );
		$result->output = [ 'order_id' => 7, 'total' => '50.00' ];
		// Recipe JSON numbers vs WC string totals should still match.
		TestableRecipeRunner::pub_assert( $result, 'trigger', [ 'data' => [ 'total' => 50 ] ] );
		$this->assertSame( [], $result->failures );
	}

	/** @test */
	public function resolve_trigger_input_falls_back_to_integration_self_seed(): void {
		$cls = SeedingStubIntegration::class;

		// Explicit input wins.
		$this->assertSame( [ 5 ], RecipeRunner::resolve_trigger_input( [], $cls, 'thing_created', [ 5 ] ) );

		// Empty input + no factory/action → integration self-seeds.
		$this->assertSame( [ 42, 'obj' ], RecipeRunner::resolve_trigger_input( [], $cls, 'thing_created', [] ) );

		// Empty input but a factory is declared → don't override (respect author's choice).
		$recipe = [ 'setup' => [ 'factory' => 'create_x' ] ];
		$this->assertSame( [], RecipeRunner::resolve_trigger_input( $recipe, $cls, 'thing_created', [] ) );

		// Unseedable event → unchanged.
		$this->assertSame( [], RecipeRunner::resolve_trigger_input( [], $cls, 'other', [] ) );
	}

	/** @test */
	public function resolve_action_config_falls_back_to_integration_sample(): void {
		$cls = SeedingStubIntegration::class;

		// Explicit config wins.
		$this->assertSame( [ 'a' => 1 ], RecipeRunner::resolve_action_config( [], $cls, 'create_thing', [ 'a' => 1 ] ) );

		// Empty config + no factory/action → integration sample config.
		$this->assertSame( [ 'name' => 'X' ], RecipeRunner::resolve_action_config( [], $cls, 'create_thing', [] ) );

		// Non-testable action → unchanged.
		$this->assertSame( [], RecipeRunner::resolve_action_config( [], $cls, 'other', [] ) );
	}

	/** @test */
	public function load_file_parses_and_defaults_name(): void {
		$path = sys_get_temp_dir() . '/zaplane-recipe-' . md5( uniqid( 'r', true ) ) . '.json';
		file_put_contents( $path, '{"integration":"wordpress","node":{"kind":"trigger","event":"publish_post"}}' );

		$recipe = RecipeRunner::load_file( $path );
		unlink( $path );

		$this->assertSame( 'wordpress', $recipe['integration'] );
		$this->assertNotEmpty( $recipe['name'] ); // defaulted from filename.
	}

	/** @test */
	public function unfilled_scaffold_is_detected(): void {
		$this->assertTrue( RecipeRunner::is_unfilled( [ 'node' => [ 'input' => [ '{{arg0}}', '{{arg1}}' ] ] ] ) );
		$this->assertTrue( RecipeRunner::is_unfilled( [ 'node' => [ 'config' => [ 'x' => '{{arg2}}' ] ] ] ) );
	}

	/** @test */
	public function filled_recipe_is_not_flagged_unfilled(): void {
		$this->assertFalse( RecipeRunner::is_unfilled( [ 'node' => [ 'input' => [ '{{order_id}}', 5 ] ] ] ) );
		$this->assertFalse( RecipeRunner::is_unfilled( [ 'node' => [ 'input' => [] ] ] ) );
	}

	/** @test */
	public function recipe_with_a_factory_is_not_prefiltered_unfilled(): void {
		// Has {{argN}} placeholders but a factory is wired → let it run (the stub
		// can SKIP itself with a specific message) instead of generic pre-skip.
		$recipe = [ 'setup' => [ 'factory' => 'create_x' ], 'node' => [ 'input' => [ '{{arg0}}' ] ] ];
		$this->assertFalse( RecipeRunner::is_unfilled( $recipe ) );
	}

	/** @test */
	public function trigger_with_empty_input_and_no_seed_is_unfilled(): void {
		$this->assertTrue( RecipeRunner::is_unfilled( [ 'node' => [ 'kind' => 'trigger', 'input' => [] ] ] ) );
		// ...but not if it's seeded by an action.
		$this->assertFalse( RecipeRunner::is_unfilled(
			[ 'setup' => [ 'action' => [ 'app' => 'woocommerce', 'event' => 'create_order' ] ], 'node' => [ 'kind' => 'trigger', 'input' => [ '{{order.order_id}}' ] ] ]
		) );
		// Actions use config, not input — empty input is fine for them.
		$this->assertFalse( RecipeRunner::is_unfilled( [ 'node' => [ 'kind' => 'action', 'input' => [] ] ] ) );
	}

	/** @test */
	public function skip_state_overrides_pass_in_finalize(): void {
		$result = new \Zaplane\Testing\RecipeResult( 'r', 'x' );
		$result->skip( 'stub' )->finalize();
		$this->assertTrue( $result->skipped );
		$this->assertFalse( $result->passed );
		$this->assertSame( 'SKIP', $result->status_label() );
	}

	/** @test */
	public function load_file_rejects_invalid_json(): void {
		$path = sys_get_temp_dir() . '/zaplane-bad-' . md5( uniqid( 'r', true ) ) . '.json';
		file_put_contents( $path, '{ not json' );

		$this->expectException( \RuntimeException::class );
		try {
			RecipeRunner::load_file( $path );
		} finally {
			unlink( $path );
		}
	}
}
