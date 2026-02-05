<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class FluentCrm extends IntegrationBase {

    public static function get_slug(): string {
        return 'FluentCRM';
    }

    public static function get_triggers(): array {
        return [
            'added_tag' => [
                'label' => 'Tag Added To Contact',
                'hook' => 'fluentcrm_contact_added_to_tags',
            ],
            'removed_tag' => [
                'label' => 'Tag Removed From Contact',
                'hook' => 'fluentcrm_contact_removed_from_tags',
            ],
            'added_list' => [
                'label' => 'Contact Added To List',
                'hook' => 'fluentcrm_contact_added_to_lists',
            ],
            'removed_list' => [
                'label' => 'Contact Removed From List',
                'hook' => 'fluentcrm_contact_removed_from_lists',
            ],
            'created_contact' => [
                'label' => 'Created Contact',
                'hook' => 'fluentcrm_contact_created',
            ],
            'contact_status_updated' => [
                'label' => 'Contact Status Updated',
                'hook' => 'fluentcrm_subscriber_status_to_subscribed',
            ],
            'updated_custom_fields' => [
                'label' => 'Updated Custom Fields',
                'hook' => 'fluentcrm_contact_updated',
            ],
            'contact_deleted' => [
                'label' => 'Deleted Contact',
                'hook' => 'fluentcrm_contact_deleted',
            ],
            'company_created' => [
                'label' => 'Company Created',
                'hook' => 'fluent_crm/company_created',
            ],
            'company_updated' => [
                'label' => 'Company Updated',
                'hook' => 'fluent_crm/company_updated',
            ],
            'company_deleted' => [
                'label' => 'Company Deleted',
                'hook' => 'fluent_crm/company_deleted',
            ],
            'email_opened' => [
                'label' => 'Email Opened',
                'hook' => 'fluent_crm/email_opened',
            ],
            'email_clicked' => [
                'label' => 'Email Clicked',
                'hook' => 'fluent_crm/email_url_clicked',
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {

        if ( in_array( $trigger, ['added_tag','removed_tag'], true ) ) {
            $options = [ 
                ['label' => 'Any Tag', 'value' => 'any'],
            ];
            if ( class_exists( 'FluentCrm\App\Models\Tag' ) ) {
                $tags = \FluentCrm\App\Models\Tag::all();
                foreach ( $tags as $tag ) {
                    $options[] = [
                        'label' => $tag->title,
                        'value' => $tag->id,
                    ];
                }
            }
            return [
                [
                    'key'      => 'tag_id',
                    'label'    => 'Tags',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        if ( in_array( $trigger, ['added_list','removed_list'], true ) ) {
            $options = [ 
                ['label' => 'Any List', 'value' => 'any'],
            ];
            if ( class_exists( 'FluentCrm\App\Models\Lists' ) ) {
                $lists = \FluentCrm\App\Models\Lists::all();
                foreach ( $lists as $list ) {
                    $options[] = [
                        'label' => $list->title,
                        'value' => $list->id,
                    ];
                }
            }
            return [
                [
                    'key'      => 'list_id',
                    'label'    => 'List',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        if ( $trigger === 'contact_status_updated' ) {
            return [
                [
                    'key'      => 'status',
                    'label'    => 'Status Updated',
                    'type'     => 'select',
                    'options'  => [
                        ['label' => 'Any Status', 'value' => 'any' ],
                        ['label' => 'Subscribed', 'value' => 'subscribed' ],
                        ['label' => 'Pending', 'value' => 'pending' ],
                        ['label' => 'Unsubscribed',   'value' => 'unsubscribed' ],
                        ['label' => 'Transactional',   'value' => 'transactional' ],
                        ['label' => 'Bounced',   'value' => 'bounced' ],
                        ['label' => 'Complained',   'value' => 'complained' ],
                        ['label' => 'Spammed',   'value' => 'Spammed' ],
                    ],
                    'required' => true,
                ],
            ];
        }

        if ( in_array( $trigger, ['email_opened', 'email_clicked'], true ) ) {
            $options = [ 
                ['label' => 'Any Campaign', 'value' => 'any'],
            ];
            if ( class_exists( 'FluentCrm\App\Models\Campaign' ) ) {
                $campaigns = \FluentCrm\App\Models\Campaign::all();
                foreach ( $campaigns as $campaign ) {
                    $options[] = [
                        'label' => $campaign->title,
                        'value' => $campaign->id,
                    ];
                }
            }
            return [
                [
                    'key'      => 'campaign_id',
                    'label'    => 'Campaign',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        return [];
    }

    private static function resolve_contact_payload( $contact ): array {
        return [
            'id'             => $contact->id ?? $contact->ID ?? 0,
            'prefix'         => $contact->prefix ?? '',
            'first_name'     => $contact->first_name ?? '',
            'last_name'      => $contact->last_name ?? '',
            'full_name'      => $contact->full_name ?? '',
            'email'          => $contact->email ?? '',
            'status'         => $contact->status ?? '',
            'timezone'       => $contact->timezone ?? '',
            'address_line_1' => $contact->address_line_1 ?? '',
            'address_line_2' => $contact->address_line_2 ?? '',
            'city'           => $contact->city ?? '',
            'state'          => $contact->state ?? '',
            'postal_code'    => $contact->postal_code ?? '',
            'country'        => $contact->country ?? '',
            'ip'             => $contact->ip ?? '',
            'phone'          => $contact->phone ?? '',
            'source'         => $contact->source ?? '',
            'date_of_birth'  => $contact->date_of_birth ?? '',
        ];
    }

    private static function resolve_company_payload( $company ): array {
        return [
            'id'          => $company->id ?? 0,
            'title'       => $company->title ?? '',
            'email'       => $company->email ?? '',
            'phone'       => $company->phone ?? '',
            'website'     => $company->website ?? '',
            'address'     => $company->address ?? '',
            'city'        => $company->city ?? '',
            'state'       => $company->state ?? '',
            'postal_code' => $company->postal_code ?? '',
            'country'     => $company->country ?? '',
            'created_at'  => $company->created_at ?? '',
            'updated_at'  => $company->updated_at ?? '',
        ];
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {
    
            case 'added_tag':
            case 'removed_tag':
                $contact = $args[0] ?? null;
                $tag_ids = $args[1] ?? [];
                if ( ! $contact || empty( $tag_ids) ) return false;
                $contact_data = self::resolve_contact_payload( $contact );
                $tags = [];
                if ( ! empty( $contact->tags ) ) {
                    foreach ( $contact->tags as $tag ) {
                        $tags[] = [
                            'id'   => $tag->id,
                            'name' => $tag->name ?? $tag->title ?? '',
                            'slug' => $tag->slug ?? '',
                        ];
                    }
                }
                return [
                    'success'    => true,
                    'contact'    => $contact_data,
                    'object_ids' => $tag_ids,
                    'tags'       => $tags,
                ];

            case 'added_list':
            case 'removed_list':
                $contact = $args[0] ?? null;
                $list_ids = $args[1] ?? [];
                if ( ! $contact || empty( $list_ids) ) return false;
                $contact_data = self::resolve_contact_payload( $contact );
                $lists = [];
                if ( ! empty( $contact->lists ) ) {
                    foreach ( $contact->lists as $list ) {
                        $lists[] = [
                            'id'   => $list->id,
                            'name' => $list->name ?? $list->title ?? '',
                            'slug' => $list->slug ?? '',
                        ];
                    }
                }
                return [
                    'success'  => true,
                    'contact'  => $contact_data,
                    'list_ids' => $list_ids,
                    'tags'     => $lists,
                ];

            case 'created_contact':
            case 'contact_deleted':
                $contact = $args[0] ?? null;
                if ( ! $contact ) return false;
                return [
                    'success' => true,
                    'contact' => self::resolve_contact_payload( $contact ),
                ];

            case 'contact_status_updated':
                $contact    = $args[0] ?? null;
                $old_status = $args[1] ?? '';
                $new_status = $args[2] ?? '';
                if ( ! $contact || ! $new_status ) return false;
                return [
                    'success'    => true,
                    'contact'    => self::resolve_contact_payload( $contact ),
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                ];

            case 'updated_custom_fields':
                $contact  = $args[0] ?? null;
                $new_data = $args[1] ?? [];
                if ( ! $contact ) return false;
                $new_field = $new_data['custom_fields'] ?? [];
                return [
                    'success'  => true,
                    'contact'  => self::resolve_contact_payload( $contact ),
                    'custom_fields' => $new_field,
                ];

            case 'company_created':
            case 'company_deleted':
                $company    = $args[0] ?? null;
                if ( ! $company ) return false;
                return [
                    'success' => true,
                    'company' => self::resolve_company_payload( $company ),
                ];

            case 'company_updated':
                $company    = $args[0] ?? null;
                $old_status = $args[1] ?? [];
                $new_status = $args[2] ?? [];
                if ( ! $company ) return false;
                return [
                    'success'    => true,
                    'company'    => self::resolve_company_payload( $company ),
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                ];

            case 'email_opened':
                $contact  = $args[0] ?? null;
                $campaign = $args[1] ?? null;
                if ( ! $contact ) return false;
                return [
                    'success'  => true,
                    'contact'  => self::resolve_contact_payload( $contact ),
                    'campaign' => [
                        'id'    => $campaign->id ?? 0,
                        'title' => $campaign->title ?? '',
                    ],
                ];

            case 'email_clicked':
                $contact  = $args[0] ?? null;
                $campaign = $args[1] ?? null;
                $link     = $args[2] ?? null;
                if ( ! $contact ) return false;
                return [
                    'success'  => true,
                    'contact'  => self::resolve_contact_payload( $contact ),
                    'campaign' => [
                        'id'    => $campaign->id ?? 0,
                        'title' => $campaign->title ?? '',
                    ],
                    'link' => [
                        'id'    => $link['url'] ?? '',
                        'title' => $link['title'] ?? '',
                    ],
                ];


        }
        return false;
    }

    public static function get_actions(): array {
        return [
           'created_contact' => ['label' => 'Create Contact'],
           'get_contact_all' => ['label' => 'Get Contact (All)'],
           'get_contact_id' => ['label' => 'Get Contact (By Id)'],
           'get_contact_email' => ['label' => 'Get Contact (By Email)'],
           'get_contact_by_tags' => ['label' => 'Get Contact (By Tag ID)'],
           'get_contact_by_lists' => ['label' => 'Get Contact (By Lists ID)'],
           'get_contact_by_status' => ['label' => 'Get Contact (By Status)'],
           'get_tag_all' => ['label' => 'Get Tag (All)'],
           'created_tag' => ['label' => 'Created Tag'],
           'add_tag_to_contact' => ['label' => 'Add Tag To Contact'],
           'remove_tag_from_contact' => ['label' => 'Remove Tag From Contact'],
           'get_list_all' => ['label' => 'Get List (All)'],
           'created_list' => ['label' => 'Created List'],
           'add_list_to_contact' => ['label' => 'Add List To Contact'],
           'remove_list_from_contact' => ['label' => 'Remove List From Contact'],
           'get_company_all' => ['label' => 'Get Company (All)'],
           'get_company_id' => ['label' => 'Get Company (By Id)'],
           'created_company' => ['label' => 'Created company'],
           'add_company_to_contact' => ['label' => 'Add Company To Contact'],
           'remove_company_from_contact' => ['label' => 'Remove Company From Contact'],
        ];
    }

    private static function get_lists() {
        $options = [['label' => 'Any List', 'value' => 'any'],];
        if ( class_exists( 'FluentCrm\App\Models\Lists' ) ) {
            $lists = \FluentCrm\App\Models\Lists::all();
            foreach ( $lists as $list ) {
                $options[] = [
                    'label' => $list->title,
                    'value' => $list->id,
                ];
            }
        }
        return $options;
    }

    private static function get_tags() {
        $options = [['label' => 'Any Tag', 'value' => 'any'],];
        if ( class_exists( 'FluentCrm\App\Models\Tag' ) ) {
             $tags = \FluentCrm\App\Models\Tag::all();
            foreach ( $tags as $tag ) {
                $options[] = [
                    'label' => $tag->title,
                    'value' => $tag->id,
                ];
            }
        }
        return $options;
    }

    private static function get_companies() {
        $options = [['label' => 'Any Campaign', 'value' => 'any'],];
        if ( class_exists( 'FluentCrm\App\Models\Campaign' ) ) {
            $campaigns = \FluentCrm\App\Models\Campaign::all();
            foreach ( $campaigns as $campaign ) {
                $options[] = [
                    'label' => $campaign->title,
                    'value' => $campaign->id,
                ];
            }
        }
        return $options;
    }

    private static function get_company() {
        $options = [];
        if ( class_exists( 'FluentCrm\App\Models\Company' ) ) {
            $companies = \FluentCrm\App\Models\Company::all();
            foreach ( $companies as $company ) {
                $options[] = [
                    'label' => $company->name,
                    'value' => $company->id,
                ];
            }
        }
        return $options;
    }
    
    public static function get_action_config_schema( string $action ): array {

        $field_options = [
            ['label' => 'Prefix',          'value' => 'prefix'],
            ['label' => 'First Name',      'value' => 'first_name'],
            ['label' => 'Last Name',       'value' => 'last_name'],
            ['label' => 'Full Name',       'value' => 'full_name'],
            ['label' => 'Email',           'value' => 'email'],
            ['label' => 'Timezone',        'value' => 'timezone'],
            ['label' => 'Address Line 1',  'value' => 'address_line_1'],
            ['label' => 'Address Line 2',  'value' => 'address_line_2'],
            ['label' => 'City',            'value' => 'city'],
            ['label' => 'State',           'value' => 'state'],
            ['label' => 'Postal Code',     'value' => 'postal_code'],
            ['label' => 'Country',         'value' => 'country'],
            ['label' => 'IP',              'value' => 'ip'],
            ['label' => 'Phone',           'value' => 'phone'],
            ['label' => 'Source',          'value' => 'source'],
            ['label' => 'Date of Birth',   'value' => 'date_of_birth'],
            ['label' => 'Status',          'value' => 'status'],
        ];
        $company_field = [
            ['label' => 'Company Name',          'value' => 'name'],
            ['label' => 'Owner Name',            'value' => 'owner_name'],
            ['label' => 'Owner Email',         'value' => 'email'],
            ['label' => 'Company Email',         'value' => 'email'],
            ['label' => 'Company Phone Number',  'value' => 'phone'],
            ['label' => 'Company Logo',          'value' => 'Company_logo'],
            ['label' => 'Industry',              'value' => 'industry'],
            ['label' => 'Type',                  'value' => 'type'],
            ['label' => 'Company Owner',         'value' => 'owner_id'],
            ['label' => 'Description',           'value' => 'description'],
            ['label' => 'Number of Employees',   'value' => 'employees_number'],
            ['label' => 'Address Line 1',        'value' => 'address_line_1'],
            ['label' => 'Address Line 2',        'value' => 'address_line_2'],
            ['label' => 'City',                  'value' => 'city'],
            ['label' => 'State',                 'value' => 'state'],
            ['label' => 'Postal Code',           'value' => 'postal_code'],
            ['label' => 'Country',               'value' => 'country'],
            ['label' => 'LinkedIn URL',           'value' => 'linkedin_url'],
            ['label' => 'Facebook Page URL',      'value' => 'facebook_url'],
            ['label' => 'Twitter URL',            'value' => 'twitter_url'],
            ['label' => 'Website',               'value' => 'website'],
        ];


        $schemas = [
            'created_contact' => [
                ['key'=>'lists','label'=>'Lists','type'=>'select','options'=>self::get_lists()],
                ['key'=>'tags','label'=>'Tags','type'=>'select','options'=>self::get_tags()],
                ['key'=>'primary_company','label'=>'Primary Company','type'=>'select','options'=>self::get_companies()],
                ['key' => 'field_map','label' => 'Field Map','type' => 'repeater','button_label' => 'Add Field','fields' => [
                    ['key' => 'field', 'label' => 'Field', 'type' => 'select','options'=>$field_options],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text'],]
                ],
                ['key' => 'custom_field_map','label' => 'Custom Field Map','type' => 'repeater','button_label' => 'Add Custom Field','fields' => [
                    ['key' => 'field', 'label' => 'Field', 'type' => 'text'],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text'],]
                ],       
            ],
            'get_contact_id' => [
                ['key'=>'contact_id', 'label'=>'ID', 'type'=>'expression', 'required'=>true,],
            ],
            'get_contact_email' => [
                ['key'=>'email', 'label'=>'Email Address', 'type'=>'expression', 'required'=>true,],
            ],
            'get_contact_by_tags' => [
                ['key'=>'tag_id', 'label'=>'Tag ID', 'type'=>'expression', 'required'=>true,],
            ],
            'get_contact_by_lists' => [
                ['key'=>'list_id', 'label'=>'List ID', 'type'=>'expression', 'required'=>true,],
            ],
            'get_contact_by_status' => [
                ['key'=>'status', 'label'=>'Contact Status', 'type'=>'select','options'  => [
                        ['label' => 'Subscribed', 'value' => 'subscribed' ],
                        ['label' => 'Pending', 'value' => 'pending' ],
                        ['label' => 'Unsubscribed', 'value' => 'unsubscribed' ],
                        ['label' => 'Transactional', 'value' => 'transactional' ],
                        ['label' => 'Bounced', 'value' => 'bounced' ],
                        ['label' => 'Complained', 'value' => 'complained' ],
                        ['label' => 'Spammed', 'value' => 'Spammed' ],
                    ],
                'required'=>true,],
            ],
            'created_tag' => [
                ['key'=>'title', 'label'=>'Tag Title', 'type'=>'text', 'required'=>true,],
                ['key'=>'slug', 'label'=>'Tag Slug', 'type'=>'text', 'required'=>false,],
                ['key'=>'description', 'label'=>'Description', 'type'=>'text', 'required'=>false,],
            ],
            'add_tag_to_contact' => [
                ['key'=>'contact_id', 'label'=>'Contact ID', 'type'=>'expression', 'required'=>true,],
                ['key'=>'tags','label'=>'Tags','type'=>'select','options'=>self::get_tags(), 'required'=>true, 'multiple' => true,],
            ],
            'remove_tag_from_contact' => [
                ['key'=>'contact_id', 'label'=>'Contact ID', 'type'=>'expression', 'required'=>true,],
                ['key'=>'tags','label'=>'Tags','type'=>'select','options'=>self::get_tags(), 'required'=>true, 'multiple' => true,],
            ],
            'created_list' => [
                ['key'=>'title', 'label'=>'List Title', 'type'=>'text', 'required'=>true,],
                ['key'=>'slug', 'label'=>'List Slug', 'type'=>'text', 'required'=>false,],
                ['key'=>'description', 'label'=>'Description', 'type'=>'text', 'required'=>false,],
            ],
            'add_list_to_contact' => [
                ['key'=>'contact_id', 'label'=>'Contact ID', 'type'=>'expression', 'required'=>true,],
                ['key'=>'lists','label'=>'Lists','type'=>'select','options'=>self::get_lists(), 'required'=>true, 'multiple' => true,],
            ],
            'remove_list_from_contact' => [
                ['key'=>'contact_id', 'label'=>'Contact ID', 'type'=>'expression', 'required'=>true,],
                ['key'=>'lists','label'=>'Lists','type'=>'select','options'=>self::get_lists(), 'required'=>true, 'multiple' => true,],
            ],
            'get_company_id' => [
                ['key'=>'company_id', 'label'=>'Company ID', 'type'=>'expression', 'required'=>true,],
            ],
            'created_company' => [
                ['key' => 'field_map','label' => 'Field Map','type' => 'repeater','button_label' => 'Add Field','fields' => [
                    ['key' => 'field', 'label' => 'Field', 'type' => 'select','options'=>$company_field],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text'],]
                ],
                ['key' => 'custom_field_map','label' => 'Custom Field Map','type' => 'repeater','button_label' => 'Add Custom Field','fields' => [
                    ['key' => 'field', 'label' => 'Field', 'type' => 'text'],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text'],]
                ],       
            ],
            'add_company_to_contact' => [
                ['key'=>'contact_id', 'label'=>'Contact ID', 'type'=>'expression', 'required'=>true,],
                ['key'=>'company','label'=>'company','type'=>'select','options'=>self::get_company(), 'required'=>true, 'multiple' => true,],
            ],
            'remove_company_from_contact' => [
                ['key'=>'contact_id', 'label'=>'Contact ID', 'type'=>'expression', 'required'=>true,],
                ['key'=>'company','label'=>'company','type'=>'select','options'=>self::get_company(), 'required'=>true, 'multiple' => true,],
            ],
        ];

        return $schemas[$action] ?? [];
    }

    private static function resolve_sub_payload( $sub ): array {
        return [
            'id'             => $sub->id,
            'user_id'        => $sub->user_id,
            'hash'           => $sub->hash,
            'contact_owner'  => $sub->contact_owner,
            'company_id'     => $sub->company_id,
            'prefix'         => $sub->prefix,
            'first_name'     => $sub->first_name,
            'last_name'      => $sub->last_name,
            'full_name'      => $sub->full_name,
            'email'          => $sub->email,
            'timezone'       => $sub->timezone,
            'address_line_1' => $sub->address_line_1,
            'address_line_2' => $sub->address_line_2,
            'postal_code'    => $sub->postal_code,
            'city'           => $sub->city,
            'state'          => $sub->state,
            'country'        => $sub->country,
            'ip'             => $sub->ip,
            'latitude'       => $sub->latitude,
            'longitude'      => $sub->longitude,
            'total_points'   => $sub->total_points,
            'life_time_value'=> $sub->life_time_value,
            'phone'          => $sub->phone,
            'status'         => $sub->status,
            'contact_type'   => $sub->contact_type,
            'source'         => $sub->source,
            'avatar'         => $sub->avatar,
            'date_of_birth'  => $sub->date_of_birth,
            'created_at'     => $sub->created_at,
            'last_activity'  => $sub->last_activity,
            'updated_at'     => $sub->updated_at,
            'photo'          => $sub->photo,
        ];
    }

    private static function resolve_companies_payload( $company ): array {
        return [
            'id'               => $company->id,
            'hash'             => $company->hash,
            'owner_id'         => $company->owner_id,
            'name'             => $company->name,
            'industry'         => $company->industry,
            'email'            => $company->email,
            'timezone'         => $company->timezone,
            'address_line_1'   => $company->address_line_1,
            'address_line_2'   => $company->address_line_2,
            'postal_code'      => $company->postal_code,
            'city'             => $company->city,
            'state'            => $company->state,
            'country'          => $company->country,
            'employees_number' => $company->employees_number,
            'description'      => $company->description,
            'phone'            => $company->phone,
            'type'             => $company->type,
            'logo'             => $company->logo,
            'website'          => $company->website,
            'linkedin_url'     => $company->linkedin_url,
            'facebook_url'     => $company->facebook_url,
            'twitter_url'      => $company->twitter_url,
            'date_of_start'    => $company->date_of_start,
            'meta'             => $company->meta ?? ['custom_values'=>[]],
            'created_at'       => $company->created_at,
            'updated_at'       => $company->updated_at,
        ];
    }

    private static function resolve_tag_payload( $tag ): array {
        return [
            'id'          => $tag->id,
            'title'       => $tag->title,
            'slug'        => $tag->slug,
            'description' => $tag->description,
            'created_at'  => $tag->created_at,
            'updated_at'  => $tag->updated_at,
        ];
    }

    private static function resolve_list_payload( $list ): array {
        return [
            'id'          => $list->id,
            'title'       => $list->title,
            'slug'        => $list->slug,
            'description' => $list->description,
            'created_at'  => $list->created_at,
            'updated_at'  => $list->updated_at,
        ];
    }

    private static function resolve_pivot_tag_payload( $tag ): array {
        return [
            'subscriber_id' => $tag->pivot->subscriber_id,
            'object_id'     => $tag->pivot->object_id,
            'object_type'   => $tag->pivot->object_type,
            'created_at'    => $tag->pivot->created_at,
            'updated_at'    => $tag->pivot->updated_at,
        ];
    }

    private static function resolve_pivot_list_payload( $list ): array {
        return [
            'subscriber_id' => $list->pivot->subscriber_id,
            'object_id'     => $list->pivot->object_id,
            'object_type'   => $list->pivot->object_type,
            'created_at'    => $list->pivot->created_at,
            'updated_at'    => $list->pivot->updated_at,
        ];
    }

    private static function resolve_pivot_company_payload( $company ): array {
        return [
            'subscriber_id' => $company->pivot->subscriber_id,
            'object_id'     => $company->pivot->object_id,
            'object_type'   => $company->pivot->object_type,
            'created_at'    => $company->pivot->created_at,
            'updated_at'    => $company->pivot->updated_at,
        ];
    }

    private static function normalize_ids( $ids ): array {
        if ( empty( $ids ) ) return [];
        if ( is_string( $ids ) ) {
            $ids = strpos( $ids, ',') !== false ? explode( ',', $ids ) : [ $ids ];
        }
        return array_map( 'intval', (array) $ids );
    }

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];
                
        switch ( $node['data']['event'] ?? '' ) {

            case 'created_contact':
                $field_map        = $config['field_map'] ?? [];
                $custom_field_map = $config['custom_field_map'] ?? [];
                $lists            = $config['lists'] ?? [];
                $tags             = $config['tags'] ?? [];
                $data             = [];
                $email            = '';
                
                foreach ( $field_map as $row ) {
                    if ( empty( $row['field'] ) ) continue;
                    $data[ $row['field'] ] = $row['value'] ?? '';
                    if ( $row['field'] === 'email' ) {
                        $email = $row['value'];
                    }
                }
                if ( ! $email ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Email is required',],];
                }
                if ( ! class_exists('\FluentCrm\App\Models\Subscriber') ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::updateOrCreate(['email' => $email], $data );
                if ( ! $contact ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact Create/Update Failed',], ];
                }
                if ( ! empty( $lists ) && $lists !== 'any' ) {
                    $contact->attachLists( (array) $lists );
                }
                if ( ! empty( $tags ) && $tags !== 'any' ) {
                    $contact->attachTags( (array) $tags );
                }
                foreach ( $custom_field_map as $row ) {
                    if ( empty( $row['field'] ) ) continue;
                    $contact->updateCustomField( $row['field'], $row['value'] ?? '' );
                }
                return ['port'=>'main','data'=>['success' => true, 'contact' => self::resolve_sub_payload( $contact ), ], ];

            case 'get_contact_all':
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $subscriber = \FluentCrm\App\Models\Subscriber::all();
                $contacts = [];
                foreach ( $subscriber as $sub ) {
                    $contacts[] = self::resolve_sub_payload($sub);
                }
                return ['port'=>'main','data'=>['success' => true, 'contacts' => $contacts ], ];

            case 'get_contact_id':
                $contact_id = $config['contact_id'] ?? 0;
                if ( ! $contact_id ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id);
                if ( ! $contact ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact is not found',],];
                }
                return ['port'=>'main','data'=>['success' => true, 'contact' => self::resolve_sub_payload( $contact ), ], ];
            
            case 'get_contact_email':
                $email = $config['email'] ?? 0;
                if ( ! $email ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Email is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::where('email', $email)->first();
                if ( ! $contact ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact is not found',],];
                }
                return ['port'=>'main','data'=>['success' => true, 'contact' => self::resolve_sub_payload( $contact ), ], ];
            
            case 'get_contact_by_tags':
                $tag_ids = self::normalize_ids( $config['tag_id'] ?? [] );
                if ( empty( $tag_ids ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag ID is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $subscribers = \FluentCrm\App\Models\Subscriber::whereHas('tags', function ($q) use ($tag_ids) {
                    $q->whereIn('fc_tags.id', $tag_ids);
                })->get();
                if ($subscribers->isEmpty()) {
                    return ['port' => 'main','data' => ['success' => false,'message' => 'No contacts found for given tags',],];
                }
                $contacts = [];
                foreach ($subscribers as $sub) {
                    $contacts[] = self::resolve_sub_payload($sub);
                }
                return ['port' => 'main','data' => ['success' => true,'count' => count($contacts),'contacts'=> $contacts,],];
            
            case 'get_contact_by_lists':
                $list_ids = self::normalize_ids( $config['list_id'] ?? [] );
                if ( empty( $list_ids ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'List ID is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $subscribers = \FluentCrm\App\Models\Subscriber::whereHas('lists', function ($q) use ($list_ids) {
                    $q->whereIn('fc_lists.id', $list_ids);
                })->get();
                if ($subscribers->isEmpty()) {
                    return ['port' => 'main','data' => ['success' => false,'message' => 'No contacts found for given lists',],];
                }
                $contacts = [];
                foreach ($subscribers as $sub) {
                    $contacts[] = self::resolve_sub_payload($sub);
                }
                return ['port' => 'main','data' => ['success' => true,'count' => count($contacts),'contacts'=> $contacts,],];
            
            case 'get_contact_by_status':
                $status = $config['status'] ?? [];
                if ( empty( $status ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Status is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $subscribers = \FluentCrm\App\Models\Subscriber::where('status', sanitize_text_field( $status) )->get();
                if ($subscribers->isEmpty()) {
                    return ['port' => 'main','data' => ['success' => false,'message' => 'No contacts found for given status',],];
                }
                $contacts = [];
                foreach ($subscribers as $sub) {
                    $contacts[] = self::resolve_sub_payload($sub);
                }
                return ['port' => 'main','data' => ['success' => true,'status' => $status, 'count' => count($contacts),'contacts'=> $contacts,],];
                 
            case 'get_tag_all':
                if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Tag Not Found',],];
                }
                $tags = \FluentCrm\App\Models\Tag::all();
                if ($tags->isEmpty()) {
                    return ['port' => 'main','data' => ['success' => true,'count' => 0,'tags' => [],],];
                }
                $tag_list = [];
                foreach ($tags as $tag) {
                    $tag_list[] = self::resolve_tag_payload($tag);
                }
                return ['port' => 'main','data' => ['success' => true,'count' => count($tag_list),'tags'=> $tag_list,],];
            
            case 'created_tag':
                $title       = $config['title'] ?? '';
                $slug        = $config['slug'] ?? '';
                $description = $config['description'] ?? '';
                if ( ! $title )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag Title is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Tag Not Found',],];
                }
                $tag = \FluentCrm\App\Models\Tag::where('title', $title)->first();
                if (! $tag) {
                    $tag = new \FluentCrm\App\Models\Tag();
                }
                $tag->title       = $title;
                $tag->description = $description;
                $tag->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
                $tag->save();
                return ['port' => 'main','data' => ['success' => true,'tag' => self::resolve_tag_payload($tag),],];

            case 'add_tag_to_contact':
                $contact_id = $config['contact_id'] ?? 0;
                $tags        = $config['tags'] ?? '';
                if ( ! $contact_id )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
                }
                if ( empty( $tags ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
                if ( ! $contact )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
                }
                $contact->attachTags((array) $tags );
                $contact = \FluentCrm\App\Models\Subscriber::with('tags')->find( $contact_id );
                $tag_payload = [];
                foreach ( $contact->tags as $tag ) {
                    $tag_payload[] = array_merge( self::resolve_tag_payload( $tag ), ['pivot' => self::resolve_pivot_tag_payload( $tag )],);
                }
                return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => self::resolve_sub_payload($contact),'tags' => $tag_payload,],];
            
            case 'remove_tag_from_contact':
                $contact_id = $config['contact_id'] ?? 0;
                $tags = array_map('intval', (array) ($config['tags'] ?? []));

                if ( ! $contact_id )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
                }
                if ( empty( $tags ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
                if ( ! $contact )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
                }
                $contact->detachTags( $tags );
                $contact = \FluentCrm\App\Models\Subscriber::with('tags')->find( $contact_id );
                $tag_payload = [];
                foreach ( $contact->tags as $tag ) {
                    $tag_payload[] = array_merge( self::resolve_tag_payload( $tag ), ['pivot' => self::resolve_pivot_tag_payload( $tag )],);
                }
                return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => self::resolve_sub_payload($contact),'tags' => $tag_payload,],];
            
            case 'get_list_all':
                if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Lists Not Found',],];
                }
                $lists = \FluentCrm\App\Models\Lists::all();
                if ($lists->isEmpty()) {
                    return ['port' => 'main','data' => ['success' => true,'count' => 0,'lists' => [],],];
                }
                $list_payload = [];
                foreach ($lists as $list) {
                    $list_payload[] = self::resolve_list_payload($list);
                }
                return ['port' => 'main','data' => ['success' => true,'count' => count($list_payload),'lists'=> $list_payload,],];
            
            case 'created_list':
                $title       = $config['title'] ?? '';
                $slug        = $config['slug'] ?? '';
                $description = $config['description'] ?? '';
                if ( ! $title )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'List Title is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM List Not Found',],];
                }
                $list = \FluentCrm\App\Models\Lists::where('title', $title)->first();
                if (! $list) {
                    $list = new \FluentCrm\App\Models\Lists();
                }
                $list->title       = $title;
                $list->description = $description;
                $list->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
                $list->save();
                return ['port' => 'main','data' => ['success' => true,'list' => self::resolve_list_payload($list),],];

            case 'add_list_to_contact':
                $contact_id = $config['contact_id'] ?? 0;
                $lists      = $config['lists'] ?? [];
                if ( ! $contact_id )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
                }
                if ( empty( $lists ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Lists is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
                if ( ! $contact )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
                }
                $contact->attachLists((array) $lists );
                $contact = \FluentCrm\App\Models\Subscriber::with('lists')->find( $contact_id );
                $list_payload = [];
                foreach ( $contact->lists as $list ) {
                    $list_payload[] = array_merge( self::resolve_list_payload( $list ), ['pivot' => self::resolve_pivot_list_payload( $list )],);
                }
                return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => self::resolve_sub_payload($contact),'lists' => $list_payload,],];
            
            case 'remove_list_from_contact':
                $contact_id = $config['contact_id'] ?? 0;
                $lists = array_map('intval', (array) ($config['lists'] ?? []));

                if ( ! $contact_id )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
                }
                if ( empty( $lists ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Lists is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
                if ( ! $contact )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
                }
                $contact->detachLists( $lists );
                $contact = \FluentCrm\App\Models\Subscriber::with('lists')->find( $contact_id );
                $list_payload = [];
                foreach ( $contact->lists as $list ) {
                    $list_payload[] = array_merge( self::resolve_list_payload( $list ), ['pivot' => self::resolve_pivot_list_payload( $list )],);
                }
                return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => self::resolve_sub_payload($contact),'lists' => $list_payload,],];
            
            case 'get_company_all':
                if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Company Not Found',],];
                }
                $companies = \FluentCrm\App\Models\Company::all();
                if ($companies->isEmpty()) {
                    return ['port' => 'main','data' => ['success' => true,'count' => 0,'companies' => [],],];
                }
                $company_payload = [];
                foreach ($companies as $company) {
                    $company_payload[] = self::resolve_companies_payload( $company );
                }
                return ['port' => 'main','data' => ['success' => true,'count' => count($company_payload),'companies'=> $company_payload,],];
            
            case 'get_company_id':
                $company_id = $config['company_id'] ?? 0;
                if ( ! $company_id )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Company ID is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Company Not Found',],];
                }
                $company = \FluentCrm\App\Models\Company::find( $company_id );
                if ( ! $company )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Company Not Found',],];
                }
                return ['port' => 'main','data' => ['success' => true,'company' => self::resolve_companies_payload( $company ),],];
            
            case 'created_company':
                $field_map        = $config['field_map'] ?? [];
                $custom_field_map = $config['custom_field_map'] ?? [];
                if ( empty( $field_map ) )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Field Map is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Company Not Found',],];
                }
                $field_data = [];
                foreach ( $field_map as $row ) {
                    if ( empty( $row['field'] ) ) continue;
                    $field_data[ $row['field'] ] = $row['value'] ?? null;
                }
                if ( empty( $field_data['name'] ) )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Company Name is required',],];
                }
                $company = new \FluentCrm\App\Models\Company();
                foreach ( $field_data as $key => $value ) {
                    $company->{ $key } = $value;
                }
                $company->save();
                if ( ! empty( $custom_field_map ) ) {
                    $meta = $company->meta ?? ['custom_values' => []];
                }
                foreach ( $custom_field_map as $row ) {
                    if ( empty( $row['field'] ) ) continue;
                    $meta['custom_values'][ $row['field'] ] = $row['value'] ?? '';
                }
                $company->meta = $meta;
                $company->save();
                return ['port'=>'main','data'=>['success' => true, 'company' => self::resolve_companies_payload( $company ), ], ];


            case 'add_company_to_contact':
                $contact_id = $config['contact_id'] ?? 0;
                $companies  = $config['company'] ?? [];
                if ( ! $contact_id )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
                }
                if ( empty( $companies ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Company is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::with('companies')->find( $contact_id );
                if ( ! $contact )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact not found',],];
                }
                $company_payload = [];
                foreach ( $companies as $company_id ) {
                    $company = \FluentCrm\App\Models\Company::find( $company_id );
                    if ( $company ) {
                        $contact->company_id = $company_id;
                        $contact->save();
                        $company_payload[] = array_merge( self::resolve_companies_payload( $company ), ['pivot' => self::resolve_pivot_company_payload( $company )],);
                    }
                }
                $contact = \FluentCrm\App\Models\Subscriber::with('companies')->find( $contact_id );
                return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => self::resolve_sub_payload($contact),'companies' => $company_payload,],];
            
            case 'remove_company_from_contact':
                $contact_id = $config['contact_id'] ?? 0;
                $lists = array_map('intval', (array) ($config['lists'] ?? []));

                if ( ! $contact_id )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
                }
                if ( empty( $lists ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'Lists is required',],];
                }
                if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
                }
                $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
                if ( ! $contact )  {
                    return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
                }
                $contact->detachLists( $lists );
                $contact = \FluentCrm\App\Models\Subscriber::with('lists')->find( $contact_id );
                $list_payload = [];
                foreach ( $contact->lists as $list ) {
                    $list_payload[] = array_merge( self::resolve_list_payload( $list ), ['pivot' => self::resolve_pivot_list_payload( $list )],);
                }
                return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => self::resolve_sub_payload($contact),'lists' => $list_payload,],];

            
        }
        return ['port'=>'main','data'=>$input];
    }
}
