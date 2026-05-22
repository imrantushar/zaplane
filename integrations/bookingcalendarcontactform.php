<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Bookingcalendarcontactform extends IntegrationBase {


	public static function get_slug(): string {
		return 'bookingcalendarcontactform';
	}

	public static function get_name(): string {
		return 'Booking Calendar Contact Form';
	}

	public static function get_icon(): string {
		return 'bookingcalendarcontactform.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'dexbccf_process_data'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$data = $args[0] ?? [];

				if ( empty( $data ) ) {
					return false;
				}

				return [
					'form_id'     => $data['formid'] ?? '',
					'item_number' => $data['itemnumber'] ?? '',
					'start_date'  => $data['startdate'] ?? '',
					'end_date'    => $data['enddate'] ?? '',
					'total_cost'  => $data['totalcost'] ?? '',
					'discount'    => $data['discount'] ?? '',
					'coupon'      => $data['coupon'] ?? '',
					'service'     => $data['service'] ?? '',
					'email'       => $data['email'] ?? '',
					'subject'     => $data['subject'] ?? '',
					'message'     => $data['message'] ?? '',
				];
		}//end switch

		return false;
	}
}
