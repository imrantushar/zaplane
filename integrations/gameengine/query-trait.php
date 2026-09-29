<?php

namespace Zaplane\Integrations\Gameengine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QueryTrait {

    public static function query_point_types( $query = null ): array {
		if ( class_exists( '\GameEngine\Classes\PointsManager' ) ) {
			$items = [];
			foreach ( (array) \GameEngine\Classes\PointsManager::get_point_types() as $row ) {
				$row   = (array) $row;
				$items[] = [
					'value' => (int) ( $row['id'] ?? 0 ),
					'label' => (string) ( $row['plural_name'] ?? ( $row['name'] ?? ( $row['slug'] ?? '' ) ) ),
				];
			}

			return $items;
		}

		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, name, plural_name, slug FROM {$wpdb->prefix}gameengine_point_types WHERE status = 'publish' ORDER BY id ASC",
			ARRAY_A
		);

		return self::pairs_from_rows( $rows, 'plural_name', 'id', [ 'name', 'slug' ] );
	}

	public static function query_achievements( $query = null ): array {
		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, title FROM {$wpdb->prefix}gameengine_achievements WHERE status = 'publish' ORDER BY title ASC LIMIT 200",
			ARRAY_A
		);

		return self::pairs_from_rows( $rows, 'title', 'id' );
	}

	public static function query_levels( $query = null ): array {
		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, title FROM {$wpdb->prefix}gameengine_levels WHERE status = 'publish' ORDER BY priority ASC, id ASC LIMIT 200",
			ARRAY_A
		);

		return self::pairs_from_rows( $rows, 'title', 'id' );
	}

	public static function query_rewards( $query = null ): array {
		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, title, cost_points FROM {$wpdb->prefix}gameengine_rewards WHERE status = 'publish' ORDER BY title ASC LIMIT 200",
			ARRAY_A
		);

		$items = [];
		foreach ( (array) $rows as $row ) {
			$row    = (array) $row;
			$items[] = [
				'value' => (int) ( $row['id'] ?? 0 ),
				'label' => sprintf( '%s (%s)', (string) ( $row['title'] ?? '' ), (string) ( $row['cost_points'] ?? 0 ) ),
			];
		}

		return $items;
	}

	public static function query_payouts( $query = null ): array {
		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, user_id, points, status FROM {$wpdb->prefix}gameengine_pro_payouts ORDER BY id DESC LIMIT 100",
			ARRAY_A
		);

		$items = [];
		foreach ( (array) $rows as $row ) {
			$row    = (array) $row;
			$items[] = [
				'value' => (int) ( $row['id'] ?? 0 ),
				'label' => sprintf(
					'#%d — user #%d — %s pts (%s)',
					(int) ( $row['id'] ?? 0 ),
					(int) ( $row['user_id'] ?? 0 ),
					(string) ( $row['points'] ?? 0 ),
					(string) ( $row['status'] ?? '' )
				),
			];
		}

		return $items;
	}

	public static function query_posts( $query = null ): array {
		$search = is_array( $query ) ? (string) ( $query['search'] ?? '' ) : (string) $query;

		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => $search,
		] );

		$items = [];
		foreach ( (array) $posts as $post ) {
			$items[] = [
				'value' => (int) $post->ID,
				'label' => $post->post_title,
			];
		}

		return $items;
	}

	private static function pairs_from_rows( $rows, string $label_key, string $value_key, array $label_fallback = [] ): array {
		$items = [];

		foreach ( (array) $rows as $row ) {
			$row   = (array) $row;
			$label = (string) ( $row[ $label_key ] ?? '' );

			if ( '' === $label ) {
				foreach ( $label_fallback as $fallback ) {
					if ( ! empty( $row[ $fallback ] ) ) {
						$label = (string) $row[ $fallback ];
						break;
					}
				}
			}

			$items[] = [
				'value' => (int) ( $row[ $value_key ] ?? 0 ),
				'label' => $label,
			];
		}

		return $items;
	}
}
