<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The trigger nodes of a workflow graph.
 *
 * A workflow can start from more than one trigger. Whichever one fires begins a
 * run at that trigger and follows only its own edges. Everything that has to
 * pick a trigger — a manual run, a webhook call, a schedule tick, a test, the
 * listener — asks this class, so the rule for "which trigger" lives in one place.
 */
class TriggerNodes {

	/** The canvas's name for a trigger slot that has no app picked yet. */
	private const PLACEHOLDER_APP = 'Select an app';

	/**
	 * Every trigger node, in graph order.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( array $graph ): array {
		$triggers = [];

		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			if ( is_array( $node ) && 'trigger' === ( $node['type'] ?? '' ) && isset( $node['id'] ) ) {
				$triggers[] = $node;
			}
		}

		return $triggers;
	}

	/**
	 * Whether an app and an event have been picked for this trigger.
	 *
	 * @param array<string,mixed> $node
	 */
	public static function is_configured( array $node ): bool {
		$app   = (string) ( $node['data']['app'] ?? '' );
		$event = (string) ( $node['data']['event'] ?? '' );

		return '' !== $app && self::PLACEHOLDER_APP !== $app && '' !== $event;
	}

	/**
	 * The triggers that can fire: the ones with an app and event picked.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array<string,mixed>>
	 */
	public static function configured( array $graph ): array {
		return array_values( array_filter( self::all( $graph ), [ self::class, 'is_configured' ] ) );
	}

	/**
	 * A trigger node by id, or null when no trigger has that id.
	 *
	 * @param array<string,mixed> $graph
	 * @param int|string|null     $node_id
	 * @return array<string,mixed>|null
	 */
	public static function find( array $graph, $node_id ): ?array {
		if ( null === $node_id || '' === (string) $node_id ) {
			return null;
		}

		foreach ( self::all( $graph ) as $node ) {
			if ( (string) $node['id'] === (string) $node_id ) {
				return $node;
			}
		}

		return null;
	}

	/**
	 * The trigger a run starts from when the caller did not name one.
	 *
	 * The Manual trigger when the workflow has one, since it exists to be started
	 * by hand. Otherwise the first trigger, which for a workflow with a single
	 * trigger is the one it has always started from.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<string,mixed>|null
	 */
	public static function default_node( array $graph ): ?array {
		$triggers = self::all( $graph );

		foreach ( $triggers as $node ) {
			if ( 'manual' === strtolower( (string) ( $node['data']['app'] ?? '' ) ) ) {
				return $node;
			}
		}

		return $triggers[0] ?? null;
	}

	/**
	 * The named trigger, or the default one when no name is given.
	 *
	 * A name that is not a trigger resolves to null instead of falling back:
	 * starting from a different trigger than the one asked for would run the
	 * wrong steps with the wrong data.
	 *
	 * @param array<string,mixed> $graph
	 * @param int|string|null     $node_id
	 * @return array<string,mixed>|null
	 */
	public static function resolve( array $graph, $node_id = null ): ?array {
		if ( null === $node_id || '' === (string) $node_id ) {
			return self::default_node( $graph );
		}

		return self::find( $graph, $node_id );
	}

	/**
	 * The trigger nodes of one app, in graph order.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array<string,mixed>>
	 */
	public static function of_app( array $graph, string $app ): array {
		$app = strtolower( $app );

		return array_values(
			array_filter(
				self::all( $graph ),
				static fn( $node ) => strtolower( (string) ( $node['data']['app'] ?? '' ) ) === $app
			)
		);
	}

	/**
	 * A trigger's position among the workflow's triggers, counting from 1. This
	 * is the number the canvas shows on it; 0 when the id is not a trigger.
	 *
	 * @param array<string,mixed> $graph
	 * @param int|string          $node_id
	 */
	public static function number( array $graph, $node_id ): int {
		foreach ( self::all( $graph ) as $index => $node ) {
			if ( (string) $node['id'] === (string) $node_id ) {
				return $index + 1;
			}
		}

		return 0;
	}

	/**
	 * The ids of the triggers a node can be reached from.
	 *
	 * @param array<string,mixed> $graph
	 * @param int|string          $node_id
	 * @return array<int,string>
	 */
	public static function upstream_ids( array $graph, $node_id ): array {
		$incoming = [];

		foreach ( (array) ( $graph['edges'] ?? [] ) as $edge ) {
			if ( is_array( $edge ) && isset( $edge['source'], $edge['target'] ) ) {
				$incoming[ (string) $edge['target'] ][] = (string) $edge['source'];
			}
		}

		$trigger_ids = array_map( static fn( $node ) => (string) $node['id'], self::all( $graph ) );

		return self::walk( $incoming, (string) $node_id, $trigger_ids );
	}

	/**
	 * The ids of every node reachable from a node, following edges forward.
	 *
	 * @param array<string,mixed> $graph
	 * @param int|string          $node_id
	 * @return array<int,string>
	 */
	public static function downstream_ids( array $graph, $node_id ): array {
		$outgoing = [];

		foreach ( (array) ( $graph['edges'] ?? [] ) as $edge ) {
			if ( is_array( $edge ) && isset( $edge['source'], $edge['target'] ) ) {
				$outgoing[ (string) $edge['source'] ][] = (string) $edge['target'];
			}
		}

		return self::walk( $outgoing, (string) $node_id, null );
	}

	/**
	 * Apply a trigger's field map to the payload it received.
	 *
	 * A trigger that shares steps with another trigger can match its fields to
	 * that trigger's names, so a step written against the other trigger, such as
	 * {{1.email}}, still gets a value when this one fires. Each mapping is read
	 * against this trigger's own payload, as {{field}} or {{<this id>.field}},
	 * and written under the matched name on top of the payload. Dotted names
	 * nest. The payload's own fields are left as they were.
	 *
	 * @param array<string,mixed> $node
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public static function apply_field_map( array $node, array $payload ): array {
		$fields = $node['data']['field_map']['fields'] ?? null;

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $payload;
		}

		$scope = $payload;
		if ( isset( $node['id'] ) && '' !== (string) $node['id'] ) {
			$scope[ (string) $node['id'] ] = $payload;
		}

		$mapped = $payload;

		foreach ( $fields as $name => $expression ) {
			$name = trim( (string) $name );

			// `items[].name` addresses every element of a list. There is no single
			// place to write that, so a name like it cannot be mapped.
			if ( '' === $name || str_contains( $name, '[' ) || ! is_scalar( $expression ) || '' === trim( (string) $expression ) ) {
				continue;
			}

			$mapped = self::set_path( $mapped, $name, Expression::evaluate( (string) $expression, $scope ) );
		}

		return $mapped;
	}

	/**
	 * The run's data as its steps read it, given the trigger that started it.
	 *
	 * `trigger` holds that trigger's output, so a step can read whichever trigger
	 * fired without naming one. When that trigger matches its fields to another
	 * trigger, the other trigger's number answers with the same output: it did
	 * not run, so it would otherwise read as empty.
	 *
	 * @param array<int|string,mixed> $context       Node outputs keyed by node id.
	 * @param array<string,mixed>     $graph
	 * @param int|string|null         $start_node_id The trigger the run started from.
	 * @return array<int|string,mixed>
	 */
	public static function with_aliases( array $context, array $graph, $start_node_id ): array {
		if ( null === $start_node_id || '' === (string) $start_node_id ) {
			return $context;
		}

		$start = (string) $start_node_id;

		if ( ! array_key_exists( $start, $context ) ) {
			return $context;
		}

		$output             = $context[ $start ];
		$context['trigger'] = $output;

		$node   = self::find( $graph, $start );
		$target = (string) ( $node['data']['field_map']['target'] ?? '' );

		if ( '' !== $target && $target !== $start && null !== self::find( $graph, $target ) && ! array_key_exists( $target, $context ) ) {
			$context[ $target ] = $output;
		}

		return $context;
	}

	/**
	 * A readable name for a trigger, its app and event: "WooCommerce · Order
	 * status completed". Empty for a trigger with no app picked.
	 *
	 * @param array<string,mixed> $node
	 */
	public static function label( array $node ): string {
		$app   = trim( (string) ( $node['data']['name'] ?? $node['data']['label'] ?? $node['data']['app'] ?? '' ) );
		$event = trim( str_replace( [ '_', '-' ], ' ', (string) ( $node['data']['event'] ?? '' ) ) );

		if ( '' === $app || self::PLACEHOLDER_APP === $app ) {
			return '';
		}

		return '' === $event ? $app : $app . ' · ' . ucfirst( $event );
	}

	/**
	 * Breadth-first walk over an adjacency list. Returns the ids it reaches, or
	 * only those of them that are in $only.
	 *
	 * @param array<int|string,array<int,string>> $adjacent
	 * @param array<int,string>|null              $only
	 * @return array<int,string>
	 */
	private static function walk( array $adjacent, string $start, ?array $only ): array {
		$seen  = [ $start => true ];
		$queue = [ $start ];
		$found = [];

		while ( $queue ) {
			$current = (string) array_shift( $queue );

			foreach ( $adjacent[ $current ] ?? [] as $next ) {
				if ( isset( $seen[ $next ] ) ) {
					continue;
				}

				$seen[ $next ] = true;
				$queue[]       = $next;

				if ( null === $only || in_array( $next, $only, true ) ) {
					$found[] = $next;
				}
			}
		}

		return $found;
	}

	/**
	 * Write a value at a dotted path, creating the arrays along the way.
	 *
	 * @param array<int|string,mixed> $data
	 * @param mixed                   $value
	 * @return array<int|string,mixed>
	 */
	private static function set_path( array $data, string $path, $value ): array {
		$segments = array_values( array_filter( explode( '.', $path ), static fn( $segment ) => '' !== $segment ) );

		if ( empty( $segments ) ) {
			return $data;
		}

		$cursor = &$data;
		$last   = count( $segments ) - 1;

		foreach ( $segments as $index => $segment ) {
			if ( $index === $last ) {
				$cursor[ $segment ] = $value;
				break;
			}

			if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
				$cursor[ $segment ] = [];
			}

			$cursor = &$cursor[ $segment ];
		}

		unset( $cursor );

		return $data;
	}
}
