<?php

namespace Zaplane\Modules\Inbox\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which channels and sources are connected, read from active workflows.
 *
 * The inbox has no on/off switches of its own for them: a channel is on
 * while a workflow that feeds it (Messenger: "Add Event to Inbox") is
 * active, and replies go out while one that delivers them ("Send Inbox
 * Reply", or "Reply to Deliver" for a source) is active. Pause the workflow
 * and the channel stops; the connection is the one linked in the workflow.
 */
class Connectors {

	/** @var array<int,array{workflow_id:int,title:string,node:array<string,mixed>}>|null */
	private static ?array $nodes = null;

	/**
	 * Every step of every active workflow that belongs to the inbox's
	 * wiring. Read once per request.
	 *
	 * @return array<int,array{workflow_id:int,title:string,node:array<string,mixed>}>
	 */
	private static function nodes(): array {
		if ( null !== self::$nodes ) {
			return self::$nodes;
		}
		self::$nodes = [];

		global $wpdb;
		$workflows = $wpdb->prefix . 'zaplane_workflows';
		$versions  = $wpdb->prefix . 'zaplane_workflow_versions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Cached for the request.
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT w.id, w.title, v.graph_json FROM %i w JOIN %i v ON v.workflow_id = w.id AND v.is_active = 1
			WHERE w.status = 'active' AND ( v.graph_json LIKE %s OR v.graph_json LIKE %s OR v.graph_json LIKE %s )",
			$workflows,
			$versions,
			'%"inbox_%',
			'%"reply_requested"%',
			'%"receive_message"%'
		), ARRAY_A );

		foreach ( $rows as $row ) {
			$graph = json_decode( (string) $row['graph_json'], true );
			foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
				if ( is_array( $node['data'] ?? null ) ) {
					self::$nodes[] = [
						'workflow_id' => (int) $row['id'],
						'title'       => (string) $row['title'],
						'node'        => $node['data'],
					];
				}
			}
		}

		/**
		 * The workflow steps that connect channels and sources to the inbox,
		 * from active workflows. Add to it to connect a channel another way.
		 *
		 * @param array<int,array{workflow_id:int,title:string,node:array<string,mixed>}> $nodes
		 */
		self::$nodes = (array) apply_filters( 'zaplane/inbox/connector_nodes', self::$nodes );
		return self::$nodes;
	}

	/**
	 * @return array{workflow_id:int,title:string,node:array<string,mixed>}|null
	 */
	private static function find( string $app, string $event, ?callable $match = null ): ?array {
		foreach ( self::nodes() as $entry ) {
			$node = $entry['node'];
			if ( $app === ( $node['app'] ?? '' ) && $event === ( $node['event'] ?? '' ) && ( ! $match || $match( $node ) ) ) {
				return $entry;
			}
		}
		return null;
	}

	/** An active workflow feeds this channel into the inbox. */
	public static function receives( string $channel ): bool {
		return null !== self::find( $channel, 'inbox_receive' );
	}

	/** An active workflow sends inbox replies on this channel. */
	public static function delivers( string $channel ): bool {
		return null !== self::find( $channel, 'inbox_send' );
	}

	/** An active "Reply to Deliver" workflow listens for this source. */
	public static function delivers_source( string $source ): bool {
		return null !== self::find( 'inbox', 'reply_requested', static function ( $node ) use ( $source ) {
			return Sources::key( (string) ( $node['config']['source'] ?? '' ) ) === $source;
		} );
	}

	/**
	 * The connection a channel's workflows use (the sending one first, since
	 * it needs the token), or 0.
	 */
	public static function connection_id( string $channel ): int {
		foreach ( [ 'inbox_send', 'inbox_receive' ] as $event ) {
			$entry = self::find( $channel, $event, static fn( $node ) => ! empty( $node['connection_id'] ) );
			if ( $entry ) {
				return (int) $entry['node']['connection_id'];
			}
		}
		return 0;
	}

	/**
	 * For the settings screen: the workflows doing each job.
	 *
	 * @return array{receive:array<int,array{id:int,title:string}>,deliver:array<int,array{id:int,title:string}>}
	 */
	public static function workflows( string $channel ): array {
		$out = [
			'receive' => [],
			'deliver' => [],
		];
		foreach ( self::nodes() as $entry ) {
			$node = $entry['node'];
			$job  = null;
			if ( $channel === ( $node['app'] ?? '' ) ) {
				$job = 'inbox_receive' === ( $node['event'] ?? '' ) ? 'receive' : ( 'inbox_send' === ( $node['event'] ?? '' ) ? 'deliver' : null );
			} elseif ( 'inbox' === ( $node['app'] ?? '' ) && 'reply_requested' === ( $node['event'] ?? '' ) && Sources::key( (string) ( $node['config']['source'] ?? '' ) ) === $channel ) {
				$job = 'deliver';
			} elseif ( 'inbox' === ( $node['app'] ?? '' ) && 'receive_message' === ( $node['event'] ?? '' ) && Sources::key( (string) ( $node['config']['source'] ?? '' ) ) === $channel ) {
				$job = 'receive';
			}
			if ( $job ) {
				$out[ $job ][ $entry['workflow_id'] ] = [
					'id'    => $entry['workflow_id'],
					'title' => $entry['title'],
				];
			}
		}
		return [
			'receive' => array_values( $out['receive'] ),
			'deliver' => array_values( $out['deliver'] ),
		];
	}

	/**
	 * The recipes tagged "inbox", each with what it connects, where it's set
	 * up (folders made from it, and their workflows' status) and whether the
	 * channel or source is live right now. The settings screen is built from
	 * this, so a workflow turned on or off anywhere shows here.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function catalog(): array {
		\Zaplane\Recipes\Registry::instance()->sync();

		$out = [];
		foreach ( \Zaplane\Models\Recipe::orderBy( 'title', 'asc' )->get()->all() as $recipe ) {
			if ( ! in_array( 'inbox', $recipe->tags(), true ) ) {
				continue;
			}
			$meta   = (array) ( $recipe->getBlueprint()['inbox'] ?? [] );
			$target = (string) ( $meta['channel'] ?? $meta['source'] ?? '' );

			$installs = [];
			foreach ( \Zaplane\Models\Folder::where( 'recipe_id', (int) $recipe->id )->orderBy( 'id', 'asc' )->fresh()->get()->all() as $folder ) {
				$workflows = [];
				foreach ( \Zaplane\Models\Workflow::where( 'folder_id', (int) $folder->id )->orderBy( 'id', 'asc' )->fresh()->get()->all() as $workflow ) {
					$workflows[] = [
						'id'     => (int) $workflow->id,
						'title'  => (string) $workflow->title,
						'status' => (string) $workflow->status,
					];
				}
				if ( $workflows ) {
					$installs[] = [
						'folder_id' => (int) $folder->id,
						'title'     => (string) $folder->title,
						'workflows' => $workflows,
					];
				}
			}

			$live       = '' !== $target ? self::workflows( $target ) : [ 'receive' => [], 'deliver' => [] ];
			$connection = null;
			if ( isset( $meta['channel'] ) && ( $cid = self::connection_id( $target ) ) ) {
				$row        = \Zaplane\Models\Connection::find( $cid );
				$connection = $row ? [ 'id' => $cid, 'name' => (string) $row->name ] : null;
			}
			$out[] = [
				'id'          => (int) $recipe->id,
				'slug'        => (string) $recipe->slug,
				'title'       => (string) $recipe->title,
				'description' => (string) $recipe->description,
				'icons'       => $recipe->integration_icons ?? [],
				'recipe'      => $recipe->toResponse(),
				'kind'        => isset( $meta['channel'] ) ? 'channel' : 'source',
				'target'      => $target,
				'installs'    => $installs,
				'receiving'   => count( $live['receive'] ) > 0,
				'delivering'  => count( $live['deliver'] ) > 0,
				// More than one active workflow doing the same job sends every
				// reply twice: say so.
				'duplicates'  => count( $live['receive'] ) > 1 || count( $live['deliver'] ) > 1,
				'live'        => $live,
				'connection'  => $connection,
			];
		}
		return $out;
	}

	/** For tests, and after a workflow changes within one request. */
	public static function reset(): void {
		self::$nodes = null;
	}
}
