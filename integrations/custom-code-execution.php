<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class CustomCodeExecution extends IntegrationBase {

	private const MIN_TIMEOUT     = 1;
	private const MAX_TIMEOUT     = 30;
	private const DEFAULT_TIMEOUT = 10;

	private const MIN_MEMORY     = 32;
	private const MAX_MEMORY     = 512;
	private const DEFAULT_MEMORY = 128;

	private const BYTES_PER_MB = 1048576;

	/**
	 * Exec-family and file primitives that are never legitimate inside a
	 * data-processing snippet. Scanned after comments/strings are stripped.
	 */
	private const PHP_BLOCKED_CALLS = '/\b(eval|assert|system|exec|passthru|shell_exec|popen|proc_open|pcntl_exec|putenv|dl|file_put_contents|file_get_contents|fopen|fwrite|unlink)\s*\(/i';

	/**
	 * include/require blocked with or without parentheses. `exit`/`die` are here
	 * too because they are constructs that would end the whole workflow request
	 * rather than just the snippet.
	 */
	private const PHP_BLOCKED_KEYWORDS = '/\b(include|include_once|require|require_once|exit|die)\b/i';

	/** The backtick shell-execution operator. */
	private const PHP_BLOCKED_BACKTICKS = '/`[^`]*`/';

	/** Node.js: exec-family calls and host reach (scanned with strings stripped). */
	private const NODE_BLOCKED_CALLS = '/\beval\s*\(|\bFunction\s*\(|\bprocess\s*\.|\bexec\s*\(|\bspawn\s*\(|\bfork\s*\(/i';

	/** Node.js: modules that reach the OS or spawn (module names live in strings, so this is scanned with comments stripped only). */
	private const NODE_BLOCKED_MODULES = '/\brequire\s*\(\s*["\'](child_process|fs|net|http|https|http2|dgram|cluster|worker_threads|vm|os|dns|tls)["\']\s*\)/i';

	/** Wall-clock budget for the where/which lookup, in seconds. */
	private const RESOLVE_TIMEOUT = 3;

	/** Poll interval for reading child-process pipes, in microseconds. */
	private const POLL_INTERVAL_USEC = 20000;

	public static function get_slug(): string {
		return 'custom-code-execution';
	}

	public static function get_name(): string {
		return 'Custom Code Execution';
	}

	public static function get_icon(): string {
		return 'code';
	}

	public static function get_category(): string {
		return 'tool';
	}

	/**
	 * A snippet is source code, so `{{ ... }}` inside it has to stay literal —
	 * otherwise the merge engine rewrites a token found in the code before the
	 * code ever runs. Snippets read upstream data from `$input` / `input`.
	 */
	public static function get_literal_config_keys(): array {
		return [ 'code' ];
	}

	/**
	 * Only the PHP action runs without an external binary, so it is the one the
	 * recipe tester can exercise on any host.
	 */
	public static function get_testable_actions(): array {
		return [ 'execute_php', 'execute_js', 'execute_nodejs' ];
	}

	public static function get_sample_action_config( string $event ): ?array {
		if ( 'execute_php' !== $event ) {
			return null;
		}

		return [ 'code' => 'return array_merge( $input, [ "ran" => true ] );' ];
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function get_actions(): array {
		return [
			'execute_php'     => [ 'label' => 'Execute PHP Code' ],
			'execute_js'      => [ 'label' => 'Execute JavaScript Code' ],
			'execute_nodejs' => [ 'label' => 'Execute Node.js Code' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'execute_php' === $action ) {
			return [
				self::code_field(
					'PHP Code',
					'return "Hello " . $input["name"];',
					'PHP code to run. The leading <?php tag is optional. Previous steps\' data is available as $input (array) and the node settings as $config (array). The return value becomes this step\'s output.'
				),
				self::timeout_field( false ),
				[
					'key'         => 'memory_limit',
					'label'       => 'Memory Limit (MB)',
					'type'        => 'number',
					'required'    => false,
					'default'     => self::DEFAULT_MEMORY,
					'help'        => 'Maximum memory in MB (default: ' . self::DEFAULT_MEMORY . ', max: ' . self::MAX_MEMORY . ').',
				],
			];
		}

		if ( in_array( $action, [ 'execute_js', 'execute_javascript', 'execute_nodejs' ], true ) ) {
			return [
				self::code_field(
					'JavaScript Code',
					'return "Hello " + input.name;',
					'JavaScript to run with the server\'s Node.js binary. Previous steps\' data is available as the input object and async/await is supported. The return value becomes this step\'s output. Node.js must be installed on the server.'
				),
				self::timeout_field( true ),
			];
		}

		return [];
	}

	private static function code_field( string $label, string $placeholder, string $help ): array {
		return [
			'key'         => 'code',
			'label'       => $label,
			'type'        => 'code',
			'required'    => true,
			'placeholder' => $placeholder,
			'help'        => $help,
		];
	}

	private static function timeout_field( bool $terminates_process ): array {
		$field = [
			'key'         => 'timeout',
			'label'       => 'Timeout (seconds)',
			'type'        => 'number',
			'required'    => false,
			'default'     => self::DEFAULT_TIMEOUT,
			'help'        => 'Maximum execution time in seconds (default: ' . self::DEFAULT_TIMEOUT . ', max: ' . self::MAX_TIMEOUT . ').',
		];

		if ( $terminates_process ) {
			$field['help'] .= ' The process is terminated when it is exceeded.';
		}

		return $field;
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		$code = $config['code'] ?? '';
		if ( ! \is_string( $code ) || '' === trim( $code ) ) {
			throw new \Exception( 'Code is required.' );
		}

		$timeout = self::clamp( (int) ( $config['timeout'] ?? self::DEFAULT_TIMEOUT ), self::MIN_TIMEOUT, self::MAX_TIMEOUT );

		switch ( $event ) {
			case 'execute_php':
				return self::execute_php_code( $code, $input, $config, $timeout );

			case 'execute_js':
			case 'execute_javascript':
			case 'execute_nodejs':
				return self::execute_javascript_code( $code, $input, $timeout );

			default:
				throw new \Exception( 'Unknown execution type: ' . $event );
		}
	}

	// =========================================================================
	// PHP EXECUTION
	// =========================================================================

	private static function execute_php_code( string $code, array $input, array $config, int $timeout ): array {
		$source = self::strip_php_open_tag( $code );

		$syntax_error = self::php_syntax_error( $source );
		if ( '' !== $syntax_error ) {
			throw new \Exception( 'PHP code has a syntax error: ' . $syntax_error );
		}

		if ( self::php_code_is_blocked( $source ) ) {
			throw new \Exception( 'PHP code contains blocked functions. File, exec and include/require calls are not available inside snippets.' );
		}

		$memory_limit = self::clamp( (int) ( $config['memory_limit'] ?? self::DEFAULT_MEMORY ), self::MIN_MEMORY, self::MAX_MEMORY );

		// Best-effort limits: max_execution_time counts CPU time, not sleep()
		// or I/O waits, so this bounds runaway computation only. Both ini
		// values are restored in the finally block so one snippet's config
		// never leaks into the rest of the request.
		$previous_time_limit = \function_exists( 'ini_get' ) ? @ini_get( 'max_execution_time' ) : false;
		if ( \function_exists( 'set_time_limit' ) ) {
			@set_time_limit( $timeout );
		}

		$previous_memory_limit = \function_exists( 'ini_get' ) ? @ini_get( 'memory_limit' ) : false;
		// Applying a cap at or below what PHP has already allocated raises an
		// uncatchable memory error mid-request, so set one only when there is
		// real headroom beneath the requested ceiling.
		$can_apply_memory = \function_exists( 'ini_set' )
			&& ( $memory_limit * self::BYTES_PER_MB ) > \memory_get_usage( true );

		if ( $can_apply_memory ) {
			@ini_set( 'memory_limit', $memory_limit . 'M' );
		}

		try {
			$result = self::run_php_closure( $source, $input, $config );
		} catch ( \ParseError $e ) {
			// The pre-check above normally catches this first.
			throw new \Exception( 'PHP code has a syntax error: ' . $e->getMessage() );
		} catch ( \Throwable $e ) {
			throw new \Exception( 'PHP execution error: ' . $e->getMessage() );
		} finally {
			if ( $can_apply_memory && false !== $previous_memory_limit ) {
				@ini_set( 'memory_limit', $previous_memory_limit );
			}
			if ( false !== $previous_time_limit && \function_exists( 'ini_set' ) ) {
				@ini_set( 'max_execution_time', $previous_time_limit );
			}
		}

		return self::success_response( $result );
	}

	/**
	 * Execute the snippet inside a generated closure file. The include()
	 * returns a Closure with no bound scope, so the snippet has no access to
	 * this class or the runner — and no eval() anywhere in the process.
	 */
	private static function run_php_closure( string $source, array $input, array $config ) {
		$runtime_file  = self::create_runtime_file( $source );
		$initial_level = \ob_get_level();

		try {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			$runner = include $runtime_file;

			if ( ! ( $runner instanceof \Closure ) ) {
				throw new \RuntimeException( 'Unable to load the runtime code.' );
			}

			// Buffer so an echo in the snippet never leaks into the response.
			\ob_start();

			return $runner( $input, $config );
		} finally {
			while ( \ob_get_level() > $initial_level ) {
				\ob_end_clean();
			}
			if ( \is_file( $runtime_file ) ) {
				@unlink( $runtime_file );
			}
		}
	}

	/**
	 * Write the snippet, wrapped in a namespaced closure, to a random file
	 * under the system temp directory. The file is deleted right after the
	 * run; the ABSPATH guard kills any direct web hit to the file.
	 */
	private static function create_runtime_file( string $source ): string {
		$dir        = self::runtime_directory();
		$runtime_id = bin2hex( random_bytes( 8 ) );
		$file_path  = $dir . '/run-' . $runtime_id . '.php';
		$namespace  = 'Zaplane\\CodeRuntime\\Run_' . $runtime_id;
		$content    = "<?php\n\nnamespace {$namespace};\n\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\nreturn static function ( array \$input, array \$config ) {\n" . $source . "\n};\n";

		// Direct write is required: the file is included locally right after,
		// so WP_Filesystem (which may be an FTP/SSH transport) is unsuitable.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $file_path, $content, LOCK_EX ) === false ) {
			throw new \RuntimeException( 'Unable to write the runtime code file.' );
		}

		return $file_path;
	}

	private static function runtime_directory(): string {
		$base = \function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$dir  = rtrim( $base, '/\\' ) . '/zaplane-code';

		if ( ! is_dir( $dir ) ) {
			// Owner-only: this directory holds executable snippets.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			@mkdir( $dir, 0700, true );
		}

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			throw new \RuntimeException( 'Unable to create a writable runtime directory for code execution.' );
		}

		return $dir;
	}

	/**
	 * Tolerate snippets pasted with a full open or close tag: the runtime
	 * closure already sits inside PHP context, and a trailing `?>` would close
	 * that context early and break the wrapper's syntax.
	 */
	private static function strip_php_open_tag( string $code ): string {
		$code = ltrim( $code );

		if ( strpos( $code, '<?php' ) === 0 ) {
			$code = ltrim( substr( $code, 5 ) );
		} elseif ( strpos( $code, '<?=' ) === 0 ) {
			$code = ltrim( substr( $code, 3 ) );
		}

		$code = rtrim( $code );

		if ( substr( $code, -2 ) === '?>' ) {
			$code = rtrim( substr( $code, 0, -2 ) );
		}

		return $code;
	}

	private static function php_code_is_blocked( string $code ): bool {
		$scannable = self::strip_php_comments_and_strings( $code );

		return (bool) preg_match( self::PHP_BLOCKED_CALLS, $scannable )
			|| (bool) preg_match( self::PHP_BLOCKED_KEYWORDS, $scannable )
			|| (bool) preg_match( self::PHP_BLOCKED_BACKTICKS, $scannable );
	}

	/**
	 * Check the snippet's grammar before it is ever included, because a parse
	 * error inside an included file surfaces as a fatal no try/catch can reach.
	 * TOKEN_PARSE (PHP 8+) walks the grammar while tokenizing and throws a
	 * ParseError for it; on PHP 7.4 this returns '' and the include itself
	 * reports the failure.
	 *
	 * @return string Error message, or '' when the code parses.
	 */
	private static function php_syntax_error( string $source ): string {
		if ( ! \defined( 'TOKEN_PARSE' ) || ! \function_exists( 'token_get_all' ) ) {
			return '';
		}

		try {
			@token_get_all( "<?php\n" . $source, TOKEN_PARSE );
		} catch ( \Throwable $e ) {
			return $e->getMessage();
		}

		return '';
	}

	/**
	 * Return a copy of the code with comment bodies and string-literal
	 * contents removed, so the guards only ever match real executable tokens
	 * — a comment mentioning system() must not trip them. Function-call
	 * syntax is preserved. Fails closed: if tokenizing fails, the raw code is
	 * scanned (over-block).
	 */
	private static function strip_php_comments_and_strings( string $code ): string {
		$tokens = @token_get_all( "<?php\n" . $code );

		if ( ! \is_array( $tokens ) ) {
			return $code;
		}

		$dropped = [
			T_COMMENT,
			T_DOC_COMMENT,
			T_CONSTANT_ENCAPSED_STRING,
			T_ENCAPSED_AND_WHITESPACE,
			T_INLINE_HTML,
			T_OPEN_TAG,
			T_CLOSE_TAG,
		];

		$scannable = '';

		foreach ( $tokens as $token ) {
			if ( \is_string( $token ) ) {
				$scannable .= $token;
				continue;
			}

			if ( \in_array( $token[0], $dropped, true ) ) {
				continue;
			}

			$scannable .= $token[1];
		}

		return $scannable;
	}

	// =========================================================================
	// JAVASCRIPT EXECUTION (NODE.JS RUNTIME)
	// =========================================================================

	private static function execute_javascript_code( string $code, array $input, int $timeout ): array {
		if ( ! \function_exists( 'proc_open' ) ) {
			throw new \RuntimeException( 'JavaScript execution is not available: proc_open() is disabled on this server.' );
		}

		if ( self::javascript_code_is_blocked( $code ) ) {
			// This scan is a deterrent, not a sandbox: a determined snippet can
			// still reach Node's module system indirectly, so the message must not
			// promise isolation the guard cannot deliver.
			throw new \Exception( 'JavaScript contains blocked patterns (process, eval, Function, or require of an OS module). Snippets are screened by text search, not sandboxed — only run code you trust.' );
		}

		$node_path = self::find_node_executable();
		if ( '' === $node_path ) {
			throw new \Exception( 'Node.js is not installed or not found on this server. Install Node.js, or point the zaplane_custom_code_node_path filter at the node binary.' );
		}

		$temp_file = self::write_node_script( $code, $input );
		if ( '' === $temp_file ) {
			throw new \RuntimeException( 'Failed to create a temporary script file.' );
		}

		try {
			$command = escapeshellarg( $node_path ) . ' ' . escapeshellarg( $temp_file );
			$run     = self::run_process( $command, $timeout );

			if ( ! $run['started'] ) {
				throw new \RuntimeException( 'Failed to start the Node.js process.' );
			}

			if ( $run['timed_out'] ) {
				throw new \Exception( 'JavaScript execution timed out after ' . $timeout . ' seconds.' );
			}

			if ( 0 !== $run['exit_code'] ) {
				$message = trim( $run['stderr'] ) !== '' ? trim( $run['stderr'] ) : 'exit code ' . $run['exit_code'];

				throw new \Exception( 'JavaScript execution failed: ' . $message );
			}

			$decoded = json_decode( trim( $run['stdout'] ), true );
			if ( null === $decoded || ! \is_array( $decoded ) || ! \array_key_exists( 'result', $decoded ) ) {
				throw new \Exception( 'Failed to parse the Node.js output: ' . trim( $run['stdout'] ) );
			}

			return self::success_response( $decoded['result'] );
		} finally {
			if ( \is_file( $temp_file ) ) {
				@unlink( $temp_file );
			}
		}
	}

	/**
	 * Build the wrapper script: input is injected as JSON (data, never code),
	 * the snippet runs inside an async IIFE so sync and async code both work,
	 * and the resolved value is printed as a JSON envelope on stdout. Errors
	 * go to stderr and set a non-zero exit code.
	 */
	private static function write_node_script( string $code, array $input ): string {
		// Use PHP's configured system temporary directory. This avoids a
		// permission fallback notice when a custom subdirectory is not writable.
		$temp_file = tempnam( sys_get_temp_dir(), 'zaplane-' );
		if ( false === $temp_file ) {
			return '';
		}

		$script = sprintf(
			"const input = %s;\n(async () => {\n%s\n})().then(\n  ( r ) => { console.log( JSON.stringify( { result: ( r === undefined ? null : r ) } ) ); },\n  ( e ) => { console.error( String( ( e && e.message ) || e ) ); process.exitCode = 1; }\n);\n",
			wp_json_encode( $input ) ?: '{}',
			$code
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $temp_file, $script, LOCK_EX ) === false ) {
			@unlink( $temp_file );

			return '';
		}

		return $temp_file;
	}

	private static function javascript_code_is_blocked( string $code ): bool {
		// Module names live inside string literals, so that check runs on the
		// comment-stripped code; the call patterns run on the fully stripped one.
		return (bool) preg_match( self::NODE_BLOCKED_CALLS, self::strip_js_comments_and_strings( $code ) )
			|| (bool) preg_match( self::NODE_BLOCKED_MODULES, self::strip_js_comments( $code ) );
	}

	/**
	 * Locate the node binary: filter override first, then the system PATH via
	 * where/which (run through proc_open, never shell_exec), then common
	 * install locations. Result is cached per request.
	 */
	private static function find_node_executable(): string {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$cached = '';

		$override = \function_exists( 'apply_filters' ) ? (string) apply_filters( 'zaplane_custom_code_node_path', '' ) : '';
		if ( '' !== $override && self::is_executable_file( $override ) ) {
			return $cached = $override;
		}

		$names = ( 'Windows' === \PHP_OS_FAMILY ) ? [ 'node', 'node.exe', 'nodejs', 'nodejs.exe' ] : [ 'node', 'nodejs' ];

		foreach ( $names as $name ) {
			$path = self::resolve_from_path( $name );
			if ( '' !== $path ) {
				return $cached = $path;
			}
		}

		$candidates = [
			'/usr/bin/node',
			'/usr/bin/nodejs',
			'/usr/local/bin/node',
			'/opt/homebrew/bin/node',
			'C:\\Program Files\\nodejs\\node.exe',
			'C:\\Program Files (x86)\\nodejs\\node.exe',
		];

		foreach ( $candidates as $candidate ) {
			if ( self::is_executable_file( $candidate ) ) {
				return $cached = $candidate;
			}
		}

		return $cached;
	}

	private static function is_executable_file( string $path ): bool {
		return '' !== $path && is_file( $path ) && ( \is_executable( $path ) || '.exe' === strtolower( substr( $path, -4 ) ) );
	}

	private static function resolve_from_path( string $binary ): string {
		$finder = ( 'Windows' === \PHP_OS_FAMILY ) ? 'where' : 'which';
		$run    = self::run_process( $finder . ' ' . escapeshellarg( $binary ), self::RESOLVE_TIMEOUT );

		if ( ! $run['started'] || $run['timed_out'] || 0 !== $run['exit_code'] ) {
			return '';
		}

		$lines = preg_split( '/\r\n|\r|\n/', trim( $run['stdout'] ) );
		$line  = trim( (string) ( $lines[0] ?? '' ) );

		return self::is_executable_file( $line ) ? $line : '';
	}

	/**
	 * Run a command with a hard wall-clock deadline, reading its pipes
	 * through a non-blocking poll loop.
	 *
	 * Deliberately avoids stream_select(): PHP documents that function as
	 * unreliable on Windows for anything other than network sockets, which
	 * makes it unsafe for proc_open() pipes on a Windows dev box even though
	 * it happens to work on Linux. Polling with proc_get_status() works
	 * identically on both platforms.
	 *
	 * @return array{started: bool, timed_out: bool, exit_code: int, stdout: string, stderr: string}
	 */
	private static function run_process( string $command, int $timeout ): array {
		$result = [
			'started'   => false,
			'timed_out' => false,
			'exit_code' => -1,
			'stdout'    => '',
			'stderr'    => '',
		];

		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- runs the admin-configured node/where/which binary only
		$process = proc_open( $command, $descriptors, $pipes );

		if ( ! \is_resource( $process ) ) {
			return $result;
		}

		$result['started'] = true;

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$deadline = microtime( true ) + $timeout;

		while ( true ) {
			$result['stdout'] .= (string) stream_get_contents( $pipes[1] );
			$result['stderr'] .= (string) stream_get_contents( $pipes[2] );

			$status = proc_get_status( $process );

			if ( ! $status['running'] ) {
				// Accurate only on this first post-exit read — capture now.
				$result['exit_code'] = $status['exitcode'];
				break;
			}

			if ( microtime( true ) >= $deadline ) {
				$result['timed_out'] = true;
				proc_terminate( $process );
				break;
			}

			usleep( self::POLL_INTERVAL_USEC );
		}

		// Drain whatever the child wrote right before it exited.
		$result['stdout'] .= (string) stream_get_contents( $pipes[1] );
		$result['stderr'] .= (string) stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );

		return $result;
	}

	/**
	 * Return a copy of the code with comments and string/template literals
	 * removed, so the guards only match real code. Template literals are
	 * stripped too — in JS, backticks are strings, not a shell operator.
	 */
	private static function strip_js_comments_and_strings( string $code ): string {
		$pattern = <<<'REGEX'
			#//[^\n]*|/\*.*?\*/|"(?:\\.|[^"\\\n])*"|'(?:\\.|[^'\\\n])*'|`(?:\\.|[^`\\])*`#s
			REGEX;

		return (string) preg_replace( $pattern, '', $code );
	}

	/** Comments only — string contents stay available for the guards that inspect them. */
	private static function strip_js_comments( string $code ): string {
		$pattern = <<<'REGEX'
			#//[^\n]*|/\*.*?\*/#s
			REGEX;

		return (string) preg_replace( $pattern, '', $code );
	}

	// =========================================================================
	// RESPONSE HELPERS
	// =========================================================================

	private static function clamp( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}

	private static function success_response( $data ): array {
		return [
			'port' => 'main',
			'data' => [
				'result' => $data,
				'output' => is_scalar( $data ) ? (string) $data : wp_json_encode( $data ),
			],
		];
	}

	public static function get_action_sample_output( string $action ): array {
		return [
			'result' => [ 'message' => 'Hello World' ],
			'output' => '{"message":"Hello World"}',
		];
	}
}
