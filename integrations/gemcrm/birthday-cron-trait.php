<?php

namespace Zaplane\Integrations\Gemcrm;

use Zaplane\Framework\Core\Automation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait BirthdayCronTrait {

	/**
	 * Schedule the daily birthday scan if not already scheduled.
	 * Safe to call on every boot — idempotent.
	 */
	public static function register_birthday_cron(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		if ( as_next_scheduled_action( 'zaplane_gemcrm_birthday_check', [], 'zaplane_birthday' ) ) {
			return;
		}

		$midnight = strtotime( 'tomorrow midnight' );
		as_schedule_recurring_action(
			$midnight,
			DAY_IN_SECONDS,
			'zaplane_gemcrm_birthday_check',
			[],
			'zaplane_birthday'
		);
	}

	/**
	 * Unschedule the birthday cron. Called on plugin deactivation.
	 */
	public static function unschedule_birthday_cron(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( 'zaplane_gemcrm_birthday_check', [], 'zaplane_birthday' );
	}

	/**
	 * ActionScheduler handler for 'zaplane_gemcrm_birthday_check'.
	 * Finds contacts with today's birthday (month+day) and enqueues batch jobs.
	 */
	public static function handle_birthday_check(): void {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return;
		}

		global $wpdb;

		$today_md   = gmdate( 'm-d' );
		$batch_size = 500;
		$offset     = 0;
		$prefix     = $wpdb->prefix;
		$table      = $prefix . 'gemcrm_contacts';

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table}
					WHERE dob IS NOT NULL
					AND DATE_FORMAT( dob, %s ) = %s
					LIMIT %d OFFSET %d",
					'%m-%d',
					$today_md,
					$batch_size,
					$offset
				)
			);

			if ( ! empty( $ids ) ) {
				as_enqueue_async_action(
					'zaplane_gemcrm_birthday_process_batch',
					[ 'contact_ids' => array_map( 'intval', $ids ) ],
					'zaplane_birthday'
				);
			}

			$offset += $batch_size;
		} while ( count( $ids ) === $batch_size );
	}

	/**
	 * ActionScheduler handler for 'zaplane_gemcrm_birthday_process_batch'.
	 * Applies year-dedup and fires the birthday trigger for each contact.
	 */
	public static function handle_birthday_batch( array $contact_ids ): void {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return;
		}

		$automation   = Automation::get_instance();
		$current_year = (int) gmdate( 'Y' );

		foreach ( $contact_ids as $contact_id ) {
			$contact_id = (int) $contact_id;
			if ( $contact_id <= 0 ) {
				continue;
			}

			if ( self::birthday_already_triggered( $contact_id, $current_year ) ) {
				continue;
			}

			$contact = \GemCrm\Database\Models\Contact::by_id( $contact_id );
			if ( ! $contact ) {
				continue;
			}

			if ( $automation ) {
				$automation->trigger_event( 'zaplane_gemcrm_contact_birthday', [
					'id'         => $contact['id'],
					'first_name' => $contact['first_name'] ?? '',
					'last_name'  => $contact['last_name'] ?? '',
					'email'      => $contact['email'] ?? '',
					'phone'      => $contact['phone'] ?? '',
					'meta'       => $contact['meta'] ?? [],
					'lists'      => $contact['lists'] ?? [],
					'tags'       => $contact['tags'] ?? [],
				] );
			} else {
				// Fallback: fire via WP action so Automation's trigger_router picks it up.
				do_action( 'zaplane_gemcrm_contact_birthday', $contact );
			}

			\GemCrm\Database\Models\Contact::update_meta(
				$contact_id,
				'_zaplane_birthday_triggered',
				wp_json_encode( [ 'year' => $current_year ] )
			);
		}
	}

	/**
	 * Returns true if the birthday trigger already fired for this contact this year.
	 */
	private static function birthday_already_triggered( int $contact_id, int $year ): bool {
		global $wpdb;

		$prefix = $wpdb->prefix;
		$table  = $prefix . 'gemcrm_contact_meta';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m_value FROM {$table} WHERE contact_id = %d AND m_key = %s LIMIT 1",
				$contact_id,
				'_zaplane_birthday_triggered'
			)
		);

		if ( ! $value ) {
			return false;
		}

		$data = json_decode( $value, true );
		return is_array( $data ) && (int) ( $data['year'] ?? 0 ) === $year;
	}
}
