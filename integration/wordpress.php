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
                    'taxonomy' => $config['taxonomy'] ?? '',
                    'hide_empty' => false,
                ] )];

            case 'get_term_by_field':
                return ['port'=>'main','data'=>get_term_by(
                    $config['field'] ?? '',
                    $config['value'] ?? '',
                    $config['taxonomy'] ?? ''
                )];

            case 'create_term':
                return ['port'=>'main','data'=>wp_insert_term(
                    $config['name'] ?? '',
                    $config['taxonomy'] ?? '',
                    [
                        'slug' => $config['slug'] ?? '',
                        'parent' => $config['parent'] ?? 0,
                        'description' => $config['description'] ?? '',
                    ]
                )];

            case 'update_term':
                return ['port'=>'main','data'=>wp_update_term(
                    $config['term_id'] ?? 0,
                    $config['taxonomy'] ?? '',
                    [
                        'name' => $config['name'] ?? '',
                        'slug' => $config['slug'] ?? '',
                        'description' => $config['description'] ?? '',
                        'parent' => $config['parent'] ?? 0,
                    ]
                )];

            case 'delete_term':
                return ['port'=>'main','data'=>wp_delete_term(
                    $config['term_id'] ?? 0,
                    $config['taxonomy'] ?? ''
                )];

            case 'register_taxonomy':
                $registered = register_taxonomy(
                    $config['taxonomy'] ?? '',
                    WordpressHelpers::normalize_list( $config['object_type'] ?? [] ),
                    WordpressHelpers::normalize_taxonomy_args( $config['args'] ?? [] )
                );
                return ['port'=>'main','data'=>$registered];

            case 'unregister_taxonomy':
                $unregistered = unregister_taxonomy( $config['taxonomy'] ?? '' );
                return ['port'=>'main','data'=>$unregistered];

            case 'get_taxonomies':
                return ['port'=>'main','data'=>get_taxonomies( [], 'objects' )];

            case 'get_taxonomy':
                return ['port'=>'main','data'=>get_taxonomy( $config['taxonomy'] ?? '' )];

            case 'add_taxonomy_to_post':
                $terms = WordpressHelpers::normalize_list( $config['terms'] ?? [] );
                $append = isset( $config['append'] ) ? (bool) $config['append'] : true;
                $added = wp_set_object_terms(
                    (int) ( $config['post_id'] ?? 0 ),
                    $terms,
                    $config['taxonomy'] ?? '',
                    $append
                );
                return ['port'=>'main','data'=>$added];

            case 'remove_taxonomy_from_post':
                $terms = WordpressHelpers::normalize_list( $config['terms'] ?? [] );
                $removed = wp_remove_object_terms(
                    (int) ( $config['post_id'] ?? 0 ),
                    $terms,
                    $config['taxonomy'] ?? ''
                );
                return ['port'=>'main','data'=>$removed];

            case 'create_category':
                $created = wp_insert_category( [
                    'cat_name' => $config['name'] ?? '',
                    'category_description' => $config['description'] ?? '',
                    'category_parent' => $config['parent'] ?? 0,
                    'category_nicename' => $config['slug'] ?? '',
                ] );
                return ['port'=>'main','data'=>$created];

            case 'update_category':
                $updated = wp_update_category( [
                    'cat_ID' => $config['term_id'] ?? 0,
                    'cat_name' => $config['name'] ?? '',
                    'category_nicename' => $config['slug'] ?? '',
                    'category_parent' => $config['parent'] ?? 0,
                    'category_description' => $config['description'] ?? '',
                ] );
                return ['port'=>'main','data'=>$updated];

            case 'delete_category':
                $deleted = wp_delete_term( (int) ( $config['term_id'] ?? 0 ), 'category' );
                return ['port'=>'main','data'=>$deleted];

            case 'add_category_to_post':
                $categories = WordpressHelpers::normalize_list( $config['categories'] ?? [] );
                $append = isset( $config['append'] ) ? (bool) $config['append'] : true;
                $added = wp_set_post_categories(
                    (int) ( $config['post_id'] ?? 0 ),
                    $categories,
                    $append
                );
                return ['port'=>'main','data'=>$added];

            case 'get_categories':
                return ['port'=>'main','data'=>get_categories( [ 'hide_empty' => false ] )];

            case 'get_category':
                return ['port'=>'main','data'=>get_category( (int) ( $config['category_id'] ?? 0 ) )];

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
