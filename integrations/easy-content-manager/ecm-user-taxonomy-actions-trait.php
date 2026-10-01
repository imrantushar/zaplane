<?php

namespace Zaplane\Integrations\EasyContentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait EcmUserTaxonomyActionsTrait {

	protected static function ecm_execute_user_taxonomy_action( string $event, array $config ): ?array {
		switch ( $event ) {
			// --- Users ---
			case 'create_user':
				return static::ecm_action_create_user( $config );

			case 'update_user':
				return static::ecm_action_update_user( $config );

			case 'update_user_custom_meta':
				return static::ecm_action_update_user_meta( $config );

			case 'get_user_custom_meta':
				return static::ecm_action_get_user_meta( $config );

			// --- Taxonomy terms ---
			case 'create_taxonomy_term':
				return static::ecm_action_create_term( $config );

			case 'update_taxonomy_term':
				return static::ecm_action_update_term( $config );

			case 'delete_taxonomy_term':
				return static::ecm_action_delete_term( $config );

			case 'assign_term_to_item':
				return static::ecm_action_assign_terms( $config, true );

			case 'remove_term_from_item':
				return static::ecm_action_assign_terms( $config, false );
		}//end switch

		// Unknown in this group — let the entry's dispatcher try the next trait.
		return null;
	}

	private static function ecm_action_create_user( array $config ): array {
		$raw_login = (string) ( $config['user_login'] ?? '' );
		$user_login = function_exists( 'sanitize_user' ) ? sanitize_user( $raw_login, true ) : sanitize_title( $raw_login );
		$user_email = sanitize_email( (string) ( $config['user_email'] ?? '' ) );

		if ( '' === $user_login || '' === $user_email ) {
			return static::ecm_action_error( 'A username and email are required.' );
		}

		if ( ! is_email( $user_email ) ) {
			return static::ecm_action_error( 'A valid email address is required.' );
		}

		$password = (string) ( $config['user_pass'] ?? '' );
		$generated = false;

		if ( '' === $password ) {
			$password  = wp_generate_password( 16, true, true );
			$generated = true;
		}

		$user_id = wp_insert_user(
			[
				'user_login'   => $user_login,
				'user_email'   => $user_email,
				'user_pass'    => $password,
				'display_name' => (string) ( $config['display_name'] ?? '' ),
				'role'         => sanitize_key( (string) ( $config['role'] ?? 'subscriber' ) ),
			]
		);

		if ( is_wp_error( $user_id ) ) {
			return static::ecm_action_error( $user_id->get_error_message() );
		}

		return static::ecm_success(
			[
				'success'      => true,
				'user_id'      => (int) $user_id,
				'user_login'   => $user_login,
				'user_email'   => $user_email,
				'generated_password' => $generated,
			]
		);
	}

	private static function ecm_action_update_user( array $config ): array {
		$user_id = absint( $config['user_id'] ?? 0 );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return static::ecm_action_error( 'User not found.' );
		}

		$userdata = [ 'ID' => $user_id ];

		$email = sanitize_email( (string) ( $config['user_email'] ?? '' ) );
		if ( '' !== $email ) {
			if ( ! is_email( $email ) ) {
				return static::ecm_action_error( 'A valid email address is required.' );
			}
			$userdata['user_email'] = $email;
		}

		$display_name = (string) ( $config['display_name'] ?? '' );
		if ( '' !== $display_name ) {
			$userdata['display_name'] = $display_name;
		}

		$password = (string) ( $config['user_pass'] ?? '' );
		if ( '' !== $password ) {
			$userdata['user_pass'] = $password;
		}

		$result = wp_update_user( $userdata );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		$role = sanitize_key( (string) ( $config['role'] ?? '' ) );
		if ( '' !== $role ) {
			$user = get_userdata( $user_id );

			if ( $user && method_exists( $user, 'set_role' ) ) {
				$user->set_role( $role );
			} elseif ( $user && get_role( $role ) ) {
				// The mock user object has no set_role(); wp_update_user with a
				// role key covers real WordPress.
				wp_update_user( [
					'ID' => $user_id,
					'role' => $role
				] );
			} elseif ( ! $user ) {
				return static::ecm_action_error( 'User not found.' );
			}
		}

		return static::ecm_success(
			[
				'success' => true,
				'user_id' => $user_id,
			]
		);
	}

	/**
	 * Write one ECM user-scoped custom field. The key must be one ECM defines
	 * for user profiles — allowing arbitrary keys here would be a role-escalation
	 * hazard inside automations.
	 */
	private static function ecm_action_update_user_meta( array $config ): array {
		$user_id  = absint( $config['user_id'] ?? 0 );
		$meta_key = (string) ( $config['meta_key'] ?? '' );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return static::ecm_action_error( 'User not found.' );
		}

		if ( ! static::ecm_is_user_meta_key_allowed( $meta_key ) ) {
			return static::ecm_action_error( sprintf( '"%s" is not a user field defined in Easy Content Manager.', $meta_key ) );
		}

		$value = $config['value'] ?? '';

		update_user_meta( $user_id, $meta_key, $value );

		return static::ecm_success(
			[
				'success'  => true,
				'user_id'  => $user_id,
				'meta_key' => $meta_key,
				'value'    => $value,
			]
		);
	}

	private static function ecm_action_get_user_meta( array $config ): array {
		$user_id  = absint( $config['user_id'] ?? 0 );
		$meta_key = (string) ( $config['meta_key'] ?? '' );

		if ( ! $user_id ) {
			return static::ecm_action_error( 'User not found.' );
		}

		if ( '' === $meta_key ) {
			return static::ecm_action_error( 'A field name is required.' );
		}

		return static::ecm_success(
			[
				'user_id'  => $user_id,
				'meta_key' => $meta_key,
				'value'    => maybe_unserialize( get_user_meta( $user_id, $meta_key, true ) ),
			]
		);
	}

	private static function ecm_is_user_meta_key_allowed( string $meta_key ): bool {
		if ( '' === $meta_key || 0 === strpos( $meta_key, '_' ) ) {
			return false;
		}

		return in_array( $meta_key, static::ecm_user_meta_keys(), true );
	}

	private static function ecm_action_create_term( array $config ): array {
		$taxonomy = sanitize_key( (string) ( $config['taxonomy'] ?? '' ) );
		$name     = (string) ( $config['name'] ?? '' );

		if ( '' === $taxonomy || '' === trim( $name ) ) {
			return static::ecm_action_error( 'A taxonomy and term name are required.' );
		}

		$args = [];

		$slug = (string) ( $config['slug'] ?? '' );
		if ( '' !== $slug ) {
			$args['slug'] = sanitize_title( $slug );
		}

		$description = (string) ( $config['description'] ?? '' );
		if ( '' !== $description ) {
			$args['description'] = $description;
		}

		$result = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		return static::ecm_success(
			[
				'success'  => true,
				'term_id'  => (int) ( $result['term_id'] ?? 0 ),
				'name'     => $name,
				'taxonomy' => $taxonomy,
			]
		);
	}

	private static function ecm_action_update_term( array $config ): array {
		$term_id = absint( $config['term_id'] ?? 0 );

		if ( ! $term_id ) {
			return static::ecm_action_error( 'A term ID is required.' );
		}

		$args = [];

		$name = (string) ( $config['name'] ?? '' );
		if ( '' !== $name ) {
			$args['name'] = $name;
		}

		$slug = (string) ( $config['slug'] ?? '' );
		if ( '' !== $slug ) {
			$args['slug'] = sanitize_title( $slug );
		}

		$description = (string) ( $config['description'] ?? '' );
		if ( '' !== $description ) {
			$args['description'] = $description;
		}

		if ( empty( $args ) ) {
			return static::ecm_action_error( 'Nothing to update — provide a name, slug or description.' );
		}

		$result = wp_update_term( $term_id, (string) ( $config['taxonomy'] ?? '' ), $args );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		return static::ecm_success(
			[
				'success' => true,
				'term_id' => $term_id,
			]
		);
	}

	private static function ecm_action_delete_term( array $config ): array {
		$term_id  = absint( $config['term_id'] ?? 0 );
		$taxonomy = sanitize_key( (string) ( $config['taxonomy'] ?? '' ) );

		if ( ! $term_id || '' === $taxonomy ) {
			return static::ecm_action_error( 'A term ID and taxonomy are required.' );
		}

		$result = wp_delete_term( $term_id, $taxonomy );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		if ( false === $result ) {
			return static::ecm_action_error( 'Term not found.' );
		}

		return static::ecm_success(
			[
				'success' => true,
				'term_id' => $term_id,
			]
		);
	}

	private static function ecm_action_assign_terms( array $config, bool $assign ): array {
		$post_id  = absint( $config['post_id'] ?? 0 );
		$taxonomy = sanitize_key( (string) ( $config['taxonomy'] ?? '' ) );
		$terms    = static::ecm_term_list( $config['terms'] ?? '' );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		if ( '' === $taxonomy || empty( $terms ) ) {
			return static::ecm_action_error( 'A taxonomy and at least one term are required.' );
		}

		if ( $assign ) {
			$result = wp_set_object_terms( $post_id, $terms, $taxonomy, true );
		} elseif ( function_exists( 'wp_remove_object_terms' ) ) {
			$result = wp_remove_object_terms( $post_id, $terms, $taxonomy );
		} else {
			// Fallback (test mocks): remove by rewriting the remaining terms.
			$existing = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			$existing = is_array( $existing ) ? array_map( 'absint', $existing ) : [];

			$remove_ids = [];
			foreach ( $terms as $term ) {
				if ( is_int( $term ) ) {
					$remove_ids[] = $term;
					continue;
				}

				if ( function_exists( 'get_term_by' ) ) {
					$match = get_term_by( 'name', $term, $taxonomy );
					if ( $match ) {
						$remove_ids[] = (int) $match->term_id;
					}
				}
			}

			$result = wp_set_object_terms( $post_id, array_values( array_diff( $existing, array_map( 'absint', $remove_ids ) ) ), $taxonomy, false );
		}//end if

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		return static::ecm_success(
			[
				'success'  => true,
				'item_id'  => $post_id,
				'taxonomy' => $taxonomy,
				'terms'    => $terms,
			]
		);
	}
}
