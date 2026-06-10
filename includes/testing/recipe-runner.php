<?php
/**
 * Runs declarative JSON recipes against live integrations.
 *
 * A recipe describes an integration plus the trigger/action to fire, the input,
 * and the expected output. The runner auto-activates any required dependency
 * plugin, fires the trigger/action against the *real* plugin, asserts the
 * result, then restores plugin state.
 *
 * CLI-agnostic on purpose so it can be unit-tested and reused.
 *
 * @package Zaplane
 */

namespace Zaplane\Testing;

use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeRunner {

	/** Absolute path to the recipes-test/ tree. */
	public static function base_dir(): string {
		return ZAPLANE_ROOT_DIR_PATH . 'recipes-test/';
	}

	/**
	 * Discover recipe files under recipes-test/.
	 *
	 * @param string|null $filter A direct .json path, an integration slug
	 *                            (recipes-test/<slug>/*.json), or null for all.
	 * @return string[] Absolute file paths.
	 */
	public static function discover( ?string $filter = null ): array {
		if ( $filter ) {
			// A direct .json path: try as given, then relative to the plugin root.
			foreach ( [ $filter, ZAPLANE_ROOT_DIR_PATH . ltrim( $filter, '/' ) ] as $candidate ) {
				if ( is_file( $candidate ) ) {
					return [ $candidate ];
				}
			}
		}

		$base = self::base_dir();

		if ( $filter ) {
			$dir = $base . trim( $filter, '/' ) . '/';
			return is_dir( $dir ) ? self::glob_recursive( $dir ) : [];
		}

		return is_dir( $base ) ? self::glob_recursive( $base ) : [];
	}

	/** @return string[] */
	protected static function glob_recursive( string $dir ): array {
		$found = [];
		$iter  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iter as $file ) {
			if ( $file->isFile() && 'json' === strtolower( $file->getExtension() ) ) {
				$found[] = $file->getPathname();
			}
		}
		sort( $found );
		return $found;
	}

	/** Load and decode a recipe JSON file. */
	public static function load_file( string $path ): array {
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			throw new \RuntimeException( "Cannot read recipe: {$path}" );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( "Invalid JSON in recipe: {$path}" );
		}

		if ( empty( $data['name'] ) ) {
			$data['name'] = basename( $path, '.json' );
		}

		return $data;
	}

	/**
	 * The plugin basenames a recipe needs: the recipe's `required_plugins`
	 * merged with the integration's `get_required_plugins()`.
	 *
	 * @return string[]
	 */
	public static function required_plugins( array $recipe ): array {
		$declared = (array) ( $recipe['required_plugins'] ?? [] );

		$slug     = (string) ( $recipe['integration'] ?? '' );
		$instance = '' !== $slug ? IntegrationLoader::get( $slug ) : null;
		$from_cls = $instance ? (array) get_class( $instance )::get_required_plugins() : [];

		return array_values( array_unique( array_merge( $declared, $from_cls ) ) );
	}

	/**
	 * Run a single recipe in-process: activate required plugins, fire, assert,
	 * restore. Reliable for lightweight plugins or when deps are already loaded;
	 * heavy plugins (e.g. WooCommerce) must be loaded at request start, so the
	 * CLI fires them in a fresh subprocess via fire() instead — see RecipeCommand.
	 *
	 * @param array $opts [ 'keep_active' => bool ]
	 */
	public static function run( array $recipe, array $opts = [] ): RecipeResult {
		$required  = self::required_plugins( $recipe );
		$activated = [];

		try {
			$activated = self::activate_plugins( $required );
		} catch ( \Throwable $e ) {
			$result = new RecipeResult( (string) ( $recipe['name'] ?? 'unnamed' ), (string) ( $recipe['integration'] ?? '' ) );
			return $result->abort( $e->getMessage() );
		}

		try {
			$result = self::fire( $recipe );
		} finally {
			if ( empty( $opts['keep_active'] ) && ! empty( $activated ) ) {
				self::deactivate_plugins( $activated );
			}
		}

		$result->activated_plugins = $activated;
		return $result;
	}

	/**
	 * Fire a recipe's trigger/action and assert the output. Assumes every
	 * required plugin is already active *and loaded* for this request. Does no
	 * activation or restoration of its own.
	 */
	public static function fire( array $recipe ): RecipeResult {
		$name        = (string) ( $recipe['name'] ?? 'unnamed' );
		$integration = (string) ( $recipe['integration'] ?? '' );
		$result      = new RecipeResult( $name, $integration );

		if ( '' === $integration ) {
			return $result->abort( 'Recipe is missing the "integration" slug.' );
		}

		$instance = IntegrationLoader::get( $integration );
		if ( ! $instance ) {
			return $result->abort( "Unknown integration: {$integration}" );
		}
		$class = get_class( $instance );

		$node = $recipe['node'] ?? [];
		$kind = (string) ( $node['kind'] ?? 'action' );
		if ( ! in_array( $kind, [ 'trigger', 'action' ], true ) ) {
			return $result->abort( "Recipe node.kind must be 'trigger' or 'action', got '{$kind}'." );
		}

		try {
			// Seed data via the optional factory or action.
			$vars = self::seed_vars( $recipe );

			// Build the node + input with vars interpolated.
			$event      = (string) ( $node['event'] ?? '' );
			$config     = self::interpolate( (array) ( $node['config'] ?? [] ), $vars );
			$input      = self::interpolate( (array) ( $node['input'] ?? [] ), $vars );
			if ( 'trigger' === $kind ) {
				$input = self::resolve_trigger_input( $recipe, $class, $event, array_values( $input ) );
			} else {
				$config = self::resolve_action_config( $recipe, $class, $event, $config );
			}
			$node_array = [
				'type'   => $kind,
				'event'  => $event,
				'config' => $config,
				'data'   => [
					'app'    => $integration,
					'event'  => $event,
					'config' => $config,
				],
			];

			// Fire against the live integration.
			if ( 'trigger' === $kind ) {
				$result->output = $class::resolve_trigger( $node_array, array_values( $input ) );
			} else {
				$result->output = $class::execute_node( $node_array, $input );
			}

			self::assert_expectations( $result, $kind, $recipe['expect'] ?? [] );
		} catch ( RecipeSkip $e ) {
			return $result->skip( $e->getMessage() );
		} catch ( \Throwable $e ) {
			$result->abort( 'Run error: ' . $e->getMessage() );
		}

		return $result->finalize();
	}

	/**
	 * Evaluate a recipe's `expect` block against an already-captured output.
	 * Public so the E2E runner can assert the engine's node output with the same
	 * rules as direct mode.
	 */
	public static function evaluate( RecipeResult $result, string $kind, array $expect, $output ): void {
		$result->output = $output;
		self::assert_expectations( $result, $kind, $expect );
	}

	/** Public wrapper around the {{var}} interpolation, for the E2E runner. */
	public static function interpolate_value( $value, array $vars ) {
		return self::interpolate( $value, $vars );
	}

	/**
	 * True when a recipe is still an unfilled `generate` scaffold — its node
	 * input/config contains `{{argN}}` placeholder tokens. Such recipes can't run
	 * meaningfully (the args are placeholders), so the CLI skips them.
	 */
	public static function is_unfilled( array $recipe ): bool {
		$setup = $recipe['setup'] ?? [];
		// A factory/action (even a stub) means it's being worked on — let it run so a
		// stub can SKIP with its own "implement me" message instead of a generic skip.
		if ( ! empty( $setup['factory'] ) || ! empty( $setup['action'] ) ) {
			return false;
		}

		$node = $recipe['node'] ?? [];

		// Still has generator {{argN}} placeholders → not filled in.
		$blob = wp_json_encode( $node );
		if ( is_string( $blob ) && preg_match( '/\{\{\s*arg\d+\s*\}\}/', $blob ) ) {
			return true;
		}

		// A trigger with no input and nothing to seed it can't fire meaningfully —
		// unless the integration can self-seed this event (get_seedable_triggers).
		if ( 'trigger' === ( $node['kind'] ?? '' ) && empty( $node['input'] ) ) {
			return ! self::integration_can_seed( (string) ( $recipe['integration'] ?? '' ), (string) ( $node['event'] ?? '' ) );
		}

		return false;
	}

	/** Whether the integration declares it can create sample data for this trigger event. */
	private static function integration_can_seed( string $slug, string $event ): bool {
		if ( '' === $slug || '' === $event ) {
			return false;
		}
		$instance = IntegrationLoader::get( $slug );
		return $instance && in_array( $event, (array) get_class( $instance )::get_seedable_triggers(), true );
	}

	/**
	 * Produce the variable map a recipe's `input`/`config` reference, from its
	 * `setup`. Two ways, no custom PHP needed for the action case:
	 *   - setup.action  → run the integration's OWN action (e.g. woocommerce
	 *                     create_order) and expose its output data as vars.
	 *   - setup.factory → a named factory (generic or recipes-test/<x>/factories.php).
	 */
	public static function seed_vars( array $recipe ): array {
		$setup = $recipe['setup'] ?? [];

		if ( ! empty( $setup['action'] ) && is_array( $setup['action'] ) ) {
			return self::seed_via_action( $setup['action'] );
		}
		if ( ! empty( $setup['factory'] ) ) {
			return RecipeFactories::run( (string) $setup['factory'], (array) ( $setup['args'] ?? [] ) );
		}
		return [];
	}

	/** Run an integration action purely to create seed data; return its output data as vars. */
	private static function seed_via_action( array $action ): array {
		$slug     = (string) ( $action['app'] ?? '' );
		$instance = '' !== $slug ? IntegrationLoader::get( $slug ) : null;
		if ( ! $instance ) {
			throw new \RuntimeException( "setup.action: unknown integration '{$slug}'" );
		}

		$class = get_class( $instance );
		$node  = [
			'data' => [
				'app'    => $slug,
				'event'  => (string) ( $action['event'] ?? '' ),
				'config' => (array) ( $action['config'] ?? [] ),
			],
		];

		$out = $class::execute_node( $node, [] );
		if ( is_array( $out ) && 'error' === ( $out['port'] ?? '' ) ) {
			throw new \RuntimeException( 'setup.action failed: ' . wp_json_encode( $out['data'] ?? $out ) );
		}

		$data = is_array( $out ) ? ( $out['data'] ?? $out ) : [];
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Final positional input for a trigger. Uses the recipe's own input if it has
	 * any; otherwise — when the recipe declares no factory/action — falls back to
	 * the integration's own self-seeding (seed_trigger_args), which creates real
	 * data and returns the hook args. This is what lets a bare recipe just run.
	 */
	public static function resolve_trigger_input( array $recipe, string $class, string $event, array $input ): array {
		if ( ! empty( $input ) ) {
			return $input;
		}

		$setup = $recipe['setup'] ?? [];
		if ( ! empty( $setup['factory'] ) || ! empty( $setup['action'] ) ) {
			return $input; // author picked an explicit seed; respect it.
		}

		$sample = $class::seed_trigger_args( $event );
		return is_array( $sample ) ? array_values( $sample ) : $input;
	}

	/**
	 * Final config for an action. Uses the recipe's own config if any; otherwise —
	 * when the recipe declares no factory/action — falls back to the integration's
	 * own sample config (get_sample_action_config), so a bare action recipe runs.
	 */
	public static function resolve_action_config( array $recipe, string $class, string $event, array $config ): array {
		if ( ! empty( $config ) ) {
			return $config;
		}

		$setup = $recipe['setup'] ?? [];
		if ( ! empty( $setup['factory'] ) || ! empty( $setup['action'] ) ) {
			return $config;
		}

		$sample = $class::get_sample_action_config( $event );
		return is_array( $sample ) ? $sample : $config;
	}

	/**
	 * Activate any of the given plugin basenames that aren't already active.
	 *
	 * @return string[] The basenames this call activated.
	 * @throws \RuntimeException When a required plugin can't be activated.
	 */
	public static function activate_plugins( array $basenames ): array {
		if ( empty( $basenames ) ) {
			return [];
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$activated = [];
		foreach ( $basenames as $basename ) {
			if ( is_plugin_active( $basename ) ) {
				continue;
			}

			$error = activate_plugin( $basename );
			if ( is_wp_error( $error ) ) {
				// Roll back anything we activated before failing.
				self::deactivate_plugins( $activated );
				throw new \RuntimeException(
					"Could not activate required plugin '{$basename}': " . $error->get_error_message()
				);
			}
			$activated[] = $basename;
		}

		return $activated;
	}

	public static function deactivate_plugins( array $basenames ): void {
		if ( empty( $basenames ) ) {
			return;
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Deactivate in reverse activation order.
		deactivate_plugins( array_reverse( $basenames ), true );
	}

	/**
	 * Replace {{var}} tokens in any string within $value using $vars.
	 * A string that is exactly "{{var}}" yields the var's native type.
	 */
	protected static function interpolate( $value, array $vars ) {
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::interpolate( $v, $vars );
			}
			return $out;
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		// Whole-string token: preserve the variable's native type. Supports dot
		// paths (e.g. {{order.order_id}}) for nested data from action seeding.
		if ( preg_match( '/^\{\{\s*([\w.]+)\s*\}\}$/', $value, $m ) ) {
			[ $found, $val ] = self::var_lookup( $vars, $m[1] );
			if ( $found ) {
				return $val;
			}
		}

		// Inline tokens: substitute as strings.
		return preg_replace_callback(
			'/\{\{\s*([\w.]+)\s*\}\}/',
			static function ( $m ) use ( $vars ) {
				[ $found, $val ] = self::var_lookup( $vars, $m[1] );
				return $found ? (string) $val : $m[0];
			},
			$value
		);
	}

	/** Look up a var by key, supporting dot paths into nested arrays. @return array{0:bool,1:mixed} */
	private static function var_lookup( array $vars, string $key ): array {
		if ( array_key_exists( $key, $vars ) ) {
			return [ true, $vars[ $key ] ];
		}
		$cur = $vars;
		foreach ( explode( '.', $key ) as $seg ) {
			if ( is_array( $cur ) && array_key_exists( $seg, $cur ) ) {
				$cur = $cur[ $seg ];
			} else {
				return [ false, null ];
			}
		}
		return [ true, $cur ];
	}

	/**
	 * Evaluate the recipe's `expect` block against the output.
	 *
	 * Supported keys: not_false, port, has_keys, data (partial), equals (exact).
	 */
	protected static function assert_expectations( RecipeResult $result, string $kind, array $expect ): void {
		$output = $result->output;

		if ( ! empty( $expect['not_false'] ) ) {
			if ( false === $output || null === $output ) {
				$result->fail( 'Expected a payload but the integration returned false/null (trigger filtered out or action failed).' );
				return;
			}
		}

		// Extract the comparable payload.
		if ( 'action' === $kind ) {
			$payload = is_array( $output ) ? ( $output['data'] ?? [] ) : [];
			if ( isset( $expect['port'] ) ) {
				$actual_port = is_array( $output ) ? ( $output['port'] ?? null ) : null;
				if ( $actual_port !== $expect['port'] ) {
					$result->fail( sprintf( "Expected port '%s', got '%s'.", $expect['port'], var_export( $actual_port, true ) ) );
				}
			}
		} else {
			$payload = is_array( $output ) ? $output : [];
		}

		if ( ! empty( $expect['has_keys'] ) ) {
			foreach ( (array) $expect['has_keys'] as $key ) {
				if ( ! array_key_exists( $key, $payload ) ) {
					$result->fail( "Missing expected key '{$key}' in output." );
				}
			}
		}

		if ( isset( $expect['data'] ) && is_array( $expect['data'] ) ) {
			self::assert_partial( $result, $expect['data'], $payload, 'data' );
		}

		if ( isset( $expect['equals'] ) && is_array( $expect['equals'] ) ) {
			if ( $expect['equals'] !== $payload ) {
				$result->fail( 'Output did not exactly equal the expected payload.' );
			}
		}
	}

	/** Recursively assert every key/value in $expected exists and matches in $actual. */
	protected static function assert_partial( RecipeResult $result, array $expected, $actual, string $path ): void {
		if ( ! is_array( $actual ) ) {
			$result->fail( "Expected an object/array at '{$path}'." );
			return;
		}

		foreach ( $expected as $key => $value ) {
			$here = "{$path}.{$key}";
			if ( ! array_key_exists( $key, $actual ) ) {
				$result->fail( "Missing expected key '{$here}'." );
				continue;
			}

			if ( is_array( $value ) ) {
				self::assert_partial( $result, $value, $actual[ $key ], $here );
			} elseif ( $actual[ $key ] != $value ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- recipes are JSON; allow scalar coercion.
				$result->fail(
					sprintf( "At '%s' expected %s, got %s.", $here, var_export( $value, true ), var_export( $actual[ $key ], true ) )
				);
			}
		}
	}
}
