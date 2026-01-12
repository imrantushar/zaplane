<?php
namespace Zaplane\Integration;

use Zaplane\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) exit;

class Wordpress extends IntegrationBase {

    public static function get_slug(): string {
        return 'wordpress';
    }

    /* =====================================================
     * TRIGGERS
     * ===================================================== */

    
    public static function get_triggers(): array {
        return [
            'publish_post'  => ['label' => 'Post Published', 'hook' => 'publish_post'],
            'post_updated'  => ['label' => 'Post Updated',   'hook' => 'post_updated'],
            'user_register' => ['label' => 'User Registered','hook' => 'user_register'],
            'comment_post'  => ['label' => 'Comment Added',  'hook' => 'comment_post'],
            'set_user_role'  => ['label' => 'User Role Updated',  'hook' => 'set_user_role'],
            'show_user_profile' => ['label' => 'User Profile Show', 'hook' => 'show_user_profile'],
            'edit_user_profile' => ['label' => 'User Profile Edit', 'hook' => 'edit_user_profile'],
            'personal_options_update' => ['label' => 'Personal Options Update', 'hook' => 'personal_options_update'],
            'edit_user_profile_update' => ['label' => 'Edit User', 'hook' => 'edit_user_profile_update'],
            'profile_update' => ['label' => 'Profile Update', 'hook' => 'profile_update'],
            'remove_user_from_blog' => ['label' => 'Remove Blog User', 'hook' => 'remove_user_from_blog'],
            'delete_user' => ['label' => 'Delete User', 'hook' => 'delete_user'],
            'login_footer' => ['label' => 'Login Footer', 'hook' => 'login_footer'],
            'login_form' => ['label' => 'Login Form', 'hook' => 'login_form'],
            'login_head' => ['label' => 'Login Head', 'hook' => 'login_head'],
            'login_init' => ['label' => 'Login Initialization', 'hook' => 'login_init'],
            'lostpassword_form' => ['label' => 'Lost Password Form', 'hook' => 'lostpassword_form'],
            'retrieve_password' => ['label' => 'Password Retrieval', 'hook' => 'retrieve_password'],
            'password_reset' => ['label' => 'Password Reset', 'hook' => 'password_reset'],
            'after_password_reset' => ['label' => 'After Password Reset', 'hook' => 'after_password_reset'],
            'register_form' => ['label' => 'Registration Form', 'hook' => 'register_form'],
            'signup_blogform' => ['label' => 'Signup Blog Form', 'hook' => 'signup_blogform'],
            'signup_extra_fields' => ['label' => 'Signup Extra Fields', 'hook' => 'signup_extra_fields'],
            'signup_finished' => ['label' => 'Signup Finished', 'hook' => 'signup_finished'],
            'signup_header' => ['label' => 'Signup Header', 'hook' => 'signup_header'],
            'create_term' => ['label' => 'Term Creation', 'hook' => 'create_term'],
            'created_term' => ['label' => 'Term Created', 'hook' => 'created_term'],
            'edit_term' => ['label' => 'Term Edit', 'hook' => 'edit_term'],
            'edited_term' => ['label' => 'Term Edited', 'hook' => 'edited_term'],
            'saved_term' => ['label' => 'Term Update', 'hook' => 'saved_term'],
            'delete_term' => ['label' => 'Term Deletion', 'hook' => 'delete_term'],
            'delete_term_taxonomy' => ['label' => 'Delete Term Taxonomy', 'hook' => 'delete_term_taxonomy'],
            'generate_rewrite_rules' => ['label' => 'Rewrite Rules', 'hook' => 'generate_rewrite_rules'],
            'add_action' => ['label' => 'Add Action', 'hook' => 'add_action'],
            'do_action' => ['label' => 'Do Action', 'hook' => 'do_action'],
            'add_meta_boxes' => ['label' => 'Meta Box Setup', 'hook' => 'add_meta_boxes'],
            'add_option' => ['label' => 'Option Addition', 'hook' => 'add_option'],
            'delete_option' => ['label' => 'Option Deletion', 'hook' => 'delete_option'],
            'delete_post_meta' => ['label' => 'Post Option Delete', 'hook' => 'delete_post_meta'],
            'admin_post' => ['label' => 'Admin Post Action', 'hook' => 'admin_post'],
            'wp_delete_site' => ['label' => 'Site Deletion', 'hook' => 'wp_delete_site'],
        ];
    }

    /**
     * Trigger UI Schema
     */
    public static function get_trigger_config_schema( string $trigger ): array {

        if ( in_array( $trigger, ['publish_post','post_updated'], true ) ) {
            return [
                [
                    'key'   => 'post_type',
                    'label' => 'Post Type',
                    'type'  => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'post_types',
                        'select'      => ['name','label'],
                    ],
                    'required' => true,
                ],
                [
                    'key'   => 'post_status',
                    'label' => 'Post Status',
                    'type'  => 'select',
                    'options' => [
                        ['label'=>'Publish','value'=>'publish'],
                        ['label'=>'Draft','value'=>'draft'],
                    ]
                ]
            ];
        }

        if ( $trigger === 'do_action' ) {
            return [
                [
                    'key' => 'hook_name',
                    'label' => "Hook Name (Use this code to trigger the action: do_action('hook_name', 1, ['key' => 'value']))",
                    'type' => 'text',
                    'required' => true,
                ],
            ];
        }

        if ( $trigger === 'add_action' ) {
            return [
                [
                    'key' => 'hook_name',
                    'label' => 'Hook Name',
                    'type' => 'text',
                    'required' => true,
                ],
            ];
        }

        return [];
    }

    /* =====================================================
     * TRIGGER PAYLOAD
     * ===================================================== */

    public static function resolve_trigger( array $node, array $args ) {
  
        switch ( $node['event'] ) {

            case 'publish_post':
            case 'post_updated':

                $post = get_post( $args[0] ?? 0 );
                 if ( ! $post ) return false;

                // Apply trigger filters
                if ( ! empty($node['config']['post_type']) && $post->post_type !== $node['config']['post_type'] ) {
                    return false;
                }
                if ( ! empty($node['config']['post_status']) && $post->post_status !== $node['config']['post_status'] ) {
                    return false;
                }

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                ];

            case 'user_register':

                return static::get_user_payload( $args[0] ?? 0 ) ?: false;

            case 'comment_post':

                $comment = get_comment( $args[0] ?? 0 );
                if ( ! $comment ) return false;

                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'content'    => $comment->comment_content,
                ];

            case 'set_user_role':

                $user_data = static::get_user_payload( $args[0] ?? 0 ) ?: [];

                $user_data['role'] = $args[1] ?? ( $user_data['role'] ?? '' );
                $user_data['old_roles'] = $args[2] ?? [];

                return $user_data ?: false;

            case 'show_user_profile':
            case 'edit_user_profile':
                return static::get_user_payload( $args[0] ?? 0 );

            case 'personal_options_update':
            case 'edit_user_profile_update':
                return static::get_user_payload( $args[0] ?? 0 );

            case 'profile_update':
                $old_user = (object) ( $args[1] ?? [] );
                return static::get_user_payload(
                    $args[0] ?? 0,
                    [
                        'old_email' => $old_user->user_email ?? '',
                        'old_role' => $old_user->roles[0] ?? '',
                    ]
                );

            case 'remove_user_from_blog':
                return static::get_user_payload(
                    $args[0] ?? 0,
                    [
                        'blog_id' => $args[1] ?? 0,
                        'reassign' => $args[2] ?? 0,
                    ]
                );

            case 'delete_user':
                return static::get_user_payload(
                    $args[2] ?? ( $args[0] ?? 0 ),
                    [ 'reassign' => $args[1] ?? 0 ]
                );

            case 'login_footer':
            case 'login_form':
            case 'login_head':
            case 'login_init':
            case 'lostpassword_form':
            case 'register_form':
            case 'signup_extra_fields':
            case 'signup_finished':
            case 'signup_header':
                return [
                    'event' => $node['event'],
                ];

            case 'retrieve_password':
                $user_payload = static::get_user_payload( $args[0] ?? '' );
                return $user_payload ?: [
                    'event' => $node['event'],
                    'user_login' => $args[0] ?? '',
                ];

            case 'password_reset':
                return static::get_user_payload( $args[0] ?? 0 );

            case 'after_password_reset':
                return static::get_user_payload(
                    $args[0] ?? 0,
                    [ 'after_reset' => true ]
                );

            case 'create_term':
            case 'created_term':
            case 'edit_term':
            case 'edited_term':
                return self::get_term_payload(
                    $args[0] ?? 0,
                    $args[2] ?? '',
                    $args[1] ?? 0,
                    [ 'args' => $args[3] ?? [] ]
                );

            case 'saved_term':
                if ( empty( $args[3] ) ) return false;

                return self::get_term_payload(
                    $args[0] ?? 0,
                    $args[2] ?? '',
                    $args[1] ?? 0,
                    [
                        'update' => true,
                        'args' => $args[4] ?? [],
                    ]
                );

            case 'delete_term':
                return self::get_term_payload(
                    $args[0] ?? 0,
                    $args[2] ?? '',
                    $args[1] ?? 0,
                    [
                        'deleted' => true,
                        'object_ids' => $args[4] ?? [],
                    ],
                    $args[3] ?? null
                );

            case 'delete_term_taxonomy':
                return self::get_term_payload( 0, '', $args[0] ?? 0 );

            case 'generate_rewrite_rules':
                $rewrite = (object) ( $args[0] ?? [] );
                return [
                    'event' => $node['event'],
                    'rules_count' => count( (array) ( $rewrite->rules ?? [] ) ),
                    'permalink_structure' => $rewrite->permalink_structure ?? '',
                ];

            case 'add_action':
                $hook = $args[0] ?? '';
                return [
                    'hook_name' => $hook,
                    'callback' => $args[1] ?? null,
                    'priority' => $args[2] ?? 10,
                    'accepted_args' => $args[3] ?? 1,
                ];

            case 'do_action':
                $hook = $args[0] ?? '';
                return [
                    'hook_name' => $hook,
                    'args' => array_slice( $args, 1 ),
                ];

            case 'add_meta_boxes':
                $post_type = $args[0] ?? '';
                $post = (object) ( $args[1] ?? [] );

                return [
                    'post_type' => $post_type,
                    'post_id' => $post->ID ?? 0,
                    'post_title' => $post->post_title ?? '',
                    'post_status' => $post->post_status ?? '',
                ];

            case 'add_option':
                return [
                    'option_name' => (string) ( $args[0] ?? '' ),
                    'value' => $args[1] ?? null,
                ];

            case 'delete_option':
                return [
                    'option_name' => (string) ( $args[0] ?? '' ),
                ];

            case 'delete_post_meta':
                return [
                    'meta_ids' => $args[0] ?? [],
                    'post_id' => $args[1] ?? 0,
                    'meta_key' => $args[2] ?? '',
                    'meta_value' => $args[3] ?? null,
                ];

            case 'admin_post':
                return [
                    'action' => $_REQUEST['action'] ?? '',
                ];

            case 'wp_delete_site':
                $site = (object) ( $args[0] ?? [] );
                return [
                    'blog_id' => $site->blog_id ?? 0,
                    'site_id' => $site->site_id ?? 0,
                    'domain' => $site->domain ?? '',
                    'path' => $site->path ?? '',
                    'registered' => $site->registered ?? '',
                    'deleted' => $site->deleted ?? '',
                ];

            case 'signup_blogform':
                $errors = $args[0] ?? null;
                $error_messages = is_object( $errors ) ? $errors->get_error_messages() : [];
                return [
                    'event' => $node['event'],
                    'error_messages' => $error_messages,
                ];
        }

        return false;
    }

    /* =====================================================
     * ACTIONS
     * ===================================================== */

    public static function get_actions(): array {
        return [
            'create_post'   => ['label'=>'Create Post'],
            'update_option' => ['label'=>'Update Option'],
        ];
    }

    /**
     * Action UI Schema
     */
    public static function get_action_config_schema( string $action ): array {

        if ( $action === 'create_post' ) {
            return [
                [
                    'key'=>'post_title',
                    'label'=>'Title',
                    'type'=>'expression',
                    'required'=>true,
                ],
                [
                    'key'=>'post_content',
                    'label'=>'Content',
                    'type'=>'textarea',
                ],
                [
                    'key'=>'post_type',
                    'label'=>'Post Type',
                    'type'=>'select',
                    'dynamic'=>[
                        'integration'=>'wordpress',
                        'query'=>'post_types',
                        'select'=>['name','label'],
                    ],
                    'required'=>true,
                ],
                [
                    'key'=>'post_status',
                    'label'=>'Status',
                    'type'=>'select',
                    'options'=>[
                        ['label'=>'Draft','value'=>'draft'],
                        ['label'=>'Publish','value'=>'publish'],
                    ],
                ],
            ];
        }

        if ( $action === 'update_option' ) {
            return [
                ['key'=>'option_name','label'=>'Option','type'=>'text','required'=>true],
                ['key'=>'value','label'=>'Value','type'=>'expression'],
            ];
        }

        return [];
    }

    /* =====================================================
     * ACTION EXECUTION
     * ===================================================== */

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];

        switch ( $node['data']['event'] ?? '' ) {

            case 'create_post':
                $id = wp_insert_post([
                    'post_title'   => $config['post_title'],
                    'post_content' => $config['post_content'] ?? '',
                    'post_status'  => $config['post_status'] ?? 'draft',
                    'post_type'    => $config['post_type'],
                ]);
                return ['port'=>'main','data'=>['post_id'=>$id]];

            case 'update_option':
                update_option( $config['option_name'], $config['value'] );
                return ['port'=>'main','data'=>[]];
        }

        return ['port'=>'main','data'=>$input];
    }

    /* =====================================================
     * DYNAMIC DATA QUERIES (API)
     * ===================================================== */

    public static function get_dynamic_queries(): array {
        return [
            'post_types' => [ self::class, 'query_post_types' ],
            'posts'      => [ self::class, 'query_posts' ],
            'terms'      => [ self::class, 'query_terms' ],
            'users'      => [ self::class, 'query_users' ],
        ];
    }

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

    // Resolve user and reuse query_users payload
    public static function get_user_payload( $user_ref, array $extra_data = [] ): ?array {
        $resolved_user = $user_ref;
        if ( is_numeric( $user_ref ) ) {
            $resolved_user = get_userdata( (int) $user_ref );
        } elseif ( ! is_object( $user_ref ) ) {
            $resolved_user = get_user_by( 'login', (string) $user_ref );
        }

        $user_payload = $resolved_user ? static::query_users( [ 'users' => [ $resolved_user ] ] ) : [];
        return $user_payload ? array_merge( $user_payload[0], $extra_data ) : null;
    }

    public static function get_term_payload( $term_id, $taxonomy, $term_taxonomy_id = 0, array $extra_data = [], $term_object = null ) {
        $term_id = $term_object->term_id ?? $term_id;
        $taxonomy = $term_object->taxonomy ?? $taxonomy;

        $terms = self::query_terms( [
            'where' => [
                'taxonomy' => $taxonomy,
                'include' => $term_id ? [ (int) $term_id ] : [],
            ],
            'limit' => 1,
        ] );

        return array_merge( $terms[0] ?? [], $extra_data );
    }
}
