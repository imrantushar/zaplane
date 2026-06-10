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
				if ( isset( $assoc_args['all'] ) && empty( $args[1] ) ) {
					$this->generate_all( $assoc_args );
				} else {
					$this->generate_recipe( $args[1] ?? null, $assoc_args );
				}
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
			case 'clean':
				$this->clean_e2e( $assoc_args );
				break;
			default:
				$this->error( "Unknown subcommand '{$sub}'. Use: run | generate | list | clean" );
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

		// E2E inserts temp workflows; clear any active leftovers from a crashed run
		// before starting so they don't pollute hook matching.
		if ( $e2e ) {
			$purged = RecipeE2eRunner::purge_leftovers();
			if ( $purged > 0 ) {
				$this->warning( sprintf( 'Cleared %d leftover [recipe-e2e] workflow(s) from a previous run.', $purged ) );
			}
		}

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
				$rows[] = [ $name, $slug, 'SKIP', 'not filled in — set node.input (and setup.factory/action if needed)' ];
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

				$is_skip = $result && $result->skipped;
				if ( $is_skip ) {
					++$skipped;
				} elseif ( $result && $result->passed ) {
					++$passed;
				} else {
					++$failed;
				}

				$notes = 'no result';
				if ( $is_skip ) {
					$notes = $result->skip_reason ?? 'skipped';
				} elseif ( $result && $result->passed ) {
					$notes = $note;
				} elseif ( $result ) {
					$notes = $result->failure_summary();
				}

				$rows[] = [
					$result ? $result->name : basename( $file, '.json' ),
					$result ? $result->integration : '—',
					$result ? $result->status_label() : 'FAIL',
					$notes,
				];

				if ( $fail_fast && ! $is_skip && ( ! $result || ! $result->passed ) ) {
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
	 * Run a plugin-group of recipes in child WP-CLI processes and return results
	 * aligned positionally to $files.
	 *
	 * Direct mode batches all recipes into ONE subprocess (the plugin boots once —
	 * fast, and only resolve_trigger/execute_node runs so there's no shared-state
	 * risk). E2E mode fires REAL hooks that run the plugin's own listeners and can
	 * fatal/mutate global state, so each recipe gets its OWN subprocess for clean
	 * isolation.
	 *
	 * @return array<int, ?RecipeResult>
	 */
	protected function exec_batch_subprocess( array $files, array $flags = [] ): array {
		$extra  = ! empty( $flags['e2e'] ) ? ' --e2e' : '';
		$extra .= ! empty( $flags['keep_workflow'] ) ? ' --keep-workflow' : '';

		// E2E: one subprocess per recipe (isolation).
		if ( ! empty( $flags['e2e'] ) ) {
			$out = [];
			foreach ( $files as $i => $file ) {
				$out[ $i ] = $this->run_subprocess( [ $file ], $extra )[0]
					?? ( new RecipeResult( basename( $file, '.json' ), '' ) )->abort( 'Subprocess produced no result.' );
			}
			return $out;
		}

		// Direct: one subprocess for the whole group.
		$results = $this->run_subprocess( $files, $extra );
		$out     = [];
		foreach ( $files as $i => $file ) {
			$out[ $i ] = $results[ $i ]
				?? ( new RecipeResult( basename( $file, '.json' ), '' ) )->abort( 'Subprocess produced no result (batch may have crashed).' );
		}
		return $out;
	}

	/** Launch one child process for the given files; return parsed results in order. @return array<int,?RecipeResult> */
	protected function run_subprocess( array $files, string $extra ): array {
		$paths = implode( ' ', array_map( 'escapeshellarg', $files ) );
		$run   = \WP_CLI::runcommand(
			"zaplane recipe exec-batch {$paths}{$extra}",
			[
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			]
		);
		return RecipeResult::all_from_wire( (string) ( $run->stdout ?? '' ) );
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

	/**
	 * `recipe generate --all` — sync the runnable suite from the integrations:
	 * for every integration that declares get_seedable_triggers(), generate one
	 * bare recipe per seedable trigger. This is the "before release" step — the
	 * recipes are a generated artifact, the integrations are the source of truth.
	 */
	protected function generate_all( array $assoc_args ): void {
		$created = 0;
		$skipped = 0;
		$rows    = [];

		foreach ( IntegrationLoader::getAllSlugs() as $slug ) {
			$instance = IntegrationLoader::get( $slug );
			if ( ! $instance ) {
				continue;
			}
			$class    = get_class( $instance );
			$seedable = array_values( array_intersect( array_keys( (array) $class::get_triggers() ), (array) $class::get_seedable_triggers() ) );
			if ( empty( $seedable ) ) {
				continue;
			}

			$dir = RecipeRunner::base_dir() . $slug . '/';
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$made = 0;
			foreach ( $seedable as $event ) {
				if ( 'created' === $this->write_recipe_file( $slug, $class, 'trigger', $event, $dir ) ) {
					++$created;
					++$made;
				} else {
					++$skipped;
				}
			}
			$rows[] = [ $slug, (string) count( $seedable ), (string) $made ];
		}

		if ( empty( $rows ) ) {
			$this->warning( 'No integrations declare get_seedable_triggers() yet — nothing to generate.' );
			return;
		}

		$this->table( [ 'Integration', 'Seedable triggers', 'Created' ], $rows );
		$this->line();
		$this->success( sprintf( '%d recipe(s) created, %d already existed.', $created, $skipped ) );
		$this->line( 'Run the suite: wp zaplane recipe run   (add --e2e for the full engine)' );
	}

	protected function generate_recipe( ?string $integration, array $assoc_args ): void {
		if ( ! $integration ) {
			$this->error( 'Usage: wp zaplane recipe generate <integration> [--event=<e>] [--kind=trigger|action|all] | generate --all' );
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

			// No --event: by default generate only the triggers the integration can
			// self-seed (they run green out of the box). Pass --all-events to also
			// scaffold the non-seedable ones (as stubs). Actions are always all.
			$keys = array_keys( $events );
			if ( 'trigger' === $k && ! isset( $assoc_args['all-events'] ) ) {
				$seedable = (array) $class::get_seedable_triggers();
				if ( ! empty( $seedable ) ) {
					$keys = array_values( array_intersect( $keys, $seedable ) );
				}
			}
			foreach ( $keys as $event ) {
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

		// If the integration can self-seed this trigger (get_seedable_triggers), the
		// recipe needs NOTHING — leave input empty and the runner asks the integration
		// for real data at run time. Otherwise pre-fill {{argN}} placeholders and
		// scaffold a stub factory so you only write the data-creation body.
		$input   = [];
		$factory = '';
		if ( 'trigger' === $kind && ! in_array( $event, (array) $class::get_seedable_triggers(), true ) ) {
			$arity = $this->detect_input_arity( $class, $event );
			for ( $i = 0; $i < $arity; $i++ ) {
				$input[] = "{{arg{$i}}}";
			}
			if ( $arity > 0 ) {
				$factory = "create_{$integration}_{$event}";
				$this->scaffold_factory_stub( $dir, $integration, $factory, $arity );
			}
		}

		$recipe = [
			'name'             => "{$integration}-{$event}",
			'integration'      => $integration,
			'required_plugins' => array_values( (array) $class::get_required_plugins() ),
			'setup'            => [ 'factory' => $factory, 'args' => new \stdClass() ],
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

	/**
	 * Append a ready-to-fill stub factory to recipes-test/<integration>/factories.php
	 * (creating the file if needed). The stub throws RecipeSkip so the recipe SKIPs
	 * with a clear "implement me" message until you fill in the data-creation body —
	 * which is the only part you write by hand.
	 */
	protected function scaffold_factory_stub( string $dir, string $integration, string $factory, int $arity ): void {
		$path = $dir . 'factories.php';

		if ( file_exists( $path ) && false !== strpos( (string) file_get_contents( $path ), "'{$factory}'" ) ) {
			return; // already scaffolded.
		}

		if ( ! file_exists( $path ) ) {
			$header  = "<?php\n";
			$header .= "/**\n * Recipe data factories for the {$integration} integration.\n";
			$header .= " * Auto-scaffolded by `wp zaplane recipe generate`. Fill in each stub's body.\n */\n\n";
			$header .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n";
			file_put_contents( $path, $header );
		}

		$returns = [];
		for ( $i = 0; $i < $arity; $i++ ) {
			$returns[] = "\t\t\t\t'arg{$i}' => null, // TODO: real value the trigger expects at hook arg {$i}";
		}
		$returns = implode( "\n", $returns );

		$block  = "\nadd_filter(\n\t'zaplane_recipe_factories',\n\tfunction ( array \$factories ) {\n";
		$block .= "\t\t\$factories['{$factory}'] = function ( array \$args ) {\n";
		$block .= "\t\t\t// TODO: create the real data this trigger needs (posts, users, orders, …),\n";
		$block .= "\t\t\t// then delete the throw below and return the values for {{arg0}}..{{argN}}.\n";
		$block .= "\t\t\tthrow new \\Zaplane\\Testing\\RecipeSkip( \"Stub factory '{$factory}' — implement it in recipes-test/{$integration}/factories.php\" );\n\n";
		$block .= "\t\t\t// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable\n";
		$block .= "\t\t\treturn [\n{$returns}\n\t\t\t];\n";
		$block .= "\t\t};\n\t\treturn \$factories;\n\t}\n);\n";

		file_put_contents( $path, $block, FILE_APPEND );
	}

	/** `recipe clean [--all]` — remove leftover [recipe-e2e] temp workflows. */
	protected function clean_e2e( array $assoc_args ): void {
		$include_kept = isset( $assoc_args['all'] );
		$removed      = RecipeE2eRunner::purge_leftovers( $include_kept );

		if ( 0 === $removed ) {
			$this->success( 'No leftover [recipe-e2e] workflows.' );
			return;
		}
		$scope = $include_kept ? '' : ' active';
		$this->success( sprintf( 'Removed %d%s [recipe-e2e] workflow(s).', $removed, $scope ) );
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
