<?php
namespace Zaplane\Integrations\Easydigitaldownload;

trait PaymentActionsTrait {

	protected static function action_update_payment_status( array $config, array $input ): array {
		if ( ! function_exists( 'edd_update_payment_status' ) || ! function_exists( 'edd_get_payment' ) ) {
			return self::action_error( 'Easy Digital Downloads is not available', $input );
		}

		$payment_id = (int) ( $config['payment_id'] ?? 0 );
		$status = $config['status'] ?? '';

		if ( ! $payment_id || $status === '' ) {
			return self::action_error( 'Payment ID and status are required', $input );
		}

		$payment = edd_get_payment( $payment_id );
		if ( ! $payment ) {
			return self::action_error( 'Payment not found', $input );
		}

		$updated = edd_update_payment_status( $payment_id, $status );

		if ( ! $updated ) {
			return self::action_error( 'Failed to update payment status', $input );
		}

		return self::action_success(array_merge($input, [
			'payment_id' => $payment_id,
			'payment_status' => $status,
		]));
	}

	protected static function action_add_payment_note( array $config, array $input ): array {
		if ( ! function_exists( 'edd_insert_payment_note' ) ) {
			return self::action_error( 'Easy Digital Downloads is not available', $input );
		}

		$payment_id = (int) ( $config['payment_id'] ?? 0 );
		$note = trim( $config['note'] ?? '' );

		if ( ! $payment_id || $note === '' ) {
			return self::action_error( 'Payment ID and note are required', $input );
		}

		$note_id = edd_insert_payment_note( $payment_id, $note );

		if ( empty( $note_id ) ) {
			return self::action_error( 'Failed to add payment note', $input );
		}

		return self::action_success(array_merge($input, [
			'payment_id' => $payment_id,
			'payment_note_id' => $note_id,
		]));
	}
}
