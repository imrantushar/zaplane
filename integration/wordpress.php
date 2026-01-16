<?php
namespace Zaplane\Integration;

use Zaplane\Classes\IntegrationBase;
use Zaplane\Classes\WordpressHelpers;

if ( ! defined( 'ABSPATH' ) ) exit;

require_once ZAPLANE_INCLUDES_DIR_PATH . 'classes/wordpressHelper.php';

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
            case 'set_user_role':
                $user_data = WordpressHelpers::get_user_payload( $args[0] ?? 0 );
                if ( ! $user_data ) {
                    return false;
                }
                $user_data['role'] = $args[1] ?? ( $user_data['role'] ?? '' );
                $user_data['old_roles'] = $args[2] ?? [];
                return $user_data;

            case 'show_user_profile':
            case 'edit_user_profile':
                return WordpressHelpers::get_user_payload( $args[0] ?? 0 );

            case 'personal_options_update':
            case 'edit_user_profile_update':
                return WordpressHelpers::get_user_payload( $args[0] ?? 0 );

            case 'profile_update':
                $old_user = (object) ( $args[1] ?? [] );
                return WordpressHelpers::get_user_payload(
                    $args[0] ?? 0,
                    [
                        'old_email' => $old_user->user_email ?? '',
                        'old_role' => $old_user->roles[0] ?? '',
                    ]
                );

            case 'remove_user_from_blog':
                return WordpressHelpers::get_user_payload(
                    $args[0] ?? 0,
                    [
                        'blog_id' => $args[1] ?? 0,
                        'reassign' => $args[2] ?? 0,
                    ]
                );

            case 'delete_user':
                return WordpressHelpers::get_user_payload(
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
                return WordpressHelpers::get_user_payload( $args[0] ?? '' ) ?: [
                    'event' => $node['event'],
                    'user_login' => $args[0] ?? '',
                ];

            case 'password_reset':
                return WordpressHelpers::get_user_payload( $args[0] ?? 0 );

            case 'after_password_reset':
                return WordpressHelpers::get_user_payload(
                    $args[0] ?? 0,
                    [ 'after_reset' => true ]
                );

            case 'create_term':
            case 'created_term':
            case 'edit_term':
            case 'edited_term':
                return WordpressHelpers::get_term_payload(
                    $args[0] ?? 0,
                    $args[2] ?? '',
                    $args[1] ?? 0,
                    [ 'args' => $args[3] ?? [] ]
                );

            case 'saved_term':
                if ( empty( $args[3] ) ) {
                    return false;
                }
                return WordpressHelpers::get_term_payload(
                    $args[0] ?? 0,
                    $args[2] ?? '',
                    $args[1] ?? 0,
                    [
                        'update' => true,
                        'args' => $args[4] ?? [],
                    ]
                );

            case 'delete_term':
                return WordpressHelpers::get_term_payload(
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
                return WordpressHelpers::get_term_payload( 0, '', $args[0] ?? 0 );

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
            'create_post'   => ['label'=>'Create Post'],
            'update_option' => ['label'=>'Update Option'],
            'create_user' => ['label'=>'Create User'],
            'update_user' => ['label'=>'Update User'],
            'delete_user' => ['label'=>'Delete User'],
            'get_users' => ['label'=>'Get All Users'],
            'get_users_by_role' => ['label'=>'Get All Users by Role'],
            'get_user_by_id' => ['label'=>'Get User by ID'],
            'get_user_by_email' => ['label'=>'Get User by Email'],
            'get_user_by_field' => ['label'=>'Get User by Field'],
            'get_user_meta_all' => ['label'=>'Get User Metadata (All)'],
            'get_user_meta_single' => ['label'=>'Get User Metadata (Single)'],
            'update_user_meta' => ['label'=>'Update User Metadata'],
            'send_password_reset_email' => ['label'=>'Send Password Reset Email'],
            'authenticate_user' => ['label'=>'Authenticate User'],
            'logout_user' => ['label'=>'Logout User'],
            'activate_user' => ['label'=>'Activate User'],
            'deactivate_user' => ['label'=>'Deactivate User'],
            'create_role' => ['label'=>'Create Role'],
            'delete_role' => ['label'=>'Delete Role'],
            'add_user_role' => ['label'=>'Add User Role'],
            'remove_user_role' => ['label'=>'Remove User Role'],
            'update_user_role' => ['label'=>'Update User Role'],
            'get_roles' => ['label'=>'Get All Roles'],
            'get_caps' => ['label'=>'Get All Capabilities'],
            'get_role_caps' => ['label'=>'Get Role Capabilities'],
            'add_role_caps' => ['label'=>'Add Role Capabilities'],
            'remove_role_caps' => ['label'=>'Remove Role Capabilities'],
            'get_user_caps' => ['label'=>'Get User Capabilities'],
            'add_user_caps' => ['label'=>'Add User Capabilities'],
            'remove_user_caps' => ['label'=>'Remove User Capabilities'],
            'get_term' => ['label'=>'Get Term (Single)'],
            'get_terms_by_taxonomy' => ['label'=>'Get Term by Taxonomy'],
            'get_term_by_field' => ['label'=>'Get Term by Field'],
            'create_term' => ['label'=>'Create New Term'],
            'update_term' => ['label'=>'Update Term'],
            'delete_term' => ['label'=>'Delete Term'],
            'register_taxonomy' => ['label'=>'Register Taxonomy'],
            'unregister_taxonomy' => ['label'=>'Unregister Taxonomy'],
            'get_taxonomies' => ['label'=>'Get Taxonomy (All)'],
            'get_taxonomy' => ['label'=>'Get Taxonomy (Single)'],
            'add_taxonomy_to_post' => ['label'=>'Add Taxonomy to Post'],
            'remove_taxonomy_from_post' => ['label'=>'Remove Taxonomy from Post'],
            'bulk_assign_terms_to_posts' => ['label'=>'Bulk Assign Terms to Posts'],
            'bulk_remove_terms_from_posts' => ['label'=>'Bulk Remove Terms from Posts'],
            'create_category' => ['label'=>'Create Category'],
            'update_category' => ['label'=>'Update Category'],
            'delete_category' => ['label'=>'Delete Category'],
            'add_category_to_post' => ['label'=>'Add Category to Post'],
            'get_categories' => ['label'=>'Get Category (All)'],
            'get_category' => ['label'=>'Get Category (Single)'],
            'create_post_tag' => ['label'=>'Create Post Tag'],
            'update_post_tag' => ['label'=>'Update Post Tag'],
            'delete_post_tag' => ['label'=>'Delete Post Tag'],
            'add_tags_to_post' => ['label'=>'Add Tags to Post'],
            'remove_tags_from_post' => ['label'=>'Remove Tags from Post'],
            'get_post_tags' => ['label'=>'Get Post Tag (All)'],
            'get_post_tag' => ['label'=>'Get Post Tag (Single)'],
            'create_site' => ['label'=>'Create New Site'],
            'delete_site' => ['label'=>'Delete Site'],
            'add_user_to_site' => ['label'=>'Add User to Site'],
            'remove_user_from_site' => ['label'=>'Remove User from Site'],
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
            'add_plugin_theme_option'   => ['label'=>'Add Option'],
            'update_option_advanced'         => ['label' => 'Update Option'],
            'delete_option'             => ['label'=>'Delete Option'],
            'generate_attachment_metadata' => ['label'=>'Generate Attachment Metadata'],
            'regenerate_image_sizes'    => ['label'=>'Resize / Regenerate Image Sizes'],
            'set_featured_image'        => ['label'=>'Set Media Featured Image'],
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

        if ($action === 'add_plugin_theme_option' ) {
          
            return [
                ['key'=>'option_name','label'=>'Option','type'=>'text','required'=>true],
                ['key'=>'value','label'=>'Value','type'=>'expression'],
            ];
        }
        if ( $action === 'update_option_advanced' ) {
    return [
        ['key' => 'option_name', 'label' => 'Option Name', 'type' => 'text', 'required' => true],
        ['key' => 'value', 'label' => 'New Value', 'type' => 'expression'],
    ];
}
        if ($action === 'delete_option' ) {
            return [
                ['key'=>'option_name','label'=>'Option','type'=>'text','required'=>true],
            ];
        }

        if ($action === 'generate_attachment_metadata' ) {
            return [
                ['key'=>'attachment_id','label'=>'Attachment ID','type'=>'expression','required'=>true],
            ];
        }

        if ($action === 'regenerate_image_sizes' ) {
            return [
                ['key'=>'attachment_id','label'=>'Attachment ID','type'=>'expression','required'=>true],
            ];
        }

        if ($action === 'set_featured_image' ) {   
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
                ['key'=>'attachment_id','label'=>'Attachment ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'create_user' ) {
            return [
                ['key'=>'user_login','label'=>'Username','type'=>'text','required'=>true],
                ['key'=>'user_email','label'=>'Email','type'=>'text','required'=>true],
                ['key'=>'user_pass','label'=>'Password','type'=>'text'],
                ['key'=>'display_name','label'=>'Display Name','type'=>'text'],
                ['key'=>'first_name','label'=>'First Name','type'=>'text'],
                ['key'=>'last_name','label'=>'Last Name','type'=>'text'],
                ['key'=>'role','label'=>'Role','type'=>'text'],
            ];
        }

        if ( $action === 'update_user' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'user_email','label'=>'Email','type'=>'text'],
                ['key'=>'user_pass','label'=>'Password','type'=>'text'],
                ['key'=>'display_name','label'=>'Display Name','type'=>'text'],
                ['key'=>'first_name','label'=>'First Name','type'=>'text'],
                ['key'=>'last_name','label'=>'Last Name','type'=>'text'],
                ['key'=>'role','label'=>'Role','type'=>'text'],
            ];
        }

        if ( $action === 'delete_user' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'reassign','label'=>'Reassign User ID','type'=>'expression'],
            ];
        }

        if ( $action === 'get_users' ) {
            return [
                ['key'=>'search','label'=>'Search','type'=>'text'],
                ['key'=>'number','label'=>'Limit','type'=>'expression'],
            ];
        }

        if ( $action === 'get_users_by_role' ) {
            return [
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
                ['key'=>'search','label'=>'Search','type'=>'text'],
                ['key'=>'number','label'=>'Limit','type'=>'expression'],
            ];
        }

        if ( $action === 'get_user_by_id' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'get_user_by_email' ) {
            return [
                ['key'=>'user_email','label'=>'Email','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_user_by_field' ) {
            return [
                ['key'=>'field','label'=>'Field','type'=>'text','required'=>true],
                ['key'=>'value','label'=>'Value','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_user_meta_all' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'get_user_meta_single' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'meta_key','label'=>'Meta Key','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'update_user_meta' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'meta_key','label'=>'Meta Key','type'=>'text','required'=>true],
                ['key'=>'meta_value','label'=>'Meta Value','type'=>'expression'],
            ];
        }

        if ( $action === 'send_password_reset_email' ) {
            return [
                ['key'=>'user_login','label'=>'Username or Email','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'authenticate_user' ) {
            return [
                ['key'=>'user_login','label'=>'Username or Email','type'=>'text','required'=>true],
                ['key'=>'user_password','label'=>'Password','type'=>'text','required'=>true],
                ['key'=>'remember','label'=>'Remember (true/false)','type'=>'text'],
                ['key'=>'secure_cookie','label'=>'Secure Cookie (true/false)','type'=>'text'],
            ];
        }

        if ( $action === 'logout_user' ) {
            return [];
        }

        if ( $action === 'activate_user' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'deactivate_user' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'create_role' ) {
            return [
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
                ['key'=>'display_name','label'=>'Display Name','type'=>'text','required'=>true],
                ['key'=>'capabilities','label'=>'Capabilities (comma separated)','type'=>'text'],
            ];
        }

        if ( $action === 'delete_role' ) {
            return [
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'add_user_role' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'remove_user_role' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'update_user_role' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_role_caps' ) {
            return [
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'add_role_caps' ) {
            return [
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
                ['key'=>'caps','label'=>'Capabilities (comma separated)','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'remove_role_caps' ) {
            return [
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
                ['key'=>'caps','label'=>'Capabilities (comma separated)','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_user_caps' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'add_user_caps' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'caps','label'=>'Capabilities (comma separated)','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'remove_user_caps' ) {
            return [
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'caps','label'=>'Capabilities (comma separated)','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_term' ) {
            return [
                ['key'=>'term_id','label'=>'Term ID','type'=>'expression','required'=>true],
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_terms_by_taxonomy' ) {
            return [
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_term_by_field' ) {
            return [
                ['key'=>'field','label'=>'Field','type'=>'text','required'=>true],
                ['key'=>'value','label'=>'Value','type'=>'text','required'=>true],
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'create_term' ) {
            return [
                ['key'=>'name','label'=>'Name','type'=>'text','required'=>true],
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
                ['key'=>'slug','label'=>'Slug','type'=>'text'],
                ['key'=>'parent','label'=>'Parent ID','type'=>'expression'],
                ['key'=>'description','label'=>'Description','type'=>'textarea'],
            ];
        }

        if ( $action === 'update_term' ) {
            return [
                ['key'=>'term_id','label'=>'Term ID','type'=>'expression','required'=>true],
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
                ['key'=>'name','label'=>'Name','type'=>'text'],
                ['key'=>'slug','label'=>'Slug','type'=>'text'],
                ['key'=>'parent','label'=>'Parent ID','type'=>'expression'],
                ['key'=>'description','label'=>'Description','type'=>'textarea'],
            ];
        }

        if ( $action === 'delete_term' ) {
            return [
                ['key'=>'term_id','label'=>'Term ID','type'=>'expression','required'=>true],
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'register_taxonomy' ) {
            return [
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
                ['key'=>'object_type','label'=>'Object Type (comma separated)','type'=>'text','required'=>true],
                ['key'=>'args','label'=>'Args (JSON)','type'=>'text'],
            ];
        }

        if ( $action === 'unregister_taxonomy' ) {
            return [
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_taxonomy' ) {
            return [
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'add_taxonomy_to_post' ) {
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
                ['key'=>'terms','label'=>'Terms (comma separated)','type'=>'text','required'=>true],
                ['key'=>'append','label'=>'Append (true/false)','type'=>'text'],
            ];
        }

        if ( $action === 'remove_taxonomy_from_post' ) {
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
                ['key'=>'taxonomy','label'=>'Taxonomy','type'=>'text','required'=>true],
                ['key'=>'terms','label'=>'Terms (comma separated)','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'create_category' ) {
            return [
                ['key'=>'name','label'=>'Name','type'=>'text','required'=>true],
                ['key'=>'slug','label'=>'Slug','type'=>'text'],
                ['key'=>'parent','label'=>'Parent ID','type'=>'expression'],
                ['key'=>'description','label'=>'Description','type'=>'textarea'],
            ];
        }

        if ( $action === 'update_category' ) {
            return [
                ['key'=>'term_id','label'=>'Category ID','type'=>'expression','required'=>true],
                ['key'=>'name','label'=>'Name','type'=>'text'],
                ['key'=>'slug','label'=>'Slug','type'=>'text'],
                ['key'=>'parent','label'=>'Parent ID','type'=>'expression'],
                ['key'=>'description','label'=>'Description','type'=>'textarea'],
            ];
        }

        if ( $action === 'delete_category' ) {
            return [
                ['key'=>'term_id','label'=>'Category ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'add_category_to_post' ) {
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
                ['key'=>'categories','label'=>'Categories (comma separated)','type'=>'text','required'=>true],
                ['key'=>'append','label'=>'Append (true/false)','type'=>'text'],
            ];
        }

        if ( $action === 'get_category' ) {
            return [
                ['key'=>'category_id','label'=>'Category ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'create_post_tag' ) {
            return [
                ['key'=>'name','label'=>'Name','type'=>'text','required'=>true],
                ['key'=>'slug','label'=>'Slug','type'=>'text'],
                ['key'=>'description','label'=>'Description','type'=>'textarea'],
            ];
        }

        if ( $action === 'update_post_tag' ) {
            return [
                ['key'=>'term_id','label'=>'Tag ID','type'=>'expression','required'=>true],
                ['key'=>'name','label'=>'Name','type'=>'text'],
                ['key'=>'slug','label'=>'Slug','type'=>'text'],
                ['key'=>'description','label'=>'Description','type'=>'textarea'],
            ];
        }

        if ( $action === 'delete_post_tag' ) {
            return [
                ['key'=>'term_id','label'=>'Tag ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'add_tags_to_post' ) {
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
                ['key'=>'tags','label'=>'Tags (comma separated)','type'=>'text','required'=>true],
                ['key'=>'append','label'=>'Append (true/false)','type'=>'text'],
            ];
        }

        if ( $action === 'remove_tags_from_post' ) {
            return [
                ['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true],
                ['key'=>'tags','label'=>'Tags (comma separated)','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'get_post_tags' ) {
            return [];
        }

        if ( $action === 'get_post_tag' ) {
            return [
                ['key'=>'term_id','label'=>'Tag ID','type'=>'expression','required'=>true],
            ];
        }

        if ( $action === 'create_site' ) {
            return [
                ['key'=>'domain','label'=>'Domain','type'=>'text','required'=>true],
                ['key'=>'path','label'=>'Path','type'=>'text','required'=>true],
                ['key'=>'title','label'=>'Title','type'=>'text','required'=>true],
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'site_id','label'=>'Site ID','type'=>'expression'],
            ];
        }

        if ( $action === 'delete_site' ) {
            return [
                ['key'=>'blog_id','label'=>'Blog ID','type'=>'expression','required'=>true],
                ['key'=>'drop','label'=>'Drop Tables (true/false)','type'=>'text'],
            ];
        }

        if ( $action === 'add_user_to_site' ) {
            return [
                ['key'=>'blog_id','label'=>'Blog ID','type'=>'expression','required'=>true],
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
                ['key'=>'role','label'=>'Role','type'=>'text','required'=>true],
            ];
        }

        if ( $action === 'remove_user_from_site' ) {
            return [
                ['key'=>'blog_id','label'=>'Blog ID','type'=>'expression','required'=>true],
                ['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true],
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

            case 'add_plugin_theme_option':
                add_option($config['option_name'],$config['value']);
                return ['port'=>'main','data'=>['option_name'=>$config['option_name']]];
            case 'update_option_advanced':
                update_option( $config['option_name'], $config['value'] );
                return ['port'=>'main','data'=>[]];

            case 'create_user':
                return ['port'=>'main','data'=>wp_insert_user( $config )];

            case 'update_user':
                $config['ID'] = $config['user_id'];
                return ['port'=>'main','data'=>wp_update_user( $config )];

            case 'delete_user':
                return ['port'=>'main','data'=>wp_delete_user( $config['user_id'], $config['reassign'] ?? null )];

            case 'get_users':
                return ['port'=>'main','data'=>static::query_users( [ 'users' => get_users( $config ) ] )];

            case 'get_users_by_role':
                return ['port'=>'main','data'=>static::query_users( [ 'users' => get_users( $config ) ] )];

            case 'get_user_by_id':
                return ['port'=>'main','data'=>WordpressHelpers::get_user_payload( get_userdata( $config['user_id'] ) )];

            case 'get_user_by_email':
                return ['port'=>'main',
                'data'=>WordpressHelpers::get_user_payload( get_user_by( 'email',
                 $config['user_email'] ) )
                ];

            case 'get_user_by_field':
                return ['port'=>'main',
                'data'=>WordpressHelpers::get_user_payload( get_user_by( $config['field'],
                 $config['value'] ) )];

            case 'get_user_meta_all':
                return ['port'=>'main','data'=>get_user_meta( $config['user_id'] )];

            case 'get_user_meta_single':
                return ['port'=>'main','data'=>get_user_meta( $config['user_id'],
                 $config['meta_key'], true 
                 )];

            case 'update_user_meta':
                return ['port'=>'main','data'=>update_user_meta( $config['user_id'], 
                $config['meta_key'], 
                $config['meta_value'] ?? null
                 )];

            case 'send_password_reset_email':
                return ['port'=>'main','data'=>retrieve_password( $config['user_login'] ?? '' )];

            case 'authenticate_user':
                $creds = [
                    'user_login' => $config['user_login'] ?? '',
                    'user_password' => $config['user_password'] ?? '',
                    'remember' => ! empty( $config['remember'] ),
                ];
                $secure_cookie = ! empty( $config['secure_cookie'] );
                return ['port'=>'main','data'=>wp_signon( $creds, $secure_cookie )];

            case 'logout_user':
                wp_logout();
                return ['port'=>'main','data'=>true];

            case 'activate_user':
                return ['port'=>'main','data'=>wp_update_user( [
                    'ID' => $config['user_id'] ?? 0,
                    'user_status' => 0,
                ] )];

            case 'deactivate_user':
                return ['port'=>'main','data'=>wp_update_user( [
                    'ID' => $config['user_id'] ?? 0,
                    'user_status' => 1,
                ] )];

            case 'create_role':
                $role_key = sanitize_key( $config['role'] );
                return ['port'=>'main','data'=>WordpressHelpers::format_role_payload(
                    $role_key,
                    add_role( 
                        $role_key, 
                        $config['display_name'], 
                        WordpressHelpers::normalize_caps( $config['capabilities'] ) )
                )];

            case 'delete_role':
                return ['port'=>'main','data'=>remove_role( $config['role'] )];

            case 'add_user_role':
                $user = get_userdata( $config['user_id'] );
                $user->add_role( $config['role'] );
                return ['port'=>'main','data'=>(bool) $user];

            case 'remove_user_role':
                $user = get_userdata( $config['user_id'] );
                $user->remove_role( $config['role'] );
                return ['port'=>'main','data'=>(bool) $user];

            case 'update_user_role':
                $user = get_userdata( $config['user_id'] );
                $user->set_role( $config['role'] );
                return ['port'=>'main','data'=>(bool) $user];

            case 'get_roles':
                return ['port'=>'main','data'=>wp_roles()->roles ?? []];

            case 'get_caps':
                $roles = wp_roles()->roles ?? [];
                $caps = [];
                foreach ( $roles as $role ) {
                    foreach ( $role['capabilities'] ?? [] as $cap => $grant ) {
                        if ( $grant ) {
                            $caps[$cap] = true;
                        }
                    }
                }
                return ['port'=>'main','data'=>array_keys( $caps )];

            case 'get_role_caps':
                $role = get_role( $config['role'] );
                return ['port'=>'main','data'=>array_keys( $role->capabilities )];

            case 'add_role_caps':
                $role = get_role( $config['role'] );
                foreach ( WordpressHelpers::normalize_list( $config['caps'] ) as $cap ) {
                        $role->add_cap( $cap );
                    }
                return ['port'=>'main','data'=>true];

            case 'remove_role_caps':
                $role = get_role( $config['role'] );
                foreach ( WordpressHelpers::normalize_list( $config['caps'] ) as $cap ) {
                        $role->remove_cap( $cap );
                    }
                return ['port'=>'main','data'=>true];

            case 'get_user_caps':
                $user = get_userdata( $config['user_id'] );
                return ['port'=>'main','data'=>array_keys( $user->allcaps )];

            case 'add_user_caps':
                $user = get_userdata( $config['user_id'] );
                foreach ( WordpressHelpers::normalize_list( $config['caps'] ) as $cap ) {
                        $user->add_cap( $cap );
                    }
                return ['port'=>'main','data'=>true];

            case 'remove_user_caps':
                $user = get_userdata( $config['user_id'] );
                foreach ( WordpressHelpers::normalize_list( $config['caps'] ) as $cap ) {
                        $user->remove_cap( $cap );
                    }
                return ['port'=>'main','data'=>true];

            case 'get_term':
                return ['port'=>'main','data'=>get_term( $config['term_id'], $config['taxonomy'] )];

            case 'get_terms_by_taxonomy':
                return ['port'=>'main','data'=>get_terms( [
                    'taxonomy' => $config['taxonomy'],
                    'hide_empty' => false,
                ] )];

            case 'get_term_by_field':
                return ['port'=>'main','data'=>get_term_by(
                    $config['field'],
                    $config['value'],
                    $config['taxonomy']
                )];

            case 'create_term':
                return ['port'=>'main','data'=>wp_insert_term(
                    $config['name'],
                    $config['taxonomy'],
                    [
                        'slug' => $config['slug'],
                        'parent' => $config['parent'],
                        'description' => $config['description'],
                    ]
                )];

            case 'update_term':
                return ['port'=>'main','data'=>wp_update_term(
                    $config['term_id'],
                    $config['taxonomy'],
                    [
                        'name' => $config['name'],
                        'slug' => $config['slug'],
                        'description' => $config['description'],
                        'parent' => $config['parent'],
                    ]
                )];

            case 'delete_term':
                return ['port'=>'main','data'=>wp_delete_term(
                    $config['term_id'],
                    $config['taxonomy']
                )];

            case 'register_taxonomy':
                return ['port'=>'main','data'=>register_taxonomy(
                    $config['taxonomy'],
                    WordpressHelpers::normalize_list( $config['object_type'] ),
                    WordpressHelpers::normalize_taxonomy_args( $config['args'] )
                )];

            case 'unregister_taxonomy':
                return ['port'=>'main','data'=>unregister_taxonomy( $config['taxonomy'] )];

            case 'get_taxonomies':
                return ['port'=>'main','data'=>get_taxonomies( [], 'objects' )];

            case 'get_taxonomy':
                return ['port'=>'main','data'=>get_taxonomy( $config['taxonomy'] )];

            case 'add_taxonomy_to_post':
                $added = wp_set_object_terms(
                    $config['post_id'],
                    WordpressHelpers::normalize_list( $config['terms'] ),
                    $config['taxonomy'],
                    $config['append']
                );
                return ['port'=>'main','data'=>$added];

            case 'remove_taxonomy_from_post':
                $removed = wp_remove_object_terms(
                    $config['post_id'],
                    WordpressHelpers::normalize_list( $config['terms'] ),
                    $config['taxonomy']
                );
                return ['port'=>'main','data'=>$removed];

            case 'bulk_assign_terms_to_posts':
                $post_ids = WordpressHelpers::normalize_list( $config['post_ids'] );
                $terms = WordpressHelpers::normalize_list( $config['terms'] );
                $taxonomy = $config['taxonomy'];
                $append = $config['append'];
                $results = [];
                foreach ( $post_ids as $post_id ) {
                    $results[ $post_id ] = wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
                }
                return ['port'=>'main','data'=>$results];

            case 'bulk_remove_terms_from_posts':
                $post_ids = WordpressHelpers::normalize_list( $config['post_ids'] );
                $terms = WordpressHelpers::normalize_list( $config['terms'] );
                $taxonomy = $config['taxonomy'];
                $results = [];
                foreach ( $post_ids as $post_id ) {
                    $results[ $post_id ] = wp_remove_object_terms( $post_id, $terms, $taxonomy );
                }
                return ['port'=>'main','data'=>$results];

            case 'create_category':
                $created = wp_insert_category( [
                    'cat_name' => $config['name'],
                    'category_description' => $config['description'],
                    'category_parent' => $config['parent'],
                    'category_nicename' => $config['slug'],
                ] );
                return ['port'=>'main','data'=>$created];

            case 'update_category':
                $updated = wp_update_category( [
                    'cat_ID' => $config['term_id'],
                    'cat_name' => $config['name'],
                    'category_nicename' => $config['slug'],
                    'category_parent' => $config['parent'],
                    'category_description' => $config['description'],
                ] );
                return ['port'=>'main','data'=>$updated];

            case 'delete_category':
                return ['port'=>'main','data'=>wp_delete_term( $config['term_id'], 'category' )];

            case 'add_category_to_post':
                $added = wp_set_post_categories(
                    $config['post_id'],
                    WordpressHelpers::normalize_list( $config['categories'] ),
                    $config['append']
                );
                return ['port'=>'main','data'=>$added];

            case 'get_categories':
                return ['port'=>'main','data'=>get_categories( [ 'hide_empty' => false ] )];

            case 'get_category':
                return ['port'=>'main','data'=>get_category( $config['category_id'] )];

            case 'create_post_tag':
                return ['port'=>'main','data'=>wp_insert_term(
                    $config['name'],
                    'post_tag',
                    [
                        'slug' => $config['slug'],
                        'description' => $config['description'],
                    ]
                )];

            case 'update_post_tag':
                return ['port'=>'main','data'=>wp_update_term(
                    $config['term_id'],
                    'post_tag',
                    [
                        'name' => $config['name'],
                        'slug' => $config['slug'],
                        'description' => $config['description'],
                    ]
                )];

            case 'delete_post_tag':
                return ['port'=>'main',
                'data'=>wp_delete_term( $config['term_id'], 'post_tag' )];

            case 'add_tags_to_post':
                return ['port'=>'main','data'=>wp_set_post_tags(
                    $config['post_id'],
                    WordpressHelpers::normalize_list( $config['tags'] ),
                    $config['append']
                )];

            case 'remove_tags_from_post':
                return ['port'=>'main','data'=>wp_remove_object_terms(
                    $config['post_id'],
                    WordpressHelpers::normalize_list( $config['tags'] ),
                    'post_tag'
                )];

            case 'get_post_tags':
                return ['port'=>'main','data'=>get_terms( [ 'taxonomy' => 'post_tag' ] )];

            case 'get_post_tag':
                return ['port'=>'main','data'=>get_term( $config['term_id'], 'post_tag' )];

            case 'create_site':
                return ['port'=>'main','data'=>wpmu_create_blog(
                    $config['domain'],
                    $config['path'],
                    $config['title'],
                    $config['user_id'],
                    [],
                    $config['site_id'] ?? get_current_network_id()
                )];

            case 'delete_site':
                return ['port'=>'main','data'=>wpmu_delete_blog(
                    $config['blog_id'],
                    $config['drop']
                )];

            case 'add_user_to_site':
                return ['port'=>'main','data'=>add_user_to_blog(
                    $config['blog_id'],
                    $config['user_id'],
                    $config['role']
                )];

            case 'remove_user_from_site':
                return ['port'=>'main','data'=>remove_user_from_blog(
                    $config['user_id'],
                    $config['blog_id']
                )];
                return ['port'=>'main','data'=>['option_name'=>$config['option_name'],'value'=>$config['value']]];

            case 'delete_option':
                $result = delete_option($config['option_name']);
                return ['port'=>'main','data'=>['success'=>(bool)$result,'option_name'=>$config['option_name']]];

            case 'generate_attachment_metadata':
                $file = get_attached_file($config['attachment_id']);
                $metadata = wp_generate_attachment_metadata($config['attachment_id'],$file);
                wp_update_attachment_metadata($config['attachment_id'],$metadata);
                return ['port'=>'main','data'=>['attachment_id'=>$config['attachment_id'],'metadata'=>$metadata]];

            case 'regenerate_image_sizes':
                require_once(ABSPATH.'wp-admin/includes/image.php');
                $file = get_attached_file($config['attachment_id']);
                $metadata = wp_generate_attachment_metadata($config['attachment_id'],$file);
                wp_update_attachment_metadata($config['attachment_id'],$metadata);
                return ['port'=>'main','data'=>['attachment_id'=>$config['attachment_id'],'metadata'=>$metadata,'success'=>!empty($metadata)]];

            case 'set_featured_image':
                $result = set_post_thumbnail($config['post_id'],$config['attachment_id']);
                return ['port'=>'main','data'=>['success'=>(bool)$result,'post_id'=>$config['post_id'],'attachment_id'=>$config['attachment_id']]];
                
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
            'taxonomies' => [ self::class, 'query_taxonomies' ],
            'categories' => [ self::class, 'query_categories' ],
            'roles'      => [ self::class, 'query_roles' ],
            'caps'       => [ self::class, 'query_caps' ],
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
