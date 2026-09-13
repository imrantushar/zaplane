<?php
namespace Zaplane\Integrations\Easydigitaldownload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ActionsResponseTrait {

	protected static function action_error( string $message, array $input = [] ): array {
		return [
			'port' => 'main',
			'data' => array_merge($input, [
				'error' => $message,
			]),
		];
	}

	protected static function action_success( array $data = [] ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}
}
