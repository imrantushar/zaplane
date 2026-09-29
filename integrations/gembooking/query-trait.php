<?php

namespace Zaplane\Integrations\Gembooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QueryTrait
{

    public static function query_booking( $q = null ): array {
		if ( ! class_exists( '\\GemBooking\\Models\\BookingModels' ) ) {
			return [];
		}

		$model = new \GemBooking\Models\BookingModels();

		$search = '';
		$booking_id = 0;

		if ( is_array( $q ) ) {
			$search     = sanitize_text_field( (string) ( $q['search'] ?? '' ) );
			$booking_id = absint( $q['booking_id'] ?? 0 );
		} elseif ( is_numeric( $q ) ) {
			$booking_id = absint( $q );
		} elseif ( is_string( $q ) ) {
			$search = sanitize_text_field( $q );
		}

		/*
		 * If a specific booking ID is requested,
		 * return only that booking.
		 */
		if ( $booking_id ) {
			$booking = $model->find_by_id( $booking_id );

			if ( ! is_array( $booking ) || empty( $booking ) ) {
				return [];
			}

			return [
				[
					'value' => $booking_id,
					'label' => self::booking_query_label( $booking, $booking_id ),
				],
			];
		}

		/*
		 * Load booking list.
		 */
		$result = $model->get_list(
			1,
			100,
			$search,
			''
		);

		if ( ! is_array( $result ) ) {
			return [];
		}

		/*
		 * GemBooking may return rows directly or inside "rows"/"data".
		 */
		$rows = $result;

		if ( isset( $result['rows'] ) && is_array( $result['rows'] ) ) {
			$rows = $result['rows'];
		} elseif ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			$rows = $result['data'];

			if ( isset( $result['data']['rows'] ) && is_array( $result['data']['rows'] ) ) {
				$rows = $result['data']['rows'];
			}
		}

		$options = [];

		foreach ( $rows as $item ) {
			$row = is_object( $item ) ? get_object_vars( $item ) : ( is_array( $item ) ? $item : [] );
			if ( empty( $row ) ) {
				continue;
			}

			$id = absint(
				$row['id']
				?? $row['booking_id']
				?? $row['booked_id']
				?? 0
			);

			if ( ! $id ) {
				continue;
			}

			$options[] = [
				'value' => $id,
				'label' => self::booking_query_label( $row, $id ),
			];
		}

		return $options;
	}

	private static function booking_query_label( array $booking, $id ): string {
		$name = trim(
			(string) (
				$booking['name']
				?? $booking['customer_name']
				?? $booking['first_name']
				?? ''
			)
		);

		$email = trim(
			(string) (
				$booking['email']
				?? $booking['customer_email']
				?? ''
			)
		);

		$status = trim(
			(string) ( $booking['status'] ?? '' )
		);

		$label = '#' . absint( $id );

		if ( '' !== $name ) {
			$label .= ' - ' . $name;
		} elseif ( '' !== $email ) {
			$label .= ' - ' . $email;
		}

		if ( '' !== $email && '' !== $name ) {
			$label .= ' (' . $email . ')';
		}

		if ( '' !== $status ) {
			$label .= ' - ' . ucfirst( $status );
		}

		return $label;
	}

	public static function query_bookables( $q = null ): array {
		$type = is_array( $q ) ? sanitize_key( (string) ( $q['booking_type'] ?? '' ) ) : '';
		$post_type_map = [
			'service'  => self::bookable_post_type( 'service', 'gembk_service' ),
			'event'    => self::bookable_post_type( 'event', 'gembk_event' ),
			'resource' => self::bookable_post_type( 'resource', 'gembk_resource' ),
			'package'  => self::bookable_post_type( 'package', 'gembk_package' ),
		];
		$post_type = $post_type_map[ $type ] ?? $post_type_map['service'];

		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		return array_map( static fn( $p ) => [ 'value' => $p->ID, 'label' => $p->post_title ], $posts );
	}

	public static function query_packages( $q = null ): array {
		$search = is_array( $q )
			? sanitize_text_field( (string) ( $q['search'] ?? '' ) )
			: ( is_string( $q ) ? sanitize_text_field( $q ) : '' );

		$args = [
			'post_type'      => self::bookable_post_type( 'package', 'gembk_package' ),
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$posts = get_posts( $args );

		return array_map( static fn( $p ) => [ 'value' => $p->ID, 'label' => $p->post_title ], $posts );
	}

	public static function query_purchases( $q = null ): array {
		global $wpdb;
		$search = is_array( $q )
			? sanitize_text_field( (string) ( $q['search'] ?? '' ) )
			: ( is_string( $q ) ? sanitize_text_field( $q ) : '' );

		$table = $wpdb->prefix . 'gembk_package_purchases';

		$sql = "SELECT id, package_id, user_id, email, status FROM {$table} ORDER BY id DESC LIMIT 100";
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$options = [];
		foreach ( $rows as $row ) {
			$label = 'Purchase #' . $row['id'];
			if ( $row['email'] ) {
				$label .= ' - ' . $row['email'];
			} elseif ( $row['user_id'] ) {
				$user = get_user_by( 'id', $row['user_id'] );
				if ( $user ) {
					$label .= ' - ' . $user->display_name . ' (' . $user->user_email . ')';
				}
			}
			$label .= ' - Package ID: ' . $row['package_id'] . ' - Status: ' . ucfirst( $row['status'] );

			if ( '' !== $search && false === stripos( $label, $search ) ) {
				continue;
			}

			$options[] = [
				'value' => (int) $row['id'],
				'label' => $label,
			];
		}

		return $options;
	}

	public static function query_customers( $q = null ): array {
		$search = is_array( $q ) ? sanitize_text_field( (string) ( $q['search'] ?? '' ) ) : '';

		$users = get_users( [
			'search'         => $search ? '*' . $search . '*' : '',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'number'         => 20,
			'role__in'       => [ 'subscriber', 'customer' ],
		] );

		return array_map( static fn( $u ) => [
			'value' => $u->ID,
			'label' => $u->display_name . ' (' . $u->user_email . ')',
		], $users );
	}

	public static function query_timezones( $q = null ): array {
		$search = is_array( $q ) ? strtolower( sanitize_text_field( (string) ( $q['search'] ?? '' ) ) ) : '';

		// Use caching for timezone data since it doesn't change frequently
		$cache_key = 'zaplane_gembooking_timezones';
		$zones = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key ) : false;

		if ( false === $zones ) {
			$zones = [];

			// UTC first.
			$zones[] = [
				'value' => 'UTC',
				'label' => '(GMT+0:00) UTC',
				'offset' => 0,
			];

			foreach ( \DateTimeZone::listIdentifiers( \DateTimeZone::ALL ) as $identifier ) {
				if ( 'UTC' === $identifier ) {
					continue;
				}

				try {
					$tz     = new \DateTimeZone( $identifier );
					$offset = $tz->getOffset( new \DateTime( 'now', $tz ) );
				} catch ( \Throwable $e ) {
					continue;
				}

				$hours   = intdiv( abs( $offset ), 3600 );
				$minutes = intdiv( abs( $offset ) % 3600, 60 );
				$sign    = $offset < 0 ? '-' : '+';

				$zones[] = [
					'value'  => $identifier,
					'label'  => sprintf( '(GMT%s%d:%02d) %s', $sign, $hours, $minutes, $identifier ),
					'offset' => $offset,
				];
			}

			// Cache for 1 hour if WordPress cache functions are available
			// (HOUR_IN_SECONDS is a constant, so check with defined()).
			if ( function_exists( 'wp_cache_set' ) && defined( 'HOUR_IN_SECONDS' ) ) {
				wp_cache_set( $cache_key, $zones, '', HOUR_IN_SECONDS );
			}
		}

		if ( '' !== $search ) {
			$zones = array_values( array_filter( $zones, static function ( $zone ) use ( $search ) {
				return false !== strpos( strtolower( $zone['label'] ), $search );
			} ) );
		}

		// Drop the internal sort key before returning.
		return array_map( static fn( $zone ) => [
			'value' => $zone['value'],
			'label' => $zone['label'],
		], $zones );
	}

	public static function query_services( $q = null ): array {
		return self::bookable_options( 'service', $q );
	}

	public static function query_events( $q = null ): array {
		return self::bookable_options( 'event', $q );
	}

	public static function query_resources( $q = null ): array {
		return self::bookable_options( 'resource', $q );
	}

	/**
	 * Options for the service/event/resource dropdowns. Includes every
	 * status (drafts and trash too) so items can be re-published or
	 * restored, not just the published ones.
	 */
	private static function bookable_options( string $kind, $q = null ): array {
		$search = '';
		$id     = 0;

		if ( is_array( $q ) ) {
			$search = sanitize_text_field( (string) ( $q['search'] ?? '' ) );
			$id     = absint( $q['bookable'] ?? 0 );
		} elseif ( is_numeric( $q ) ) {
			$id = absint( $q );
		} elseif ( is_string( $q ) ) {
			$search = sanitize_text_field( $q );
		}

		$args = [
			'post_type'      => self::bookable_type_for( $kind ),
			'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future', 'trash' ],
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $id ) {
			$args['include'] = [ $id ];
		} elseif ( '' !== $search ) {
			$args['s'] = $search;
		}

		return array_map(
			static function ( $p ) {
				$title = '' !== trim( (string) $p->post_title ) ? $p->post_title : '(no title)';
				$label = sprintf( '%s (#%d)', $title, $p->ID );

				if ( 'publish' !== $p->post_status ) {
					$label .= ' - ' . ucfirst( $p->post_status );
				}

				return [ 'value' => $p->ID, 'label' => $label ];
			},
			get_posts( $args )
		);
	}
}
