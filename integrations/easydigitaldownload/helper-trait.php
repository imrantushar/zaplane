<?php
namespace Zaplane\Integrations\Easydigitaldownload;

trait HelperTrait {

	protected static function normalize_prefixed_status( string $status, string $prefix ): string {
		$status = sanitize_key( $status );
		if ( 0 === strpos( $status, $prefix ) ) {
			return substr( $status, strlen( $prefix ) );
		}

		return $status;
	}

	protected static function normalize_customer_status( string $status ): string {
		return self::normalize_prefixed_status( $status, 'edd_customer_' );
	}

	protected static function get_customer_status_config( array $config ): string {
		return (string) ( $config['customer_status'] ?? ( $config['status'] ?? '' ) );
	}

	protected static function normalize_discount_status( string $status ): string {
		return self::normalize_prefixed_status( $status, 'edd_discount_' );
	}

	protected static function get_discount_status_config( array $config ): string {
		return (string) ( $config['discount_status'] ?? ( $config['status'] ?? '' ) );
	}

	protected static function normalize_payment_status( string $status ): string {
		return self::normalize_prefixed_status( $status, 'edd_payment_' );
	}

	protected static function get_payment_status_config( array $config ): string {
		return (string) ( $config['payment_status'] ?? ( $config['status'] ?? '' ) );
	}

	protected static function normalize_download_status( string $status ): string {
		return self::normalize_prefixed_status( $status, 'edd_download_' );
	}

	protected static function get_download_status_config( array $config ): string {
		return (string) ( $config['download_status'] ?? ( $config['status'] ?? '' ) );
	}

	protected static function is_valid_download_post( $post ): bool {
		if ( ! is_object( $post ) || ! property_exists( $post, 'ID' ) ) {
			return false;
		}

		if ( ! property_exists( $post, 'post_type' ) || 'download' !== $post->post_type ) {
			return false;
		}

		return true;
	}

	protected static function extract_id( $value ): int {
		if ( is_object( $value ) ) {
			if ( isset( $value->ID ) ) {
				return (int) $value->ID;
			}
			if ( isset( $value->id ) ) {
				return (int) $value->id;
			}
		}

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		return 0;
	}

	protected static function payload_with_id( string $key, $id, array $extra = [] ) {
		$id = self::extract_id( $id );
		if ( ! $id ) {
			return false;
		}

		return array_merge( [ $key => $id ], $extra );
	}

	protected static function build_payment_status_payload( $payment_id, string $new_status, string $old_status = '' ) {
		if ( '' === $new_status ) {
			return false;
		}

		return self::payload_with_id('payment_id', $payment_id, [
			'new_status' => $new_status,
			'old_status' => $old_status,
		]);
	}

	protected static function matches_download_post( $post, $update, bool $expect_update ): bool {
		if ( ! self::is_valid_download_post( $post ) ) {
			return false;
		}

		return (bool) $update === $expect_update;
	}

	protected static function build_download_created_payload( $download_id, $post, $update ) {
		if ( ! self::matches_download_post( $post, $update, false ) ) {
			return false;
		}

		return self::payload_with_id('download_id', $download_id, [
			'data' => $post,
		]);
	}

	protected static function build_download_updated_payload( $download_id, $post, $update ) {
		if ( ! self::matches_download_post( $post, $update, true ) ) {
			return false;
		}

		return self::payload_with_id('download_id', $download_id, [
			'post' => $post,
		]);
	}

	protected static function build_download_deleted_payload( $download_id ) {
		$payload = self::payload_with_id( 'download_id', $download_id );
		if ( ! $payload ) {
			return false;
		}

		$post = get_post( $payload['download_id'] );
		if ( ! $post || ! self::is_valid_download_post( $post ) ) {
			return false;
		}

		$payload['post'] = $post;
		return $payload;
	}

	public static function query_downloads( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$args = [
			'post_type' => 'download',
			'numberposts' => $limit,
			's' => $search,
		];

		$posts = function_exists( 'get_posts' ) ? get_posts( $args ) : [];
		$items = [];
		foreach ( $posts as $post ) {
			$id = $post->ID ?? 0;
			if ( ! $id ) {
				continue;
			}
			$name = $post->post_title ?? '';
			if ( '' === $name ) {
				$name = 'Download #' . $id;
			}
			if ( ! self::matches_dynamic_search( $search, $name ) ) {
				continue;
			}
			$items[] = [
				'id' => (string) $id,
				'name' => $name,
			];
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_payments( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );
		$items = [];

		if ( function_exists( 'edd_get_payments' ) ) {
			$args = [
				'number' => $limit,
			];
			if ( '' !== $search ) {
				$args['s'] = $search;
			}
			$payments = edd_get_payments( $args );
			foreach ( $payments as $payment ) {
				$id = is_object( $payment ) && isset( $payment->ID ) ? (int) $payment->ID : (int) ( $payment->id ?? 0 );
				if ( ! $id ) {
					continue;
				}
				$email = $payment->email ?? '';
				$label = '#' . $id . ( $email ? ' - ' . $email : '' );
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'label' => $label,
				];
			}
		} elseif ( function_exists( 'get_posts' ) ) {
			$args = [
				'post_type' => 'edd_payment',
				'numberposts' => $limit,
				's' => $search,
			];
			$posts = get_posts( $args );
			foreach ( $posts as $post ) {
				$id = $post->ID ?? 0;
				if ( ! $id ) {
					continue;
				}
				$label = '#' . $id;
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'label' => $label,
				];
			}
		}//end if

		return array_slice( $items, 0, $limit );
	}

	public static function query_customers( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );
		$items = [];

		if ( function_exists( 'edd_get_customers' ) ) {
			$args = [
				'number' => $limit,
			];
			if ( '' !== $search ) {
				$args['search'] = $search;
			}
			$customers = edd_get_customers( $args );
			foreach ( $customers as $customer ) {
				$id = is_object( $customer ) ? (int) ( $customer->id ?? 0 ) : 0;
				if ( ! $id ) {
					continue;
				}
				$email = $customer->email ?? '';
				$name = trim( (string) ( $customer->name ?? '' ) );
					$label = '' !== $name ? $name : $email;
				if ( '' === $label ) {
					$label = 'Customer #' . $id;
				}
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'label' => $label,
					'email' => $email,
				];
			}
		}//end if

		return array_slice( $items, 0, $limit );
	}

	public static function query_discounts( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );
		$items = [];

		if ( function_exists( 'edd_get_discounts' ) ) {
			$args = [
				'number' => $limit,
			];
			if ( '' !== $search ) {
				$args['search'] = $search;
			}
			$discounts = edd_get_discounts( $args );
			foreach ( $discounts as $discount ) {
				$id = is_object( $discount ) ? (int) ( $discount->id ?? 0 ) : 0;
				if ( ! $id ) {
					continue;
				}
				$name = $discount->name ?? '';
				if ( '' === $name ) {
					$name = 'Discount #' . $id;
				}
				if ( ! self::matches_dynamic_search( $search, $name ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'name' => $name,
					'code' => $discount->code ?? '',
				];
			}
		}//end if

		return array_slice( $items, 0, $limit );
	}

	public static function query_users( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$args = [ 'number' => $limit ];
		if ( '' !== $search ) {
			$args['search'] = '*' . $search . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}

		$users = function_exists( 'get_users' ) ? get_users( $args ) : [];
		$items = [];

		foreach ( $users as $user ) {
			$id = $user->ID ?? 0;
			if ( ! $id ) {
				continue;
			}
				$label = ! empty( $user->display_name ) ? $user->display_name : ( $user->user_email ?? '' );
			if ( '' === $label ) {
				$label = 'User #' . $id;
			}
			if ( ! self::matches_dynamic_search( $search, $label ) ) {
				continue;
			}
			$items[] = [
				'id' => (string) $id,
				'label' => $label,
				'email' => $user->user_email ?? '',
			];
		}

		return array_slice( $items, 0, $limit );
	}

	protected static function normalize_dynamic_limit( array $q ): int {
		$limit = (int) ( $q['limit'] ?? 20 );
		if ( $limit < 1 ) {
			$limit = 20;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		return $limit;
	}

	protected static function normalize_dynamic_search( array $q ): string {
		return trim( (string) ( $q['search'] ?? '' ) );
	}

	protected static function matches_dynamic_search( string $search, string $value ): bool {
		if ( '' === $search ) {
			return true;
		}
		return stripos( $value, $search ) !== false;
	}
}
