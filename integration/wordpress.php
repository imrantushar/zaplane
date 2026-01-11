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
            'trashed_comment'           => ['label' => 'Comment Trashed',          'hook' => 'trashed_comment'],
            'untrashed_comment'         => ['label' => 'Comment Untrashed',        'hook' => 'untrashed_comment'],
            'transition_comment_status' => ['label' => 'Comment Status Changed',   'hook' => 'transition_comment_status'],
            'untrashed_post'            => ['label' => 'Post Untrashed',           'hook' => 'untrashed_post'],
            'wp_update_comment_count'   => ['label' => 'Comment Count Updated',    'hook' => 'wp_update_comment_count'],
            'wp_set_comment_status'     => ['label' => 'Comment Status Set',       'hook' => 'wp_set_comment_status'],

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
         if ( in_array( $trigger, ['trashed_comment','untrashed_comment'], true ) ) {
            return [
                [
                    'key'=>'post_type',
                    'label'=>'Post Type',
                    'type'=>'select',
                    'dynamic'=>[
                        'integration'=>'wordpress',
                        'query'=>'post_types',
                        'select'=>['name','label'],
                    ],
                    'required'=>false,
                ],
            ];
        }


        if ( $trigger === 'transition_comment_status' ) {
            return [
                [
                    'key'=>'from_status',
                    'label'=>'From Status',
                    'type'=>'select',
                    'options'=>[
                        ['label'=>'Approved','value'=>'1'],
                        ['label'=>'Pending','value'=>'0'],
                        ['label'=>'Spam','value'=>'spam'],
                        ['label'=>'Trash','value'=>'trash'],
                    ]
                ],
                [
                    'key'=>'to_status',
                    'label'=>'To Status',
                    'type'=>'select',
                    'options'=>[
                        ['label'=>'Approved','value'=>'1'],
                        ['label'=>'Pending','value'=>'0'],
                        ['label'=>'Spam','value'=>'spam'],
                        ['label'=>'Trash','value'=>'trash'],
                    ]
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

            case 'trashed_comment':
            case 'untrashed_comment':


                $comment = get_comment( $args[0] ?? 0 );
                if ( ! $comment ) return false;


                // Apply post type filter if specified
                if ( ! empty($node['config']['post_type']) ) {
                    $post = get_post( $comment->comment_post_ID );
                    if ( ! $post || $post->post_type !== $node['config']['post_type'] ) {
                        return false;
                    }
                }


                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'content'    => $comment->comment_content,
                    'status'     => $comment->comment_approved,
                ];


            case 'transition_comment_status':


                $comment_id = $args[1] ?? 0;
                $comment = get_comment( $comment_id );
                if ( ! $comment ) return false;


                $new_status = $args[0] ?? '';
                $old_status = $args[2] ?? '';


                // Apply status filters
                if ( ! empty($node['config']['from_status']) && $old_status !== $node['config']['from_status'] ) {
                    return false;
                }
                if ( ! empty($node['config']['to_status']) && $new_status !== $node['config']['to_status'] ) {
                    return false;
                }


                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                    'content'    => $comment->comment_content,
                ];


            case 'untrashed_post':


                $post = get_post( $args[0] ?? 0 );
                if ( ! $post ) return false;


                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                ];


            case 'wp_update_comment_count':


                $post_id = $args[0] ?? 0;
                $post = get_post( $post_id );
                if ( ! $post ) return false;


                return [
                    'post_id'       => $post_id,
                    'comment_count' => $post->comment_count,
                    'post_title'    => $post->post_title,
                ];


            case 'wp_set_comment_status':


                $comment_id = $args[0] ?? 0;
                $status = $args[1] ?? '';
                $comment = get_comment( $comment_id );
                if ( ! $comment ) return false;


                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'status'     => $status,
                    'content'    => $comment->comment_content,
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
            'untrash_post'          => ['label'=>'Untrash Post'],
            'untrash_comment'       => ['label'=>'Untrash Comment'],
            'update_comment_count'  => ['label'=>'Update Comment Count'],
            'set_comment_status'    => ['label'=>'Set Comment Status'],

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
   if ( $action === 'untrash_post' ) {
            return [
                [
                    'key'=>'post_id',
                    'label'=>'Post ID',
                    'type'=>'expression',
                    'required'=>true,
                ],
            ];
        }


        if ( $action === 'untrash_comment' ) {
            return [
                [
                    'key'=>'comment_id',
                    'label'=>'Comment ID',
                    'type'=>'expression',
                    'required'=>true,
                ],
            ];
        }


        if ( $action === 'update_comment_count' ) {
            return [
                [
                    'key'=>'post_id',
                    'label'=>'Post ID',
                    'type'=>'expression',
                    'required'=>true,
                ],
            ];
        }


        if ( $action === 'set_comment_status' ) {
            return [
                [
                    'key'=>'comment_id',
                    'label'=>'Comment ID',
                    'type'=>'expression',
                    'required'=>true,
                ],
                [
                    'key'=>'status',
                    'label'=>'Status',
                    'type'=>'select',
                    'options'=>[
                        ['label'=>'Approved','value'=>'1'],
                        ['label'=>'Pending','value'=>'0'],
                        ['label'=>'Spam','value'=>'spam'],
                        ['label'=>'Trash','value'=>'trash'],
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
           case 'untrash_post':
                $result = wp_untrash_post( $config['post_id'] );
                return ['port'=>'main','data'=>['success'=>(bool)$result,'post_id'=>$config['post_id']]];


            case 'untrash_comment':
                $result = wp_untrash_comment( $config['comment_id'] );
                return ['port'=>'main','data'=>['success'=>(bool)$result,'comment_id'=>$config['comment_id']]];


            case 'update_comment_count':
                wp_update_comment_count( $config['post_id'] );
                $post = get_post( $config['post_id'] );
                return ['port'=>'main','data'=>['post_id'=>$config['post_id'],'comment_count'=>$post->comment_count ?? 0]];


            case 'set_comment_status':
                $result = wp_set_comment_status( $config['comment_id'], $config['status'] );
                return ['port'=>'main','data'=>['success'=>(bool)$result,'comment_id'=>$config['comment_id'],'status'=>$config['status']]];                
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
        $users = get_users(['search'=>$q['search'] ?? '']);
        return array_map(fn($u)=>[
            'ID'=>$u->ID,
            'name'=>$u->display_name,
            'email'=>$u->user_email
        ], $users);
    }
}
