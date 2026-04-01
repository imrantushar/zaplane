<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gemcrm extends IntegrationBase {

	public static function get_slug(): string {
		return 'gemcrm';
	}

	public static function get_name(): string {
		return 'GemCRM';
	}

	public static function get_icon(): string {
		return 'gemcrm-logo-icon.svg';
	}

	// -------------------------------------------------------------------------
	// Triggers
	// -------------------------------------------------------------------------

	public static function get_triggers(): array {
		return [
			'contact_created' => [
				'label' => 'Contact Created',
				'hook'  => 'gemcrm/contact/created',
			],
			'contact_tag_attached' => [
				'label' => 'Tag Added To Contact',
				'hook'  => 'gemcrm/contact/tag/attached',
			],
			'contact_tag_removed' => [
				'label' => 'Tag Removed From Contact',
				'hook'  => 'gemcrm/contact/tag/removed',
			],
			'contact_list_attached' => [
				'label' => 'Contact Added To List',
				'hook'  => 'gemcrm/contact/list/attached',
			],
			'contact_list_removed' => [
				'label' => 'Contact Removed From List',
				'hook'  => 'gemcrm/contact/list/removed',
			],
		];
	}

	/**
	 * Optional config schema per trigger.
	 *
	 * Tag and list triggers let the user optionally scope them to a specific
	 * tag/list ID. Leaving the field empty means "fire for any tag/list".
	 */
	public static function get_trigger_config_schema( string $trigger ): array {
		switch ( $trigger ) {
			case 'contact_tag_attached':
			case 'contact_tag_removed':
				return [
					[
						'key'         => 'tag_id',
						'label'       => 'Tag ID (optional)',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'Leave empty to trigger for any tag',
					],
				];

			case 'contact_list_attached':
			case 'contact_list_removed':
				return [
					[
						'key'         => 'list_id',
						'label'       => 'List ID (optional)',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'Leave empty to trigger for any list',
					],
				];
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// Trigger resolver
	// -------------------------------------------------------------------------

	public static function resolve_trigger( array $node, array $args ) {
		$event = $node['event'] ?? '';

		switch ( $event ) {

			case 'contact_created':
				$contact_id = $args[0] ?? null;
				$data       = $args[1] ?? [];

				if ( ! $contact_id || empty( $data ) ) {
					return false;
				}

				return [
					'contact_id'     => (int) $contact_id,
					'id'             => $data['id'] ?? $contact_id,
					'user_id'        => $data['user_id'] ?? null,
					'first_name'     => $data['first_name'] ?? '',
					'last_name'      => $data['last_name'] ?? '',
					'email'          => $data['email'] ?? '',
					'phone'          => $data['phone'] ?? '',
					'status'         => $data['status'] ?? '',
					'type'           => $data['type'] ?? '',
					'clicks'         => $data['clicks'] ?? 0,
					'total_mail_sent' => $data['total_mail_sent'] ?? 0,
					'email_open_rate' => $data['email_open_rate'] ?? 0,
					'meta'           => $data['meta'] ?? [],
					'lists'          => $data['lists'] ?? [],
					'tags'           => $data['tags'] ?? [],
					'companies'      => $data['companies'] ?? [],
					'creator'        => $data['creator'] ?? [],
					'created_at'     => $data['created_at'] ?? '',
					'updated_at'     => $data['updated_at'] ?? '',
				];

			case 'contact_tag_attached':
			case 'contact_tag_removed':
				$contact_id = $args[0] ?? null;
				$tag_ids    = $args[1] ?? [];

				if ( ! $contact_id || empty( $tag_ids ) ) {
					return false;
				}

				// If the user configured a specific tag filter, enforce it.
				$filter_tag_id = isset( $node['config']['tag_id'] ) ? (int) $node['config']['tag_id'] : null;
				if ( $filter_tag_id && ! in_array( $filter_tag_id, array_map( 'intval', (array) $tag_ids ), true ) ) {
					return false;
				}

				return [
					'contact_id' => (int) $contact_id,
					'tag_ids'    => array_map( 'intval', (array) $tag_ids ),
				];

			case 'contact_list_attached':
			case 'contact_list_removed':
				$contact_id = $args[0] ?? null;
				$list_ids   = $args[1] ?? [];

				if ( ! $contact_id || empty( $list_ids ) ) {
					return false;
				}

				// If the user configured a specific list filter, enforce it.
				$filter_list_id = isset( $node['config']['list_id'] ) ? (int) $node['config']['list_id'] : null;
				if ( $filter_list_id && ! in_array( $filter_list_id, array_map( 'intval', (array) $list_ids ), true ) ) {
					return false;
				}

				return [
					'contact_id' => (int) $contact_id,
					'list_ids'   => array_map( 'intval', (array) $list_ids ),
				];
		}//end switch

		return false;
	}
}
