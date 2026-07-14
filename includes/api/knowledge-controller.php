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
	 * List entries, optionally filtered by business_key and a search term.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$business = sanitize_text_field( (string) $request->get_param( 'business_key' ) );
		$search   = trim( (string) $request->get_param( 'search' ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table  = Knowledge::getTable();
		$where  = '1=1';
		$params = [];

		if ( '' !== $business ) {
			$where   .= ' AND business_key = %s';
			$params[] = $business;
		}
		if ( '' !== $search ) {
			$where   .= ' AND (title LIKE %s OR content LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		// Total count.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql        = "SELECT id, business_key, title, content, source, ref_id, updated_at FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$list_params     = array_merge( $params, [ $per_page, $offset ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$keys = $wpdb->get_col( "SELECT business_key, COUNT(*) AS c FROM {$table} GROUP BY business_key ORDER BY business_key ASC" );

		return rest_ensure_response( [ 'businesses' => array_values( array_filter( (array) $keys ) ) ] );
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
			'enabled'  => ! empty( $cfg['enabled'] ),
			'provider' => (string) $cfg['provider'],
			'model'    => (string) $cfg['model'],
			'has_key'  => '' !== (string) $cfg['api_key'],
		] );
	}

	/** Save semantic-search config. The key is encrypted at rest; a blank api_key keeps the stored one. */
	public function save_embeddings_config( $request ) {
		KnowledgeEmbeddings::save( [
			'enabled'  => (bool) $request->get_param( 'enabled' ),
			'provider' => (string) ( $request->get_param( 'provider' ) ?: 'openai' ),
			'model'    => (string) $request->get_param( 'model' ),
			'api_key'  => (string) $request->get_param( 'api_key' ),
		] );

		// Reflect the effective (decrypted) state back, without exposing the key.
		$cfg = KnowledgeEmbeddings::config();
		return rest_ensure_response( [
			'success'  => true,
			'enabled'  => ! empty( $cfg['enabled'] ),
			'provider' => (string) $cfg['provider'],
			'model'    => (string) $cfg['model'],
			'has_key'  => '' !== (string) $cfg['api_key'],
		] );
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
