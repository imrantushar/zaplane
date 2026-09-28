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

	/**
	 * Exec-family and file primitives that are never legitimate inside a
	 * data-processing snippet. Scanned after comments/strings are stripped.
	 */
	private const PHP_BLOCKED_CALLS = '/\b(eval|assert|system|exec|passthru|shell_exec|popen|proc_open|pcntl_exec|putenv|dl|file_put_contents|file_get_contents|fopen|fwrite|unlink)\s*\(/i';

	/** include/require blocked with or without parentheses. */
	private const PHP_BLOCKED_KEYWORDS = '/\b(include|include_once|require|require_once)\b/i';

	/** The backtick shell-execution operator. */
	private const PHP_BLOCKED_BACKTICKS = '/`[^`]*`/';

	/** JS (V8Js) has no host APIs, so any host/eval reach is blocked. */
	private const JS_BLOCKED_PATTERN = self::NODE_BLOCKED_CALLS;

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
		return 'code-execution-icon.svg';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function get_actions(): array {
		return [
			'execute_php'    => [ 'label' => 'Execute PHP Code' ],
			'execute_js'     => [ 'label' => 'Execute JavaScript Code' ],
			'execute_nodejs' => [ 'label' => 'Execute Node.js Code' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'execute_php'    => self::php_schema(),
			'execute_js'     => self::js_schema(),
			'execute_nodejs' => self::nodejs_schema(),
		];

		return $schemas[ $action ] ?? [];
	}

	private static function php_schema(): array {
		return [
			[
				'key'         => 'code',
				'label'       => 'PHP Code',
				'type'        => 'code',
				'language'    => 'php',
				'required'    => true,
				'placeholder' => 'return "Hello " . $input["name"];',
				'help'        => 'PHP code to run. The leading <?php tag is optional. Previous steps\' data is available as $input (array), the node settings as $config (array). The return value becomes this step\'s output.',
			],
			[
				'key'         => 'timeout',
				'label'       => 'Timeout (seconds)',
				'type'        => 'number',
				'required'    => false,
				'default'     => self::DEFAULT_TIMEOUT,
				'help'        => 'Maximum execution time in seconds (default: ' . self::DEFAULT_TIMEOUT . ', max: ' . self::MAX_TIMEOUT . ').',
			],
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

	private static function js_schema(): array {
		return [
			[
				'key'         => 'code',
				'label'       => 'JavaScript Code',
				'type'        => 'code',
				'language'    => 'javascript',
				'required'    => true,
				'placeholder' => 'return "Hello " + input.name;',
				'help'        => 'JavaScript to run with the server\'s Node.js runtime. Input data is available as "input" object; async/await is supported. The return value will be the output. Node.js must be installed on the server.',
			],
			[
				'key'         => 'timeout',
				'label'       => 'Timeout (seconds)',
				'type'        => 'number',
				'required'    => false,
				'default'     => self::DEFAULT_TIMEOUT,
				'help'        => 'Maximum execution time in seconds (default: ' . self::DEFAULT_TIMEOUT . ', max: ' . self::MAX_TIMEOUT . ').',
			],
		];
	}

	private static function nodejs_schema(): array {
		return [
			[
				'key'         => 'code',
				'label'       => 'Node.js Code',
				'type'        => 'code',
				'language'    => 'nodejs',
				'required'    => true,
				'placeholder' => 'return "Hello " + input.name;',
				'help'        => 'Node.js code to run with the server\'s node binary. Input data is available as "input" object; async/await is supported. The return value will be the output. Node.js must be installed on the server.',
			],
			[
				'key'         => 'timeout',
				'label'       => 'Timeout (seconds)',
				'type'        => 'number',
				'required'    => false,
				'default'     => self::DEFAULT_TIMEOUT,
				'help'        => 'Maximum execution time in seconds (default: ' . self::DEFAULT_TIMEOUT . ', max: ' . self::MAX_TIMEOUT . '). The process is terminated when it is exceeded.',
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		$code = $config['code'] ?? '';
		if ( ! \is_string( $code ) || '' === trim( $code ) ) {
			return self::error_response( 'Code is required.' );
		}

		$timeout = self::clamp( (int) ( $config['timeout'] ?? self::DEFAULT_TIMEOUT ), self::MIN_TIMEOUT, self::MAX_TIMEOUT );

		try {
			switch ( $event ) {
				case 'execute_php':
					return self::execute_php_code( $code, $input, $config, $timeout );

				case 'execute_js':
					return self::execute_js_code( $code, $input, $timeout );

				case 'execute_nodejs':
					return self::execute_nodejs_code( $code, $input, $timeout );

				default:
					return self::error_response( 'Unknown execution type.' );
			}
		} catch ( \Throwable $e ) {
			return self::error_response( 'Execution error: ' . $e->getMessage() );
		}
	}

	// =========================================================================
	// PHP EXECUTION
	// =========================================================================

	private static function execute_php_code( string $code, array $input, array $config, int $timeout ): array {
		$source = self::strip_php_open_tag( $code );

		if ( self::php_code_is_blocked( $source ) ) {
			return self::error_response( 'Code contains blocked functions.' );
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
		if ( \function_exists( 'ini_set' ) ) {
			@ini_set( 'memory_limit', $memory_limit . 'M' );
		}

		try {
			$result = self::run_php_closure( $source, $input, $config );
		} catch ( \Throwable $e ) {
			return self::error_response( 'PHP execution error: ' . $e->getMessage() );
		} finally {
			if ( false !== $previous_memory_limit && \function_exists( 'ini_set' ) ) {
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
		$dir = self::runtime_directory();

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
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			@mkdir( $dir, 0777, true );
		}

		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			throw new \RuntimeException( 'Unable to create a writable runtime directory for code execution.' );
		}

		return $dir;
	}

	/**
	 * Tolerate snippets pasted with a full open tag; the runtime closure
	 * already sits inside PHP context.
	 */
	private static function strip_php_open_tag( string $code ): string {
		$code = ltrim( $code );

		if ( strpos( $code, '<?php' ) === 0 ) {
			return ltrim( substr( $code, 5 ) );
		}

		if ( strpos( $code, '<?=' ) === 0 ) {
			return ltrim( substr( $code, 3 ) );
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

	private static function execute_js_code( string $code, array $input, int $timeout ): array {
		if ( self::js_code_is_blocked( $code ) ) {
			return self::error_response( 'Code contains blocked JavaScript patterns.' );
		}

		return self::execute_nodejs_code( $code, $input, $timeout, 'JavaScript' );
	}

	private static function js_code_is_blocked( string $code ): bool {
		return (bool) preg_match( self::JS_BLOCKED_PATTERN, self::strip_js_comments_and_strings( $code ) )
			|| (bool) preg_match( self::NODE_BLOCKED_MODULES, self::strip_js_comments( $code ) );
	}

	// =========================================================================
	// NODE.JS EXECUTION
	// =========================================================================

	private static function execute_nodejs_code( string $code, array $input, int $timeout, string $runtime_label = 'Node.js' ): array {
		if ( ! \function_exists( 'proc_open' ) ) {
			return self::error_response( 'Node.js execution is not available: proc_open() is disabled on this server.' );
		}

		if ( self::nodejs_code_is_blocked( $code ) ) {
			return self::error_response( 'Code contains blocked Node.js patterns.' );
		}

		$node_path = self::find_node_executable();
		if ( '' === $node_path ) {
			return self::error_response( 'Node.js is not installed or not found on this server.' );
		}

		$temp_file = self::write_node_script( $code, $input );
		if ( '' === $temp_file ) {
			return self::error_response( 'Failed to create a temporary script file.' );
		}

		try {
			$command = escapeshellarg( $node_path ) . ' ' . escapeshellarg( $temp_file );
			$run     = self::run_process( $command, $timeout );

			if ( ! $run['started'] ) {
				return self::error_response( 'Failed to start the Node.js process.' );
			}

			if ( $run['timed_out'] ) {
				return self::error_response( 'Node.js execution timed out after ' . $timeout . ' seconds.' );
			}

			if ( 0 !== $run['exit_code'] ) {
				$message = trim( $run['stderr'] ) !== '' ? trim( $run['stderr'] ) : 'exit code ' . $run['exit_code'];

				return self::error_response( 'Node.js execution failed: ' . $message );
			}

			$decoded = json_decode( trim( $run['stdout'] ), true );
			if ( null === $decoded || ! \is_array( $decoded ) || ! \array_key_exists( 'result', $decoded ) ) {
				return self::error_response( 'Failed to parse Node.js output: ' . trim( $run['stdout'] ) );
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
		$dir = sys_get_temp_dir();

		$temp_file = tempnam( $dir, 'zaplane-' );
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

	private static function nodejs_code_is_blocked( string $code ): bool {
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
				'success' => true,
				'result'  => $data,
				'output'  => is_scalar( $data ) ? (string) $data : wp_json_encode( $data ),
			],
		];
	}

	private static function error_response( string $message ): array {
		return [
			'port' => 'main',
			'data' => [
				'success' => false,
				'error'   => $message,
			],
		];
	}

	public static function get_action_sample_output( string $action ): array {
		return [
			'success' => true,
			'result'  => null,
			'output'  => '',
		];
	}
}
