<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait HookActionsTrait {

	private static function action_add_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::error_response( 'Hook name is required', $input );
		}

		$accepted_args = (int) ( $config['accepted_args'] ?? 1 );
		$accepted_args = min( 99, max( 1, $accepted_args ) );

		add_action(
			$hook_name,
			static function () {
			},
			10,
			$accepted_args
		);

		return self::main_response(
			array_merge(
				$input,
				[
					'hook'          => $hook_name,
					'accepted_args' => $accepted_args,
					'registered'    => true,
					'event_time'    => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_do_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::error_response( 'Hook name is required', $input );
		}

		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;

		do_action( $hook_name, $arg_1, $arg_2 );

		return self::main_response(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'arg_1'      => $arg_1,
					'arg_2'      => $arg_2,
					'triggered'  => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}
}
