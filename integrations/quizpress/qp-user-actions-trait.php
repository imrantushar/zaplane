<?php

namespace Zaplane\Integrations\Quizpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QpUserActionsTrait {

	protected static function qp_execute_user_action( string $event, array $config ): ?array {
		switch ( $event ) {
			case 'create_user':
				return static::qp_action_create_user( $config );

			case 'update_user':
				return static::qp_action_update_user( $config );

			case 'send_email':
				return static::qp_action_send_email( $config );

			case 'create_post':
				return static::qp_action_save_post( $config, false );

			case 'update_post':
				return static::qp_action_save_post( $config, true );
		}//end switch

		return null;
	}

	private static function qp_action_create_user( array $config ): array {
		$email = sanitize_email( (string) ( $config['user_email'] ?? '' ) );

		if ( ! is_email( $email ) ) {
			return static::qp_action_error( 'A valid email address is required.' );
		}

		if ( function_exists( 'username_exists' ) && function_exists( 'email_exists' ) && ( username_exists( $email ) || email_exists( $email ) ) ) {
			return static::qp_action_error( 'A user with this email already exists.' );
		}

		$login = sanitize_user( (string) ( $config['user_login'] ?? '' ), true );

		if ( '' === $login ) {
			$login = sanitize_user( current( explode( '@', $email ) ), true );
		}

		$counter = 1;
		$base    = $login;

		while ( function_exists( 'username_exists' ) && username_exists( $login ) ) {
			++$counter;
			$login = $base . $counter;
		}

		$password = (string) ( $config['password'] ?? '' );

		if ( '' === $password ) {
			$password = wp_generate_password( 16 );
		}

		$user_id = wp_insert_user(
			[
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => (string) ( $config['first_name'] ?? '' ),
				'last_name'    => (string) ( $config['last_name'] ?? '' ),
				'role'         => sanitize_key( (string) ( $config['role'] ?? 'subscriber' ) ),
				'display_name' => trim( (string) ( $config['first_name'] ?? '' ) . ' ' . (string) ( $config['last_name'] ?? '' ) ),
			]
		);

		if ( is_wp_error( $user_id ) ) {
			return static::qp_action_error( $user_id->get_error_message() );
		}

		return static::qp_success( static::qp_user_payload( (int) $user_id ) );
	}

	private static function qp_action_update_user( array $config ): array {
		$user_id = absint( $config['user_id'] ?? 0 );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return static::qp_action_error( 'User not found.' );
		}

		$update = [ 'ID' => $user_id ];

		$email = sanitize_email( (string) ( $config['user_email'] ?? '' ) );

		if ( '' !== $email ) {
			if ( ! is_email( $email ) ) {
				return static::qp_action_error( 'The new email address is not valid.' );
			}

			$existing = email_exists( $email );

			if ( $existing && (int) $existing !== $user_id ) {
				return static::qp_action_error( 'Another user already uses this email address.' );
			}

			$update['user_email'] = $email;
		}

		foreach ( [ 'first_name', 'last_name' ] as $key ) {
			$value = (string) ( $config[ $key ] ?? '' );

			if ( '' !== $value ) {
				$update[ $key ] = $value;
			}
		}

		$role = sanitize_key( (string) ( $config['role'] ?? '' ) );

		if ( '' !== $role && get_role( $role ) ) {
			$update['role'] = $role;
		}

		$result = wp_update_user( $update );

		if ( is_wp_error( $result ) ) {
			return static::qp_action_error( $result->get_error_message() );
		}

		return static::qp_success( static::qp_user_payload( $user_id ) );
	}

	private static function qp_action_send_email( array $config ): array {
		$to = sanitize_email( (string) ( $config['to'] ?? '' ) );

		if ( ! is_email( $to ) ) {
			return static::qp_action_error( 'A valid recipient email address is required.' );
		}

		$subject = (string) ( $config['subject'] ?? '' );
		$message = (string) ( $config['message'] ?? '' );

		if ( '' === trim( $subject ) || '' === trim( $message ) ) {
			return static::qp_action_error( 'An email subject and message are required.' );
		}

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		$sent    = wp_mail( $to, $subject, $message, $headers );

		if ( ! $sent ) {
			return static::qp_action_error( 'The email could not be sent.' );
		}

		return static::qp_success(
			[
				'sent_to' => $to,
				'subject' => $subject,
			]
		);
	}

	private static function qp_action_save_post( array $config, bool $is_update ): array {
		$postarr = [];

		if ( $is_update ) {
			$post_id = absint( $config['post_id'] ?? 0 );

			if ( ! $post_id || ! get_post( $post_id ) ) {
				return static::qp_action_error( 'Post not found.' );
			}

			$postarr['ID'] = $post_id;

			foreach ( [ 'post_title', 'post_content' ] as $field ) {
				$value = (string) ( $config[ $field ] ?? '' );

				if ( '' !== $value ) {
					$postarr[ $field ] = $value;
				}
			}

			$status = sanitize_key( (string) ( $config['post_status'] ?? '' ) );

			if ( '' !== $status ) {
				$postarr['post_status'] = $status;
			}
		} else {
			$title = (string) ( $config['post_title'] ?? '' );

			if ( '' === trim( $title ) ) {
				return static::qp_action_error( 'A title is required to create a post.' );
			}

			$postarr = [
				'post_type'    => sanitize_key( (string) ( $config['post_type'] ?? 'post' ) ),
				'post_title'   => $title,
				'post_content' => (string) ( $config['post_content'] ?? '' ),
				'post_status'  => sanitize_key( (string) ( $config['post_status'] ?? 'draft' ) ),
			];
		}

		$post_id = $is_update ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return static::qp_action_error( $post_id->get_error_message() );
		}

		$post = get_post( $post_id );

		return static::qp_success(
			[
				'post_id'   => (int) $post_id,
				'title'     => (string) ( $post->post_title ?? '' ),
				'status'    => (string) ( $post->post_status ?? '' ),
				'post_type' => (string) ( $post->post_type ?? '' ),
				'permalink' => (string) get_permalink( $post_id ),
			]
		);
	}

	/**
	 * Standardized user payload, merged with any extras.
	 */
	protected static function qp_user_payload( int $user_id, array $extra = [] ): array {
		$user = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return array_merge( [ 'user_id' => $user_id ], $extra );
		}

		$payload = [
			'user_id'      => (int) $user_id,
			'user_login'   => (string) ( $user->user_login ?? '' ),
			'user_email'   => (string) ( $user->user_email ?? '' ),
			'display_name' => (string) ( $user->display_name ?? '' ),
			'first_name'   => (string) ( $user->first_name ?? '' ),
			'last_name'    => (string) ( $user->last_name ?? '' ),
			'roles'        => array_values( (array) ( $user->roles ?? [] ) ),
		];

		return array_merge( $payload, $extra );
	}
}
