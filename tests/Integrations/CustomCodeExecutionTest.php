<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\CustomCodeExecution;
use Zaplane\Tests\TestCase;

class CustomCodeExecutionTest extends TestCase {

	private static function node( string $event, array $config ): array {
		return [
			'data' => [
				'event'  => $event,
				'config' => $config,
			],
		];
	}

	/**
	 * Run a PHP snippet and return the node's `data` payload.
	 *
	 * @return array{result: mixed, output: string}
	 */
	private function php( string $code, array $input = [], array $config = [] ): array {
		$run = CustomCodeExecution::execute_node(
			self::node( 'execute_php', array_merge( [ 'code' => $code ], $config ) ),
			$input
		);

		$this->assertSame( 'main', $run['port'] );

		return $run['data'];
	}

	/**
	 * Run a JavaScript snippet, or skip the test when this host has no node.
	 *
	 * @return array{result: mixed, output: string}
	 */
	private function js( string $code, array $input = [], int $timeout = 10 ): array {
		try {
			$run = CustomCodeExecution::execute_node(
				self::node( 'execute_javascript', [ 'code' => $code, 'timeout' => $timeout ] ),
				$input
			);
		} catch ( \Exception $e ) {
			if ( false !== strpos( $e->getMessage(), 'Node.js is not installed' ) ) {
				$this->markTestSkipped( 'Node.js is not available on this host.' );
			}

			throw $e;
		}

		$this->assertSame( 'main', $run['port'] );

		return $run['data'];
	}

	// =========================================================================
	// REGISTRY CONTRACT
	// =========================================================================

	public function test_tool_metadata(): void {
		$this->assertSame( 'custom-code-execution', CustomCodeExecution::get_slug() );
		$this->assertSame( 'tool', CustomCodeExecution::get_category() );
		$this->assertSame( [], CustomCodeExecution::get_triggers() );
		// `code` is a built-in icon-font glyph; an unknown value here renders a
		// broken <img> in the builder.
		$this->assertSame( 'code', CustomCodeExecution::get_icon() );
	}

	public function test_actions_are_php_and_javascript(): void {
		$this->assertSame(
			[ 'execute_php', 'execute_javascript' ],
			array_keys( CustomCodeExecution::get_actions() )
		);
	}

	/**
	 * Every action needs a required `code` field of the `code` type, or the
	 * drawer renders nothing for it and blocks Continue on an invisible field.
	 */
	public function test_every_action_declares_a_required_code_field(): void {
		foreach ( array_keys( CustomCodeExecution::get_actions() ) as $action ) {
			$code = null;

			foreach ( CustomCodeExecution::get_action_config_schema( $action ) as $field ) {
				if ( 'code' === $field['key'] ) {
					$code = $field;
				}
			}

			$this->assertNotNull( $code, "{$action} has no code field." );
			$this->assertSame( 'code', $code['type'], "{$action} code field is not a code editor." );
			$this->assertTrue( $code['required'], "{$action} code field is not required." );
			$this->assertNotEmpty( $code['help'], "{$action} code field has no help text." );
			$this->assertNotEmpty( $code['placeholder'], "{$action} code field has no placeholder." );
		}
	}

	public function test_unknown_action_has_no_schema(): void {
		$this->assertSame( [], CustomCodeExecution::get_action_config_schema( 'execute_ruby' ) );
	}

	/** Code must reach execute_node() verbatim, so {{ }} resolution skips it. */
	public function test_code_key_is_declared_literal(): void {
		$this->assertSame( [ 'code' ], CustomCodeExecution::get_literal_config_keys() );
	}

	public function test_only_the_php_action_is_recipe_testable(): void {
		$this->assertSame( [ 'execute_php' ], CustomCodeExecution::get_testable_actions() );
		$this->assertIsArray( CustomCodeExecution::get_sample_action_config( 'execute_php' ) );
		$this->assertNull( CustomCodeExecution::get_sample_action_config( 'execute_javascript' ) );
	}

	// =========================================================================
	// PHP EXECUTION
	// =========================================================================

	public function test_php_return_value_becomes_the_result(): void {
		$data = $this->php( 'return $input["name"] . "!";', [ 'name' => 'Rofiqul' ] );

		$this->assertSame( 'Rofiqul!', $data['result'] );
		$this->assertSame( 'Rofiqul!', $data['output'] );
	}

	public function test_php_arrays_become_a_result_array_and_json_output(): void {
		$data = $this->php( 'return [ "a" => 1, "b" => 2 ];' );

		$this->assertSame( [ 'a' => 1, 'b' => 2 ], $data['result'] );
		$this->assertSame( '{"a":1,"b":2}', $data['output'] );
	}

	public function test_php_receives_node_config(): void {
		$data = $this->php( 'return $config["label"] ?? "missing";', [], [ 'label' => 'from config' ] );

		$this->assertSame( 'from config', $data['result'] );
	}

	/** Snippets are pasted with a tag half the time, so both ends are tolerated. */
	public function test_php_open_and_close_tags_are_stripped(): void {
		$this->assertSame( 'hi', $this->php( "<?php\n\nreturn 'hi';" )['result'] );
		$this->assertSame( 'hi', $this->php( "return 'hi';\n?>" )['result'] );
		$this->assertSame( 'hi', $this->php( "<?php return 'hi'; ?>" )['result'] );
	}

	public function test_php_output_buffer_never_leaks(): void {
		$before = ob_get_level();

		$data = $this->php( 'echo "noise"; return "value";' );

		$this->assertSame( 'value', $data['result'] );
		$this->assertSame( $before, ob_get_level(), 'The snippet buffer was not cleaned up.' );
		$this->assertStringNotContainsString( 'noise', $data['output'] );
	}

	public function test_php_throwable_inside_snippet_becomes_an_error(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/PHP execution error.*Division by zero/i' );

		$this->php( 'return 1 / 0;' );
	}

	/**
	 * A parse error inside an included file surfaces as a fatal no try/catch can
	 * reach, so the snippet is syntax-checked before the runtime file is loaded.
	 */
	public function test_php_syntax_error_is_reported_not_fatal(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/syntax error/i' );

		$this->php( '$a = ;' );
	}

	public function test_rejected_snippet_leaves_no_runtime_file(): void {
		$dir     = rtrim( sys_get_temp_dir(), '/\\' ) . '/zaplane-code';
		$before  = (array) glob( $dir . '/run-*.php' );

		try {
			$this->php( 'function {' );
			$this->fail( 'Expected a syntax error.' );
		} catch ( \Exception $e ) {
			$this->assertStringContainsString( 'syntax error', $e->getMessage() );
		}

		// The syntax check runs before any file is written, so nothing is added.
		$after = (array) glob( $dir . '/run-*.php' );
		$this->assertCount( count( $before ), $after );
	}

	public function test_empty_code_is_rejected(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Code is required.' );

		CustomCodeExecution::execute_node( self::node( 'execute_php', [ 'code' => "   \n " ] ), [] );
	}

	public function test_missing_code_is_rejected(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Code is required.' );

		CustomCodeExecution::execute_node( self::node( 'execute_php', [] ), [] );
	}

	public function test_unknown_event_is_rejected(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Unknown execution type: execute_ruby' );

		CustomCodeExecution::execute_node( self::node( 'execute_ruby', [ 'code' => 'return 1;' ] ), [] );
	}

	/**
	 * A failure must abort the step: the engine only marks a node run `failed`
	 * when execute_node() throws, so a returned error payload would flow
	 * downstream as if the step had succeeded.
	 */
	public function test_failures_throw_instead_of_returning_an_error_payload(): void {
		$thrown = false;

		try {
			$this->php( 'return undefined_function_call();' );
		} catch ( \Exception $e ) {
			$thrown = true;
		}

		$this->assertTrue( $thrown, 'A failed snippet returned instead of throwing.' );
	}

	/**
	 * A cap at or below what PHP has already allocated would raise an
	 * uncatchable memory error, so the runner skips it — either way the snippet
	 * still has to run.
	 */
	public function test_lowest_memory_limit_still_runs_the_snippet(): void {
		$this->assertSame( 'ran', $this->php( 'return "ran";', [], [ 'memory_limit' => 32 ] )['result'] );
	}

	// =========================================================================
	// PHP BLOCKLIST
	// =========================================================================

	/**
	 * @dataProvider blockedPhpCode
	 */
	public function test_blocked_php_calls_are_rejected( string $code ): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/blocked functions/i' );

		$this->php( $code );
	}

	public static function blockedPhpCode(): array {
		return [
			'eval'              => [ 'eval( "return 1;" );' ],
			'system'            => [ 'system( "ls" );' ],
			'shell_exec'        => [ 'shell_exec( "id" );' ],
			'proc_open'         => [ 'proc_open( "ls", [], $p );' ],
			'assert'            => [ 'assert( "phpinfo();" );' ],
			'file_get_contents' => [ 'return file_get_contents( "wp-config.php" );' ],
			'file_put_contents' => [ 'return file_put_contents( "x.txt", "x" );' ],
			'fopen'             => [ 'return fopen( "x.txt", "w" );' ],
			'unlink'            => [ 'unlink( "x.txt" );' ],
			'include'           => [ 'include "wp-config.php";' ],
			'include_once'      => [ 'return include_once "x.php";' ],
			'require'           => [ 'require "x.php";' ],
			'backticks'         => [ 'return `ls`;' ],
			// exit/die would end the whole workflow request, not just the snippet.
			'exit'              => [ 'exit;' ],
			'die'               => [ 'die( "done" );' ],
		];
	}

	/** The guards read real tokens, so prose about a blocked call stays legal. */
	public function test_blocked_names_in_comments_and_strings_are_allowed(): void {
		$this->assertSame(
			'ok',
			$this->php( "// never call system() or eval() here\n/* include is banned too */\nreturn 'ok';" )['result']
		);
	}

	public function test_blocked_patterns_respect_word_boundaries(): void {
		$this->assertSame( 4, $this->php( '$died = 2 + 2; return $died;' )['result'] );
		$this->assertSame( 9, $this->php( 'return strlen( "including" );' )['result'] );
	}

	// =========================================================================
	// JAVASCRIPT EXECUTION
	// =========================================================================

	public function test_javascript_return_value_becomes_the_result(): void {
		$data = $this->js( 'return "Hello " + input.name;', [ 'name' => 'World' ] );

		$this->assertSame( 'Hello World', $data['result'] );
	}

	public function test_javascript_receives_upstream_data_as_json(): void {
		$data = $this->js( 'return input.items.length + input.total;', [ 'items' => [ 1, 2, 3 ], 'total' => 10 ] );

		$this->assertSame( 13, $data['result'] );
	}

	public function test_javascript_supports_async_await(): void {
		$data = $this->js( 'const v = await Promise.resolve( 21 ); return v * 2;' );

		$this->assertSame( 42, $data['result'] );
	}

	/** A snippet with no return yields null rather than a parse failure. */
	public function test_javascript_without_return_is_null_result(): void {
		$data = $this->js( 'const unused = 1;' );

		$this->assertNull( $data['result'] );
	}

	public function test_javascript_returns_objects_intact(): void {
		$data = $this->js( 'return { ok: true, n: input.n, tags: [ "a", "b" ] };', [ 'n' => 7 ] );

		$this->assertSame(
			[ 'ok' => true, 'n' => 7, 'tags' => [ 'a', 'b' ] ],
			$data['result']
		);
	}

	public function test_javascript_error_becomes_a_failed_step(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/JavaScript execution failed.*is not defined/i' );

		$this->js( 'return nope();' );
	}

	public function test_javascript_syntax_error_becomes_a_failed_step(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/JavaScript execution failed/i' );

		$this->js( 'const ;' );
	}

	public function test_javascript_timeout_terminates_the_process(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/timed out after 1 seconds/i' );

		$this->js( 'while ( true ) {}', [], 1 );
	}

	/**
	 * @dataProvider blockedJavascriptCode
	 */
	public function test_blocked_javascript_is_rejected( string $code ): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/blocked patterns/i' );

		$this->js( $code );
	}

	public static function blockedJavascriptCode(): array {
		return [
			'process'       => [ 'return process.version;' ],
			'eval'          => [ 'return eval( "1 + 1" );' ],
			'Function'      => [ 'return Function( "return 1" )();' ],
			'require fs'    => [ 'return require( "fs" ).readFileSync( "/etc/passwd" );' ],
			'require child' => [ 'return require( "child_process" ).execSync( "id" );' ],
			'require net'   => [ 'return require( "net" );' ],
			'require vm'    => [ 'return require( "vm" );' ],
		];
	}

	/** A comment naming a banned module must not trip the guard. */
	public function test_blocked_names_in_javascript_comments_are_allowed(): void {
		$data = $this->js( "// do not use require(\"fs\") or process here\nreturn 'ok';" );

		$this->assertSame( 'ok', $data['result'] );
	}

	public function test_sample_output_documents_the_result_shape(): void {
		$sample = CustomCodeExecution::get_action_sample_output( 'execute_php' );

		$this->assertArrayHasKey( 'result', $sample );
		$this->assertArrayHasKey( 'output', $sample );
	}
}
