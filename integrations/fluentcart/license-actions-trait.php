<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait LicenseActionsTrait {

	private static function action_get_licenses_all( array $config, array $input ): array {
		$limit = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page  = max( 1, (int) ( $config['page'] ?? 1 ) );
		$items = self::list_licenses( $limit, $page, trim( (string) ( $config['search'] ?? '' ) ) );

		return self::main_response(
			array_merge( $input, [ 'items' => $items, 'total' => count( $items ), 'limit' => $limit, 'page' => $page ] )
		);
	}

	private static function action_get_license_single( array $config, array $input ): array {
		$license_id = self::parse_positive_int( $config['license_id'] ?? ( $input['license_id'] ?? 0 ) );
		if ( $license_id <= 0 ) {
			return self::error_response( 'License ID is required', $input );
		}

		$license = self::find_model_by_id( self::license_model_class(), $license_id );
		if ( ! $license ) {
			return self::error_response( 'License not found', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'license' => self::normalize_payload_value( $license ) ] )
		);
	}
}
