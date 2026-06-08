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

	/** Fatal error that aborted the run before assertions, if any. */
	public ?string $error = null;

	public function __construct( string $name, string $integration ) {
		$this->name        = $name;
		$this->integration = $integration;
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
		$this->passed = empty( $this->failures );
		return $this;
	}

	public function status_label(): string {
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
			]
		);
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

		$result           = new self( $data['name'] ?? 'unnamed', $data['integration'] ?? '' );
		$result->passed   = ! empty( $data['passed'] );
		$result->failures = (array) ( $data['failures'] ?? [] );
		$result->error    = $data['error'] ?? null;
		return $result;
	}
}
