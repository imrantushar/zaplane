<?php
/**
 * Value object describing the outcome of running one recipe.
 *
 * @package Zaplane
 */

namespace Zaplane\Testing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeResult {

	public string $name;
	public string $integration;
	public bool $passed = false;

	/** @var string[] Human-readable failure reasons (empty when passed). */
	public array $failures = [];

	/** The raw value returned by resolve_trigger()/execute_node(). */
	public $output = null;

	/** @var string[] Plugin basenames this run activated (so callers can report/restore). */
	public array $activated_plugins = [];

	/** @var string[] Human-readable execution log (E2E mode: workflow/run/node-run trace). */
	public array $log_lines = [];

	/** Fatal error that aborted the run before assertions, if any. */
	public ?string $error = null;

	/** Set when the recipe opted out of running (e.g. an unimplemented stub factory). */
	public bool $skipped = false;
	public ?string $skip_reason = null;

	public function __construct( string $name, string $integration ) {
		$this->name        = $name;
		$this->integration = $integration;
	}

	/** Mark the recipe as skipped (not run) — neither pass nor fail. */
	public function skip( string $reason ): self {
		$this->skipped     = true;
		$this->skip_reason = $reason;
		return $this;
	}

	public function fail( string $reason ): void {
		$this->failures[] = $reason;
		$this->passed     = false;
	}

	/** Mark the run as aborted by a fatal/setup error (counts as a failure). */
	public function abort( string $reason ): self {
		$this->error  = $reason;
		$this->passed = false;
		$this->fail( $reason );
		return $this;
	}

	/** Call once all assertions have run; passes only when no failures recorded. */
	public function finalize(): self {
		if ( $this->skipped ) {
			return $this;
		}
		$this->passed = empty( $this->failures );
		return $this;
	}

	public function status_label(): string {
		if ( $this->skipped ) {
			return 'SKIP';
		}
		return $this->passed ? 'PASS' : 'FAIL';
	}

	public function failure_summary(): string {
		return empty( $this->failures ) ? '' : implode( '; ', $this->failures );
	}

	/** Marker prefix used to transport a result across a WP-CLI subprocess boundary. */
	public const WIRE_PREFIX = 'ZAPLANE_RECIPE_RESULT:';

	/** Serialize the fields the parent process needs into a single marked line. */
	public function to_wire(): string {
		return self::WIRE_PREFIX . wp_json_encode(
			[
				'name'        => $this->name,
				'integration' => $this->integration,
				'passed'      => $this->passed,
				'failures'    => $this->failures,
				'error'       => $this->error,
				'log_lines'   => $this->log_lines,
				'skipped'     => $this->skipped,
				'skip_reason' => $this->skip_reason,
			]
		);
	}

	/** Rebuild every result from a batch subprocess's stdout, in output order. @return self[] */
	public static function all_from_wire( string $stdout ): array {
		$out = [];
		foreach ( explode( "\n", $stdout ) as $line ) {
			if ( false === strpos( $line, self::WIRE_PREFIX ) ) {
				continue;
			}
			$result = self::from_wire( $line );
			if ( $result ) {
				$out[] = $result;
			}
		}
		return $out;
	}

	/** Rebuild a result from a subprocess's marked stdout line. Null if absent. */
	public static function from_wire( string $stdout ): ?self {
		$pos = strrpos( $stdout, self::WIRE_PREFIX );
		if ( false === $pos ) {
			return null;
		}

		$line = substr( $stdout, $pos + strlen( self::WIRE_PREFIX ) );
		$line = trim( strtok( $line, "\n" ) );
		$data = json_decode( $line, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$result              = new self( $data['name'] ?? 'unnamed', $data['integration'] ?? '' );
		$result->passed      = ! empty( $data['passed'] );
		$result->failures    = (array) ( $data['failures'] ?? [] );
		$result->error       = $data['error'] ?? null;
		$result->log_lines   = (array) ( $data['log_lines'] ?? [] );
		$result->skipped     = ! empty( $data['skipped'] );
		$result->skip_reason = $data['skip_reason'] ?? null;
		return $result;
	}
}
