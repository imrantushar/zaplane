<?php

namespace Zaplane\Authoring;

use Zaplane\Framework\Classes\TriggerNodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Warnings for workflows that start from more than one trigger.
 *
 * None of these stop a save or a run. They point at what goes wrong without an
 * error once several triggers share steps: a trigger that can never fire, two
 * triggers that start every run twice, steps reading fields that only one of the
 * triggers provides, and a webhook with no secret that runs the same steps as
 * the site's own triggers.
 *
 * A workflow with a single trigger gets none of them.
 */
class TriggerAdvisor {

	/**
	 * Triggers addressed one at a time, so two identical ones never fire together:
	 * each Catch Webhook trigger has its own URL, and a Manual trigger only runs
	 * when it is picked.
	 */
	private const ADDRESSED_APPS = [ 'webhook', 'manual' ];

	/**
	 * @param array<string,mixed> $graph
	 * @return array<int,array{code:string,where:string,node_id:string,message:string}>
	 */
	public static function warnings( array $graph ): array {
		$triggers = TriggerNodes::all( $graph );

		if ( count( $triggers ) < 2 ) {
			return [];
		}

		return array_merge(
			self::setup( $graph, $triggers ),
			self::duplicates( $graph ),
			self::field_gaps( $graph ),
			self::open_webhooks( $graph )
		);
	}

	/**
	 * Triggers that are empty, lead nowhere, or match their fields to a trigger
	 * that has since been removed.
	 *
	 * @param array<string,mixed>            $graph
	 * @param array<int,array<string,mixed>> $triggers
	 * @return array<int,array<string,string>>
	 */
	private static function setup( array $graph, array $triggers ): array {
		$warnings = [];

		foreach ( $triggers as $trigger ) {
			$id   = (string) $trigger['id'];
			$name = self::name( $graph, $trigger );

			if ( ! TriggerNodes::is_configured( $trigger ) ) {
				$warnings[] = self::warning(
					'trigger_empty',
					$id,
					/* translators: %s: trigger name, e.g. "Trigger 2" */
					sprintf( __( '%s has no app picked yet, so it never starts this workflow.', 'zaplane' ), $name )
				);
				continue;
			}

			if ( empty( TriggerNodes::downstream_ids( $graph, $id ) ) ) {
				$warnings[] = self::warning(
					'trigger_unconnected',
					$id,
					/* translators: %s: trigger name, e.g. "Trigger 2 (WooCommerce · New order)" */
					sprintf( __( '%s is not connected to any step, so nothing happens when it fires.', 'zaplane' ), $name )
				);
			}

			$target = (string) ( $trigger['data']['field_map']['target'] ?? '' );
			if ( '' !== $target && null === TriggerNodes::find( $graph, $target ) ) {
				$warnings[] = self::warning(
					'field_map_target_missing',
					$id,
					/* translators: %s: trigger name, e.g. "Trigger 2 (WooCommerce · New order)" */
					sprintf( __( '%s matches its fields to a trigger that is no longer in this workflow.', 'zaplane' ), $name )
				);
			}
		}//end foreach

		return $warnings;
	}

	/**
	 * Two triggers with the same app, event and settings start a run each for
	 * every event.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array<string,string>>
	 */
	private static function duplicates( array $graph ): array {
		$warnings = [];
		$seen     = [];

		foreach ( TriggerNodes::configured( $graph ) as $trigger ) {
			if ( in_array( strtolower( (string) $trigger['data']['app'] ), self::ADDRESSED_APPS, true ) ) {
				continue;
			}

			$key = self::signature( $trigger );

			if ( isset( $seen[ $key ] ) ) {
				$warnings[] = self::warning(
					'trigger_duplicate',
					(string) $trigger['id'],
					sprintf(
						/* translators: 1: trigger name, 2: the identical trigger's name */
						__( '%1$s is the same as %2$s (same app, event and settings), so every event starts this workflow twice.', 'zaplane' ),
						self::name( $graph, $trigger ),
						self::name( $graph, $seen[ $key ] )
					)
				);
				continue;
			}

			$seen[ $key ] = $trigger;
		}//end foreach

		return $warnings;
	}

	/**
	 * Steps that read one trigger's fields while another trigger also leads to
	 * them and doesn't supply those fields: it matches its fields to a different
	 * trigger, or to none, or leaves out a field the step reads.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array<string,string>>
	 */
	private static function field_gaps( array $graph ): array {
		$warnings    = [];
		$trigger_ids = array_map( static fn( $trigger ) => (string) $trigger['id'], TriggerNodes::all( $graph ) );

		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			if ( ! is_array( $node ) || ! isset( $node['id'] ) || 'trigger' === ( $node['type'] ?? '' ) ) {
				continue;
			}

			$read = array_intersect_key( self::referenced_fields( $node ), array_flip( $trigger_ids ) );
			if ( empty( $read ) ) {
				continue;
			}

			$step     = (string) $node['id'];
			$upstream = TriggerNodes::upstream_ids( $graph, $step );

			foreach ( $read as $source => $paths ) {
				$source = (string) $source;

				// A trigger that cannot reach this step is a wiring problem, which the
				// validator and the dry run already report.
				if ( ! in_array( $source, $upstream, true ) ) {
					continue;
				}

				foreach ( $upstream as $other ) {
					$other_node = TriggerNodes::find( $graph, $other );

					if ( $other === $source || null === $other_node || ! TriggerNodes::is_configured( $other_node ) ) {
						continue;
					}

					$source_name = self::name( $graph, (array) TriggerNodes::find( $graph, $source ) );
					$other_name  = self::name( $graph, $other_node );

					if ( (string) ( $other_node['data']['field_map']['target'] ?? '' ) !== $source ) {
						$warnings[] = self::warning(
							'trigger_field_gap',
							$step,
							sprintf(
								/* translators: 1: step name, 2: trigger whose fields the step reads, 3: that trigger's id, 4: the other trigger */
								__( '%1$s uses fields from %2$s ({{%3$s.…}}), but %4$s also leads here and does not match its fields to it. When %4$s fires, those fields are empty. Match its fields, or use {{trigger.…}} with fields both triggers have.', 'zaplane' ),
								self::step_name( $node ),
								$source_name,
								$source,
								$other_name
							)
						);
						continue;
					}

					$mapped  = array_map( 'strval', array_keys( (array) ( $other_node['data']['field_map']['fields'] ?? [] ) ) );
					$missing = array_values( array_filter( $paths, static fn( $path ) => ! self::covers( $mapped, $path ) ) );

					if ( $missing ) {
						// The field is named by its id ({{3.…}}) and the trigger by its canvas
						// number, which differ once a trigger has been deleted, so the message
						// says which trigger the id belongs to.
						$warnings[] = self::warning(
							'trigger_field_gap',
							$step,
							sprintf(
								/* translators: 1: step name, 2: the unmatched fields, e.g. {{1.first_name}}, 3: trigger whose fields the step reads, 4: the other trigger, 5: the other trigger's number, e.g. "Trigger 3" */
								_n(
									'%1$s reads %2$s from %3$s. The field match on %4$s leaves it out, so it is empty when %5$s fires.',
									'%1$s reads %2$s from %3$s. The field match on %4$s leaves them out, so they are empty when %5$s fires.',
									count( $missing ),
									'zaplane'
								),
								self::step_name( $node ),
								implode( ', ', array_map( static fn( $path ) => '{{' . $source . '.' . $path . '}}', $missing ) ),
								$source_name,
								$other_name,
								/* translators: %d: the trigger's number on the canvas */
								sprintf( __( 'Trigger %d', 'zaplane' ), TriggerNodes::number( $graph, $other ) )
							)
						);
					}
				}//end foreach
			}//end foreach
		}//end foreach

		return $warnings;
	}

	/**
	 * The fields a node reads from other nodes, by node id: {{3.billing.email}}
	 * gives [ '3' => [ 'billing.email' ] ].
	 *
	 * @param array<string,mixed> $node
	 * @return array<int|string,array<int,string>>
	 */
	private static function referenced_fields( array $node ): array {
		$json = wp_json_encode( $node['data']['config'] ?? [] );

		if ( ! $json || ! preg_match_all( '/\{\{\s*(\d+)\.([A-Za-z0-9_.\-\[\]]+)/', $json, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$fields = [];
		foreach ( $matches as $match ) {
			$fields[ (string) $match[1] ][] = rtrim( $match[2], '.' );
		}

		return array_map( static fn( $paths ) => array_values( array_unique( $paths ) ), $fields );
	}

	/**
	 * Whether the matched field names supply a path: the same name, a parent of
	 * it, or a field inside it.
	 *
	 * @param array<int,string> $mapped
	 */
	private static function covers( array $mapped, string $path ): bool {
		foreach ( $mapped as $key ) {
			if ( $key === $path || 0 === strpos( $path, $key . '.' ) || 0 === strpos( $key, $path . '.' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A Catch Webhook trigger with no secret that runs the same steps as a site
	 * trigger lets anyone with its URL run those steps with their own data.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array<string,string>>
	 */
	private static function open_webhooks( array $graph ): array {
		$warnings = [];
		$others   = array_filter(
			TriggerNodes::configured( $graph ),
			static fn( $trigger ) => 'webhook' !== strtolower( (string) $trigger['data']['app'] )
		);

		foreach ( TriggerNodes::of_app( $graph, 'webhook' ) as $hook ) {
			if ( '' !== trim( (string) ( $hook['data']['config']['secret'] ?? '' ) ) ) {
				continue;
			}

			$reach = TriggerNodes::downstream_ids( $graph, $hook['id'] );
			if ( empty( $reach ) ) {
				continue;
			}

			foreach ( $others as $other ) {
				if ( ! array_intersect( $reach, TriggerNodes::downstream_ids( $graph, $other['id'] ) ) ) {
					continue;
				}

				$warnings[] = self::warning(
					'webhook_without_secret',
					(string) $hook['id'],
					sprintf(
						/* translators: 1: webhook trigger name, 2: another trigger's name */
						__( '%1$s has no secret but runs the same steps as %2$s. Anyone who finds its URL can run those steps with data they choose. Add a secret.', 'zaplane' ),
						self::name( $graph, $hook ),
						self::name( $graph, $other )
					)
				);
				break;
			}
		}//end foreach

		return $warnings;
	}

	/**
	 * Node ids read by {{n.…}} tokens anywhere in a node's settings.
	 *
	 * @param array<string,mixed> $node
	 * @return array<int,string>
	 */
	private static function referenced_ids( array $node ): array {
		$json = wp_json_encode( $node['data']['config'] ?? [] );

		if ( ! $json || ! preg_match_all( '/\{\{\s*(\d+)\./', $json, $matches ) ) {
			return [];
		}

		return array_values( array_unique( array_map( 'strval', $matches[1] ) ) );
	}

	/**
	 * What identifies a trigger's firing: its app, event and settings, with keys
	 * sorted so that settings saved in a different order still compare equal.
	 *
	 * @param array<string,mixed> $trigger
	 */
	private static function signature( array $trigger ): string {
		$config = is_array( $trigger['data']['config'] ?? null ) ? $trigger['data']['config'] : [];
		self::sort_keys( $config );

		return strtolower( (string) $trigger['data']['app'] ) . '|' . (string) $trigger['data']['event'] . '|' . (string) wp_json_encode( $config );
	}

	/**
	 * @param array<int|string,mixed> $data
	 */
	private static function sort_keys( array &$data ): void {
		ksort( $data );

		foreach ( $data as &$value ) {
			if ( is_array( $value ) ) {
				self::sort_keys( $value );
			}
		}

		unset( $value );
	}

	/**
	 * "Trigger 2 (WooCommerce · New order)". The number matches the canvas.
	 *
	 * @param array<string,mixed> $graph
	 * @param array<string,mixed> $trigger
	 */
	private static function name( array $graph, array $trigger ): string {
		/* translators: %d: the trigger's number on the canvas */
		$name  = sprintf( __( 'Trigger %d', 'zaplane' ), TriggerNodes::number( $graph, $trigger['id'] ?? '' ) );
		$label = TriggerNodes::label( $trigger );

		return '' === $label ? $name : $name . ' (' . $label . ')';
	}

	/**
	 * "Step 3 (GemCRM · Send email)".
	 *
	 * @param array<string,mixed> $node
	 */
	private static function step_name( array $node ): string {
		$label = trim( (string) ( $node['data']['label'] ?? $node['data']['name'] ?? $node['data']['app'] ?? '' ) );
		$event = trim( str_replace( [ '_', '-' ], ' ', (string) ( $node['data']['event'] ?? '' ) ) );
		$text  = '' === $event ? $label : trim( $label . ' · ' . ucfirst( $event ), ' ·' );

		/* translators: 1: step id, 2: step name */
		return sprintf( __( 'Step %1$s (%2$s)', 'zaplane' ), (string) $node['id'], $text );
	}

	/**
	 * @return array{code:string,where:string,node_id:string,message:string}
	 */
	private static function warning( string $code, string $node_id, string $message ): array {
		return [
			'code'    => $code,
			'where'   => 'node ' . $node_id,
			'node_id' => $node_id,
			'message' => $message,
		];
	}
}
