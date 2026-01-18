<?php
namespace Zaplane\Integrations;



if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Classes\IntegrationBase;
use Zaplane\Integrations\Wordpress\PostActionsTrait;
use Zaplane\Integrations\Wordpress\TaxonomyActionsTrait;
use Zaplane\Integrations\Wordpress\UserActionsTrait;
use Zaplane\Integrations\Wordpress\RoleActionsTrait;
use Zaplane\Integrations\Wordpress\OptionActionsTrait;
use Zaplane\Integrations\Wordpress\MediaActionsTrait;
use Zaplane\Integrations\Wordpress\CommentActionsTrait;
use Zaplane\Integrations\Wordpress\QueryTrait;
use Zaplane\Integrations\Wordpress\Helper;


class Wordpress extends IntegrationBase {
    use PostActionsTrait;
    use TaxonomyActionsTrait;
    use UserActionsTrait;
    use RoleActionsTrait;
    use OptionActionsTrait;
    use MediaActionsTrait;
    use CommentActionsTrait;
    use QueryTrait;
    use Helper;

    public static function get_slug(): string {
        return 'wordpress';
    }

    /* =====================================================
     * TRIGGERS
     * ===================================================== */

    public static function get_triggers(): array {
        return [
            // Posts
            'publish_post'           => ['label' => 'Post Published', 'hook' => 'publish_post'],
            'post_updated'           => ['label' => 'Post Updated', 'hook' => 'post_updated'],
            'transition_post_status' => ['label' => 'Post Status Updated', 'hook' => 'transition_post_status'],
            'wp_insert_post'         => ['label' => 'Post Inserted', 'hook' => 'wp_insert_post'],
            'wp_after_insert_post'   => ['label' => 'WP After Insert Post', 'hook' => 'wp_after_insert_post'],
            'wp_trash_post'          => ['label' => 'Post Trashed', 'hook' => 'wp_trash_post'],
            'untrashed_post'         => ['label' => 'Post Untrashed', 'hook' => 'untrashed_post'],
            'delete_post'            => ['label' => 'Delete Post', 'hook' => 'delete_post'],

            // Users
            'user_register'          => ['label' => 'User Registered', 'hook' => 'user_register'],
            'set_user_role'          => ['label' => 'User Role Updated', 'hook' => 'set_user_role'],
            'profile_update'         => ['label' => 'Profile Updated', 'hook' => 'profile_update'],
            'wp_update_user'         => ['label' => 'Update User', 'hook' => 'wp_update_user'],
            'remove_user_from_blog'  => ['label' => 'Remove Blog User', 'hook' => 'remove_user_from_blog'],
            'delete_user'            => ['label' => 'Delete User', 'hook' => 'delete_user'],
            'wpmu_delete_user'       => ['label' => 'Delete User (MU)', 'hook' => 'wpmu_delete_user'],
            'wpmu_new_user'          => ['label' => 'New User Created', 'hook' => 'wpmu_new_user'],
            'wpmu_activate_user'     => ['label' => 'Activate User', 'hook' => 'wpmu_activate_user'],

            // Auth
            'wp_login'               => ['label' => 'User Logged In', 'hook' => 'wp_login'],
            'wp_login_failed'        => ['label' => 'Login Failed', 'hook' => 'wp_login_failed'],
            'wp_logout'              => ['label' => 'User Logged Out', 'hook' => 'wp_logout'],
            'wp_authenticate'        => ['label' => 'WP Authenticate', 'hook' => 'wp_authenticate'],

            // Comments
            'comment_post'           => ['label' => 'Comment Added', 'hook' => 'comment_post'],
            'wp_insert_comment'      => ['label' => 'Comment Created', 'hook' => 'wp_insert_comment'],
            'edit_comment'           => ['label' => 'Edit Comment', 'hook' => 'edit_comment'],
            'delete_comment'         => ['label' => 'Delete Comment', 'hook' => 'delete_comment'],
            'trashed_comment'        => ['label' => 'Comment Trashed', 'hook' => 'trashed_comment'],
            'untrashed_comment'      => ['label' => 'Comment Untrashed', 'hook' => 'untrashed_comment'],
            'transition_comment_status' => ['label' => 'Comment Status Changed', 'hook' => 'transition_comment_status'],
            'pre_comment_approved'   => ['label' => 'Pre-Approve Comment', 'hook' => 'pre_comment_approved'],

            // Terms / Taxonomy
            'create_term'            => ['label' => 'Create Term', 'hook' => 'create_term'],
            'created_term'           => ['label' => 'Term Created', 'hook' => 'created_term'],
            'edit_term'              => ['label' => 'Edit Term', 'hook' => 'edit_term'],
            'edited_term'            => ['label' => 'Term Edited', 'hook' => 'edited_term'],
            'saved_term'             => ['label' => 'Term Updated', 'hook' => 'saved_term'],
            'delete_term'            => ['label' => 'Delete Term', 'hook' => 'delete_term'],

            // Options / System
            'add_option'             => ['label' => 'Add Option', 'hook' => 'add_option'],
            'update_option'          => ['label' => 'Update Option', 'hook' => 'update_option'],
            'delete_option'          => ['label' => 'Delete Option', 'hook' => 'delete_option'],
            'upgrader_process_complete' => ['label' => 'Upgrader Complete', 'hook' => 'upgrader_process_complete'],
            'generate_rewrite_rules' => ['label' => 'Rewrite Rules Generated', 'hook' => 'generate_rewrite_rules'],
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

    private static function resolve_post_payload( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return false;

        return [
            'post_id'    => $post->ID,
            'post_title' => $post->post_title,
            'post_type'  => $post->post_type,
            'status'     => $post->post_status,
        ];
    }

    private static function resolve_comment_payload( int $comment_id ) {
        $comment = get_comment( $comment_id );
        if ( ! $comment ) return false;

        return [
            'comment_id' => $comment->comment_ID,
            'post_id'    => $comment->comment_post_ID,
            'content'    => $comment->comment_content,
            'status'     => $comment->comment_approved,
            'author'     => $comment->comment_author,
        ];
    }


    /* =====================================================
     * TRIGGER PAYLOAD
     * ===================================================== */

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            /* ---------------- POSTS ---------------- */

            case 'publish_post':
            case 'post_updated':
            case 'delete_post':
            case 'untrashed_post':
            case 'wp_trash_post':
                return self::resolve_post_payload( $args[0] ?? 0 );

            case 'transition_post_status':
                $post = get_post( $args[2] ?? 0 );
                if ( ! $post || ( $args[1] ?? '' ) === 'new' ) return false;

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'old_status' => $args[1] ?? '',
                    'new_status' => $args[0] ?? '',
                ];

            case 'wp_insert_post':
                $post = get_post( $args[0] ?? 0 );
                if ( ! $post || $post->post_type !== 'revision' ) return false;

                $parent = get_post( $post->post_parent );
                return [
                    'revision_id'    => $post->ID,
                    'parent_post_id' => $post->post_parent,
                    'parent_title'   => $parent->post_title ?? '',
                    'post_type'      => $parent->post_type ?? '',
                ];

            case 'wp_after_insert_post':
                $post = get_post( $args[0] ?? 0 );
                if ( ! $post ) return false;

                return array_merge(
                    self::resolve_post_payload( $post->ID ),
                    [ 'is_update' => $args[1] ?? false ]
                );

            /* ---------------- COMMENTS ---------------- */

            case 'comment_post':
            case 'edit_comment':
            case 'delete_comment':
            case 'trashed_comment':
            case 'untrashed_comment':
            case 'wp_insert_comment':
                return self::resolve_comment_payload( $args[0] ?? 0 );

            case 'transition_comment_status':
                $comment = get_comment( $args[1] ?? 0 );
                if ( ! $comment ) return false;

                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'old_status' => $args[2] ?? '',
                    'new_status' => $args[0] ?? '',
                    'content'    => $comment->comment_content,
                ];

            case 'wp_set_comment_status':
                return self::resolve_comment_payload( $args[0] ?? 0 );

            /* ---------------- USERS ---------------- */

            case 'user_register':
            case 'profile_update':
            case 'set_user_role':
            case 'delete_user':
            case 'wpmu_delete_user':
            case 'wp_update_user':
            case 'wpmu_new_user':
                return self::get_user_payload( $args[0] ?? 0 );

            case 'wp_login':
                $user = $args[1] ?? null;
                if ( ! $user instanceof \WP_User ) return false;

                return [
                    'user_id'  => $user->ID,
                    'username' => $user->user_login,
                    'email'    => $user->user_email,
                    'roles'    => $user->roles,
                ];

            case 'wp_login_failed':
                return [
                    'username' => $args[0] ?? '',
                    'failed'   => true,
                ];

            case 'wp_logout':
                $user = wp_get_current_user();
                if ( ! $user || ! $user->ID ) return false;

                return [
                    'user_id'  => $user->ID,
                    'username' => $user->user_login,
                ];

            /* ---------------- TERMS ---------------- */

            case 'create_term':
            case 'created_term':
            case 'edit_term':
            case 'edited_term':
            case 'saved_term':
            case 'delete_term':
                return self::get_term_payload(
                    $args[0] ?? 0,
                    $args[2] ?? '',
                    $args[1] ?? 0
                );

            /* ---------------- SYSTEM ---------------- */

            case 'add_option':
            case 'update_option':
            case 'delete_option':
                return [
                    'option_name' => $args[0] ?? '',
                    'value'       => $args[2] ?? null,
                ];

            case 'upgrader_process_complete':
                return [
                    'action' => $args[1]['action'] ?? '',
                    'type'   => $args[1]['type'] ?? '',
                ];

            case 'generate_rewrite_rules':
                return [ 'event' => $node['event'] ];

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

    private static function field_post_id(): array {
        return [['key'=>'post_id','label'=>'Post ID','type'=>'expression','required'=>true]];
    }

    private static function field_comment_id(): array {
        return [['key'=>'comment_id','label'=>'Comment ID','type'=>'expression','required'=>true]];
    }

    private static function field_user_id(): array {
        return [['key'=>'user_id','label'=>'User ID','type'=>'expression','required'=>true]];
    }

    private static function field_attachment_id(): array {
        return [['key'=>'attachment_id','label'=>'Attachment ID','type'=>'expression','required'=>true]];
    }


    /**
     * Action UI Schema
     */
    public static function get_action_config_schema( string $action ): array {

        $schemas = [

            /* ---------- POSTS ---------- */

            'create_post' => [
                ['key'=>'post_title','label'=>'Title','type'=>'expression','required'=>true],
                ['key'=>'post_content','label'=>'Content','type'=>'textarea'],
                ['key'=>'post_type','label'=>'Post Type','type'=>'select','required'=>true,
                    'dynamic'=>[
                        'integration'=>'wordpress',
                        'query'=>'post_types',
                        'select'=>['name','label'],
                    ]
                ],
                ['key'=>'post_status','label'=>'Status','type'=>'select','options'=>[
                    ['label'=>'Draft','value'=>'draft'],
                    ['label'=>'Publish','value'=>'publish'],
                ]],
            ],

            'untrash_post'           => self::field_post_id(),
            'update_comment_count'   => self::field_post_id(),

            /* ---------- COMMENTS ---------- */

            'untrash_comment'        => self::field_comment_id(),
            'delete_comment'         => self::field_comment_id(),
            'get_comment_metadata_single' => self::field_comment_id(),

            'set_comment_status' => [
                ...self::field_comment_id(),
                ['key'=>'status','label'=>'Status','type'=>'select','required'=>true,'options'=>[
                    ['label'=>'Approved','value'=>'1'],
                    ['label'=>'Pending','value'=>'0'],
                    ['label'=>'Spam','value'=>'spam'],
                    ['label'=>'Trash','value'=>'trash'],
                ]],
            ],

            'create_comment' => [
                ...self::field_post_id(),
                ['key'=>'author_name','label'=>'Author Name','type'=>'expression','required'=>true],
                ['key'=>'author_email','label'=>'Author Email','type'=>'expression','required'=>true],
                ['key'=>'content','label'=>'Comment Content','type'=>'textarea','required'=>true],
            ],

            'reply_comment' => [
                ['key'=>'parent_id','label'=>'Parent Comment ID','type'=>'expression','required'=>true],
                ['key'=>'author_name','label'=>'Author Name','type'=>'expression','required'=>true],
                ['key'=>'author_email','label'=>'Author Email','type'=>'expression','required'=>true],
                ['key'=>'content','label'=>'Reply Content','type'=>'textarea','required'=>true],
            ],

            /* ---------- USERS ---------- */

            'create_user' => [
                ['key'=>'user_login','label'=>'Username','type'=>'text','required'=>true],
                ['key'=>'user_email','label'=>'Email','type'=>'text','required'=>true],
                ['key'=>'user_pass','label'=>'Password','type'=>'text'],
                ['key'=>'display_name','label'=>'Display Name','type'=>'text'],
                ['key'=>'first_name','label'=>'First Name','type'=>'text'],
                ['key'=>'last_name','label'=>'Last Name','type'=>'text'],
                ['key'=>'role','label'=>'Role','type'=>'text'],
            ],

            'update_user' => [
                ...self::field_user_id(),
                ['key'=>'user_email','label'=>'Email','type'=>'text'],
                ['key'=>'user_pass','label'=>'Password','type'=>'text'],
                ['key'=>'display_name','label'=>'Display Name','type'=>'text'],
                ['key'=>'first_name','label'=>'First Name','type'=>'text'],
                ['key'=>'last_name','label'=>'Last Name','type'=>'text'],
                ['key'=>'role','label'=>'Role','type'=>'text'],
            ],

            'delete_user' => [
                ...self::field_user_id(),
                ['key'=>'reassign','label'=>'Reassign User ID','type'=>'expression'],
            ],

            'activate_user'          => self::field_user_id(),
            'deactivate_user'        => self::field_user_id(),

            /* ---------- OPTIONS ---------- */

            'add_plugin_theme_option' => [
                ['key'=>'option_name','label'=>'Option','type'=>'text','required'=>true],
                ['key'=>'value','label'=>'Value','type'=>'expression'],
            ],

            'update_option_advanced' => [
                ['key'=>'option_name','label'=>'Option Name','type'=>'text','required'=>true],
                ['key'=>'value','label'=>'New Value','type'=>'expression'],
            ],

            'delete_option' => [
                ['key'=>'option_name','label'=>'Option','type'=>'text','required'=>true],
            ],

            /* ---------- MEDIA ---------- */

            'generate_attachment_metadata' => self::field_attachment_id(),
            'regenerate_image_sizes'       => self::field_attachment_id(),

            'set_featured_image' => [
                ...self::field_post_id(),
                ...self::field_attachment_id(),
            ],

            /* ---------- AUTH ---------- */

            'authenticate_user' => [
                ['key'=>'user_login','label'=>'Username or Email','type'=>'text','required'=>true],
                ['key'=>'user_password','label'=>'Password','type'=>'text','required'=>true],
                ['key'=>'remember','label'=>'Remember','type'=>'text'],
                ['key'=>'secure_cookie','label'=>'Secure Cookie','type'=>'text'],
            ],

            'logout_user' => [],

        ];

        return $schemas[$action] ?? [];
    }

    /* =====================================================
     * ACTION EXECUTION
     * ===================================================== */

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];
        $event = $node['data']['event'];

        $method = 'action_' . $event;

        if (method_exists(static::class, $method)) {
            return static::$method($config, $input);
        }

        return ['port' => 'main', 'data' => $input];
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

    

}
