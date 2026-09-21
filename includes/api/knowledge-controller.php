<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Knowledge;
use Zaplane\Services\KnowledgeEmbeddings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API for the Business Knowledge admin page — list/search/create/update/
 * delete per-business knowledge entries, plus the list of known business keys.
 */
class KnowledgeController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/knowledge', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/businesses', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_businesses' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/sources', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_sources' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/post-types', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_post_types' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/sync-content', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_content' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/embeddings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_embeddings_config' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_embeddings_config' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/embeddings/connections', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_embeddings_connections' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/embed-backfill', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'embed_backfill' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/sync-storeengine', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_storeengine' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/bulk', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_bulk' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/knowledge/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List entries, optionally filtered by business_key, source (e.g. "faq" to
	 * see just what the FAQ Builder produced for a business), and a search term.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$business = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		$search   = trim( (string) $request->get_param( 'search' ) );
		$source   = sanitize_key( (string) $request->get_param( 'source' ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table = Knowledge::getTable();
		$like  = '%' . $wpdb->esc_like( $search ) . '%';

		// Each filter is optional. Rather than assembling the WHERE clause, every
		// filter sits in one literal statement as "(%s = '' OR column = %s)": an
		// empty value switches that filter off. Nothing about the query is built
		// from the request — $business, $search and $source only arrive as values.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE (%s = '' OR business_key = %s) AND (%s = '' OR title LIKE %s OR content LIKE %s) AND (%s = '' OR source = %s)",
				$table,
				$business,
				$business,
				$search,
				$like,
				$like,
				$source,
				$source
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Literal statement, prepared here.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, business_key, title, content, source, ref_id, updated_at FROM %i WHERE (%s = '' OR business_key = %s) AND (%s = '' OR title LIKE %s OR content LIKE %s) AND (%s = '' OR source = %s) ORDER BY id DESC LIMIT %d OFFSET %d",
				$table,
				$business,
				$business,
				$search,
				$like,
				$like,
				$source,
				$source,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return rest_ensure_response( [
			'items'    => is_array( $rows ) ? $rows : [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		] );
	}

	/** Distinct business keys (for the page's business selector). */
	public function get_businesses() {
		global $wpdb;
		$table = Knowledge::getTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keys = $wpdb->get_col( $wpdb->prepare( 'SELECT business_key, COUNT(*) AS c FROM %i GROUP BY business_key ORDER BY business_key ASC', $table ) );

		return rest_ensure_response( [ 'businesses' => array_values( array_filter( (array) $keys ) ) ] );
	}

	/**
	 * Distinct `source` values actually present in the table — "faq" and
	 * "manual" are always offered even with zero rows yet (so the filter is
	 * discoverable before you've built anything), everything else (synced post
	 * types) only appears once it has entries.
	 */
	public function get_sources() {
		global $wpdb;
		$table = Knowledge::getTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT source FROM %i ORDER BY source ASC', $table ) );

		$sources = array_values( array_unique( array_filter( array_merge( [ 'faq', 'manual' ], $found ) ) ) );
		sort( $sources );

		return rest_ensure_response( [ 'sources' => $sources ] );
	}

	/**
	 * Sync options for the admin drawer: the public post types (with their entry
	 * counts and the taxonomies registered to each), plus the selectable post
	 * statuses. Bundled into one response so the drawer needs a single fetch.
	 */
	public function get_post_types() {
		$types = get_post_types( [ 'public' => true ], 'objects' );
		$out   = [];
		foreach ( $types as $slug => $obj ) {
			if ( 'attachment' === $slug ) {
				continue;
			}
			$counts = wp_count_posts( $slug );

			$taxonomies = [];
			foreach ( get_object_taxonomies( $slug, 'objects' ) as $tax_slug => $tax_obj ) {
				// Include taxonomies with meaningful terms. `public` catches
				// plugin-managed ones (e.g. StoreEngine product categories, which
				// set show_ui=false because they render their own admin UI);
				// show_ui catches standard editor taxonomies.
				if ( empty( $tax_obj->public ) && empty( $tax_obj->show_ui ) ) {
					continue;
				}
				$taxonomies[] = [
					'slug'  => $tax_slug,
					'label' => $tax_obj->labels->name ?? $tax_slug,
				];
			}

			$out[] = [
				'slug'       => $slug,
				'label'      => $obj->labels->name ?? $slug,
				'count'      => (int) ( $counts->publish ?? 0 ),
				'taxonomies' => $taxonomies,
			];
		}

		$statuses = [];
		foreach ( get_post_stati( [], 'objects' ) as $status_value => $status_obj ) {
			// Skip internal statuses (auto-draft, inherit, trash).
			if ( ! empty( $status_obj->internal ) ) {
				continue;
			}
			$statuses[] = [
				'value' => $status_value,
				'label' => $status_obj->label ?? $status_value,
			];
		}

		return rest_ensure_response( [
			'post_types' => $out,
			'statuses'   => $statuses,
		] );
	}

	/** Read the semantic-search config (never returns the raw key). */
	public function get_embeddings_config() {
		$cfg = KnowledgeEmbeddings::config();
		return rest_ensure_response( [
			'enabled'       => ! empty( $cfg['enabled'] ),
			'connection_id' => (int) $cfg['connection_id'],
			'provider'      => (string) $cfg['provider'],
			'model'         => (string) $cfg['model'],
			'has_key'       => '' !== (string) $cfg['api_key'],
		] );
	}

	/** Save semantic-search config: which AI Connection to embed with, and an optional model override. */
	public function save_embeddings_config( $request ) {
		$connection_id = (int) $request->get_param( 'connection_id' );

		// Only a connection the picker offers this user can be newly linked. The
		// one already saved stays accepted, so another admin can still toggle
		// the setting without owning that connection.
		$current = (int) KnowledgeEmbeddings::config()['connection_id'];
		if ( $connection_id > 0 && $connection_id !== $current && ! $this->is_embeddings_connection( $connection_id ) ) {
			return new WP_Error( 'zaplane_invalid_connection', __( 'Pick one of your OpenAI, Gemini, or OpenAI-compatible AI connections.', 'zaplane' ), [ 'status' => 400 ] );
		}

		KnowledgeEmbeddings::save( [
			'enabled'       => (bool) $request->get_param( 'enabled' ),
			'connection_id' => $connection_id,
			'model'         => (string) $request->get_param( 'model' ),
		] );

		$cfg = KnowledgeEmbeddings::config();
		return rest_ensure_response( [
			'success'       => true,
			'enabled'       => ! empty( $cfg['enabled'] ),
			'connection_id' => (int) $cfg['connection_id'],
			'provider'      => (string) $cfg['provider'],
			'model'         => (string) $cfg['model'],
			'has_key'       => '' !== (string) $cfg['api_key'],
		] );
	}

	/**
	 * AI Connections usable for embeddings: app "ai", and a provider that
	 * actually has an embeddings endpoint (Anthropic and WordPress Core AI
	 * don't, so connections using them are left out).
	 */
	public function get_embeddings_connections() {
		$connections = \Zaplane\Models\Connection::where( 'user_id', get_current_user_id() )
			->where( 'app', 'ai' )
			->orderBy( 'name', 'asc' )
			->get();

		$out = [];
		foreach ( $connections as $connection ) {
			$creds    = $connection->getCredentials();
			$provider = strtolower( (string) ( $creds['provider'] ?? '' ) );
			if ( ! in_array( $provider, KnowledgeEmbeddings::SUPPORTED_PROVIDERS, true ) ) {
				continue;
			}
			$out[] = [
				'id'       => (int) $connection->id,
				'name'     => (string) $connection->name,
				'provider' => $provider,
			];
		}

		return rest_ensure_response( [ 'connections' => $out ] );
	}

	/** Whether $id is an AI connection of the current user whose provider can embed. */
	private function is_embeddings_connection( int $id ): bool {
		$connection = \Zaplane\Models\Connection::find( $id );
		if ( ! $connection || 'ai' !== $connection->app || (int) $connection->user_id !== get_current_user_id() ) {
			return false;
		}
		$provider = strtolower( (string) ( $connection->getCredentials()['provider'] ?? '' ) );
		return in_array( $provider, KnowledgeEmbeddings::SUPPORTED_PROVIDERS, true );
	}

	/** Embed entries missing a vector (admin button; loop until remaining = 0). */
	public function embed_backfill( $request ) {
		$business = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		if ( '' === $business ) {
			return new WP_Error( 'invalid', 'business_key is required.', [ 'status' => 400 ] );
		}
		if ( ! class_exists( '\Zaplane\Integrations\Knowledge' ) ) {
			return new WP_Error( 'unavailable', 'Knowledge integration unavailable.', [ 'status' => 500 ] );
		}

		$result = \Zaplane\Integrations\Knowledge::action_embed_backfill(
			$business,
			[ 'limit' => (int) $request->get_param( 'limit' ) ],
			[]
		);
		$data = $result['data'] ?? [];
		if ( empty( $data['success'] ) ) {
			return new WP_Error( 'backfill_failed', $data['error'] ?? 'Backfill failed.', [ 'status' => 422 ] );
		}

		return rest_ensure_response( [
			'success'   => true,
			'embedded'  => (int) ( $data['embedded'] ?? 0 ),
			'failed'    => (int) ( $data['failed'] ?? 0 ),
			'remaining' => (int) ( $data['remaining'] ?? 0 ),
		] );
	}

	/** Sync any post type into a business's knowledge (admin drawer). */
	public function sync_content( $request ) {
		$business = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		if ( '' === $business ) {
			return new WP_Error( 'invalid', 'business_key is required.', [ 'status' => 400 ] );
		}

		if ( ! class_exists( '\Zaplane\Integrations\Knowledge' ) ) {
			return new WP_Error( 'unavailable', 'Knowledge integration unavailable.', [ 'status' => 500 ] );
		}

		$config = [
			'post_type'       => (string) $request->get_param( 'post_type' ),
			'post_status'     => (string) ( $request->get_param( 'post_status' ) ?: 'publish' ),
			'include_excerpt' => 'no' === $request->get_param( 'include_excerpt' ) ? 'no' : 'yes',
			'include_content' => 'no' === $request->get_param( 'include_content' ) ? 'no' : 'yes',
			'meta_keys'       => (string) $request->get_param( 'meta_keys' ),
			'taxonomies'      => (string) $request->get_param( 'taxonomies' ),
			'limit'           => (int) $request->get_param( 'limit' ),
			'prune'           => 'no' === $request->get_param( 'prune' ) ? 'no' : 'yes',
		];

		$result = \Zaplane\Integrations\Knowledge::action_sync_content( $business, $config, [] );

		$data = $result['data'] ?? [];
		if ( empty( $data['success'] ) ) {
			return new WP_Error( 'sync_failed', $data['error'] ?? 'Sync failed.', [ 'status' => 422 ] );
		}

		return rest_ensure_response( [
			'success' => true,
			'synced'  => (int) ( $data['synced'] ?? 0 ),
			'pruned'  => (int) ( $data['pruned'] ?? 0 ),
		] );
	}

	/** Sync StoreEngine products into a business's knowledge (admin button). */
	public function sync_storeengine( $request ) {
		$business = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		if ( '' === $business ) {
			return new WP_Error( 'invalid', 'business_key is required.', [ 'status' => 400 ] );
		}

		if ( ! class_exists( '\Zaplane\Integrations\Knowledge' ) ) {
			return new WP_Error( 'unavailable', 'Knowledge integration unavailable.', [ 'status' => 500 ] );
		}

		$result = \Zaplane\Integrations\Knowledge::action_sync_storeengine(
			$business,
			[ 'limit' => (int) $request->get_param( 'limit' ) ],
			[]
		);

		$data = $result['data'] ?? [];
		if ( empty( $data['success'] ) ) {
			return new WP_Error( 'sync_failed', $data['error'] ?? 'Sync failed.', [ 'status' => 422 ] );
		}

		return rest_ensure_response( [
			'success' => true,
			'synced'  => (int) ( $data['synced'] ?? 0 ),
			'pruned'  => (int) ( $data['pruned'] ?? 0 ),
		] );
	}

	/**
	 * Bulk-create entries (used by the FAQ builder — one row per Q&A).
	 * Body: { business_key, source?, entries: [ { title, content }, ... ] }
	 */
	public function create_bulk( $request ) {
		$business = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		$source   = sanitize_text_field( (string) $request->get_param( 'source' ) ) ?: 'manual';
		$entries  = $request->get_param( 'entries' );

		if ( '' === $business || ! is_array( $entries ) || empty( $entries ) ) {
			return new WP_Error( 'invalid', 'business_key and a non-empty entries array are required.', [ 'status' => 400 ] );
		}

		$created = 0;
		foreach ( $entries as $entry ) {
			$content = isset( $entry['content'] ) ? (string) $entry['content'] : '';
			if ( '' === trim( $content ) ) {
				continue;
			}
			Knowledge::create( [
				'business_key' => $business,
				'title'        => sanitize_text_field( (string) ( $entry['title'] ?? '' ) ),
				'content'      => $content,
				'source'       => $source,
				'ref_id'       => null,
				'updated_at'   => current_time( 'mysql' ),
			] );
			++$created;
		}

		// FAQ rows (and any other bulk-created source) start with no embedding —
		// queue a background batch so semantic search picks them up without a
		// manual "Backfill now" click.
		if ( $created > 0 && KnowledgeEmbeddings::enabled() ) {
			\Zaplane\Modules\KnowledgeAutomation\KnowledgeAutomationModule::queue_embedding( $business );
		}

		return rest_ensure_response( [
			'success' => true,
			'created' => $created
		] );
	}

	public function create_item( $request ) {
		$business = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		$content  = (string) $request->get_param( 'content' );

		if ( '' === $business || '' === trim( $content ) ) {
			return new WP_Error( 'invalid', 'business_key and content are required.', [ 'status' => 400 ] );
		}

		$record = Knowledge::create( [
			'business_key' => $business,
			'title'        => sanitize_text_field( (string) $request->get_param( 'title' ) ),
			'content'      => $content,
			'source'       => 'manual',
			'ref_id'       => null,
			'updated_at'   => current_time( 'mysql' ),
		] );

		return rest_ensure_response( [
			'id' => $record->id ?? 0,
			'success' => true
		] );
	}

	public function update_item( $request ) {
		$entry = Knowledge::find( (int) $request['id'] );
		if ( ! $entry ) {
			return new WP_Error( 'not_found', 'Entry not found.', [ 'status' => 404 ] );
		}

		if ( null !== $request->get_param( 'business_key' ) ) {
			$entry->business_key = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		}
		if ( null !== $request->get_param( 'title' ) ) {
			$entry->title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'content' ) ) {
			$entry->content = (string) $request->get_param( 'content' );
		}
		$entry->updated_at = current_time( 'mysql' );
		$entry->save();

		return rest_ensure_response( [
			'id' => $entry->id,
			'success' => true
		] );
	}

	public function delete_item( $request ) {
		$entry = Knowledge::find( (int) $request['id'] );
		if ( ! $entry ) {
			return new WP_Error( 'not_found', 'Entry not found.', [ 'status' => 404 ] );
		}
		$entry->delete();

		return rest_ensure_response( [ 'success' => true ] );
	}
}
