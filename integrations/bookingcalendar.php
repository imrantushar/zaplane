<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class BookingCalendar extends IntegrationBase {

	public static function get_slug(): string {
		return 'bookingcalendar';
	}

	public static function get_name(): string {
		return 'Booking Calendar';
	}

	public static function get_icon(): string {
		return 'booking-calendar.svg';
	}

	public static function get_triggers(): array {
		return [
			'dexbccf_process_data' => [
				'label' => 'Booking Calendar Contact Form',
				'hook'  => 'dexbccf_process_data',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'dexbccf_process_data':
				$fields = $args[1] ?? [];
				$result = [];
				foreach ( $fields as $field ) {
					$label          = $field['label'] ?? $field['name'] ?? '';
					$result[$label] = $field['value'] ?? '';
				}
				return $result;
		}//end switch

		return false;
	}

	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input,
		];
	}
}
