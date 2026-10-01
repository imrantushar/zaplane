<?php

namespace Zaplane\Integrations\EasyContentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait EcmAddonActionsTrait {

	protected static function ecm_execute_addon_action( string $event, array $config ): ?array {
		switch ( $event ) {
			case 'approve_frontend_submission':
				return static::ecm_action_submission_status( $config, 'publish' );

			case 'reject_frontend_submission':
				return static::ecm_action_submission_status( $config, (string) ( $config['status'] ?? 'draft' ) );

			case 'approve_review':
				return static::ecm_action_review_status( $config, 'approve' );

			case 'reject_review':
				return static::ecm_action_review_status( $config, 'hold' );

			case 'delete_review':
				return static::ecm_action_delete_review( $config );

			case 'approve_claim':
				return static::ecm_action_claim_status( $config, 'approved' );

			case 'reject_claim':
				return static::ecm_action_claim_status( $config, 'rejected' );

			case 'delete_claim':
				return static::ecm_action_delete_claim( $config );

			case 'add_bookmark':
				return static::ecm_action_bookmark( $config, true );

			case 'remove_bookmark':
				return static::ecm_action_bookmark( $config, false );

			case 'add_upvote':
				return static::ecm_action_upvote( $config, true );

			case 'remove_upvote':
				return static::ecm_action_upvote( $config, false );

			case 'add_reaction':
				return static::ecm_action_reaction( $config, true );

			case 'remove_reaction':
				return static::ecm_action_reaction( $config, false );
		}//end switch

		return null;
	}

	private static function ecm_action_submission_status( array $config, string $status ): array {
		if ( ! static::ecm_addon_enabled( 'frontend-submission' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Frontend Submission addon is not active.' );
		}

		$post_id = absint( $config['post_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Submission not found.' );
		}

		if ( '' === $status ) {
			return static::ecm_action_error( 'A status is required.' );
		}

		$result = wp_update_post( [
			'ID' => $post_id,
			'post_status' => sanitize_key( $status )
		], true );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		return static::ecm_success(
			array_merge( static::ecm_item_payload( $post_id ), [ 'success' => true ] )
		);
	}

	private static function ecm_action_review_status( array $config, string $status ): array {
		if ( ! static::ecm_addon_enabled( 'review-and-ratings' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Review & Ratings addon is not active.' );
		}

		$review_id = absint( $config['review_id'] ?? 0 );
		$comment   = $review_id ? get_comment( $review_id ) : null;

		if ( ! $comment || is_wp_error( $comment ) ) {
			return static::ecm_action_error( 'Review not found.' );
		}

		$comment = static::ecm_normalize_comment( $comment );

		if ( static::ecm_review_type() !== (string) ( $comment['comment_type'] ?? '' ) ) {
			return static::ecm_action_error( 'That comment is not an ECM review.' );
		}

		$result = wp_set_comment_status( $review_id, $status );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		return static::ecm_success(
			[
				'success'   => true,
				'review_id' => $review_id,
				'status'    => $status,
			]
		);
	}

	private static function ecm_action_delete_review( array $config ): array {
		if ( ! static::ecm_addon_enabled( 'review-and-ratings' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Review & Ratings addon is not active.' );
		}

		$review_id = absint( $config['review_id'] ?? 0 );

		if ( ! $review_id || ! get_comment( $review_id ) ) {
			return static::ecm_action_error( 'Review not found.' );
		}

		$deleted = wp_delete_comment( $review_id, true );

		if ( ! $deleted ) {
			return static::ecm_action_error( 'The review could not be deleted.' );
		}

		return static::ecm_success(
			[
				'success'   => true,
				'review_id' => $review_id,
			]
		);
	}

	private static function ecm_action_claim_status( array $config, string $status ): array {
		if ( ! static::ecm_addon_enabled( 'claim' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Claim addon is not active.' );
		}

		$claim_id = absint( $config['claim_id'] ?? 0 );

		if ( ! $claim_id ) {
			return static::ecm_action_error( 'A claim ID is required.' );
		}

		try {
			global $wpdb;

			$table = static::ecm_claims_table();

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $claim_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix, never user input.

			if ( ! $exists ) {
				return static::ecm_action_error( 'Claim not found.' );
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					sanitize_key( $status ),
					$claim_id
				)
			);
		} catch ( \Throwable $e ) {
			return static::ecm_action_error( 'The claim could not be updated: ' . $e->getMessage() );
		}//end try

		return static::ecm_success(
			[
				'success'  => true,
				'claim_id' => $claim_id,
				'status'   => $status,
			]
		);
	}

	private static function ecm_action_delete_claim( array $config ): array {
		if ( ! static::ecm_addon_enabled( 'claim' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Claim addon is not active.' );
		}

		$claim_id = absint( $config['claim_id'] ?? 0 );

		if ( ! $claim_id ) {
			return static::ecm_action_error( 'A claim ID is required.' );
		}

		try {
			global $wpdb;

			$table = static::ecm_claims_table();

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $claim_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix, never user input.

			if ( ! $exists ) {
				return static::ecm_action_error( 'Claim not found.' );
			}

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id = %d", $claim_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} catch ( \Throwable $e ) {
			return static::ecm_action_error( 'The claim could not be deleted: ' . $e->getMessage() );
		}//end try

		return static::ecm_success(
			[
				'success'  => true,
				'claim_id' => $claim_id,
			]
		);
	}

	private static function ecm_action_bookmark( array $config, bool $add ): array {
		if ( ! static::ecm_addon_enabled( 'bookmark' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Bookmark addon is not active.' );
		}

		$post_id = absint( $config['post_id'] ?? 0 );
		$user_id = absint( $config['user_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return static::ecm_action_error( 'User not found.' );
		}

		$post_bookmarkers = static::ecm_id_list_from_meta( get_post_meta( $post_id, 'ecm_bookmark_users', true ) );
		$user_bookmarks   = static::ecm_id_list_from_meta( get_user_meta( $user_id, 'ecm_user_bookmarks', true ) );

		if ( $add ) {
			if ( ! in_array( $user_id, $post_bookmarkers, true ) ) {
				$post_bookmarkers[] = $user_id;
			}
			if ( ! in_array( $post_id, $user_bookmarks, true ) ) {
				$user_bookmarks[] = $post_id;
			}
		} else {
			$post_bookmarkers = array_values( array_diff( $post_bookmarkers, [ $user_id ] ) );
			$user_bookmarks   = array_values( array_diff( $user_bookmarks, [ $post_id ] ) );
		}

		update_post_meta( $post_id, 'ecm_bookmark_users', $post_bookmarkers );
		update_user_meta( $user_id, 'ecm_user_bookmarks', $user_bookmarks );

		return static::ecm_success(
			[
				'success'         => true,
				'item_id'         => $post_id,
				'user_id'         => $user_id,
				'bookmarked'      => $add,
				'total_bookmarks' => count( $post_bookmarkers ),
			]
		);
	}

	private static function ecm_action_upvote( array $config, bool $add ): array {
		if ( ! static::ecm_addon_enabled( 'upvote' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Upvote addon is not active.' );
		}

		$post_id = absint( $config['post_id'] ?? 0 );
		$user_id = absint( $config['user_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return static::ecm_action_error( 'User not found.' );
		}

		$upvoters     = static::ecm_id_list_from_meta( get_post_meta( $post_id, '_ecm_upvoted_users', true ) );
		$user_upvotes = static::ecm_id_list_from_meta( get_user_meta( $user_id, '_ecm_upvoted_posts', true ) );
		$count        = max( 0, (int) get_post_meta( $post_id, 'ecm_upvote_count', true ) );

		$has = in_array( $user_id, $upvoters, true );

		if ( $add && ! $has ) {
			$upvoters[]     = $user_id;
			$user_upvotes[] = $post_id;
			++$count;
		} elseif ( ! $add && $has ) {
			$upvoters     = array_values( array_diff( $upvoters, [ $user_id ] ) );
			$user_upvotes = array_values( array_diff( $user_upvotes, [ $post_id ] ) );
			$count        = max( 0, $count - 1 );
		}

		update_post_meta( $post_id, '_ecm_upvoted_users', $upvoters );
		update_post_meta( $post_id, 'ecm_upvote_count', $count );
		update_user_meta( $user_id, '_ecm_upvoted_posts', $user_upvotes );

		return static::ecm_success(
			[
				'success'      => true,
				'item_id'      => $post_id,
				'user_id'      => $user_id,
				'upvoted'      => $add,
				'upvote_count' => $count,
			]
		);
	}

	private static function ecm_action_reaction( array $config, bool $add ): array {
		if ( ! static::ecm_addon_enabled( 'reactions' ) ) {
			return static::ecm_action_error( 'The Easy Content Manager Reactions addon is not active.' );
		}

		$post_id  = absint( $config['post_id'] ?? 0 );
		$user_id  = absint( $config['user_id'] ?? 0 );
		$reaction = sanitize_key( (string) ( $config['reaction'] ?? '' ) );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return static::ecm_action_error( 'User not found.' );
		}

		if ( $add && '' === $reaction ) {
			return static::ecm_action_error( 'A reaction key is required.' );
		}

		$reactions = get_post_meta( $post_id, '_ecm_reactions', true );
		$reactions = is_array( $reactions ) ? $reactions : [];

		$user_reactions = get_user_meta( $user_id, '_ecm_user_reactions', true );
		$user_reactions = is_array( $user_reactions ) ? $user_reactions : [];

		// A user holds one reaction per post — drop any existing one first, the
		// same way ECM's handler does.
		$previous = null;

		foreach ( $reactions as $type => $users ) {
			$users = static::ecm_id_list_from_meta( $users );

			if ( in_array( $user_id, $users, true ) ) {
				$previous = (string) $type;
				$remaining = array_values( array_diff( $users, [ $user_id ] ) );

				if ( empty( $remaining ) ) {
					unset( $reactions[ $type ] );
				} else {
					$reactions[ $type ] = $remaining;
				}
				break;
			}
		}

		if ( $add ) {
			$reactions[ $reaction ]          = static::ecm_id_list_from_meta( $reactions[ $reaction ] ?? [] );
			$reactions[ $reaction ][]        = $user_id;
			$reactions[ $reaction ]          = array_values( array_unique( $reactions[ $reaction ] ) );
			$user_reactions[ $post_id ]      = $reaction;
		} else {
			unset( $user_reactions[ $post_id ] );
		}

		update_post_meta( $post_id, '_ecm_reactions', $reactions );
		update_user_meta( $user_id, '_ecm_user_reactions', $user_reactions );

		return static::ecm_success(
			[
				'success'           => true,
				'item_id'           => $post_id,
				'user_id'           => $user_id,
				'reaction'          => $add ? $reaction : '',
				'previous_reaction' => $previous,
				'reactions'         => $reactions,
			]
		);
	}

	/**
	 * @return array<int,int>
	 */
	protected static function ecm_id_list_from_meta( $value ): array {
		$value = static::ecm_maybe_unserialize( $value );

		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}
}
