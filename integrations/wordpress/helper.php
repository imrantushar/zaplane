<?php
namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Integrations\Wordpress\QueryTrait;

Trait Helper {
    use QueryTrait;

    public static function get_user_payload( $user_ref, array $extra_data = [] ): ?array {
        $user = null;
        if ( is_numeric( $user_ref ) ) {
            $user = get_userdata( (int) $user_ref );
        } elseif ( is_object( $user_ref ) ) {
            $user = $user_ref;
        } else {
            $user = get_user_by( 'login', (string) $user_ref );
        }

        if ( ! $user ) {
            return null;
        }

        $user_payload = self::query_users( [ 'users' => [ $user ] ] );
        if ( ! $user_payload ) {
            return null;
        }

        return array_merge( $user_payload[0], $extra_data );
    }

    public static function get_term_payload( $term_id, $taxonomy, $term_taxonomy_id = 0, array $extra_data = [], $term_object = null ) {
        if ( $term_object ) {
            $term_id = $term_object->term_id;
            $taxonomy = $term_object->taxonomy;
        }

        $include = [];
        if ( $term_id ) {
            $include[] = (int) $term_id;
        }

        $terms = self::query_terms( [
            'where' => [
                'taxonomy' => $taxonomy,
                'include' => $include,
            ],
            'limit' => 1,
        ] );

        $term = [];
        if ( ! empty( $terms ) ) {
            $term = $terms[0];
        }

        if ( $extra_data ) {
            $term = array_merge( $term, $extra_data );
        }

        return $term;
    }

    public static function normalize_list( $value ): array {
        if ( is_string( $value ) ) {
            return array_map( 'trim', explode( ',', $value ) );
        }

        return (array) $value;
    }

    public static function normalize_caps( $value ): array {
        return array_fill_keys( self::normalize_list( $value ), true );
    }

    public static function normalize_taxonomy_args( $value ): array {
        return is_array( $value ) ? $value : [];
    }

    public static function set_role_display_name( string $role_key, string $display_name ): void {
        $roles = wp_roles();
        $roles->roles[ $role_key ]['name'] = $display_name;
        $roles->role_names[ $role_key ] = $display_name;
        update_option( $roles->role_key, $roles->roles, true );
    }

    public static function format_role_payload( string $role_key, $role ): ?array {
        if ( ! $role ) {
            return null;
        }

        return [
            'role' => $role_key,
            'display_name' => $role->name,
            'capabilities' => array_keys( $role->capabilities ),
        ];
    }

    public static function resolve_role_key( $role_input, bool $require_existing = false ): string {
        $role = trim( (string) $role_input );
        if ( $role === '' ) {
            return '';
        }
        $roles = wp_roles();
        $key = sanitize_key( $role );
        if ( isset( $roles->roles[ $key ] ) ) {
            return $key;
        }

        foreach ( $roles->role_names as $key => $name ) {
            if ( strcasecmp( (string) $name, $role ) === 0 ) {
                return $key;
            }
        }

        return $require_existing ? '' : $key;
    }
}
