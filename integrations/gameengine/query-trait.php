<?php

namespace Zaplane\Integrations\Gameengine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QueryTrait {

	public static function query_point_types( $query = null ): array {
		if ( class_exists( '\GameEngine\Classes\PointsManager' ) ) {
			// plural_name is optional — a type saved with only a name (or only
			// a slug) still has to read as something, so fall back the same
			// way GameEngine's own get_point_type_label() does.
			return self::pairs_from_rows(
				\GameEngine\Classes\PointsManager::get_point_types(),
				'plural_name',
				'id',
				[ 'name', 'slug' ]
			);
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

	public static function query_events( $query = null ): array {
		if ( ! class_exists( '\GameEngine\Classes\TriggerRegistry' ) ) {
			return [];
		}

		$items = [];
		foreach ( (array) \GameEngine\Classes\TriggerRegistry::get_all_triggers() as $key => $config ) {
			$config  = (array) $config;
			$items[] = [
				'value' => (string) $key,
				'label' => (string) ( $config['label'] ?? $key ),
			];
		}

		return $items;
	}

	public static function query_badges( $query = null ): array {
		$posts = get_posts( [
			'post_type'      => 'ge_badge',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
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

	public static function query_logs( $query = null ): array {
		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, message, trigger_key FROM {$wpdb->prefix}gameengine_logs ORDER BY id DESC LIMIT 200",
			ARRAY_A
		);

		$items = [];
		foreach ( (array) $rows as $row ) {
			$row   = (array) $row;
			$id    = (int) ( $row['id'] ?? 0 );
			$label = (string) ( $row['message'] ?? '' );

			if ( '' === $label ) {
				$label = (string) ( $row['trigger_key'] ?? '' );
			}

			$items[] = [
				'value' => $id,
				'label' => sprintf( '#%d — %s', $id, '' !== $label ? $label : 'log entry' ),
			];
		}//end foreach

		return $items;
	}

	public static function query_wheels( $query = null ): array {
		return self::crud_pairs( 'gameengine_lucky_wheels', 'name' );
	}

	public static function query_seasons( $query = null ): array {
		return self::crud_pairs( 'gameengine_pro_seasons', 'name' );
	}

	public static function query_webhooks( $query = null ): array {
		return self::crud_pairs( 'gameengine_pro_webhooks', 'name' );
	}

	private static function crud_pairs( string $table, string $column ): array {
		if ( ! self::pro_active() ) {
			return [];
		}

		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, {$column} AS label FROM {$wpdb->prefix}{$table} ORDER BY id DESC LIMIT 200",
			ARRAY_A
		);

		return self::pairs_from_rows( $rows, 'label', 'id', [] );
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

			$value = (int) ( $row[ $value_key ] ?? 0 );

			// A blank row would render as an untickable option, so name it
			// after its id rather than leaving the label empty.
			if ( '' === $label && $value > 0 ) {
				$label = '#' . $value;
			}

			$items[] = [
				'value' => $value,
				'label' => $label,
			];
		}//end foreach

		return $items;
	}
}
