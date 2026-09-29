<?php

namespace Zaplane\Integrations\Gameengine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CrudTrait {

	private static $crud_stash = [];

	private static function crud_entities(): array {
		$published = [
			[
				'value' => 'publish',
				'label' => 'Published'
			],
			[
				'value' => 'draft',
				'label' => 'Draft'
			],
		];

		return [
			'point_system' => [
				'label'     => 'Point System',
				'table'     => 'gameengine_point_types',
				'route'     => '/gameengine/v1/point-types',
				'title'     => 'name',
				'query'     => 'point_types_query',
				'slug_from' => 'name',
				'fields'    => [
					[
						'key'      => 'name',
						'label'    => 'Name',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'plural_name',
						'label'    => 'Plural Name',
						'type'     => 'text',
						'required' => false,
						'help'     => 'Shown in the points history. Falls back to the name.',
					],
					[
						'key'      => 'slug',
						'label'    => 'Slug',
						'type'     => 'text',
						'required' => false,
						'help'     => 'Derived from the name when left empty.',
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'publish',
						'options'  => $published,
					],
				],
				'sample'   => [
					'id'          => 4,
					'name'        => 'Karma',
					'plural_name' => 'Karma',
					'slug'        => 'karma',
					'status'      => 'publish',
				],
			],
			'achievement' => [
				'label'     => 'Achievement',
				'table'     => 'gameengine_achievements',
				'route'     => '/gameengine/v1/achievements',
				'title'     => 'title',
				'query'     => 'achievements_query',
				'slug_from' => 'title',
				'fields'    => [
					[
						'key'      => 'title',
						'label'    => 'Title',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'description',
						'label'    => 'Description',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'category',
						'label'    => 'Category',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'badge_image',
						'label'    => 'Badge Image URL',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'congratulations_message',
						'label'    => 'Congratulations Message',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'secret_achievement',
						'label'    => 'Secret Achievement',
						'type'     => 'select',
						'required' => false,
						'default'  => 0,
						'options'  => [
							[
								'value' => 0,
								'label' => 'Visible'
							],
							[
								'value' => 1,
								'label' => 'Hidden until earned'
							],
						],
					],
					[
						'key'      => 'max_earnings_per_user',
						'label'    => 'Max Earnings per User',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
						'help'     => '0 allows unlimited earnings.',
					],
					[
						'key'      => 'slug',
						'label'    => 'Slug',
						'type'     => 'text',
						'required' => false,
						'help'     => 'Derived from the title when left empty.',
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'publish',
						'options'  => $published,
					],
				],
				'sample'   => [
					'id'          => 4,
					'title'       => 'First Steps',
					'slug'        => 'first-steps',
					'status'      => 'publish',
					'category'    => 'Getting Started',
					'description' => 'Earn your very first points.',
				],
			],
			'level'       => [
				'label'     => 'Level',
				'table'     => 'gameengine_levels',
				'route'     => '/gameengine/v1/levels',
				'title'     => 'title',
				'query'     => 'levels_query',
				'slug_from' => 'title',
				'fields'    => [
					[
						'key'      => 'title',
						'label'    => 'Title',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'description',
						'label'    => 'Description',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'category',
						'label'    => 'Category',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'icon',
						'label'    => 'Icon',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'color',
						'label'    => 'Color',
						'type'     => 'text',
						'required' => false,
						'help'     => 'Hex value, e.g. #6c5ce7.',
					],
					[
						'key'      => 'priority',
						'label'    => 'Priority',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'point_type_id',
						'label'    => 'Point Type',
						'type'     => 'select',
						'format'   => 'int',
						'required' => false,
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'point_types_query',
							'select'      => [ 'value', 'label' ],
						],
					],
					[
						'key'      => 'min_points',
						'label'    => 'Minimum Points',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'max_points',
						'label'    => 'Maximum Points',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'slug',
						'label'    => 'Slug',
						'type'     => 'text',
						'required' => false,
						'help'     => 'Derived from the title when left empty.',
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'publish',
						'options'  => $published,
					],
				],
				'sample'   => [
					'id'         => 4,
					'title'      => 'Adept',
					'slug'       => 'adept',
					'status'     => 'publish',
					'priority'   => 3,
					'min_points' => 500,
					'max_points' => 1499,
				],
			],
			'badge'       => [
				'label' => 'Badge',
				'route' => '/gameengine/v1/badges',
				'title' => 'title',
				'query' => 'badges_query',
				'cpt'   => 'ge_badge',
				'fields' => [
					[
						'key'      => 'title',
						'label'    => 'Title',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'icon',
						'label'    => 'Icon',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'icon_type',
						'label'    => 'Icon Type',
						'type'     => 'select',
						'required' => false,
						'options'  => [
							[
								'value' => 'dashicon',
								'label' => 'Dashicon'
							],
							[
								'value' => 'image',
								'label' => 'Image'
							],
							[
								'value' => 'emoji',
								'label' => 'Emoji'
							],
						],
					],
					[
						'key'      => 'shape',
						'label'    => 'Shape',
						'type'     => 'select',
						'required' => false,
						'options'  => [
							[
								'value' => 'circle',
								'label' => 'Circle'
							],
							[
								'value' => 'square',
								'label' => 'Square'
							],
							[
								'value' => 'shield',
								'label' => 'Shield'
							],
						],
					],
					[
						'key'      => 'color',
						'label'    => 'Color',
						'type'     => 'text',
						'required' => false,
						'help'     => 'Hex value, e.g. #6366f1.',
					],
					[
						'key'      => 'border_color',
						'label'    => 'Border Color',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'text_color',
						'label'    => 'Text Color',
						'type'     => 'text',
						'required' => false,
					],
				],
				'sample' => [
					'id'      => 7,
					'title'   => 'Streak Star',
					'icon'    => 'star-filled',
					'icon_type' => 'dashicon',
					'shape'   => 'circle',
					'color'   => '#6366f1',
				],
			],
			'log'         => [
				'label'     => 'Log',
				'table'     => 'gameengine_logs',
				'route'     => '/gameengine/v1/logs',
				'title'     => 'message',
				'query'     => 'logs_query',
				'no_delete' => true,
				'fields'    => [
					[
						'key'         => 'user_id',
						'label'       => 'User ID',
						'type'        => 'expression',
						'format'      => 'int',
						'required'    => false,
						'placeholder' => '{{trigger.user.user_id}}',
						'help'        => 'A user ID or an expression. Leave empty to log against nobody.',
					],
					[
						'key'      => 'trigger_key',
						'label'    => 'Trigger Key',
						'type'     => 'text',
						'required' => false,
						'default'  => 'zaplane',
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'success',
						'options'  => [
							[
								'value' => 'success',
								'label' => 'Success'
							],
							[
								'value' => 'failed',
								'label' => 'Failed'
							],
							[
								'value' => 'skipped',
								'label' => 'Skipped'
							],
						],
					],
					[
						'key'      => 'points_awarded',
						'label'    => 'Points Awarded',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'message',
						'label'    => 'Message',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'meta',
						'label'    => 'Meta (JSON)',
						'type'     => 'textarea',
						'format'   => 'json',
						'required' => false,
					],
				],
				'sample'   => [
					'id'             => 512,
					'user_id'        => 42,
					'trigger_key'    => 'zaplane',
					'status'         => 'success',
					'points_awarded' => 50,
					'message'        => 'Awarded 50 points for a completed profile.',
				],
			],
			'reward'      => [
				'label'     => 'Reward',
				'table'     => 'gameengine_rewards',
				'route'     => '/gameengine/v1/rewards',
				'title'     => 'title',
				'query'     => 'rewards_query',
				'slug_from' => 'title',
				'fields'    => [
					[
						'key'      => 'title',
						'label'    => 'Title',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'description',
						'label'    => 'Description',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'image',
						'label'    => 'Image URL',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'cost_points',
						'label'    => 'Cost (points)',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'point_type_id',
						'label'    => 'Point Type',
						'type'     => 'select',
						'format'   => 'int',
						'required' => false,
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'point_types_query',
							'select'      => [ 'value', 'label' ],
						],
					],
					[
						'key'      => 'stock',
						'label'    => 'Stock',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => -1,
						'help'     => '-1 is unlimited.',
					],
					[
						'key'      => 'limit_per_user',
						'label'    => 'Limit per User',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
						'help'     => '0 allows unlimited redemptions.',
					],
					[
						'key'      => 'slug',
						'label'    => 'Slug',
						'type'     => 'text',
						'required' => false,
						'help'     => 'Derived from the title when left empty.',
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'publish',
						'options'  => $published,
					],
				],
				'sample'   => [
					'id'          => 4,
					'title'       => 'Sticker Pack',
					'slug'        => 'sticker-pack',
					'cost_points' => 500,
					'stock'       => 10,
					'status'      => 'publish',
				],
			],
			'wheel'       => [
				'label'   => 'Lucky Wheel',
				'table'   => 'gameengine_lucky_wheels',
				'route'   => '/gameengine/v1/lucky-wheels',
				'title'   => 'name',
				'query'   => 'wheels_query',
				'upsert'  => true,
				'pro'     => true,
				'fields'  => [
					[
						'key'      => 'name',
						'label'    => 'Name',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'spin_cost',
						'label'    => 'Spin Cost',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'daily_limit',
						'label'    => 'Daily Limit',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
						'help'     => '0 allows unlimited spins.',
					],
					[
						'key'      => 'cooldown_timer',
						'label'    => 'Cooldown (seconds)',
						'type'     => 'number',
						'format'   => 'int',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'slices',
						'label'    => 'Slices (JSON)',
						'type'     => 'textarea',
						'format'   => 'json',
						'required' => false,
						'help'     => 'The prize slices shown on the wheel.',
					],
					[
						'key'      => 'settings',
						'label'    => 'Settings (JSON)',
						'type'     => 'textarea',
						'format'   => 'json',
						'required' => false,
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'publish',
						'options'  => $published,
					],
				],
				'sample'  => [
					'id'           => 2,
					'name'         => 'Daily Spin',
					'spin_cost'    => 100,
					'daily_limit'  => 1,
					'cooldown_timer' => 0,
					'status'       => 'publish',
				],
			],
			'season'      => [
				'label' => 'Season',
				'table' => 'gameengine_pro_seasons',
				'route' => '/gameengine/v1/pro/seasons',
				'title' => 'name',
				'query' => 'seasons_query',
				'pro'   => true,
				'fields' => [
					[
						'key'      => 'name',
						'label'    => 'Name',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'start_date',
						'label'    => 'Start Date',
						'type'     => 'text',
						'required' => false,
						'placeholder' => '2026-12-01',
					],
					[
						'key'      => 'end_date',
						'label'    => 'End Date',
						'type'     => 'text',
						'required' => false,
						'placeholder' => '2027-02-28',
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'draft',
						'options'  => [
							[
								'value' => 'draft',
								'label' => 'Draft'
							],
							[
								'value' => 'active',
								'label' => 'Active'
							],
							[
								'value' => 'completed',
								'label' => 'Completed'
							],
						],
					],
				],
				'sample' => [
					'id'         => 3,
					'name'       => 'Winter 2026',
					'start_date' => '2026-12-01',
					'end_date'   => '2027-02-28',
					'status'     => 'active',
				],
			],
			'webhook'     => [
				'label' => 'Webhook',
				'table' => 'gameengine_pro_webhooks',
				'route' => '/gameengine/v1/pro/webhooks',
				'title' => 'name',
				'query' => 'webhooks_query',
				'pro'   => true,
				'fields' => [
					[
						'key'      => 'name',
						'label'    => 'Name',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'url',
						'label'    => 'URL',
						'type'     => 'text',
						'required' => true,
						'placeholder' => 'https://example.com/gameengine-hook',
					],
					[
						'key'      => 'events',
						'label'    => 'Events (JSON)',
						'type'     => 'textarea',
						'format'   => 'json',
						'required' => false,
						'help'     => 'The GameEngine event keys this endpoint subscribes to.',
					],
					[
						'key'      => 'secret',
						'label'    => 'Signing Secret',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'active',
						'label'    => 'Active',
						'type'     => 'select',
						'format'   => 'int',
						'required' => false,
						'default'  => 1,
						'options'  => [
							[
								'value' => 1,
								'label' => 'Active'
							],
							[
								'value' => 0,
								'label' => 'Paused'
							],
						],
					],
				],
				'sample' => [
					'id'     => 5,
					'name'   => 'Points Hook',
					'url'    => 'https://example.com/gameengine-hook',
					'events' => [ 'points_awarded' ],
					'active' => 1,
				],
			],
		];
	}

	private static function crud_entity( string $entity ): ?array {
		$entities = self::crud_entities();

		return $entities[ $entity ] ?? null;
	}

	private static function crud_triggers(): array {
		$triggers = [];

		foreach ( self::crud_entities() as $entity => $meta ) {
			foreach ( [ 'created', 'updated', 'deleted' ] as $operation ) {
				if ( 'deleted' === $operation && ! empty( $meta['no_delete'] ) ) {
					continue;
				}

				$item = [
					'label'       => $meta['label'] . ' ' . $operation,
					'hook'        => self::crud_hook( $operation ),
					'description' => sprintf(
						'When a %1$s is %2$s in GameEngine.',
						strtolower( (string) $meta['label'] ),
						'created' === $operation ? 'created' : ( 'updated' === $operation ? 'changed' : 'removed' )
					),
				];

				if ( ! empty( $meta['pro'] ) ) {
					$item = self::pro_meta( $item );
				}

				$triggers[ $entity . '_' . $operation ] = $item;
			}//end foreach
		}//end foreach

		return $triggers;
	}

	private static function crud_hook( string $operation ) {
		// A delete reads on the pre-dispatch pass (the row is gone by the time
		// the response exists) and confirms on the post-dispatch pass, so it
		// needs both hooks.
		return 'deleted' === $operation
			? [ 'rest_pre_dispatch', 'rest_post_dispatch' ]
			: 'rest_post_dispatch';
	}

	private static function crud_actions(): array {
		$verbs = [
			'create' => [
				'label' => 'Create',
				'help'  => 'Add a new %s to GameEngine.'
			],
			'update' => [
				'label' => 'Update',
				'help'  => 'Change an existing %s.'
			],
			'delete' => [
				'label' => 'Delete',
				'help'  => 'Remove a %s from GameEngine.'
			],
		];

		$actions = [];

		foreach ( self::crud_entities() as $entity => $meta ) {
			foreach ( $verbs as $verb => $definition ) {
				$item = [
					'label'       => $definition['label'] . ' ' . $meta['label'],
					'description' => sprintf( $definition['help'], strtolower( (string) $meta['label'] ) ),
				];

				if ( ! empty( $meta['pro'] ) ) {
					$item = self::pro_meta( $item );
				}

				$actions[ $verb . '_' . $entity ] = $item;
			}//end foreach
		}//end foreach

		return $actions;
	}

	private static function crud_pro_actions(): array {
		$actions = [];

		foreach ( self::crud_entities() as $entity => $meta ) {
			if ( empty( $meta['pro'] ) ) {
				continue;
			}

			foreach ( [ 'create', 'update', 'delete' ] as $verb ) {
				$actions[] = $verb . '_' . $entity;
			}
		}

		return $actions;
	}

	private static function crud_event_entity( string $event ): ?string {
		foreach ( [ 'created', 'updated', 'deleted' ] as $operation ) {
			$suffix = '_' . $operation;

			if ( substr( $event, -strlen( $suffix ) ) !== $suffix ) {
				continue;
			}

			$entity = substr( $event, 0, -strlen( $suffix ) );

			return self::crud_entity( $entity ) ? $entity : null;
		}

		return null;
	}

	private static function resolve_crud_event( string $event, array $config, array $args ) {
		if ( ! class_exists( '\WP_REST_Request' ) ) {
			return false;
		}

		$request = $args[2] ?? null;

		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		$operation = (string) substr( $event, strrpos( $event, '_' ) + 1 );
		$entity    = (string) self::crud_event_entity( $event );
		$meta      = self::crud_entity( $entity );

		if ( ! $meta ) {
			return false;
		}

		$route = (string) $request->get_route();
		$base  = (string) $meta['route'];
		$id    = 0;

		// Anything that is neither the collection route nor that collection's
		// own row belongs to some other plugin.
		if ( preg_match( '#^' . preg_quote( $base, '#' ) . '/(\d+)$#', $route, $matches ) ) {
			$id = (int) $matches[1];
		} elseif ( $base !== $route ) {
			return false;
		}

		$method = strtoupper( (string) $request->get_method() );

		if ( 'DELETE' === $method && $id > 0 ) {
			$found = 'deleted';
		} elseif ( $id > 0 ) {
			$found = 'updated';
		} elseif ( 'POST' === $method ) {
			$params  = $request->get_json_params();
			$body_id = (int) ( is_array( $params ) ? ( $params['id'] ?? 0 ) : 0 );

			// GameEngine's wheel endpoint saves on the collection route: an id
			// in the body means update, no id means create.
			if ( ! empty( $meta['upsert'] ) && $body_id > 0 ) {
				$found = 'updated';
				$id    = $body_id;
			} else {
				$found = 'created';
			}
		} else {
			return false;
		}

		if ( $found !== $operation ) {
			return false;
		}

		// rest_pre_dispatch passes null as its first argument; rest_post_dispatch
		// passes the finished response. Reading the difference keeps a delete on
		// the pass where its row still exists.
		if ( ! isset( $args[0] ) || ! $args[0] instanceof \WP_REST_Response ) {
			if ( 'deleted' === $operation && $id > 0 ) {
				self::$crud_stash[ $entity ][ $id ] = self::crud_row( $entity, $id );
			}

			return false;
		}

		if ( $args[0]->get_status() >= 400 ) {
			return false;
		}

		$row = [];

		if ( 'deleted' === $operation ) {
			$row = self::$crud_stash[ $entity ][ $id ] ?? [];
			unset( self::$crud_stash[ $entity ][ $id ] );
		} else {
			if ( $id <= 0 ) {
				$data = $args[0]->get_data();
				$id   = is_array( $data ) ? (int) ( $data['id'] ?? 0 ) : 0;
			}

			$row = self::crud_row( $entity, $id );
		}

		if ( ! $row ) {
			// GameEngine's log endpoint answers with a message only, so the
			// request body is the best description of what was written.
			$body = $request->get_json_params();
			$row  = is_array( $body ) ? $body : [];
		}

		return array_merge(
			self::crud_payload( $entity, $operation, $id, $row ),
			[
				'success'   => true,
				'user_id'   => get_current_user_id(),
				'timestamp' => current_time( 'mysql' ),
			]
		);
	}

	private static function crud_row( string $entity, int $id ): array {
		$meta = self::crud_entity( $entity );

		if ( ! $meta || $id <= 0 ) {
			return [];
		}

		if ( ! empty( $meta['cpt'] ) ) {
			$post = get_post( $id );

			if ( ! $post || $meta['cpt'] !== $post->post_type ) {
				return [];
			}

			return [
				'id'           => $id,
				'title'        => $post->post_title,
				'icon'         => get_post_meta( $id, '_ge_badge_icon', true ),
				'icon_type'    => get_post_meta( $id, '_ge_badge_icon_type', true ),
				'shape'        => get_post_meta( $id, '_ge_badge_shape', true ),
				'color'        => get_post_meta( $id, '_ge_badge_color', true ),
				'border_color' => get_post_meta( $id, '_ge_badge_border_color', true ),
				'text_color'   => get_post_meta( $id, '_ge_badge_text_color', true ),
			];
		}

		global $wpdb;

		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$meta['table']} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? $row : [];
	}

	private static function crud_payload( string $entity, string $operation, int $id, array $row ): array {
		$meta = self::crud_entity( $entity );

		return [
			'entity'    => $entity,
			'label'     => $meta['label'],
			'operation' => $operation,
			'id'        => $id,
			'title'     => (string) ( $row[ $meta['title'] ] ?? '' ),
			'data'      => $row,
		];
	}

	private static function crud_create( string $entity, array $config ): array {
		$meta = self::crud_entity( $entity );

		if ( ! $meta ) {
			return self::action_error( 'Unknown GameEngine entity.' );
		}

		$data = self::crud_write_data( $meta, $config, true );

		if ( null === $data ) {
			return self::action_error( sprintf( 'Creating a %s needs its name.', strtolower( (string) $meta['label'] ) ) );
		}

		if ( ! empty( $meta['cpt'] ) ) {
			$id = self::badge_insert( $data );

			if ( $id <= 0 ) {
				return self::action_error( 'GameEngine could not create that badge.' );
			}

			return self::action_success(
				self::crud_payload( $entity, 'created', $id, self::crud_row( $entity, $id ) ?: $data )
			);
		}

		global $wpdb;

		if ( ! isset( $wpdb->prefix ) ) {
			return self::action_error( 'The GameEngine database tables are not available.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$written = $wpdb->insert( $wpdb->prefix . $meta['table'], $data );
		$id      = (int) $wpdb->insert_id;

		if ( ! $written || $id <= 0 ) {
			return self::action_error( sprintf( 'GameEngine could not create that %s.', strtolower( (string) $meta['label'] ) ) );
		}

		return self::action_success(
			self::crud_payload( $entity, 'created', $id, self::crud_row( $entity, $id ) ?: $data )
		);
	}

	private static function crud_update( string $entity, array $config ): array {
		$meta = self::crud_entity( $entity );

		if ( ! $meta ) {
			return self::action_error( 'Unknown GameEngine entity.' );
		}

		$id       = (int) ( $config['id'] ?? 0 );
		$existing = self::crud_row( $entity, $id );

		if ( ! $existing ) {
			return self::action_error( sprintf( 'No %s with that id was found.', strtolower( (string) $meta['label'] ) ) );
		}

		$data = self::crud_write_data( $meta, $config, false );

		if ( ! $data ) {
			return self::action_error( 'Nothing to update — every field was left empty.' );
		}

		if ( ! empty( $meta['cpt'] ) ) {
			if ( isset( $data['title'] ) ) {
				wp_update_post(
					[
						'ID'         => $id,
						'post_title' => (string) $data['title'],
					]
				);
			}

			self::badge_save_meta( $id, $data );

			return self::action_success(
				self::crud_payload( $entity, 'updated', $id, self::crud_row( $entity, $id ) ?: $data )
			);
		}

		global $wpdb;

		if ( ! isset( $wpdb->prefix ) ) {
			return self::action_error( 'The GameEngine database tables are not available.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$written = $wpdb->update( $wpdb->prefix . $meta['table'], $data, [ 'id' => $id ] );

		if ( false === $written ) {
			return self::action_error( sprintf( 'GameEngine could not update that %s.', strtolower( (string) $meta['label'] ) ) );
		}

		return self::action_success(
			self::crud_payload( $entity, 'updated', $id, self::crud_row( $entity, $id ) ?: array_merge( $existing, $data ) )
		);
	}

	private static function crud_delete( string $entity, array $config ): array {
		$meta = self::crud_entity( $entity );

		if ( ! $meta ) {
			return self::action_error( 'Unknown GameEngine entity.' );
		}

		$id       = (int) ( $config['id'] ?? 0 );
		$existing = self::crud_row( $entity, $id );

		if ( ! $existing ) {
			return self::action_error( sprintf( 'No %s with that id was found.', strtolower( (string) $meta['label'] ) ) );
		}

		if ( ! empty( $meta['cpt'] ) ) {
			if ( ! wp_delete_post( $id, true ) ) {
				return self::action_error( 'GameEngine could not delete that badge.' );
			}
		} else {
			global $wpdb;

			if ( ! isset( $wpdb->prefix ) ) {
				return self::action_error( 'The GameEngine database tables are not available.' );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->delete( $wpdb->prefix . $meta['table'], [ 'id' => $id ] );

			if ( false === $deleted ) {
				return self::action_error( sprintf( 'GameEngine could not delete that %s.', strtolower( (string) $meta['label'] ) ) );
			}
		}

		return self::action_success(
			array_merge(
				self::crud_payload( $entity, 'deleted', $id, $existing ),
				[ 'deleted' => true ]
			)
		);
	}

	private static function crud_write_data( array $meta, array $config, bool $is_create ): ?array {
		$data = [];

		foreach ( (array) $meta['fields'] as $field ) {
			$key      = (string) $field['key'];
			$required = ! empty( $field['required'] );

			if ( array_key_exists( $key, $config ) ) {
				$value = $config[ $key ];
			} elseif ( $is_create && array_key_exists( 'default', $field ) ) {
				$value = $field['default'];
			} elseif ( $is_create && $required ) {
				return null;
			} else {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			} elseif ( null === $value ) {
				$value = '';
			} else {
				$value = (string) $value;
			}

			// An empty field means "leave it alone" on an update and "let the
			// column default apply" on a create.
			if ( '' === $value ) {
				if ( $required && $is_create ) {
					return null;
				}

				continue;
			}

			$data[ $key ] = self::crud_cast(
				$value,
				(string) ( $field['format'] ?? '' ),
				(string) ( $field['type'] ?? 'text' )
			);
		}//end foreach

		// GameEngine keys several tables on a slug, and keeps it in step with
		// whatever names the row.
		if ( ! empty( $meta['slug_from'] ) ) {
			$slug   = (string) ( $data['slug'] ?? '' );
			$source = '' !== $slug ? $slug : (string) ( $data[ $meta['slug_from'] ] ?? '' );

			if ( '' !== $source ) {
				$data['slug'] = sanitize_title( $source );
			}
		}

		return $data;
	}

	private static function crud_cast( string $value, string $format, string $type ) {
		if ( 'int' === $format ) {
			return (int) $value;
		}

		if ( 'decimal' === $format ) {
			return (float) $value;
		}

		if ( 'json' === $format ) {
			$decoded = json_decode( $value, true );

			return wp_json_encode( is_array( $decoded ) ? $decoded : [] );
		}

		if ( 'textarea' === $type ) {
			return function_exists( 'sanitize_textarea_field' )
				? sanitize_textarea_field( $value )
				: wp_strip_all_tags( $value );
		}

		return sanitize_text_field( $value );
	}

	private static function badge_insert( array $data ): int {
		$post_id = wp_insert_post(
			[
				'post_type'   => 'ge_badge',
				'post_title'  => (string) ( $data['title'] ?? '' ),
				'post_status' => 'publish',
			]
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		self::badge_save_meta( (int) $post_id, $data );

		return (int) $post_id;
	}

	private static function badge_save_meta( int $post_id, array $data ): void {
		$colors = [
			'icon'         => '_ge_badge_icon',
			'icon_type'    => '_ge_badge_icon_type',
			'shape'        => '_ge_badge_shape',
			'color'        => '_ge_badge_color',
			'border_color' => '_ge_badge_border_color',
			'text_color'   => '_ge_badge_text_color',
		];

		foreach ( $colors as $key => $meta_key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$value = (string) $data[ $key ];

			if ( '' === $value ) {
				continue;
			}

			if ( in_array( $key, [ 'color', 'border_color', 'text_color' ], true ) ) {
				$value = self::badge_color( $value, 'color' === $key ? '#6366f1' : '#ffffff' );
			} elseif ( 'icon_type' === $key ) {
				$value = sanitize_key( $value );
			} elseif ( 'shape' === $key && ! in_array( $value, [ 'circle', 'square', 'shield' ], true ) ) {
				$value = 'circle';
			}

			update_post_meta( $post_id, $meta_key, $value );
		}//end foreach
	}

	private static function badge_color( string $value, string $fallback ): string {
		if ( function_exists( 'sanitize_hex_color' ) ) {
			return sanitize_hex_color( $value ) ?: $fallback;
		}

		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ? $value : $fallback;
	}

	private static function crud_action_entity( string $action ): ?array {
		foreach ( [ 'create', 'update', 'delete' ] as $verb ) {
			$prefix = $verb . '_';

			if ( 0 !== strpos( $action, $prefix ) ) {
				continue;
			}

			$entity = substr( $action, strlen( $prefix ) );

			return self::crud_entity( $entity ) ? [ $entity, $verb ] : null;
		}

		return null;
	}

	private static function crud_action_schema( string $action ): ?array {
		$parsed = self::crud_action_entity( $action );

		if ( ! $parsed ) {
			return null;
		}

		list( $entity, $verb ) = $parsed;
		$meta    = self::crud_entity( $entity );
		$fields  = [];

		// An update or a delete both pick their target from a list of rows.
		if ( 'create' !== $verb ) {
			$fields[] = [
				'key'      => 'id',
				'label'    => $meta['label'],
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'gameengine',
					'query'       => $meta['query'],
					'select'      => [ 'value', 'label' ],
				],
				'required' => true,
			];
		}

		foreach ( (array) $meta['fields'] as $field ) {
			if ( 'create' !== $verb ) {
				// Every field is optional on a patch, and none of them may
				// prefill a default that would overwrite the stored row.
				$field['required'] = false;
				unset( $field['default'] );
			}

			$fields[] = $field;
		}

		return $fields;
	}

	private static function crud_sample( string $key ): ?array {
		$entity    = null;
		$operation = null;

		foreach ( [ 'created', 'updated', 'deleted' ] as $candidate ) {
			$suffix = '_' . $candidate;

			if ( $suffix === substr( $key, -strlen( $suffix ) ) ) {
				$entity    = substr( $key, 0, -strlen( $suffix ) );
				$operation = $candidate;
				break;
			}
		}

		if ( null === $entity ) {
			foreach ( [ 'create' => 'created', 'update' => 'updated', 'delete' => 'deleted' ] as $verb => $candidate ) {
				$prefix = $verb . '_';

				if ( 0 === strpos( $key, $prefix ) ) {
					$entity    = substr( $key, strlen( $prefix ) );
					$operation = $candidate;
					break;
				}
			}
		}

		if ( null === $entity || null === $operation ) {
			return null;
		}

		$meta = self::crud_entity( $entity );

		if ( ! $meta ) {
			return null;
		}

		return array_merge(
			self::crud_payload( $entity, $operation, (int) ( $meta['sample']['id'] ?? 4 ), (array) $meta['sample'] ),
			[
				'success'   => true,
				'user_id'   => 1,
				'timestamp' => current_time( 'mysql' ),
			]
		);
	}


	private static function action_create_point_system( array $config, array $input ): array {
		return self::crud_create( 'point_system', $config );
	}

	private static function action_update_point_system( array $config, array $input ): array {
		return self::crud_update( 'point_system', $config );
	}

	private static function action_delete_point_system( array $config, array $input ): array {
		return self::crud_delete( 'point_system', $config );
	}

	private static function action_create_achievement( array $config, array $input ): array {
		return self::crud_create( 'achievement', $config );
	}

	private static function action_update_achievement( array $config, array $input ): array {
		return self::crud_update( 'achievement', $config );
	}

	private static function action_delete_achievement( array $config, array $input ): array {
		return self::crud_delete( 'achievement', $config );
	}

	private static function action_create_level( array $config, array $input ): array {
		return self::crud_create( 'level', $config );
	}

	private static function action_update_level( array $config, array $input ): array {
		return self::crud_update( 'level', $config );
	}

	private static function action_delete_level( array $config, array $input ): array {
		return self::crud_delete( 'level', $config );
	}

	private static function action_create_badge( array $config, array $input ): array {
		return self::crud_create( 'badge', $config );
	}

	private static function action_update_badge( array $config, array $input ): array {
		return self::crud_update( 'badge', $config );
	}

	private static function action_delete_badge( array $config, array $input ): array {
		return self::crud_delete( 'badge', $config );
	}

	private static function action_create_log( array $config, array $input ): array {
		return self::crud_create( 'log', $config );
	}

	private static function action_update_log( array $config, array $input ): array {
		return self::crud_update( 'log', $config );
	}

	private static function action_delete_log( array $config, array $input ): array {
		return self::crud_delete( 'log', $config );
	}

	private static function action_create_reward( array $config, array $input ): array {
		return self::crud_create( 'reward', $config );
	}

	private static function action_update_reward( array $config, array $input ): array {
		return self::crud_update( 'reward', $config );
	}

	private static function action_delete_reward( array $config, array $input ): array {
		return self::crud_delete( 'reward', $config );
	}

	private static function action_create_wheel( array $config, array $input ): array {
		return self::crud_create( 'wheel', $config );
	}

	private static function action_update_wheel( array $config, array $input ): array {
		return self::crud_update( 'wheel', $config );
	}

	private static function action_delete_wheel( array $config, array $input ): array {
		return self::crud_delete( 'wheel', $config );
	}

	private static function action_create_season( array $config, array $input ): array {
		return self::crud_create( 'season', $config );
	}

	private static function action_update_season( array $config, array $input ): array {
		return self::crud_update( 'season', $config );
	}

	private static function action_delete_season( array $config, array $input ): array {
		return self::crud_delete( 'season', $config );
	}

	private static function action_create_webhook( array $config, array $input ): array {
		return self::crud_create( 'webhook', $config );
	}

	private static function action_update_webhook( array $config, array $input ): array {
		return self::crud_update( 'webhook', $config );
	}

	private static function action_delete_webhook( array $config, array $input ): array {
		return self::crud_delete( 'webhook', $config );
	}
}
