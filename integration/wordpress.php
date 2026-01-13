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
                $role = WordpressHelpers::resolve_role_key( $config['role'] ?? '', true );
                $user_id = wp_insert_user( [
                    'user_login' => $config['user_login'] ?? '',
                    'user_email' => $config['user_email'] ?? '',
                    'user_pass' => $config['user_pass'] ?? '',
                    'display_name' => $config['display_name'] ?? '',
                    'first_name' => $config['first_name'] ?? '',
                    'last_name' => $config['last_name'] ?? '',
                    'role' => $role,
                ] );
                return ['port'=>'main','data'=>$user_id];

            case 'update_user':
                $data = [
                    'ID' => (int) ( $config['user_id'] ?? 0 ),
                ];
                foreach ( ['user_email','user_pass','display_name','first_name','last_name','role'] as $field ) {
                    if ( array_key_exists( $field, $config ) ) {
                        $data[$field] = $config[$field];
                    }
                }
                if ( array_key_exists( 'role', $data ) ) {
                    $role = WordpressHelpers::resolve_role_key( $data['role'], true );
                    if ( $role !== '' ) {
                        $data['role'] = $role;
                    } else {
                        unset( $data['role'] );
                    }
                }
                $updated = wp_update_user( $data );
                return ['port'=>'main','data'=>$updated];

            case 'delete_user':
                $user_id = (int) ( $config['user_id'] ?? 0 );
                $reassign = $config['reassign'] ?? null;
                $deleted = $user_id ? wp_delete_user( $user_id, $reassign ? (int) $reassign : null ) : false;
                return ['port'=>'main','data'=>$deleted];

            case 'get_users':
                $args = [];
                if ( ! empty( $config['search'] ) ) {
                    $args['search'] = '*' . $config['search'] . '*';
                }
                if ( ! empty( $config['number'] ) ) {
                    $args['number'] = (int) $config['number'];
                }
                $users = get_users( $args );
                return ['port'=>'main','data'=>static::query_users( [ 'users' => $users ] )];

            case 'get_users_by_role':
                $args = [
                    'role' => $config['role'] ?? '',
                ];
                if ( ! empty( $config['search'] ) ) {
                    $args['search'] = '*' . $config['search'] . '*';
                }
                if ( ! empty( $config['number'] ) ) {
                    $args['number'] = (int) $config['number'];
                }
                $users = get_users( $args );
                return ['port'=>'main','data'=>static::query_users( [ 'users' => $users ] )];

            case 'get_user_by_id':
                $user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
                return ['port'=>'main','data'=>WordpressHelpers::get_user_payload( $user )];

            case 'get_user_by_email':
                $user = get_user_by( 'email', (string) ( $config['user_email'] ?? '' ) );
                return ['port'=>'main','data'=>WordpressHelpers::get_user_payload( $user )];

            case 'get_user_by_field':
                $user = get_user_by( (string) ( $config['field'] ?? '' ), $config['value'] ?? '' );
                return ['port'=>'main','data'=>WordpressHelpers::get_user_payload( $user )];

            case 'get_user_meta_all':
                return ['port'=>'main','data'=>get_user_meta( (int) ( $config['user_id'] ?? 0 ) )];

            case 'get_user_meta_single':
                return ['port'=>'main','data'=>get_user_meta( (int) ( $config['user_id'] ?? 0 ), (string) ( $config['meta_key'] ?? '' ), true )];

            case 'update_user_meta':
                $updated = update_user_meta(
                    (int) ( $config['user_id'] ?? 0 ),
                    (string) ( $config['meta_key'] ?? '' ),
                    $config['meta_value'] ?? null
                );
                return ['port'=>'main','data'=>$updated];

            case 'create_role':
                $role_input = (string) ( $config['role'] ?? '' );
                $role_key = sanitize_key( $role_input );
                $display_name = (string) ( $config['display_name'] ?? '' );
                $display_name = $display_name !== '' ? $display_name : $role_input;
                $caps = WordpressHelpers::normalize_caps( $config['capabilities'] ?? [] );

                $role = add_role( $role_key, $display_name, $caps );
                if ( ! $role ) {
                    $role = get_role( $role_key );
                    if ( $role ) {
                        if ( $display_name !== '' ) {
                            WordpressHelpers::set_role_display_name( $role_key, $display_name );
                        }
                        if ( $caps ) {
                            foreach ( $caps as $cap => $grant ) {
                                if ( $grant ) {
                                    $role->add_cap( $cap );
                                } else {
                                    $role->remove_cap( $cap );
                                }
                            }
                        }
                    }
                }

                return ['port'=>'main','data'=>WordpressHelpers::format_role_payload( $role_key, $role )];

            case 'delete_role':
                $removed = remove_role( (string) ( $config['role'] ?? '' ) );
                return ['port'=>'main','data'=>$removed];

            case 'add_user_role':
                $user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
                $role = WordpressHelpers::resolve_role_key( $config['role'] ?? '', true );
                if ( $user ) {
                    if ( $role !== '' ) {
                        $user->add_role( $role );
                    }
                }
                return ['port'=>'main','data'=>(bool) $user];

            case 'remove_user_role':
                $user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
                $role = WordpressHelpers::resolve_role_key( $config['role'] ?? '', true );
                if ( $user ) {
                    if ( $role !== '' ) {
                        $user->remove_role( $role );
                    }
                }
                return ['port'=>'main','data'=>(bool) $user];

            case 'update_user_role':
                $user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
                $role = WordpressHelpers::resolve_role_key( $config['role'] ?? '', true );
                if ( $user ) {
                    if ( $role !== '' ) {
                        $user->set_role( $role );
                    }
                }
                return ['port'=>'main','data'=>(bool) $user];

            case 'get_roles':
                $roles = wp_roles()->roles ?? [];
                return ['port'=>'main','data'=>$roles];

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
                $role = get_role( (string) ( $config['role'] ?? '' ) );
                $caps = $role ? array_keys( $role->capabilities ?? [] ) : [];
                return ['port'=>'main','data'=>$caps];

            case 'add_role_caps':
                $role = get_role( (string) ( $config['role'] ?? '' ) );
                if ( $role ) {
                    foreach ( WordpressHelpers::normalize_list( $config['caps'] ?? [] ) as $cap ) {
                        $role->add_cap( $cap );
                    }
                }
                return ['port'=>'main','data'=>(bool) $role];

            case 'remove_role_caps':
                $role = get_role( (string) ( $config['role'] ?? '' ) );
                if ( $role ) {
                    foreach ( WordpressHelpers::normalize_list( $config['caps'] ?? [] ) as $cap ) {
                        $role->remove_cap( $cap );
                    }
                }
                return ['port'=>'main','data'=>(bool) $role];

            case 'get_user_caps':
                $user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
                return ['port'=>'main','data'=>$user ? array_keys( $user->allcaps ?? [] ) : []];

            case 'add_user_caps':
                $user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
                if ( $user ) {
                    foreach ( WordpressHelpers::normalize_list( $config['caps'] ?? [] ) as $cap ) {
                        $user->add_cap( $cap );
                    }
                }
                return ['port'=>'main','data'=>(bool) $user];

            case 'remove_user_caps':
                $user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
                if ( $user ) {
                    foreach ( WordpressHelpers::normalize_list( $config['caps'] ?? [] ) as $cap ) {
                        $user->remove_cap( $cap );
                    }
                }
                return ['port'=>'main','data'=>(bool) $user];

            case 'get_term':
                $term = get_term( (int) ( $config['term_id'] ?? 0 ), (string) ( $config['taxonomy'] ?? '' ) );
                return ['port'=>'main','data'=>$term];

            case 'get_terms_by_taxonomy':
                $terms = get_terms( [
                    'taxonomy' => $config['taxonomy'] ?? '',
                    'hide_empty' => false,
                ] );
                return ['port'=>'main','data'=>$terms];

            case 'get_term_by_field':
                $term = get_term_by( $config['field'] ?? '', $config['value'] ?? '', $config['taxonomy'] ?? '' );
                return ['port'=>'main','data'=>$term];

            case 'create_term':
                $created = wp_insert_term(
                    $config['name'] ?? '',
                    $config['taxonomy'] ?? '',
                    [
                        'slug' => $config['slug'] ?? '',
                        'parent' => $config['parent'] ?? 0,
                        'description' => $config['description'] ?? '',
                    ]
                );
                return ['port'=>'main','data'=>$created];

            case 'update_term':
                $updated = wp_update_term(
                    $config['term_id'] ?? 0,
                    $config['taxonomy'] ?? '',
                    [
                        'name' => $config['name'] ?? '',
                        'slug' => $config['slug'] ?? '',
                        'description' => $config['description'] ?? '',
                        'parent' => $config['parent'] ?? 0,
                    ]
                );
                return ['port'=>'main','data'=>$updated];

            case 'delete_term':
                $deleted = wp_delete_term(
                    $config['term_id'] ?? 0,
                    $config['taxonomy'] ?? ''
                );
                return ['port'=>'main','data'=>$deleted];

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

class WordpressHelpers {
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

        $user_payload = Wordpress::query_users( [ 'users' => [ $user ] ] );
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

        $terms = Wordpress::query_terms( [
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
}
