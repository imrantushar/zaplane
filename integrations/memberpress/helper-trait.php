<?php
namespace Zaplane\Integrations\Memberpress;

trait HelperTrait {

	protected static function to_bool( $value ): bool {
		return in_array( $value, [ true, 1, '1', 'true', 'yes', 'on' ], true );
	}

	protected static function decode_event_args( $args ) {
		if ( is_string( $args ) ) {
			$decoded = json_decode( $args, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}
		}

		return $args;
	}

	protected static function get_transaction_status_options(): array {
		return [
			[
				'label' => 'Pending',
				'value' => 'pending'
			],
			[
				'label' => 'Complete',
				'value' => 'complete'
			],
			[
				'label' => 'Confirmed',
				'value' => 'confirmed'
			],
			[
				'label' => 'Failed',
				'value' => 'failed'
			],
			[
				'label' => 'Refunded',
				'value' => 'refunded'
			],
		];
	}

	protected static function get_subscription_status_options(): array {
		return [
			[
				'label' => 'Active',
				'value' => 'active'
			],
			[
				'label' => 'Pending',
				'value' => 'pending'
			],
			[
				'label' => 'Suspended',
				'value' => 'suspended'
			],
			[
				'label' => 'Cancelled',
				'value' => 'cancelled'
			],
		];
	}

	protected static function get_pricing_display_options(): array {
		return [
			[
				'label' => 'Auto',
				'value' => 'auto'
			],
			[
				'label' => 'Custom',
				'value' => 'custom'
			],
			[
				'label' => 'None',
				'value' => 'none'
			],
		];
	}

	protected static function get_membership_period_type_options(): array {
		return [
			[
				'label' => 'Weeks',
				'value' => 'weeks'
			],
			[
				'label' => 'Months',
				'value' => 'months'
			],
			[
				'label' => 'Years',
				'value' => 'years'
			],
			[
				'label' => 'Lifetime',
				'value' => 'lifetime'
			],
		];
	}

	protected static function get_post_status_options(): array {
		return [
			[
				'label' => 'Publish',
				'value' => 'publish'
			],
			[
				'label' => 'Draft',
				'value' => 'draft'
			],
			[
				'label' => 'Private',
				'value' => 'private'
			],
		];
	}

	protected static function get_yes_no_options(): array {
		return [
			[
				'label' => 'Yes',
				'value' => '1'
			],
			[
				'label' => 'No',
				'value' => '0'
			],
		];
	}

	protected static function get_expire_type_options(): array {
		return [
			[
				'label' => 'None',
				'value' => 'none'
			],
			[
				'label' => 'Delay',
				'value' => 'delay'
			],
			[
				'label' => 'Fixed',
				'value' => 'fixed'
			],
		];
	}

	protected static function get_expire_unit_options(): array {
		return [
			[
				'label' => 'Days',
				'value' => 'days'
			],
			[
				'label' => 'Weeks',
				'value' => 'weeks'
			],
			[
				'label' => 'Months',
				'value' => 'months'
			],
			[
				'label' => 'Years',
				'value' => 'years'
			],
		];
	}

	protected static function get_period_type_options(): array {
		return [
			[
				'label' => 'Days',
				'value' => 'days'
			],
			[
				'label' => 'Weeks',
				'value' => 'weeks'
			],
			[
				'label' => 'Months',
				'value' => 'months'
			],
			[
				'label' => 'Years',
				'value' => 'years'
			],
		];
	}

	public static function query_memberships( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$cpt = 'memberpressproduct';
		if ( class_exists( 'MeprProduct' ) && ! empty( \MeprProduct::$cpt ) ) {
			$cpt = \MeprProduct::$cpt;
		}

		$args = [
			'post_type' => $cpt,
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
				$name = 'Membership #' . $id;
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

	public static function query_subscriptions( $q ): array {
		if ( ! class_exists( 'MeprSubscription' ) ) {
			return [];
		}

		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$items = [];
		$subs = \MeprSubscription::get_all();
		if ( is_array( $subs ) ) {
			foreach ( $subs as $sub ) {
				$id = is_object( $sub ) ? (int) ( $sub->id ?? 0 ) : 0;
				if ( ! $id ) {
					continue;
				}
				$label = 'Subscription #' . $id;
				if ( isset( $sub->user_id ) ) {
					$label .= ' - User ' . $sub->user_id;
				}
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'label' => $label,
				];
				if ( count( $items ) >= $limit ) {
					break;
				}
			}
		}//end if

		return array_slice( $items, 0, $limit );
	}

	public static function query_transactions( $q ): array {
		if ( ! class_exists( 'MeprTransaction' ) ) {
			return [];
		}

		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$items = [];
		$limit_clause = '0,' . $limit;
		$txns = \MeprTransaction::get_all( '', $limit_clause );
		if ( is_array( $txns ) ) {
			foreach ( $txns as $txn ) {
				$id = is_object( $txn ) ? (int) ( $txn->id ?? 0 ) : 0;
				if ( ! $id ) {
					continue;
				}
				$label = 'Transaction #' . $id;
				if ( isset( $txn->user_id ) ) {
					$label .= ' - User ' . $txn->user_id;
				}
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'label' => $label,
				];
				if ( count( $items ) >= $limit ) {
					break;
				}
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

	public static function query_gateways( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$items = [];
		$seen = [];

		$items[] = [
			'id' => 'manual',
			'label' => 'Manual',
		];
		$seen['manual'] = true;

		if ( class_exists( 'MeprOptions' ) && method_exists( '\MeprOptions', 'fetch' ) ) {
			$options = \MeprOptions::fetch();
			$integration_ids = [];

			if ( is_object( $options ) && isset( $options->integrations ) && is_array( $options->integrations ) ) {
				$integration_ids = array_keys( $options->integrations );
			}

			foreach ( $integration_ids as $gateway_id ) {
				$gateway_id = (string) $gateway_id;
				if ( '' === $gateway_id || isset( $seen[ $gateway_id ] ) ) {
					continue;
				}

				$label = $gateway_id;
				if ( is_object( $options ) && method_exists( $options, 'payment_method' ) ) {
					$method = $options->payment_method( $gateway_id );
					if ( is_object( $method ) ) {
						$method_label = $method->label ?? $method->name ?? '';
						if ( '' !== $method_label ) {
							$label = $method_label . ' (' . $gateway_id . ')';
						}
					}
				}

				$seen[ $gateway_id ] = true;
				$items[] = [
					'id' => $gateway_id,
					'label' => $label,
				];
			}//end foreach
		}//end if

		if ( '' !== $search ) {
			$items = array_values( array_filter( $items, function ( $item ) use ( $search ) {
				return self::matches_dynamic_search( $search, (string) ( $item['label'] ?? '' ) )
					|| self::matches_dynamic_search( $search, (string) ( $item['id'] ?? '' ) );
			}));
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
