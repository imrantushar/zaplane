<?php

namespace Zaplane\Authoring;

use Zaplane\Framework\Classes\Expression;
use Zaplane\Framework\Classes\GlobalContext;
use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walks a graph the way a run would, resolving every {{...}} against sample
 * data — and executing nothing.
 *
 * validate_graph answers "are these apps and fields real". It cannot answer the
 * question that actually breaks authored workflows: does {{2.post_title}} point
 * at anything? An author only finds that out by activating the workflow and
 * letting it send the mail, which is a poor way to discover a typo.
 *
 * So this resolves instead of running. Each node's output comes from the
 * integration's declared sample — get_trigger_sample_output() for the trigger,
 * get_action_sample_output() for an action — and never from performing the
 * action. A node whose integration declares no sample leaves its output
 * unknown, and every expression downstream that leaned on it is reported as
 * unverifiable rather than quietly resolving to an empty string.
 *
 * Live execution is deliberately not offered here. A test that can send mail is
 * not a test, and the scope that permits side effects is `run`.
 */
class GraphTester {

	/**
	 * Token roots that belong to an integration's own per-recipient merge
	 * engine. Expression leaves them literal, so they are not our references to
	 * resolve and never a warning.
	 */
	private const RESERVED_ROOTS = [ 'contact', 'unsubscribe_link', 'update_preferences_link' ];

	/**
	 * Resolve a graph against sample data.
	 *
	 * @param array<string,mixed> $graph        Raw or normalized graph.
	 * @param array<string,mixed> $trigger_data Overrides the trigger's declared sample.
	 * @return array<string,mixed>
	 */
	public static function test( array $graph, array $trigger_data = [] ): array {
		$graph = WorkflowAuthor::normalize( $graph );
		$nodes = (array) ( $graph['nodes'] ?? [] );
		$edges = (array) ( $graph['edges'] ?? [] );

		$by_id = [];
		foreach ( $nodes as $node ) {
			$by_id[ (string) ( $node['id'] ?? '' ) ] = $node;
		}
		unset( $by_id[''] );

		if ( empty( $by_id ) ) {
			throw new \InvalidArgumentException( 'The graph has no nodes to test.' );
		}

		$order      = self::execution_order( $by_id, $edges );
		$position   = array_flip( $order );
		$referenced = self::referenced_nodes( $by_id );

		$context  = self::global_context( $graph );
		$known    = [];
		$results  = [];
		$warnings = [];

		foreach ( $order as $id ) {
			$node        = $by_id[ $id ];
			$type        = (string) ( $node['type'] ?? 'action' );
			$app         = strtolower( (string) ( $node['data']['app'] ?? '' ) );
			$event       = (string) ( $node['data']['event'] ?? '' );
			$integration = '' !== $app ? IntegrationLoader::get( $app ) : null;

			$entry = [
				'id'    => $id,
				'type'  => $type,
				'app'   => $app,
				'event' => $event,
				'label' => (string) ( $node['data']['label'] ?? '' ),
			];

			if ( 'trigger' === $type ) {
				[ $output, $source ] = self::trigger_output( $integration, $event, $trigger_data );
			} else {
				[ $resolved, $field_warnings ] = self::resolve_config(
					(array) ( $node['data']['config'] ?? [] ),
					$context,
					$id,
					$position,
					$known,
					$by_id
				);

				$entry['config'] = $resolved;
				$warnings        = array_merge( $warnings, $field_warnings );

				[ $output, $source ] = self::action_output( $integration, $event );
			}

			$entry['output_source'] = $source;
			$entry['output']        = $output;

			if ( is_array( $output ) ) {
				$context[ $id ] = $output;
				$known[ $id ]   = true;
			} elseif ( isset( $referenced[ $id ] ) ) {
				$warnings[] = [
					'node'     => $id,
					'severity' => 'warning',
					'code'     => 'no_sample_output',
					'message'  => sprintf(
						'%s has no declared sample for "%s", so its output is unknown here. Anything referencing {{%s.…}} cannot be checked. Adding get_action_sample_output() to the %s integration would close this.',
						$entry['label'] ?: $app,
						$event,
						$id,
						$app
					),
				];
			}

			$results[] = $entry;
		}

		$errors = array_values(
			array_filter( $warnings, fn( $w ) => 'error' === $w['severity'] )
		);

		return [
			'ok'       => empty( $errors ),
			'nodes'    => $results,
			'warnings' => array_values( $warnings ),
			'summary'  => self::summarize( $results, $warnings ),
		];
	}

	/**
	 * Trigger output: the caller's data if given, else the integration's sample.
	 *
	 * @return array{0:array<string,mixed>|null,1:string}
	 */
	private static function trigger_output( ?object $integration, string $event, array $trigger_data ): array {
		if ( ! empty( $trigger_data ) ) {
			return [ $trigger_data, 'supplied' ];
		}

		if ( $integration && method_exists( $integration, 'get_trigger_sample_output' ) ) {
			$sample = $integration::get_trigger_sample_output( $event );
			if ( ! empty( $sample ) ) {
				return [ $sample, 'sample' ];
			}
		}

		return [ null, 'unknown' ];
	}

	/**
	 * Action output, from the declared sample only — never by executing.
	 *
	 * @return array{0:array<string,mixed>|null,1:string}
	 */
	private static function action_output( ?object $integration, string $event ): array {
		if ( $integration && method_exists( $integration, 'get_action_sample_output' ) ) {
			$sample = $integration::get_action_sample_output( $event );
			if ( ! empty( $sample ) ) {
				return [ $sample, 'sample' ];
			}
		}

		return [ null, 'unknown' ];
	}

	/**
	 * Resolve one node's config, reporting each expression field as raw vs
	 * resolved. Literal fields are passed through untouched so the report stays
	 * readable.
	 *
	 * @param array<string,mixed>  $config
	 * @param array<string,mixed>  $context
	 * @param array<string,int>    $position
	 * @param array<string,bool>   $known
	 * @param array<string,mixed>  $by_id
	 * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}
	 */
	private static function resolve_config( array $config, array $context, string $node_id, array $position, array $known, array $by_id ): array {
		$out      = [];
		$warnings = [];

		foreach ( $config as $key => $value ) {
			if ( is_array( $value ) ) {
				[ $nested, $nested_warnings ] = self::resolve_config( $value, $context, $node_id, $position, $known, $by_id );
				$out[ $key ]                  = $nested;
				$warnings                     = array_merge( $warnings, $nested_warnings );
				continue;
			}

			if ( ! is_string( $value ) || ! str_contains( $value, '{{' ) ) {
				$out[ $key ] = $value;
				continue;
			}

			$resolved    = self::resolve_quietly( $value, $context );
			$out[ $key ] = [
				'raw'      => $value,
				'resolved' => $resolved,
			];

			$warnings = array_merge(
				$warnings,
				self::check_references( $value, $node_id, $key, $position, $known, $by_id ),
				self::check_empty_tokens( $value, $context, $node_id, (string) $key, $known )
			);

			if ( null === $resolved || '' === $resolved ) {
				$warnings[] = [
					'node'     => $node_id,
					'field'    => (string) $key,
					'severity' => 'warning',
					'code'     => 'empty_value',
					'message'  => sprintf(
						'"%s" resolves to nothing. If that field is required the node will fail, and inside copy it renders as a gap — a greeting becomes "Hi ,".',
						$key
					),
				];
			}
		}

		return [ $out, $warnings ];
	}

	/**
	 * Find tokens that resolve to nothing inside a field that is not itself
	 * empty.
	 *
	 * This is the defect that survives every other check. "Hi {{1.first_name}},"
	 * on a user with no first name is a populated, valid-looking field that
	 * arrives in the inbox as "Hi ,". Only looking at the whole field misses it,
	 * because the literal text around the token keeps it non-empty.
	 *
	 * Tokens whose node output is unknown are skipped — those are already
	 * reported as unverifiable, and guessing twice about the same gap is noise.
	 *
	 * @param array<string,mixed> $context
	 * @param array<string,bool>  $known
	 * @return array<int,array<string,mixed>>
	 */
	private static function check_empty_tokens( string $raw, array $context, string $node_id, string $field, array $known ): array {
		$warnings = [];

		if ( '' === trim( preg_replace( '/\{\{.*?\}\}/', '', $raw ) ) ) {
			// Nothing but tokens: an empty result is already reported as empty_value.
			return $warnings;
		}

		if ( ! preg_match_all( '/\{\{(.*?)\}\}/', $raw, $matches ) ) {
			return $warnings;
		}

		$seen = [];

		foreach ( $matches[1] as $code ) {
			$code = trim( $code );
			$root = explode( '.', $code )[0];

			if ( '' === $code || isset( $seen[ $code ] ) || in_array( $root, self::RESERVED_ROOTS, true ) ) {
				continue;
			}

			$seen[ $code ] = true;

			if ( ctype_digit( $root ) && empty( $known[ $root ] ) ) {
				continue;
			}

			$value = self::resolve_quietly( '{{' . $code . '}}', $context );

			if ( null === $value || '' === $value ) {
				$warnings[] = [
					'node'     => $node_id,
					'field'    => $field,
					'severity' => 'warning',
					'code'     => 'empty_token',
					'message'  => sprintf(
						'{{%s}} resolves to nothing inside "%s", so it renders as a gap — "Hi {{%s}}," arrives as "Hi ,". Give the field a fallback or pick a value that is always set.',
						$code,
						$field,
						$code
					),
				];
			}
		}

		return $warnings;
	}

	/**
	 * Classify every {{…}} reference in one field.
	 *
	 * @param array<string,int>   $position
	 * @param array<string,bool>  $known
	 * @param array<string,mixed> $by_id
	 * @return array<int,array<string,mixed>>
	 */
	private static function check_references( string $raw, string $node_id, string $field, array $position, array $known, array $by_id ): array {
		$warnings = [];

		if ( ! preg_match_all( '/\{\{(.*?)\}\}/', $raw, $matches ) ) {
			return $warnings;
		}

		$seen = [];

		foreach ( $matches[1] as $code ) {
			$root = explode( '.', trim( $code ) )[0];

			if ( '' === $root || in_array( $root, self::RESERVED_ROOTS, true ) || isset( $seen[ $root ] ) ) {
				continue;
			}

			$seen[ $root ] = true;

			// Non-numeric roots are global context ({{wp.…}}, {{workflow.…}}) or
			// an integration's own tag. Not ours to verify.
			if ( ! ctype_digit( $root ) ) {
				continue;
			}

			if ( ! isset( $by_id[ $root ] ) ) {
				$warnings[] = [
					'node'     => $node_id,
					'field'    => $field,
					'severity' => 'error',
					'code'     => 'unknown_node',
					'message'  => sprintf( '"%s" references node %s, which is not in this graph.', $field, $root ),
				];
				continue;
			}

			if ( ( $position[ $root ] ?? PHP_INT_MAX ) >= ( $position[ $node_id ] ?? 0 ) ) {
				$warnings[] = [
					'node'     => $node_id,
					'field'    => $field,
					'severity' => 'error',
					'code'     => 'forward_reference',
					'message'  => sprintf(
						'"%s" references node %s, which does not run before this one. It will be empty at runtime.',
						$field,
						$root
					),
				];
				continue;
			}

			if ( empty( $known[ $root ] ) ) {
				$warnings[] = [
					'node'     => $node_id,
					'field'    => $field,
					'severity' => 'warning',
					'code'     => 'unverifiable_reference',
					'message'  => sprintf(
						'"%s" references node %s, whose output is unknown here, so this value could not be checked.',
						$field,
						$root
					),
				];
			}
		}

		return $warnings;
	}

	/**
	 * Edge order, falling back to the given node order for anything the edges
	 * do not reach.
	 *
	 * @param array<string,mixed>            $by_id
	 * @param array<int,array<string,mixed>> $edges
	 * @return array<int,string>
	 */
	private static function execution_order( array $by_id, array $edges ): array {
		// PHP casts numeric-string array keys to int, so ids are normalized back
		// to strings here and membership is tracked by key rather than by value.
		// Mixing the two silently walks part of the graph twice.
		$ids      = array_map( 'strval', array_keys( $by_id ) );
		$outgoing = [];
		$indegree = array_fill_keys( $ids, 0 );

		foreach ( $edges as $edge ) {
			$source = (string) ( $edge['source'] ?? '' );
			$target = (string) ( $edge['target'] ?? '' );

			if ( ! in_array( $source, $ids, true ) || ! in_array( $target, $ids, true ) ) {
				continue;
			}

			$outgoing[ $source ][] = $target;
			++$indegree[ $target ];
		}

		// Triggers first, then anything else with nothing pointing at it.
		$queue = [];
		foreach ( $ids as $id ) {
			if ( 0 === $indegree[ $id ] ) {
				$queue[] = $id;
			}
		}

		usort(
			$queue,
			fn( $a, $b ) => ( 'trigger' === ( $by_id[ $b ]['type'] ?? '' ) ? 1 : 0 )
				<=> ( 'trigger' === ( $by_id[ $a ]['type'] ?? '' ) ? 1 : 0 )
		);

		$order = [];
		$seen  = [];

		while ( $queue ) {
			$id = (string) array_shift( $queue );

			if ( isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;
			$order[]     = $id;

			foreach ( $outgoing[ $id ] ?? [] as $target ) {
				if ( 0 === --$indegree[ $target ] ) {
					$queue[] = $target;
				}
			}
		}

		// A cycle leaves nodes unvisited; still report them, in graph order.
		foreach ( $ids as $id ) {
			if ( ! isset( $seen[ $id ] ) ) {
				$order[] = $id;
			}
		}

		return $order;
	}

	/**
	 * Every node id referenced by a {{n.…}} anywhere in the graph.
	 *
	 * An unknown output only matters if something actually reads it — a Delay
	 * node nobody references is not a gap worth reporting.
	 *
	 * @param array<string,mixed> $by_id
	 * @return array<string,bool>
	 */
	private static function referenced_nodes( array $by_id ): array {
		$refs = [];

		foreach ( $by_id as $node ) {
			$json = wp_json_encode( $node['data']['config'] ?? [] );

			if ( ! $json || ! preg_match_all( '/\{\{\s*(\d+)\./', $json, $matches ) ) {
				continue;
			}

			foreach ( $matches[1] as $id ) {
				$refs[ (string) $id ] = true;
			}
		}

		return $refs;
	}

	/**
	 * Evaluate one expression without letting the engine's diagnostics escape.
	 *
	 * Expression::compute() runs through eval() and returns null when a path is
	 * missing, but PHP still emits "Undefined array key" on the way out. Those
	 * are precisely the cases this class exists to report, so it reports them
	 * as findings rather than letting raw warnings into the response.
	 *
	 * @param array<string,mixed> $context
	 * @return mixed
	 */
	private static function resolve_quietly( string $raw, array $context ) {
		set_error_handler( fn() => true );

		try {
			return Expression::evaluate( $raw, $context );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * The {{wp.…}} / {{workflow.…}} bag, built only for prefixes the graph uses.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<string,mixed>
	 */
	private static function global_context( array $graph ): array {
		try {
			$prefixes = GlobalContext::detect_prefixes( $graph );

			return $prefixes ? GlobalContext::build( $prefixes ) : [];
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $results
	 * @param array<int,array<string,mixed>> $warnings
	 * @return array<string,mixed>
	 */
	private static function summarize( array $results, array $warnings ): array {
		$counts = [];
		foreach ( $warnings as $warning ) {
			$code            = (string) $warning['code'];
			$counts[ $code ] = ( $counts[ $code ] ?? 0 ) + 1;
		}

		return [
			'nodes_walked'   => count( $results ),
			'nodes_resolved' => count( array_filter( $results, fn( $r ) => 'unknown' !== $r['output_source'] ) ),
			'errors'         => count( array_filter( $warnings, fn( $w ) => 'error' === $w['severity'] ) ),
			'warnings'       => count( array_filter( $warnings, fn( $w ) => 'error' !== $w['severity'] ) ),
			'by_code'        => $counts,
		];
	}
}
