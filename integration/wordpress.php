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
            'publish_post'              => ['label' => 'Post Published',           'hook' => 'publish_post'],
            'post_updated'              => ['label' => 'Post Updated',             'hook' => 'post_updated'],
            'transition_post_status'    => ['label' => 'Post Status Updated',      'hook' => 'transition_post_status'],
            'wp_trash_post'             => ['label' => 'Post Status Updated',      'hook' => 'wp_trash_post'],           
            'wp_insert_post'            => ['label' => 'Revision Creation',        'hook' => 'wp_insert_post'],
            'delete_post'               => ['label' => 'Delete Post',              'hook' => 'delete_post'],
            'user_register'             => ['label' => 'User Registered',          'hook' => 'user_register'],
            'comment_post'              => ['label' => 'Comment Post',             'hook' => 'comment_post'],
            'pre_comment_approved'      => ['label' => 'Pre-Approve Comment',      'hook' => 'pre_comment_approved'],
            'edit_comment'              => ['label' => 'Edit Comment',             'hook' => 'edit_comment'],
            'delete_comment'            => ['label' => 'Comment Deletion',         'hook' => 'delete_comment'],
            'trashed_comment'           => ['label' => 'Comment Trashed',          'hook' => 'trashed_comment'],
            'untrashed_comment'         => ['label' => 'Comment Untrashed',        'hook' => 'untrashed_comment'],
            'transition_comment_status' => ['label' => 'Comment Status Changed',   'hook' => 'transition_comment_status'],
            'untrashed_post'            => ['label' => 'Post Untrashed',           'hook' => 'untrashed_post'],
            'wp_update_comment_count'   => ['label' => 'Comment Count Updated',    'hook' => 'wp_update_comment_count'],
            'wp_set_comment_status'     => ['label' => 'Comment Status Set',       'hook' => 'wp_set_comment_status'],
             'wp_login'                 => ['label' => 'User Logged in',           'hook' => 'wp_login'],
             'wp_login_failed'                 => ['label' => 'User Logged Failed', 'hook' => 'wp_login_failed'],
             'wp_logout'                => ['label' => 'User logout',               'hook' => 'wp_logout'],
            'update_blog_public'        => ['label' => 'Update Blog Public',       'hook' => 'update_blog_public'],
            'update_blog_status'        => ['label' => 'Update Blog Status',       'hook' => 'update_blog_status'],
            'update_option'             => ['label' => 'Update Option',            'hook' => 'update_option'],
            'upgrader_process_complete' => ['label' => 'Upgrader Process Complete', 'hook' => 'upgrader_process_complete'],
            'wp_after_insert_post'      => ['label' => 'WP After Insert Post',     'hook' => 'wp_after_insert_post'],
            'wp_authenticate'           => ['label' => 'WP Authenticate',          'hook' => 'wp_authenticate'],
            'validate_password_reset'   => ['label' => 'Validate Password Reset',  'hook' => 'validate_password_reset'],
            'wpmu_activate_user'        => ['label' => 'Activate User',            'hook' => 'wpmu_activate_user'],
            'wp_update_user'            => ['label' => 'Update User',              'hook' => 'wp_update_user'],
            'wpmu_delete_user'          => ['label' => 'Delete User',              'hook' => 'wpmu_delete_user'],
            'wpmu_new_blog'             => ['label' => 'New Blog Created',         'hook' => 'wpmu_new_blog'],
            'wpmu_new_user'             => ['label' => 'New User Created',         'hook' => 'wpmu_new_user'],
            'wp_insert_comment'         => ['label' => 'Comment Created',          'hook' => 'wp_insert_comment'],
            'comment_reply'             => ['label' => 'Comment Reply',            'hook' => 'comment_reply'],

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
        error_log('check comment_id:' . print_r([
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                ], true));
                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                ];

            case 'transition_post_status':

                $new_status = $args[0] ?? '';
                $old_status = $args[1] ?? '';
                $post = get_post( $args[2] ?? 0 );
                if ( ! $post ) return false;
                // Only run trigger if old status is not 'new'
                if ( $old_status == 'new' ) {
                    return false;
                }

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                ];

            case 'wp_insert_post':

                $post_id = $args[0] ?? 0;
                $post = get_post( $post_id );
                if ( ! $post || $post->post_type !== 'revision' ) return false;

                $parent_post = get_post( $post->post_parent );
                return [
                    'revision_id'    => $post->ID,
                    'parent_post_id' => $post->post_parent,
                    'parent_title'   => $parent_post->post_title ?? '',
                    'post_type'      => $parent_post->post_type ?? '',
                ];
            case 'wp_trash_post':
                $post_id = $args[0] ?? 0;
                $post = get_post( $post_id );
                 if ( ! $post ) return false;

                   return [

                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,

                ];
            case 'delete_post':

                $post = get_post( $args[0] ?? 0 );
                if ( ! $post ) return false;

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                ];

            case 'pre_comment_approved':

                $approved = $args[0] ?? null;
                $comment_data = $args[1] ?? [];
                 error_log('check pre_comment_approved:' . print_r([
                    'approved'       => $approved,
                    'comment_author' => $comment_data['comment_author'] ?? '',
                    'comment_email'  => $comment_data['comment_author_email'] ?? '',
                    'comment_content'=> $comment_data['comment_content'] ?? '',
                    'post_id'        => $comment_data['comment_post_ID'] ?? 0,
                ] , true));

                return [
                    'approved'       => $approved,
                    'comment_author' => $comment_data['comment_author'] ?? '',
                    'comment_email'  => $comment_data['comment_author_email'] ?? '',
                    'comment_content'=> $comment_data['comment_content'] ?? '',
                    'post_id'        => $comment_data['comment_post_ID'] ?? 0,
                ];

            case 'edit_comment':

                $comment_id = $args[0] ?? 0;
                $comment = get_comment( $comment_id );
                if ( ! $comment ) return false;

                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'content'    => $comment->comment_content,
                    'status'     => $comment->comment_approved,
                ];

            case 'delete_comment':

                $comment_id = $args[0] ?? 0;
                $comment = get_comment( $comment_id );
                if ( ! $comment ) return false;

                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'content'    => $comment->comment_content,
                    'author'     => $comment->comment_author,
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
    $status     = $args[1] ?? '';
    $comment = get_comment( $comment_id );

    if ( ! $comment ) {
        return false;
    }

    return [
        'comment_id' => $comment->comment_ID,
        'post_id'    => $comment->comment_post_ID,
        'status'     => $status,
        'content'    => $comment->comment_content,
    ];

    case 'wp_login':
                $user_login = $args[0] ?? '';
                $user       = $args[1] ?? null;
           if ( ! $user instanceof \WP_User ) {
        return false;
         } 

    return [
        'user_id'    => $user->ID,
        'username'   => $user_login,
        'email'      => $user->user_email,
        'roles'      => $user->roles,
        'login_time' => current_time( 'mysql' ),
    ];

    case 'wp_login_failed':
      $user_login = $args[0] ?? '';
    return [
            'user_id'    => null, // No WP_User object exists
            'username'   => $user_login,
            'email'      => null,
            'roles'      => [], // No roles on failed login
            'login_time' => current_time( 'mysql' ),
            'user_exists'=> username_exists( $user_login ) ? true : false, // optional
        ];
       case 'wp_logout':
        $user = wp_get_current_user();    
        if (! $user instanceof \WP_User || $user->ID !== 0) return false;
        return [
            'user_id'     => $user->ID,
            'username'    => $user->user_login,
            'email'       => $user->user_email,
            'roles'       => $user->roles,
            'logout_time' => current_time('mysql'),
        ];

            case 'update_blog_public':

                $blog_id = $args[0] ?? 0;
                $value = $args[1] ?? '';
       
                return [
                    'blog_id' => $blog_id,
                    'public_value' => $value,
                    'timestamp' => current_time('mysql'),
                ];

            case 'update_blog_status':

                $blog_id = $args[0] ?? 0;
                $pref = $args[1] ?? '';
                $value = $args[2] ?? '';

                return [
                    'blog_id' => $blog_id,
                    'preference' => $pref,
                    'value' => $value,
                    'timestamp' => current_time('mysql'),
                ];

            case 'update_option':

                $option = $args[0] ?? '';
                $old_value = $args[1] ?? null;
                $value = $args[2] ?? null;

                return [
                    'option_name' => $option,
                    'old_value' => $old_value,
                    'new_value' => $value,
                    'timestamp' => current_time('mysql'),
                ];

            case 'upgrader_process_complete':

                $upgrader = $args[0] ?? null;
                $hook_extra = $args[1] ?? [];

                return [
                    'upgrader_type' => get_class($upgrader) ?? 'unknown',
                    'action' => $hook_extra['action'] ?? '',
                    'type' => $hook_extra['type'] ?? '',
                    'bulk' => $hook_extra['bulk'] ?? false,
                    'plugins' => $hook_extra['plugins'] ?? [],
                    'themes' => $hook_extra['themes'] ?? [],
                    'timestamp' => current_time('mysql'),
                ];

            case 'wp_after_insert_post':

                $post_id = $args[0] ?? 0;
                $post = get_post( $post_id );
                $update = $args[1] ?? false;

         
                if ( ! $post ) return false;

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                    'is_update'  => $update,
                    'timestamp'  => current_time('mysql'),
                ];

            case 'wp_authenticate':

                $user_login = $args[0] ?? '';
                $user_password = $args[1] ?? '';

                return [
                    'username' => $user_login,
                    'password_length' => strlen($user_password),
                    'timestamp' => current_time('mysql'),
                ];

            case 'validate_password_reset':

                $errors = $args[0] ?? null;
                $user = $args[1] ?? null;

                $error_codes = [];
                if ( $errors instanceof \WP_Error ) {
                    $error_codes = $errors->get_error_codes();
                }

                $user_data = [];
                if ( $user instanceof \WP_User ) {
                    $user_data = [
                        'user_id' => $user->ID,
                        'username' => $user->user_login,
                        'email' => $user->user_email,
                    ];
                }

                return [
                    'has_errors' => !empty($error_codes),
                    'error_codes' => $error_codes,
                    'user_data' => $user_data,
                    'timestamp' => current_time('mysql'),
                ];
 case 'wpmu_activate_user':
        return [
            'user_id'   => $args[0] ?? 0,
            'password'  => $args[1] ?? '',
            'meta'      => $args[2] ?? [],
            'timestamp' => current_time( 'mysql' ),
        ];

    case 'wp_update_user':
        return [
            'user_id'       => $args[0] ?? 0,
            'userdata'      => $args[1] ?? [],
            'userdata_raw'  => $args[2] ?? [],
            'timestamp'     => current_time( 'mysql' ),
        ];

    case 'wpmu_delete_user':
          error_log('check blog_id:' . print_r( [
            'user_id'   => $args[0] ?? 0,
            'user'      => $args[1] ?? null,
            'timestamp' => current_time( 'mysql' ),
        ] , true));

        return [
            'user_id'   => $args[0] ?? 0,
            'user'      => $args[1] ?? null,
            'timestamp' => current_time( 'mysql' ),
        ];

    case 'wpmu_new_blog':
        return [
            'site_id'    => $args[0] ?? 0,
            'user_id'    => $args[1] ?? 0,
            'domain'     => $args[2] ?? '',
            'path'       => $args[3] ?? '',
            'network_id' => $args[4] ?? 0,
            'meta'       => $args[5] ?? [],
            'timestamp'  => current_time( 'mysql' ),
        ];

    case 'wpmu_new_user':
        return [
            'user_id'   => $args[0] ?? 0,
            'timestamp' => current_time( 'mysql' ),
        ];

    case 'wp_insert_comment':
        $comment_id = $args[0] ?? 0;
        $comment = get_comment($comment_id);
        if (!$comment) return false;
        return [
            'comment_id' => $comment->comment_ID,
            'post_id' => $comment->comment_post_ID,
            'author' => $comment->comment_author,
            'author_email' => $comment->comment_author_email,
            'content' => $comment->comment_content,
            'timestamp' => current_time('mysql'),
        ];

    case 'comment_reply':
        $comment_id = $args[0] ?? 0;
        $parent_id = $args[1] ?? 0;
        $comment = get_comment($comment_id);
        error_log('check comment_id:' . print_r([
            'comment_id' => $comment->comment_ID,
            'parent_id' => $parent_id,
            'post_id' => $comment->comment_post_ID,
            'author' => $comment->comment_author,
            'content' => $comment->comment_content,
            'timestamp' => current_time('mysql'),
        ], true));
        if (!$comment) return false;
        return [
            'comment_id' => $comment->comment_ID,
            'parent_id' => $parent_id,
            'post_id' => $comment->comment_post_ID,
            'author' => $comment->comment_author,
            'content' => $comment->comment_content,
            'timestamp' => current_time('mysql'),
        ];




        }

        return false;
    }

    /* =====================================================
     * ACTIONS
     * ===================================================== */

    public static function get_actions(): array {
        return [
            'create_post'               => ['label'=>'Create Post'],
            'update_option'             => ['label'=>'Update Option'],
            'untrash_post'              => ['label'=>'Untrash Post'],
            'untrash_comment'           => ['label'=>'Untrash Comment'],
            'update_comment_count'      => ['label'=>'Update Comment Count'],
            'set_comment_status'        => ['label'=>'Set Comment Status'],
            'get_post_comments_all'     => ['label'=>'Get Post Comments (All)'],
            'get_post_comments_single'  => ['label'=>'Get Post Comments (Single Post)'],
            'get_user_comments'         => ['label'=>'Get User Comments'],
            'get_user_comments_email'   => ['label'=>'Get User Comments (By Email)'],
            'get_comment_metadata_all'  => ['label'=>'Get Comment Metadata (All)'],
            'get_comment_metadata_single' => ['label'=>'Get Comment Metadata (Single)'],
            'create_comment'            => ['label'=>'Create New Comment'],
            'reply_comment'             => ['label'=>'Reply To Comment'],
            'delete_comment'            => ['label'=>'Delete Comment'],
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

        if ( $action === 'get_post_comments_single' ) {
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'get_user_comments' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'get_user_comments_email' ) {
            return [
                ['key'=>'email','label'=>'Email','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'get_comment_metadata_single' ) {
            return [
                ['key'=>'comment_id','label'=>'Comment ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'create_comment' ) {
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
                ['key'=>'author_name','label'=>'Author Name','type'=>'expression','required'=>true],
                ['key'=>'author_email','label'=>'Author Email','type'=>'expression','required'=>true],
                ['key'=>'content','label'=>'Comment Content','type'=>'textarea','required'=>true],
            ];
        }

        if ( $action === 'reply_comment' ) {
            return [
                ['key'=>'parent_id','label'=>'Parent Comment ID','type'=>'expression','required'=>true],
                ['key'=>'author_name','label'=>'Author Name','type'=>'expression','required'=>true],
                ['key'=>'author_email','label'=>'Author Email','type'=>'expression','required'=>true],
                ['key'=>'content','label'=>'Reply Content','type'=>'textarea','required'=>true],
            ];
        }

        if ( $action === 'delete_comment' ) {
            return [
                ['key'=>'comment_id','label'=>'Comment ID','type'=>'expression','required'=>true],
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

            case 'get_post_comments_all':
                $comments = get_comments();
                return ['port'=>'main','data'=>['comments'=>$comments]];

            case 'get_post_comments_single':
                $comments = get_comments(['post_id'=>$config['post_id']]);
                return ['port'=>'main','data'=>['comments'=>$comments,'post_id'=>$config['post_id']]];

            case 'get_user_comments':
                $comments = get_comments(['user_id'=>$config['user_id']]);
                
                return ['port'=>'main','data'=>['comments'=>$comments,'user_id'=>$config['user_id']]];

            case 'get_user_comments_email':
                $comments = get_comments(['author_email'=>$config['email']]);
                error_log('check comments:' . print_r( ['port'=>'main','data'=>['comments'=>$comments,'email'=>$config['email']]] , true));
                return ['port'=>'main','data'=>['comments'=>$comments,'email'=>$config['email']]];

            case 'get_comment_metadata_all':
                $comments = get_comments();
                $metadata = [];
                foreach($comments as $comment) {
                    $metadata[] = ['comment_id'=>$comment->comment_ID,'meta'=>get_comment_meta($comment->comment_ID)];
                }
              
                return ['port'=>'main','data'=>['metadata'=>$metadata]];

            case 'get_comment_metadata_single':
                $meta = get_comment_meta($config['comment_id']);               
                return ['port'=>'main','data'=>['comment_id'=>$config['comment_id'],'metadata'=>$meta]];

            case 'create_comment':
                $comment_id = wp_insert_comment([
                    'comment_post_ID'=>$config['post_id'],
                    'comment_author'=>$config['author_name'],
                    'comment_author_email'=>$config['author_email'],
                    'comment_content'=>$config['content'],
                    'comment_approved'=>1
                ]);        
                return ['port'=>'main','data'=>['comment_id'=>$comment_id]];

            case 'reply_comment':
                $parent = get_comment($config['parent_id']);
                $comment_id = wp_insert_comment([
                    'comment_post_ID'=>$parent->comment_post_ID,
                    'comment_parent'=>$config['parent_id'],
                    'comment_author'=>$config['author_name'],
                    'comment_author_email'=>$config['author_email'],
                    'comment_content'=>$config['content'],
                    'comment_approved'=>1
                ]);
              
                return ['port'=>'main','data'=>['comment_id'=>$comment_id,'parent_id'=>$config['parent_id']]];

            case 'delete_comment':
                $result = wp_delete_comment($config['comment_id'],true);

                return ['port'=>'main','data'=>['success'=>(bool)$result,'comment_id'=>$config['comment_id']]];
                
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
