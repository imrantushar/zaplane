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
 * - Unpublishing, trashing or deleting a synced post removes its knowledge
 *   entry so stale product/content info doesn't linger.
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
	 * Queue a background embedding run for a business, unless one is already
	 * waiting — a bulk edit or several syncs in a row then share one run.
	 */
	public static function queue_embedding( string $business_key ): void {
		if ( '' === $business_key || ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$args = [ 'business_key' => $business_key ];
		if ( self::is_pending( 'zaplane_knowledge_embed_pending', $args ) ) {
			return;
		}
		as_enqueue_async_action( 'zaplane_knowledge_embed_pending', $args, 'zaplane' );
	}

	/**
	 * Embed one batch of a business's un-embedded rows; re-enqueue itself if
	 * more remain instead of doing an unbounded loop in one request.
	 *
	 * Action Scheduler passes the enqueued args positionally (array_values), so
	 * the parameter order must match the order of the keys at the enqueue site.
	 */
	public static function handle_embed_pending( string $business_key ): void {
		if ( '' === $business_key ) {
			return;
		}

		$result = Knowledge::action_embed_backfill( $business_key, [ 'limit' => self::EMBED_BATCH ], [] );
		$data   = $result['data'] ?? [];

		if ( ! empty( $data['success'] ) && (int) ( $data['remaining'] ?? 0 ) > 0 ) {
			self::queue_embedding( $business_key );
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

		$profiles = Knowledge::sync_profiles_for_post_type( $post->post_type );
		if ( empty( $profiles ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		foreach ( $profiles as $profile ) {
			$business_key = (string) $profile['business_key'];

			// Moved out of the status this business syncs (e.g. published → draft):
			// a customer-facing agent must stop quoting it right away.
			if ( ! Knowledge::profile_accepts_status( $profile, $post->post_status ) ) {
				Knowledge::forget_synced_post( $post_id, $post->post_type, $business_key );
				continue;
			}

			$args = [
				'business_key' => $business_key,
				'post_type'    => (string) $profile['post_type'],
				'post_id'      => $post_id,
			];

			// Debounce: saves while a re-sync is still waiting fold into it.
			if ( self::is_pending( 'zaplane_knowledge_autosync_post', $args ) ) {
				continue;
			}

			as_schedule_single_action( time() + self::AUTOSYNC_DELAY, 'zaplane_knowledge_autosync_post', $args, 'zaplane' );
		}//end foreach
	}

	/**
	 * Re-sync exactly one post using its remembered profile settings, then
	 * queue embedding for whatever that just produced/changed. See the note on
	 * handle_embed_pending() — parameter order must match the enqueued keys.
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

		// The post may have changed status since the save that queued this.
		$post = get_post( $post_id );
		if ( ! $post || ! Knowledge::profile_accepts_status( $profile, $post->post_status ) ) {
			Knowledge::forget_synced_post( $post_id, $post_type, $business_key );
			return;
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

	/** Whether an action with exactly these args is still waiting to run (a running one doesn't count). */
	private static function is_pending( string $hook, array $args ): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( '\ActionScheduler_Store' ) ) {
			return false;
		}
		$ids = as_get_scheduled_actions(
			[
				'hook'     => $hook,
				'args'     => $args,
				'group'    => 'zaplane',
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
			],
			'ids'
		);
		return ! empty( $ids );
	}
}
