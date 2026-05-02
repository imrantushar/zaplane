<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Newsletter extends IntegrationBase {

	public static function get_slug(): string {
		return 'newsletter';
	}

	public static function get_name(): string {
		return 'Newsletter';
	}

	public static function get_icon(): string {
		return 'newsletter.svg';
	}

	public static function get_triggers(): array {
		return [
			'newsletter_user_post_subscribe' => [
				'label' => 'Subscription Form Submitted',
				'hook'  => 'newsletter_user_post_subscribe',
			],
			'newsletter_user_confirmed'      => [
				'label' => 'User Confirmed',
				'hook'  => 'newsletter_user_confirmed',
			],
			'newsletter_user_unsubscribed'   => [
				'label' => 'User Unsubscribed',
				'hook'  => 'newsletter_user_unsubscribed',
			],
			'newsletter_user_reactivated'    => [
				'label' => 'User Reactivated',
				'hook'  => 'newsletter_user_reactivated',
			],
			'newsletter_send_end'            => [
				'label' => 'Newsletter Send End',
				'hook'  => 'newsletter_send_end',
			],
		];
	}

	private static function resolve_user_payload( $user ): array {
		if ( ! $user ) {
			return [];
		}

		return [
			'id'         => $user->id ?? null,
			'email'      => $user->email ?? null,
			'name'       => $user->name ?? null,
			'surname'    => $user->surname ?? null,
			'status'     => $user->status ?? null,
			'created'    => $user->created ?? null,
			'ip'         => $user->ip ?? null,
			'country'    => $user->country ?? null,
			'language'   => $user->language ?? null,
			'token'      => $user->token ?? null,
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$user_triggers = [
			'newsletter_user_post_subscribe',
			'newsletter_user_confirmed',
			'newsletter_user_unsubscribed',
			'newsletter_user_reactivated',
		];

		if ( in_array( $node['event'], $user_triggers, true ) ) {
			$user = $args[0] ?? null;

			if ( ! $user ) {
				return false;
			}

			return [
				'success' => true,
				'user'    => self::resolve_user_payload( $user ),
			];
		}

		if ( 'newsletter_send_end' === $node['event'] ) {
			$newsletter_id = $args[0] ?? null;

			if ( ! $newsletter_id ) {
				return false;
			}

			return [
				'success'       => true,
				'newsletter_id' => $newsletter_id,
			];
		}

		return false;
	}

	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function get_output_ports(): array {
		return [
			'main' => 'Main output',
		];
	}
}
