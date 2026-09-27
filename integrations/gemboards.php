<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GemBoards integration.
 *
 * Uses GemBoards' public entity layer and native hooks so cache invalidation,
 * cleanup hooks, notifications and downstream automations keep working.
 */
class Gemboards extends IntegrationBase {

	public static function get_slug(): string {
		return 'gemboards';
	}

	public static function get_name(): string {
		return 'GemBoards';
	}

	public static function get_icon(): string {
		return 'gemboards.svg';
	}

	public static function get_required_plugins(): array {
		return [ 'gemboards/gemboards.php' ];
	}

	private static function require_gemboards(): void {
		if ( ! class_exists( '\GemBoards\Utils\Helper' ) ) {
			throw new \RuntimeException( 'GemBoards must be active before this action can run.' );
		}
	}

	private static function event( array $node ): string {
		return (string) ( $node['event'] ?? $node['data']['event'] ?? $node['flow_details']['event'] ?? '' );
	}

	private static function config( array $node ): array {
		foreach ( [ $node['config'] ?? null, $node['data']['config'] ?? null, $node['flow_details'] ?? null ] as $value ) {
			if ( is_array( $value ) ) {
				return $value;
			}
		}
		return $node;
	}

	private static function text_field( string $key, string $label, bool $required = false, array $extra = [] ): array {
		return array_merge(
			[
				'key'      => $key,
				'label'    => $label,
				'type'     => 'text',
				'required' => $required,
			],
			$extra
		);
	}

	private static function number_field( string $key, string $label, bool $required = false, array $extra = [] ): array {
		return self::text_field( $key, $label, $required, array_merge( [ 'type' => 'number' ], $extra ) );
	}

	private static function checkbox_field( string $key, string $label ): array {
		return [
			'key'      => $key,
			'label'    => $label,
			'type'     => 'checkbox',
			'required' => false,
		];
	}

	private static function dynamic_select( string $key, string $label, string $query, bool $required = false, array $extra = [] ): array {
		return array_merge(
			[
				'key'      => $key,
				'label'    => $label,
				'type'     => 'select',
				'required' => $required,
				'dynamic'  => [
					'integration' => 'gemboards',
					'query'       => $query,
					'select'      => [ 'name', 'label' ],
				],
			],
			$extra
		);
	}

	private static function normalize( $value ) {
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( 'Y-m-d H:i:s' );
		}
		if ( $value instanceof \WP_Post ) {
			return [
				'ID'           => (int) $value->ID,
				'post_title'   => (string) $value->post_title,
				'post_content' => (string) $value->post_content,
				'post_status'  => (string) $value->post_status,
				'post_type'    => (string) $value->post_type,
				'post_author'  => (int) $value->post_author,
			];
		}
		if ( $value instanceof \WP_User ) {
			return [
				'ID'           => (int) $value->ID,
				'display_name' => (string) $value->display_name,
				'user_email'   => (string) $value->user_email,
				'user_login'   => (string) $value->user_login,
			];
		}
		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_data' ) ) {
				$data = self::normalize( $value->get_data() );
				if ( method_exists( $value, 'get_id' ) ) {
					$data['id'] = (int) $value->get_id();
				}
				return $data;
			}
			return self::normalize( get_object_vars( $value ) );
		}
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $key => $item ) {
				$out[ $key ] = self::normalize( $item );
			}
			return $out;
		}
		return $value;
	}

	private static function project_payload( $project ): array {
		if ( ! $project || ! is_object( $project ) || ! method_exists( $project, 'get_id' ) ) {
			return [];
		}
		$data = (array) self::normalize( $project );
		$data['event_object'] = 'project';
		if ( method_exists( $project, 'get_background_type' ) ) {
			$data['background_type'] = self::normalize( $project->get_background_type() );
			$data['background']      = self::normalize( $project->get_background() );
		}
		return $data;
	}

	private static function board_payload( $board ): array {
		if ( ! $board || ! is_object( $board ) || ! method_exists( $board, 'get_id' ) ) {
			return [];
		}
		$data = (array) self::normalize( $board );
		$data['event_object'] = 'board';
		$data['columns'] = [];
		if ( method_exists( $board, 'get_columns' ) ) {
			foreach ( (array) $board->get_columns() as $column ) {
				$data['columns'][] = self::column_payload( $column );
			}
		}
		return $data;
	}

	private static function column_payload( $column ): array {
		if ( ! $column || ! is_object( $column ) || ! method_exists( $column, 'get_id' ) ) {
			return [];
		}
		$data = (array) self::normalize( $column );
		$data['event_object'] = 'column';
		return $data;
	}

	private static function card_payload( $card ): array {
		if ( ! $card || ! is_object( $card ) || ! method_exists( $card, 'get_id' ) ) {
			return [];
		}
		$data = (array) self::normalize( $card );
		$data['event_object'] = 'card';

		$task = method_exists( $card, 'get_task' ) ? $card->get_task() : null;
		if ( $task instanceof \WP_Post ) {
			$data['title']          = (string) $task->post_title;
			$data['description']    = (string) $task->post_content;
			$data['parent_task_id'] = (int) $task->post_parent;
			$data['created_by']     = (int) $task->post_author;
		}

		$data['assignees'] = [];
		if ( method_exists( $card, 'get_assignees' ) ) {
			foreach ( (array) $card->get_assignees( true ) as $assignee ) {
				$user_id = method_exists( $assignee, 'get_user_id' ) ? (int) $assignee->get_user_id() : 0;
				$user    = $user_id ? get_userdata( $user_id ) : false;
				$data['assignees'][] = [
					'id'    => $user_id,
					'name'  => $user ? (string) $user->display_name : '',
					'email' => $user ? (string) $user->user_email : '',
				];
			}
		}

		if ( ! empty( $data['task_id'] ) ) {
			$labels = wp_get_object_terms( (int) $data['task_id'], 'gemboards_label' );
			$data['labels'] = is_wp_error( $labels ) ? [] : array_values(
				array_map(
					static function ( $term ) {
						return [
							'id'   => (int) $term->term_id,
							'name' => (string) $term->name,
							'slug' => (string) $term->slug,
						];
					},
					(array) $labels
				)
			);
			$priority = wp_get_object_terms( (int) $data['task_id'], 'gemboards_priority' );
			$data['priority'] = ! empty( $priority ) && ! is_wp_error( $priority )
				? [
					'id'   => (int) $priority[0]->term_id,
					'name' => (string) $priority[0]->name,
					'slug' => (string) $priority[0]->slug,
				]
				: null;
		}
		return $data;
	}

	private static function attachment_payload( int $attachment_id, int $task_id ): array {
		$payload = [
			'attachment_id' => $attachment_id,
			'task_id'       => $task_id,
			'card_id'       => 0,
		];

		if ( class_exists( '\GemBoards\Entities\TaskAttachment' ) ) {
			try {
				$attachment = new \GemBoards\Entities\TaskAttachment( $attachment_id );
				if ( method_exists( $attachment, 'get_id' ) && $attachment->get_id() ) {
					$payload = array_merge( $payload, (array) self::normalize( $attachment ) );
				}
			} catch ( \Throwable $e ) {
				// Deletion events may fire after the row is gone; IDs are still useful.
			}
		}

		$card = self::find_card_by_task( $task_id );
		if ( $card ) {
			$payload['card_id'] = (int) $card->get_id();
			$payload['card']    = self::card_payload( $card );
		}
		return $payload;
	}

	private static function find_card_by_task( int $task_id ) {
		if ( ! $task_id || ! class_exists( '\GemBoards\Collections\CardCollection' ) ) {
			return null;
		}
		try {
			$items = ( new \GemBoards\Collections\CardCollection(
				[
					'per_page' => 1,
					'where'    => [
						[
							'key'   => 'task_id',
							'value' => $task_id,
						],
					],
				]
			) )->get_results();
			return $items ? reset( $items ) : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function entity_collection( string $kind, array $where = [], int $per_page = 200 ): array {
		self::require_gemboards();
		$classes = [
			'project' => '\GemBoards\Collections\ProjectCollection',
			'board'   => '\GemBoards\Collections\BoardCollection',
			'column'  => '\GemBoards\Collections\ColumnCollection',
			'card'    => '\GemBoards\Collections\CardCollection',
		];
		$class = $classes[ $kind ] ?? '';
		if ( ! $class || ! class_exists( $class ) ) {
			return [];
		}
		$args = [ 'per_page' => $per_page ];
		if ( $where ) {
			$args['where'] = $where;
		}
		try {
			return (array) ( new $class( $args ) )->get_results();
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	private static function helper_get( string $kind, int $id ) {
		if ( ! $id ) {
			return null;
		}
		$method = 'get_' . $kind;
		return method_exists( '\GemBoards\Utils\Helper', $method ) ? \GemBoards\Utils\Helper::$method( $id ) : null;
	}

	private static function id_from_config( array $config, string $key, array $input = [] ): int {
		$value = $config[ $key ] ?? $input[ $key ] ?? 0;
		if ( is_array( $value ) ) {
			$value = $value['name'] ?? $value['value'] ?? $value['id'] ?? 0;
		}
		return absint( $value );
	}

	public static function get_triggers(): array {
		return [
			'project_created'       => [ 'label' => 'Project Created', 'hook' => 'gemboards/project/after/object_save' ],
			'project_updated'       => [ 'label' => 'Project Updated', 'hook' => 'gemboards/project/updated' ],
			'project_deleted'       => [ 'label' => 'Project Deleted', 'hook' => 'gemboards/project/deleted' ],
			'board_created'         => [ 'label' => 'Board Created', 'hook' => 'gemboards/board/after/object_save' ],
			'board_updated'         => [ 'label' => 'Board Updated', 'hook' => 'gemboards/board/updated' ],
			'board_deleted'         => [ 'label' => 'Board Deleted', 'hook' => 'gemboards/board/deleted' ],
			'column_created'        => [ 'label' => 'Column Created', 'hook' => 'gemboards/column/after/object_save' ],
			'column_updated'        => [ 'label' => 'Column Updated', 'hook' => 'gemboards/column/updated' ],
			'column_deleted'        => [ 'label' => 'Column Deleted', 'hook' => 'gemboards/column/deleted' ],
			'card_created'          => [ 'label' => 'Card Created', 'hook' => 'gemboards/card/created' ],
			'card_updated'          => [ 'label' => 'Card Updated', 'hook' => 'gemboards/card/updated' ],
			'card_moved'            => [ 'label' => 'Card Moved to Another Stage', 'hook' => 'gemboards/card/moved' ],
			'card_archived'         => [ 'label' => 'Card Archived', 'hook' => 'gemboards/card/archived' ],
			'card_restored'         => [ 'label' => 'Card Restored', 'hook' => 'gemboards/card/restored' ],
			'card_deleted'          => [ 'label' => 'Card Deleted', 'hook' => 'gemboards/card/deleted' ],
			'card_user_assigned'    => [ 'label' => 'User Assigned to Card', 'hook' => 'gemboards/card/user_assigned' ],
			'card_user_removed'     => [ 'label' => 'User Removed from Card', 'hook' => 'gemboards/card/user_removed' ],
			'attachment_added'      => [ 'label' => 'Attachment Added to Card', 'hook' => 'gemboards/task/attachment/created' ],
			'attachment_removed'    => [ 'label' => 'Attachment Removed from Card', 'hook' => 'gemboards/task/attachment/deleted' ],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		self::require_gemboards();
		$event = self::event( $node );

		if ( in_array( $event, [ 'project_created', 'board_created', 'column_created' ], true ) ) {
			$entity = $args[0] ?? null;
			$create = (bool) ( $args[1] ?? false );
			if ( ! $create || ! is_object( $entity ) ) {
				return false;
			}
			$payload = 'project_created' === $event
				? self::project_payload( $entity )
				: ( 'board_created' === $event ? self::board_payload( $entity ) : self::column_payload( $entity ) );
			return array_merge( [ 'event' => $event ], $payload );
		}

		if ( in_array( $event, [ 'project_updated', 'board_updated', 'column_updated', 'card_updated' ], true ) ) {
			$entity = $args[1] ?? null;
			if ( ! is_object( $entity ) ) {
				return false;
			}
			$payload = [
				'project_updated' => [ self::class, 'project_payload' ],
				'board_updated'   => [ self::class, 'board_payload' ],
				'column_updated'  => [ self::class, 'column_payload' ],
				'card_updated'    => [ self::class, 'card_payload' ],
			];
			return array_merge( [ 'event' => $event ], call_user_func( $payload[ $event ], $entity ) );
		}

		if ( in_array( $event, [ 'project_deleted', 'board_deleted', 'column_deleted', 'card_deleted' ], true ) ) {
			$id     = absint( $args[0] ?? 0 );
			$entity = $args[1] ?? null;
			$data   = is_object( $entity ) ? self::normalize( $entity ) : [];
			return [
				'event'        => $event,
				'id'           => $id,
				'entity'       => $data,
				'force_delete' => (bool) ( $args[2] ?? false ),
			];
		}

		if ( 'card_created' === $event ) {
			$card = self::helper_get( 'card', absint( $args[0] ?? 0 ) );
			return $card ? array_merge( [ 'event' => $event ], self::card_payload( $card ) ) : false;
		}

		if ( 'card_moved' === $event ) {
			$card_id   = absint( $args[0] ?? 0 );
			$column_id = absint( $args[1] ?? 0 );
			$card      = self::helper_get( 'card', $card_id );
			return $card ? array_merge(
				[
					'event'              => $event,
					'moved_to_column_id' => $column_id,
				],
				self::card_payload( $card )
			) : false;
		}

		if ( in_array( $event, [ 'card_archived', 'card_restored' ], true ) ) {
			$card = $args[0] ?? null;
			return is_object( $card ) ? array_merge( [ 'event' => $event ], self::card_payload( $card ) ) : false;
		}

		if ( in_array( $event, [ 'card_user_assigned', 'card_user_removed' ], true ) ) {
			$card_id     = absint( $args[0] ?? 0 );
			$user_id     = absint( $args[1] ?? 0 );
			$assignee_id = absint( $args[2] ?? 0 );
			$user        = $user_id ? get_userdata( $user_id ) : false;
			$card        = self::helper_get( 'card', $card_id );
			return [
				'event'       => $event,
				'card_id'     => $card_id,
				'user_id'     => $user_id,
				'assignee_id' => $assignee_id,
				'user'        => $user ? self::normalize( $user ) : [],
				'card'        => $card ? self::card_payload( $card ) : [],
			];
		}

		if ( in_array( $event, [ 'attachment_added', 'attachment_removed' ], true ) ) {
			return array_merge(
				[ 'event' => $event ],
				self::attachment_payload( absint( $args[0] ?? 0 ), absint( $args[1] ?? 0 ) )
			);
		}
		return false;
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$base = [
			'event'        => $trigger,
			'id'           => 101,
			'title'        => 'Sample GemBoards item',
			'event_object' => 'card',
		];
		if ( false !== strpos( $trigger, 'project_' ) ) {
			return [ 'event' => $trigger, 'id' => 11, 'title' => 'Website Redesign', 'owner_id' => 1, 'project_status' => 'active', 'event_object' => 'project' ];
		}
		if ( false !== strpos( $trigger, 'board_' ) ) {
			return [ 'event' => $trigger, 'id' => 21, 'project_id' => 11, 'title' => 'Sprint Board', 'board_type' => 'kanban', 'board_status' => 'active', 'event_object' => 'board' ];
		}
		if ( false !== strpos( $trigger, 'column_' ) ) {
			return [ 'event' => $trigger, 'id' => 31, 'board_id' => 21, 'title' => 'In Progress', 'slug' => 'in-progress', 'event_object' => 'column' ];
		}
		if ( in_array( $trigger, [ 'card_user_assigned', 'card_user_removed' ], true ) ) {
			return [ 'event' => $trigger, 'card_id' => 41, 'user_id' => 1, 'assignee_id' => 51, 'user' => [ 'ID' => 1, 'display_name' => 'Admin' ], 'card' => [ 'id' => 41, 'title' => 'Sample Task' ] ];
		}
		if ( in_array( $trigger, [ 'attachment_added', 'attachment_removed' ], true ) ) {
			return [ 'event' => $trigger, 'attachment_id' => 61, 'task_id' => 71, 'card_id' => 41, 'file_name' => 'brief.pdf' ];
		}
		return array_merge( $base, [ 'board_id' => 21, 'column_id' => 31, 'task_id' => 71, 'description' => 'Sample task description', 'assignees' => [] ] );
	}

	public static function get_actions(): array {
		return [
			'create_project'   => [ 'label' => 'Create Project' ],
			'update_project'   => [ 'label' => 'Update Project' ],
			'get_project'      => [ 'label' => 'Get Project Details' ],
			'search_projects'  => [ 'label' => 'Search Projects' ],
			'create_board'     => [ 'label' => 'Create Board' ],
			'update_board'     => [ 'label' => 'Update Board' ],
			'get_board'        => [ 'label' => 'Get Board Details' ],
			'search_boards'    => [ 'label' => 'Search Boards' ],
			'archive_board'    => [ 'label' => 'Archive Board' ],
			'restore_board'    => [ 'label' => 'Restore Board' ],
			'create_column'    => [ 'label' => 'Create Stage / Column' ],
			'update_column'    => [ 'label' => 'Update Stage / Column' ],
			'get_column'       => [ 'label' => 'Get Stage / Column Details' ],
			'search_columns'   => [ 'label' => 'Search Stages / Columns' ],
			'create_card'      => [ 'label' => 'Create Card' ],
			'update_card'      => [ 'label' => 'Update Card' ],
			'get_card'         => [ 'label' => 'Get Card Details' ],
			'search_cards'     => [ 'label' => 'Search Cards' ],
			'move_card'        => [ 'label' => 'Move Card to Stage' ],
			'archive_card'     => [ 'label' => 'Archive Card' ],
			'restore_card'     => [ 'label' => 'Restore Card' ],
			'delete_card'      => [ 'label' => 'Delete Card' ],
			'assign_user'      => [ 'label' => 'Assign User to Card' ],
			'remove_user'      => [ 'label' => 'Remove User from Card' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'create_project':
				return [
					self::text_field( 'title', 'Project Title', true ),
					self::text_field( 'description', 'Description' ),
					self::dynamic_select( 'owner_id', 'Owner', 'users' ),
					self::text_field( 'color', 'Color (hex)' ),
				];
			case 'update_project':
				return [
					self::dynamic_select( 'project_id', 'Project', 'projects', true ),
					self::text_field( 'title', 'Project Title' ),
					self::text_field( 'description', 'Description' ),
					self::text_field( 'project_status', 'Status (active/archived)' ),
					self::text_field( 'color', 'Color (hex)' ),
				];
			case 'get_project':
				return [ self::dynamic_select( 'project_id', 'Project', 'projects', true ) ];
			case 'search_projects':
				return [ self::text_field( 'search', 'Search text' ) ];
			case 'create_board':
				return [
					self::dynamic_select( 'project_id', 'Project', 'projects', true ),
					self::text_field( 'title', 'Board Title', true ),
					self::text_field( 'board_type', 'Board Type', false, [ 'default' => 'kanban', 'help' => 'Use kanban. Scrum requires GemBoards Pro.' ] ),
					self::dynamic_select( 'owner_id', 'Owner', 'users' ),
					self::text_field( 'icon_color', 'Icon Color (hex)' ),
					self::checkbox_field( 'is_private', 'Private board' ),
				];
			case 'update_board':
				return [
					self::dynamic_select( 'board_id', 'Board', 'boards', true ),
					self::text_field( 'title', 'Board Title' ),
					self::text_field( 'board_type', 'Board Type' ),
					self::text_field( 'board_status', 'Status (active/archived)' ),
					self::text_field( 'icon_color', 'Icon Color (hex)' ),
					self::checkbox_field( 'is_private', 'Private board' ),
				];
			case 'get_board':
				return [ self::dynamic_select( 'board_id', 'Board', 'boards', true ) ];
			case 'search_boards':
				return [
					self::text_field( 'search', 'Search text' ),
					self::dynamic_select( 'project_id', 'Limit to Project', 'projects' ),
				];
			case 'archive_board':
			case 'restore_board':
				return [ self::dynamic_select( 'board_id', 'Board', 'boards', true ) ];
			case 'create_column':
				return [
					self::dynamic_select( 'board_id', 'Board', 'boards', true ),
					self::text_field( 'title', 'Stage / Column Title', true ),
					self::text_field( 'color', 'Color (hex)' ),
					self::number_field( 'sort_order', 'Sort Order' ),
				];
			case 'update_column':
				return [
					self::dynamic_select( 'column_id', 'Stage / Column', 'columns', true ),
					self::text_field( 'title', 'Stage / Column Title' ),
					self::text_field( 'color', 'Color (hex)' ),
					self::number_field( 'sort_order', 'Sort Order' ),
				];
			case 'get_column':
				return [ self::dynamic_select( 'column_id', 'Stage / Column', 'columns', true ) ];
			case 'search_columns':
				return [
					self::text_field( 'search', 'Search text' ),
					self::dynamic_select( 'board_id', 'Limit to Board', 'boards' ),
				];
			case 'create_card':
				return [
					self::dynamic_select( 'board_id', 'Board', 'boards', true ),
					self::dynamic_select( 'column_id', 'Stage / Column', 'columns', true ),
					self::text_field( 'title', 'Card Title', true ),
					self::text_field( 'description', 'Description' ),
					self::text_field( 'due_date', 'Due Date (YYYY-MM-DD)' ),
					self::number_field( 'story_point', 'Story Point' ),
					self::text_field( 'priority', 'Priority term ID or slug' ),
					self::text_field( 'labels', 'Label IDs/slugs (comma separated)' ),
					self::text_field( 'assignees', 'User IDs (comma separated)' ),
				];
			case 'update_card':
				return [
					self::dynamic_select( 'card_id', 'Card', 'cards', true ),
					self::text_field( 'title', 'Card Title' ),
					self::text_field( 'description', 'Description' ),
					self::text_field( 'due_date', 'Due Date (YYYY-MM-DD)' ),
					self::number_field( 'story_point', 'Story Point' ),
					self::text_field( 'priority', 'Priority term ID or slug' ),
					self::text_field( 'labels', 'Label IDs/slugs (comma separated)' ),
				];
			case 'get_card':
			case 'archive_card':
			case 'restore_card':
			case 'delete_card':
				return [ self::dynamic_select( 'card_id', 'Card', 'cards', true ) ];
			case 'search_cards':
				return [
					self::text_field( 'search', 'Search text' ),
					self::dynamic_select( 'board_id', 'Limit to Board', 'boards' ),
					self::dynamic_select( 'column_id', 'Limit to Stage / Column', 'columns' ),
				];
			case 'move_card':
				return [
					self::dynamic_select( 'card_id', 'Card', 'cards', true ),
					self::dynamic_select( 'column_id', 'Destination Stage / Column', 'columns', true ),
				];
			case 'assign_user':
			case 'remove_user':
				return [
					self::dynamic_select( 'card_id', 'Card', 'cards', true ),
					self::dynamic_select( 'user_id', 'User', 'users', true ),
				];
		}
		return [];
	}

	private static function save_or_throw( $entity, string $message ): void {
		$result = $entity->save();
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() ?: $message );
		}
	}

	private static function ensure_project_role( int $project_id, int $user_id ): void {
		if ( ! $project_id || ! $user_id || ! class_exists( '\GemBoards\Entities\Role' ) ) {
			return;
		}
		$role = new \GemBoards\Entities\Role();
		$role->set_object_id( $project_id );
		$role->set_user_id( $user_id );
		$role->set_role( 'manager' );
		self::save_or_throw( $role, 'Could not create project manager role.' );
	}

	private static function create_default_columns( $board ): array {
		$columns = [
			[ 'To Do', '#141A24', 1 ],
			[ 'In Progress', '#EA580C', 2 ],
			[ 'Done', '#22A06B', 3 ],
		];
		$out = [];
		foreach ( $columns as $column_data ) {
			$column = new \GemBoards\Entities\Column();
			$column->set_board_id( (int) $board->get_id() );
			$column->set_title( $column_data[0] );
			$column->set_slug( sanitize_title( $column_data[0] ) );
			$column->set_color( $column_data[1] );
			$column->set_sort_order( $column_data[2] );
			self::save_or_throw( $column, 'Could not create board stage.' );
			$out[] = $column;
		}
		foreach ( $out as $column ) {
			if ( 'done' === $column->get_slug() ) {
				$board->set_completed_column_id( (int) $column->get_id() );
				self::save_or_throw( $board, 'Could not set completed stage.' );
				break;
			}
		}
		return $out;
	}

	private static function apply_terms( int $task_id, array $config ): void {
		if ( array_key_exists( 'priority', $config ) ) {
			$value = trim( (string) $config['priority'] );
			wp_set_object_terms( $task_id, '' === $value ? [] : [ $value ], 'gemboards_priority' );
		}
		if ( array_key_exists( 'labels', $config ) ) {
			$value  = trim( (string) $config['labels'] );
			$labels = '' === $value ? [] : array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
			wp_set_object_terms( $task_id, $labels, 'gemboards_label' );
		}
	}

	private static function clear_user_assignee_cache( int $card_id, int $user_id ): void {
		wp_cache_delete( 'card_assignee:' . $card_id . ':' . $user_id, 'gemboards_card_assignees' );
	}

	private static function user_assignee( int $card_id, int $user_id ) {
		$assignee = \GemBoards\Utils\Helper::get_user_assignee( $card_id, $user_id );
		if ( is_wp_error( $assignee ) ) {
			self::clear_user_assignee_cache( $card_id, $user_id );
			$assignee = \GemBoards\Utils\Helper::get_user_assignee( $card_id, $user_id );
		}
		return is_wp_error( $assignee ) ? null : $assignee;
	}

	private static function assign_users( $card, string $csv ): void {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $csv ) ) ) ) );
		foreach ( $ids as $user_id ) {
			if ( ! get_userdata( $user_id ) ) {
				continue;
			}
			$existing = self::user_assignee( (int) $card->get_id(), $user_id );
			if ( ! is_wp_error( $existing ) && $existing ) {
				continue;
			}
			$assignee = new \GemBoards\Entities\CardAssignee();
			$assignee->set_card_id( (int) $card->get_id() );
			$assignee->set_user_id( $user_id );
			self::save_or_throw( $assignee, 'Could not assign user to card.' );
			self::clear_user_assignee_cache( (int) $card->get_id(), $user_id );
		}
	}

	private static function search_entities( string $kind, string $search, int $parent_id = 0, string $parent_key = '' ): array {
		$where = [];
		if ( $parent_id && $parent_key ) {
			$where[] = [ 'key' => $parent_key, 'value' => $parent_id ];
		}
		$items  = self::entity_collection( $kind, $where, 200 );
		$search = strtolower( trim( $search ) );
		$out    = [];
		foreach ( $items as $item ) {
			$payload = [
				'project' => [ self::class, 'project_payload' ],
				'board'   => [ self::class, 'board_payload' ],
				'column'  => [ self::class, 'column_payload' ],
				'card'    => [ self::class, 'card_payload' ],
			];
			$data = call_user_func( $payload[ $kind ], $item );
			$title = strtolower( (string) ( $data['title'] ?? $data['slug'] ?? '' ) );
			if ( '' !== $search && false === strpos( $title, $search ) ) {
				continue;
			}
			$out[] = $data;
			if ( count( $out ) >= 50 ) {
				break;
			}
		}
		return $out;
	}

	public static function execute_node( array $node, array $input ): array {
		self::require_gemboards();
		$action = self::event( $node );
		$config = self::config( $node );

		if ( ! isset( self::get_actions()[ $action ] ) ) {
			throw new \InvalidArgumentException( 'Unknown GemBoards action.' );
		}

		if ( 'create_project' === $action ) {
			$title = trim( (string) ( $config['title'] ?? '' ) );
			if ( '' === $title ) {
				throw new \InvalidArgumentException( 'Project title is required.' );
			}
			$owner_id = self::id_from_config( $config, 'owner_id' ) ?: get_current_user_id();
			$project  = new \GemBoards\Entities\Project();
			$project->set_title( $title );
			$project->set_slug( sanitize_title( (string) ( $config['slug'] ?? $title ) ) );
			$project->set_description( wp_kses_post( (string) ( $config['description'] ?? '' ) ) );
			$project->set_icon( (string) ( $config['icon'] ?? mb_substr( $title, 0, 1, 'UTF-8' ) ) );
			$project->set_color( (string) ( $config['color'] ?? '' ) );
			$project->set_owner_id( $owner_id );
			self::save_or_throw( $project, 'Could not create project.' );
			self::ensure_project_role( (int) $project->get_id(), $owner_id );
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::project_payload( $project ) ) ];
		}

		if ( in_array( $action, [ 'update_project', 'get_project' ], true ) ) {
			$id      = self::id_from_config( $config, 'project_id', $input );
			$project = self::helper_get( 'project', $id );
			if ( ! $project ) {
				throw new \InvalidArgumentException( 'Project not found.' );
			}
			if ( 'update_project' === $action ) {
				if ( array_key_exists( 'title', $config ) && '' !== trim( (string) $config['title'] ) ) {
					$project->set_title( trim( (string) $config['title'] ) );
					$project->set_slug( sanitize_title( trim( (string) $config['title'] ) ) );
				}
				if ( array_key_exists( 'description', $config ) ) {
					$project->set_description( wp_kses_post( (string) $config['description'] ) );
				}
				if ( array_key_exists( 'project_status', $config ) && '' !== trim( (string) $config['project_status'] ) ) {
					$project->set_project_status( sanitize_key( (string) $config['project_status'] ) );
				}
				if ( array_key_exists( 'color', $config ) ) {
					$project->set_color( sanitize_text_field( (string) $config['color'] ) );
				}
				self::save_or_throw( $project, 'Could not update project.' );
			}
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::project_payload( $project ) ) ];
		}

		if ( 'search_projects' === $action ) {
			return [ 'port' => 'main', 'data' => [ 'items' => self::search_entities( 'project', (string) ( $config['search'] ?? '' ) ) ] ];
		}

		if ( 'create_board' === $action ) {
			$project_id = self::id_from_config( $config, 'project_id' );
			$project    = self::helper_get( 'project', $project_id );
			$title      = trim( (string) ( $config['title'] ?? '' ) );
			if ( ! $project || '' === $title ) {
				throw new \InvalidArgumentException( 'A valid project and board title are required.' );
			}
			$board_type = sanitize_key( (string) ( $config['board_type'] ?? 'kanban' ) );
			if ( 'scrum' === $board_type && ! \GemBoards\Utils\Helper::has_pro() ) {
				throw new \InvalidArgumentException( 'Scrum boards require GemBoards Pro.' );
			}
			$board = new \GemBoards\Entities\Board();
			$board->set_title( $title );
			$board->set_board_type( $board_type ?: 'kanban' );
			$board->set_owner_id( self::id_from_config( $config, 'owner_id' ) ?: get_current_user_id() );
			$board->set_project_id( $project_id );
			$board->set_folder_id( null );
			$board->set_icon_color( sanitize_text_field( (string) ( $config['icon_color'] ?? '' ) ) );
			$board->set_is_private( ! empty( $config['is_private'] ) );
			self::save_or_throw( $board, 'Could not create board.' );
			self::create_default_columns( $board );
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::board_payload( $board ) ) ];
		}

		if ( in_array( $action, [ 'update_board', 'get_board', 'archive_board', 'restore_board' ], true ) ) {
			$id    = self::id_from_config( $config, 'board_id', $input );
			$board = self::helper_get( 'board', $id );
			if ( ! $board ) {
				throw new \InvalidArgumentException( 'Board not found.' );
			}
			if ( 'archive_board' === $action || 'restore_board' === $action ) {
				$board->set_board_status( 'archive_board' === $action ? 'archived' : 'active' );
				self::save_or_throw( $board, 'Could not change board status.' );
			} elseif ( 'update_board' === $action ) {
				if ( array_key_exists( 'title', $config ) && '' !== trim( (string) $config['title'] ) ) {
					$board->set_title( trim( (string) $config['title'] ) );
				}
				if ( array_key_exists( 'board_type', $config ) && '' !== trim( (string) $config['board_type'] ) ) {
					$type = sanitize_key( (string) $config['board_type'] );
					if ( 'scrum' === $type && ! \GemBoards\Utils\Helper::has_pro() ) {
						throw new \InvalidArgumentException( 'Scrum boards require GemBoards Pro.' );
					}
					$board->set_board_type( $type );
				}
				if ( array_key_exists( 'board_status', $config ) && '' !== trim( (string) $config['board_status'] ) ) {
					$board->set_board_status( sanitize_key( (string) $config['board_status'] ) );
				}
				if ( array_key_exists( 'icon_color', $config ) ) {
					$board->set_icon_color( sanitize_text_field( (string) $config['icon_color'] ) );
				}
				if ( array_key_exists( 'is_private', $config ) ) {
					$board->set_is_private( ! empty( $config['is_private'] ) );
				}
				self::save_or_throw( $board, 'Could not update board.' );
			}
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::board_payload( $board ) ) ];
		}

		if ( 'search_boards' === $action ) {
			return [ 'port' => 'main', 'data' => [ 'items' => self::search_entities( 'board', (string) ( $config['search'] ?? '' ), self::id_from_config( $config, 'project_id' ), 'project_id' ) ] ];
		}

		if ( 'create_column' === $action ) {
			$board_id = self::id_from_config( $config, 'board_id' );
			$board    = self::helper_get( 'board', $board_id );
			$title    = trim( (string) ( $config['title'] ?? '' ) );
			if ( ! $board || '' === $title ) {
				throw new \InvalidArgumentException( 'A valid board and stage title are required.' );
			}
			$column = new \GemBoards\Entities\Column();
			$column->set_board_id( $board_id );
			$column->set_title( $title );
			$column->set_slug( sanitize_title( $title ) );
			$column->set_color( sanitize_text_field( (string) ( $config['color'] ?? '' ) ) );
			$column->set_sort_order( absint( $config['sort_order'] ?? 0 ) );
			self::save_or_throw( $column, 'Could not create stage.' );
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::column_payload( $column ) ) ];
		}

		if ( in_array( $action, [ 'update_column', 'get_column' ], true ) ) {
			$id     = self::id_from_config( $config, 'column_id', $input );
			$column = self::helper_get( 'column', $id );
			if ( ! $column ) {
				throw new \InvalidArgumentException( 'Stage / column not found.' );
			}
			if ( 'update_column' === $action ) {
				if ( array_key_exists( 'title', $config ) && '' !== trim( (string) $config['title'] ) ) {
					$column->set_title( trim( (string) $config['title'] ) );
					$column->set_slug( sanitize_title( trim( (string) $config['title'] ) ) );
				}
				if ( array_key_exists( 'color', $config ) ) {
					$column->set_color( sanitize_text_field( (string) $config['color'] ) );
				}
				if ( array_key_exists( 'sort_order', $config ) ) {
					$column->set_sort_order( absint( $config['sort_order'] ) );
				}
				self::save_or_throw( $column, 'Could not update stage.' );
			}
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::column_payload( $column ) ) ];
		}

		if ( 'search_columns' === $action ) {
			return [ 'port' => 'main', 'data' => [ 'items' => self::search_entities( 'column', (string) ( $config['search'] ?? '' ), self::id_from_config( $config, 'board_id' ), 'board_id' ) ] ];
		}

		if ( 'create_card' === $action ) {
			$board_id  = self::id_from_config( $config, 'board_id' );
			$column_id = self::id_from_config( $config, 'column_id' );
			$board     = self::helper_get( 'board', $board_id );
			$column    = self::helper_get( 'column', $column_id );
			$title     = trim( (string) ( $config['title'] ?? '' ) );
			if ( ! $board || ! $column || (int) $column->get_board_id() !== $board_id || '' === $title ) {
				throw new \InvalidArgumentException( 'A valid board, stage and card title are required.' );
			}
			$task_id = wp_insert_post(
				[
					'post_title'   => $title,
					'post_content' => wp_kses_post( (string) ( $config['description'] ?? '' ) ),
					'post_type'    => \GemBoards\Utils\Helper::BOARD_TASK_POST_TYPE,
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
				],
				true
			);
			if ( is_wp_error( $task_id ) ) {
				throw new \RuntimeException( $task_id->get_error_message() );
			}
			$card = new \GemBoards\Entities\Card();
			$card->set_task_id( (int) $task_id );
			$card->set_board_id( $board_id );
			$card->set_column_id( $column_id );
			$card->set_sort_order( 1 );
			$card->set_story_point( absint( $config['story_point'] ?? 0 ) );
			$due = trim( (string) ( $config['due_date'] ?? '' ) );
			$card->set_due_date( '' === $due ? null : $due );
			self::save_or_throw( $card, 'Could not create card.' );
			self::apply_terms( (int) $task_id, $config );
			self::assign_users( $card, (string) ( $config['assignees'] ?? '' ) );
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::card_payload( $card ) ) ];
		}

		if ( in_array( $action, [ 'update_card', 'get_card', 'move_card', 'archive_card', 'restore_card', 'delete_card', 'assign_user', 'remove_user' ], true ) ) {
			$id   = self::id_from_config( $config, 'card_id', $input );
			$card = self::helper_get( 'card', $id );
			if ( ! $card ) {
				throw new \InvalidArgumentException( 'Card not found.' );
			}

			if ( 'update_card' === $action ) {
				$task = $card->get_task();
				if ( ! $task ) {
					throw new \RuntimeException( 'GemBoards task post for this card is missing.' );
				}
				$post_update = [ 'ID' => (int) $card->get_task_id() ];
				$title_changed = false;
				$description_changed = false;
				if ( array_key_exists( 'title', $config ) && '' !== trim( (string) $config['title'] ) ) {
					$post_update['post_title'] = trim( (string) $config['title'] );
					$title_changed = $task->post_title !== $post_update['post_title'];
				}
				if ( array_key_exists( 'description', $config ) ) {
					$post_update['post_content'] = wp_kses_post( (string) $config['description'] );
					$description_changed = $task->post_content !== $post_update['post_content'];
				}
				if ( count( $post_update ) > 1 ) {
					$result = wp_update_post( $post_update, true );
					if ( is_wp_error( $result ) ) {
						throw new \RuntimeException( $result->get_error_message() );
					}
				}
				if ( $title_changed ) {
					do_action( 'gemboards/card/title_updated', $id, $post_update['post_title'] );
				}
				if ( $description_changed ) {
					do_action( 'gemboards/card/description_updated', $id, $post_update['post_content'] );
				}
				if ( array_key_exists( 'story_point', $config ) ) {
					$card->set_story_point( absint( $config['story_point'] ) );
				}
				if ( array_key_exists( 'due_date', $config ) ) {
					$due = trim( (string) $config['due_date'] );
					$card->set_due_date( '' === $due ? null : $due );
				}
				self::save_or_throw( $card, 'Could not update card.' );
				self::apply_terms( (int) $card->get_task_id(), $config );
			} elseif ( 'move_card' === $action ) {
				$column_id = self::id_from_config( $config, 'column_id' );
				$column    = self::helper_get( 'column', $column_id );
				if ( ! $column || (int) $column->get_board_id() !== (int) $card->get_board_id() ) {
					throw new \InvalidArgumentException( 'Destination stage must belong to the same board.' );
				}
				$board = self::helper_get( 'board', (int) $card->get_board_id() );
				$card->set_column_id( $column_id );
				$card->set_is_completed( $board && (int) $board->get_completed_column_id() === $column_id );
				self::save_or_throw( $card, 'Could not move card.' );
				do_action( 'gemboards/card/moved', $id, $column_id );
			} elseif ( 'archive_card' === $action || 'restore_card' === $action ) {
				$card->set_archived( 'archive_card' === $action );
				self::save_or_throw( $card, 'Could not change card archive state.' );
				do_action( 'archive_card' === $action ? 'gemboards/card/archived' : 'gemboards/card/restored', $card );
			} elseif ( 'delete_card' === $action ) {
				$payload = self::card_payload( $card );
				$result  = $card->delete();
				if ( false === $result || is_wp_error( $result ) ) {
					throw new \RuntimeException( is_wp_error( $result ) ? $result->get_error_message() : 'Could not delete card.' );
				}
				return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true, 'deleted' => true ], $payload ) ];
			} elseif ( 'assign_user' === $action ) {
				$user_id = self::id_from_config( $config, 'user_id' );
				if ( ! get_userdata( $user_id ) ) {
					throw new \InvalidArgumentException( 'User not found.' );
				}
				$existing = self::user_assignee( $id, $user_id );
				if ( is_wp_error( $existing ) || ! $existing ) {
					$assignee = new \GemBoards\Entities\CardAssignee();
					$assignee->set_card_id( $id );
					$assignee->set_user_id( $user_id );
					self::save_or_throw( $assignee, 'Could not assign user.' );
					self::clear_user_assignee_cache( $id, $user_id );
				}
			} elseif ( 'remove_user' === $action ) {
				$user_id  = self::id_from_config( $config, 'user_id' );
				$assignee = self::user_assignee( $id, $user_id );
				if ( is_wp_error( $assignee ) || ! is_object( $assignee ) || ! method_exists( $assignee, 'delete' ) ) {
					throw new \InvalidArgumentException( 'That user is not assigned to this card.' );
				}
				$result = $assignee->delete();
				self::clear_user_assignee_cache( $id, $user_id );
				if ( false === $result || is_wp_error( $result ) ) {
					throw new \RuntimeException( 'Could not remove user from card.' );
				}
			}
			$card = self::helper_get( 'card', $id ) ?: $card;
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::card_payload( $card ) ) ];
		}

		if ( 'search_cards' === $action ) {
			$where = [];
			$board_id  = self::id_from_config( $config, 'board_id' );
			$column_id = self::id_from_config( $config, 'column_id' );
			if ( $board_id ) {
				$where[] = [ 'key' => 'board_id', 'value' => $board_id ];
			}
			if ( $column_id ) {
				$where[] = [ 'key' => 'column_id', 'value' => $column_id ];
			}
			$items  = self::entity_collection( 'card', $where, 200 );
			$search = strtolower( trim( (string) ( $config['search'] ?? '' ) ) );
			$out    = [];
			foreach ( $items as $item ) {
				$data = self::card_payload( $item );
				if ( '' !== $search && false === strpos( strtolower( (string) ( $data['title'] ?? '' ) ), $search ) ) {
					continue;
				}
				$out[] = $data;
				if ( count( $out ) >= 50 ) {
					break;
				}
			}
			return [ 'port' => 'main', 'data' => [ 'items' => $out ] ];
		}

		throw new \InvalidArgumentException( 'GemBoards action is not implemented.' );
	}

	public static function get_action_sample_output( string $action ): array {
		if ( 0 === strpos( $action, 'search_' ) ) {
			return [ 'items' => [] ];
		}
		if ( false !== strpos( $action, 'project' ) ) {
			return array_merge( [ 'success' => true ], self::get_trigger_sample_output( 'project_created' ) );
		}
		if ( false !== strpos( $action, 'board' ) ) {
			return array_merge( [ 'success' => true ], self::get_trigger_sample_output( 'board_created' ) );
		}
		if ( false !== strpos( $action, 'column' ) ) {
			return array_merge( [ 'success' => true ], self::get_trigger_sample_output( 'column_created' ) );
		}
		return array_merge( [ 'success' => true ], self::get_trigger_sample_output( 'card_created' ) );
	}

	public static function get_dynamic_queries(): array {
		return [
			'projects' => [ self::class, 'query_projects' ],
			'boards'   => [ self::class, 'query_boards' ],
			'columns'  => [ self::class, 'query_columns' ],
			'cards'    => [ self::class, 'query_cards' ],
			'users'    => [ self::class, 'query_users' ],
		];
	}

	public static function query_projects( array $request = [] ): array {
		$out = [];
		foreach ( self::entity_collection( 'project', [], 200 ) as $item ) {
			$data  = self::project_payload( $item );
			$out[] = [ 'name' => (int) $data['id'], 'label' => (string) ( $data['title'] ?? '#' . $data['id'] ) ];
		}
		return $out;
	}

	public static function query_boards( array $request = [] ): array {
		$out = [];
		foreach ( self::entity_collection( 'board', [], 300 ) as $item ) {
			$data  = self::board_payload( $item );
			$out[] = [ 'name' => (int) $data['id'], 'label' => (string) ( $data['title'] ?? '#' . $data['id'] ) ];
		}
		return $out;
	}

	public static function query_columns( array $request = [] ): array {
		$out = [];
		foreach ( self::entity_collection( 'column', [], 500 ) as $item ) {
			$data  = self::column_payload( $item );
			$out[] = [ 'name' => (int) $data['id'], 'label' => (string) ( $data['title'] ?? '#' . $data['id'] ) . ' (Board ' . (int) ( $data['board_id'] ?? 0 ) . ')' ];
		}
		return $out;
	}

	public static function query_cards( array $request = [] ): array {
		$out = [];
		foreach ( self::entity_collection( 'card', [], 300 ) as $item ) {
			$data  = self::card_payload( $item );
			$out[] = [ 'name' => (int) $data['id'], 'label' => (string) ( $data['title'] ?? '#' . $data['id'] ) . ' (Board ' . (int) ( $data['board_id'] ?? 0 ) . ')' ];
		}
		return $out;
	}

	public static function query_users( array $request = [] ): array {
		$out = [];
		foreach ( get_users( [ 'number' => 200, 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name', 'user_email' ] ] ) as $user ) {
			$out[] = [
				'name'  => (int) $user->ID,
				'label' => (string) $user->display_name . ' (' . (string) $user->user_email . ')',
			];
		}
		return $out;
	}
}
