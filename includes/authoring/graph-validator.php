<?php

namespace Zaplane\Authoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks a flow graph against the integration manifest before it is persisted.
 *
 * The canvas can only ever emit a well-formed graph — every app, event and field
 * came from a picker. A graph assembled programmatically has no such guarantee,
 * so everything the editor enforces implicitly has to be asserted here instead:
 * that the app exists, that the event belongs to it, that required fields are
 * filled, and — the one that fails silently rather than loudly — that node ids
 * are numeric.
 *
 * Node ids are read back as `(int)` by the run engine (node_key is an unsigned
 * int column), so a graph with ids like "send_email" saves cleanly, renders on
 * the canvas, and then collapses every node onto key 0 at run time.
 *
 * Errors block a save; warnings don't (an unlinked connection is normal — the
 * author picks it afterwards).
 */
class GraphValidator {

	private const NODE_TYPES = [ 'trigger', 'action' ];

	/** Edge handles that attach a sub-node to an AI Agent rather than continuing the flow. */
	private const SUB_HANDLES = [ 'ai_tool', 'ai_memory', 'ai_model' ];

	/** @var array<int,array<string,string>> */
	private array $errors = [];

	/** @var array<int,array<string,string>> */
	private array $warnings = [];

	/**
	 * @param array<string,mixed> $graph
	 * @return array{valid:bool,errors:array<int,array<string,string>>,warnings:array<int,array<string,string>>}
	 */
	public static function check( array $graph ): array {
		return ( new self() )->validate( $graph );
	}

	/**
	 * @param array<string,mixed> $graph
	 * @return array{valid:bool,errors:array<int,array<string,string>>,warnings:array<int,array<string,string>>}
	 */
	public function validate( array $graph ): array {
		$this->errors   = [];
		$this->warnings = [];

		$nodes = $graph['nodes'] ?? null;
		$edges = $graph['edges'] ?? null;

		if ( ! is_array( $nodes ) ) {
			$this->error( '', 'Graph must have a "nodes" array.' );
			return $this->result();
		}

		if ( ! is_array( $edges ) ) {
			$this->error( '', 'Graph must have an "edges" array.' );
			return $this->result();
		}

		if ( empty( $nodes ) ) {
			$this->error( '', 'Graph has no nodes.' );
			return $this->result();
		}

		$ids = $this->validate_nodes( $nodes );
		$this->validate_trigger_count( $nodes );
		$this->validate_edges( $edges, $ids );
		$this->validate_reachability( $nodes, $edges, $ids );

		return $this->result();
	}

	/* --------------------------------------------------------------------- */

	/**
	 * @param array<int,mixed> $nodes
	 * @return array<int,string> The ids that were structurally usable.
	 */
	private function validate_nodes( array $nodes ): array {
		$seen = [];

		foreach ( $nodes as $index => $node ) {
			$where = 'node #' . $index;

			if ( ! is_array( $node ) ) {
				$this->error( $where, 'Node must be an object.' );
				continue;
			}

			$id = isset( $node['id'] ) ? (string) $node['id'] : '';
			if ( '' === $id ) {
				$this->error( $where, 'Node is missing an "id".' );
				continue;
			}

			$where = 'node ' . $id;

			// The run engine casts ids to int; anything non-numeric silently
			// collapses to key 0 and the workflow runs the wrong node (or none).
			if ( ! ctype_digit( $id ) || '0' === $id ) {
				$this->error( $where, 'Node id must be a positive whole number given as a string, e.g. "1". Got "' . $id . '".' );
				continue;
			}

			if ( isset( $seen[ $id ] ) ) {
				$this->error( $where, 'Duplicate node id "' . $id . '".' );
				continue;
			}
			$seen[ $id ] = true;

			$type = (string) ( $node['type'] ?? '' );
			if ( ! in_array( $type, self::NODE_TYPES, true ) ) {
				$this->error( $where, 'Node type must be one of: ' . implode( ', ', self::NODE_TYPES ) . '. Got "' . $type . '".' );
				continue;
			}

			$this->validate_node_capability( $where, $type, (array) ( $node['data'] ?? [] ) );

			if ( ! isset( $node['position']['x'], $node['position']['y'] ) ) {
				$this->warn( $where, 'Node has no position; it will be laid out automatically.' );
			}
		}//end foreach

		// PHP silently narrows numeric array keys to int, and node ids are compared
		// strictly against these, so cast them back to the strings they came in as.
		return array_map( 'strval', array_keys( $seen ) );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function validate_node_capability( string $where, string $type, array $data ): void {
		$app = (string) ( $data['app'] ?? '' );
		if ( '' === $app ) {
			$this->error( $where, 'Node is missing "data.app".' );
			return;
		}

		$entry = Catalog::entry( $app );
		if ( null === $entry ) {
			$this->error( $where, 'Unknown app "' . $app . '". Use list_apps to see what is available.' );
			return;
		}

		$event = (string) ( $data['event'] ?? '' );
		if ( '' === $event ) {
			$this->error( $where, 'Node is missing "data.event".' );
			return;
		}

		$capability = Catalog::describe_capability( $app, $type, $event );
		if ( null === $capability ) {
			$bucket    = 'trigger' === $type ? 'triggers' : 'actions';
			$available = array_keys( (array) ( $entry[ $bucket ] ?? [] ) );
			$this->error(
				$where,
				sprintf(
					'"%s" is not a %s of app "%s". Available: %s',
					$event,
					$type,
					$app,
					$available ? implode( ', ', array_slice( $available, 0, 25 ) ) : '(none)'
				)
			);
			return;
		}

		if ( ! empty( $capability['disabled'] ) ) {
			$this->warn( $where, sprintf( '"%s" is marked disabled on this site and may not fire.', $event ) );
		}

		if ( ! empty( $entry['requires_connection'] ) && empty( $data['connection_id'] ) ) {
			$this->warn(
				$where,
				sprintf( 'App "%s" needs a connection; none is linked yet.', $app ),
				'missing_connection'
			);
		}

		$this->validate_config( $where, (array) $capability['schema'], (array) ( $data['config'] ?? [] ) );
	}

	/**
	 * @param array<int,mixed>    $schema
	 * @param array<string,mixed> $config
	 */
	private function validate_config( string $where, array $schema, array $config ): void {
		foreach ( $schema as $field ) {
			if ( ! is_array( $field ) || empty( $field['key'] ) ) {
				continue;
			}

			$key = (string) $field['key'];

			if ( ! $this->field_applies( $field, $config ) ) {
				continue;
			}

			$value   = $config[ $key ] ?? null;
			$isEmpty = null === $value || '' === $value || [] === $value;

			if ( ! empty( $field['required'] ) && $isEmpty ) {
				$this->error(
					$where,
					sprintf( 'Required field "%s" (%s) is missing.', $key, (string) ( $field['label'] ?? $key ) )
				);
				continue;
			}

			if ( $isEmpty ) {
				continue;
			}

			$this->validate_field_value( $where, $field, $value );
		}

		// Surface stray keys — usually a guessed field name that will be ignored.
		$known = [];
		foreach ( $schema as $field ) {
			if ( is_array( $field ) && ! empty( $field['key'] ) ) {
				$known[ (string) $field['key'] ] = true;
			}
		}

		foreach ( array_keys( $config ) as $key ) {
			if ( ! isset( $known[ (string) $key ] ) ) {
				$this->warn( $where, sprintf( 'Config key "%s" is not in this event\'s schema and will be ignored.', (string) $key ) );
			}
		}
	}

	/**
	 * A field guarded by depends_on only applies when the field it depends on
	 * currently holds one of the listed values.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $config
	 */
	private function field_applies( array $field, array $config ): bool {
		if ( empty( $field['depends_on'] ) || ! is_array( $field['depends_on'] ) ) {
			return true;
		}

		foreach ( $field['depends_on'] as $otherKey => $allowed ) {
			$current = $config[ (string) $otherKey ] ?? null;
			$allowed = is_array( $allowed ) ? $allowed : [ $allowed ];

			if ( ! in_array( $current, $allowed, false ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $field
	 * @param mixed               $value
	 */
	private function validate_field_value( string $where, array $field, $value ): void {
		$key  = (string) $field['key'];
		$type = (string) ( $field['type'] ?? 'text' );

		// Options are only authoritative when they ship in the manifest. Fields
		// marked dynamic are populated at edit time from the live site, so there is
		// nothing here to check them against.
		$options = ( isset( $field['options'] ) && is_array( $field['options'] ) ) ? $field['options'] : [];

		if ( $options && empty( $field['dynamic'] ) && in_array( $type, [ 'select', 'multi-select', 'multiselect' ], true ) ) {
			$allowed = [];
			foreach ( $options as $option ) {
				if ( is_array( $option ) && array_key_exists( 'value', $option ) ) {
					$allowed[] = (string) $option['value'];
				} elseif ( is_scalar( $option ) ) {
					$allowed[] = (string) $option;
				}
			}

			if ( $allowed ) {
				foreach ( (array) $value as $single ) {
					if ( ! is_scalar( $single ) ) {
						continue;
					}
					// An expression resolves at run time — it can't be checked now.
					if ( $this->is_expression( (string) $single ) ) {
						continue;
					}
					if ( ! in_array( (string) $single, $allowed, true ) ) {
						$this->error(
							$where,
							sprintf(
								'Field "%s" got "%s", which is not one of: %s',
								$key,
								(string) $single,
								implode( ', ', array_slice( $allowed, 0, 20 ) )
							)
						);
					}
				}
			}
		}//end if

		if ( 'number' === $type && is_scalar( $value ) && ! $this->is_expression( (string) $value ) && ! is_numeric( $value ) ) {
			$this->error( $where, sprintf( 'Field "%s" expects a number, got "%s".', $key, (string) $value ) );
		}

		if ( 'email' === $type && is_string( $value ) && ! $this->is_expression( $value ) && ! is_email( $value ) ) {
			$this->warn( $where, sprintf( 'Field "%s" does not look like an email address.', $key ) );
		}
	}

	/** Values carrying a {{…}} token are resolved from upstream output at run time. */
	private function is_expression( string $value ): bool {
		return false !== strpos( $value, '{{' );
	}

	/**
	 * @param array<int,mixed> $nodes
	 */
	private function validate_trigger_count( array $nodes ): void {
		$triggers = [];

		foreach ( $nodes as $node ) {
			if ( is_array( $node ) && 'trigger' === ( $node['type'] ?? '' ) ) {
				$triggers[] = (string) ( $node['id'] ?? '?' );
			}
		}

		if ( empty( $triggers ) ) {
			$this->error( '', 'Graph has no trigger node. Every workflow starts with exactly one.' );
			return;
		}

		if ( count( $triggers ) > 1 ) {
			$this->error( '', 'Graph has ' . count( $triggers ) . ' trigger nodes (' . implode( ', ', $triggers ) . '); only one is allowed.' );
		}
	}

	/**
	 * @param array<int,mixed>  $edges
	 * @param array<int,string> $ids
	 */
	private function validate_edges( array $edges, array $ids ): void {
		$known = array_flip( $ids );
		$seen  = [];

		foreach ( $edges as $index => $edge ) {
			$where = 'edge #' . $index;

			if ( ! is_array( $edge ) ) {
				$this->error( $where, 'Edge must be an object.' );
				continue;
			}

			$source = isset( $edge['source'] ) ? (string) $edge['source'] : '';
			$target = isset( $edge['target'] ) ? (string) $edge['target'] : '';

			if ( '' === $source || '' === $target ) {
				$this->error( $where, 'Edge needs both "source" and "target".' );
				continue;
			}

			$where = sprintf( 'edge %s→%s', $source, $target );

			if ( ! isset( $known[ $source ] ) ) {
				$this->error( $where, 'Edge source "' . $source . '" is not a node in this graph.' );
			}

			if ( ! isset( $known[ $target ] ) ) {
				$this->error( $where, 'Edge target "' . $target . '" is not a node in this graph.' );
			}

			if ( $source === $target ) {
				$this->error( $where, 'Edge connects a node to itself.' );
			}

			$id = isset( $edge['id'] ) ? (string) $edge['id'] : '';
			if ( '' === $id ) {
				$this->warn( $where, 'Edge has no "id"; one will be generated.' );
			} elseif ( isset( $seen[ $id ] ) ) {
				$this->error( $where, 'Duplicate edge id "' . $id . '".' );
			} else {
				$seen[ $id ] = true;
			}
		}//end foreach
	}

	/**
	 * @param array<int,mixed>  $nodes
	 * @param array<int,mixed>  $edges
	 * @param array<int,string> $ids
	 */
	private function validate_reachability( array $nodes, array $edges, array $ids ): void {
		$inbound = [];

		foreach ( $edges as $edge ) {
			if ( ! is_array( $edge ) ) {
				continue;
			}
			$target = isset( $edge['target'] ) ? (string) $edge['target'] : '';
			$source = isset( $edge['source'] ) ? (string) $edge['source'] : '';
			$handle = (string) ( $edge['targetHandle'] ?? '' );

			if ( '' !== $target ) {
				$inbound[ $target ][] = $handle;
			}

			// A sub-node feeds an AI Agent handle instead of continuing the flow,
			// so it is never "downstream" of anything.
			if ( '' !== $source && in_array( $handle, self::SUB_HANDLES, true ) ) {
				$inbound[ $source ][] = '__sub__';
			}
		}

		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$id   = (string) ( $node['id'] ?? '' );
			$type = (string) ( $node['type'] ?? '' );

			if ( '' === $id || ! in_array( $id, $ids, true ) ) {
				continue;
			}

			$handles = $inbound[ $id ] ?? [];

			if ( 'trigger' === $type ) {
				$flowInbound = array_filter( $handles, fn( $h ) => '__sub__' !== $h && ! in_array( $h, self::SUB_HANDLES, true ) );
				if ( $flowInbound ) {
					$this->error( 'node ' . $id, 'A trigger node cannot have an incoming edge.' );
				}
				continue;
			}

			if ( empty( $handles ) ) {
				$this->warn( 'node ' . $id, 'Node is not connected to anything upstream and will never run.' );
			}
		}//end foreach
	}

	/* --------------------------------------------------------------------- */

	private function error( string $where, string $message, string $code = 'invalid' ): void {
		$this->errors[] = [
			'code'    => $code,
			'where'   => $where,
			'message' => $message,
		];
	}

	/**
	 * A code is carried so a caller can single one finding out without matching
	 * on message text — set_status() blocks activation on `missing_connection`,
	 * which is only advisory while the graph is still a draft.
	 */
	private function warn( string $where, string $message, string $code = 'advisory' ): void {
		$this->warnings[] = [
			'code'    => $code,
			'where'   => $where,
			'message' => $message,
		];
	}

	/**
	 * @return array{valid:bool,errors:array<int,array<string,string>>,warnings:array<int,array<string,string>>}
	 */
	private function result(): array {
		return [
			'valid'    => empty( $this->errors ),
			'errors'   => $this->errors,
			'warnings' => $this->warnings,
		];
	}
}
