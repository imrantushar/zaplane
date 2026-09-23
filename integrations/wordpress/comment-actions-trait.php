<?php
namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Traits\ActionResponseTrait;

trait CommentActionsTrait {

	protected static function action_create_comment( array $config ): array {
		$comment_id = wp_insert_comment([
			'comment_post_ID'      => $config['post_id'],
			'comment_author'       => $config['author_name'],
			'comment_author_email' => $config['author_email'],
			'comment_content'      => $config['content'],
			'comment_approved'     => 1,
		]);

		if ( is_wp_error( $comment_id ) ) {
			return static::error( $comment_id->get_error_message() );
		}

		return static::success( [ 'comment_id' => $comment_id ] );
	}

	protected static function action_reply_comment( array $config ): array {
		$parent = get_comment( $config['parent_id'] );

		if ( ! $parent ) {
			return static::error( "Parent comment ID {$config['parent_id']} not found" );
		}

		$user = ! empty( $config['user_id'] ) ? get_userdata( (int) $config['user_id'] ) : false;

		$comment_id = wp_insert_comment([
			'comment_post_ID'      => $parent->comment_post_ID,
			'comment_parent'       => $config['parent_id'],
			'comment_author'       => $user ? $user->display_name : $config['author_name'],
			'comment_author_email' => $user ? $user->user_email : $config['author_email'],
			'comment_author_url'   => $user ? $user->user_url : '',
			'user_id'              => $user ? (int) $user->ID : 0,
			'comment_content'      => $config['content'],
			'comment_approved'     => 1,
		]);

		// Replying approves the comment replied to, as WordPress's own
		// "Approve and Reply" does.
		if ( $comment_id && '0' === (string) $parent->comment_approved ) {
			wp_set_comment_status( (int) $parent->comment_ID, 'approve' );
		}

		if ( is_wp_error( $comment_id ) ) {
			return static::error( $comment_id->get_error_message() );
		}

		return static::success([
			'comment_id' => $comment_id,
			'parent_id'  => $config['parent_id']
		]);
	}

	protected static function action_approve_comment( array $config ): array {
		$comment_id = $config['comment_id'] ?? 0;
		$result = wp_set_comment_status( $comment_id, 'approve' );
		if ( is_wp_error( $result ) || ! $result ) {
			return static::error( "Failed to approve comment ID {$config['comment_id']}" );
		}
		return static::success([
			'comment_id' => $comment_id,
			'status'     => 'approved',
		]);
	}

	protected static function action_unapproved_comment( array $config ): array {
		$comment_id = $config['comment_id'] ?? 0;
		$result = wp_set_comment_status( $comment_id, 'hold' );
		if ( is_wp_error( $result ) || ! $result ) {
			return static::error( "Failed to unapproved comment ID {$config['comment_id']}" );
		}
		return static::success([
			'comment_id' => $comment_id,
			'status'     => 'hold',
		]);
	}

	protected static function action_mark_comment_spam( array $config ): array {
		$comment_id = $config['comment_id'] ?? 0;
		$result = wp_spam_comment( $comment_id );
		if ( is_wp_error( $result ) || ! $result ) {
			return static::error( "Failed to mark comment ID {$comment_id}" );
		}
		return static::success([
			'comment_id' => $comment_id,
			'status'     => 'spam',
		]);
	}

	protected static function action_unmark_comment_spam( array $config ): array {
		$comment_id = $config['comment_id'] ?? 0;
		$result = wp_unspam_comment( $comment_id );
		if ( is_wp_error( $result ) || ! $result ) {
			return static::error( "Failed to unmark comment ID {$comment_id}" );
		}
		return static::success([
			'comment_id' => $comment_id,
			'status'     => 'unspam',
		]);
	}

	protected static function action_delete_trash_comment( array $config ): array {
		$result = wp_delete_comment( $config['comment_id'], true );

		if ( ! $result ) {
			return static::error( "Failed to delete trash comment ID {$config['comment_id']}" );
		}

		return static::success( [ 'comment_id' => $config['comment_id'] ] );
	}

	protected static function action_delete_comment( array $config ): array {
		$result = wp_delete_comment( $config['comment_id'], true );

		if ( ! $result ) {
			return static::error( "Failed to delete comment ID {$config['comment_id']}" );
		}

		return static::success( [ 'comment_id' => $config['comment_id'] ] );
	}

	protected static function action_trash_comment( array $config ): array {
		$result = wp_trash_comment( $config['comment_id'] );

		if ( ! $result ) {
			return static::error( "Failed to trash comment ID {$config['comment_id']}" );
		}

		return static::success( [ 'comment_id' => $config['comment_id'] ] );
	}

	protected static function action_restore_comment( array $config ): array {
		$result = wp_untrash_comment( $config['comment_id'] );

		if ( ! $result ) {
			return static::error( "Failed to restore comment ID {$config['comment_id']}" );
		}

		return static::success( [ 'comment_id' => $config['comment_id'] ] );
	}
	protected static function action_untrash_comment( array $config ): array {
		$result = wp_untrash_comment( $config['comment_id'] );

		if ( ! $result ) {
			return static::error( "Failed to untrash comment ID {$config['comment_id']}" );
		}

		return static::success( [ 'comment_id' => $config['comment_id'] ] );
	}

	protected static function action_get_post_comments_single( array $config ): array {
		$post_id = $config['post_id'] ?? 0;

		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}

		$comments = get_comments( [ 'post_id' => $post_id ] );

		return static::success( [
			'post_id' => $post_id,
			'comments' => $comments,
			'count' => count( $comments )
		] );
	}

	protected static function action_get_user_comments_email( array $config ): array {
		$user_email = $config['user_email'] ?? '';

		if ( ! $user_email ) {
			return static::error( 'User email is required' );
		}

		$comments = get_comments( [ 'author_email' => $user_email ] );

		return static::success( [
			'user_email' => $user_email,
			'comments' => $comments,
			'count' => count( $comments )
		] );
	}

	protected static function action_get_post_comments_all( array $config ): array {
		$args = [
			'number' => ! empty( $config['limit'] ) ? (int) $config['limit'] : 20,
			'status' => (string) ( $config['status'] ?? 'approve' ),
		];
		if ( ! empty( $config['post_type'] ) ) {
			$args['post_type'] = (string) $config['post_type'];
		}
		$comments = get_comments( $args );

		return static::success( [
			'comments' => $comments,
			'count'    => count( $comments ),
		] );
	}

	protected static function action_get_user_comments( array $config ): array {
		$user_id = (int) ( $config['user_id'] ?? 0 );
		if ( ! $user_id ) {
			return static::error( 'User ID is required' );
		}
		$comments = get_comments( [ 'user_id' => $user_id ] );

		return static::success( [
			'user_id'  => $user_id,
			'comments' => $comments,
			'count'    => count( $comments ),
		] );
	}

	protected static function action_get_comment_metadata_all( array $config ): array {
		$comment_id = (int) ( $config['comment_id'] ?? 0 );
		if ( ! $comment_id ) {
			return static::error( 'Comment ID is required' );
		}
		return static::success( [
			'comment_id' => $comment_id,
			'meta'       => get_comment_meta( $comment_id ),
		] );
	}

	protected static function action_get_comment_metadata_single( array $config ): array {
		$comment_id = (int) ( $config['comment_id'] ?? 0 );
		$meta_key   = (string) ( $config['meta_key'] ?? '' );
		if ( ! $comment_id || '' === $meta_key ) {
			return static::error( 'Comment ID and meta key are required' );
		}
		return static::success( [
			'comment_id' => $comment_id,
			'meta_key'   => $meta_key,
			'value'      => get_comment_meta( $comment_id, $meta_key, true ),
		] );
	}

	protected static function action_set_comment_status( array $config ): array {
		$comment_id = (int) ( $config['comment_id'] ?? 0 );
		if ( ! $comment_id ) {
			return static::error( 'Comment ID is required' );
		}
		// The select stores WordPress's own values: 1 approved, 0 pending.
		$map    = [ '1' => 'approve', 'approve' => 'approve', '0' => 'hold', 'hold' => 'hold', 'spam' => 'spam', 'trash' => 'trash' ];
		$status = $map[ (string) ( $config['status'] ?? '' ) ] ?? '';
		if ( '' === $status ) {
			return static::error( 'A status of approved, pending, spam or trash is required' );
		}

		$result = wp_set_comment_status( $comment_id, $status, true );
		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}
		if ( ! $result ) {
			return static::error( "Failed to set the status of comment ID {$comment_id}" );
		}

		return static::success( [
			'comment_id' => $comment_id,
			'status'     => $status,
		] );
	}

	protected static function action_update_comment_count( array $config ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return static::error( 'Post ID is required' );
		}
		wp_update_comment_count( $post_id );

		return static::success( [
			'post_id' => $post_id,
			'count'   => (int) get_comments_number( $post_id ),
		] );
	}
}
