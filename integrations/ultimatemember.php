<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Ultimatemember extends IntegrationBase {


	public static function get_slug(): string {
		return 'ultimatemember';
	}

	public static function get_name(): string {
		return 'Ultimate Member';
	}

	public static function get_icon(): string {
		return 'ultimatemember.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'User Login',
				'hook'  => 'um_user_login'
			],
			'form_submitted' => [
				'label' => 'User Registration',
				'hook'  => 'um_registration_complete'
			],
			'form_submitted' => [
				'label' => 'Change User Role',
				'hook'  => 'set_user_role'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$entry = $args[0] ?? null;
				$form  = $args[1] ?? null;

				if ( ! $entry || ! $form ) {
					return false;
				}

				$form_id = $form['id'] ?? 0;

				if ( ! empty( $node['form_id'] ) && 'any' !== $node['form_id'] ) {
					if ( (int) $form_id !== (int) $node['form_id'] ) {
						return false;
					}
				}

				$enter_entry = self::convertkey( $entry );
				$enter_entry['title'] = $form['title'] ?? '';

				return [
					'success'  => true,
					'form_id'  => $form_id,
					'entry_id' => $entry['id'] ?? 0,
					'data'     => $enter_entry,
				];
		}//end switch
		return false;
	}
}
