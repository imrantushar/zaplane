<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Models\Knowledge as KnowledgeModel;

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, content FROM {$table} WHERE business_key = %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name resolved internally.
				$key,
				self::MAX_SCAN
			),
			ARRAY_A
		);

		$rows = is_array( $rows ) ? $rows : [];

		$words = self::tokenize( $query );

		if ( empty( $words ) ) {
			// No usable query terms — fall back to the most recent entries.
			$top = array_slice( $rows, 0, $limit );
		} else {
			$scored = [];
			foreach ( $rows as $row ) {
				$score = self::score_row( $row, $words );
				if ( $score > 0 ) {
					$scored[] = [
						'score' => $score,
						'row'   => $row,
					];
				}
			}
			usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
			$top = array_map( static fn( $s ) => $s['row'], array_slice( $scored, 0, $limit ) );
		}

		$blocks  = [];
		$matches = [];
		foreach ( $top as $row ) {
			$title     = (string) ( $row['title'] ?? '' );
			$content   = (string) ( $row['content'] ?? '' );
			$blocks[]  = ( '' !== $title ? $title . ":\n" : '' ) . $content;
			$matches[] = [
				'id'    => (int) $row['id'],
				'title' => $title,
			];
		}

		$context = implode( "\n\n---\n\n", $blocks );

		return self::respond(
			array_merge(
				$input,
				[
					'success' => true,
					'context' => $context,
					'matches' => $matches,
					'count'   => count( $matches ),
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
			sanitize_text_field( (string) ( $config['ref_id'] ?? '' ) )
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
		$limit = (int) ( $config['limit'] ?? 0 );

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

		$source      = self::source_for( $post_type );
		$synced      = 0;
		$synced_refs = [];
		foreach ( (array) $ids as $pid ) {
			$post = get_post( (int) $pid );
			if ( ! $post ) {
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
		// run. Only on a full sync (limit 0) so a partial sync can't wipe items
		// beyond the limit; entries from other sources (manual, etc.) are untouched.
		$pruned = 0;
		$prune  = ( 'no' !== ( $config['prune'] ?? 'yes' ) );
		if ( $prune && $limit <= 0 ) {
			$pruned = self::prune_source( $key, $source, $synced_refs );
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
					"DELETE FROM {$table} WHERE business_key = %s AND source = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name resolved internally.
					$key,
					$source
				)
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $keep_refs ), '%s' ) );
		$params       = array_merge( [ $key, $source ], $keep_refs );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholder count is dynamic; $params supplies exactly count($keep_refs)+2 values to match.
				"DELETE FROM {$table} WHERE business_key = %s AND source = %s AND ref_id NOT IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and placeholder count generated internally.
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

	private static function upsert( string $key, string $title, string $content, string $source, string $ref_id ): int {
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
			$existing->title      = $title;
			$existing->content    = $content;
			$existing->source     = $source;
			$existing->updated_at = current_time( 'mysql' );
			$existing->save();
			return (int) $existing->id;
		}

		$record = KnowledgeModel::create(
			[
				'business_key' => $key,
				'title'        => $title,
				'content'      => $content,
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
				"DELETE FROM {$table} WHERE business_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name resolved internally.
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
