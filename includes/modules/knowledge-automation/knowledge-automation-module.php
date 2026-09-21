<?php

namespace Zaplane\Modules\KnowledgeAutomation;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;
use Zaplane\Integrations\Knowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps Business Knowledge current without manual clicks:
 *
 * - `zaplane_knowledge_embed_pending` embeds newly-synced/FAQ'd rows in the
 *   background (queued by Knowledge::action_sync_content() and
 *   KnowledgeController::create_bulk()), batching itself until nothing is left.
 * - `save_post` re-syncs a single post shortly after it's saved, but only for
 *   a (business_key, post_type) pair that was already synced at least once
 *   manually — this never starts syncing content nobody opted into.
 * - Trashing/deleting a synced post removes its knowledge entry so stale
 *   product/content info doesn't linger.
 */
class KnowledgeAutomationModule implements ModuleInterface {

	protected static ?self $instance = null;
	protected Container $container;

	/** Batch size per embedding run; re-enqueues itself while rows remain. */
	private const EMBED_BATCH = 100;

	/** Debounce delay before an edited post is re-synced (seconds). */
	private const AUTOSYNC_DELAY = 30;

	public static function init( Container $container ): self {
		if ( ! self::$instance ) {
			self::$instance = new self( $container );
		}
		return self::$instance;
	}

	private function __construct( Container $container ) {
		$this->container = $container;
	}

	public function register_hooks(): void {
		add_action( 'zaplane_knowledge_embed_pending', [ self::class, 'handle_embed_pending' ], 10, 1 );
		add_action( 'zaplane_knowledge_autosync_post', [ self::class, 'handle_autosync_post' ], 10, 3 );

		add_action( 'save_post', [ self::class, 'handle_post_saved' ], 20, 3 );
		add_action( 'wp_trash_post', [ self::class, 'handle_post_removed' ], 10, 1 );
		add_action( 'before_delete_post', [ self::class, 'handle_post_removed' ], 10, 1 );
	}

	/**
	 * Embed one batch of a business's un-embedded rows; re-enqueue itself if
	 * more remain instead of doing an unbounded loop in one request.
	 *
	 * Action Scheduler (like WP's do_action_ref_array) calls this via
	 * call_user_func_array() with the associative args array we enqueue —
	 * PHP 8 matches those string keys to named parameters, so this parameter
	 * name must match the 'business_key' key used at every enqueue call site.
	 */
	public static function handle_embed_pending( string $business_key ): void {
		if ( '' === $business_key ) {
			return;
		}

		$result = Knowledge::action_embed_backfill( $business_key, [ 'limit' => self::EMBED_BATCH ], [] );
		$data   = $result['data'] ?? [];

		if ( ! empty( $data['success'] ) && (int) ( $data['remaining'] ?? 0 ) > 0 && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'zaplane_knowledge_embed_pending', [ 'business_key' => $business_key ], 'zaplane' );
		}
	}

	/**
	 * A post was saved — if its post type has a remembered sync profile
	 * (meaning it was already synced into Business Knowledge at least once),
	 * schedule a debounced re-sync of just that post.
	 */
	public static function handle_post_saved( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			// Only auto-resync content that's actually live; an unpublished
			// draft isn't visible to a customer-facing agent anyway, and a
			// status change away from publish is handled by handle_post_removed
			// only on trash/delete — a manual unsync is on the user, same as
			// today's manual-sync workflow.
			return;
		}

		$profiles = Knowledge::sync_profiles_for_post_type( $post->post_type );
		if ( empty( $profiles ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		foreach ( $profiles as $profile ) {
			as_schedule_single_action(
				time() + self::AUTOSYNC_DELAY,
				'zaplane_knowledge_autosync_post',
				[
					'business_key' => (string) $profile['business_key'],
					'post_type'    => (string) $profile['post_type'],
					'post_id'      => $post_id,
				],
				'zaplane'
			);
		}
	}

	/**
	 * Re-sync exactly one post using its remembered profile settings, then
	 * queue embedding for whatever that just produced/changed. See the note on
	 * handle_embed_pending() — parameter names must match the enqueued keys.
	 */
	public static function handle_autosync_post( string $business_key, string $post_type, int $post_id ): void {
		if ( '' === $business_key || '' === $post_type || $post_id <= 0 ) {
			return;
		}

		$profiles = Knowledge::sync_profiles_for_post_type( $post_type );
		$profile  = null;
		foreach ( $profiles as $p ) {
			if ( (string) ( $p['business_key'] ?? '' ) === $business_key ) {
				$profile = $p;
				break;
			}
		}
		if ( null === $profile ) {
			return; // profile removed since the save (e.g. business cleared) — nothing to do
		}

		$profile['post_id'] = $post_id;
		Knowledge::action_sync_content( $business_key, $profile, [] );
	}

	/**
	 * A synced post was trashed or hard-deleted — drop its knowledge entry
	 * (across every post type; forget_synced_post() is a no-op for unrelated
	 * source/ref combinations) rather than leaving stale info behind.
	 */
	public static function handle_post_removed( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		Knowledge::forget_synced_post( $post_id, $post->post_type );
	}
}
