<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class WpFusion extends IntegrationBase {

	public static function get_slug(): string {
		return 'wpfusion';
	}

	public static function get_name(): string {
		return 'WP Fusion';
	}

	public static function get_icon(): string {
		return 'wpfusion.svg';
	}

	public static function get_triggers(): array {
		return [
			'tags_applied' => [
				'label' => 'Tags Applied',
				'hook'  => 'wpf_tags_applied',
			],
			'tags_removed' => [
				'label' => 'Tags Removed',
				'hook'  => 'wpf_tags_removed',
			],
			'user_imported' => [
				'label' => 'User Imported',
				'hook'  => 'wpf_user_imported',
			],
			'guest_contact_created' => [
				'label' => 'Guest Contact Created',
				'hook'  => 'wpf_guest_contact_created',
			],
			'guest_contact_updated' => [
				'label' => 'Guest Contact Updated',
				'hook'  => 'wpf_guest_contact_updated',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'tags_applied', 'tags_removed' ], true ) ) {
			return [
				[
					'key'      => 'tag_id',
					'label'    => 'Tag',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'wpfusion',
						'query'       => 'tags_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => false,
				],
			];
		}

		return [];
	}

	public static function get_actions(): array {
		return [
			'apply_tags' => [
				'label' => 'Apply Tags',
			],
			'remove_tags' => [
				'label' => 'Remove Tags',
			],
			'get_contact_id' => [
				'label' => 'Get Contact ID',
			],
			'get_user_id' => [
				'label' => 'Get User ID',
			],
			'import_user' => [
				'label' => 'Import User',
			],
			'create_or_update_contact' => [
				'label' => 'Create Or Update Contact',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'apply_tags':
			case 'remove_tags':
				return [
					[
						'key'      => 'user_id',
						'label'    => 'User ID',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'tags',
						'label'    => 'Tags',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'get_contact_id':
				return [
					[
						'key'      => 'user_id',
						'label'    => 'User ID',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'get_user_id':
				return [
					[
						'key'      => 'contact_id',
						'label'    => 'Contact ID',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'import_user':
				return [
					[
						'key'      => 'contact_id',
						'label'    => 'Contact ID',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'role',
						'label'    => 'Role',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'send_notification',
						'label'    => 'Send Notification',
						'type'     => 'boolean',
						'required' => false,
					],
				];

			case 'create_or_update_contact':
				return [
					[
						'key'      => 'email',
						'label'    => 'Email',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'first_name',
						'label'    => 'First Name',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'last_name',
						'label'    => 'Last Name',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'phone',
						'label'    => 'Phone',
						'type'     => 'expression',
						'required' => false,
					],
				];
		}

		return [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'tags_query' => [ self::class, 'query_tags' ],
		];
	}

	public static function query_tags( $query ): array {
		$search  = strtolower( trim( (string) ( $query['search'] ?? '' ) ) );
		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Tag',
			],
		];

		foreach ( self::get_available_tags() as $tag_id => $label ) {
			$haystack = strtolower( $tag_id . ' ' . $label );
			if ( '' !== $search && false === strpos( $haystack, $search ) ) {
				continue;
			}

			$options[] = [
				'name'  => (string) $tag_id,
				'label' => (string) $label,
			];
		}

		return $options;
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event  = $node['event'] ?? ( $node['data']['event'] ?? '' );
		$config = $node['config'] ?? ( $node['data']['config'] ?? [] );

		switch ( $event ) {
			case 'tags_applied':
			case 'tags_removed':
				$user_id = (int) ( $args[0] ?? 0 );
				$tags    = self::normalize_tag_list( $args[1] ?? [] );

				if ( ! $user_id || empty( $tags ) ) {
					return false;
				}

				$selected_tag = (string) ( $config['tag_id'] ?? 'any' );
				$tag_ids      = array_map( 'strval', $tags );

				if ( 'any' !== $selected_tag && ! in_array( $selected_tag, $tag_ids, true ) ) {
					return false;
				}

				return [
					'success'        => true,
					'user_id'        => $user_id,
					'contact_id'     => self::get_contact_id_for_user( $user_id ),
					'user'           => self::resolve_user_payload( $user_id ),
					'tags'           => $tag_ids,
					'tag_labels'     => self::resolve_tag_labels( $tags ),
					'matched_tag_id' => 'any' === $selected_tag ? ( $tag_ids[0] ?? null ) : $selected_tag,
				];

			case 'user_imported':
				$user_id  = (int) ( $args[0] ?? 0 );
				$userdata = $args[1] ?? [];

				if ( ! $user_id ) {
					return false;
				}

				return [
					'success'    => true,
					'user_id'    => $user_id,
					'contact_id' => self::get_contact_id_for_user( $user_id ),
					'user'       => self::resolve_user_payload( $user_id ),
					'userdata'   => is_array( $userdata ) ? $userdata : [],
				];

			case 'guest_contact_created':
			case 'guest_contact_updated':
				$contact_id = $args[0] ?? null;
				$email      = trim( (string) ( $args[1] ?? '' ) );

				if ( empty( $contact_id ) && '' === $email ) {
					return false;
				}

				$user_id = self::get_user_id_for_contact( $contact_id );

				return [
					'success'    => true,
					'contact_id' => $contact_id,
					'email'      => $email,
					'user_id'    => $user_id,
					'user'       => $user_id ? self::resolve_user_payload( $user_id ) : null,
				];
		}

		return false;
	}

	public static function execute_node( array $node, array $input ): array {
		unset( $input );

		$event  = $node['data']['event'] ?? ( $node['config']['action'] ?? '' );
		$config = $node['data']['config'] ?? ( $node['config']['data'] ?? [] );

		switch ( $event ) {
			case 'apply_tags':
				return self::handle_tag_action( $config, 'apply_tags' );

			case 'remove_tags':
				return self::handle_tag_action( $config, 'remove_tags' );

			case 'get_contact_id':
				$user_id = self::require_positive_int( $config['user_id'] ?? null, 'User ID is required' );

				return self::output( [
					'user_id'    => $user_id,
					'contact_id' => self::require_user_service()->get_contact_id( $user_id ),
				] );

			case 'get_user_id':
				$contact_id = self::require_string( $config['contact_id'] ?? '', 'Contact ID is required' );

				return self::output( [
					'contact_id' => $contact_id,
					'user_id'    => self::require_user_service()->get_user_id( $contact_id ),
				] );

			case 'import_user':
				$contact_id         = self::require_string( $config['contact_id'] ?? '', 'Contact ID is required' );
				$send_notification  = self::to_bool( $config['send_notification'] ?? false );
				$role               = trim( (string) ( $config['role'] ?? '' ) );
				$user_id            = self::require_user_service()->import_user( $contact_id, $send_notification, $role ?: false );

				if ( is_wp_error( $user_id ) ) {
					throw new \Exception( $user_id->get_error_message() );
				}

				return self::output( [
					'contact_id'        => $contact_id,
					'user_id'           => $user_id,
					'role'              => $role,
					'send_notification' => $send_notification,
				] );

			case 'create_or_update_contact':
				return self::handle_create_or_update_contact( $config );
		}

		return parent::execute_node( $node, [] );
	}

	private static function handle_tag_action( array $config, string $method ): array {
		$user_id = self::require_positive_int( $config['user_id'] ?? null, 'User ID is required' );
		$tags    = self::normalize_tag_list( $config['tags'] ?? [] );

		if ( empty( $tags ) ) {
			throw new \Exception( 'At least one tag is required' );
		}

		$result = self::require_user_service()->{$method}( $tags, $user_id );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		return self::output( [
			'user_id'    => $user_id,
			'contact_id' => self::get_contact_id_for_user( $user_id ),
			'tags'       => array_map( 'strval', $tags ),
			'tag_labels' => self::resolve_tag_labels( $tags ),
			'result'     => false !== $result,
		] );
	}

	private static function handle_create_or_update_contact( array $config ): array {
		$email = trim( (string) ( $config['email'] ?? '' ) );
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( 'A valid email address is required' );
		}

		$data = array_filter(
			[
				'email'      => $email,
				'first_name' => trim( (string) ( $config['first_name'] ?? '' ) ),
				'last_name'  => trim( (string) ( $config['last_name'] ?? '' ) ),
				'phone'      => trim( (string) ( $config['phone'] ?? '' ) ),
			],
			static function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);

		$crm        = self::require_crm_service();
		$contact_id = method_exists( $crm, 'get_contact_id' ) ? $crm->get_contact_id( $email ) : false;
		$mode       = $contact_id ? 'updated' : 'created';
		$result     = $contact_id ? $crm->update_contact( $contact_id, $data ) : $crm->add_contact( $data );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		if ( ! $contact_id ) {
			if ( is_scalar( $result ) && '' !== (string) $result ) {
				$contact_id = $result;
			} elseif ( is_array( $result ) && ! empty( $result['id'] ) ) {
				$contact_id = $result['id'];
			}
		}

		return self::output( [
			'contact_id' => $contact_id,
			'email'      => $email,
			'mode'       => $mode,
			'contact'    => $data,
		] );
	}

	private static function resolve_user_payload( int $user_id ): array {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return [
				'id' => $user_id,
			];
		}

		return [
			'id'           => $user_id,
			'login'        => $user->user_login ?? '',
			'email'        => $user->user_email ?? '',
			'display_name' => $user->display_name ?? '',
			'roles'        => $user->roles ?? [],
		];
	}

	private static function get_contact_id_for_user( int $user_id ) {
		$user = self::get_user_service();
		if ( ! $user || ! method_exists( $user, 'get_contact_id' ) ) {
			return null;
		}

		return $user->get_contact_id( $user_id );
	}

	private static function get_user_id_for_contact( $contact_id ) {
		$user = self::get_user_service();
		if ( ! $user || ! method_exists( $user, 'get_user_id' ) ) {
			return false;
		}

		return $user->get_user_id( $contact_id );
	}

	private static function resolve_tag_labels( array $tags ): array {
		$available_tags = self::get_available_tags();
		$labels         = [];

		foreach ( $tags as $tag ) {
			$key      = (string) $tag;
			$labels[] = $available_tags[ $key ] ?? $key;
		}

		return $labels;
	}

	private static function normalize_tag_list( $tags ): array {
		if ( is_string( $tags ) ) {
			$tags = preg_split( '/[\r\n,]+/', $tags );
		}

		if ( ! is_array( $tags ) ) {
			$tags = [ $tags ];
		}

		$normalized = [];
		foreach ( $tags as $tag ) {
			$tag = self::normalize_tag_value( $tag );
			if ( null === $tag || '' === $tag ) {
				continue;
			}
			$normalized[] = $tag;
		}

		return array_values( array_unique( $normalized, SORT_REGULAR ) );
	}

	private static function normalize_tag_value( $tag ) {
		if ( is_array( $tag ) || is_object( $tag ) ) {
			return null;
		}

		$tag = trim( (string) $tag );
		if ( '' === $tag ) {
			return null;
		}

		$available_tags = self::get_available_tags();
		if ( isset( $available_tags[ $tag ] ) ) {
			return $tag;
		}

		$tag_id = array_search( $tag, $available_tags, true );
		if ( false !== $tag_id ) {
			return (string) $tag_id;
		}

		return $tag;
	}

	private static function get_available_tags(): array {
		$raw_tags = [];

		if ( function_exists( 'wpf_get_option' ) ) {
			$raw_tags = (array) wpf_get_option( 'available_tags', [] );
		}

		if ( empty( $raw_tags ) ) {
			$settings = self::get_settings_service();
			if ( $settings && method_exists( $settings, 'get_available_tags_flat' ) ) {
				$raw_tags = (array) $settings->get_available_tags_flat( true, false );
			}
		}

		$tags = [];
		foreach ( $raw_tags as $tag_id => $tag ) {
			if ( is_array( $tag ) ) {
				$tags[ (string) $tag_id ] = (string) ( $tag['label'] ?? $tag['name'] ?? $tag_id );
				continue;
			}

			$tags[ (string) $tag_id ] = (string) $tag;
		}

		return $tags;
	}

	private static function get_instance() {
		if ( ! function_exists( 'wp_fusion' ) ) {
			return null;
		}

		return wp_fusion();
	}

	private static function get_user_service() {
		$instance = self::get_instance();
		return is_object( $instance ) ? ( $instance->user ?? null ) : null;
	}

	private static function get_crm_service() {
		$instance = self::get_instance();
		return is_object( $instance ) ? ( $instance->crm ?? null ) : null;
	}

	private static function get_settings_service() {
		$instance = self::get_instance();
		return is_object( $instance ) ? ( $instance->settings ?? null ) : null;
	}

	private static function require_user_service() {
		$user = self::get_user_service();
		if ( ! $user ) {
			throw new \Exception( 'WP Fusion user service is not available' );
		}

		return $user;
	}

	private static function require_crm_service() {
		$crm = self::get_crm_service();
		if ( ! $crm ) {
			throw new \Exception( 'WP Fusion CRM service is not available' );
		}

		return $crm;
	}

	private static function require_positive_int( $value, string $message ): int {
		$int = (int) $value;
		if ( $int <= 0 ) {
			throw new \Exception( $message );
		}

		return $int;
	}

	private static function require_string( $value, string $message ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			throw new \Exception( $message );
		}

		return $value;
	}

	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
	}

	private static function output( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}
}
