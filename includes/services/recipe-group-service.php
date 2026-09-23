<?php

namespace Zaplane\Services;

use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\WorkflowAuthor;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Database\ORM\DB;
use Zaplane\Models\Connection;
use Zaplane\Models\Folder;
use Zaplane\Models\Recipe;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up a recipe: describes what its setup asks, and creates the workflows its
 * answers pick. A group recipe's workflows go in a folder of their own; a recipe
 * of one workflow goes through the same setup, and creates just that workflow.
 *
 * What to create is worked out by RecipeGroupBuilder; this class reads the site
 * (apps, plugins, connections, folders) and writes the result.
 */
class RecipeGroupService {

	/**
	 * What the setup shows: the group's workflows and their options, the values it
	 * asks for and which steps read them, the apps it uses, and the folders it was
	 * set up in before.
	 *
	 * @return array<string,mixed>
	 */
	public function setup( Recipe $recipe ): array {
		$group = self::group_of( $recipe );
		$usage = RecipeGroupBuilder::value_usage( $group );

		$workflows = [];
		$apps      = [];

		foreach ( RecipeGroupBuilder::workflows( $group ) as $workflow ) {
			$icons = [];

			foreach ( $workflow['graph']['nodes'] as $node ) {
				$app = (string) ( $node['data']['app'] ?? '' );

				if ( ! empty( $node['data']['icon'] ) ) {
					$icons[] = (string) $node['data']['icon'];
				}

				if ( '' !== $app && ! in_array( $workflow['key'], $apps[ $app ] ?? [], true ) ) {
					$apps[ $app ][] = $workflow['key'];
				}
			}

			$workflows[] = [
				'key'         => $workflow['key'],
				'title'       => $workflow['title'],
				'description' => $workflow['description'],
				'default'     => $workflow['default'],
				'icons'       => array_values( array_unique( $icons ) ),
				'steps'       => RecipeGroupBuilder::steps( $workflow ),
				'options'     => array_map(
					static function ( $option ) {
						unset( $option['nodes'] );
						return $option;
					},
					$workflow['options']
				),
			];
		}//end foreach

		$values = [];
		foreach ( RecipeGroupBuilder::values( $group ) as $value ) {
			$value['used_by'] = $usage[ $value['key'] ] ?? [];
			$values[]         = $value;
		}

		return [
			'id'           => (int) $recipe->id,
			'type'         => $recipe->isGroup() ? Recipe::TYPE_GROUP : Recipe::TYPE_WORKFLOW,
			'title'        => (string) $recipe->title,
			'description'  => (string) ( $recipe->description ?? '' ),
			'folder_title' => (string) ( $group['folder'] ?? $recipe->title ),
			'workflows'    => $workflows,
			'values'       => $values,
			'apps'         => $this->apps( $apps ),
			'folders'      => $recipe->isGroup() ? $this->earlier_setups( $recipe ) : [],
		];
	}

	/**
	 * Creates the workflows the answers pick, as drafts: a group's in a new folder,
	 * and a recipe of one workflow's on its own, named `title`. With `activate`,
	 * each is then switched on if it passes the checks a workflow has to pass to go
	 * live; one that doesn't stays a draft, and says why.
	 *
	 * @param array<string,mixed> $given { workflows, options, values, connections: {app: connection id}, folder_title, title, activate }
	 * @return array{folder: array{id:int,title:string}|null, workflows: array<int,array<string,mixed>>}
	 * @throws \InvalidArgumentException When the answers are not valid.
	 */
	public function install( Recipe $recipe, array $given ): array {
		$group  = self::group_of( $recipe );
		$single = ! $recipe->isGroup();

		if ( $single ) {
			// There is nothing to pick: the recipe is its workflow.
			$given['workflows'] = array_fill_keys( array_column( RecipeGroupBuilder::workflows( $group ), 'key' ), true );
		}

		$answers     = RecipeGroupBuilder::answers( $group, $given );
		$built       = RecipeGroupBuilder::build( $group, $answers );
		$connections = $this->connections( $given['connections'] ?? [] );

		// The setup wizard: every app that needs an account gets one, or
		// nothing is created (a workflow without it can't be turned on).
		if ( ! empty( $given['require_connections'] ) ) {
			$this->require_connections( $built, $connections );
		}

		$title = '';
		foreach ( $single ? [ 'title', 'folder_title' ] : [ 'folder_title' ] as $field ) {
			if ( '' === $title && is_scalar( $given[ $field ] ?? null ) ) {
				$title = sanitize_text_field( (string) $given[ $field ] );
			}
		}
		if ( '' === $title ) {
			$title = $single ? (string) $recipe->title : (string) ( $group['folder'] ?? $recipe->title );
		}

		$user_id = get_current_user_id();

		list( $folder, $workflows ) = DB::transaction(
			function () use ( $recipe, $built, $answers, $connections, $title, $user_id, $single ) {
				$folder = $single ? null : Folder::create(
					[
						'title'      => $this->unused_folder_title( $title ),
						'created_by' => $user_id,
						'recipe_id'  => (int) $recipe->id,
					]
				);

				$workflows = [];

				foreach ( $built as $item ) {
					$graph = $this->with_connections( $item['graph'], $connections );

					$attributes = [
						'user_id'           => $user_id,
						'title'             => $single ? $title : $item['title'],
						'status'            => 'draft',
						'layout'            => $item['layout'],
						'integration_icons' => $this->icons( $graph ),
					];

					if ( $folder ) {
						$attributes['folder_id'] = (int) $folder->id;
					}

					$workflow = Workflow::create( $attributes );

					WorkflowVersion::create(
						[
							'workflow_id'    => (int) $workflow->id,
							'graph_json'     => $graph,
							'graph_hash'     => hash( 'sha256', (string) wp_json_encode( $graph ) ),
							'is_active'      => true,
							'version_number' => 1,
						]
					);

					$workflows[ $item['key'] ] = $workflow;
				}//end foreach

				if ( $folder ) {
					$folder->setup = [
						'recipe_title' => (string) $recipe->title,
						'answers'      => $answers,
						'connections'  => $connections,
						'workflows'    => array_map(
							static function ( $workflow ) {
								return (int) $workflow->id;
							},
							$workflows
						),
					];
					$folder->save();
				}

				return [ $folder, $workflows ];
			}
		);

		$activate = filter_var( $given['activate'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$created  = [];

		foreach ( $workflows as $key => $workflow ) {
			$status = 'draft';
			$error  = null;

			if ( $activate ) {
				try {
					$status = (string) WorkflowAuthor::set_status( (int) $workflow->id, 'active' )['status'];
				} catch ( \InvalidArgumentException $e ) {
					$error = $e->getMessage();
				}
			}

			$created[] = [
				'key'    => (string) $key,
				'id'     => (int) $workflow->id,
				'title'  => (string) $workflow->title,
				'status' => $status,
				'error'  => $error,
			];
		}

		$result = [
			'folder'    => $folder ? [
				'id'    => (int) $folder->id,
				'title' => (string) $folder->title,
			] : null,
			'workflows' => $created,
		];

		if ( $single ) {
			return $result;
		}

		/**
		 * Fires once a group recipe's workflows are created, and switched on if asked.
		 *
		 * @param array  $result What was created: the folder, and each workflow's key, id, title, status and error.
		 * @param Recipe $recipe The group recipe they came from.
		 */
		do_action( 'zaplane_recipe_group_installed', $result, $recipe );

		return $result;
	}

	/**
	 * A recipe's blueprint in the shape a group recipe has.
	 *
	 * A registered recipe is stored that way already, one workflow or several. A
	 * recipe saved from a workflow holds that workflow's versions instead, and
	 * becomes a group of one from its active version, without the connections of
	 * the site it was saved on.
	 *
	 * @return array<string,mixed>
	 */
	public static function group_of( Recipe $recipe ): array {
		$blueprint = $recipe->getBlueprint();

		if ( isset( $blueprint['workflows'] ) ) {
			return $blueprint;
		}

		$graph = [];
		foreach ( (array) ( $blueprint['versions'] ?? [] ) as $version ) {
			$version_graph = $version['graph_json'] ?? [];
			$version_graph = is_string( $version_graph ) ? json_decode( $version_graph, true ) : $version_graph;

			if ( is_array( $version_graph ) && ( ! $graph || ! empty( $version['is_active'] ) ) ) {
				$graph = $version_graph;
			}
		}

		foreach ( (array) ( $graph['nodes'] ?? [] ) as $index => $node ) {
			unset( $graph['nodes'][ $index ]['data']['connection_id'] );
		}

		return [
			'folder'    => (string) $recipe->title,
			'values'    => [],
			'workflows' => [
				[
					'key'     => 'workflow',
					'title'   => (string) $recipe->title,
					'layout'  => (string) ( $blueprint['layout'] ?? 'LR' ),
					'graph'   => $graph,
					'options' => [],
				],
			],
		];
	}

	/**
	 * @param array<string,array<int,string>> $apps App slug => keys of the workflows that use it.
	 * @return array<int,array<string,mixed>>
	 */
	private function apps( array $apps ): array {
		$out = [];

		foreach ( $apps as $slug => $workflow_keys ) {
			$entry = Catalog::entry( (string) $slug );

			$out[] = [
				'slug'                => (string) $slug,
				'name'                => (string) ( $entry['name'] ?? $slug ),
				'icon'                => (string) ( $entry['icon'] ?? '' ),
				'category'            => (string) ( $entry['category'] ?? 'app' ),
				'requires_connection' => ! empty( $entry['requires_connection'] ),
				'plugin_active'       => $this->plugins_active( (string) $slug ),
				'workflows'           => $workflow_keys,
			];
		}

		return $out;
	}

	/**
	 * Whether the plugins an app needs are active. An app that needs none is ready.
	 */
	private function plugins_active( string $slug ): bool {
		$integration = IntegrationLoader::get( $slug );
		$required    = $integration ? (array) get_class( $integration )::get_required_plugins() : [];

		if ( ! $required ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $required as $basename ) {
			if ( ! is_plugin_active( (string) $basename ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<int,array{id:int,title:string}>
	 */
	private function earlier_setups( Recipe $recipe ): array {
		$folders = [];

		foreach ( Folder::where( 'recipe_id', (int) $recipe->id )->orderBy( 'id', 'desc' )->fresh()->get() as $folder ) {
			$folders[] = [
				'id'    => (int) $folder->id,
				'title' => (string) $folder->title,
			];
		}

		return $folders;
	}

	/**
	 * The connections picked in the setup, by app.
	 *
	 * @param mixed $given
	 * @return array<string,int>
	 * @throws \InvalidArgumentException When one doesn't exist, or belongs to another app.
	 */
	private function connections( $given ): array {
		$connections = [];

		foreach ( is_array( $given ) ? $given : [] as $app => $id ) {
			$id = (int) $id;

			if ( $id <= 0 ) {
				continue;
			}

			$connection = Connection::find( $id );

			if ( ! $connection || (string) $connection->app !== (string) $app ) {
				throw new \InvalidArgumentException( esc_html__( 'A connection picked in the setup no longer exists. Pick one again.', 'zaplane' ) );
			}

			$connections[ (string) $app ] = $id;
		}

		return $connections;
	}

	/**
	 * @param array<int,array<string,mixed>> $built
	 * @param array<string,int>              $connections
	 * @throws \InvalidArgumentException Naming the apps still missing one.
	 */
	private function require_connections( array $built, array $connections ): void {
		$missing = [];
		foreach ( $built as $item ) {
			foreach ( (array) ( $item['graph']['nodes'] ?? [] ) as $node ) {
				$app   = (string) ( $node['data']['app'] ?? '' );
				$entry = '' !== $app ? Catalog::entry( $app ) : null;
				if ( ! empty( $entry['requires_connection'] ) && empty( $connections[ $app ] ) && empty( $node['data']['connection_id'] ) ) {
					$missing[ $app ] = (string) ( $entry['name'] ?? $app );
				}
			}
		}
		if ( $missing ) {
			throw new \InvalidArgumentException(
				esc_html( sprintf(
					/* translators: %s: app names, e.g. Messenger. */
					__( 'Link a connection for %s before creating these workflows.', 'zaplane' ),
					implode( ', ', $missing )
				) )
			);
		}
	}

	/**
	 * @param array<string,mixed> $graph
	 * @param array<string,int>   $connections
	 * @return array<string,mixed>
	 */
	private function with_connections( array $graph, array $connections ): array {
		foreach ( $graph['nodes'] as $index => $node ) {
			$app = (string) ( $node['data']['app'] ?? '' );

			if ( isset( $connections[ $app ] ) ) {
				// The editor stores the id as a string.
				$graph['nodes'][ $index ]['data']['connection_id'] = (string) $connections[ $app ];
			}
		}

		return $graph;
	}

	/**
	 * @param array<string,mixed> $graph
	 * @return array<int,string>
	 */
	private function icons( array $graph ): array {
		$icons = [];

		foreach ( $graph['nodes'] as $node ) {
			if ( ! empty( $node['data']['icon'] ) ) {
				$icons[] = (string) $node['data']['icon'];
			}
		}

		return array_values( array_unique( $icons ) );
	}

	/**
	 * The title, numbered when a folder already has it, so a second setup of the
	 * same group can be told apart from the first.
	 */
	private function unused_folder_title( string $title ): string {
		$candidate = $title;

		for ( $number = 2; $number < 100 && Folder::where( 'title', $candidate )->fresh()->first(); $number++ ) {
			$candidate = $title . ' ' . $number;
		}

		return $candidate;
	}
}
