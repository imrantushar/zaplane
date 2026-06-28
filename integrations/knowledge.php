<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Models\Knowledge as KnowledgeModel;

/**
 * Business Knowledge — a per-business fact store (products, prices, FAQ,
 * policies) the AI node retrieves from so replies are grounded in real data.
 *
 * Scoped by a `business_key` so two businesses never see each other's data.
 * Retrieval is keyword/word-score based (no embeddings, no API key) and scales
 * to thousands of rows; the `embedding` column is reserved for a future
 * semantic layer that can be added without changing the workflow.
 */
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
			'help'        => 'Identifies which business this knowledge belongs to.',
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
						'key'         => 'ref_id',
						'label'       => 'Reference ID (optional)',
						'type'        => 'expression',
						'required'    => false,
						'help'        => 'If set, re-adding with the same ref updates the entry instead of duplicating.',
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
							[ 'value' => 'yes', 'label' => 'Yes — mirror the catalog (full sync only)' ],
							[ 'value' => 'no', 'label' => 'No — only add/update' ],
						],
						'help'     => 'When on (and syncing all products), removes synced entries whose product no longer exists. Manual entries are never touched.',
					],
				];

			case 'clear':
				return [ $business_field ];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$key    = trim( (string) ( $config['business_key'] ?? '' ) );

		if ( '' === $key ) {
			return self::respond( array_merge( $input, [ 'success' => false, 'error' => 'business_key is required.' ] ) );
		}

		switch ( $event ) {
			case 'retrieve':
				return self::action_retrieve( $key, $config, $input );
			case 'add_entry':
				return self::action_add_entry( $key, $config, $input );
			case 'sync_storeengine':
				return self::action_sync_storeengine( $key, $config, $input );
			case 'clear':
				return self::action_clear( $key, $input );
		}

		return self::respond( $input );
	}

	/**
	 * Keyword/word-score retrieval scoped to one business. Returns the top
	 * matching entries concatenated into a `context` string for the AI prompt.
	 */
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
					$scored[] = [ 'score' => $score, 'row' => $row ];
				}
			}
			usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
			$top = array_map( static fn( $s ) => $s['row'], array_slice( $scored, 0, $limit ) );
		}

		$blocks  = [];
		$matches = [];
		foreach ( $top as $row ) {
			$title    = (string) ( $row['title'] ?? '' );
			$content  = (string) ( $row['content'] ?? '' );
			$blocks[] = ( '' !== $title ? $title . ":\n" : '' ) . $content;
			$matches[] = [ 'id' => (int) $row['id'], 'title' => $title ];
		}

		$context = implode( "\n\n---\n\n", $blocks );

		return self::respond( array_merge( $input, [
			'success' => true,
			'context' => $context,
			'matches' => $matches,
			'count'   => count( $matches ),
		] ) );
	}

	private static function action_add_entry( string $key, array $config, array $input ): array {
		$content = (string) ( $config['content'] ?? '' );
		if ( '' === trim( $content ) ) {
			return self::respond( array_merge( $input, [ 'success' => false, 'error' => 'content is required.' ] ) );
		}

		$id = self::upsert(
			$key,
			sanitize_text_field( (string) ( $config['title'] ?? '' ) ),
			$content,
			sanitize_text_field( (string) ( $config['source'] ?? 'manual' ) ) ?: 'manual',
			sanitize_text_field( (string) ( $config['ref_id'] ?? '' ) )
		);

		return self::respond( array_merge( $input, [ 'success' => (bool) $id, 'entry_id' => $id ] ) );
	}

	/**
	 * Pull StoreEngine products into the business's knowledge. Each product is
	 * upserted by ref_id (se_<product_id>) so re-running refreshes prices/names
	 * without duplicating, and never touches manual or other-source entries.
	 */
	public static function action_sync_storeengine( string $key, array $config, array $input ): array {
		if ( ! function_exists( 'storeengine_get_product' ) ) {
			return self::respond( array_merge( $input, [ 'success' => false, 'error' => 'StoreEngine is not active.' ] ) );
		}

		$limit = (int) ( $config['limit'] ?? 0 );

		$ids = get_posts( [
			'post_type'      => 'storeengine_product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		$synced      = 0;
		$synced_refs = [];
		foreach ( (array) $ids as $pid ) {
			$product = storeengine_get_product( (int) $pid );
			if ( ! $product || is_wp_error( $product ) ) {
				continue;
			}

			$name = method_exists( $product, 'get_name' ) ? $product->get_name() : get_the_title( $pid );
			$desc = method_exists( $product, 'get_content' ) ? wp_strip_all_tags( (string) $product->get_content() ) : '';
			$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );
			if ( strlen( $desc ) > 400 ) {
				$desc = substr( $desc, 0, 400 ) . '…';
			}

			$price_line = self::storeengine_price_line( $product );

			$content = $name;
			if ( '' !== $price_line ) {
				$content .= "\nPrice: " . $price_line;
			}
			if ( '' !== $desc ) {
				$content .= "\n" . $desc;
			}

			$ref = 'se_' . (int) $pid;
			self::upsert( $key, (string) $name, $content, 'storeengine', $ref );
			$synced_refs[] = $ref;
			++$synced;
		}

		// Prune deleted products: drop any storeengine-sourced entries not seen
		// in this run. Only on a full sync (limit 0) so a partial sync can't wipe
		// products beyond the limit; manual entries (other sources) are untouched.
		$pruned = 0;
		$prune  = ( 'no' !== ( $config['prune'] ?? 'yes' ) );
		if ( $prune && $limit <= 0 ) {
			$pruned = self::prune_storeengine( $key, $synced_refs );
		}

		return self::respond( array_merge( $input, [
			'success' => true,
			'synced'  => $synced,
			'pruned'  => $pruned,
		] ) );
	}

	/**
	 * Delete storeengine-sourced rows for a business whose ref_id is NOT in the
	 * keep list (i.e. products that no longer exist). Never touches rows from
	 * other sources (manual FAQ/policy entries).
	 */
	private static function prune_storeengine( string $key, array $keep_refs ): int {
		global $wpdb;
		$table = KnowledgeModel::getTable();

		if ( empty( $keep_refs ) ) {
			// No products at all → remove every storeengine entry for this business.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE business_key = %s AND source = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$key,
					'storeengine'
				)
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $keep_refs ), '%s' ) );
		$params       = array_merge( [ $key, 'storeengine' ], $keep_refs );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE business_key = %s AND source = %s AND ref_id NOT IN ({$placeholders})",
				$params
			)
		);
	}

	/** Build a readable "Standard: 25, Premium: 40" price string for a product. */
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
			$amount = $price->get_price();
			$label  = method_exists( $price, 'get_name' ) ? trim( (string) $price->get_name() ) : '';
			$value  = $symbol . rtrim( rtrim( number_format( (float) $amount, 2 ), '0' ), '.' );
			$parts[] = ( '' !== $label ) ? ( $label . ': ' . $value ) : $value;
		}

		return implode( ', ', $parts );
	}

	/**
	 * Insert or update a knowledge row. Upserts by (business_key, ref_id) when a
	 * ref is supplied; otherwise always inserts. Returns the row id.
	 */
	private static function upsert( string $key, string $title, string $content, string $source, string $ref_id ): int {
		$existing = null;
		if ( '' !== $ref_id ) {
			$existing = KnowledgeModel::where( 'business_key', $key )
				->where( 'ref_id', $ref_id )
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

		$record = KnowledgeModel::create( [
			'business_key' => $key,
			'title'        => $title,
			'content'      => $content,
			'source'       => $source ?: 'manual',
			'ref_id'       => '' !== $ref_id ? $ref_id : null,
			'updated_at'   => current_time( 'mysql' ),
		] );

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

		return self::respond( array_merge( $input, [ 'success' => true, 'deleted' => (int) $deleted ] ) );
	}

	/** Split a query into lowercase search terms (>=2 chars, deduped). */
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

	/** Score a row: title matches weighted higher than content matches. */
	private static function score_row( array $row, array $words ): int {
		$title   = strtolower( (string) ( $row['title'] ?? '' ) );
		$content = strtolower( (string) ( $row['content'] ?? '' ) );
		$score   = 0;

		foreach ( $words as $w ) {
			if ( '' !== $title && false !== strpos( $title, $w ) ) {
				$score += 3;
			}
			if ( false !== strpos( $content, $w ) ) {
				$score += 1;
			}
		}

		return $score;
	}

	private static function respond( array $data ): array {
		return [ 'port' => 'main', 'data' => $data ];
	}
}
