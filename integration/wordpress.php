<?php
namespace Zaplane\Integration;

use WP_Post;
use WP_User;
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
            'publish_post'                => ['label' => 'Post Published',               'hook' => 'publish_post'],
            'post_updated'                => ['label' => 'Post Updated',                 'hook' => 'post_updated'],
            'user_register'               => ['label' => 'User Registered',              'hook' => 'user_register'],
            'comment_post'                => ['label' => 'Comment Added',                'hook' => 'comment_post'],
            'deleted_post'                => ['label' => 'Before Deleted Post',          'hook' => 'before_delete_post'],
            'trashed_post'                => ['label' => 'Post Moved to Trash',          'hook' => 'trashed_post'],
            'save_post'                   => ['label' => 'Save Post',                    'hook' => 'save_post'],
            'activated_plugin'            => ['label' => 'Activate Plugin',              'hook' => 'activated_plugin'],
            'deactivate_plugin'           => ['label' => 'Deactivate Plugin',            'hook' => 'deactivate_plugin'],
            'switch_theme'                => ['label' => 'Theme Switch',                 'hook' => 'switch_theme'],
            'switch_blog'                 => ['label' => 'Blog Switch',                  'hook' => 'switch_blog'],
            'customize_register'          => ['label' => 'Customizer Registration',      'hook' => 'customize_register'],
            'rest_api_init'               => ['label' => 'REST API Init',                'hook' => 'rest_api_init'],
            'add_attachment'              => ['label' => 'Add Attachment',               'hook' => 'add_attachment'],
            'edit_attachment'             => ['label' => 'Attachment Edit',              'hook' => 'edit_attachment'],
            'save_attachment'             => ['label' => 'Attachment Save',              'hook' => 'attachment_fields_to_save'],
            'attachment_updated'          => ['label' => 'Attachment Update',            'hook' => 'attachment_updated'],
            'attachment_count'            => ['label' => 'Attachment Count',             'hook' => 'wp_count_attachments'],
            'attachment_metadata'         => ['label' => 'Generate Attachment Metadata', 'hook' => 'wp_generate_attachment_metadata'],
            'delete_attachment'           => ['label' => 'Media Deletion',               'hook' => 'delete_attachment'],
            'media_edit'                  => ['label' => 'Media Edit',                   'hook' => 'edit_attachment'],
            'media_upload_tabs'           => ['label' => 'Media Tabs',                   'hook' => 'media_upload_tabs'],
            'image_sizes'                 => ['label' => 'Image Sizes',                  'hook' => 'image_size_names_choose'],
            'wp_insert_post'              => ['label' => 'WP Insert Post',               'hook' => 'wp_insert_post'],
            'wp_insert_comment'           => ['label' => 'WP Insert Comment',            'hook' => 'wp_insert_comment'],
            'create_application_password' => ['label' => 'Create Application Password',  'hook' => 'wp_create_application_password'],
            'update_application_password' => ['label' => 'Update Application Password',  'hook' => 'wp_update_application_password'],
            'delete_application_password' => ['label' => 'Delete Application Password',  'hook' => 'wp_delete_application_password'],
            'added_option'                => ['label' => 'Option Addition',              'hook' => 'added_option'],
            'update_option'               => ['label' => 'Option Update',                'hook' => 'update_option'],
            'delete_option'               => ['label' => 'Option Delete',                'hook' => 'delete_option'],
            'wp_login'                    => ['label' => 'WP login',                     'hook' => 'wp_login'],
            'wp_login_failed'             => ['label' => 'WP Login Failed',              'hook' => 'wp_login_failed'],
            'wp_logout'                   => ['label' => 'WP Logout',                    'hook' => 'wp_logout'],
            'validate_reset'              => ['label' => 'Validate Reset',               'hook' => 'validate_password_reset'],
            'activate_user'               => ['label' => 'Activate User',                'hook' => 'wpmu_activate_user'],
            'update_blog_public'          => ['label' => 'Update Blog Public',           'hook' => 'update_blog_public'],
            'update_blog_status'          => ['label' => 'Update Blog Status',           'hook' => 'update_blog_status'],
            'new_blog'                    => ['label' => 'New Blog',                     'hook' => 'wpmu_new_blog'],
            'transition_post_status'      => ['label' => 'On Post Status Update',        'hook' => 'transition_post_status'],
            'post_revision'               => ['label' => 'Revision Creation',            'hook' => '_wp_put_post_revision'],
            'set_user_role'               => ['label' => 'Set User Role',                'hook' => 'set_user_role'],
            'add_user_role'               => ['label' => 'User Added to a Role',                'hook' => 'add_user_role'],
        ];
    }

    /**
     * Trigger UI Schema
     */
    public static function get_trigger_config_schema( string $trigger ): array {

        if ( in_array( $trigger, ['publish_post','post_updated', 'transition_post_status'], true ) ) {
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

        if ( $trigger === 'activated_plugin' ) {
            return [
                [
                    'key'     => 'plugin',
                    'label'   => 'Inactive Plugin',
                    'type'    => 'select',
                    'dynamic' =>[
                        'integration' => 'wordpress',
                        'query'       => 'inactive_plugins',
                        'select'      => [ 'file', 'name' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if ( $trigger === 'switch_theme' ) {
            return [
                [
                    'key'     => 'theme',
                    'label'   => 'Theme Switch',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'deactivate_theme',
                        'select'      => [ 'file', 'name' ],
                    ],
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

            case 'trashed_post' :
            case 'deleted_post':
            case 'save_post' :
            case 'wp_insert_post' :
                $post_id = $args[0] ?? 0;
                $post    = get_post( $post_id );
                if ( ! $post) return false;

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'status'     => $post->post_status,
                ];

            // case 'deleted_post':
            //     $post = get_post( $args[0] ?? 0 );
            //     if ( ! $post) return false;

            //     return [
            //         'post_id'    => $post->ID,
            //         'post_title' => $post->post_title,
            //         'post_type'  => $post->post_type,
            //         'status'     => $post->post_status,
            //     ];

            // case 'save_post' :
            //     $post = get_post( $args[0] ?? 0 );
            //     if ( ! $post ) return false;

            //     return [
            //         'post_id'    => $post->ID,
            //         'post_title' => $post->post_title,
            //         'post_type'  => $post->post_type,
            //         'status'     => $post->post_status,
            //     ];

            case 'activated_plugin' :
            case 'deactivate_plugin' :
                $plugin = $args[0] ?? 0;
                if ( ! $plugin ) return false;

                return [
                    'plugin' => $plugin,
                ];

            // case 'deactivate_plugin' :
            //     $plugin = $args[0] ?? 0;
            //     if ( ! $plugin ) return false;
                
            //     return [
            //         'plugin' => $plugin,
            //     ];

            case 'switch_theme' :
                $theme = $args[0] ?? 0;
                if ( ! $theme ) return false;
                
                return [
                    'theme' => $theme,
                ];

            case 'switch_blog' :
                $blog = $args[0] ?? 0;
                if ( ! $blog ) return false;

                return [
                    'blog_id'   => $blog,
                    'blog_url'  => get_home_url( $blog ),
                    'blog_name' => get_blog_option( $blog, 'blogname' ),
                ];

            case 'customize_register' :
                $customize = $args[0] ?? null;
                if ( ! $customize ) return false;

                return [
                    'message' => 'Customize Registration',
                    'time'    => current_time( 'mysql' ),
                ];
            
            case 'rest_api_init' :
                return [
                    'time' => current_time( 'mysql' ),
                ];

            case 'add_attachment' : 
            case 'edit_attachment' : 
            case 'attachment_updated' :
            case 'media_edit' :
                
                $attachment_id = $args[0] ?? 0;
                if ( ! $attachment_id ) return false;

                $attachment = get_post( $attachment_id );
                if ( ! $attachment || $attachment->post_type !== 'attachment' ) return false;

                return [
                    'attachment_id' => $attachment_id,
                    'post_title'    => $attachment->post_title,
                    'mime_type'     => get_post_mime_type( $attachment_id ),
                    'url'           => wp_get_attachment_url( $attachment_id ),
                    'user_id'       => get_current_user_id(),
                    'time'          => current_time( 'mysql' ),
                ];

            case 'save_attachment' : 
                $post_id = $args[0] ?? 0;
                $attachment = $args[1] ?? [];
                if ( ! $post_id ) return false;

                $attachment_post = get_post( $post_id );
                if ( ! $attachment_post || $attachment_post->post_type !== 'attachment' ) return false;

                return [
                    'attachment_id' => $post_id,
                    'post_title'    => $attachment_post->post_title,
                    'mime_type'     => get_post_mime_type( $post_id ),
                    'url'           => wp_get_attachment_url( $post_id ),
                    'user_id'       => get_current_user_id(),
                    'time'          => current_time( 'mysql' ),
                    'fields'        => $attachment,
                ];

            case 'attachment_count' :
                $post_type = $args[0] ?? 0;
                $count = wp_count_attachments( $post_type );

                return [
                    'post_type' => $post_type,
                    'counts'    => (array) $count,
                    'time'     => current_time( 'mysql' ), 
                ];

            case 'attachment_metadata' :
                $metadata = $args[0] ?? [];
                $attachment_id = $args[1] ?? 0;
                if ( ! $attachment_id || empty( $metadata ) ) return false;

                $attachment = get_post( $attachment_id );
                if ( ! $attachment || $attachment->post_type !== 'attachment' ) return false;

                return [
                    'attachment_id' => $attachment_id,
                    'post_title'    => $attachment->post_title,
                    'mime_type'     => get_post_mime_type( $attachment_id ),
                    'url'           => wp_get_attachment_url( $attachment_id ),
                    'matadata'      => $metadata,
                    'user_id'       => get_current_user_id(),
                    'time'          => current_time( 'mysql' ),
                ];
            
            case 'delete_attachment' :
                $attachment_id = $args[0] ?? 0;
                if ( ! $attachment_id ) return false;

                return [
                    'attachment_id' => $attachment_id,
                    'user_id'       => get_current_user_id(),
                    'time'          => current_time( 'mysql' ),
                ];

            case 'media_upload_tabs' : 
                $tabs = $args[0] ?? [];
                if ( empty( $tabs ) || ! is_array( $tabs) ) return false;

                return [
                    'tabs'         => $tabs,
                    'tabs_keys'    => array_keys( $tabs ),
                    'count'        => count( $tabs ),
                    'triggered_at' => current_time( 'mysql' ),
                ];
            
            case 'image_sizes' : 
                $sizes = $args[0] ?? [];
                if ( empty( $sizes ) || ! is_array( $sizes ) ) return false;

                return [
                    'sizes'      => $sizes,
                    'sizes_keys' => array_keys( $sizes ),
                    'count'      => count( $sizes ),
                    'time'       => current_time( 'mysql' ),
                ];

            // case 'wp_insert_post' :
            //     $post_id = $args[0] ?? 0;
            //     $post    = get_post( $post_id );
            //     if ( ! $post ) return false;

            //     return [
            //         'post_id'    => $post->ID,
            //         'post_title' => $post->post_title,
            //         'post_type'  => $post->post_type,
            //         'status'     => $post->post_status,
            //         'created_at' => $post->post_date,
            //         'author_id'  => $post->post_author,
            //     ];

            case 'wp_insert_comment' :
                $comment_id = $args[0] ?? 0;
                if ( ! $comment_id ) return false;
                $comment = get_comment( $comment_id );
                if ( ! $comment ) return false;

                return [
                    'comment_id'   => $comment->comment_ID,
                    'post_id'      => $comment->comment_post_ID,
                    'author'       => $comment->comment_author,
                    'author_email' => $comment->comment_author_email,
                    'content'      => $comment->comment_content,
                    'status'       => $comment->comment_approved,
                    'date'         => $comment->comment_date,
                ];
                
            case 'create_application_password' :
                $user_id      = $args[0] ?? 0;
                $new_password = $args[1] ?? '';
                if ( ! $user_id || empty( $new_password ) ) return false;
                $user = get_userdata( $user_id );
                if ( ! $user ) return false;

                return [
                    'user_id'      => $user_id,
                    'user_login'   => $user->user_login,
                    'new_password' => $new_password,
                    'time'         => current_time( 'mysql' ),
                ];

            case 'update_application_password' :
                $user_id = $args[0] ?? 0;
                $item    = $args[1] ?? null;
                if ( ! $user_id || empty( $item ) ) return false;

                return [
                    'user_id'   => $user_id,
                    'item_name' => $item['name'] ?? '',
                    'item_id'   => $item['uuid'] ?? '',
                    'time'      => current_time( 'mysql' ),
                ];

            case 'delete_application_password' :
                $user_id = $args[0] ?? 0;
                $uuid    = $args[1] ?? '';
                if ( ! $user_id ) return false;
                return [
                    'user_id' => $user_id,
                    'uuid'    => $uuid,
                ];

            case 'added_option' :
                $option_name = $args[0] ?? '';
                $option_value = $args[1] ?? null;
                if ( empty( $option_name ) ) return false;

                return [
                    'option_name' => $option_name,
                    'option_value' => $option_value,
                ];

            case 'update_option' :
                $option_name = $args[0] ?? '';
                $old_value = $args[1] ?? null;
                $new_value = $args[2] ?? null;
                if ( empty( $option_name ) ) return false;

                return [
                    'option_name' => $option_name,
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            
            case 'delete_option' :
                $option_name = $args[0] ?? '';
                if ( empty( $option_name ) ) return false;

                return [
                    'option_name' => $option_name,
                ];
            
            case 'wp_login' :
            case 'validate_reset' :
            case 'activate_user' :  
                $user = $args[0] ?? 0;
                if ( ! $user || $user instanceof WP_User ) return false;

                return [
                    'user_id'      => $user->ID,
                    'user_login'   => $user->user_login,
                    'user_email'   => $user->user_email,
                    'display_name' => $user->display_name,
                ];

            case 'wp_login_failed' :
                $username = $args[0] ?? '';
                if ( empty( $username ) ) return false;

                return [
                    'user_login_attempt' => $username,
                    'time' => current_time( 'mysql' ),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                ];

            // case 'validate_reset' : 
            //     $user = $args[0] ?? null;
            //     if ( ! $user || ! $user instanceof WP_User ) return false;
                
            //     return [
            //         'user_id'      => $user->ID,
            //         'user_login'   => $user->user_login,
            //         'user_email'   => $user->user_email,
            //         'display_name' => $user->display_name,
            //     ];

            // case 'activate_user' : 
            //     $user_id = $args[0] ?? 0;
            //     if ( ! $user_id ) return false;
            //     $user = get_user_by( 'id', $user_id );
            //     if ( ! $user ) return false;

            //     return [
            //         'user_id'      => $user->ID,
            //         'user_login'   => $user->user_login,
            //         'user_email'   => $user->user_email,
            //         'display_name' => $user->display_name,
            //     ];

            case 'update_blog_public' :
                $blog_id = $args[0] ?? 0;
                $public  = $args[1] ?? 0;
                if ( ! $blog_id ) return false;

                return [
                    'blog_id' => $blog_id,
                    'is_public' => $public,
                ];

            case 'update_blog_status' :
                $blog_id    = $args[0] ?? 0;
                $new_status = $args[1] ?? 0;
                $old_status = $args[2] ?? 0;
                if ( ! $blog_id ) return false;

                return [
                    'blog_id' => $blog_id,
                    'new_status' => $new_status,
                    'old_status' => $old_status,
                ];

            case 'new_blog' : 
                $blog_id = $args[0] ?? 0;
                $user_id = $args[1] ?? 0;
                $domain  = $args[2] ?? '';
                $path    = $args[3] ?? '';
                $site_id = $args[4] ?? 0;
                $meta    = $args[5] ?? [];
                if ( ! $blog_id ) return false;

                return [
                    'blog_id' => $blog_id,
                    'user_id' => $user_id,
                    'domain'  => $domain,
                    'path'    => $path,
                    'site_id' => $site_id,
                    'meta'    => $meta,
                ];

            case 'transition_post_status' :
                $new_status = $args[0] ?? '';
                $old_status = $args[1] ?? '';
                $post       = $args[2] ?? null;
                if ( ! $post || ! $post instanceof WP_Post ) return false;

                return [
                    'post_id'    => $post->ID,
                    'post_title' => $post->post_title,
                    'post_type'  => $post->post_type,
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                ];

            case 'post_revision' :
                $revision_id = $args[0] ?? '';
                $post        = $args[1] ?? null;
                if ( ! $post || ! $post instanceof WP_Post ) return false;

                return [
                    'revision_id' => $revision_id,
                    'post_id'     => $post->ID,
                    'post_title'  => $post->post_title,
                    'post_type'   => $post->post_type,
                ];

            case 'set_user_role' :
            case 'add_user_role' :
                $user_id = $args[0] ?? 0;
                $role = $args[1] ?? '';
                $user = $args[2] ?? null;
                if ( ! $user || ! $user instanceof WP_User ) return false;

                return [
                    'user_id'      => $user_id,
                    'role'         => $role,
                    'user_login'   => $user->user_login,
                    'user_email'   => $user->user_email,
                    'display_name' => $user->display_name,
                ];
        }

        return false;
    }

    /* =====================================================
     * ACTIONS
     * ===================================================== */

    public static function get_actions(): array {
        return [
            'create_post'          => ['label' => 'Create Post'],
            'update_option'        => ['label' => 'Update Option'],
            'update_post_title'    => ['label' => 'Update Post Title'],
            'update_post'          => ['label' => 'Update Post'],
            'update_post_status'   => ['label' => 'Update Post Status'],
            'delete_post'          => ['label' => 'Delete Post'],
            'posts_all'            => ['label' => 'Post (All)'],
            'post_single'          => ['label' => 'Post (Single)'],
            'posts_by_post_type'   => ['label' => 'Posts by Post Type'],
            'posts_by_metadata'    => ['label' => 'Posts by Metadata'],
            'posts_metadata_all'   => ['label' => 'Post Metadata (All)'],
            'post_metadata_single' => ['label' => 'Post Metadata (Single)'],
            'post_permalink'       => ['label' => 'Post Permalink'],
            'post_content'         => ['label' => 'Post Content'],
            'post_excerpt'         => ['label' => 'Post Excerpt'],
            'post_status'          => ['label' => 'Post Status'],
            'post_type_all'        => ['label' => 'Post Type (All)'],
            'post_type_single'        => ['label' => 'Post Type (Single Post)'],
            'register_post_type'        => ['label' => 'Register Post Type'],
            'unregister_post_type'        => ['label' => 'Unregister Post Type'],
            'add_post_type_support'        => ['label' => 'Add Post Type Features'],
            'activate_plugin'      => ['label' => 'Activate Plugin'],
            'deactivate_plugin'    => ['label' => 'Deactivate Plugin'],
            'switch_theme'         => ['label' => 'Theme Switch'],
        ];
    }

    /**
     * Action UI Schema
     */
    public static function get_action_config_schema( string $action ): array {

        if ( $action === 'create_post' ) {
            return [
                [
                    'key'      => 'post_title',
                    'label'    => 'Title',
                    'type'     => 'expression',
                    'required' => true,
                ],
                [
                    'key'   => 'post_content',
                    'label' => 'Content',
                    'type'  => 'textarea',
                ],
                [
                    'key'     => 'post_type',
                    'label'   => 'Post Type',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'post_types',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'     => 'post_status',
                    'label'   => 'Status',
                    'type'    => 'select',
                    'options' => [
                        ['label' => 'Draft',   'value' => 'draft' ],
                        ['label' => 'Publish', 'value' => 'publish' ],
                    ],
                ],
            ];
        }

        if ( $action === 'update_option' ) {
            return [
                [
                    'key'      => 'option_name',
                    'label'    => 'Option',
                    'type'     => 'text',
                    'required' => true
                ],
                [
                    'key'   => 'value',
                    'label' => 'Value',
                    'type'  => 'expression'
                ],
            ];
        }

        if ( $action === 'update_post_title' ) {
            return [
                [
                    'key'      => 'post_id',
                    'label'    => 'Post ID',
                    'type'     => 'expression',
                    'required' => true,
                ],
                [
                    'key'      => 'post_title',
                    'label'    => 'New Post Title',
                    'type'     => 'expression',
                    'required' => true,
                ],
            ];
        }

        $post_id_actions = [
                'delete_post',
                'post_single',
                'posts_metadata_all',
                'post_permalink',
                'post_content',
                'post_excerpt',
                'post_status',
                'post_type_single',
        ];

        if ( in_array( $action, $post_id_actions, true ) ) {
            return [
                [
                    'key'      => 'post_id',
                    'label'    => 'Post ID',
                    'type'     => 'expression', 
                    'required' => true,
                ],
            ];
        }

        if ( $action === 'update_post' ) {
            return [
                [
                    'key'      => 'post_id',
                    'label'    => 'Post ID to Update',
                    'type'     => 'expression', 
                    'required' => true,
                ],
                [
                    'key'      => 'post_title',
                    'label'    => 'New Post Title',
                    'type'     => 'expression',
                    'required' => true, 
                ],
                [
                    'key'      => 'post_content',
                    'label'    => 'New Post Content',
                    'type'     => 'expression',
                    'required' => true,                 
                ],
                [
                    'key'     => 'post_type',
                    'label'   => 'Post Type',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'post_types',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'     => 'post_status',
                    'label'   => 'Status',
                    'type'    => 'select',
                    'options' => [
                        ['label' => 'Draft',   'value' => 'draft' ],
                        ['label' => 'Publish', 'value' => 'publish' ],
                    ],
                ],
            ];
        }

        if ( $action === 'update_post_status' ) {
            return [
                [
                    'key'      => 'post_id',
                    'label'    => 'Post ID to Update',
                    'type'     => 'expression', 
                    'required' => true,
                ],
                [
                    'key'     => 'post_status',
                    'label'   => 'Status',
                    'type'    => 'select',
                    'options' => [
                        ['label' => 'Publish', 'value' => 'publish' ],
                        ['label' => 'Pending', 'value' => 'pending' ],
                        ['label' => 'Private', 'value' => 'private' ],
                        ['label' => 'Draft',   'value' => 'draft' ],
                    ],
                ],
            ];
        }

        if ( $action === 'posts_by_post_type' ) {
            return [
                [
                    'key'     => 'post_type',
                    'label'   => 'Post Type',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'post_types',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if ( $action === 'posts_by_metadata' ) {
            return [
                [
                    'key'     => 'post_type',
                    'label'   => 'Post Type',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'post_types',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'      => 'meta_kry',
                    'label'    => 'Post Meta Key',
                    'type'     => 'expression',
                    'required' => true,                 
                ],
                [
                    'key'      => 'meta_value',
                    'label'    => 'Post Meta Value',
                    'type'     => 'expression',
                    'required' => true,                 
                ],
            ];
        }

        if ( $action === 'post_metadata_single' ) {
            return [
                [
                    'key'      => 'post_id',
                    'label'    => 'Post ID',
                    'type'     => 'expression', 
                    'required' => true,
                ],
                [
                    'key'      => 'meta_kry',
                    'label'    => 'Post Meta Key',
                    'type'     => 'expression',
                    'required' => true,                 
                ],
            ];
        }

        if ( $action === 'register_post_type' ) {
            return [
                [
                    'key'      => 'slug',
                    'label'    => 'Post Type Slug',
                    'type'     => 'text',
                    'required' => true, 
                ],
                [
                    'key'      => 'label',
                    'label'    => 'Label',
                    'type'     => 'text',
                    'required' => false,
                    'default'  => '',
                ],
                [
                    'key'      => 'hierarchical',
                    'label'    => 'Hierarchy (Category Style)',
                    'type'     => 'boolean',
                    'required' => false, 
                ],
                [
                    'key'      => 'public',
                    'label'    => 'Public',
                    'type'     => 'boolean',
                    'required' => false, 
                    'default'  => true,
                ],
                [
                    'key'      => 'show_in_rest',
                    'label'    => 'Show in REST API',
                    'type'     => 'boolean',
                    'required' => false, 
                    'default'  => true,
                ],
                [
                    'key'      => 'show_ui',
                    'label'    => 'Show UI',
                    'type'     => 'boolean',
                    'required' => false, 
                    'default'  => true,
                ],
                [
                    'key'      => 'show_in_menu',
                    'label'    => 'Show In Menu',
                    'type'     => 'boolean',
                    'required' => false, 
                    'default'  => true,
                ],
                [
                    'key'      => 'show_in_nav_menus',
                    'label'    => 'Show In Nav Menus',
                    'type'     => 'boolean',
                    'required' => false, 
                    'default'  => true,
                ],
                [
                    'key'      => 'show_in_admin_bar',
                    'label'    => 'Show In Admin Bar',
                    'type'     => 'boolean',
                    'required' => false, 
                    'default'  => true,
                ],
                [
                    'key'      => 'menu_icon',
                    'label'    => 'Menu Icon',
                    'type'     => 'text',
                    'required' => false, 
                ],
                [
                    'key'      => 'menu_position',
                    'label'    => 'Menu Position',
                    'type'     => 'number',
                    'required' => false, 
                ],
                [
                    'key'      => 'supports',
                    'label'    => 'Supports',
                    'type'     => 'multiselect',
                    'option'   => [ 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'author', 'custom-fields' ],
                    'default'  => ['title', 'editor' ],
                    'required' => false, 
                ],
                [
                    'key'      => 'capability_type',
                    'label'    => 'Capability Type',
                    'type'     => 'text',
                    'required' => false, 
                    'default'  => 'post',
                ],
                [
                    'key'      => 'description',
                    'label'    => 'Description',
                    'type'     => 'textarea',
                    'required' => false, 
                    'default'  => '',
                ],
                [
                    'key'      => 'rewrite_slug',
                    'label'    => 'Custom URL Slug',
                    'type'     => 'textarea',
                    'required' => false, 
                    'default'  => '',
                ],
            ];
        }

        if ( $action === 'unregister_post_type' ) {
            return [
                [
                    'key'     => 'post_type',
                    'label'   => 'Post Type',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'post_types',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if ( $action === 'add_post_type_support' ) {
            return [
                [
                    'key'     => 'post_type',
                    'label'   => 'Post Type',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'post_types',
                        'select'      => [ 'name', 'label' ],
                    ],
                    'required' => true,
                ],
                [
                    'key'      => 'features',
                    'label'    => 'Features (Supports)',
                    'type'     => 'multiselect',
                    'option'   => [ 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'author', 'custom-fields' ],
                    'required' => false, 
                ],
            ];
        }

        if ( $action === 'activate_plugin' ) {
            return [
                [
                    'key'     => 'plugin',
                    'label'   => 'Inactive Plugin',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'inactive_plugins',
                        'select'      => [ 'file', 'name' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if ( $action === 'deactivate_plugin' ) {
            return [
                [
                    'key'     => 'plugin',
                    'label'   => 'Active Plugin',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'active_plugins',
                        'select'      => [ 'file', 'name' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if ( $action === 'switch_theme' ) {
            return [
                [
                    'key'     => 'theme',
                    'label'   => 'Theme Switch',
                    'type'    => 'select',
                    'dynamic' => [
                        'integration' => 'wordpress',
                        'query'       => 'deactivate_theme',
                        'select'      => [ 'file', 'name' ],
                    ],
                    'required' => true,
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
                    'ID'         => $config['post_id'] ,
                    'post_title' => $config['post_title'],
                ]);
                return ['port'=>'main','data'=>[]];

            case 'delete_post':
                wp_delete_post( $config['post_id'], true );
                return ['port'=>'main','data'=>['post_id'=>$config['post_id']]];

            case 'update_post':
                wp_update_post([
                    'ID'           => $config['post_id'],
                    'post_title'   => $config['post_title'],
                    'post_type'    => $config['post_type'],
                    'post_content' => $config['post_content'],
                    'post_status'  => $config['post_status'],
                ]);
                return ['port' => 'main', 'data' => []];

            case 'update_post_status':
                wp_update_post([
                    'ID'          => $config['post_id'],
                    'post_status' => $config['post_status'],
                ]);
                return ['port' => 'main', 'data' => []];

            case 'posts_all':
                $posts = get_posts([
                    'post_type'   => 'post',
                    'post_status' => 'any',
                    'numberposts' => -1,
                ]);
                $post_details = array_map( function($post) {
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
                }, $posts );
                return ['port' => 'main', 'data' => $post_details];

            case 'post_single':
                $post_id = $config['post_id'] ?? 0;
                $post = get_post( $post_id );
                if ( ! $post ) return ['port' => 'main', 'data' => []];
                $post_details = [
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
                return ['port' => 'main', 'data' => $post_details];

            case 'posts_by_post_type':
                $post_type = $config['post_type'];
                $posts = get_posts([
                    'post_type'      => $post_type,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                ]);
                $post_details = [];
                foreach ( $posts as $post ) {
                $post_details[] = [
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
                return ['port' => 'main', 'data' => $post_details];

            case 'posts_by_metadata':
                $post_type  = $config['post_type'];
                $meta_key   = $config['meta_key'];
                $meta_value = $config['meta_value'];
                $posts = get_posts([
                    'post_type'      => $post_type,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key'     => $meta_key,
                            'value'   => $meta_value,
                            'compare' => '=',
                        ],
                    ],
                ]);
                $post_details = [];
                foreach ( $posts as $post ) {
                $post_details[] = [
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
                return ['port' => 'main', 'data' => $post_details];
            
            case 'posts_metadata_all':
                $post_id      = $config['post_id'] ?? 0;
                $meta         = get_post_meta( $post_id );
                $meta_details = [];
                foreach ( $meta as $key => $values ) {
                    $meta_details[] = [
                        'meta_key'   => $key,
                        'meta_value' => maybe_unserialize( $values ),
                    ];
                }
                return ['port' => 'main', 'data' => $meta_details];

            case 'post_metadata_single':
                $post_id = $config['post_id'] ?? 0;
                $meta_key = $config['meta_key'] ?? '';
                $meta_value = maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) );
                return ['port' => 'main', 'data' => ['meta_key' => $meta_key, 'meta_value' => $meta_value, ] ];

            case 'post_permalink':
                $post_id = $config['post_id'] ?? 0;
                $permalink = get_permalink( $post_id );
                return ['port' => 'main', 'data' => ['post_id' => $post_id, 'permalink' => $permalink, ] ];

            case 'post_content':
                $post_id = $config['post_id'] ?? 0;
                $post = get_post( $post_id );
                return ['port' => 'main', 'data' => ['post_id' => $post_id, 'post_content' => $post->post_content, ] ];

            case 'post_excerpt':
                $post_id = $config['post_id'] ?? 0;
                $post = get_post( $post_id );
                return ['port' => 'main', 'data' => ['post_id' => $post_id, 'post_excerpt' => $post->post_excerpt, ] ];

            case 'post_status':
                $post_id = $config['post_id'] ?? 0;
                $post = get_post( $post_id );
                return ['port' => 'main', 'data' => ['post_id' => $post_id, 'post_status' => $post->post_status, ] ];

            case 'post_type_all':
                $types = get_post_types( [], 'objects' );
                $post_types = [];
                foreach ( $types as $typ_name => $typ_object ) {
                    $post_types[ $typ_name ] = [
                        'name'            => $typ_object->name,
                        'label'           => $typ_object->label,
                        'description'     => $typ_object->description,
                        'hierarchical'    => $typ_object->hierarchical,
                        'rest_base'       => $typ_object->rest_base,
                        'show_in_rest'    => $typ_object->show_in_rest,
                        'public'          => $typ_object->public,
                        'capability_type' => $typ_object->capability_type,
                        'capabilities'    => $typ_object->cap,
                        'labels'          => (array) $typ_object->labels,
                        'supports'        => $typ_object->supports ?? [],
                        'menu_icon'       => $typ_object->menu_icon ?? '',
                        'menu_position'   => $typ_object->menu_position ?? null,
                    ];
                }
                return ['port'=>'main', 'data'=> $post_types];

            case 'post_type_single':
                $post_id = $config['post_id'] ?? 0;
                $post_type = get_post_type( $post_id );
                $typ_object = get_post_type_object( $post_type );
                return ['port'=>'main', 'data'=> [
                    'post_id'      => $post_id,
                    'post_type'    => $post_type,
                    'label'        => $typ_object->label,
                    'public'       => $typ_object->public,
                    'show_in_rest' => $typ_object->show_in_rest,
                    'rest_base'    => $typ_object->rest_base,
                    'capability_type' => $typ_object->capability_type,
                    'hierarchical'    => $typ_object->hierarchical,
                    'supports'        => $typ_object->supports,
                ]];

            case 'register_post_type':
                $slug               = $config['slug'] ?? '';
                $label              = $config['label'] ?? ucfirst( $slug );
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
                    'rewrite_slug'      => ['slug' => $rewrite_slug],
                ];
                $result = register_post_type( $slug, $args );
                if ( is_wp_error( $result ) ) {
                    return ['port'=>'main', 'data'=>['error'=>$result->get_error_message()]];
                }
                return ['port'=>'main', 'data'=>['success' => true, 'post_type' => $slug, 'args' => $args ]];

            case 'unregister_post_type':
                $post_type = $config['post_type'] ?? '';
                $result = unregister_post_type( $post_type );
                return ['port'=>'main', 'data'=>['result'=>$result]];

            case 'add_post_type_support':
                $post_type = $config['post_type'] ?? '';
                $features = $config['features'] ?? [];
                foreach ( $features as $feature ) {
                    add_post_type_support( $post_type, $feature );
                }
                return ['port'=>'main', 'data'=>['success' => true, 'post_type' => $post_type, 'features' => $features ]];


            case 'activate_plugin' : 
                if ( $plugin = $config['plugin'] ?? '' ) {
                    activate_plugin( $plugin );
                }
                return ['port'=>'main', 'data'=>['plugin'=>$plugin]];

            case 'deactivate_plugin':
                if ( is_plugin_active( $config[ 'plugin' ] ) ) {
                    deactivate_plugins( $config[ 'plugin' ] ); 
                }
                return ['port'=>'main', 'data'=>[]];

            case 'switch_theme' : 
                if ( $theme = $config['theme'] ?? '' ) {
                    switch_theme( $theme );
                }
                return ['port'=>'main', 'data'=>['theme'=>$theme]];
        }

        return ['port'=>'main','data'=>$input];
    }

    /* =====================================================
     * DYNAMIC DATA QUERIES (API)
     * ===================================================== */

    public static function get_dynamic_queries(): array {
        return [
            'post_types'        => [ self::class, 'query_post_types' ],
            'posts'             => [ self::class, 'query_posts' ],
            'users'             => [ self::class, 'query_users' ],
            'active_plugins'    => [ self::class, 'query_active_plugins' ],
            'inactive_plugins'  => [ self::class, 'query_deactivate_plugins' ],
            'deactivate_theme'  => [ self::class, 'query_deactivate_theme' ],
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
            'post_title' => $p->post_title,
        ], $posts);
    }

    public static function query_users( $q ) {
        $users = get_users(['search'=>$q['search'] ?? '']);
        return array_map(fn($u)=>[
            'ID'    => $u->ID,
            'name'  => $u->display_name,
            'email' => $u->user_email
        ], $users);
    }

    public static function query_active_plugins( $q ) {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins   = get_plugins();
        $active_plugin = get_option( 'active_plugins', [] );
        $result        = [];

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

    public static function query_deactivate_plugins( $q ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins   = get_plugins();
        $active_plugin = get_option( 'active_plugins', [] );
        $result        = [];

        foreach ( $all_plugins as $inactive_plugin => $plugin ) {
            if ( in_array( $inactive_plugin, $active_plugin, true  ) ) {
                continue;
            }

            if ( $inactive_plugin === 'zaplane/zaplane.php' ) {
                continue;
            }

            $result[] = [
                'file' => $inactive_plugin,
                'name' => $plugin[ 'Name' ],
            ];
        }

        return $result;
    }

    public static function query_deactivate_theme( $q ) {
        $all_themes   = wp_get_themes();
        $active_theme = wp_get_theme()->get_stylesheet();
        $result       = [];

        foreach ( $all_themes as $stylesheet => $theme ) {
        
            if ( $stylesheet === $active_theme ) {
                continue;
            }

            $result[] = [
                'file' => $stylesheet,
                'name' => $theme->get( 'Name' ),
            ];
        }

        return $result;
    }
}
