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
            'deleted_post'  => ['label' => 'Post Deleted',   'hook' => 'before_delete_post'],
            'trashed_post'  => ['label' => 'Post Trashed',   'hook' => 'trashed_post'],
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

                $user = get_userdata( $args[0] ?? 0 );
                if ( ! $user ) return false;

                return [
                    'user_id' => $user->ID,
                    'email'   => $user->user_email,
                    'role'    => $user->roles[0] ?? '',
                ];

            case 'comment_post':

                $comment = get_comment( $args[0] ?? 0 );
                if ( ! $comment ) return false;

                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'content'    => $comment->comment_content,
                ];

            case 'trashed_post' :
                $post = get_post( $args[0] ?? 0 );
                if ( ! $post) return false;

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                ];

            case 'deleted_post':
                $post = get_post( $args[0] ?? 0 );
                if ( ! $post) return false;

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
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
            'update_post_title' => ['label'=>'Update Post Title'],
            'delete_post'   => ['label'=>'Delete Post'],
            'deactivate_plugin'   => ['label'=>'Deactivate Plugin'],
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
                [
                    'key'=>'option_name',
                    'label'=>'Option',
                    'type'=>'text',
                    'required'=>true
                ],
                [
                    'key'=>'value',
                    'label'=>'Value',
                    'type'=>'expression'
                ],
            ];
        }

        if ( $action === 'update_post_title' ) {
            return [
                [
                    'key'=>'post_id',
                    'label'=>'Post ID',
                    'type'=>'expression',
                    'required'=>true,
                ],
                [
                    'key'=>'post_title',
                    'label'=>'New Post Title',
                    'type'=>'expression',
                    'required'=>true,
                ],
            ];
        }
        
        if ( $action === 'delete_post' ) {
            return [
                [
                    'key'=>'post_id',
                    'label'=>'Post ID to Delete',
                    'type'=>'expression', 
                    'required'=>true,
                ],
            ]; 
        }

        if ( $action === 'deactivate_plugin' ) {
            return [
                [
                    'key'=>'plugin',
                    'label'=>'Active Plugin',
                    'type'=>'select',
                    'dynamic'=>[
                        'integration'=>'wordpress',
                        'query'=>'active_plugins',
                        'select'=>['file', 'name' ],
                    ],
                    'required'=>true,
                ],
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

            case 'update_post_title':
                wp_update_post([
                    'ID'  => $config['post_id'] ,
                    'post_title' => $config['post_title'],
                ]);
                return ['port'=>'main','data'=>[]];

            case 'delete_post':
                wp_delete_post( $config['post_id'], true );
                return ['port'=>'main','data'=>['post_id'=>$config['post_id']]];

            case 'deactivate_plugin':
                if ( is_plugin_active( $config[ 'plugin' ] ) ) {
                    deactivate_plugins( $config[ 'plugin' ] ); 
                }
                return ['port'=>'main', 'data'=>[]];
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
            'active_plugins' => [ self::class, 'query_active_plugins' ],
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
        $users = get_users(['search'=>$q['search'] ?? '']);
        return array_map(fn($u)=>[
            'ID'=>$u->ID,
            'name'=>$u->display_name,
            'email'=>$u->user_email
        ], $users);
    }

    public static function query_active_plugins( $q ) {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugin = get_option( 'active_plugins', [] );
        $result = [];

        foreach ( $active_plugin as $plugin ) {
            if ( ! isset( $all_plugins[ $plugin ] ) ) {
                continue;
            }

            if ( $plugin === 'zaplane/zaplane.php' ) {
                continue;
            }

            $result[] = [
                'file' => $plugin,
                'name' => $all_plugins[ $plugin ][ 'Name' ],
            ];
        }

        return $result;
    }
}


