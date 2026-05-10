<?php

namespace Zaplane\Modules\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCartHelper {

	const SETTINGS_OPTION = 'zaplane_abandoned_cart_settings';

	public static function get_default_settings(): array {
		return [
			'status'                                         => false,
			'cart_off_time'                                  => 30,
			'mark_as_lost_after_minutes'                     => 10080,
			'cool_off_period'                                => 10080,
			'status_of_new_contact'                          => 'transactional',
			'mark_as_recovered_when_order_status_changed_to' => [ 'processing', 'completed' ],
			'gdpr_consent_in_woo_checkout_page'              => false,
			'gdpr_msg'                                       => 'By continuing, you agree that we may save your cart data.',
			'disabled_user_roles'                            => [],
			'abandoned_list'                                 => [],
			'abandoned_tags'                                 => [],
			'lost_list'                                      => [],
			'lost_tags'                                      => [],
		];
	}

	public static function get_settings(): array {
		$saved = get_option( self::SETTINGS_OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return array_merge( self::get_default_settings(), $saved );
	}

	public static function save_settings( array $data ): bool {
		$defaults = self::get_default_settings();
		$sanitized = [];

		$sanitized['status']                                         = ! empty( $data['status'] );
		$sanitized['cart_off_time']                                  = absint( $data['cart_off_time'] ?? $defaults['cart_off_time'] );
		$sanitized['mark_as_lost_after_minutes']                     = absint( $data['mark_as_lost_after_minutes'] ?? $defaults['mark_as_lost_after_minutes'] );
		$sanitized['cool_off_period']                                = absint( $data['cool_off_period'] ?? $defaults['cool_off_period'] );
		$sanitized['status_of_new_contact']                          = sanitize_text_field( $data['status_of_new_contact'] ?? $defaults['status_of_new_contact'] );
		$sanitized['mark_as_recovered_when_order_status_changed_to'] = array_map( 'sanitize_text_field', (array) ( $data['mark_as_recovered_when_order_status_changed_to'] ?? $defaults['mark_as_recovered_when_order_status_changed_to'] ) );
		$sanitized['gdpr_consent_in_woo_checkout_page']              = ! empty( $data['gdpr_consent_in_woo_checkout_page'] );
		$sanitized['gdpr_msg']                                       = sanitize_text_field( $data['gdpr_msg'] ?? $defaults['gdpr_msg'] );
		$sanitized['disabled_user_roles']                            = array_map( 'sanitize_text_field', (array) ( $data['disabled_user_roles'] ?? [] ) );
		$sanitized['abandoned_list']                                 = array_map( 'absint', (array) ( $data['abandoned_list'] ?? [] ) );
		$sanitized['abandoned_tags']                                 = array_map( 'absint', (array) ( $data['abandoned_tags'] ?? [] ) );
		$sanitized['lost_list']                                      = array_map( 'absint', (array) ( $data['lost_list'] ?? [] ) );
		$sanitized['lost_tags']                                      = array_map( 'absint', (array) ( $data['lost_tags'] ?? [] ) );

		return update_option( self::SETTINGS_OPTION, $sanitized );
	}

	public static function is_enabled(): bool {
		$settings = self::get_settings();
		return ! empty( $settings['status'] );
	}

	public static function will_track_for_role( string $role ): bool {
		$settings = self::get_settings();
		$disabled = $settings['disabled_user_roles'] ?? [];
		return ! in_array( $role, $disabled, true );
	}

	public static function is_within_cool_off( int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		$settings      = self::get_settings();
		$cool_off_mins = absint( $settings['cool_off_period'] );
		if ( ! $cool_off_mins ) {
			return false;
		}

		// WC_Order_Query uses '>TIMESTAMP' syntax for date_created comparisons.
		$since = time() - ( $cool_off_mins * MINUTE_IN_SECONDS );

		$recent_orders = wc_get_orders( [
			'customer'     => $user_id,
			'status'       => [ 'wc-processing', 'wc-completed' ],
			'date_created' => '>' . $since,
			'limit'        => 1,
			'return'       => 'ids',
		] );

		return ! empty( $recent_orders );
	}

	public static function get_gemcrm_tags(): array {
		if ( ! class_exists( 'GemCrm\Database\Models\Tag' ) ) {
			return [];
		}
		try {
			$tags = \GemCrm\Database\Models\Tag::index();
			if ( ! $tags ) {
				return [];
			}
			return array_map( fn( $t ) => [ 'id' => $t['id'], 'title' => $t['title'] ], (array) $tags );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	public static function get_gemcrm_lists(): array {
		if ( ! class_exists( 'GemCrm\Database\Models\ListModel' ) ) {
			return [];
		}
		try {
			$lists = \GemCrm\Database\Models\ListModel::index();
			if ( ! $lists ) {
				return [];
			}
			return array_map( fn( $l ) => [ 'id' => $l['id'], 'title' => $l['title'] ], (array) $lists );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	public static function create_or_update_gemcrm_contact( string $email, string $first_name, string $last_name, string $status ): ?int {
		if ( ! class_exists( 'GemCrm\Database\Models\Contact' ) ) {
			return null;
		}
		try {
			if ( \GemCrm\Database\Models\Contact::email_exists( $email ) ) {
				$contact = \GemCrm\Database\Models\Contact::ins()->qb()->where( 'ct.email', '=', $email )->first();
				return $contact ? (int) $contact['id'] : null;
			}
			$result = \GemCrm\Database\Models\Contact::create( [
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'status'     => $status,
			] );
			return $result ? (int) $result['id'] : null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public static function apply_gemcrm_tags( int $contact_id, array $tag_ids ): void {
		if ( ! class_exists( 'GemCrm\Database\Models\Tag' ) || ! $contact_id ) {
			return;
		}
		try {
			\GemCrm\Database\Models\Tag::attach( $contact_id, $tag_ids );
		} catch ( \Exception $e ) {
			// silent
		}
	}

	public static function apply_gemcrm_lists( int $contact_id, array $list_ids ): void {
		if ( ! class_exists( 'GemCrm\Database\Models\ListModel' ) || ! $contact_id ) {
			return;
		}
		try {
			\GemCrm\Database\Models\ListModel::attach( $contact_id, $list_ids );
		} catch ( \Exception $e ) {
			// silent
		}
	}

	public static function remove_gemcrm_abandoned_tags_lists( int $contact_id, array $settings ): void {
		self::apply_gemcrm_tags( $contact_id, [] );
		self::apply_gemcrm_lists( $contact_id, [] );
	}
}
