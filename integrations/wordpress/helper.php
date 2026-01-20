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

    public static function copy_taxonomies(int $from_post, int $to_post): void {
        $taxonomies = get_object_taxonomies(get_post_type($from_post));

        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms(
                $from_post,
                $taxonomy,
                ['fields' => 'slug']
            );
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($to_post, $terms, $taxonomy);
            }
        }
    }

     public static function copy_meta(int $from_post, int $to_post): void {
        $meta = get_post_meta($from_post);

        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                add_post_meta(
                    $to_post,
                    $key,
                    maybe_unserialize($value)
                );
            }
        }
    }

     public static function get_posts(array $args = []): array {
        if (!empty($args['post_id'])) {
            $post = get_post( $args['post_id']);
            return $post ? self::format_post($post) : [];
        }

        $defaults = [
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ];

        $posts = get_posts(wp_parse_args($args, $defaults));

        return array_map([self::class, 'format_post'], $posts);
    }

    public static function format_post(\WP_Post $post): array {
        return [
            'ID'                    => $post->ID,
            'post_author'           => $post->post_author,
            'post_date'             => $post->post_date,
            'post_date_gmt'         => $post->post_date_gmt,
            'post_content'          => $post->post_content,
            'post_title'            => $post->post_title,
            'post_excerpt'          => $post->post_excerpt,
            'post_status'           => $post->post_status,
            'comment_status'        => $post->comment_status,
            'ping_status'           => $post->ping_status,
            'post_password'         => $post->post_password,
            'post_name'             => $post->post_name,
            'to_ping'               => $post->to_ping,
            'pinged'                => $post->pinged,
            'post_modified'         => $post->post_modified,
            'post_modified_gmt'     => $post->post_modified_gmt,
            'post_content_filtered' => $post->post_content_filtered,
            'post_parent'           => $post->post_parent,
            'guid'                  => $post->guid,
            'menu_order'            => $post->menu_order,
            'post_type'             => $post->post_type,
            'post_mime_type'        => $post->post_mime_type,
            'comment_count'         => $post->comment_count,
            'filter'                => 'raw',
        ];
    }

    public static function get_post_types(): array {
        $types = get_post_types([], 'objects');
        $post_types = [];

        foreach ($types as $name => $obj) {
            $post_types[$name] = [
                'name'            => $obj->name,
                'label'           => $obj->label,
                'description'     => $obj->description,
                'hierarchical'    => $obj->hierarchical,
                'rest_base'       => $obj->rest_base,
                'show_in_rest'    => $obj->show_in_rest,
                'public'          => $obj->public,
                'capability_type' => $obj->capability_type,
                'capabilities'    => $obj->cap,
                'labels'          => (array) $obj->labels,
                'supports'        => $obj->supports ?? [],
                'menu_icon'       => $obj->menu_icon ?? '',
                'menu_position'   => $obj->menu_position ?? null,
            ];
        }
        return $post_types;
    }

    public static function get_post_type_by_post_id(int $post_id): array {
        $post_type = get_post_type($post_id);
        if (!$post_type) return [];

        $obj = get_post_type_object($post_type);
        if (!$obj) return [];
        return [
            'post_id'         => $post_id,
            'post_type'       => $post_type,
            'label'           => $obj->label,
            'public'          => $obj->public,
            'show_in_rest'    => $obj->show_in_rest,
            'rest_base'       => $obj->rest_base,
            'capability_type' => $obj->capability_type,
            'hierarchical'    => $obj->hierarchical,
            'supports'        => $obj->supports ?? [],
            'description'     => $obj->description,
            'menu_icon'       => $obj->menu_icon ?? '',
            'menu_position'   => $obj->menu_position ?? null,
            'capabilities'    => $obj->cap ?? [],
            'labels'          => (array) $obj->labels,
        ];
    }

    public static function register_post_type(array $config): array {
        $slug               = $config['slug'] ?? '';
        if (!$slug) {
            return ['error' => 'Post type slug is required'];
        }

        $label              = $config['label'] ?? ucfirst($slug);
        $hierarchical       = $config['hierarchical'] ?? false;
        $public             = $config['public'] ?? true;
        $show_in_rest       = $config['show_in_rest'] ?? true;
        $show_ui            = $config['show_ui'] ?? true;
        $show_in_menu       = $config['show_in_menu'] ?? true;
        $show_in_nav_menus  = $config['show_in_nav_menus'] ?? true;
        $show_in_admin_bar  = $config['show_in_admin_bar'] ?? true;
        $menu_icon          = $config['menu_icon'] ?? '';
        $menu_position      = $config['menu_position'] ?? null;
        $supports           = $config['supports'] ?? ['title', 'editor'];
        $capability_type    = $config['capability_type'] ?? 'post';
        $description        = $config['description'] ?? '';
        $rewrite_slug       = $config['rewrite_slug'] ?? $slug;
        $args = [
            'label'             => $label,
            'public'            => $public,
            'hierarchical'      => $hierarchical,
            'show_ui'           => $show_ui,
            'show_in_menu'      => $show_in_menu,
            'show_in_nav_menus' => $show_in_nav_menus,
            'show_in_admin_bar' => $show_in_admin_bar,
            'menu_icon'         => $menu_icon,
            'menu_position'     => $menu_position,
            'supports'          => $supports,
            'capability_type'   => $capability_type,
            'description'       => $description,
            'show_in_rest'      => $show_in_rest,
            'rewrite'           => ['slug' => $rewrite_slug],
        ];
        $result = register_post_type($slug, $args);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }
        return ['success' => true, 'post_type' => $slug, 'args' => $args];
    }

    public static function upload_media_from_url(string $image_url, string $title = '', string $alt = '', string $caption = '', string $description = '') {

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image($image_url, 0, $title, 'id');
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        wp_update_post([
            'ID'           => $attachment_id,
            'post_title'   => $title,
            'post_excerpt' => $caption,
            'post_content' => $description,
        ]);
        if ($alt) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }
        return [
            'attachment_id'    => $attachment_id,
            'image_url'        => wp_get_attachment_url($attachment_id),
            'image_title'      => $title,
            'alternative_text' => $alt,
            'caption'          => $caption,
            'description'      => $description,
        ];
    }

    public static function get_media_posts(array $args = []): array
    {
        $defaults = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $query_args = array_merge($defaults, $args);
        return get_posts($query_args);
    }

    public static function format_media_items(array $media_posts): array
    {
        return array_map(function($media) {
            return [
                'ID'          => $media->ID,
                'title'       => $media->post_title,
                'url'         => wp_get_attachment_url($media->ID),
                'type'        => $media->post_mime_type,
                'alt_text'    => get_post_meta($media->ID, '_wp_attachment_image_alt', true),
                'caption'     => wp_get_attachment_caption($media->ID),
                'description' => $media->post_content,
                'date'        => $media->post_date,
            ];
        }, $media_posts);
    }
}
