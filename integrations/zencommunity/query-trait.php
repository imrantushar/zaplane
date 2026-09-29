<?php
namespace Zaplane\Integrations\Zencommunity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic option lookups referenced by trigger/action schema fields via
 * `dynamic => [ integration => zencommunity, query => … ]`.
 *
 * Every query is guarded: when the ZenCommunity plugin is inactive the lookup
 * returns an empty list instead of fataling, so the node editor shows an empty
 * select rather than a broken request.
 */
trait QueryTrait {

	public static function query_spaces( $query = [] ): array {
		if ( ! class_exists( \ZenCommunity\Database\Models\Group::class ) ) {
			return [];
		}

		try {
			$rows = \ZenCommunity\Database\Models\Group::ins()->qb()
				->select( [ 's.id', 's.name' ] )
				->where_not_null( 's.category_id' )
				->order_by( 's.name', 'ASC' )
				->limit( 200 )
				->get();

			$out = [];
			foreach ( (array) $rows as $row ) {
				$row = (array) $row;
				$id  = (int) ( $row['id'] ?? 0 );
				if ( ! $id ) {
					continue;
				}
				$out[] = [
					'value' => $id,
					'label' => (string) ( $row['name'] ?? ( '#' . $id ) ),
				];
			}

			return $out;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	public static function query_users( $query = [] ): array {
		if ( ! function_exists( 'get_users' ) ) {
			return [];
		}

		try {
			$users = get_users( [
				'fields'  => [ 'ID', 'display_name' ],
				'number'  => 200,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			] );

			$out = [];
			foreach ( (array) $users as $user ) {
				if ( is_object( $user ) ) {
					$id    = (int) ( $user->ID ?? 0 );
					$label = (string) ( $user->display_name ?? '' );
				} elseif ( is_array( $user ) ) {
					$id    = (int) ( $user['ID'] ?? 0 );
					$label = (string) ( $user['display_name'] ?? '' );
				} else {
					continue;
				}

				if ( ! $id ) {
					continue;
				}

				$out[] = [
					'value' => $id,
					'label' => '' !== $label ? $label : ( '#' . $id ),
				];
			}

			return $out;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/** Global community roles (support agent, developer, custom globals) — used by change_community_role. */
	public static function query_roles( $query = [] ): array {
		if ( ! class_exists( \ZenCommunity\Classes\RoleManager::class ) ) {
			return [];
		}

		try {
			$roles = \ZenCommunity\Classes\RoleManager::roles();

			$out = [];
			foreach ( $roles as $key => $role ) {
				$role = (array) $role;
				$is_global = true === ( $role['is_global'] ?? false )
					|| in_array( $key, \ZenCommunity\Classes\RoleManager::BUILT_IN_GLOBAL_ROLES, true );

				if ( ! $is_global ) {
					continue;
				}

				$out[] = [
					'value' => (string) $key,
					'label' => (string) ( $role['label'] ?? $key ),
				];
			}

			return $out;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/** Group-scoped roles (admin, moderator, member, customs) — used by space membership actions. */
	public static function query_group_roles( $query = [] ): array {
		if ( ! class_exists( \ZenCommunity\Classes\RoleManager::class ) ) {
			return [];
		}

		try {
			$keys = \ZenCommunity\Classes\RoleManager::group_role_keys();
			$roles = \ZenCommunity\Classes\RoleManager::roles();

			$out = [];
			foreach ( $keys as $key ) {
				$out[] = [
					'value' => (string) $key,
					'label' => (string) ( $roles[ $key ]['label'] ?? $key ),
				];
			}

			return $out;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	public static function query_events( $query = [] ): array {
		if ( ! class_exists( \ZenCommunity\Database\Models\Event::class ) ) {
			return [];
		}

		try {
			$rows = \ZenCommunity\Database\Models\Event::ins()->qb()
				->select( [ 'evt.id', 'evt.title', 'evt.start_at' ] )
				->where( 'evt.status', '=', 'active' )
				->order_by( 'evt.start_at', 'DESC' )
				->limit( 200 )
				->get();

			$out = [];
			foreach ( (array) $rows as $row ) {
				$row = (array) $row;
				$id  = (int) ( $row['id'] ?? 0 );
				if ( ! $id ) {
					continue;
				}
				$title = (string) ( $row['title'] ?? '' );
				$out[] = [
					'value' => $id,
					'label' => '' !== $title ? $title : ( '#' . $id ),
				];
			}

			return $out;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	public static function query_feeds( $query = [] ): array {
		if ( ! class_exists( \ZenCommunity\Database\Models\Feed::class ) ) {
			return [];
		}

		try {
			$rows = \ZenCommunity\Database\Models\Feed::ins()->qb()
				->select( [ 'fd.id', 'fd.title', 'fd.content' ] )
				->order_by( 'fd.id', 'DESC' )
				->limit( 200 )
				->get();

			$out = [];
			foreach ( (array) $rows as $row ) {
				$row = (array) $row;
				$id  = (int) ( $row['id'] ?? 0 );
				if ( ! $id ) {
					continue;
				}

				$title   = (string) ( $row['title'] ?? '' );
				$content = trim( wp_strip_all_tags( (string) ( $row['content'] ?? '' ) ) );
				if ( '' === $title ) {
					$title = function_exists( 'mb_substr' ) ? mb_substr( $content, 0, 50 ) : substr( $content, 0, 50 );
				}

				$out[] = [
					'value' => $id,
					'label' => '' !== $title ? $title : ( '#' . $id ),
				];
			}

			return $out;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	private static function query_ticket_rows( string $kind ): array {
		try {
			if ( 'product' === $kind ) {
				$class = \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Product::class;
				if ( ! class_exists( $class ) ) { return []; }
				$data = $class::index( [ 'page' => 1, 'per_page' => 100, 'status' => 'published', 'order' => 'asc', 'order_by' => 'name' ] );
			} else {
				$class = \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Label::class;
				if ( ! class_exists( $class ) ) { return []; }
				$data = $class::index( [ 'type' => $kind, 'page' => 1, 'per_page' => 100, 'status' => 'published', 'order' => 'asc', 'order_by' => 'name' ] );
			}
			$out = [];
			foreach ( (array) ( $data['records'] ?? [] ) as $row ) {
				$id = absint( $row['id'] ?? 0 );
				if ( $id ) { $out[] = [ 'value' => $id, 'label' => (string) ( $row['name'] ?? ( '#' . $id ) ) ]; }
			}
			return $out;
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	public static function query_ticket_types( $query = [] ): array { return self::query_ticket_rows( 'type' ); }
	public static function query_ticket_priorities( $query = [] ): array { return self::query_ticket_rows( 'priority' ); }
	public static function query_ticket_products( $query = [] ): array { return self::query_ticket_rows( 'product' ); }
}
