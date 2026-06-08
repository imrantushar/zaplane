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
		if ( $filter && is_file( $filter ) ) {
			return [ $filter ];
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
			// Seed data via the optional factory.
			$vars = [];
			if ( ! empty( $recipe['setup']['factory'] ) ) {
				$vars = RecipeFactories::run(
					(string) $recipe['setup']['factory'],
					(array) ( $recipe['setup']['args'] ?? [] )
				);
			}

			// Build the node + input with vars interpolated.
			$event      = (string) ( $node['event'] ?? '' );
			$config     = self::interpolate( (array) ( $node['config'] ?? [] ), $vars );
			$input      = self::interpolate( (array) ( $node['input'] ?? [] ), $vars );
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
		} catch ( \Throwable $e ) {
			$result->abort( 'Run error: ' . $e->getMessage() );
		}

		return $result->finalize();
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

		// Whole-string token: preserve the variable's native type.
		if ( preg_match( '/^\{\{\s*([\w.]+)\s*\}\}$/', $value, $m ) && array_key_exists( $m[1], $vars ) ) {
			return $vars[ $m[1] ];
		}

		// Inline tokens: substitute as strings.
		return preg_replace_callback(
			'/\{\{\s*([\w.]+)\s*\}\}/',
			static function ( $m ) use ( $vars ) {
				return array_key_exists( $m[1], $vars ) ? (string) $vars[ $m[1] ] : $m[0];
			},
			$value
		);
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
