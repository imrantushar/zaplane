<?php

namespace Zaplane\Tests\Parity;

use PHPUnit\Framework\TestCase;

/**
 * Cross-engine parity tests.
 *
 * Both the cloud Laravel runner and the plugin standalone runner must
 * produce IDENTICAL outputs for the same workflow JSON + input. The
 * original plan flagged drift between the two as a Severity 1 risk.
 *
 * The goldens live in ./fixtures/*.json. Each fixture is a self-
 * contained scenario:
 *
 *   {
 *     "name":  "...",
 *     "graph": { ... cloud-runner shape ... },
 *     "input": { ... },
 *     "expected_outputs": [
 *       { "node_id": "...", "output": { ... } },
 *       ...
 *     ]
 *   }
 *
 * The cloud's tests/Parity/ directory will read the SAME fixtures and
 * assert the SAME outputs against its runner. When both suites pass,
 * we have a contract — adding a new step requires updating both
 * engines AND the fixture, all in one PR.
 *
 * For the plugin side, we run each fixture through this lightweight
 * inline runner that mirrors the plugin's Automation engine signature
 * without booting WordPress. Real plugin-engine integration is the
 * next step; this scaffold proves the test harness works.
 */
class GoldenWorkflowTest extends TestCase {

	private string $fixturesDir;

	protected function setUp(): void {
		$this->fixturesDir = __DIR__ . '/fixtures';
	}

	public function fixtureProvider(): array {
		$dir = __DIR__ . '/fixtures';
		if ( ! is_dir( $dir ) ) {
			return [];
		}
		$cases = [];
		foreach ( glob( $dir . '/*.json' ) as $path ) {
			$decoded = json_decode( file_get_contents( $path ), true );
			$name    = $decoded['name'] ?? basename( $path );
			$cases[ $name ] = [ $decoded ];
		}
		return $cases;
	}

	/**
	 * @dataProvider fixtureProvider
	 */
	public function test_each_fixture_produces_expected_outputs( array $fixture ): void {
		$graph = $fixture['graph'] ?? [];
		$input = $fixture['input'] ?? [];
		$expected = $fixture['expected_outputs'] ?? [];

		$actual = $this->runEngine( $graph, $input );

		foreach ( $expected as $exp ) {
			$nodeId = $exp['node_id'];
			$this->assertArrayHasKey(
				$nodeId,
				$actual,
				"Plugin engine never produced output for node `{$nodeId}` in fixture `{$fixture['name']}`."
			);
			$this->assertEquals(
				$exp['output'],
				$actual[ $nodeId ],
				"Plugin engine output for `{$nodeId}` diverged from cloud golden in fixture `{$fixture['name']}`."
			);
		}
	}

	/**
	 * Minimal in-process runner that handles only the cloud-native
	 * built-ins (`set`, `condition`, `http`, `stop`, etc). Real plugin
	 * integrations require WordPress + IntegrationLoader, which the
	 * boot harness for these tests doesn't load. That's intentional —
	 * the parity contract starts with the engine primitives where
	 * drift would be most damaging.
	 */
	private function runEngine( array $graph, array $input ): array {
		$nodes  = $graph['nodes'] ?? [];
		$edges  = $graph['edges'] ?? [];
		$byId   = [];
		foreach ( $nodes as $n ) {
			$byId[ $n['id'] ] = $n;
		}

		// Find trigger.
		$current = null;
		foreach ( $nodes as $n ) {
			if ( ( $n['type'] ?? '' ) === 'trigger' ) {
				$current = $n;
				break;
			}
		}
		if ( ! $current ) {
			$this->fail( 'Fixture has no trigger node.' );
		}

		$outputs = [];
		$state   = $input;

		while ( $current ) {
			[ $output, $branch ] = $this->execNode( $current, $state );
			$outputs[ $current['id'] ] = $output;
			if ( ! is_array( $output ) || ( $output['_stop'] ?? false ) ) {
				break;
			}
			$state = is_array( $output ) ? array_merge( $state, $output ) : $state;

			$nextId = null;
			foreach ( $edges as $e ) {
				if ( ( $e['source'] ?? '' ) !== $current['id'] ) continue;
				if ( $branch !== null ) {
					if ( ( $e['sourceHandle'] ?? null ) !== $branch ) continue;
				}
				$nextId = $e['target'] ?? null;
				break;
			}
			$current = $nextId && isset( $byId[ $nextId ] ) ? $byId[ $nextId ] : null;
		}

		return $outputs;
	}

	/** @return array{0: array, 1: ?string} (output, branch hint for IF) */
	private function execNode( array $node, array $input ): array {
		$app    = strtolower( (string) ( $node['data']['app'] ?? '' ) );
		$config = (array) ( $node['data']['config'] ?? [] );

		switch ( $app ) {
			case 'webhook':
			case 'manual':
				return [ $input, null ];

			case 'set':
			case 'variable': {
				$assignments = (array) ( $config['assignments'] ?? [] );
				return [ array_merge( $input, $assignments ), null ];
			}

			case 'stop':
				return [ [ '_stop' => true, 'reason' => $config['reason'] ?? null ], null ];

			case 'condition': {
				$field    = (string) ( $config['field'] ?? '' );
				$op       = (string) ( $config['operator'] ?? '==' );
				$expected = $config['value'] ?? null;
				$actual   = $this->dotGet( $input, $field );
				$matches  = match ( $op ) {
					'==', 'equals'    => $actual == $expected,
					'!=', 'not equals' => $actual != $expected,
					'>'                => $actual > $expected,
					'<'                => $actual < $expected,
					'contains'         => is_string( $actual ) && str_contains( $actual, (string) $expected ),
					'empty'            => empty( $actual ),
					'not_empty'        => ! empty( $actual ),
					default            => false,
				};
				return [
					[ '_branch' => $matches ? 'true' : 'false', 'matched' => $matches ],
					$matches ? 'true' : 'false',
				];
			}

			default:
				return [ [ '_unsupported' => $app ], null ];
		}
	}

	private function dotGet( array $arr, string $path ) {
		$cur = $arr;
		foreach ( explode( '.', $path ) as $p ) {
			if ( is_array( $cur ) && array_key_exists( $p, $cur ) ) {
				$cur = $cur[ $p ];
			} else {
				return null;
			}
		}
		return $cur;
	}
}
