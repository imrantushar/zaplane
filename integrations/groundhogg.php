<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Groundhogg\Tag;

class Groundhogg extends IntegrationBase {


	public static function get_slug(): string {
		return 'groundhogg';
	}

	public static function get_name(): string {
		return 'Groundhogg';
	}

	public static function get_icon(): string {
		return 'groundhogg-icon.svg';
	}

	public static function get_triggers(): array {
		return [
			'created_contact' => [
				'label' => 'Created Contact',
				'hook'  => 'groundhogg/contact/post_create'
			],
			'added_tag' => [
				'label' => 'Tag Added To Contact',
				'hook'  => 'groundhogg/contact/tag_applied'
			],
			'removed_tag' => [
				'label' => 'Tag Remove From Contact',
				'hook'  => 'groundhogg/contact/tag_removed'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'added_tag', 'removed_tag' ], true ) ) {
			return [
				[
					'key'      => 'tag_id',
					'label'    => 'Tags',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'groundhogg',
						'query'       => 'groundhogg_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	private static function resolve_contact_payload( $contact ): array {
		$owner_data = null;
		$owner_id   = method_exists( $contact, 'get_owner_id' ) ? $contact->get_owner_id() : 0;
		if ( $owner_id ) {
			$user = get_userdata( $owner_id );
			if ( $user ) {
				$owner_data = [
					'id'        => $user->ID,
					'name'      => $user->display_name,
					'email'     => $user->user_email,
				];
			}
		}
		return [
			'id'            => $contact->get_id(),
			'first_name'    => (string) $contact->get_first_name(),
			'last_name'     => (string) $contact->get_last_name(),
			'email'         => (string) $contact->get_email(),
			'optin_status'  => $contact->get_optin_status(),
			'date_created'  => $contact->get_date_created(),
			'owner'         => $owner_data,
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'created_contact':
				$contact = $args[2] ?? null;
				if ( ! $contact ) {
					return false;
				}
				return [
					'success' => true,
					'contact' => self::resolve_contact_payload( $contact ),
				];

			case 'added_tag':
			case 'removed_tag':
				$contact = $args[0] ?? null;
				$tag_id  = $args[1] ?? null;

				if ( ! $contact instanceof \Groundhogg\Contact ) {
					return false;
				}
				if ( ! is_numeric( $tag_id ) ) {
					return false;
				}

				$tag_id     = (int) $tag_id;

				$select_tag = $node['config']['tag_id'] ?? 'any';
				if ( 'any' !== $select_tag && (int) $select_tag !== (int) $tag_id ) {
					return false;
				}

				$tag_data = null;
				$tag      = new Tag( $tag_id );

				if ( $tag && $tag->exists() ) {
					$tag_data = [
						'id'   => $tag->get_id(),
						'name' => $tag->get_name(),
						'slug' => $tag->get_slug(),
					];
				}
				return [
					'success'   => true,
					'contact'   => self::resolve_contact_payload( $contact ),
					'object_id' => $tag_id,
					'tag'       => $tag_data,
				];
		}//end switch
		return false;
	}

	public static function get_dynamic_queries(): array {
		return [
			'groundhogg_query' => [ self::class, 'grounhogg_query_types' ],
		];
	}

	public static function grounhogg_query_types( $query ) {
		$options = [
			[
				'label' => 'Any Tag',
				'name' => 'any'
			],
		];
		if ( function_exists( '\Groundhogg\get_db' ) ) {
			$tags = \Groundhogg\get_db( 'tags' )->query( [ 'limit' => 1000 ] );
			foreach ( $tags as $tag ) {
				$options[] = [
					'name' => $tag->tag_id,
					'label' => $tag->tag_name,
				];
			}
		}

		return $options;
	}
}
