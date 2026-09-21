<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Models\Knowledge as KnowledgeModel;
use Zaplane\Services\KnowledgeEmbeddings;

class Knowledge extends IntegrationBase {

	private const DEFAULT_LIMIT = 5;
	private const MAX_SCAN       = 5000;

	public static function get_slug(): string {
		return 'knowledge';
	}

	public static function get_name(): string {
		return 'Business Knowledge';
	}

	public static function get_icon(): string {
		return 'knowledge.svg';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_actions(): array {
		return [
			'retrieve'         => [ 'label' => 'Retrieve Business Knowledge' ],
			'add_entry'        => [ 'label' => 'Add / Update Knowledge Entry' ],
			'sync_content'     => [ 'label' => 'Sync WordPress Content' ],
			'sync_storeengine' => [ 'label' => 'Sync StoreEngine Products' ],
			'embed_backfill'   => [ 'label' => 'Backfill Semantic Embeddings' ],
			'clear'            => [ 'label' => 'Clear Business Knowledge' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$business_field = [
			'key'         => 'business_key',
			'label'       => 'Business Key',
			'type'        => 'expression',
			'required'    => true,
			'placeholder' => 'business_a',
			'help'        => 'A label that groups all knowledge for one business (e.g. "business_a" or "acme_store"). Not a license or API key — just a name you choose. Use the exact same value across Add, Sync, Retrieve and Clear so they read/write the same knowledge.',
		];

		switch ( $action ) {
			case 'retrieve':
				return [
					$business_field,
					[
						'key'         => 'query',
						'label'       => 'Query',
						'type'        => 'expression',
						'required'    => true,
						'placeholder' => '{{1.text}}',
						'help'        => 'The customer message — used to find the most relevant entries.',
					],
					[
						'key'      => 'limit',
						'label'    => 'Max Results',
						'type'     => 'number',
						'required' => false,
						'default'  => self::DEFAULT_LIMIT,
					],
				];

			case 'add_entry':
				return [
					$business_field,
					[
						'key'         => 'title',
						'label'       => 'Title',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'Returns policy',
					],
					[
						'key'      => 'content',
						'label'    => 'Content',
						'type'     => 'textarea',
						'required' => true,
						'help'     => 'A product (name + price + details) or an FAQ/policy entry.',
					],
					[
						'key'      => 'ref_id',
						'label'    => 'Reference ID (optional)',
						'type'     => 'expression',
						'required' => false,
						'help'     => 'If set, re-adding with the same ref updates the entry instead of duplicating.',
					],
				];

			case 'sync_content':
				return [
					$business_field,
					[
						'key'         => 'post_type',
						'label'       => 'Post Type',
						'type'        => 'expression',
						'required'    => true,
						'placeholder' => 'post',
						'help'        => 'The slug of the content to sync — e.g. post, page, product, docs. Each item becomes one knowledge entry (title + body).',
					],
					[
						'key'         => 'post_status',
						'label'       => 'Status',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'publish',
						'help'        => 'Which post status to include. Defaults to publish.',
					],
					[
						'key'      => 'include_excerpt',
						'label'    => 'Include excerpt',
						'type'     => 'select',
						'required' => false,
						'default'  => 'yes',
						'options'  => [
							[
								'value' => 'yes',
								'label' => 'Yes',
							],
							[
								'value' => 'no',
								'label' => 'No',
							],
						],
					],
					[
						'key'      => 'include_content',
						'label'    => 'Include body content',
						'type'     => 'select',
						'required' => false,
						'default'  => 'yes',
						'options'  => [
							[
								'value' => 'yes',
								'label' => 'Yes',
							],
							[
								'value' => 'no',
								'label' => 'No',
							],
						],
					],
					[
						'key'         => 'meta_keys',
						'label'       => 'Custom fields (optional)',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'price, sku',
						'help'        => 'Comma-separated meta keys to append to each entry (scalar values only).',
					],
					[
						'key'         => 'taxonomies',
						'label'       => 'Taxonomies (optional)',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'category, product_cat',
						'help'        => 'Comma-separated taxonomies whose term names get appended to each entry.',
					],
					[
						'key'      => 'limit',
						'label'    => 'Max Items (0 = all)',
						'type'     => 'number',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'prune',
						'label'    => 'Remove deleted items',
						'type'     => 'select',
						'required' => false,
						'default'  => 'yes',
						'options'  => [
							[
								'value' => 'yes',
								'label' => 'Yes — mirror the source (full sync only)',
							],
							[
								'value' => 'no',
								'label' => 'No — only add/update',
							],
						],
						'help'     => 'When on (and syncing all items), removes synced entries whose source item no longer exists. Manual entries are never touched.',
					],
				];

			case 'sync_storeengine':
				return [
					$business_field,
					[
						'key'      => 'limit',
						'label'    => 'Max Products (0 = all)',
						'type'     => 'number',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'prune',
						'label'    => 'Remove deleted products',
						'type'     => 'select',
						'required' => false,
						'default'  => 'yes',
						'options'  => [
							[
								'value' => 'yes',
								'label' => 'Yes — mirror the catalog (full sync only)',
							],
							[
								'value' => 'no',
								'label' => 'No — only add/update',
							],
						],
						'help'     => 'When on (and syncing all products), removes synced entries whose product no longer exists. Manual entries are never touched.',
					],
				];

			case 'embed_backfill':
				return [
					$business_field,
					[
						'key'      => 'limit',
						'label'    => 'Max Entries Per Run',
						'type'     => 'number',
						'required' => false,
						'default'  => 100,
						'help'     => 'Embeds entries that have no vector yet (newest sync leaves them empty). Run again until remaining is 0.',
					],
				];

			case 'clear':
				return [ $business_field ];
		}//end switch

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$key    = trim( (string) ( $config['business_key'] ?? '' ) );

		if ( '' === $key ) {
			return self::respond(
				array_merge(
					$input,
					[
						'success' => false,
						'error'   => 'business_key is required.',
					]
				)
			);
		}

		switch ( $event ) {
			case 'retrieve':
				return self::action_retrieve( $key, $config, $input );
			case 'add_entry':
				return self::action_add_entry( $key, $config, $input );
			case 'sync_content':
				return self::action_sync_content( $key, $config, $input );
			case 'sync_storeengine':
				return self::action_sync_storeengine( $key, $config, $input );
			case 'embed_backfill':
				return self::action_embed_backfill( $key, $config, $input );
			case 'clear':
				return self::action_clear( $key, $input );
		}

		return self::respond( $input );
	}

	private static function action_retrieve( string $key, array $config, array $input ): array {
		global $wpdb;

		$query = trim( (string) ( $config['query'] ?? '' ) );
		$limit = (int) ( $config['limit'] ?? self::DEFAULT_LIMIT );
		if ( $limit <= 0 ) {
			$limit = self::DEFAULT_LIMIT;
		}

		$table = KnowledgeModel::getTable();
		$words = self::tokenize( $query );

		// Try semantic scoring; fall back to keyword-only if embeddings are off or
		// the query can't be embedded. Hybrid = weighted blend of the two.
		$query_vec   = KnowledgeEmbeddings::enabled() ? KnowledgeEmbeddings::embed( $query ) : null;
		$use_vec     = ! empty( $query_vec );
		$current_tag = $use_vec ? KnowledgeEmbeddings::tag() : '';
		$query_dims  = $use_vec ? count( $query_vec ) : 0;

		// Cap on how many rows we pull into PHP for scoring. Filterable for large
		// deployments; see the note on scale below.
		$scan = (int) apply_filters( 'zaplane_knowledge_max_scan', self::MAX_SCAN );
		if ( $scan <= 0 ) {
			$scan = self::MAX_SCAN;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE business_key = %s",
				$table,
				$key
			)
		);

		$rows = null;

		// Keyword-only mode: an indexed FULLTEXT lookup returns just the matching
		// candidates instead of scanning every row. Semantic mode still needs the
		// full (bounded) set, since vector matches don't share keywords. Falls back
		// to the scan when FULLTEXT is unavailable or finds nothing.
		if ( ! $use_vec && ! empty( $words ) ) {
			$rows = self::fulltext_candidates( $table, $key, $words, $scan );
		}

		if ( null === $rows ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title, content, embedding, source FROM %i WHERE business_key = %s ORDER BY id DESC LIMIT %d",
					$table,
					$key,
					$scan
				),
				ARRAY_A
			);
		}

		$rows      = is_array( $rows ) ? $rows : [];
		$truncated = $total > count( $rows ) && $total > $scan;

		if ( ! $use_vec && empty( $words ) ) {
			// Nothing to rank by — return the most recent entries.
			$top = array_slice( $rows, 0, $limit );
		} else {
			$weights   = apply_filters(
				'zaplane_knowledge_hybrid_weights',
				[
					'vector'  => 0.6,
					'keyword' => 0.4,
				]
			);
			$w_vec = (float) ( $weights['vector'] ?? 0.6 );
			$w_kw  = (float) ( $weights['keyword'] ?? 0.4 );

			// Normalize keyword scores to [0,1] so they blend with cosine.
			$kw_scores = [];
			$max_kw    = 0;
			foreach ( $rows as $i => $row ) {
				$s             = empty( $words ) ? 0 : self::score_row( $row, $words );
				$kw_scores[ $i ] = $s;
				$max_kw        = max( $max_kw, $s );
			}

			$scored = [];
			foreach ( $rows as $i => $row ) {
				$kw_norm = $max_kw > 0 ? ( $kw_scores[ $i ] / $max_kw ) : 0.0;

				if ( $use_vec ) {
					// usable_vector() returns null for vectors from a different
					// embedding model (or dimension) — those score keyword-only
					// instead of contributing a meaningless cosine.
					$row_vec = KnowledgeEmbeddings::usable_vector( $row['embedding'] ?? null, $current_tag, $query_dims );
					$cos     = KnowledgeEmbeddings::cosine( $query_vec, $row_vec );
					$final   = ( $w_vec * $cos ) + ( $w_kw * $kw_norm );
				} else {
					$final = $kw_norm;
				}

				// A hand-authored FAQ answering this exact question should
				// usually outrank an incidental mention buried in a synced
				// product description — a small, filterable boost, not a hard
				// filter (so a strong content match can still win).
				if ( 'faq' === ( $row['source'] ?? '' ) ) {
					$final *= (float) apply_filters( 'zaplane_knowledge_faq_boost', 1.15 );
				}

				if ( $final > 0 ) {
					$scored[] = [
						'score' => $final,
						'row'   => $row,
					];
				}
			}

			usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
			$top = array_map( static fn( $s ) => $s['row'], array_slice( $scored, 0, $limit ) );

			// Keyword query that matched nothing → recent entries, as before.
			if ( empty( $top ) && ! $use_vec ) {
				$top = array_slice( $rows, 0, $limit );
			}
		}//end if

		$blocks  = [];
		$matches = [];
		foreach ( $top as $row ) {
			$title     = (string) ( $row['title'] ?? '' );
			$content   = (string) ( $row['content'] ?? '' );
			$blocks[]  = ( '' !== $title ? $title . ":\n" : '' ) . $content;
			$matches[] = [
				'id'     => (int) $row['id'],
				'title'  => $title,
				'source' => (string) ( $row['source'] ?? '' ),
			];
		}

		$context = implode( "\n\n---\n\n", $blocks );

		return self::respond(
			array_merge(
				$input,
				[
					'success'   => true,
					'context'   => $context,
					'matches'   => $matches,
					'count'     => count( $matches ),
					// True when the business has more entries than the scan cap, so
					// some were not considered. Raise `zaplane_knowledge_max_scan`
					// (or move to a vector store) for very large knowledge bases.
					'truncated' => $truncated,
				]
			)
		);
	}

	private static function action_add_entry( string $key, array $config, array $input ): array {
		$content = (string) ( $config['content'] ?? '' );
		if ( '' === trim( $content ) ) {
			return self::respond(
				array_merge(
					$input,
					[
						'success' => false,
						'error'   => 'content is required.',
					]
				)
			);
		}

		$source = sanitize_text_field( (string) ( $config['source'] ?? 'manual' ) );

		$id = self::upsert(
			$key,
			sanitize_text_field( (string) ( $config['title'] ?? '' ) ),
			$content,
			'' !== $source ? $source : 'manual',
			sanitize_text_field( (string) ( $config['ref_id'] ?? '' ) ),
			true
		);

		return self::respond(
			array_merge(
				$input,
				[
					'success'  => (bool) $id,
					'entry_id' => $id,
				]
			)
		);
	}

	/**
	 * Generic content sync: mirror any WordPress post type into a business's
	 * knowledge. Each post becomes one entry (title + assembled body). Works for
	 * posts, pages, products, docs, or any registered CPT.
	 *
	 * A `post_id` in $config re-syncs just that one post instead of querying the
	 * whole post type — used by the auto-sync-on-save hook so an edit doesn't
	 * re-walk the entire catalog. Pruning and profile-remembering are skipped in
	 * that mode (see below).
	 */
	public static function action_sync_content( string $key, array $config, array $input ): array {
		$post_type = sanitize_key( (string) ( $config['post_type'] ?? '' ) );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return self::respond(
				array_merge(
					$input,
					[
						'success' => false,
						'error'   => '' === $post_type ? 'post_type is required.' : sprintf( 'Unknown post type "%s".', $post_type ),
					]
				)
			);
		}

		$status = sanitize_key( (string) ( $config['post_status'] ?? 'publish' ) );
		if ( '' === $status ) {
			$status = 'publish';
		}
		$limit          = (int) ( $config['limit'] ?? 0 );
		$single_post_id = (int) ( $config['post_id'] ?? 0 );

		if ( $single_post_id > 0 ) {
			$ids = [ $single_post_id ];
		} else {
			$ids = get_posts(
				[
					'post_type'      => $post_type,
					'post_status'    => $status,
					'posts_per_page' => $limit > 0 ? $limit : -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				]
			);
		}

		$source      = self::source_for( $post_type );
		$synced      = 0;
		$synced_refs = [];
		foreach ( (array) $ids as $pid ) {
			$post = get_post( (int) $pid );
			if ( ! $post || ( $single_post_id > 0 && $post->post_type !== $post_type ) ) {
				continue;
			}

			$title   = (string) get_the_title( $pid );
			$content = self::build_post_content( $post, $post_type, $config );

			if ( '' === trim( $content ) && '' === trim( $title ) ) {
				continue;
			}

			$ref = self::ref_for( $post_type, (int) $pid );
			self::upsert( $key, $title, $content, $source, $ref );
			$synced_refs[] = $ref;
			++$synced;
		}//end foreach

		// Prune deleted items: drop any entry from this source not seen in this
		// run. Only on a full sync (limit 0, not a single-post resync) so a
		// partial sync can't wipe items beyond the limit; entries from other
		// sources (manual, etc.) are untouched.
		$pruned = 0;
		$prune  = ( 'no' !== ( $config['prune'] ?? 'yes' ) );
		if ( $prune && $limit <= 0 && 0 === $single_post_id ) {
			$pruned = self::prune_source( $key, $source, $synced_refs );
		}

		// Remember this as the sync profile for the post type, so the auto-sync
		// hook knows what settings to reuse when a post of this type is saved.
		// Skipped for a single-post resync (that's already following a profile).
		if ( 0 === $single_post_id ) {
			self::remember_sync_profile( $key, $post_type, $config );
		}

		// New/changed rows above were left unembedded (bulk syncs don't embed
		// inline). Queue a background batch instead of requiring a manual
		// "Backfill now" click.
		if ( $synced > 0 && KnowledgeEmbeddings::enabled() && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'zaplane_knowledge_embed_pending', [ 'business_key' => $key ], 'zaplane' );
		}

		return self::respond(
			array_merge(
				$input,
				[
					'success'   => true,
					'synced'    => $synced,
					'pruned'    => $pruned,
					'post_type' => $post_type,
				]
			)
		);
	}

	/** Option storing, per business+post type, the sync settings to reuse for auto-sync-on-save. */
	private const SYNC_PROFILES_OPTION = 'zaplane_knowledge_sync_profiles';

	/**
	 * Persist the settings a manual (or storeengine) sync just used, so the
	 * auto-sync-on-save hook can reproduce the same entry shape for a single
	 * edited post without the caller re-specifying everything.
	 *
	 * @param array<string,mixed> $config
	 */
	private static function remember_sync_profile( string $key, string $post_type, array $config ): void {
		$profiles = get_option( self::SYNC_PROFILES_OPTION, [] );
		if ( ! is_array( $profiles ) ) {
			$profiles = [];
		}

		$profiles[ $key . '::' . $post_type ] = [
			'business_key'    => $key,
			'post_type'       => $post_type,
			'post_status'     => (string) ( $config['post_status'] ?? 'publish' ),
			'include_excerpt' => ( 'no' === ( $config['include_excerpt'] ?? 'yes' ) ) ? 'no' : 'yes',
			'include_content' => ( 'no' === ( $config['include_content'] ?? 'yes' ) ) ? 'no' : 'yes',
			'meta_keys'       => (string) ( $config['meta_keys'] ?? '' ),
			'taxonomies'      => (string) ( $config['taxonomies'] ?? '' ),
		];

		update_option( self::SYNC_PROFILES_OPTION, $profiles, false );
	}

	/**
	 * Sync profiles registered for a given post type (a post type can be synced
	 * into more than one business). Used by the auto-sync-on-save hook.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function sync_profiles_for_post_type( string $post_type ): array {
		$profiles = get_option( self::SYNC_PROFILES_OPTION, [] );
		if ( ! is_array( $profiles ) ) {
			return [];
		}
		return array_values( array_filter( $profiles, static fn( $p ) => is_array( $p ) && ( $p['post_type'] ?? '' ) === $post_type ) );
	}

	/**
	 * Remove every synced knowledge entry that referenced a now-deleted/trashed
	 * post, across every business it was synced into. Manual/FAQ entries are
	 * untouched (they're never sourced from a post).
	 */
	public static function forget_synced_post( int $post_id, string $post_type ): void {
		$ref = self::ref_for( $post_type, $post_id );
		global $wpdb;
		$table = KnowledgeModel::getTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE source = %s AND ref_id = %s', $table, self::source_for( $post_type ), $ref ) );
	}

	/**
	 * StoreEngine products are just the `storeengine_product` post type — this is
	 * a thin preset over the generic engine, kept for the admin button, existing
	 * recipes, and saved nodes. Prices are added by build_post_content().
	 */
	public static function action_sync_storeengine( string $key, array $config, array $input ): array {
		if ( ! function_exists( 'storeengine_get_product' ) ) {
			return self::respond(
				array_merge(
					$input,
					[
						'success' => false,
						'error'   => 'StoreEngine is not active.',
					]
				)
			);
		}

		$config['post_type']   = 'storeengine_product';
		$config['post_status'] = 'publish';

		return self::action_sync_content( $key, $config, $input );
	}

	/**
	 * The `source` column value for a post type. StoreEngine products keep their
	 * historical `storeengine` source so existing synced rows are updated in
	 * place (not duplicated); every other post type uses its own slug.
	 */
	private static function source_for( string $post_type ): string {
		return 'storeengine_product' === $post_type ? 'storeengine' : $post_type;
	}

	/**
	 * A stable, per-item reference id so re-syncing updates instead of
	 * duplicating. StoreEngine keeps its historical `se_{id}` scheme.
	 */
	private static function ref_for( string $post_type, int $id ): string {
		return 'storeengine_product' === $post_type ? 'se_' . $id : $post_type . '_' . $id;
	}

	/**
	 * Assemble one knowledge entry body from a post: excerpt, stripped content,
	 * selected custom fields, and taxonomy terms. First-party StoreEngine pricing
	 * is added inline; third parties can extend via the
	 * `zaplane_knowledge_sync_entry` filter.
	 *
	 * @param \WP_Post            $post
	 * @param array<string,mixed> $config
	 */
	private static function build_post_content( $post, string $post_type, array $config ): string {
		$parts = [];

		if ( 'no' !== ( $config['include_excerpt'] ?? 'yes' ) ) {
			$excerpt = has_excerpt( $post ) ? wp_strip_all_tags( (string) get_the_excerpt( $post ) ) : '';
			$excerpt = trim( preg_replace( '/\s+/', ' ', $excerpt ) );
			if ( '' !== $excerpt ) {
				$parts[] = $excerpt;
			}
		}

		if ( 'no' !== ( $config['include_content'] ?? 'yes' ) ) {
			$body = wp_strip_all_tags( (string) $post->post_content );
			$body = trim( preg_replace( '/\s+/', ' ', $body ) );
			if ( strlen( $body ) > 1500 ) {
				$body = substr( $body, 0, 1500 ) . '…';
			}
			if ( '' !== $body ) {
				$parts[] = $body;
			}
		}

		// StoreEngine prices live on the product object, not in post meta — pull
		// them in for the product post type so entries carry pricing.
		if ( 'storeengine_product' === $post_type && function_exists( 'storeengine_get_product' ) ) {
			$product = storeengine_get_product( (int) $post->ID );
			if ( $product && ! is_wp_error( $product ) ) {
				$price_line = self::storeengine_price_line( $product );
				if ( '' !== $price_line ) {
					$parts[] = 'Price: ' . $price_line;
				}
			}
		}

		foreach ( self::split_list( (string) ( $config['meta_keys'] ?? '' ) ) as $meta_key ) {
			$value = get_post_meta( (int) $post->ID, $meta_key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$parts[] = $meta_key . ': ' . trim( (string) $value );
			}
		}

		foreach ( self::split_list( (string) ( $config['taxonomies'] ?? '' ) ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( (int) $post->ID, $taxonomy );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				$names = array_map( static fn( $t ) => $t->name, $terms );
				$parts[] = $taxonomy . ': ' . implode( ', ', $names );
			}
		}

		$content = implode( "\n", $parts );

		/**
		 * Filter a synced knowledge entry's body before it is stored. Lets any
		 * integration enrich entries for its own post types.
		 *
		 * @param string   $content   Assembled entry body.
		 * @param \WP_Post $post      Source post.
		 * @param string   $post_type Post type slug.
		 * @param array    $config    Sync action config.
		 */
		return (string) apply_filters( 'zaplane_knowledge_sync_entry', $content, $post, $post_type, $config );
	}

	/**
	 * Split a comma-separated list into trimmed, non-empty, unique values.
	 *
	 * @return array<int,string>
	 */
	private static function split_list( string $csv ): array {
		$out = [];
		foreach ( explode( ',', $csv ) as $item ) {
			$item = trim( $item );
			if ( '' !== $item ) {
				$out[ $item ] = true;
			}
		}
		return array_keys( $out );
	}

	private static function prune_source( string $key, string $source, array $keep_refs ): int {
		global $wpdb;
		$table = KnowledgeModel::getTable();

		if ( empty( $keep_refs ) ) {
			// No items at all → remove every entry for this source in this business.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM %i WHERE business_key = %s AND source = %s",
					$table,
					$key,
					$source
				)
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $keep_refs ), '%s' ) );
		$params       = array_merge( [ $table, $key, $source ], $keep_refs );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholder count is dynamic; $params supplies exactly count($keep_refs)+3 values to match.
				"DELETE FROM %i WHERE business_key = %s AND source = %s AND ref_id NOT IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholder count generated internally.
				$params
			)
		);
	}

	private static function storeengine_price_line( $product ): string {
		if ( ! method_exists( $product, 'get_prices' ) ) {
			return '';
		}

		$symbol = '';
		if ( class_exists( '\StoreEngine\Utils\Helper' ) && method_exists( '\StoreEngine\Utils\Helper', 'get_currency_symbol' ) ) {
			$symbol = (string) \StoreEngine\Utils\Helper::get_currency_symbol();
		}

		$parts = [];
		foreach ( (array) $product->get_prices() as $price ) {
			if ( ! is_object( $price ) || ! method_exists( $price, 'get_price' ) ) {
				continue;
			}
			$amount  = $price->get_price();
			$label   = method_exists( $price, 'get_name' ) ? trim( (string) $price->get_name() ) : '';
			$value   = $symbol . rtrim( rtrim( number_format( (float) $amount, 2 ), '0' ), '.' );
			$parts[] = ( '' !== $label ) ? ( $label . ': ' . $value ) : $value;
		}

		return implode( ', ', $parts );
	}

	private static function upsert( string $key, string $title, string $content, string $source, string $ref_id, bool $embed_now = false ): int {
		// Embed inline only for one-off manual entries. Bulk syncs skip this
		// (N API calls would stall the request) and leave embeddings null for the
		// backfill action to fill in the background.
		$embedding = null;
		if ( $embed_now && KnowledgeEmbeddings::enabled() ) {
			$embedding = KnowledgeEmbeddings::encode( KnowledgeEmbeddings::embed( trim( $title . "\n" . $content ) ) );
		}

		$existing = null;
		if ( '' !== $ref_id ) {
			// fresh() bypasses the ORM's per-request query cache: without it a
			// prior "not found" lookup for this ref is cached, so a second sync
			// pass in the same request would re-insert instead of update.
			$existing = KnowledgeModel::where( 'business_key', $key )
				->where( 'ref_id', $ref_id )
				->fresh()
				->first();
		}

		if ( $existing ) {
			$changed              = ( (string) $existing->title !== $title ) || ( (string) $existing->content !== $content );
			$existing->title      = $title;
			$existing->content    = $content;
			$existing->source     = $source;
			if ( $embed_now ) {
				$existing->embedding = $embedding;
			} elseif ( $changed ) {
				// Content changed → drop the now-stale vector so backfill re-embeds.
				$existing->embedding = null;
			}
			$existing->updated_at = current_time( 'mysql' );
			$existing->save();
			return (int) $existing->id;
		}

		$record = KnowledgeModel::create(
			[
				'business_key' => $key,
				'title'        => $title,
				'content'      => $content,
				'embedding'    => $embedding,
				'source'       => '' !== $source ? $source : 'manual',
				'ref_id'       => '' !== $ref_id ? $ref_id : null,
				'updated_at'   => current_time( 'mysql' ),
			]
		);

		return is_object( $record ) && isset( $record->id ) ? (int) $record->id : 0;
	}

	private static function action_clear( string $key, array $input ): array {
		global $wpdb;
		$table = KnowledgeModel::getTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE business_key = %s",
				$table,
				$key
			)
		);

		return self::respond(
			array_merge(
				$input,
				[
					'success' => true,
					'deleted' => (int) $deleted,
				]
			)
		);
	}

	/**
	 * Embed entries that don't have a vector yet (bulk syncs leave them empty).
	 * Processes up to `limit` per run and reports how many remain so the caller
	 * can loop until done.
	 */
	public static function action_embed_backfill( string $key, array $config, array $input ): array {
		global $wpdb;
		$table = KnowledgeModel::getTable();

		if ( ! KnowledgeEmbeddings::enabled() ) {
			return self::respond(
				array_merge(
					$input,
					[
						'success' => false,
						'error'   => 'Semantic embeddings are not configured.',
					]
				)
			);
		}

		$limit = (int) ( $config['limit'] ?? 100 );
		if ( $limit <= 0 ) {
			$limit = 100;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, content FROM %i WHERE business_key = %s AND ( embedding IS NULL OR embedding = '' ) ORDER BY id ASC LIMIT %d",
				$table,
				$key,
				$limit
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : [];

		$embedded = 0;
		$failed   = 0;

		// Embed in batches (one API call per chunk) instead of one call per row.
		foreach ( array_chunk( $rows, 96 ) as $chunk ) {
			$texts = array_map(
				static fn( $r ) => trim( (string) $r['title'] . "\n" . (string) $r['content'] ),
				$chunk
			);
			$vectors = KnowledgeEmbeddings::embed_batch( $texts );

			foreach ( $chunk as $i => $row ) {
				$vec = $vectors[ $i ] ?? null;
				if ( null === $vec ) {
					++$failed;
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, [ 'embedding' => KnowledgeEmbeddings::encode( $vec ) ], [ 'id' => (int) $row['id'] ] );
				++$embedded;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE business_key = %s AND ( embedding IS NULL OR embedding = '' )",
				$table,
				$key
			)
		);

		return self::respond(
			array_merge(
				$input,
				[
					'success'   => true,
					'embedded'  => $embedded,
					'failed'    => $failed,
					'remaining' => $remaining,
				]
			)
		);
	}

	/**
	 * Fetch keyword candidates via the FULLTEXT index (BOOLEAN MODE, prefix
	 * match). Returns matching rows, or null to signal "fall back to the row
	 * scan" — when there's no index (error) or no match, so callers keep their
	 * existing recency fallback behavior.
	 *
	 * @param array<int,string> $words
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function fulltext_candidates( string $table, string $key, array $words, int $limit ): ?array {
		global $wpdb;

		// InnoDB's default min token length is 3; shorter words are never indexed.
		$terms = [];
		foreach ( $words as $w ) {
			$w = preg_replace( '/[^a-z0-9]/', '', strtolower( $w ) );
			if ( strlen( $w ) >= 3 ) {
				$terms[] = $w . '*';
			}
		}
		if ( empty( $terms ) ) {
			return null; // nothing indexable → let the scan + PHP scorer handle it
		}

		$boolean = implode( ' ', $terms );

		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, content, embedding, source FROM %i WHERE business_key = %s AND MATCH(title, content) AGAINST (%s IN BOOLEAN MODE) LIMIT %d",
				$table,
				$key,
				$boolean,
				$limit
			),
			ARRAY_A
		);
		$err = $wpdb->last_error;
		$wpdb->suppress_errors( $suppress );

		// No FULLTEXT index (error) or no matches → fall back to the scan so the
		// keyword scorer and its recency fallback still run.
		if ( '' !== (string) $err || empty( $rows ) ) {
			return null;
		}

		return $rows;
	}

	private static function tokenize( string $text ): array {
		$text  = strtolower( $text );
		$parts = preg_split( '/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$words = [];
		foreach ( (array) $parts as $p ) {
			if ( strlen( $p ) >= 2 ) {
				$words[ $p ] = true;
			}
		}
		return array_keys( $words );
	}

	private static function score_row( array $row, array $words ): int {
		$title   = strtolower( (string) ( $row['title'] ?? '' ) );
		$content = strtolower( (string) ( $row['content'] ?? '' ) );
		$score   = 0;

		foreach ( $words as $w ) {
			if ( '' !== $title && false !== strpos( $title, $w ) ) {
				$score += 3;
			}
			if ( false !== strpos( $content, $w ) ) {
				++$score;
			}
		}

		return $score;
	}

	private static function respond( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}
}
