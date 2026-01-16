<?php
namespace Zaplane\Integration\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait QueryTrait { 
    
    public static function query_post_types( $q ) {
        $types = get_post_types(['public'=>true],'objects');
        return array_map(fn($t)=>[
            'name'=>$t->name,
            'label'=>$t->label
        ], $types);
    }

    public static function query_posts( $q ) {

        $args = [
            'post_type'   => $q['where']['post_type'] ?? 'post',
            'post_status' => $q['where']['post_status'] ?? 'publish',
            's'           => $q['search'] ?? '',
            'numberposts' => $q['limit'] ?? 20,
        ];

        $posts = get_posts( $args );

        return array_map(fn($p)=>[
            'ID'         => $p->ID,
            'post_title'=> $p->post_title,
        ], $posts);
    }

    public static function query_terms( $query ) {
        $term_args = [
            'taxonomy'   => $query['where']['taxonomy'] ?? 'category',
            'search'     => $query['search'] ?? '',
            'number'     => $query['limit'] ?? 20,
            'hide_empty' => $query['where']['hide_empty'] ?? false,
            'parent'     => (int) ( $query['where']['parent'] ?? 0 ),
            'include'    => $query['where']['include'] ?? [],
        ];

        $terms = get_terms( $term_args );
        return array_map(
            fn( $term ) => [
                'term_id'          => $term->term_id,
                'term_taxonomy_id' => $term->term_taxonomy_id,
                'taxonomy'         => $term->taxonomy,
                'name'             => $term->name,
                'slug'             => $term->slug,
                'description'      => $term->description,
                'parent'           => $term->parent,
                'count'            => $term->count,
            ],
            $terms
        );
    }

    public static function query_users( $q ) {
        $q = is_array( $q ) ? $q : [];
        $users = $q['users'] ?? get_users( [ 'search' => $q['search'] ?? '' ] );
         return array_map( function( $user ) {
            $data = (array) $user->data;
            unset( $data['user_pass'], $data['user_activation_key'] );

            return [
                'ID'          => $user->ID,
                'name'        => $user->display_name,
                'email'       => $user->user_email,
                'login'       => $user->user_login,
                'nicename'    => $user->user_nicename,
                'url'         => $user->user_url,
                'registered'  => $user->user_registered,
                'roles'       => $user->roles ?? [],
                'first_name'  => $user->first_name ?? '',
                'last_name'   => $user->last_name ?? '',
                'nickname'    => $user->nickname ?? '',
                'description' => $user->description ?? '',
                'locale'      => function_exists( 'get_user_locale' ) ? get_user_locale( $user->ID ) : '',
                'avatar'      => get_avatar_url( $user->ID ),
                'caps'        => array_keys( $user->caps ?? [] ),
                'data'        => $data,
                'meta'        => get_user_meta( $user->ID ),
            ];
        }, $users );
    }

    public static function query_taxonomies( $q ) {
        $taxonomies = get_taxonomies( [], 'objects' );
        $items = [];
        foreach ( $taxonomies as $tax ) {
            $items[] = [
                'name' => $tax->name,
                'label' => $tax->label,
            ];
        }
        return $items;
    }

    public static function query_categories( $q ) {
        $args = [ 'hide_empty' => false ];
        if ( ! empty( $q['search'] ) ) {
            $args['search'] = $q['search'];
        }
        if ( ! empty( $q['limit'] ) ) {
            $args['number'] = (int) $q['limit'];
        }

        $categories = get_categories( $args );
        $items = [];
        foreach ( $categories as $cat ) {
            $items[] = [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'label' => $cat->name,
                'slug' => $cat->slug,
            ];
        }
        return $items;
    }

    public static function query_roles( $q ) {
        $roles = wp_roles();
        $items = [];
        foreach ( $roles->role_names as $key => $name ) {
            $items[] = [
                'name' => $key,
                'label' => $name,
            ];
        }
        return $items;
    }

    public static function query_caps( $q ) {
        $roles = wp_roles();
        $caps = [];
        foreach ( $roles->roles as $role ) {
            foreach ( $role['capabilities'] ?? [] as $cap => $grant ) {
                if ( $grant ) {
                    $caps[ $cap ] = true;
                }
            }
        }

        $items = [];
        foreach ( array_keys( $caps ) as $cap ) {
            $items[] = [
                'name' => $cap,
                'label' => $cap,
            ];
        }
        return $items;
    }
}