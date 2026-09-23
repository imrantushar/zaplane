<?php

namespace Zaplane\Authoring;

use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and edits workflows from a graph, for callers that aren't the canvas.
 *
 * Two jobs. normalize() fills in everything the editor would have supplied as a
 * side effect of a human clicking — node ids, canvas positions, labels, icons,
 * trigger hooks, edge ids — so a caller only has to state intent: which app,
 * which event, which config. save() then applies the same versioning rules the
 * editor's save path uses, so a workflow built here is indistinguishable from
 * one built by hand.
 *
 * Every write runs through GraphValidator first. Warnings are returned to the
 * caller; errors refuse the save.
 */
class WorkflowAuthor {

	/** Canvas spacing used when positions are generated, matching the seeded recipes. */
	private const ORIGIN_X  = 80;
	private const ORIGIN_Y  = 200;
	private const STEP_X    = 340;

	/**
	 * Fill in the mechanical parts of a graph from the manifest.
	 *
	 * Renumbers node ids to sequential positive integers when any of them is not
	 * already a usable numeric id, rewriting edges to match. Chains nodes in the
	 * given order when no edges are supplied at all.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<string,mixed>
	 */
	public static function normalize( array $graph ): array {
		$nodes = array_values( array_filter( (array) ( $graph['nodes'] ?? [] ), 'is_array' ) );
		$edges = array_values( array_filter( (array) ( $graph['edges'] ?? [] ), 'is_array' ) );

		if ( empty( $nodes ) ) {
			return [
				'nodes' => [],
				'edges' => [],
			];
		}

		$map = self::id_map( $nodes );

		foreach ( $nodes as $index => $node ) {
			$original = isset( $node['id'] ) ? (string) $node['id'] : '';
			$id       = $map[ $original ] ?? (string) ( $index + 1 );

			$type = in_array( ( $node['type'] ?? '' ), [ 'trigger', 'action' ], true )
				? (string) $node['type']
				: ( 0 === $index ? 'trigger' : 'action' );

			$position = isset( $node['position']['x'], $node['position']['y'] )
				? $node['position']
				: [
					'x' => self::ORIGIN_X + ( $index * self::STEP_X ),
					'y' => self::ORIGIN_Y,
				];

			// Union, not merge: the canonical keys lead in the order the canvas and
			// the seeded recipes use, and any extra keys the caller passed through
			// (width, selected, …) keep their values and follow.
			$nodes[ $index ] = [
				'id'       => $id,
				'type'     => $type,
				'position' => $position,
				'data'     => self::normalize_node_data( (array) ( $node['data'] ?? [] ), $type ),
			] + $node;
		}//end foreach

		$edges = empty( $edges )
			? self::chain( $nodes )
			: self::normalize_edges( $edges, $map );

		return [
			'nodes' => $nodes,
			'edges' => $edges,
		];
	}

	/**
	 * Create a new workflow from a graph. Returns the workflow plus any warnings.
	 *
	 * @param array<string,mixed> $graph
	 * @param array<string,mixed> $opts  user_id, layout, folder_id, status.
	 * @return array<string,mixed>
	 * @throws \InvalidArgumentException When the graph is invalid.
	 */
	public static function create( string $title, array $graph, array $opts = [] ): array {
		$title = sanitize_text_field( $title );
		if ( '' === trim( $title ) ) {
			throw new \InvalidArgumentException( 'A workflow title is required.' );
		}

		$graph  = self::normalize( $graph );
		$report = GraphValidator::check( $graph );

		if ( ! $report['valid'] ) {
			throw new \InvalidArgumentException( esc_html( self::format_errors( $report['errors'] ) ) );
		}

		$status = (string) ( $opts['status'] ?? 'draft' );
		if ( ! in_array( $status, [ 'draft', 'paused', 'active' ], true ) ) {
			$status = 'draft';
		}

		$workflow = Workflow::create( [
			// Falls back to the current user, but callers authenticating with
			// something other than a WordPress session (where there is no current
			// user) should pass this explicitly or the workflow lands ownerless.
			'user_id'   => (int) ( $opts['user_id'] ?? get_current_user_id() ),
			'folder_id' => isset( $opts['folder_id'] ) ? (int) $opts['folder_id'] : null,
			'title'     => $title,
			'status'    => $status,
			'layout'    => sanitize_text_field( (string) ( $opts['layout'] ?? 'LR' ) ),
		] );

		$json = wp_json_encode( $graph );

		WorkflowVersion::create( [
			'workflow_id'    => $workflow->id,
			'graph_json'     => $graph,
			'graph_hash'     => hash( 'sha256', $json ),
			'is_active'      => 1,
			'version_number' => null,
		] );

		$workflow->integration_icons = self::icons( $graph );
		$workflow->save();

		return [
			'workflow_id' => (int) $workflow->id,
			'title'       => $workflow->title,
			'status'      => $workflow->status,
			'graph'       => $graph,
			'warnings'    => $report['warnings'],
		];
	}

	/**
	 * Replace an existing workflow's graph.
	 *
	 * Mirrors the editor's versioning rules: a draft is edited in place, while a
	 * workflow that has already gone live keeps its history — an unchanged node
	 * set bumps the version number, and a structural change supersedes the active
	 * version with a new one.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<string,mixed>
	 * @throws \InvalidArgumentException When the workflow is missing or the graph is invalid.
	 */
	public static function save( int $workflow_id, array $graph ): array {
		$workflow = Workflow::find( $workflow_id );
		if ( ! $workflow ) {
			throw new \InvalidArgumentException( 'Workflow ' . (int) $workflow_id . ' not found.' );
		}

		$graph  = self::normalize( $graph );
		$report = GraphValidator::check( $graph );

		if ( ! $report['valid'] ) {
			throw new \InvalidArgumentException( esc_html( self::format_errors( $report['errors'] ) ) );
		}

		$hash    = hash( 'sha256', (string) wp_json_encode( $graph ) );
		$current = WorkflowVersion::where( 'workflow_id', $workflow_id )->where( 'is_active', 1 )->first();

		$workflow->integration_icons = self::icons( $graph );
		$workflow->save();

		if ( $current && $current->graph_hash === $hash ) {
			return self::saved( $workflow_id, $current, $report['warnings'], false );
		}

		if ( ! $current ) {
			$version = WorkflowVersion::create( [
				'workflow_id'    => $workflow_id,
				'graph_json'     => $graph,
				'graph_hash'     => $hash,
				'is_active'      => 1,
				'version_number' => null,
			] );

			return self::saved( $workflow_id, $version, $report['warnings'], true );
		}

		if ( 'draft' === $workflow->status ) {
			$current->graph_json = $graph;
			$current->graph_hash = $hash;
			$current->save();

			return self::saved( $workflow_id, $current, $report['warnings'], false );
		}

		$currentIds = array_map( fn( $n ) => (string) $n['id'], (array) ( $current->getGraph()['nodes'] ?? [] ) );
		$newIds     = array_map( fn( $n ) => (string) $n['id'], $graph['nodes'] );
		sort( $currentIds );
		sort( $newIds );

		if ( $currentIds === $newIds ) {
			$current->graph_json     = $graph;
			$current->graph_hash     = $hash;
			$current->version_number = ( $current->version_number ?? 0 ) + 1;
			$current->save();

			return self::saved( $workflow_id, $current, $report['warnings'], false );
		}

		$current->is_active = 0;
		$current->save();

		$version = WorkflowVersion::create( [
			'workflow_id'    => $workflow_id,
			'graph_json'     => $graph,
			'graph_hash'     => $hash,
			'is_active'      => 1,
			'version_number' => 1,
		] );

		return self::saved( $workflow_id, $version, $report['warnings'], true );
	}

	/**
	 * Flip a workflow between active and paused.
	 *
	 * @return array<string,mixed>
	 * @throws \InvalidArgumentException When the workflow is missing or can't go live.
	 */
	public static function set_status( int $workflow_id, string $status ): array {
		if ( ! in_array( $status, [ 'active', 'paused', 'draft' ], true ) ) {
			throw new \InvalidArgumentException( 'Status must be active, paused or draft.' );
		}

		$workflow = Workflow::find( $workflow_id );
		if ( ! $workflow ) {
			throw new \InvalidArgumentException( 'Workflow ' . (int) $workflow_id . ' not found.' );
		}

		if ( 'active' === $status ) {
			$version = $workflow->activeVersion();
			if ( ! $version ) {
				throw new \InvalidArgumentException( 'Workflow ' . (int) $workflow_id . ' has no active version to run.' );
			}

			$report = GraphValidator::check( $version->getGraph() );
			if ( ! $report['valid'] ) {
				// Not HTML: it's returned as JSON and shown as text, so escaping
				// here only turned quotes into &quot; on screen.
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new \InvalidArgumentException( 'Workflow ' . (int) $workflow_id . ' cannot go live: ' . self::format_errors( $report['errors'] ) );
			}

			// A node whose app needs credentials is only a warning while the graph
			// is a draft — the author links the account afterwards. Going live is a
			// different bar: the step cannot run at all without one, so activating
			// would produce a workflow that fires and silently does nothing.
			$blocking = array_values(
				array_filter(
					$report['warnings'],
					static fn( $w ) => 'missing_connection' === ( $w['code'] ?? '' )
				)
			);

			if ( $blocking ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text, see above.
				throw new \InvalidArgumentException( self::connection_message( $version->getGraph(), $blocking ) );
			}
		}

		$workflow->status = $status;
		$workflow->save();

		return [
			'workflow_id' => $workflow_id,
			'status'      => $workflow->status,
		];
	}

	/* --------------------------------------------------------------------- */

	/**
	 * Map supplied node ids onto usable numeric ones. Ids are left alone when
	 * every one of them is already a positive integer and they are unique.
	 *
	 * @param array<int,array<string,mixed>> $nodes
	 * @return array<string,string>
	 */
	private static function id_map( array $nodes ): array {
		$original = [];
		$usable   = true;

		foreach ( $nodes as $index => $node ) {
			$id = isset( $node['id'] ) ? (string) $node['id'] : '';
			$original[ $index ] = $id;

			if ( '' === $id || ! ctype_digit( $id ) || '0' === $id ) {
				$usable = false;
			}
		}

		if ( $usable && count( array_unique( $original ) ) === count( $original ) ) {
			return array_combine( $original, $original );
		}

		$map = [];
		foreach ( $original as $index => $id ) {
			// Later duplicates would clobber earlier ones; first mapping wins and
			// the validator reports the collision if one survives.
			if ( '' !== $id && ! isset( $map[ $id ] ) ) {
				$map[ $id ] = (string) ( $index + 1 );
			}
		}

		return $map;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private static function normalize_node_data( array $data, string $type ): array {
		$app   = (string) ( $data['app'] ?? '' );
		$event = (string) ( $data['event'] ?? '' );

		$data['config'] = (array) ( $data['config'] ?? [] );

		$entry = '' === $app ? null : Catalog::entry( $app );
		if ( null === $entry ) {
			return $data;
		}

		$data['name'] = (string) ( $data['name'] ?? $entry['name'] ?? $app );
		$data['icon'] = (string) ( $data['icon'] ?? $entry['icon'] ?? '' );

		if ( ! empty( $entry['requires_connection'] ) && ! array_key_exists( 'connection_id', $data ) ) {
			$data['connection_id'] = null;
		}

		$capability = '' === $event ? null : Catalog::describe_capability( $app, $type, $event );
		if ( null === $capability ) {
			return $data;
		}

		if ( empty( $data['label'] ) ) {
			$data['label'] = (string) $capability['label'];
		}

		// The engine matches a fired WordPress hook back to the trigger node, so
		// this has to be the manifest's hook, not whatever the caller guessed.
		if ( 'trigger' === $type && ! empty( $capability['hook'] ) ) {
			$data['hook'] = Catalog::primary_hook( $capability );
		}

		return $data;
	}

	/**
	 * @param array<int,array<string,mixed>> $nodes
	 * @return array<int,array<string,string>>
	 */
	private static function chain( array $nodes ): array {
		$edges = [];
		$count = count( $nodes );

		for ( $i = 0; $i < $count - 1; $i++ ) {
			$source  = (string) $nodes[ $i ]['id'];
			$target  = (string) $nodes[ $i + 1 ]['id'];
			$edges[] = [
				'id'     => 'e' . $source . '-' . $target,
				'source' => $source,
				'target' => $target,
			];
		}

		return $edges;
	}

	/**
	 * @param array<int,array<string,mixed>> $edges
	 * @param array<string,string>           $map
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_edges( array $edges, array $map ): array {
		$out = [];

		foreach ( $edges as $edge ) {
			$source = isset( $edge['source'] ) ? (string) $edge['source'] : '';
			$target = isset( $edge['target'] ) ? (string) $edge['target'] : '';

			$edge['source'] = $map[ $source ] ?? $source;
			$edge['target'] = $map[ $target ] ?? $target;

			if ( empty( $edge['id'] ) ) {
				$edge['id'] = 'e' . $edge['source'] . '-' . $edge['target']
					. ( isset( $edge['sourceHandle'] ) ? '-' . (string) $edge['sourceHandle'] : '' );
			}

			$out[] = $edge;
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $graph
	 * @return array<int,string>
	 */
	private static function icons( array $graph ): array {
		$icons = [];

		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			$icon = $node['data']['icon'] ?? '';
			if ( is_string( $icon ) && '' !== $icon ) {
				$icons[] = $icon;
			}
		}

		return array_values( array_unique( $icons ) );
	}

	/**
	 * @param array<int,array<string,string>> $errors
	 */
	/**
	 * "Link a Facebook Messenger account to “Send Inbox Reply” before turning
	 * this workflow on." Names the steps and apps as the editor shows them,
	 * not node ids and slugs.
	 *
	 * @param array<string,mixed>              $graph
	 * @param array<int,array<string,string>> $warnings missing_connection warnings.
	 */
	private static function connection_message( array $graph, array $warnings ): string {
		$nodes = [];
		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			$nodes[ (string) ( $node['id'] ?? '' ) ] = (array) ( $node['data'] ?? [] );
		}

		$steps = [];
		foreach ( $warnings as $warning ) {
			$data = $nodes[ preg_replace( '/^node\s+/', '', (string) ( $warning['where'] ?? '' ) ) ] ?? [];
			$app  = (string) ( $data['app'] ?? '' );
			$name = trim( (string) ( $data['name'] ?? $data['label'] ?? '' ) );
			if ( '' === $name ) {
				$name = ucwords( str_replace( '_', ' ', (string) ( $data['event'] ?? __( 'a step', 'zaplane' ) ) ) );
			}
			$entry   = '' !== $app ? Catalog::entry( $app ) : null;
			$steps[] = [
				'name' => $name,
				'app'  => (string) ( $entry['name'] ?? ( '' !== $app ? $app : __( 'an app', 'zaplane' ) ) ),
			];
		}

		if ( 1 === count( $steps ) ) {
			return sprintf(
				/* translators: 1: app name, e.g. Facebook Messenger, 2: step name. */
				__( 'Link a %1$s account to “%2$s” before turning this workflow on.', 'zaplane' ),
				$steps[0]['app'],
				$steps[0]['name']
			);
		}

		return sprintf(
			/* translators: %s: list of steps, e.g. “Send Reply” (Facebook Messenger), “Write the Reply” (AI). */
			__( 'Link an account to each of these steps before turning this workflow on: %s.', 'zaplane' ),
			implode( ', ', array_map( static fn( $s ) => sprintf( '“%s” (%s)', $s['name'], $s['app'] ), $steps ) )
		);
	}

	private static function format_errors( array $errors ): string {
		$lines = [];

		foreach ( $errors as $error ) {
			$where   = (string) ( $error['where'] ?? '' );
			$lines[] = ( '' !== $where ? $where . ': ' : '' ) . (string) ( $error['message'] ?? '' );
		}

		return implode( ' | ', $lines );
	}

	/**
	 * @param array<int,array<string,string>> $warnings
	 * @return array<string,mixed>
	 */
	private static function saved( int $workflow_id, WorkflowVersion $version, array $warnings, bool $isNew ): array {
		return [
			'workflow_id'    => $workflow_id,
			'version_id'     => (int) $version->id,
			'version_number' => $version->version_number,
			'hash'           => $version->graph_hash,
			'new_version'    => $isNew,
			'warnings'       => $warnings,
		];
	}
}
