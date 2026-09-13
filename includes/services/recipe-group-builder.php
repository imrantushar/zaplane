<?php

namespace Zaplane\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a group recipe, and the answers from its setup, into the workflows to
 * create. It only works on arrays, so it can be checked without a database.
 *
 * A group recipe holds several workflows. Each one can be switched on or off,
 * and can have options: steps that only exist while the option is on. The group
 * can also ask for values, such as a coupon's discount, which its steps read as
 * {{setup.key}}. Those are written in when the workflows are built, so a saved
 * workflow never contains one.
 *
 * The blueprint of a group recipe:
 *
 *     folder    Title of the folder the workflows go in.
 *     values    [ { key, type: number|text, label, description, default, min, max, suffix, required } ]
 *     workflows [ { key, title, description, default, layout, graph,
 *                   options: [ { key, label, description, default, nodes: [ node ids ] } ] } ]
 */
class RecipeGroupBuilder {

	/** How a group's steps read a setup value. */
	const VALUE_PATTERN = '/\{\{\s*setup\.([A-Za-z0-9_]+)\s*\}\}/';

	/** Handles on an AI Agent that take a sub-node rather than the next step. */
	const SUB_NODE_PORTS = [ 'ai_tool', 'ai_memory', 'ai_model' ];

	/**
	 * The group's workflows, with every setting spelled out.
	 *
	 * @param array<string,mixed> $group
	 * @return array<int,array<string,mixed>>
	 */
	public static function workflows( array $group ): array {
		$workflows = [];

		foreach ( (array) ( $group['workflows'] ?? [] ) as $workflow ) {
			if ( ! is_array( $workflow ) || empty( $workflow['key'] ) ) {
				continue;
			}

			$options = [];
			foreach ( (array) ( $workflow['options'] ?? [] ) as $option ) {
				if ( ! is_array( $option ) || empty( $option['key'] ) ) {
					continue;
				}

				$options[] = [
					'key'         => (string) $option['key'],
					'label'       => (string) ( $option['label'] ?? $option['key'] ),
					'description' => (string) ( $option['description'] ?? '' ),
					'default'     => ! array_key_exists( 'default', $option ) || (bool) $option['default'],
					'nodes'       => array_values( array_map( 'strval', (array) ( $option['nodes'] ?? [] ) ) ),
				];
			}

			$graph = is_array( $workflow['graph'] ?? null ) ? $workflow['graph'] : [];

			$workflows[] = [
				'key'         => (string) $workflow['key'],
				'title'       => (string) ( $workflow['title'] ?? $workflow['key'] ),
				'description' => (string) ( $workflow['description'] ?? '' ),
				'default'     => ! array_key_exists( 'default', $workflow ) || (bool) $workflow['default'],
				'layout'      => (string) ( $workflow['layout'] ?? 'LR' ),
				'graph'       => [
					'nodes' => array_values( (array) ( $graph['nodes'] ?? [] ) ),
					'edges' => array_values( (array) ( $graph['edges'] ?? [] ) ),
				],
				'options'     => $options,
			];
		}//end foreach

		return $workflows;
	}

	/**
	 * The values the group asks for, with every setting spelled out.
	 *
	 * @param array<string,mixed> $group
	 * @return array<int,array<string,mixed>>
	 */
	public static function values( array $group ): array {
		$values = [];

		foreach ( (array) ( $group['values'] ?? [] ) as $value ) {
			if ( ! is_array( $value ) || empty( $value['key'] ) ) {
				continue;
			}

			$type = 'number' === ( $value['type'] ?? 'text' ) ? 'number' : 'text';

			$values[] = [
				'key'         => (string) $value['key'],
				'type'        => $type,
				'label'       => (string) ( $value['label'] ?? $value['key'] ),
				'description' => (string) ( $value['description'] ?? '' ),
				'default'     => $value['default'] ?? ( 'number' === $type ? 0 : '' ),
				'min'         => isset( $value['min'] ) && is_numeric( $value['min'] ) ? $value['min'] + 0 : null,
				'max'         => isset( $value['max'] ) && is_numeric( $value['max'] ) ? $value['max'] + 0 : null,
				'suffix'      => (string) ( $value['suffix'] ?? '' ),
				'required'    => ! empty( $value['required'] ),
			];
		}

		return $values;
	}

	/**
	 * The setup's answers, with whatever they leave out set to the group's defaults.
	 *
	 * Only the values that a chosen step reads are checked. The rest keep their
	 * defaults, so a value left over from a workflow that was switched off can't
	 * stop the others being set up.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $given { workflows: {key: bool}, options: {workflow: {key: bool}}, values: {key: value} }
	 * @return array{workflows: array<string,bool>, options: array<string,array<string,bool>>, values: array<string,string>}
	 * @throws \InvalidArgumentException When nothing is picked, or a value a chosen step reads is not valid.
	 */
	public static function answers( array $group, array $given ): array {
		$workflows = [];
		$options   = [];

		$given_options = is_array( $given['options'] ?? null ) ? $given['options'] : [];

		foreach ( self::workflows( $group ) as $workflow ) {
			$key = $workflow['key'];

			$workflows[ $key ] = self::flag( $given['workflows'] ?? [], $key, $workflow['default'] );
			$options[ $key ]   = [];

			foreach ( $workflow['options'] as $option ) {
				$options[ $key ][ $option['key'] ] = self::flag( $given_options[ $key ] ?? [], $option['key'], $option['default'] );
			}
		}

		if ( ! in_array( true, $workflows, true ) ) {
			throw new \InvalidArgumentException( __( 'Pick at least one workflow to set up.', 'zaplane' ) );
		}

		$usage        = self::value_usage( $group );
		$given_values = is_array( $given['values'] ?? null ) ? $given['values'] : [];
		$values       = [];

		foreach ( self::values( $group ) as $value ) {
			$key = $value['key'];

			if ( ! self::in_use( $usage[ $key ] ?? [], $workflows, $options ) ) {
				$values[ $key ] = is_scalar( $value['default'] ) ? (string) $value['default'] : '';
				continue;
			}

			$values[ $key ] = self::checked_value(
				$value,
				array_key_exists( $key, $given_values ) ? $given_values[ $key ] : $value['default']
			);
		}

		return [
			'workflows' => $workflows,
			'options'   => $options,
			'values'    => $values,
		];
	}

	/**
	 * The workflows to create: each one switched on, without the steps of its
	 * options that are off, and with the setup values written in.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $answers As answers() returns them.
	 * @return array<int,array{key:string,title:string,description:string,layout:string,graph:array}>
	 */
	public static function build( array $group, array $answers ): array {
		$built = [];

		foreach ( self::workflows( $group ) as $workflow ) {
			$key = $workflow['key'];

			if ( empty( $answers['workflows'][ $key ] ) ) {
				continue;
			}

			$off = [];
			foreach ( $workflow['options'] as $option ) {
				if ( empty( $answers['options'][ $key ][ $option['key'] ] ) ) {
					$off = array_merge( $off, $option['nodes'] );
				}
			}

			$graph = $off ? self::without_nodes( $workflow['graph'], $off ) : $workflow['graph'];

			$built[] = [
				'key'         => $key,
				'title'       => $workflow['title'],
				'description' => $workflow['description'],
				'layout'      => $workflow['layout'],
				'graph'       => self::with_values( $graph, (array) ( $answers['values'] ?? [] ) ),
			];
		}

		return $built;
	}

	/**
	 * A graph with some steps taken out. A line that ran into a removed step is
	 * joined to wherever that step led, so the steps either side stay connected.
	 *
	 * @param array<string,mixed> $graph
	 * @param array<int,string>   $node_ids
	 * @return array<string,mixed>
	 */
	public static function without_nodes( array $graph, array $node_ids ): array {
		$removed = array_fill_keys( array_map( 'strval', $node_ids ), true );
		$edges   = array_values( (array) ( $graph['edges'] ?? [] ) );

		$kept = [];
		$seen = [];

		foreach ( $edges as $edge ) {
			$source = (string) ( $edge['source'] ?? '' );
			$target = (string) ( $edge['target'] ?? '' );

			if ( isset( $removed[ $source ] ) ) {
				continue;
			}

			if ( ! isset( $removed[ $target ] ) ) {
				$lines = [ $edge ];
			} elseif ( self::is_sub_node_line( $edge ) ) {
				// A sub-node's line belongs to the agent it plugs into, and nothing else.
				$lines = [];
			} else {
				$lines = self::joined( $edge, $edges, $removed );
			}

			foreach ( $lines as $line ) {
				$signature = implode( '|', [ $line['source'], $line['sourceHandle'] ?? '', $line['target'], $line['targetHandle'] ?? '' ] );

				if ( ! isset( $seen[ $signature ] ) ) {
					$seen[ $signature ] = true;
					$kept[]             = $line;
				}
			}
		}//end foreach

		$graph['nodes'] = array_values(
			array_filter(
				(array) ( $graph['nodes'] ?? [] ),
				static function ( $node ) use ( $removed ) {
					return ! isset( $removed[ (string) ( $node['id'] ?? '' ) ] );
				}
			)
		);
		$graph['edges'] = $kept;

		return $graph;
	}

	/**
	 * A graph with its setup values written in.
	 *
	 * @param array<string,mixed>  $graph
	 * @param array<string,string> $values
	 * @return array<string,mixed>
	 */
	public static function with_values( array $graph, array $values ): array {
		array_walk_recursive(
			$graph,
			static function ( &$item ) use ( $values ) {
				if ( ! is_string( $item ) || false === strpos( $item, '{{' ) ) {
					return;
				}

				$item = preg_replace_callback(
					self::VALUE_PATTERN,
					static function ( $match ) use ( $values ) {
						return array_key_exists( $match[1], $values ) ? (string) $values[ $match[1] ] : $match[0];
					},
					$item
				);
			}
		);

		return $graph;
	}

	/**
	 * Where each setup value is read: by which workflow, and whether only by the
	 * steps of one of its options (`option`), or by steps that are always there
	 * (`option` is null).
	 *
	 * @param array<string,mixed> $group
	 * @return array<string,array<int,array{workflow:string,option:?string}>>
	 */
	public static function value_usage( array $group ): array {
		$usage = [];

		foreach ( self::workflows( $group ) as $workflow ) {
			$option_of = [];
			foreach ( $workflow['options'] as $option ) {
				foreach ( $option['nodes'] as $id ) {
					$option_of[ $id ] = $option['key'];
				}
			}

			foreach ( $workflow['graph']['nodes'] as $node ) {
				$use = [
					'workflow' => $workflow['key'],
					'option'   => $option_of[ (string) ( $node['id'] ?? '' ) ] ?? null,
				];

				foreach ( self::strings( $node['data'] ?? [] ) as $text ) {
					if ( ! preg_match_all( self::VALUE_PATTERN, $text, $matches ) ) {
						continue;
					}

					foreach ( $matches[1] as $key ) {
						if ( ! in_array( $use, $usage[ $key ] ?? [], true ) ) {
							$usage[ $key ][] = $use;
						}
					}
				}
			}
		}//end foreach

		return $usage;
	}

	/**
	 * Steps that read the output of a step the graph doesn't have, such as
	 * `{{4.coupon.code}}` after step 4 was taken out.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array{node_id:string,missing:string}>
	 */
	public static function dangling_references( array $graph ): array {
		$ids = [];
		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			$ids[ (string) ( $node['id'] ?? '' ) ] = true;
		}

		$found = [];
		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			foreach ( self::strings( $node['data'] ?? [] ) as $text ) {
				if ( ! preg_match_all( '/\{\{\s*(\d+)\./', $text, $matches ) ) {
					continue;
				}

				foreach ( $matches[1] as $id ) {
					$entry = [
						'node_id' => (string) ( $node['id'] ?? '' ),
						'missing' => $id,
					];

					if ( ! isset( $ids[ $id ] ) && ! in_array( $entry, $found, true ) ) {
						$found[] = $entry;
					}
				}
			}
		}

		return $found;
	}

	/**
	 * The lines that replace one running into removed steps: one to each step
	 * that the removed steps led on to.
	 *
	 * @param array<string,mixed>            $edge
	 * @param array<int,array<string,mixed>> $edges
	 * @param array<string,bool>             $removed
	 * @return array<int,array<string,mixed>>
	 */
	private static function joined( array $edge, array $edges, array $removed ): array {
		$lines   = [];
		$queue   = [ (string) $edge['target'] ];
		$visited = [];

		while ( $queue ) {
			$id = array_shift( $queue );

			if ( isset( $visited[ $id ] ) ) {
				continue;
			}
			$visited[ $id ] = true;

			foreach ( $edges as $next ) {
				if ( (string) ( $next['source'] ?? '' ) !== $id || self::is_sub_node_line( $next ) ) {
					continue;
				}

				$target = (string) ( $next['target'] ?? '' );

				if ( isset( $removed[ $target ] ) ) {
					$queue[] = $target;
					continue;
				}

				$source = (string) $edge['source'];
				$handle = isset( $edge['sourceHandle'] ) && '' !== (string) $edge['sourceHandle'] ? (string) $edge['sourceHandle'] : '';

				$line = [
					'id'     => 'e' . $source . ( '' !== $handle ? '-' . $handle : '' ) . '-' . $target,
					'source' => $source,
					'target' => $target,
				];

				if ( '' !== $handle ) {
					$line['sourceHandle'] = $handle;
				}

				if ( isset( $next['targetHandle'] ) && '' !== (string) $next['targetHandle'] ) {
					$line['targetHandle'] = (string) $next['targetHandle'];
				}

				$lines[] = $line;
			}//end foreach
		}//end while

		return $lines;
	}

	/**
	 * @param array<string,mixed> $edge
	 */
	private static function is_sub_node_line( array $edge ): bool {
		return 'sub_out' === ( $edge['sourceHandle'] ?? '' )
			|| in_array( (string) ( $edge['targetHandle'] ?? '' ), self::SUB_NODE_PORTS, true );
	}

	/**
	 * @param mixed $map
	 */
	private static function flag( $map, string $key, bool $default ): bool {
		if ( ! is_array( $map ) || ! array_key_exists( $key, $map ) ) {
			return $default;
		}

		return (bool) filter_var( $map[ $key ], FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Whether a step that will exist reads a value.
	 *
	 * @param array<int,array{workflow:string,option:?string}> $uses
	 * @param array<string,bool>                               $workflows
	 * @param array<string,array<string,bool>>                 $options
	 */
	private static function in_use( array $uses, array $workflows, array $options ): bool {
		foreach ( $uses as $use ) {
			if ( empty( $workflows[ $use['workflow'] ] ) ) {
				continue;
			}

			if ( null === $use['option'] || ! empty( $options[ $use['workflow'] ][ $use['option'] ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A setup value as it is written into the steps.
	 *
	 * @param array<string,mixed> $value
	 * @param mixed               $given
	 * @throws \InvalidArgumentException When it is not valid.
	 */
	private static function checked_value( array $value, $given ): string {
		if ( 'text' === $value['type'] ) {
			$text = sanitize_text_field( is_scalar( $given ) ? (string) $given : '' );

			if ( $value['required'] && '' === $text ) {
				/* translators: %s: name of a setup value, e.g. "Sender name". */
				throw new \InvalidArgumentException( sprintf( __( '%s is required.', 'zaplane' ), $value['label'] ) );
			}

			return $text;
		}

		if ( is_bool( $given ) || ! is_numeric( $given ) ) {
			/* translators: %s: name of a setup value, e.g. "Coupon discount". */
			throw new \InvalidArgumentException( sprintf( __( '%s must be a number.', 'zaplane' ), $value['label'] ) );
		}

		$number = $given + 0;
		$min    = $value['min'];
		$max    = $value['max'];

		if ( ( null !== $min && $number < $min ) || ( null !== $max && $number > $max ) ) {
			if ( null !== $min && null !== $max ) {
				/* translators: 1: name of a setup value, 2: lowest allowed number, 3: highest allowed number. */
				$message = sprintf( __( '%1$s must be between %2$s and %3$s.', 'zaplane' ), $value['label'], $min, $max );
			} elseif ( null !== $min ) {
				/* translators: 1: name of a setup value, 2: lowest allowed number. */
				$message = sprintf( __( '%1$s must be at least %2$s.', 'zaplane' ), $value['label'], $min );
			} else {
				/* translators: 1: name of a setup value, 2: highest allowed number. */
				$message = sprintf( __( '%1$s must be at most %2$s.', 'zaplane' ), $value['label'], $max );
			}

			throw new \InvalidArgumentException( $message );
		}

		return (string) $number;
	}

	/**
	 * Every string inside a value, however deeply it is nested.
	 *
	 * @param mixed $data
	 * @return array<int,string>
	 */
	private static function strings( $data ): array {
		if ( is_string( $data ) ) {
			return [ $data ];
		}

		if ( ! is_array( $data ) ) {
			return [];
		}

		$strings = [];
		foreach ( $data as $item ) {
			foreach ( self::strings( $item ) as $string ) {
				$strings[] = $string;
			}
		}

		return $strings;
	}
}
