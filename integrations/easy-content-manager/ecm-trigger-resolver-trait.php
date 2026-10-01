<?php

namespace Zaplane\Integrations\EasyContentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait EcmTriggerResolverTrait {

	/**
	 * Resolve one trigger node against raw hook args. Returns the payload
	 * array, or false when the event does not apply to this node.
	 */
	protected static function ecm_resolve_trigger( array $node, array $args ) {
		$event = (string) ( $node['event'] ?? '' );
		$data  = is_array( $node['data'] ?? null ) ? $node['data'] : [];
		$config = is_array( $data['config'] ?? null ) ? $data['config'] : [];

		switch ( $event ) {
			case 'item_created':
			case 'specific_item_created':
				return static::ecm_resolve_item_created( $config, $args, $event );

			case 'item_updated':
			case 'specific_item_updated':
				return static::ecm_resolve_item_updated( $config, $args, $event );

			case 'item_deleted':
				return static::ecm_resolve_item_deleted( $args );

			case 'item_status_changed':
			case 'item_published':
			case 'specific_item_published':
				return static::ecm_resolve_item_status( $config, $args, $event );

			case 'custom_field_updated':
				return static::ecm_resolve_custom_field_updated( $config, $args );

			case 'user_created':
				return static::ecm_resolve_user_created( $args );

			case 'user_meta_updated':
				return static::ecm_resolve_user_meta_updated( $config, $args );

			case 'term_created':
			case 'term_updated':
				return static::ecm_resolve_term_saved( $config, $args, $event );

			case 'term_deleted':
				return static::ecm_resolve_term_deleted( $config, $args );

			case 'frontend_submission_created':
				return static::ecm_resolve_frontend_submission_created( $args );

			case 'frontend_submission_status_changed':
				return static::ecm_resolve_frontend_submission_status( $args );

			case 'review_submitted':
				return static::ecm_resolve_review_submitted( $args );

			case 'review_approved':
			case 'review_rejected':
				return static::ecm_resolve_review_status( $args, $event );

			case 'rating_submitted':
				return static::ecm_resolve_rating_submitted( $args );

			case 'claim_submitted':
			case 'claim_approved':
			case 'claim_rejected':
				return static::ecm_resolve_claim( $config, $args, $event );

			case 'bookmark_added':
			case 'bookmark_removed':
				return static::ecm_resolve_bookmark( $args, $event );

			case 'upvote_added':
				return static::ecm_resolve_upvote_added( $args );

			case 'reaction_added':
			case 'reaction_removed':
				return static::ecm_resolve_reaction( $args, $event );
		}//end switch

		return false;
	}

	/**
	 * wp_insert_post: ( $post_id, $post, $update ).
	 */
	protected static function ecm_resolve_item_created( array $config, array $args, string $event ) {
		$post_id = absint( $args[0] ?? 0 );

		if ( ! $post_id || ! static::ecm_is_managed_item( $post_id ) ) {
			return false;
		}

		// Revisions and auto-drafts are not real creations.
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || in_array( (string) ( $post->post_status ?? '' ), [ 'auto-draft', 'inherit' ], true ) ) {
			return false;
		}

		// wp_insert_post fires with $update = false for initial saves only.
		if ( false !== ( $args[2] ?? false ) ) {
			return false;
		}

		if ( 'specific_item_created' === $event ) {
			$required = (string) ( $config['post_type'] ?? '' );
			if ( '' === $required || (string) ( $post->post_type ?? '' ) !== $required ) {
				return false;
			}
		}

		return static::ecm_item_payload( $post_id, [ 'created_at_time' => current_time( 'mysql' ) ] );
	}

	/**
	 * post_updated: ( $post_id, $post_after, $post_before ).
	 */
	protected static function ecm_resolve_item_updated( array $config, array $args, string $event ) {
		$post_id = absint( $args[0] ?? 0 );

		if ( ! $post_id || ! static::ecm_is_managed_item( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( 'specific_item_updated' === $event ) {
			$required = (string) ( $config['post_type'] ?? '' );
			if ( '' === $required || (string) ( $post->post_type ?? '' ) !== $required ) {
				return false;
			}
		}

		$before = is_object( $args[2] ?? null ) ? $args[2] : null;

		return static::ecm_item_payload(
			$post_id,
			[
				'previous_title'  => $before ? (string) ( $before->post_title ?? '' ) : '',
				'previous_status' => $before ? (string) ( $before->post_status ?? '' ) : '',
			]
		);
	}

	/**
	 * before_delete_post: ( $post_id, $post ).
	 */
	protected static function ecm_resolve_item_deleted( array $args ) {
		$post_id = absint( $args[0] ?? 0 );

		if ( ! $post_id || ! static::ecm_is_managed_item( $post_id ) ) {
			return false;
		}

		return static::ecm_item_payload( $post_id, [ 'deleted_at' => current_time( 'mysql' ) ] );
	}

	/**
	 * transition_post_status: ( $new_status, $old_status, $post ).
	 */
	protected static function ecm_resolve_item_status( array $config, array $args, string $event ) {
		$new_status = (string) ( $args[0] ?? '' );
		$old_status = (string) ( $args[1] ?? '' );
		$post       = is_object( $args[2] ?? null ) ? $args[2] : null;
		$post_id    = $post ? absint( $post->ID ?? 0 ) : 0;

		if ( ! $post_id || ! static::ecm_is_managed_item( $post_id ) ) {
			return false;
		}

		if ( in_array( $event, [ 'item_published', 'specific_item_published' ], true ) && 'publish' !== $new_status ) {
			return false;
		}

		if ( 'specific_item_published' === $event ) {
			$required = (string) ( $config['post_type'] ?? '' );
			if ( '' === $required || (string) ( $post->post_type ?? '' ) !== $required ) {
				return false;
			}
		}

		return static::ecm_item_payload(
			$post_id,
			[
				'new_status' => $new_status,
				'old_status' => $old_status,
			]
		);
	}

	/**
	 * update_post_meta / added_post_meta: ( $meta_id, $object_id, $meta_key, $meta_value ).
	 */
	protected static function ecm_resolve_custom_field_updated( array $config, array $args ) {
		$post_id  = absint( $args[1] ?? 0 );
		$meta_key = (string) ( $args[2] ?? '' );

		if ( ! $post_id || '' === $meta_key ) {
			return false;
		}

		// Internal ECM bookkeeping and every underscore-prefixed key are not
		// user-authored field values.
		if ( 0 === strpos( $meta_key, '_' ) || in_array( $meta_key, static::ECM_INTERNAL_META_KEYS, true ) ) {
			return false;
		}

		$allowed = static::ecm_field_meta_keys( (string) get_post_type( $post_id ) );
		if ( ! in_array( $meta_key, $allowed, true ) ) {
			return false;
		}

		$required_field = (string) ( $config['meta_key'] ?? '' );
		if ( '' !== $required_field && $required_field !== $meta_key ) {
			return false;
		}

		$hook = function_exists( 'current_filter' ) ? (string) current_filter() : '';

		return static::ecm_item_payload(
			$post_id,
			[
				'meta_key'       => $meta_key,
				'value'          => static::ecm_maybe_unserialize( $args[3] ?? '' ),
				'previous_value' => static::ecm_old_meta_list( $hook, $post_id, $meta_key ) ? static::ecm_old_meta_list( $hook, $post_id, $meta_key ) : null,
				'updated_at'     => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * user_register: ( $user_id ).
	 */
	protected static function ecm_resolve_user_created( array $args ) {
		$user_id = absint( $args[0] ?? 0 );

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return [
			'user_id'     => $user_id,
			'user_login'  => (string) ( $user->user_login ?? '' ),
			'user_email'  => (string) ( $user->user_email ?? '' ),
			'display_name' => (string) ( $user->display_name ?? '' ),
			'roles'       => isset( $user->roles ) ? array_values( (array) $user->roles ) : [],
			'registered_at' => current_time( 'mysql' ),
		];
	}

	/**
	 * updated_user_meta / added_user_meta: ( $meta_id, $user_id, $meta_key, $meta_value ).
	 */
	protected static function ecm_resolve_user_meta_updated( array $config, array $args ) {
		$user_id  = absint( $args[1] ?? 0 );
		$meta_key = (string) ( $args[2] ?? '' );

		if ( ! $user_id || '' === $meta_key ) {
			return false;
		}

		if ( 0 === strpos( $meta_key, '_' ) || in_array( $meta_key, static::ECM_INTERNAL_USER_META_KEYS, true ) ) {
			return false;
		}

		$allowed = static::ecm_user_meta_keys();
		if ( ! in_array( $meta_key, $allowed, true ) ) {
			return false;
		}

		$required_field = (string) ( $config['meta_key'] ?? '' );
		if ( '' !== $required_field && $required_field !== $meta_key ) {
			return false;
		}

		return [
			'user_id'    => $user_id,
			'meta_key'   => $meta_key,
			'value'      => static::ecm_maybe_unserialize( $args[3] ?? '' ),
			'updated_at' => current_time( 'mysql' ),
		];
	}

	/**
	 * created_term / edited_term: ( $term_id, $tt_id, $taxonomy ).
	 */
	protected static function ecm_resolve_term_saved( array $config, array $args, string $event ) {
		$taxonomy = (string) ( $args[2] ?? '' );

		if ( ! static::ecm_is_managed_taxonomy( $taxonomy, $config ) ) {
			return false;
		}

		$term_id = absint( $args[0] ?? 0 );
		$term    = $term_id ? get_term( $term_id, $taxonomy ) : null;

		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		return static::ecm_term_payload( $term, $taxonomy, [ 'event_kind' => 'term_created' === $event ? 'created' : 'updated' ] );
	}

	/**
	 * delete_term: ( $term_id, $tt_id, $taxonomy, $deleted_term, $object_ids ).
	 */
	protected static function ecm_resolve_term_deleted( array $config, array $args ) {
		$taxonomy = (string) ( $args[2] ?? '' );

		if ( ! static::ecm_is_managed_taxonomy( $taxonomy, $config ) ) {
			return false;
		}

		$deleted = is_object( $args[3] ?? null ) ? $args[3] : null;

		if ( ! $deleted ) {
			return false;
		}

		$payload = static::ecm_term_payload( $deleted, $taxonomy, [ 'event_kind' => 'deleted' ] );
		$payload['deleted_at'] = current_time( 'mysql' );

		return $payload;
	}

	protected static function ecm_term_payload( $term, string $taxonomy, array $extra = [] ): array {
		return array_merge(
			[
				'term_id'     => (int) ( $term->term_id ?? 0 ),
				'name'        => (string) ( $term->name ?? '' ),
				'slug'        => (string) ( $term->slug ?? '' ),
				'description' => (string) ( $term->description ?? '' ),
				'taxonomy'    => $taxonomy,
			],
			$extra
		);
	}

	protected static function ecm_is_managed_taxonomy( string $taxonomy, array $config ): bool {
		if ( '' === $taxonomy || ! static::ecm_active() ) {
			return false;
		}

		$required = (string) ( $config['taxonomy'] ?? '' );
		if ( '' !== $required && $required !== $taxonomy ) {
			return false;
		}

		return array_key_exists( $taxonomy, static::ecm_taxonomy_slugs() );
	}

	/**
	 * Frontend submissions are posts inserted by ECM's
	 * easy-content-manager/save_user_post AJAX endpoint — that AJAX action has
	 * already fired by the time the endpoint calls wp_insert_post().
	 */
	protected static function ecm_resolve_frontend_submission_created( array $args ) {
		$post_id = absint( $args[0] ?? 0 );

		if ( ! $post_id || ! static::ecm_is_managed_item( $post_id ) ) {
			return false;
		}

		if ( ! static::ecm_did_action( 'wp_ajax_easy-content-manager/save_user_post' ) ) {
			return false;
		}

		return static::ecm_item_payload( $post_id, [ 'submitted_at' => current_time( 'mysql' ) ] );
	}

	/**
	 * A status transition on a post type the frontend-submission addon covers.
	 */
	protected static function ecm_resolve_frontend_submission_status( array $args ) {
		$post       = is_object( $args[2] ?? null ) ? $args[2] : null;
		$post_id    = $post ? absint( $post->ID ?? 0 ) : 0;
		$new_status = (string) ( $args[0] ?? '' );
		$old_status = (string) ( $args[1] ?? '' );

		if ( ! $post_id || $new_status === $old_status || '' === $new_status ) {
			return false;
		}

		$type = (string) ( $post->post_type ?? '' );

		if ( ! static::ecm_is_managed_item( $post_id ) || ! static::ecm_addon_for_post_type( 'frontend-submission', $type ) ) {
			return false;
		}

		return static::ecm_item_payload(
			$post_id,
			[
				'new_status' => $new_status,
				'old_status' => $old_status,
			]
		);
	}

	/**
	 * wp_insert_comment: ( $comment_id, $comment ).
	 */
	protected static function ecm_resolve_review_submitted( array $args ) {
		if ( ! static::ecm_addon_enabled( 'review-and-ratings' ) ) {
			return false;
		}

		$comment = static::ecm_comment_from_args( $args );
		if ( ! $comment || static::ecm_review_type() !== (string) ( $comment['comment_type'] ?? '' ) ) {
			return false;
		}

		$comment_id = absint( $comment['comment_ID'] ?? 0 );
		$post_id    = absint( $comment['comment_post_ID'] ?? 0 );

		if ( ! $comment_id ) {
			return false;
		}

		return [
			'review_id'    => $comment_id,
			'post_id'      => $post_id,
			'author_name'  => (string) ( $comment['comment_author'] ?? '' ),
			'author_email' => (string) ( $comment['comment_author_email'] ?? '' ),
			'content'      => (string) ( $comment['comment_content'] ?? '' ),
			'rating'       => static::ecm_comment_rating( $comment_id ),
			'submitted_at' => current_time( 'mysql' ),
		];
	}

	/**
	 * transition_comment_status: ( $new_status, $old_status, $comment ).
	 * wp_update_comment() fires this too, so both the moderation UI and ECM's
	 * REST admin routes land here.
	 */
	protected static function ecm_resolve_review_status( array $args, string $event ) {
		if ( ! static::ecm_addon_enabled( 'review-and-ratings' ) ) {
			return false;
		}

		$new_status = (string) ( $args[0] ?? '' );
		$old_status = (string) ( $args[1] ?? '' );
		$comment    = static::ecm_comment_from_args( array_slice( $args, 2 ) );

		if ( ! $comment || static::ecm_review_type() !== (string) ( $comment['comment_type'] ?? '' ) ) {
			return false;
		}

		if ( 'review_approved' === $event ) {
			if ( 'approved' !== $new_status ) {
				return false;
			}
		} elseif ( 'approved' === $new_status || $new_status === $old_status ) {
			return false;
		}

		$comment_id = absint( $comment['comment_ID'] ?? 0 );

		return [
			'review_id'    => $comment_id,
			'post_id'      => absint( $comment['comment_post_ID'] ?? 0 ),
			'author_name'  => (string) ( $comment['comment_author'] ?? '' ),
			'content'      => (string) ( $comment['comment_content'] ?? '' ),
			'rating'       => static::ecm_comment_rating( $comment_id ),
			'new_status'   => $new_status,
			'old_status'   => $old_status,
			'changed_at'   => current_time( 'mysql' ),
		];
	}

	/**
	 * added_comment_meta / updated_comment_meta: ( $meta_id, $comment_id, $meta_key, $meta_value ).
	 * The rating meta is written right after the comment insert, so watching
	 * the meta rather than the comment catches ratings as soon as they exist.
	 */
	protected static function ecm_resolve_rating_submitted( array $args ) {
		if ( ! static::ecm_addon_enabled( 'review-and-ratings' ) ) {
			return false;
		}

		$meta_key = (string) ( $args[2] ?? '' );

		if ( '_ecm_rating' !== $meta_key ) {
			return false;
		}

		$comment_id = absint( $args[1] ?? 0 );
		$comment    = $comment_id ? get_comment( $comment_id ) : null;

		if ( ! $comment || is_wp_error( $comment ) ) {
			return false;
		}

		$comment = static::ecm_normalize_comment( $comment );

		if ( static::ecm_review_type() !== (string) ( $comment['comment_type'] ?? '' ) ) {
			return false;
		}

		return [
			'review_id'    => $comment_id,
			'post_id'      => absint( $comment['comment_post_ID'] ?? 0 ),
			'author_name'  => (string) ( $comment['comment_author'] ?? '' ),
			'rating'       => (float) ( $args[3] ?? 0 ),
			'submitted_at' => current_time( 'mysql' ),
		];
	}

	protected static function ecm_comment_rating( int $comment_id ): float {
		$rating = $comment_id ? get_comment_meta( $comment_id, '_ecm_rating', true ) : 0;

		return (float) ( is_numeric( $rating ) ? $rating : 0 );
	}

	/**
	 * Accepts the comment object/array from any comment hook and normalizes to
	 * an associative array.
	 */
	protected static function ecm_comment_from_args( array $args ) {
		$comment = $args[1] ?? ( $args[0] ?? null );

		if ( is_numeric( $comment ) ) {
			$comment = get_comment( (int) $comment );
		}

		if ( ! $comment ) {
			return null;
		}

		return static::ecm_normalize_comment( $comment );
	}

	protected static function ecm_normalize_comment( $comment ): array {
		if ( is_array( $comment ) ) {
			return $comment;
		}

		if ( is_object( $comment ) ) {
			return (array) $comment;
		}

		return [];
	}

	/**
	 * The Claim addon persists to its own table and exposes submit/status
	 * changes only through its REST routes, so the triggers read the request
	 * itself on rest_pre_dispatch. See the trait docblock for why returning
	 * false here (never a payload for other routes) is load-bearing.
	 */
	protected static function ecm_resolve_claim( array $config, array $args, string $event ) {
		if ( ! static::ecm_addon_enabled( 'claim' ) ) {
			return false;
		}

		$request = $args[2] ?? null;

		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return false;
		}

		$route = (string) $request->get_route();

		if ( 'claim_submitted' === $event ) {
			if ( '/easy-content-manager/v1/claim/submit' !== $route ) {
				return false;
			}

			$post_id = absint( $request->get_param( 'post_id' ) );

			if ( ! $post_id ) {
				return false;
			}

			return [
				'post_id'       => $post_id,
				'name'          => (string) $request->get_param( 'name' ),
				'email'         => (string) $request->get_param( 'email' ),
				'phone'         => (string) $request->get_param( 'phone' ),
				'proof_content' => (string) $request->get_param( 'proof_content' ),
				'status'        => 'pending',
				'submitted_at'  => current_time( 'mysql' ),
			];
		}//end if

		// PATCH/PUT /easy-content-manager/v1/claims/{id} with a status param.
		if ( ! preg_match( '#^/easy-content-manager/v1/claims/(\d+)$#', $route, $matches ) ) {
			return false;
		}

		$method = strtoupper( (string) $request->get_method() );
		if ( ! in_array( $method, [ 'PATCH', 'PUT', 'POST' ], true ) ) {
			return false;
		}

		$status = (string) $request->get_param( 'status' );
		$wanted = 'claim_approved' === $event ? 'approved' : 'rejected';

		if ( $status !== $wanted ) {
			return false;
		}

		return [
			'claim_id'    => (int) $matches[1],
			'status'      => $status,
			'previous_status' => 'pending',
			'changed_at'  => current_time( 'mysql' ),
		];
	}

	/**
	 * update_post_meta / added_post_meta on ecm_bookmark_users.
	 * The value is the post's list of bookmarker user IDs.
	 */
	protected static function ecm_resolve_bookmark( array $args, string $event ) {
		if ( ! static::ecm_addon_enabled( 'bookmark' ) ) {
			return false;
		}

		$meta_key = (string) ( $args[2] ?? '' );

		if ( 'ecm_bookmark_users' !== $meta_key ) {
			return false;
		}

		$post_id  = absint( $args[1] ?? 0 );
		$hook     = function_exists( 'current_filter' ) ? (string) current_filter() : '';
		$new_list = static::ecm_normalize_id_list( $args[3] ?? [] );
		$old_list = static::ecm_old_meta_list( $hook, $post_id, $meta_key );

		$added   = array_values( array_diff( $new_list, $old_list ) );
		$removed = array_values( array_diff( $old_list, $new_list ) );

		if ( 'bookmark_added' === $event && empty( $added ) ) {
			return false;
		}

		if ( 'bookmark_removed' === $event && empty( $removed ) ) {
			return false;
		}

		$payload = static::ecm_item_payload( $post_id );

		if ( empty( $payload ) ) {
			$payload = [ 'post_id' => $post_id ];
		}

		$payload = array_merge(
			$payload,
			[
				'event_kind'      => 'bookmark_added' === $event ? 'added' : 'removed',
				'user_ids'        => 'bookmark_added' === $event ? $added : $removed,
				'user_id'         => (int) ( 'bookmark_added' === $event ? $added[0] ?? 0 : $removed[0] ?? 0 ),
				'total_bookmarks' => count( $new_list ),
			]
		);

		return $payload;
	}

	/**
	 * update_post_meta / added_post_meta on _ecm_upvoted_users (logged-in) or
	 * ecm_upvote_count (guest upvotes only move the counter).
	 */
	protected static function ecm_resolve_upvote_added( array $args ) {
		if ( ! static::ecm_addon_enabled( 'upvote' ) ) {
			return false;
		}

		$meta_key = (string) ( $args[2] ?? '' );
		$post_id  = absint( $args[1] ?? 0 );

		if ( ! $post_id ) {
			return false;
		}

		$hook     = function_exists( 'current_filter' ) ? (string) current_filter() : '';

		if ( '_ecm_upvoted_users' === $meta_key ) {
			$new_list = static::ecm_normalize_id_list( $args[3] ?? [] );
			$old_list = static::ecm_old_meta_list( $hook, $post_id, $meta_key );
			$added    = array_values( array_diff( $new_list, $old_list ) );

			if ( empty( $added ) ) {
				return false;
			}

			$user_id = (int) $added[0];
		} elseif ( 'ecm_upvote_count' === $meta_key ) {
			// Anonymous upvotes: the count rising is the only signal.
			$old = (int) get_post_meta( $post_id, 'ecm_upvote_count', true );
			$new = (int) ( $args[3] ?? 0 );

			if ( $new <= $old ) {
				return false;
			}

			$user_id = 0;
		} else {
			return false;
		}//end if

		$payload = static::ecm_item_payload( $post_id );

		if ( empty( $payload ) ) {
			$payload = [ 'post_id' => $post_id ];
		}

		return array_merge(
			$payload,
			[
				'event_kind'  => 'upvoted',
				'user_id'     => $user_id,
				'is_guest'    => 0 === $user_id,
				'upvote_count' => (int) get_post_meta( $post_id, 'ecm_upvote_count', true ),
			]
		);
	}

	/**
	 * update_post_meta / added_post_meta on _ecm_reactions
	 * ( [ reaction_key => [ user ids ] ] ).
	 */
	protected static function ecm_resolve_reaction( array $args, string $event ) {
		if ( ! static::ecm_addon_enabled( 'reactions' ) ) {
			return false;
		}

		$meta_key = (string) ( $args[2] ?? '' );

		if ( '_ecm_reactions' !== $meta_key ) {
			return false;
		}

		$post_id  = absint( $args[1] ?? 0 );
		$hook     = function_exists( 'current_filter' ) ? (string) current_filter() : '';
		$new_map  = is_array( $args[3] ?? null ) ? $args[3] : [];
		$old_map  = static::ecm_old_meta_list( $hook, $post_id, $meta_key );
		$old_map  = is_array( $old_map ) ? $old_map : [];

		$added   = [];
		$removed = [];

		foreach ( $new_map as $reaction => $users ) {
			foreach ( static::ecm_normalize_id_list( $users ) as $user_id ) {
				if ( ! in_array( $user_id, static::ecm_normalize_id_list( $old_map[ $reaction ] ?? [] ), true ) ) {
					$added[] = [
						'reaction' => (string) $reaction,
						'user_id' => $user_id
					];
				}
			}
		}

		foreach ( $old_map as $reaction => $users ) {
			foreach ( static::ecm_normalize_id_list( $users ) as $user_id ) {
				if ( ! in_array( $user_id, static::ecm_normalize_id_list( $new_map[ $reaction ] ?? [] ), true ) ) {
					$removed[] = [
						'reaction' => (string) $reaction,
						'user_id' => $user_id
					];
				}
			}
		}

		$changes = 'reaction_added' === $event ? $added : $removed;

		if ( empty( $changes ) ) {
			return false;
		}

		$payload = static::ecm_item_payload( $post_id );

		if ( empty( $payload ) ) {
			$payload = [ 'post_id' => $post_id ];
		}

		return array_merge(
			$payload,
			[
				'event_kind'      => 'reaction_added' === $event ? 'added' : 'removed',
				'reaction'        => (string) ( $changes[0]['reaction'] ?? '' ),
				'user_id'         => (int) ( $changes[0]['user_id'] ?? 0 ),
				'changes'         => $changes,
				'total_reactions' => count( $new_map, COUNT_RECURSIVE ) - count( $new_map ),
			]
		);
	}

	/**
	 * Meta list values arrive serialized strings sometimes; coerce to int list.
	 *
	 * @return array<int,int>
	 */
	protected static function ecm_normalize_id_list( $value ): array {
		$value = static::ecm_maybe_unserialize( $value );

		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/**
	 * Whether the item belongs to a post type ECM created.
	 */
	protected static function ecm_is_managed_item( int $post_id ): bool {
		if ( ! static::ecm_active() ) {
			return false;
		}

		$type = (string) get_post_type( $post_id );

		return '' !== $type && array_key_exists( $type, static::ecm_post_type_slugs() );
	}
}
