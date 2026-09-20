<?php

namespace Zaplane\Tests\Integrations;

/**
 * Base class for trigger-focused integration tests.
 *
 * Extends IntegrationTestCase with:
 *
 * 1. Scenario-based testing via getTriggerScenarios() — define named happy/sad
 *    path cases declaratively; test_trigger_scenarios() runs them all.
 *
 * 2. Coverage check — test_all_registered_triggers_have_coverage() fails when
 *    a trigger has neither a scenario nor a getTriggerTests() entry.
 *
 * 3. Assertion helpers:
 *    - assertTriggerResolvesPayload()  — fires the trigger; returns payload.
 *    - assertTriggerFiltersOut()       — asserts the trigger returns false.
 *    - assertPayloadHasKeys()          — checks a list of keys are present.
 *    - assertPayloadContains()         — checks exact key-value pairs.
 *
 * Usage example:
 *
 *   class GemcrmTriggerTest extends TriggerTestCase {
 *
 *       protected function getIntegrationClass(): string {
 *           return Gemcrm::class;
 *       }
 *
 *       protected function getTriggerScenarios(): array {
 *           return [
 *               'contact created fires with full data' => [
 *                   'event'       => 'contact_created',
 *                   'args'        => [1, ['email' => 'a@b.com', 'first_name' => 'Ada']],
 *                   'expects'     => 'payload',
 *                   'payload_has' => ['contact_id', 'email'],
 *               ],
 *               'contact created without id filters out' => [
 *                   'event'   => 'contact_created',
 *                   'args'    => [null, []],
 *                   'expects' => 'filtered',
 *               ],
 *           ];
 *       }
 *   }
 */
abstract class TriggerTestCase extends IntegrationTestCase {

	/**
	 * Define named trigger test scenarios.
	 *
	 * Each scenario is an associative array with:
	 *   - event       (string)   — the trigger key (required)
	 *   - args        (array)    — hook args passed to resolve_trigger(); defaults
	 *                              to getDefaultTriggerArgs( $event ) when omitted
	 *   - config      (array)    — optional trigger node config (filters etc.)
	 *   - expects     (string)   — 'payload' (default) or 'filtered'
	 *   - payload_has (string[]) — keys that must be present in the payload
	 *
	 * @return array<string, array>
	 */
	protected function getTriggerScenarios(): array {
		return [];
	}

	// ========== AUTOMATIC TESTS ==========

	/**
	 * @test
	 */
	public function trigger_scenarios_pass(): void {
		$scenarios = $this->getTriggerScenarios();

		if ( empty( $scenarios ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $scenarios as $name => $scenario ) {
			$event      = $scenario['event'];
			$args       = $scenario['args'] ?? $this->getDefaultTriggerArgs( $event );
			$config     = $scenario['config'] ?? [];
			$expects    = $scenario['expects'] ?? 'payload';
			$payloadHas = $scenario['payload_has'] ?? [];

			$class  = $this->getIntegrationClass();
			$node   = $this->makeTriggerNode( $event, $config );
			$result = $class::resolve_trigger( $node['data'], $args );

			if ( 'filtered' === $expects ) {
				$this->assertFalse(
					$result,
					"Scenario '{$name}': expected trigger '{$event}' to filter out (return false), got: "
					. ( is_array( $result ) ? json_encode( $result ) : gettype( $result ) )
				);
			} else {
				$this->assertIsArray(
					$result,
					"Scenario '{$name}': expected trigger '{$event}' to fire (return array), got: "
					. gettype( $result )
				);
				$this->assertNotEmpty(
					$result,
					"Scenario '{$name}': trigger '{$event}' payload must not be empty"
				);

				foreach ( $payloadHas as $key ) {
					$this->assertArrayHasKey(
						$key,
						$result,
						"Scenario '{$name}': payload missing expected key '{$key}'"
					);
				}
			}
		}//end foreach
	}

	/**
	 * @test
	 */
	public function all_registered_triggers_have_coverage(): void {
		$class    = $this->getIntegrationClass();
		$triggers = array_keys( $class::get_triggers() );

		if ( empty( $triggers ) ) {
			$this->assertTrue( true );
			return;
		}

		// Collect covered events from both getTriggerScenarios and getTriggerTests.
		$covered = [];
		foreach ( $this->getTriggerScenarios() as $scenario ) {
			if ( isset( $scenario['event'] ) ) {
				$covered[ $scenario['event'] ] = true;
			}
		}
		foreach ( $this->getTriggerTests() as $key => $value ) {
			$event           = is_int( $key ) ? $value : $key;
			$covered[$event] = true;
		}

		$uncovered = array_diff( $triggers, array_keys( $covered ) );

		$this->assertEmpty(
			$uncovered,
			'The following triggers have no test coverage: ' . implode( ', ', $uncovered )
		);
	}

	// ========== ASSERTION HELPERS ==========

	/**
	 * Assert that the trigger resolves to a non-empty payload.
	 *
	 * Returns the resolved payload for further assertions.
	 *
	 * @param string $event  Trigger key.
	 * @param array  $args   Hook args to pass to resolve_trigger().
	 * @param array  $config Optional trigger node config.
	 * @return array         The resolved payload.
	 */
	protected function assertTriggerResolvesPayload( string $event, array $args, array $config = [] ): array {
		$class  = $this->getIntegrationClass();
		$node   = $this->makeTriggerNode( $event, $config );
		$result = $class::resolve_trigger( $node['data'], $args );

		$this->assertIsArray(
			$result,
			"Trigger '{$event}' expected to return payload (array), got: " . gettype( $result )
		);
		$this->assertNotEmpty( $result, "Trigger '{$event}' payload must not be empty" );

		return $result;
	}

	/**
	 * Assert that the trigger filters out (returns false).
	 *
	 * @param string $event  Trigger key.
	 * @param array  $args   Hook args to pass to resolve_trigger().
	 * @param array  $config Optional trigger node config.
	 */
	protected function assertTriggerFiltersOut( string $event, array $args, array $config = [] ): void {
		$class  = $this->getIntegrationClass();
		$node   = $this->makeTriggerNode( $event, $config );
		$result = $class::resolve_trigger( $node['data'], $args );

		$this->assertFalse(
			$result,
			"Trigger '{$event}' expected to filter out (return false), got: "
			. ( is_array( $result ) ? json_encode( $result ) : gettype( $result ) )
		);
	}

	/**
	 * Assert that all given keys are present in a payload.
	 *
	 * @param array    $payload Resolved trigger payload.
	 * @param string[] $keys    Keys that must exist.
	 */
	protected function assertPayloadHasKeys( array $payload, array $keys ): void {
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$payload,
				"Payload missing expected key '{$key}'"
			);
		}
	}

	/**
	 * Assert that a payload contains specific key-value pairs.
	 *
	 * @param array $payload  Resolved trigger payload.
	 * @param array $expected Map of key => expected value.
	 */
	protected function assertPayloadContains( array $payload, array $expected ): void {
		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $payload, "Payload missing key '{$key}'" );
			$this->assertSame(
				$value,
				$payload[ $key ],
				"Payload['{$key}'] does not match expected value"
			);
		}
	}
}
