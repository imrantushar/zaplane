<?php

namespace Zaplane\Integrations\EasyContentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait EcmSchemasTrait {

	protected static function ecm_trigger_config_schema( string $trigger ): array {
		$post_type_select = static::ecm_post_type_select_field( false );

		switch ( $trigger ) {
			case 'specific_item_created':
			case 'specific_item_updated':
			case 'specific_item_published':
				$field            = $post_type_select;
				$field['required'] = true;

				return [ $field ];

			case 'custom_field_updated':
				return [
					[
						'key'      => 'meta_key',
						'label'    => 'Field (leave empty for any ECM field)',
						'type'     => 'select',
						'required' => false,
						'dynamic'  => [
							'integration' => 'easycontentmanager',
							'query'       => 'post_meta_keys',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'user_meta_updated':
				return [
					[
						'key'      => 'meta_key',
						'label'    => 'Field (leave empty for any ECM user field)',
						'type'     => 'select',
						'required' => false,
						'dynamic'  => [
							'integration' => 'easycontentmanager',
							'query'       => 'user_meta_keys',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'term_created':
			case 'term_updated':
			case 'term_deleted':
				return [
					[
						'key'      => 'taxonomy',
						'label'    => 'Taxonomy (leave empty for any ECM taxonomy)',
						'type'     => 'select',
						'required' => false,
						'dynamic'  => [
							'integration' => 'easycontentmanager',
							'query'       => 'taxonomies',
							'select'      => [ 'value', 'label' ],
						],
					],
				];
		}//end switch

		return [];
	}

	protected static function ecm_action_config_schema( string $action ): array {
		$item_type = [
			'key'         => 'item_type',
			'label'       => 'Item Type',
			'type'        => 'select',
			'required'    => true,
			'options'     => [
				[
					'label' => 'Post',
					'value' => 'post'
				],
				[
					'label' => 'Page',
					'value' => 'page'
				],
			],
		];
		$post_id = [
			'key'      => 'post_id',
			'label'    => 'Item ID',
			'type'     => 'expression',
			'required' => true,
		];
		$user_id = [
			'key'      => 'user_id',
			'label'    => 'User ID',
			'type'     => 'expression',
			'required' => true,
		];
		$meta_key = static::ecm_meta_key_field();
		$value    = [
			'key'      => 'value',
			'label'    => 'Value',
			'type'     => 'expression',
			'required' => true,
		];
		$status = [
			'key'      => 'status',
			'label'    => 'Status',
			'type'     => 'select',
			'required' => false,
			'default'  => 'draft',
			'options'  => static::ecm_status_options(),
		];

		switch ( $action ) {
			case 'create_item':
				return [
					$item_type,
					static::ecm_post_type_select_field( true ),
					[
						'key'      => 'post_title',
						'label'    => 'Title',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'post_content',
						'label'    => 'Content',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'post_excerpt',
						'label'    => 'Excerpt',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'post_status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'draft',
						'options'  => static::ecm_status_options(),
					],
					[
						'key'      => 'author_id',
						'label'    => 'Author ID (leave empty for the run user)',
						'type'     => 'expression',
						'required' => false,
					],
				];

			case 'update_item':
				return [
					$item_type,
					[
						'key'      => 'post_title',
						'label'    => 'New Title (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'post_content',
						'label'    => 'New Content (leave empty to keep)',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'post_excerpt',
						'label'    => 'New Excerpt (leave empty to keep)',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'post_status',
						'label'    => 'New Status (leave empty to keep)',
						'type'     => 'select',
						'required' => false,
						'default'  => '',
						'options'  => array_merge(
							[ [ 'label' => '— Keep current —', 'value' => '' ] ],
							static::ecm_status_options()
						),
					],
				];

			case 'delete_item':
				return [
					$post_id,
					$item_type,
					[
						'key'      => 'force_delete',
						'label'    => 'Delete Permanently',
						'type'     => 'select',
						'required' => false,
						'default'  => 'no',
						'options'  => [
							[ 'label' => 'No — move to trash', 'value' => 'no' ],
							[ 'label' => 'Yes — delete permanently', 'value' => 'yes' ],
						],
					],
				];

			case 'get_item':
				return [ $post_id ];

			case 'find_item':
				return [
					static::ecm_post_type_select_field( true ),
					[
						'key'      => 'search_field',
						'label'    => 'Find By',
						'type'     => 'select',
						'required' => false,
						'default'  => 'title',
						'options'  => [
							[ 'label' => 'Title contains', 'value' => 'title' ],
							[ 'label' => 'Custom field equals', 'value' => 'meta' ],
						],
					],
					[
						'key'      => 'meta_key',
						'label'    => 'Field (when Find By is Custom field)',
						'type'     => 'select',
						'required' => false,
						'dynamic'  => [
							'integration' => 'easycontentmanager',
							'query'       => 'post_meta_keys',
							'select'      => [ 'value', 'label' ],
						],
					],
					[
						'key'      => 'search_value',
						'label'    => 'Search Value',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'limit',
						'label'    => 'Maximum Results',
						'type'     => 'text',
						'required' => false,
						'default'  => '10',
					],
				];

			case 'change_item_status':
				return [
					$post_id,
					array_merge( $status, [ 'required' => true ] ),
				];

			case 'publish_item':
				return [ $post_id ];

			case 'duplicate_item':
				return [
					$post_id,
					[
						'key'      => 'new_status',
						'label'    => 'Copy Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'draft',
						'options'  => static::ecm_status_options(),
					],
				];

			case 'update_custom_field_value':
				return [ $post_id, $meta_key, $value ];

			case 'get_custom_field_value':
				return [ $post_id, $meta_key ];

			case 'update_multiple_custom_fields':
				return [
					$post_id,
					[
						'key'     => 'fields',
						'label'   => 'Fields (one row per field)',
						'type'    => 'map',
						'help'    => 'Add a row per custom field: key = field name, value = new value.',
						'fields'  => [
							[
								'key'  => 'key',
								'label' => 'Field',
								'type' => 'text',
							],
							[
								'key'  => 'value',
								'label' => 'Value',
								'type' => 'expression',
							],
						],
					],
				];

			case 'create_user':
				return [
					[
						'key'      => 'user_login',
						'label'    => 'Username',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'user_email',
						'label'    => 'Email',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'user_pass',
						'label'    => 'Password (leave empty to generate)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'display_name',
						'label'    => 'Display Name',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'role',
						'label'    => 'Role',
						'type'     => 'text',
						'required' => false,
						'default'  => 'subscriber',
					],
				];

			case 'update_user':
				return [
					$user_id,
					[
						'key'      => 'user_email',
						'label'    => 'New Email (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'display_name',
						'label'    => 'New Display Name (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'role',
						'label'    => 'New Role (leave empty to keep)',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'user_pass',
						'label'    => 'New Password (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
				];

			case 'update_user_custom_meta':
				return [
					$user_id,
					static::ecm_user_meta_key_field(),
					$value,
				];

			case 'get_user_custom_meta':
				return [
					$user_id,
					static::ecm_user_meta_key_field(),
				];

			case 'create_taxonomy_term':
				return [
					static::ecm_taxonomy_select_field(),
					[
						'key'      => 'name',
						'label'    => 'Term Name',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'slug',
						'label'    => 'Slug (leave empty to generate)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'description',
						'label'    => 'Description',
						'type'     => 'textarea',
						'required' => false,
					],
				];

			case 'update_taxonomy_term':
				return [
					[
						'key'      => 'term_id',
						'label'    => 'Term ID',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'name',
						'label'    => 'New Name (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'slug',
						'label'    => 'New Slug (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'description',
						'label'    => 'New Description (leave empty to keep)',
						'type'     => 'textarea',
						'required' => false,
					],
				];

			case 'delete_taxonomy_term':
				return [
					[
						'key'      => 'term_id',
						'label'    => 'Term ID',
						'type'     => 'expression',
						'required' => true,
					],
					static::ecm_taxonomy_select_field(),
				];

			case 'assign_term_to_item':
			case 'remove_term_from_item':
				return [
					$post_id,
					static::ecm_taxonomy_select_field(),
					[
						'key'      => 'terms',
						'label'    => 'Terms (comma-separated names/IDs or array)',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'approve_frontend_submission':
				return [ $post_id ];

			case 'reject_frontend_submission':
				return [
					$post_id,
					array_merge( $status, [ 'default' => 'draft' ] ),
				];

			case 'approve_review':
			case 'reject_review':
			case 'delete_review':
				return [
					[
						'key'      => 'review_id',
						'label'    => 'Review ID',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'approve_claim':
			case 'reject_claim':
			case 'delete_claim':
				return [
					[
						'key'      => 'claim_id',
						'label'    => 'Claim ID',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'add_bookmark':
			case 'remove_bookmark':
			case 'add_upvote':
			case 'remove_upvote':
				return [ $post_id, $user_id ];

			case 'add_reaction':
				return [
					$post_id,
					$user_id,
					[
						'key'      => 'reaction',
						'label'    => 'Reaction Key',
						'type'     => 'text',
						'required' => true,
						'help'     => 'One of the reaction keys configured in ECM (e.g. like, love).',
					],
				];

			case 'remove_reaction':
				return [ $post_id, $user_id ];
		}//end switch

		return [];
	}

	protected static function ecm_post_type_select_field( bool $required ): array {
		return [
			'key'      => 'post_type',
			'label'    => $required ? 'Post Type' : 'Post Type (leave empty for any ECM post type)',
			'type'     => 'select',
			'required' => $required,
			'dynamic'  => [
				'integration' => 'easycontentmanager',
				'query'       => 'post_types',
				'select'      => [ 'value', 'label' ],
			],
		];
	}

	protected static function ecm_taxonomy_select_field(): array {
		return [
			'key'      => 'taxonomy',
			'label'    => 'Taxonomy',
			'type'     => 'select',
			'required' => true,
			'dynamic'  => [
				'integration' => 'easycontentmanager',
				'query'       => 'taxonomies',
				'select'      => [ 'value', 'label' ],
			],
		];
	}

	protected static function ecm_meta_key_field(): array {
		return [
			'key'      => 'meta_key',
			'label'    => 'Field',
			'type'     => 'select',
			'required' => true,
			'dynamic'  => [
				'integration' => 'easycontentmanager',
				'query'       => 'post_meta_keys',
				'select'      => [ 'value', 'label' ],
			],
		];
	}

	protected static function ecm_user_meta_key_field(): array {
		return [
			'key'      => 'meta_key',
			'label'    => 'User Field',
			'type'     => 'select',
			'required' => true,
			'dynamic'  => [
				'integration' => 'easycontentmanager',
				'query'       => 'user_meta_keys',
				'select'      => [ 'value', 'label' ],
			],
		];
	}

	protected static function ecm_status_options(): array {
		return [
			[ 'label' => 'Publish', 'value' => 'publish' ],
			[ 'label' => 'Draft', 'value' => 'draft' ],
			[ 'label' => 'Pending Review', 'value' => 'pending' ],
			[ 'label' => 'Private', 'value' => 'private' ],
		];
	}

	/**
	 * Each query returns rows of [ 'value' => …, 'label' => … ] for the builder
	 * select fields declared above.
	 */
	protected static function ecm_dynamic_queries(): array {
		return [
			'post_types' => static function (): array {
				$rows = [];
				foreach ( static::ecm_post_type_slugs() as $slug => $label ) {
					$rows[] = [ 'value' => $slug, 'label' => $label ];
				}
				return $rows;
			},
			'taxonomies' => static function (): array {
				$rows = [];
				foreach ( static::ecm_taxonomy_slugs() as $slug => $label ) {
					$rows[] = [ 'value' => $slug, 'label' => $label ];
				}
				return $rows;
			},
			'post_meta_keys' => static function (): array {
				$rows = [];
				foreach ( static::ecm_field_meta_keys() as $key ) {
					$rows[] = [ 'value' => $key, 'label' => $key ];
				}
				return $rows;
			},
			'user_meta_keys' => static function (): array {
				$rows = [];
				foreach ( static::ecm_user_meta_keys() as $key ) {
					$rows[] = [ 'value' => $key, 'label' => $key ];
				}
				return $rows;
			},
		];
	}

	protected static function ecm_trigger_sample( string $event ): array {
		$item = [
			'item_id'     => 42,
			'post_id'     => 42,
			'title'       => 'Sample Item',
			'slug'        => 'sample-item',
			'status'      => 'publish',
			'post_type'   => 'listings',
			'content'     => 'Sample content.',
			'excerpt'     => '',
			'author_id'   => 1,
			'author_name' => 'Admin',
			'permalink'   => 'https://example.com/sample-item',
			'created_at'  => '2026-01-01 12:00:00',
			'modified_at' => '2026-01-01 12:00:00',
		];

		$user = [
			'user_id'      => 5,
			'user_login'   => 'jane',
			'user_email'   => 'jane@example.com',
			'display_name' => 'Jane Doe',
			'roles'        => [ 'subscriber' ],
		];

		$term = [
			'term_id'     => 9,
			'name'        => 'Featured',
			'slug'        => 'featured',
			'description' => '',
			'taxonomy'    => 'ecm_tag',
		];

		$review = [
			'review_id'    => 77,
			'post_id'      => 42,
			'author_name'  => 'Jane Doe',
			'author_email' => 'jane@example.com',
			'content'      => 'Great experience!',
			'rating'       => 4.5,
		];

		switch ( $event ) {
			case 'item_created':
				return array_merge( $item, [ 'created_at_time' => '2026-01-01 12:00:00' ] );

			case 'specific_item_created':
			case 'frontend_submission_created':
				return array_merge( $item, [ 'post_status' => 'draft' ], [ 'submitted_at' => '2026-01-01 12:00:00' ] );

			case 'item_updated':
			case 'specific_item_updated':
				return array_merge( $item, [ 'previous_title' => 'Old Title', 'previous_status' => 'draft' ] );

			case 'item_deleted':
				return array_merge( $item, [ 'deleted_at' => '2026-01-01 12:00:00' ] );

			case 'frontend_submission_status_changed':
				return array_merge( $item, [ 'new_status' => 'publish', 'old_status' => 'draft' ] );

			case 'item_status_changed':
				return array_merge( $item, [ 'new_status' => 'pending', 'old_status' => 'publish' ] );

			case 'item_published':
			case 'specific_item_published':
				return array_merge( $item, [ 'new_status' => 'publish', 'old_status' => 'draft' ] );

			case 'custom_field_updated':
				return array_merge( $item, [ 'meta_key' => 'price', 'value' => '199', 'previous_value' => '149', 'updated_at' => '2026-01-01 12:00:00' ] );

			case 'user_created':
				return array_merge( $user, [ 'registered_at' => '2026-01-01 12:00:00' ] );

			case 'user_meta_updated':
				return array_merge( $user, [ 'meta_key' => 'phone', 'value' => '+8801700000000', 'updated_at' => '2026-01-01 12:00:00' ] );

			case 'term_created':
			case 'term_updated':
				return array_merge( $term, [ 'event_kind' => 'term_updated' === $event ? 'updated' : 'created' ] );

			case 'term_deleted':
				return array_merge( $term, [ 'event_kind' => 'deleted', 'deleted_at' => '2026-01-01 12:00:00' ] );

			case 'review_submitted':
				return array_merge( $review, [ 'submitted_at' => '2026-01-01 12:00:00' ] );

			case 'review_approved':
				return array_merge( $review, [ 'new_status' => 'approved', 'old_status' => 'hold', 'changed_at' => '2026-01-01 12:00:00' ] );

			case 'review_rejected':
				return array_merge( $review, [ 'new_status' => 'spam', 'old_status' => 'approved', 'changed_at' => '2026-01-01 12:00:00' ] );

			case 'rating_submitted':
				return [ 'review_id' => 77, 'post_id' => 42, 'author_name' => 'Jane Doe', 'rating' => 4.5, 'submitted_at' => '2026-01-01 12:00:00' ];

			case 'claim_submitted':
				return [
					'post_id'       => 42,
					'name'          => 'Jane Doe',
					'email'         => 'jane@example.com',
					'phone'         => '+8801700000000',
					'proof_content' => 'I own this listing.',
					'status'        => 'pending',
					'submitted_at'  => '2026-01-01 12:00:00',
				];

			case 'claim_approved':
			case 'claim_rejected':
				return [
					'claim_id'        => 12,
					'status'          => 'claim_approved' === $event ? 'approved' : 'rejected',
					'previous_status' => 'pending',
					'changed_at'      => '2026-01-01 12:00:00',
				];

			case 'bookmark_added':
			case 'bookmark_removed':
				return array_merge( $item, [
					'event_kind'      => 'bookmark_added' === $event ? 'added' : 'removed',
					'user_ids'        => [ 5 ],
					'user_id'         => 5,
					'total_bookmarks' => 3,
				] );

			case 'upvote_added':
				return array_merge( $item, [
					'event_kind'   => 'upvoted',
					'user_id'      => 5,
					'is_guest'     => false,
					'upvote_count' => 12,
				] );

			case 'reaction_added':
			case 'reaction_removed':
				return array_merge( $item, [
					'event_kind'      => 'reaction_added' === $event ? 'added' : 'removed',
					'reaction'        => 'like',
					'user_id'         => 5,
					'changes'         => [ [ 'reaction' => 'like', 'user_id' => 5 ] ],
					'total_reactions' => 8,
				] );
		}//end switch

		// Keyword fallbacks so no trigger returns [].
		if ( false !== strpos( $event, 'user' ) ) {
			return $user;
		}
		if ( false !== strpos( $event, 'term' ) ) {
			return $term;
		}
		if ( false !== strpos( $event, 'review' ) || false !== strpos( $event, 'rating' ) ) {
			return $review;
		}

		return $item;
	}

	protected static function ecm_action_sample( string $action ): array {
		$item = static::ecm_trigger_sample( 'item_created' );

		switch ( $action ) {
			case 'create_item':
			case 'duplicate_item':
			case 'get_item':
			case 'find_item':
				return 'find_item' === $action
					? [ 'items' => [ $item ], 'found' => 1 ]
					: $item;

			case 'update_item':
			case 'change_item_status':
			case 'publish_item':
			case 'approve_frontend_submission':
			case 'reject_frontend_submission':
				return array_merge( $item, [ 'success' => true ] );

			case 'delete_item':
				return [ 'success' => true, 'item_id' => 42 ];

			case 'update_custom_field_value':
				return [ 'success' => true, 'item_id' => 42, 'meta_key' => 'price', 'value' => '199' ];

			case 'get_custom_field_value':
				return [ 'item_id' => 42, 'meta_key' => 'price', 'value' => '199' ];

			case 'update_multiple_custom_fields':
				return [ 'success' => true, 'item_id' => 42, 'updated' => [ 'price' => '199' ] ];

			case 'create_user':
				return array_merge( static::ecm_trigger_sample( 'user_created' ), [ 'success' => true ] );

			case 'update_user':
				return array_merge( static::ecm_trigger_sample( 'user_created' ), [ 'success' => true ] );

			case 'update_user_custom_meta':
				return [ 'success' => true, 'user_id' => 5, 'meta_key' => 'phone', 'value' => '+8801700000000' ];

			case 'get_user_custom_meta':
				return [ 'user_id' => 5, 'meta_key' => 'phone', 'value' => '+8801700000000' ];

			case 'create_taxonomy_term':
			case 'update_taxonomy_term':
				return array_merge( static::ecm_trigger_sample( 'term_created' ), [ 'success' => true ] );

			case 'delete_taxonomy_term':
				return [ 'success' => true, 'term_id' => 9 ];

			case 'assign_term_to_item':
			case 'remove_term_from_item':
				return [ 'success' => true, 'item_id' => 42, 'taxonomy' => 'ecm_tag', 'terms' => [ 'Featured' ] ];

			case 'approve_review':
			case 'reject_review':
			case 'delete_review':
				return [ 'success' => true, 'review_id' => 77 ];

			case 'approve_claim':
			case 'reject_claim':
			case 'delete_claim':
				return [ 'success' => true, 'claim_id' => 12 ];

			case 'add_bookmark':
			case 'remove_bookmark':
				return [ 'success' => true, 'item_id' => 42, 'user_id' => 5, 'total_bookmarks' => 3 ];

			case 'add_upvote':
			case 'remove_upvote':
				return [ 'success' => true, 'item_id' => 42, 'user_id' => 5, 'upvote_count' => 12 ];

			case 'add_reaction':
			case 'remove_reaction':
				return [ 'success' => true, 'item_id' => 42, 'user_id' => 5, 'reaction' => 'like' ];
		}//end switch

		return [ 'success' => true ];
	}
}
