<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\EasyContentManager\EcmConfigTrait;
use Zaplane\Integrations\EasyContentManager\EcmTriggerResolverTrait;
use Zaplane\Integrations\EasyContentManager\EcmSchemasTrait;
use Zaplane\Integrations\EasyContentManager\EcmItemActionsTrait;
use Zaplane\Integrations\EasyContentManager\EcmUserTaxonomyActionsTrait;
use Zaplane\Integrations\EasyContentManager\EcmAddonActionsTrait;

class EasyContentManager extends IntegrationBase {

	use EcmConfigTrait;
	use EcmTriggerResolverTrait;
	use EcmSchemasTrait;
	use EcmItemActionsTrait;
	use EcmUserTaxonomyActionsTrait;
	use EcmAddonActionsTrait;

	public static function get_slug(): string {
		return 'easycontentmanager';
	}

	public static function get_name(): string {
		return 'Easy Content Manager (ECM)';
	}

	public static function get_category(): string {
		return 'app';
	}

	public static function get_icon(): string {
		return 'easycontentmanager.svg';
	}

	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/trigger-easy-content-manager/',
			'action'  => 'https://zaplane.app/docs/action-easy-content-manager/',
		];
	}

	public static function get_triggers(): array {
		return [
			'item_created'        => [
				'label' => 'Item Created',
				'hook'  => 'wp_insert_post',
			],
			'item_updated'        => [
				'label' => 'Item Updated',
				'hook'  => 'post_updated',
			],
			'item_deleted'        => [
				'label' => 'Item Deleted',
				'hook'  => 'before_delete_post',
			],
			'item_status_changed' => [
				'label' => 'Item Status Changed',
				'hook'  => 'transition_post_status',
			],
			'item_published'      => [
				'label' => 'Item Published',
				'hook'  => 'transition_post_status',
			],
			'specific_item_created'  => [
				'label' => 'Specific Post Type Item Created',
				'hook'  => 'wp_insert_post',
			],
			'specific_item_updated'  => [
				'label' => 'Specific Post Type Item Updated',
				'hook'  => 'post_updated',
			],
			'specific_item_published'  => [
				'label' => 'Specific Post Type Item Published',
				'hook'  => 'transition_post_status',
			],
			'custom_field_updated' => [
				'label' => 'Custom Field Value Updated',
				'hook'  => [ 'update_post_meta', 'added_post_meta' ],
			],
			'user_created'        => [
				'label' => 'User Created',
				'hook'  => 'user_register',
			],
			'user_meta_updated'   => [
				'label' => 'User Custom Meta Updated',
				'hook'  => [ 'updated_user_meta', 'added_user_meta' ],
			],
			'term_created'        => [
				'label' => 'Taxonomy Term Created',
				'hook'  => 'created_term',
			],
			'term_updated'        => [
				'label' => 'Taxonomy Term Updated',
				'hook'  => 'edited_term',
			],
			'term_deleted'        => [
				'label' => 'Taxonomy Term Deleted',
				'hook'  => 'delete_term',
			],
			'frontend_submission_created'        => [
				'label' => 'Frontend Submission Created',
				'hook'  => 'wp_insert_post',
			],
			'frontend_submission_status_changed' => [
				'label' => 'Frontend Submission Status Changed',
				'hook'  => 'transition_post_status',
			],
			'review_submitted'    => [
				'label' => 'Review Submitted',
				'hook'  => 'wp_insert_comment',
			],
			'review_approved'     => [
				'label' => 'Review Approved',
				'hook'  => 'transition_comment_status',
			],
			'review_rejected'     => [
				'label' => 'Review Rejected',
				'hook'  => 'transition_comment_status',
			],
			'rating_submitted'    => [
				'label' => 'Rating Submitted',
				'hook'  => [ 'added_comment_meta', 'updated_comment_meta' ],
			],
			'claim_submitted'     => [
				'label' => 'Claim Submitted',
				'hook'  => 'rest_pre_dispatch',
			],
			'claim_approved'      => [
				'label' => 'Claim Approved',
				'hook'  => 'rest_pre_dispatch',
			],
			'claim_rejected'      => [
				'label' => 'Claim Rejected',
				'hook'  => 'rest_pre_dispatch',
			],
			'bookmark_added'      => [
				'label' => 'Post Bookmarked',
				'hook'  => [ 'update_post_meta', 'added_post_meta' ],
			],
			'bookmark_removed'    => [
				'label' => 'Post Bookmark Removed',
				'hook'  => [ 'update_post_meta', 'added_post_meta' ],
			],
			'upvote_added'        => [
				'label' => 'Post Upvoted',
				'hook'  => [ 'update_post_meta', 'added_post_meta' ],
			],
			'reaction_added'      => [
				'label' => 'Post Reaction Added',
				'hook'  => [ 'update_post_meta', 'added_post_meta' ],
			],
			'reaction_removed'    => [
				'label' => 'Post Reaction Removed',
				'hook'  => [ 'update_post_meta', 'added_post_meta' ],
			],
		];
	}

	public static function get_actions(): array {
		return [
			'create_item'                   => [ 'label' => 'Create Item' ],
			'update_item'                   => [ 'label' => 'Update Item' ],
			'delete_item'                   => [ 'label' => 'Delete Item' ],
			'get_item'                      => [ 'label' => 'Get Item' ],
			'find_item'                     => [ 'label' => 'Find Item' ],
			'change_item_status'            => [ 'label' => 'Change Item Status' ],
			'publish_item'                  => [ 'label' => 'Publish Item' ],
			'duplicate_item'                => [ 'label' => 'Duplicate Item' ],
			'update_custom_field_value'     => [ 'label' => 'Update Custom Field Value' ],
			'get_custom_field_value'        => [ 'label' => 'Get Custom Field Value' ],
			'update_multiple_custom_fields' => [ 'label' => 'Update Multiple Custom Fields' ],
			'create_user'                   => [ 'label' => 'Create User' ],
			'update_user'                   => [ 'label' => 'Update User' ],
			'update_user_custom_meta'       => [ 'label' => 'Update User Custom Meta' ],
			'get_user_custom_meta'          => [ 'label' => 'Get User Custom Meta' ],
			'create_taxonomy_term'          => [ 'label' => 'Create Taxonomy Term' ],
			'update_taxonomy_term'          => [ 'label' => 'Update Taxonomy Term' ],
			'delete_taxonomy_term'          => [ 'label' => 'Delete Taxonomy Term' ],
			'assign_term_to_item'           => [ 'label' => 'Assign Taxonomy Term to Item' ],
			'remove_term_from_item'         => [ 'label' => 'Remove Taxonomy Term from Item' ],
			'approve_frontend_submission'   => [ 'label' => 'Approve Frontend Submission' ],
			'reject_frontend_submission'    => [ 'label' => 'Reject Frontend Submission' ],
			'approve_review'                => [ 'label' => 'Approve Review' ],
			'reject_review'                 => [ 'label' => 'Reject Review' ],
			'delete_review'                 => [ 'label' => 'Delete Review' ],
			'approve_claim'                 => [ 'label' => 'Approve Claim' ],
			'reject_claim'                  => [ 'label' => 'Reject Claim' ],
			'delete_claim'                  => [ 'label' => 'Delete Claim' ],
			'add_bookmark'                  => [ 'label' => 'Add Bookmark' ],
			'remove_bookmark'               => [ 'label' => 'Remove Bookmark' ],
			'add_upvote'                    => [ 'label' => 'Add Upvote' ],
			'remove_upvote'                 => [ 'label' => 'Remove Upvote' ],
			'add_reaction'                  => [ 'label' => 'Add Reaction' ],
			'remove_reaction'               => [ 'label' => 'Remove Reaction' ],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return static::ecm_trigger_config_schema( $trigger );
	}

	public static function get_trigger_sample_output( string $event ): array {
		return static::ecm_trigger_sample( $event );
	}

	public static function get_action_config_schema( string $action ): array {
		return static::ecm_action_config_schema( $action );
	}

	public static function get_action_sample_output( string $action ): array {
		return static::ecm_action_sample( $action );
	}

	public static function get_dynamic_queries(): array {
		return static::ecm_dynamic_queries();
	}

	public static function resolve_trigger( array $node, array $args ) {
		return static::ecm_resolve_trigger( $node, $args );
	}

	public static function execute_node( array $node, array $input ): array {
		$data   = is_array( $node['data'] ?? null ) ? $node['data'] : [];
		$config = is_array( $data['config'] ?? null ) ? $data['config'] : [];
		$event  = (string) ( $data['event'] ?? '' );

		if ( '' === $event ) {
			return static::ecm_unknown_action( '(no action configured)' );
		}

		$result = static::ecm_execute_item_action( $event, $config );

		if ( null !== $result ) {
			return $result;
		}

		$result = static::ecm_execute_user_taxonomy_action( $event, $config );

		if ( null !== $result ) {
			return $result;
		}

		$result = static::ecm_execute_addon_action( $event, $config );

		if ( null !== $result ) {
			return $result;
		}

		return static::ecm_unknown_action( $event );
	}
}
