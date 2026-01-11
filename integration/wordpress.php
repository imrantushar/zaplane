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

        return [];
    }

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

                $payload = static::get_user_payload( $args[0] ?? 0 );
                if ( ! $payload ) return false;

                $role = $args[1] ?? '';
                $payload['role'] = $role ?: ( $payload['role'] ?? '' );
                $payload['old_roles'] = $args[2] ?? [];

                return $payload;

            case 'show_user_profile':
            case 'edit_user_profile':
                return static::get_user_payload( $args[0] ?? 0 ) ?: false;

            case 'personal_options_update':
            case 'edit_user_profile_update':
                return static::get_user_payload( $args[0] ?? 0 ) ?: false;

            case 'profile_update':
                $payload = static::get_user_payload( $args[0] ?? 0 );
                if ( ! $payload ) return false;

                $old_user = $args[1] ?? null;
                if ( $old_user instanceof \WP_User ) {
                    $payload['old_email'] = $old_user->user_email;
                    $payload['old_role'] = $old_user->roles[0] ?? '';
                }

                return $payload;

            case 'remove_user_from_blog':
                return static::get_user_payload(
                    $args[0] ?? 0,
                    [
                        'blog_id' => $args[1] ?? 0,
                        'reassign' => $args[2] ?? 0,
                    ]
                ) ?: false;

            case 'delete_user':
                return static::get_user_payload(
                    $args[2] ?? ( $args[0] ?? 0 ),
                    [
                        'reassign' => $args[1] ?? 0,
                    ]
                ) ?: false;

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
                return static::get_user_payload( $args[0] ?? '' ) ?: [
                    'event' => $node['event'],
                    'user_login' => (string) ( $args[0] ?? '' ),
                ];

            case 'password_reset':
                return static::get_user_payload( $args[0] ?? 0 ) ?: false;

            case 'after_password_reset':
                return static::get_user_payload(
                    $args[0] ?? 0,
                    [
                        'after_reset' => true,
                    ]
                ) ?: false;

            case 'add_action':
                $hook_name = (string) ( $args[0] ?? '' );
                if ( ! empty( $node['config']['hook_name'] ) && $hook_name !== $node['config']['hook_name'] ) {
                    return false;
                }

                return [
                    'hook_name' => $hook_name,
                    'callback' => $args[1] ?? null,
                    'priority' => (int) ( $args[2] ?? 10 ),
                    'accepted_args' => (int) ( $args[3] ?? 1 ),
                ];

            case 'do_action':
                $hook_name = (string) ( $args[0] ?? '' );
                if ( ! empty( $node['config']['hook_name'] ) && $hook_name !== $node['config']['hook_name'] ) {
                    return false;
                }

                return [
                    'hook_name' => $hook_name,
                    'args' => array_slice( $args, 1 ),
                ];

            case 'add_meta_boxes':
                $post_type = (string) ( $args[0] ?? '' );
                $post = $args[1] ?? null;

                return [
                    'post_type' => $post_type,
                    'post_id' => $post instanceof \WP_Post ? $post->ID : 0,
                    'post_title' => $post instanceof \WP_Post ? $post->post_title : '',
                    'post_status' => $post instanceof \WP_Post ? $post->post_status : '',
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
                    'post_id' => (int) ( $args[1] ?? 0 ),
                    'meta_key' => (string) ( $args[2] ?? '' ),
                    'meta_value' => $args[3] ?? null,
                ];

            case 'admin_post':
                return [
                    'action' => (string) ( $_REQUEST['action'] ?? '' ),
                ];

            case 'wp_delete_site':
                $site = $args[0] ?? null;
                if ( $site instanceof \WP_Site ) {
                    return [
                        'blog_id' => (int) $site->blog_id,
                        'site_id' => (int) $site->site_id,
                        'domain' => (string) $site->domain,
                        'path' => (string) $site->path,
                        'registered' => (string) $site->registered,
                        'deleted' => (string) $site->deleted,
                    ];
                }
                return [
                    'blog_id' => 0,
                ];

            case 'signup_blogform':
                $errors = $args[0] ?? null;
                $error_messages = [];
                if ( $errors instanceof \WP_Error ) {
                    $error_messages = $errors->get_error_messages();
                }
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
    private static function get_user_payload( $user_id_or_login, array $extra = [] ): ?array {
        if ( $user_id_or_login instanceof \WP_User ) {
            $user = $user_id_or_login;
        } elseif ( is_numeric( $user_id_or_login ) ) {
            $user = get_userdata( (int) $user_id_or_login );
        } else {
            $user = get_user_by( 'login', (string) $user_id_or_login );
        }

        if ( ! $user ) {
            return null;
        }

        $payload = static::query_users( [ 'users' => [ $user ] ] );
        return $payload ? array_merge( $payload[0], $extra ) : null;
    }
}
