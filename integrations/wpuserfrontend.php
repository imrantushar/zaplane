<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Wpuserfrontend extends IntegrationBase {


	public static function get_slug(): string {
		return 'wpuserfrontend';
	}

	public static function get_name(): string {
		return 'WP User Frontend';
	}

	public static function get_icon(): string {
		return 'wp-user-frontend.svg';
	}

	public static function get_triggers(): array {
		return [
			'post_form_submission' => [
				'label' => 'Post Form Submission',
				'hook'  => 'wpuf_add_post_after_insert'
			],
			'registration_form_submission' => [
				'label' => 'Registration Form Submission',
				'hook'  => 'wpuf_after_register'
			],
			'profile_edit_form_submission' => [
				'label' => 'Profile Edit Form Submission',
				'hook'  => 'wpuf_update_profile'
			],
			'metadata_update_profile_edit_form_submission' => [
				'label' => 'User Metadata updated Profile Form',
				'hook'  => 'wpuf_pro_frontend_form_update_user_meta'
			],
			'subscription_pack_update' => [
				'label' => 'Subscription Pack Create/Updated',
				'hook'  => 'wpuf_before_update_subscription_pack'
			],
			'created_coupon' => [
				'label' => 'Created Coupon',
				'hook'  => 'wp_after_insert_post'
			],
			'updated_coupon' => [
				'label' => 'Updated Coupon',
				'hook'  => 'wpuf_update_coupon'
			],
		];
	}

	private static function resolve_user_payload( int $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		return [
			'user_id'      => (string) $user->ID,
			'first_name'   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'last_name', true ),
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'nickname'     => $user->nickname,
			'avatar_url'   => get_avatar_url( $user_id ),
			'display_name' => $user->display_name,
			'user_roles'   => $user->roles,
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'post_form_submission':
				$post_id       = $args[0] ?? 0;
				$form_id       = $args[1] ?? 0;
				$form_settings = $args[2] ?? [];
				$meta_data     = $args[3] ?? [];

				$post = get_post( $post_id );

				if ( ! $post ) {
					return false;
				}

				$raw_meta  = get_post_meta( $post_id );
				$post_meta = [];

				foreach ( $raw_meta as $key => $value ) {
					$post_meta[ $key ] = is_array( $value ) ? $value[0] : $value;
				}

				$post_data = array_merge( (array) $post, $post_meta );
				$user      = get_userdata( $post->post_author );
				$user_data = [];

				if ( $user ) {
					$user_data = [
						'id' => $user->ID,
						'user' => [
							'data' => [
								'ID'              => $user->ID,
								'user_login'      => $user->user_login,
								'user_email'      => $user->user_email,
								'display_name'    => $user->display_name,
								'user_url'        => $user->user_url,
								'user_registered' => $user->user_registered,
							],
							'roles' => $user->roles,
						]
					];
				}

				return [
					'success'      => true,
					'post_id'      => $post_id,
					'form_id'      => $form_id,
					'post_data'    => $post_data,
					'user_data'    => $user_data,
					'meta_data'    => $meta_data,
					'formSettings' => $form_settings,
				];

			case 'registration_form_submission':
				$user_id       = $args[0] ?? 0;
				$form_id       = $args[1] ?? 0;
				$form_settings = $args[2] ?? [];

				if ( ! $user_id ) {
					return false;
				}

				return [
					'success'       => true,
					'user_id'       => $user_id,
					'form_id'       => $form_id,
					'user_data'     => self::resolve_user_payload( $user_id ),
					'form_settings' => $form_settings,
				];

			case 'profile_edit_form_submission':
				$user_id       = $args[0] ?? 0;
				$form_id       = $args[1] ?? 0;
				$form_settings = $args[2] ?? [];
				$meta_data     = $args[3] ?? [];

				if ( ! $user_id ) {
					return false;
				}

				return [
					'success'       => true,
					'user_id'       => $user_id,
					'form_id'       => $form_id,
					'user_data'     => self::resolve_user_payload( $user_id ),
					'meta_data'     => $meta_data,
					'form_settings' => $form_settings,
				];

			case 'metadata_update_profile_edit_form_submission':
				$user_id   = $args[0] ?? 0;
				$post_data = $args[1] ?? [];

				if ( ! $user_id ) {
					return false;
				}

				$remove_keys = [ 'pass1', 'pass2', '_wpnonce', '_wp_http_referer' ];

				foreach ( $remove_keys as $key ) {
					unset( $post_data[ $key ] );
				}

				return [
					'success'   => true,
					'user_data' => self::resolve_user_payload( $user_id ),
					'post_data' => $post_data,
				];

			case 'subscription_pack_update':
				$id      = $args[0] ?? 0;
				$request = $args[1] ?? [];
				$post    = $args[2] ?? null;

				if ( ! $post && $id ) {
					$post = get_post( $id );
				}

				if ( is_array( $post ) ) {
					$post_data = [
						'post_type'        => $post['post_type'] ?? '',
						'post_date'        => $post['post_date'] ?? '',
						'post_date_gmt'    => $post['post_date_gmt'] ?? '',
						'post_content'     => $post['post_content'] ?? '',
						'post_title'       => $post['post_title'] ?? '',
						'post_status'      => $post['post_status'] ?? '',
						'post_modified'    => $post['post_modified'] ?? '',
						'post_modified_gmt' => $post['post_modified_gmt'] ?? '',
						'post_name'        => $post['post_name'] ?? '',
					];
				} elseif ( $post instanceof \WP_Post ) {
					$post_data = [
						'post_type'        => $post->post_type,
						'post_date'        => $post->post_date,
						'post_date_gmt'    => $post->post_date_gmt,
						'post_content'     => $post->post_content,
						'post_title'       => $post->post_title,
						'post_status'      => $post->post_status,
						'post_modified'    => $post->post_modified,
						'post_modified_gmt' => $post->post_modified_gmt,
						'post_name'        => $post->post_name,
					];
				} else {
					return false;
				}//end if

				$subscription = $request['subscription'] ?? [];
				$meta_value   = $subscription['meta_value'] ?? [];

				return [
					'success'      => true,
					'id'           => $id,
					'subscription' => array_merge( $subscription, [ 'meta_value' => $meta_value ] ),
					'post'         => $post_data,
				];

			case 'created_coupon':
				$post_id = $args[0] ?? 0;

				$post = get_post( $post_id );

				if ( ! $post || 'wpuf_coupon' !== $post->post_type ) {
					return false;
				}

				$post_data = [
					'ID'               => $post->ID,
					'post_author'      => $post->post_author,
					'post_date'        => $post->post_date,
					'post_date_gmt'    => $post->post_date_gmt,
					'post_content'     => $post->post_content,
					'post_title'       => $post->post_title,
					'post_excerpt'     => $post->post_excerpt,
					'post_status'      => $post->post_status,
					'comment_status'   => $post->comment_status,
					'ping_status'      => $post->ping_status,
					'post_password'    => $post->post_password,
					'post_name'        => $post->post_name,
					'post_modified'    => $post->post_modified,
					'post_modified_gmt' => $post->post_modified_gmt,
					'post_parent'      => $post->post_parent,
					'guid'             => $post->guid,
					'menu_order'       => $post->menu_order,
					'post_type'        => $post->post_type,
					'post_mime_type'   => $post->post_mime_type,
					'comment_count'    => $post->comment_count,
					'filter'           => 'raw',
				];

				$user = get_userdata( $post->post_author );
				$user_data = [
					'user_id'      => (string) $user->ID,
					'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
					'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
					'user_login'   => $user->user_login,
					'user_email'   => $user->user_email,
					'nickname'     => $user->nickname,
					'avatar_url'   => get_avatar_url( $user->ID ),
					'display_name' => $user->display_name,
					'user_roles'   => $user->roles,
				];

				return [
					'success'   => true,
					'post_data' => $post_data,
					'user_data' => $user_data,
				];

			case 'updated_coupon':
				$post_id = $args[0] ?? 0;

				$post = get_post( $post_id );

				if ( ! $post || 'wpuf_coupon' !== $post->post_type ) {
					return false;
				}

				$post_meta = get_post_meta( $post_id );
				$post_data = array_merge(
					[
						'ID'               => $post->ID,
						'post_author'      => $post->post_author,
						'post_date'        => $post->post_date,
						'post_date_gmt'    => $post->post_date_gmt,
						'post_content'     => $post->post_content,
						'post_title'       => $post->post_title,
						'post_excerpt'     => $post->post_excerpt,
						'post_status'      => $post->post_status,
						'comment_status'   => $post->comment_status,
						'ping_status'      => $post->ping_status,
						'post_password'    => $post->post_password,
						'post_name'        => $post->post_name,
						'post_modified'    => $post->post_modified,
						'post_modified_gmt' => $post->post_modified_gmt,
						'post_parent'      => $post->post_parent,
						'guid'             => $post->guid,
						'menu_order'       => $post->menu_order,
						'post_type'        => $post->post_type,
						'post_mime_type'   => $post->post_mime_type,
						'comment_count'    => $post->comment_count,
						'filter'           => 'raw',
					],
					$post_meta
				);

				$user = get_userdata( $post->post_author );
				$user_data = [
					'user_id'      => (string) $user->ID,
					'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
					'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
					'user_login'   => $user->user_login,
					'user_email'   => $user->user_email,
					'nickname'     => $user->nickname,
					'avatar_url'   => get_avatar_url( $user->ID ),
					'display_name' => $user->display_name,
					'user_roles'   => $user->roles,
				];

				return [
					'success'   => true,
					'post_data' => $post_data,
					'user_data' => $user_data,
				];

		}//end switch
		return false;
	}
}
