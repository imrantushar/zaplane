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
use Zaplane\Testing\RecipeE2eRunner;
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
				$this->exec_recipe( $args[1] ?? null, $assoc_args );
				break;
			case 'exec-batch':
				$this->exec_batch( array_slice( $args, 1 ), $assoc_args );
				break;
			default:
				$this->error( "Unknown subcommand '{$sub}'. Use: run | generate | list" );
		}
	}

	protected function run_recipes( ?string $filter, array $assoc_args ): void {
		$keep_active = isset( $assoc_args['keep-active'] );
		$e2e         = isset( $assoc_args['e2e'] );
		$fail_fast   = isset( $assoc_args['fail-fast'] );
		$files       = RecipeRunner::discover( $filter );

		if ( empty( $files ) ) {
			$this->error( 'No recipes found' . ( $filter ? " for '{$filter}'." : '.' ) );
			return;
		}

		$flags = [ 'e2e' => $e2e, 'keep_workflow' => isset( $assoc_args['keep-workflow'] ) ];

		$mode = $e2e ? 'end-to-end (real workflow + engine)' : 'direct';
		$this->info( sprintf( 'Running %d recipe(s) — %s mode...', count( $files ), $mode ) );
		$this->line();

		$rows    = [];
		$passed  = 0;
		$failed  = 0;
		$skipped = 0;

		// Classify recipes, skipping ones that can't run, then group the runnable
		// ones by their required-plugin set so each group shares ONE subprocess
		// (the plugin boots once for all of its recipes — the main speed win).
		$groups = [];
		foreach ( $files as $file ) {
			try {
				$recipe = RecipeRunner::load_file( $file );
			} catch ( \Throwable $e ) {
				$rows[] = [ basename( $file ), '—', 'FAIL', $e->getMessage() ];
				++$failed;
				continue;
			}

			$name = $recipe['name'] ?? basename( $file, '.json' );
			$slug = $recipe['integration'] ?? '—';

			if ( $e2e && 'trigger' !== ( $recipe['node']['kind'] ?? '' ) ) {
				$rows[] = [ $name, $slug, 'SKIP', 'action recipe — E2E only runs triggers' ];
				++$skipped;
				continue;
			}

			if ( RecipeRunner::is_unfilled( $recipe ) ) {
				$rows[] = [ $name, $slug, 'SKIP', 'scaffold not filled in ({{argN}} placeholders)' ];
				++$skipped;
				continue;
			}

			$req = RecipeRunner::required_plugins( $recipe );
			sort( $req );
			$sig = implode( '|', $req );
			if ( ! isset( $groups[ $sig ] ) ) {
				$groups[ $sig ] = [ 'plugins' => $req, 'files' => [] ];
			}
			$groups[ $sig ]['files'][] = $file;
		}

		// Run each plugin-group: activate once → one subprocess for all → restore.
		$aborted = false;
		foreach ( $groups as $group ) {
			if ( $aborted ) {
				break;
			}

			try {
				$activated = RecipeRunner::activate_plugins( $group['plugins'] );
			} catch ( \Throwable $e ) {
				foreach ( $group['files'] as $file ) {
					$rows[] = [ basename( $file, '.json' ), '—', 'FAIL', $e->getMessage() ];
					++$failed;
				}
				if ( $fail_fast ) {
					$aborted = true;
				}
				continue;
			}

			try {
				$results = $this->exec_batch_subprocess( $group['files'], $flags );
			} finally {
				if ( ! $keep_active && ! empty( $activated ) ) {
					RecipeRunner::deactivate_plugins( $activated );
				}
			}

			$note = empty( $activated ) ? '' : 'activated: ' . implode( ', ', $activated );

			foreach ( $group['files'] as $i => $file ) {
				$result = $results[ $i ] ?? null;

				// In E2E mode show the workflow/run/node-run log the engine produced.
				if ( $e2e && $result && ! empty( $result->log_lines ) ) {
					$this->line( '▸ ' . $result->name );
					foreach ( $result->log_lines as $log ) {
						$this->line( '  ' . $log );
					}
					$this->line();
				}

				if ( $result && $result->passed ) {
					++$passed;
				} else {
					++$failed;
				}

				$rows[] = [
					$result ? $result->name : basename( $file, '.json' ),
					$result ? $result->integration : '—',
					$result ? $result->status_label() : 'FAIL',
					$result && $result->passed ? $note : ( $result ? $result->failure_summary() : 'no result' ),
				];

				if ( $fail_fast && ( ! $result || ! $result->passed ) ) {
					$aborted = true;
					break;
				}
			}
		}

		$this->table( [ 'Recipe', 'Integration', 'Status', 'Notes' ], $rows );
		$this->line();

		$summary = sprintf( '%d passed, %d skipped, %d failed.', $passed, $skipped, $failed );
		if ( $aborted && $failed > 0 ) {
			$summary .= ' (stopped early — --fail-fast)';
		}

		if ( $failed > 0 ) {
			$this->error( $summary ); // exits non-zero under WP-CLI.
			return;
		}
		$this->success( $summary );
	}

	/**
	 * Run a whole plugin-group of recipes in ONE child WP-CLI process. The plugin
	 * (just activated by the parent) boots once for all of them, instead of once
	 * per recipe. Results are returned positionally aligned to $files.
	 *
	 * @return array<int, ?RecipeResult>
	 */
	protected function exec_batch_subprocess( array $files, array $flags = [] ): array {
		$extra  = ! empty( $flags['e2e'] ) ? ' --e2e' : '';
		$extra .= ! empty( $flags['keep_workflow'] ) ? ' --keep-workflow' : '';
		$paths  = implode( ' ', array_map( 'escapeshellarg', $files ) );

		$run = \WP_CLI::runcommand(
			"zaplane recipe exec-batch {$paths}{$extra}",
			[
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			]
		);

		$results = RecipeResult::all_from_wire( (string) ( $run->stdout ?? '' ) );

		$out = [];
		foreach ( $files as $i => $file ) {
			if ( isset( $results[ $i ] ) ) {
				$out[ $i ] = $results[ $i ];
				continue;
			}
			$fallback  = new RecipeResult( basename( $file, '.json' ), '' );
			$stderr    = trim( (string) ( $run->stderr ?? '' ) );
			$out[ $i ] = $fallback->abort( $stderr ?: 'Subprocess produced no result (batch may have crashed).' );
		}
		return $out;
	}

	/**
	 * Best-effort count of positional hook args a trigger event reads, by scanning
	 * the `case '<event>':` block of its resolve_trigger() for the highest
	 * `$args[N]` index. Returns 0 when it can't tell (e.g. the case delegates to a
	 * helper) — then you fill `input` by reading the integration yourself.
	 */
	protected function detect_input_arity( string $class, string $event ): int {
		try {
			$file = ( new \ReflectionClass( $class ) )->getFileName();
			$src  = $file ? (string) file_get_contents( $file ) : '';
		} catch ( \Throwable $e ) {
			return 0;
		}

		$pattern = "/case\s+'" . preg_quote( $event, '/' ) . "'\s*:(.*?)(?=\n\s*case\s+'|\n\s*default\s*:|\n\s*\}\s*\n)/s";
		if ( ! preg_match( $pattern, $src, $m ) ) {
			return 0;
		}

		if ( ! preg_match_all( '/\$args\[(\d+)\]/', $m[1], $idx ) || empty( $idx[1] ) ) {
			return 0;
		}

		return max( array_map( 'intval', $idx[1] ) ) + 1;
	}

	protected function exec_recipe( ?string $file, array $assoc_args = [] ): void {
		if ( ! $file || ! is_file( $file ) ) {
			$this->error( 'recipe exec requires a valid recipe file path.' );
			return;
		}

		$this->exec_batch( [ $file ], $assoc_args );
	}

	/**
	 * Internal: fire each recipe in *this* process (deps assumed already loaded)
	 * and print one marked result line per recipe for the parent to parse. Each
	 * recipe is isolated in try/catch so one failure can't drop the rest.
	 */
	protected function exec_batch( array $files, array $assoc_args = [] ): void {
		$e2e  = isset( $assoc_args['e2e'] );
		$keep = isset( $assoc_args['keep-workflow'] );

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			try {
				$recipe = RecipeRunner::load_file( $file );
				$result = $e2e
					? RecipeE2eRunner::run( $recipe, [ 'keep_workflow' => $keep ] )
					: RecipeRunner::fire( $recipe );
			} catch ( \Throwable $e ) {
				$result = new RecipeResult( basename( $file, '.json' ), '' );
				$result->abort( $e->getMessage() );
			}
			$this->line( $result->to_wire() );
		}
	}

	protected function generate_recipe( ?string $integration, array $assoc_args ): void {
		if ( ! $integration ) {
			$this->error( 'Usage: wp zaplane recipe generate <integration> [--event=<e>] [--kind=trigger|action|all]' );
			return;
		}

		$instance = IntegrationLoader::get( $integration );
		if ( ! $instance ) {
			$this->error( "Unknown integration: {$integration}" );
			return;
		}
		$class = get_class( $instance );

		$kind = $assoc_args['kind'] ?? 'trigger';
		if ( ! in_array( $kind, [ 'trigger', 'action', 'all' ], true ) ) {
			$this->error( "--kind must be 'trigger', 'action', or 'all'." );
			return;
		}
		$kinds = 'all' === $kind ? [ 'trigger', 'action' ] : [ $kind ];

		// Build the (kind, event) work list.
		$targets = [];
		foreach ( $kinds as $k ) {
			$events = 'trigger' === $k ? $class::get_triggers() : $class::get_actions();

			if ( isset( $assoc_args['event'] ) ) {
				if ( isset( $events[ $assoc_args['event'] ] ) ) {
					$targets[] = [ $k, $assoc_args['event'] ];
				}
				continue;
			}

			// No --event: generate one recipe per registered event of this kind.
			foreach ( array_keys( $events ) as $event ) {
				$targets[] = [ $k, $event ];
			}
		}

		if ( empty( $targets ) ) {
			$hint = isset( $assoc_args['event'] ) ? "event '{$assoc_args['event']}' not found" : "no {$kind}s declared";
			$this->error( "Nothing to generate for '{$integration}' ({$hint})." );
			return;
		}

		$dir = RecipeRunner::base_dir() . $integration . '/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$created = 0;
		$skipped = 0;
		foreach ( $targets as [$k, $event] ) {
			$status = $this->write_recipe_file( $integration, $class, $k, $event, $dir );
			if ( 'created' === $status ) {
				++$created;
				$this->success( "Created recipes-test/{$integration}/{$event}.json ({$k})" );
			} else {
				++$skipped;
				$this->line( "Skipped {$event}.json — already exists" );
			}
		}

		$this->line();
		$this->success( sprintf( '%d recipe(s) created, %d skipped.', $created, $skipped ) );
		$this->line( 'Fill in setup.factory + node.input/config, then run:' );
		$this->line( "  wp zaplane recipe run {$integration}" );
	}

	/** Write one scaffold recipe file. Returns 'created' or 'exists'. */
	protected function write_recipe_file( string $integration, string $class, string $kind, string $event, string $dir ): string {
		$path = $dir . "{$event}.json";
		if ( file_exists( $path ) ) {
			return 'exists';
		}

		$expect = [ 'not_false' => true ];
		if ( 'trigger' === $kind ) {
			$sample = $class::get_trigger_sample_output( $event );
			if ( ! empty( $sample ) ) {
				$expect['has_keys'] = array_slice( array_keys( $sample ), 0, 3 );
			}
		}

		// Pre-fill `input` with one {{argN}} placeholder per positional hook arg the
		// trigger reads (detected from its resolve_trigger). Replace each token with a
		// literal value or a {{var}} produced by setup.factory.
		$input = [];
		if ( 'trigger' === $kind ) {
			$arity = $this->detect_input_arity( $class, $event );
			for ( $i = 0; $i < $arity; $i++ ) {
				$input[] = "{{arg{$i}}}";
			}
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
				'input'  => $input,
			],
			'expect'           => $expect,
		];

		file_put_contents( $path, wp_json_encode( $recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
		return 'created';
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
