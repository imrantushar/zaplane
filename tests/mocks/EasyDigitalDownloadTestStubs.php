<?php

namespace {
	if ( ! class_exists( 'EDD_Download' ) ) {
		class EDD_Download {
			public int $ID = 0;

			public function __construct( int $id = 0 ) {
				$this->ID = $id;
			}

			public function create( array $data ) {
				$this->ID = wp_insert_post( $data );
				return $this->ID;
			}
		}
	}

	if ( ! function_exists( 'edd_add_customer' ) ) {
		function edd_add_customer( $data ) {
			return empty( $data['email'] ) ? 0 : 321;
		}
	}

	if ( ! function_exists( 'edd_add_discount' ) ) {
		function edd_add_discount( $data ) {
			return empty( $data['code'] ) ? 0 : 91;
		}
	}

	if ( ! function_exists( 'edd_get_payment' ) ) {
		function edd_get_payment( $payment_id ) {
			$payment_id = (int) $payment_id;
			if ( $payment_id <= 0 ) {
				return false;
			}

			return (object) [
				'ID'     => $payment_id,
				'status' => 'pending',
			];
		}
	}

	if ( ! function_exists( 'edd_update_payment_status' ) ) {
		function edd_update_payment_status( $payment_id, $status ) {
			return $payment_id > 0 && '' !== (string) $status;
		}
	}

	if ( ! function_exists( 'edd_insert_payment_note' ) ) {
		function edd_insert_payment_note( $payment_id, $note ) {
			return $payment_id > 0 && '' !== trim( (string) $note ) ? 444 : 0;
		}
	}

	if ( ! function_exists( 'edd_get_download' ) ) {
		function edd_get_download( $download_id ) {
			$post = get_post( (int) $download_id );
			return ( $post && 'download' === ( $post->post_type ?? '' ) ) ? $post : false;
		}
	}
}