<?php
/**
 * `wp zaplane recipe ...` — live integration recipe testing.
 *
 * @package Zaplane
 */

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Testing\RecipeRunner;
use Zaplane\Testing\RecipeResult;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeCommand extends Command {

	protected string $signature = 'recipe';
	protected string $description = 'Run, generate, and list live integration test recipes';

	public function handle( array $args, array $assoc_args ): void {
		$sub = $args[0] ?? 'run';

		switch ( $sub ) {
			case 'run':
				$this->run_recipes( $args[1] ?? null, $assoc_args );
				break;
			case 'generate':
				$this->generate_recipe( $args[1] ?? null, $assoc_args );
				break;
			case 'list':
				$this->list_recipes();
				break;
			case 'exec':
				$this->exec_recipe( $args[1] ?? null );
				break;
			default:
				$this->error( "Unknown subcommand '{$sub}'. Use: run | generate | list" );
		}
	}

	protected function run_recipes( ?string $filter, array $assoc_args ): void {
		$keep_active = isset( $assoc_args['keep-active'] );
		$files       = RecipeRunner::discover( $filter );

		if ( empty( $files ) ) {
			$this->error( 'No recipes found' . ( $filter ? " for '{$filter}'." : '.' ) );
			return;
		}

		$this->info( sprintf( 'Running %d recipe(s)...', count( $files ) ) );
		$this->line();

		$rows   = [];
		$failed = 0;

		foreach ( $files as $file ) {
			[ $result, $note ] = $this->run_one( $file, $keep_active );

			if ( ! $result || ! $result->passed ) {
				++$failed;
			}

			$rows[] = [
				$result ? $result->name : basename( $file ),
				$result ? $result->integration : '—',
				$result ? $result->status_label() : 'FAIL',
				$result && $result->passed ? $note : ( $result ? $result->failure_summary() : $note ),
			];
		}

		$this->table( [ 'Recipe', 'Integration', 'Status', 'Notes' ], $rows );
		$this->line();

		$passed = count( $files ) - $failed;
		if ( $failed > 0 ) {
			$this->error( sprintf( '%d passed, %d failed.', $passed, $failed ) );
			return; // Command::error() exits non-zero under WP-CLI.
		}

		$this->success( sprintf( 'All %d recipe(s) passed.', $passed ) );
	}

	/**
	 * Run one recipe file: activate any inactive dependency plugins, fire the
	 * recipe in a fresh WP-CLI subprocess (so heavy plugins like WooCommerce
	 * load normally on plugins_loaded), then restore plugin state.
	 *
	 * @return array{0: ?RecipeResult, 1: string} [ result, note ]
	 */
	protected function run_one( string $file, bool $keep_active ): array {
		try {
			$recipe = RecipeRunner::load_file( $file );
		} catch ( \Throwable $e ) {
			return [ null, $e->getMessage() ];
		}

		$required = RecipeRunner::required_plugins( $recipe );

		try {
			$activated = RecipeRunner::activate_plugins( $required );
		} catch ( \Throwable $e ) {
			$result = new RecipeResult( $recipe['name'] ?? basename( $file, '.json' ), $recipe['integration'] ?? '' );
			$result->abort( $e->getMessage() );
			return [ $result, $result->failure_summary() ];
		}

		try {
			$result = $this->fire_in_subprocess( $file );
		} finally {
			if ( ! $keep_active && ! empty( $activated ) ) {
				RecipeRunner::deactivate_plugins( $activated );
			}
		}

		$note = empty( $activated ) ? '' : 'activated: ' . implode( ', ', $activated );
		return [ $result, $note ];
	}

	/**
	 * Fire one recipe in a child WP-CLI process via `recipe exec` and parse the
	 * marked result line from its output.
	 */
	protected function fire_in_subprocess( string $file ): RecipeResult {
		$run = \WP_CLI::runcommand(
			'zaplane recipe exec ' . escapeshellarg( $file ),
			[
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			]
		);

		$result = RecipeResult::from_wire( (string) ( $run->stdout ?? '' ) );
		if ( $result ) {
			return $result;
		}

		// No marked line — surface whatever the child emitted.
		$recipe  = RecipeRunner::load_file( $file );
		$fallback = new RecipeResult( $recipe['name'] ?? basename( $file, '.json' ), $recipe['integration'] ?? '' );
		$stderr   = trim( (string) ( $run->stderr ?? '' ) );
		return $fallback->abort( $stderr ?: 'Subprocess produced no result.' );
	}

	/**
	 * Internal: fire a single recipe in *this* process (deps assumed loaded)
	 * and print the result as a marked line for the parent to parse.
	 */
	protected function exec_recipe( ?string $file ): void {
		if ( ! $file || ! is_file( $file ) ) {
			$this->error( 'recipe exec requires a valid recipe file path.' );
			return;
		}

		$result = RecipeRunner::fire( RecipeRunner::load_file( $file ) );
		$this->line( $result->to_wire() );
	}

	protected function generate_recipe( ?string $integration, array $assoc_args ): void {
		if ( ! $integration ) {
			$this->error( 'Usage: wp zaplane recipe generate <integration> [--event=<e>] [--kind=trigger|action]' );
			return;
		}

		$instance = IntegrationLoader::get( $integration );
		if ( ! $instance ) {
			$this->error( "Unknown integration: {$integration}" );
			return;
		}
		$class = get_class( $instance );

		$kind = $assoc_args['kind'] ?? 'trigger';
		if ( ! in_array( $kind, [ 'trigger', 'action' ], true ) ) {
			$this->error( "--kind must be 'trigger' or 'action'." );
			return;
		}

		$events = 'trigger' === $kind ? $class::get_triggers() : $class::get_actions();
		if ( empty( $events ) ) {
			$this->error( "Integration '{$integration}' declares no {$kind}s." );
			return;
		}

		$event = $assoc_args['event'] ?? array_key_first( $events );
		if ( ! isset( $events[ $event ] ) ) {
			$this->error( "Unknown {$kind} '{$event}'. Available: " . implode( ', ', array_keys( $events ) ) );
			return;
		}

		$sample = 'trigger' === $kind ? $class::get_trigger_sample_output( $event ) : [];
		$expect = [ 'not_false' => true ];
		if ( ! empty( $sample ) ) {
			$expect['has_keys'] = array_slice( array_keys( $sample ), 0, 3 );
		}

		$recipe = [
			'name'             => "{$integration}-{$event}",
			'integration'      => $integration,
			'required_plugins' => array_values( (array) $class::get_required_plugins() ),
			'setup'            => [ 'factory' => '', 'args' => new \stdClass() ],
			'node'             => [
				'kind'   => $kind,
				'event'  => $event,
				'config' => new \stdClass(),
				'input'  => [],
			],
			'expect'           => $expect,
		];

		$dir = RecipeRunner::base_dir() . $integration . '/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$path = $dir . "{$event}.json";

		if ( file_exists( $path ) ) {
			$this->error( 'Recipe already exists: ' . str_replace( ZAPLANE_ROOT_DIR_PATH, '', $path ) );
			return;
		}

		$json = wp_json_encode( $recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		file_put_contents( $path, $json . "\n" );

		$this->success( 'Created ' . str_replace( ZAPLANE_ROOT_DIR_PATH, '', $path ) );
		$this->line( 'Fill in setup.factory, node.input/config, and expect, then run:' );
		$this->line( "  wp zaplane recipe run {$integration}" );
	}

	protected function list_recipes(): void {
		$files = RecipeRunner::discover();

		if ( empty( $files ) ) {
			$this->warning( 'No recipes found under recipes-test/.' );
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$rows = [];
		foreach ( $files as $file ) {
			try {
				$recipe = RecipeRunner::load_file( $file );
			} catch ( \Throwable $e ) {
				$rows[] = [ basename( $file ), '—', 'invalid JSON' ];
				continue;
			}

			$slug     = $recipe['integration'] ?? '—';
			$instance = is_string( $slug ) ? IntegrationLoader::get( $slug ) : null;
			$required = $instance
				? array_unique( array_merge( (array) ( $recipe['required_plugins'] ?? [] ), (array) get_class( $instance )::get_required_plugins() ) )
				: (array) ( $recipe['required_plugins'] ?? [] );

			$deps = [];
			foreach ( $required as $basename ) {
				$deps[] = $basename . ( is_plugin_active( $basename ) ? ' (active)' : ' (inactive)' );
			}

			$rows[] = [
				$recipe['name'] ?? basename( $file, '.json' ),
				$slug,
				empty( $deps ) ? 'wp core' : implode( ', ', $deps ),
			];
		}

		$this->table( [ 'Recipe', 'Integration', 'Required plugins' ], $rows );
	}
}
