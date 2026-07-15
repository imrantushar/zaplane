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

	public static function get_trigger_sample_output( string $event ): array {

		$user_payload = [
			'user_id'      => '42',
			'first_name'   => 'Jane',
			'last_name'    => 'Doe',
			'user_login'   => 'janedoe',
			'user_email'   => 'jane@example.com',
			'nickname'     => 'janedoe',
			'avatar_url'   => 'https://www.gravatar.com/avatar/0123456789abcdef?s=96&d=mm&r=g',
			'display_name' => 'Jane Doe',
			'user_roles'   => [ 'subscriber' ],
		];

		$post_object = [
			'ID'                => 101,
			'post_author'       => '42',
			'post_date'         => '2026-07-09 10:15:00',
			'post_date_gmt'     => '2026-07-09 10:15:00',
			'post_content'      => 'This is the submitted post content.',
			'post_title'        => 'My Submitted Post',
			'post_excerpt'      => '',
			'post_status'       => 'publish',
			'comment_status'    => 'open',
			'ping_status'       => 'open',
			'post_password'     => '',
			'post_name'         => 'my-submitted-post',
			'post_modified'     => '2026-07-09 10:15:00',
			'post_modified_gmt' => '2026-07-09 10:15:00',
			'post_parent'       => 0,
			'guid'              => 'https://example.com/?p=101',
			'menu_order'        => 0,
			'post_type'         => 'post',
			'post_mime_type'    => '',
			'comment_count'     => '0',
			'filter'            => 'raw',
		];

		$coupon_post = array_merge(
			$post_object,
			[
				'post_title' => 'SUMMER25',
				'post_name'  => 'summer25',
				'post_type'  => 'wpuf_coupon',
				'guid'       => 'https://example.com/?post_type=wpuf_coupon&p=101',
			]
		);

		$nested_user_data = [
			'id'   => 42,
			'user' => [
				'data'  => [
					'ID'              => 42,
					'user_login'      => 'janedoe',
					'user_email'      => 'jane@example.com',
					'display_name'    => 'Jane Doe',
					'user_url'        => 'https://example.com',
					'user_registered' => '2026-01-01 08:00:00',
				],
				'roles' => [ 'subscriber' ],
			],
		];

		$subscription_post = [
			'post_type'         => 'wpuf_subscription',
			'post_date'         => '2026-07-09 10:15:00',
			'post_date_gmt'     => '2026-07-09 10:15:00',
			'post_content'      => 'Premium subscription pack.',
			'post_title'        => 'Premium Pack',
			'post_status'       => 'publish',
			'post_modified'     => '2026-07-09 10:15:00',
			'post_modified_gmt' => '2026-07-09 10:15:00',
			'post_name'         => 'premium-pack',
		];

		$subscription = [
			'meta_value' => [
				'recurring_pay'     => 'no',
				'billing_amount'    => '29.00',
				'expiration_number' => '1',
				'expiration_period' => 'month',
				'post_count'        => '10',
			],
		];

		$samples = [
			'post_form_submission' => [
				'success'      => true,
				'post_id'      => 101,
				'form_id'      => 7,
				'post_data'    => array_merge(
					$post_object,
					[
						'custom_field' => 'Custom field value',
						'phone_number' => '+1-555-0100',
					]
				),
				'user_data'    => $nested_user_data,
				'meta_data'    => [
					'custom_field' => [ 'Custom field value' ],
					'phone_number' => [ '+1-555-0100' ],
				],
				'formSettings' => [
					'post_type'   => 'post',
					'post_status' => 'publish',
					'redirect_to' => 'page',
				],
			],
			'registration_form_submission' => [
				'success'       => true,
				'user_id'       => 42,
				'form_id'       => 3,
				'user_data'     => $user_payload,
				'form_settings' => [
					'role'        => 'subscriber',
					'redirect_to' => 'same',
				],
			],
			'profile_edit_form_submission' => [
				'success'       => true,
				'user_id'       => 42,
				'form_id'       => 5,
				'user_data'     => $user_payload,
				'meta_data'     => [
					'company' => 'Acme Inc',
					'website' => 'https://example.com',
				],
				'form_settings' => [
					'role'        => 'subscriber',
					'redirect_to' => 'same',
				],
			],
			'metadata_update_profile_edit_form_submission' => [
				'success'   => true,
				'user_data' => $user_payload,
				'post_data' => [
					'first_name' => 'Jane',
					'last_name'  => 'Doe',
					'company'    => 'Acme Inc',
					'website'    => 'https://example.com',
				],
			],
			'subscription_pack_update' => [
				'success'      => true,
				'id'           => 88,
				'subscription' => $subscription,
				'post'         => $subscription_post,
			],
			'created_coupon' => [
				'success'   => true,
				'post_data' => $coupon_post,
				'user_data' => $user_payload,
			],
			'updated_coupon' => [
				'success'   => true,
				'post_data' => array_merge(
					$coupon_post,
					[
						'_coupon_amount' => '25',
						'_coupon_type'   => 'percent',
					]
				),
				'user_data' => $user_payload,
			],
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( false !== strpos( $event, 'coupon' ) ) {
			return [
				'success'   => true,
				'post_data' => $coupon_post,
				'user_data' => $user_payload,
			];
		}

		if ( false !== strpos( $event, 'subscription' ) ) {
			return [
				'success'      => true,
				'id'           => 88,
				'subscription' => $subscription,
				'post'         => $subscription_post,
			];
		}

		if ( false !== strpos( $event, 'profile' )
			|| false !== strpos( $event, 'registration' )
			|| false !== strpos( $event, 'user' )
		) {
			return [
				'success'   => true,
				'user_id'   => 42,
				'user_data' => $user_payload,
			];
		}

		return [
			'success'   => true,
			'user_data' => $user_payload,
			'post_data' => $post_object,
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
