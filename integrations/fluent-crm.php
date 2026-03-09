<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'added_tag', 'removed_tag' ], true ) ) {
			$options = [
				[
					'label' => 'Any Tag',
					'value' => 'any'
				],
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
					'key' => 'tag_id',
					'label' => 'Tags',
					'type' => 'select',
					'options' => $options,
					'required' => true
				],
			];
		}//end if

		if ( in_array( $trigger, [ 'added_list', 'removed_list' ], true ) ) {
			$options = [
				[
					'label' => 'Any List',
					'value' => 'any'
				],
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
					'key' => 'list_id',
					'label' => 'List',
					'type' => 'select',
					'options' => $options,
					'required' => true
				],
			];
		}//end if

		return [];
	}

	private static function resolve_contact_payload( $contact ): array {
		return [
			'id'             => $contact->id,
			'user_id'        => $contact->user_id,
			'hash'           => $contact->hash,
			'contact_owner'  => $contact->contact_owner,
			'company_id'     => $contact->company_id,
			'prefix'         => $contact->prefix,
			'first_name'     => $contact->first_name,
			'last_name'      => $contact->last_name,
			'full_name'      => $contact->full_name,
			'email'          => $contact->email,
			'timezone'       => $contact->timezone,
			'address_line_1' => $contact->address_line_1,
			'address_line_2' => $contact->address_line_2,
			'postal_code'    => $contact->postal_code,
			'city'           => $contact->city,
			'state'          => $contact->state,
			'country'        => $contact->country,
			'ip'             => $contact->ip,
			'latitude'       => $contact->latitude,
			'longitude'      => $contact->longitude,
			'total_points'   => $contact->total_points,
			'life_time_value' => $contact->life_time_value,
			'phone'          => $contact->phone,
			'status'         => $contact->status,
			'contact_type'   => $contact->contact_type,
			'source'         => $contact->source,
			'avatar'         => $contact->avatar,
			'date_of_birth'  => $contact->date_of_birth,
			'created_at'     => $contact->created_at,
			'last_activity'  => $contact->last_activity,
			'updated_at'     => $contact->updated_at,
			'photo'          => $contact->photo,
		];
	}

	private static function resolve_company_payload( $company ): array {
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
			'meta'             => $company->meta ?? [ 'custom_values' => [] ],
			'created_at'       => $company->created_at,
			'updated_at'       => $company->updated_at,
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {

			case 'added_tag':
			case 'removed_tag':
				$contact = $args[0] ?? null;
				$tag_ids = $args[1] ?? [];
				if ( ! $contact || empty( $tag_ids ) ) {
					return false;
				}
				$tags = [];
				if ( ! empty( $contact->tags ) ) {
					foreach ( $contact->tags as $tag ) {
						$tags[] = array_merge( self::resolve_tag_payload( $tag ), [ 'pivot' => self::resolve_pivot_payload( $tag ) ], );
					}
				}
				return [
					'success' => true,
					'contact' => $tag_ids
				];

			case 'added_list':
			case 'removed_list':
				$contact  = $args[0] ?? null;
				$list_ids = $args[1] ?? [];
				if ( ! $contact || empty( $list_ids ) ) {
					return false;
				}
				$lists = [];
				if ( ! empty( $contact->lists ) ) {
					foreach ( $contact->lists as $list ) {
						$lists[] = self::resolve_list_payload( $list );
					}
				}
				return [
					'success' => true,
					'contact' => $list_ids
				];

			case 'created_contact':
				$contact = $args[0] ?? null;
				if ( ! $contact ) {
					return false;
				}
				return [
					'success' => true,
					'contact' => self::resolve_contact_payload( $contact )
				];

			case 'company_created':
			case 'company_deleted':
				$company = $args[0] ?? null;
				if ( ! $company ) {
					return false;
				}
				return [
					'success' => true,
					'company' => self::resolve_company_payload( $company )
				];

			case 'company_updated':
				$company    = $args[0] ?? null;
				$old_status = $args[1] ?? [];
				$new_status = $args[2] ?? [];
				if ( ! $company ) {
					return false;
				}
				return [
					'success' => true,
					'company' => self::resolve_company_payload( $company ),
					'old_status' => $old_status,
					'new_status' => $new_status
				];
		}//end switch
		return false;
	}

	public static function get_actions(): array {
		return [
			'created_contact'             => [ 'label' => 'Create Contact' ],
			'get_contact_all'             => [ 'label' => 'Get Contact (All)' ],
			'get_contact_id'              => [ 'label' => 'Get Contact (By Id)' ],
			'get_contact_email'           => [ 'label' => 'Get Contact (By Email)' ],
			'get_contact_by_tags'         => [ 'label' => 'Get Contact (By Tag ID)' ],
			'get_contact_by_lists'        => [ 'label' => 'Get Contact (By Lists ID)' ],
			'get_contact_by_status'       => [ 'label' => 'Get Contact (By Status)' ],
			'delete_contact'              => [ 'label' => 'Delete Contact' ],
			'get_tag_all'                 => [ 'label' => 'Get Tag (All)' ],
			'created_tag'                 => [ 'label' => 'Created Tag' ],
			'add_tag_to_contact'          => [ 'label' => 'Add Tag To Contact' ],
			'remove_tag_from_contact'     => [ 'label' => 'Remove Tag From Contact' ],
			'delete_tag'                  => [ 'label' => 'Delete Tag' ],
			'get_list_all'                => [ 'label' => 'Get List (All)' ],
			'created_list'                => [ 'label' => 'Created List' ],
			'add_list_to_contact'         => [ 'label' => 'Add List To Contact' ],
			'remove_list_from_contact'    => [ 'label' => 'Remove List From Contact' ],
			'delete_list'                 => [ 'label' => 'Delete List' ],
			'get_company_all'             => [ 'label' => 'Get Company (All)' ],
			'get_company_id'              => [ 'label' => 'Get Company (By Id)' ],
			'created_company'             => [ 'label' => 'Created company' ],
			'add_company_to_contact'      => [ 'label' => 'Add Company To Contact' ],
			'remove_company_from_contact' => [ 'label' => 'Remove Company From Contact' ],
			'delete_company'              => [ 'label' => 'Delete Company' ],
			'get_campaign_all'            => [ 'label' => 'Get Campaign (All)' ],
			'get_campaign_single'         => [ 'label' => 'Get Campaign (Single)' ],
			'create_campaign'             => [ 'label' => 'Create Campaign' ],
			'delete_campaign'             => [ 'label' => 'Delete Campaign' ],
			'add_event_tracking'          => [ 'label' => 'Add Event Tracking' ],
			'add_note'                    => [ 'label' => 'Add Note' ],
		];
	}

	private static function get_lists() {
		$options = [
			[
				'label' => 'Any List',
				'value' => 'any'
			]
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
		return $options;
	}

	private static function get_tags() {
		$options = [
			[
				'label' => 'Any Tag',
				'value' => 'any'
			]
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
		return $options;
	}

	private static function get_company() {
		global $wpdb;
		$options = [];
		$table = $wpdb->prefix . 'fc_companies';
		if ( $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s', $table
		) ) !== $table ) {
			return $options;
		}
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

	private static function contact_id(): array {
		return [
			[
				'key' => 'contact_id',
				'label' => 'Contact ID',
				'type' => 'expression',
				'required' => true
			]
		];
	}

	private static function tag_id(): array {
		return [
			[
				'key' => 'tag_id',
				'label' => 'Tag ID',
				'type' => 'expression',
				'required' => true
			]
		];
	}

	private static function list_id(): array {
		return [
			[
				'key' => 'list_id',
				'label' => 'List ID',
				'type' => 'expression',
				'required' => true
			]
		];
	}

	private static function company_id(): array {
		return [
			[
				'key' => 'company_id',
				'label' => 'Company ID',
				'type' => 'expression',
				'required' => true
			]
		];
	}

	private static function campaign_id(): array {
		return [
			[
				'key' => 'campaign_id',
				'label' => 'Campaign ID',
				'type' => 'expression',
				'required' => true
			]
		];
	}

	private static function contact_title(): array {
		return [
			[
				'key' => 'title',
				'label' => 'Title',
				'type' => 'text',
				'required' => true
			]
		];
	}

	private static function select_tag(): array {
		return [
			[
				'key' => 'tags',
				'label' => 'Tags',
				'type' => 'select',
				'options' => self::get_tags(),
				'required' => true,
				'multiple' => true
			]
		];
	}

	private static function select_list(): array {
		return [
			[
				'key' => 'lists',
				'label' => 'Lists',
				'type' => 'select',
				'options' => self::get_lists(),
				'required' => true,
				'multiple' => true
			]
		];
	}

	private static function contact_email(): array {
		return [
			[
				'key' => 'email',
				'label' => 'Email Address',
				'type' => 'expression',
				'required' => true
			]
		];
	}

	private static function select_company(): array {
		return [
			[
				'key' => 'company',
				'label' => 'company',
				'type' => 'select',
				'options' => self::get_company(),
				'required' => true,
				'multiple' => true
			]
		];
	}
	private static function description(): array {
		return [
			[
				'key' => 'description',
				'label' => 'Description',
				'type' => 'text',
				'required' => false
			]
		];
	}

	private static function contact_status(): array {
		return [
			[
				'key' => 'status',
				'label' => 'Status',
				'type' => 'select',
				'options'  => [
					[
						'label' => 'Subscribed',
						'value' => 'subscribed'
					],
					[
						'label' => 'Pending',
						'value' => 'pending'
					],
					[
						'label' => 'Unsubscribed',
						'value' => 'unsubscribed'
					],
					[
						'label' => 'Transactional',
						'value' => 'transactional'
					],
					[
						'label' => 'Bounced',
						'value' => 'bounced'
					],
					[
						'label' => 'Complained',
						'value' => 'complained'
					],
					[
						'label' => 'Spammed',
						'value' => 'spammed'
					],
				],
				'required' => true
			]
		];
	}

	public static function get_action_config_schema( string $action ): array {

		$schemas = [
			'created_contact' => [
				[
					'key' => 'prefix',
					'label' => 'Prefix',
					'type' => 'text'
				],
				[
					'key' => 'first_name',
					'label' => 'First Name',
					'type' => 'text'
				],
				[
					'key' => 'last_name',
					'label' => 'Last Name',
					'type' => 'text'
				],
				...self::contact_email(),
				[
					'key' => 'phone',
					'label' => 'Phone',
					'type' => 'number'
				],
				[
					'key' => 'date_of_birth',
					'label' => 'Date of Birth',
					'type' => 'date'
				],
				[
					'key' => 'address_line_1',
					'label' => 'Address Line 1',
					'type' => 'text'
				],
				[
					'key' => 'address_line_2',
					'label' => 'Address Line 2',
					'type' => 'text'
				],
				[
					'key' => 'city',
					'label' => 'City',
					'type' => 'text'
				],
				[
					'key' => 'state',
					'label' => 'State',
					'type' => 'text'
				],
				[
					'key' => 'country',
					'label' => 'Country',
					'type' => 'text'
				],
				[
					'key' => 'postal_code',
					'label' => 'Postal Code',
					'type' => 'number'
				],
				...self::select_list(),
				...self::select_tag(),
				...self::select_company(),
				...self::contact_status(),
			],
			'get_contact_id' => self::contact_id(),
			'get_contact_email' => self::contact_email(),
			'get_contact_by_tags' => self::tag_id(),
			'get_contact_by_lists' => self::list_id(),
			'get_contact_by_status' => self::contact_status(),
			'delete_contact' => self::contact_id(),
			'created_tag' => [
				...self::contact_title(),
				[
					'key' => 'slug',
					'label' => 'Tag Slug',
					'type' => 'text',
					'required' => false
				],
				...self::description(),
			],
			'add_tag_to_contact' => [
				...self::contact_id(),
				...self::select_tag(),
			],
			'remove_tag_from_contact' => [
				...self::contact_id(),
				...self::select_tag(),
			],
			'delete_tag' => self::tag_id(),
			'created_list' => [
				...self::contact_title(),
				[
					'key' => 'slug',
					'label' => 'List Slug',
					'type' => 'text',
					'required' => false
				],
				...self::description(),
			],
			'add_list_to_contact' => [
				...self::contact_id(),
				...self::select_list(),
			],
			'remove_list_from_contact' => [
				...self::contact_id(),
				...self::select_list(),
			],
			'delete_list' => self::list_id(),
			'get_company_id' => self::company_id(),
			'created_company' => [
				[
					'key' => 'company_name',
					'label' => 'Company Name',
					'type' => 'text'
				],
				[
					'key' => 'description',
					'label' => 'Description',
					'type' => 'textarea'
				],
				...self::contact_email(),
				[
					'key' => 'phone',
					'label' => 'Phone',
					'type' => 'number'
				],
				[
					'key' => 'address_line_1',
					'label' => 'Address Line 1',
					'type' => 'text'
				],
				[
					'key' => 'address_line_2',
					'label' => 'Address Line 2',
					'type' => 'text'
				],
				[
					'key' => 'city',
					'label' => 'City',
					'type' => 'text'
				],
				[
					'key' => 'state',
					'label' => 'State',
					'type' => 'text'
				],
				[
					'key' => 'country',
					'label' => 'Country',
					'type' => 'text'
				],
				[
					'key' => 'postal_code',
					'label' => 'Postal Code',
					'type' => 'number'
				],
				[
					'key' => 'company_type',
					'label' => 'Company Type',
					'type' => 'text'
				],
				[
					'key' => 'company_owner_id',
					'label' => 'Company Owner ID',
					'type' => 'expression'
				],
				[
					'key' => 'company_employee_count',
					'label' => 'Company Employee Count',
					'type' => 'number'
				],
				[
					'key' => 'company_industry',
					'label' => 'Company Industry',
					'type' => 'text'
				],
				[
					'key' => 'company_website',
					'label' => 'Company Website',
					'type' => 'url'
				],
				[
					'key' => 'company_linkedin_url',
					'label' => 'Company LinkedIn URL',
					'type' => 'url'
				],
				[
					'key' => 'company_facebook_url',
					'label' => 'Company Facebook URL',
					'type' => 'url'
				],
				[
					'key' => 'company_twitter_url',
					'label' => 'Company Twitter URL',
					'type' => 'url'
				],
			],
			'add_company_to_contact' => [
				...self::contact_id(),
				...self::select_company(),
			],
			'remove_company_from_contact' => [
				...self::contact_id(),
				...self::select_company(),
			],
			'delete_company' => self::company_id(),
			'get_campaign_single' => self::campaign_id(),
			'create_campaign' => self::contact_title(),
			'delete_campaign' => self::campaign_id(),
			'add_event_tracking' => [
				...self::contact_email(),
				[
					'key' => 'event_key',
					'label' => 'Event Tracking Key',
					'type' => 'text',
					'required' => true
				],
				...self::contact_title(),
				[
					'key' => 'value',
					'label' => 'Event Value',
					'type' => 'text',
					'required' => false
				],
				[
					'key' => 'provider',
					'label' => 'Provider',
					'type' => 'text',
					'required' => false
				],
			],
			'add_note' => [
				...self::contact_id(),
				...self::contact_title(),
				[
					'key' => 'note_type',
					'label' => 'Type',
					'type' => 'text',
					'required' => false
				],
				...self::description(),
			],
		];

		return $schemas[ $action ] ?? [];
	}

	private static function resolve_campaign_payload( $campaign ): array {
		return [
			'id'                 => $campaign->id,
			'parent_id'          => $campaign->parent_id,
			'type'               => $campaign->type,
			'title'              => $campaign->title,
			'available_urls'     => $campaign->available_urls,
			'slug'               => $campaign->slug,
			'status'             => $campaign->status,
			'template_id'        => $campaign->template_id,
			'email_subject'      => $campaign->email_subject,
			'email_pre_header'   => $campaign->email_pre_header,
			'email_body'         => $campaign->email_body,
			'recipients_count'   => $campaign->recipients_count,
			'delay'              => $campaign->delay,
			'utm_status'         => $campaign->utm_status,
			'utm_source'         => $campaign->utm_source,
			'utm_medium'         => $campaign->utm_medium,
			'utm_campaign'       => $campaign->utm_campaign,
			'utm_term'           => $campaign->utm_term,
			'utm_content'        => $campaign->utm_content,
			'design_template'    => $campaign->design_template,
			'scheduled_at'       => $campaign->scheduled_at,
			'sending_type'       => $campaign->sending_type,
			'is_transactional'   => $campaign->is_transactional,
			'created_by'         => $campaign->created_by,
			'created_at'         => $campaign->created_at,
			'updated_at'         => $campaign->updated_at,
			'settings'           => $campaign->settings ?? [],
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

	private static function resolve_pivot_payload( $company ): array {
		$pivot = $company->pivot ?? null;
		if ( ! $pivot ) {
			return [];
		}
		return [
			'subscriber_id' => $pivot->subscriber_id,
			'object_id'     => $pivot->object_id,
			'object_type'   => $pivot->object_type,
			'created_at'    => $pivot->created_at,
			'updated_at'    => $pivot->updated_at,
		];
	}

	private static function normalize_ids( $ids ): array {
		if ( empty( $ids ) ) {
			return [];
		}
		if ( is_string( $ids ) ) {
			$ids = strpos( $ids, ',' ) !== false ? explode( ',', $ids ) : [ $ids ];
		}
		return array_map( 'intval', (array) $ids );
	}

	public static function execute_node( array $node, array $input ): array {

		$config = $node['data']['config'] ?? [];

		switch ( $node['data']['event'] ?? '' ) {

			case 'created_contact':
				$email  = sanitize_email( $config['email'] ?? '' );
				$status = sanitize_text_field( $config['status'] ?? '' );
				if ( ! is_email( $email ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Valid Email is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Not Installed'
						]
					];
				}
				$data = [
					'email'           => $email,
					'status'          => $status,
					'prefix'          => sanitize_text_field( $config['prefix'] ?? '' ),
					'first_name'      => sanitize_text_field( $config['first_name'] ?? '' ),
					'last_name'       => sanitize_text_field( $config['last_name'] ?? '' ),
					'phone'           => sanitize_text_field( $config['phone'] ?? '' ),
					'date_of_birth'   => sanitize_text_field( $config['date_of_birth'] ?? '' ),
					'address_line_1'  => sanitize_text_field( $config['address_line_1'] ?? '' ),
					'address_line_2'  => sanitize_text_field( $config['address_line_2'] ?? '' ),
					'city'            => sanitize_text_field( $config['city'] ?? '' ),
					'state'           => sanitize_text_field( $config['state'] ?? '' ),
					'country'         => sanitize_text_field( $config['country'] ?? '' ),
					'postal_code'     => sanitize_text_field( $config['postal_code'] ?? '' ),
				];
				$subscriberModel = new \FluentCrm\App\Models\Subscriber();
				$contact = $subscriberModel->where( 'email', $email )->first();
				if ( $contact ) {
					$contact->fill( array_filter( $data ) );
					$contact->save();
				} else {
					$contact = $subscriberModel->create( array_filter( $data ) );
				}
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact Create/Update Failed'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact->id );
				$custom_fields = [
					'date_of_birth',
					'address_line_1',
					'address_line_2',
					'city',
					'state',
					'country',
					'postal_code'
				];
				foreach ( $custom_fields as $field ) {
					if ( ! empty( $config[ $field ] ) ) {
						$contact->updateMeta( $field, sanitize_text_field( $config[ $field ] ), false );
					}
				}
				if ( ! empty( $config['lists'] ) && $config['lists'] !== 'any' ) {
					$contact->attachLists( (array) $config['lists'] );
				}
				if ( ! empty( $config['tags'] ) && $config['tags'] !== 'any' ) {
					$contact->attachTags( (array) $config['tags'] );
				}
				if ( ! empty( $config['company_id'] ) ) {
					$company_id = (int) $config['company_id'];
					$contact->attachCompanies( [ $company_id ] );
					$contact->company_id = $company_id;
					$contact->save();
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'contact' => self::resolve_contact_payload( $contact )
					]
				];

			case 'get_contact_all':
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$subscriber = \FluentCrm\App\Models\Subscriber::all();
				$contacts   = [];
				foreach ( $subscriber as $sub ) {
					$contacts[] = self::resolve_contact_payload( $sub );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'contacts' => $contacts
					]
				];

			case 'get_contact_id':
				$contact_id = $config['contact_id'] ?? 0;
				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact is not found'
						]
					];
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'contact' => self::resolve_contact_payload( $contact )
					]
				];

			case 'get_contact_email':
				$email = $config['email'] ?? 0;
				if ( ! $email ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Email is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::where( 'email', $email )->first();
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact is not found'
						]
					];
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'contact' => self::resolve_contact_payload( $contact )
					]
				];

			case 'get_contact_by_tags':
				$tag_ids = self::normalize_ids( $config['tag_id'] ?? [] );
				if ( empty( $tag_ids ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Tag ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$subscribers = \FluentCrm\App\Models\Subscriber::whereHas('tags', function ( $q ) use ( $tag_ids ) {
					$q->whereIn( 'fc_tags.id', $tag_ids );
				})->get();
				if ( $subscribers->isEmpty() ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'No contacts found for given tags'
						]
					];
				}
				$contacts = [];
				foreach ( $subscribers as $sub ) {
					$contacts[] = self::resolve_contact_payload( $sub );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'count' => count( $contacts ),
						'contacts' => $contacts
					]
				];

			case 'get_contact_by_lists':
				$list_ids = self::normalize_ids( $config['list_id'] ?? [] );
				if ( empty( $list_ids ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'List ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$subscribers = \FluentCrm\App\Models\Subscriber::whereHas('lists', function ( $q ) use ( $list_ids ) {
					$q->whereIn( 'fc_lists.id', $list_ids );
				})->get();
				if ( $subscribers->isEmpty() ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'No contacts found for given lists'
						]
					];
				}
				$contacts = [];
				foreach ( $subscribers as $sub ) {
					$contacts[] = self::resolve_contact_payload( $sub );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'count' => count( $contacts ),
						'contacts' => $contacts
					]
				];

			case 'get_contact_by_status':
				$status = sanitize_text_field( $config['status'] ?? '' );
				if ( empty( $status ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Status is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				if ( $status === 'any' ) {
					$subscribers = \FluentCrm\App\Models\Subscriber::all();
				} else {
					$subscribers = \FluentCrm\App\Models\Subscriber::where( 'status', $status )->get();
				}
				if ( $subscribers->isEmpty() ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'No contacts found for given status'
						]
					];
				}
				$contacts = [];
				foreach ( $subscribers as $sub ) {
					$contacts[] = self::resolve_contact_payload( $sub );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'status' => $status,
						'count' => count( $contacts ),
						'contacts' => $contacts
					]
				];

			case 'delete_contact':
				$contact_id = $config['contact_id'] ?? 0;
				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Class Not Found'
						]
					];
				}
				$subscriber = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $subscriber ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact not found'
						]
					];
				}
				$subscriber->delete();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'message' => 'Contact deleted successfully'
					]
				];

			case 'get_tag_all':
				if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Tag Not Found'
						]
					];
				}
				$tags = \FluentCrm\App\Models\Tag::all();
				if ( $tags->isEmpty() ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => true,
							'count' => 0,
							'tags' => []
						]
					];
				}
				$tag_list = [];
				foreach ( $tags as $tag ) {
					$tag_list[] = self::resolve_tag_payload( $tag );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'count' => count( $tag_list ),
						'tags' => $tag_list
					]
				];

			case 'created_tag':
				$title       = $config['title'] ?? '';
				$slug        = $config['slug'] ?? '';
				$description = $config['description'] ?? '';
				if ( ! $title ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Tag Title is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Tag Not Found'
						]
					];
				}
				$tag = \FluentCrm\App\Models\Tag::where( 'title', $title )->first();
				if ( ! $tag ) {
					$tag = new \FluentCrm\App\Models\Tag();
				}
				$tag->title       = $title;
				$tag->description = $description;
				$tag->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
				$tag->save();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'tag' => self::resolve_tag_payload( $tag )
					]
				];

			case 'add_tag_to_contact':
				$contact_id = $config['contact_id'] ?? 0;
				$tags       = $config['tags'] ?? '';
				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( empty( $tags ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Tag is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'contact not found'
						]
					];
				}
				$contact->attachTags( (array) $tags );
				$contact = \FluentCrm\App\Models\Subscriber::with( 'tags' )->find( $contact_id );
				$tag_payload = [];
				foreach ( $contact->tags as $tag ) {
					$tag_payload[] = array_merge( self::resolve_tag_payload( $tag ), [ 'pivot' => self::resolve_pivot_payload( $tag ) ], );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'status' => $contact->status,
						'contact' => self::resolve_contact_payload( $contact ),
						'tags' => $tag_payload
					]
				];

			case 'remove_tag_from_contact':
				$contact_id = $config['contact_id'] ?? 0;
				$tags       = array_map( 'intval', (array) ( $config['tags'] ?? '' ) );

				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( empty( $tags ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Tag is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'contact not found'
						]
					];
				}
				$contact->detachTags( $tags );
				$contact = \FluentCrm\App\Models\Subscriber::with( 'tags' )->find( $contact_id );
				$tag_payload = [];
				foreach ( $contact->tags as $tag ) {
					$tag_payload[] = array_merge( self::resolve_tag_payload( $tag ), [ 'pivot' => self::resolve_pivot_payload( $tag ) ], );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'status' => $contact->status,
						'contact' => self::resolve_contact_payload( $contact ),
						'tags' => $tag_payload
					]
				];

			case 'delete_tag':
				$tag_id = $config['tag_id'] ?? 0;
				if ( ! $tag_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Tag ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Class Not Found'
						]
					];
				}
				$tag = \FluentCrm\App\Models\Tag::find( $tag_id );
				if ( ! $tag ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Tag not found'
						]
					];
				}
				$tag->delete();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'message' => 'Tag deleted successfully'
					]
				];

			case 'get_list_all':
				if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Lists Not Found'
						]
					];
				}
				$lists = \FluentCrm\App\Models\Lists::all();
				if ( $lists->isEmpty() ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => true,
							'count' => 0,
							'lists' => []
						]
					];
				}
				$list_payload = [];
				foreach ( $lists as $list ) {
					$list_payload[] = self::resolve_list_payload( $list );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'count' => count( $list_payload ),
						'lists' => $list_payload
					]
				];

			case 'created_list':
				$title       = $config['title'] ?? '';
				$slug        = $config['slug'] ?? '';
				$description = $config['description'] ?? '';
				if ( ! $title ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'List Title is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM List Not Found'
						]
					];
				}
				$list = \FluentCrm\App\Models\Lists::where( 'title', $title )->first();
				if ( ! $list ) {
					$list = new \FluentCrm\App\Models\Lists();
				}
				$list->title       = $title;
				$list->description = $description;
				$list->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
				$list->save();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'list' => self::resolve_list_payload( $list )
					]
				];

			case 'add_list_to_contact':
				$contact_id = $config['contact_id'] ?? 0;
				$lists      = $config['lists'] ?? '';
				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( empty( $lists ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Lists is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'contact not found'
						]
					];
				}
				$contact->attachLists( (array) $lists );
				$contact = \FluentCrm\App\Models\Subscriber::with( 'lists' )->find( $contact_id );
				$list_payload = [];
				foreach ( $contact->lists as $list ) {
					$list_payload[] = array_merge( self::resolve_list_payload( $list ), [ 'pivot' => self::resolve_pivot_payload( $list ) ], );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'status' => $contact->status,
						'contact' => self::resolve_contact_payload( $contact ),
						'lists' => $list_payload
					]
				];

			case 'remove_list_from_contact':
				$contact_id = $config['contact_id'] ?? 0;
				$lists      = array_map( 'intval', (array) ( $config['lists'] ?? '' ) );

				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( empty( $lists ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Lists is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'contact not found'
						]
					];
				}
				$contact->detachLists( $lists );
				$contact = \FluentCrm\App\Models\Subscriber::with( 'lists' )->find( $contact_id );
				$list_payload = [];
				foreach ( $contact->lists as $list ) {
					$list_payload[] = array_merge( self::resolve_list_payload( $list ), [ 'pivot' => self::resolve_pivot_payload( $list ) ], );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'status' => $contact->status,
						'contact' => self::resolve_contact_payload( $contact ),
						'lists' => $list_payload
					]
				];

			case 'delete_list':
				$list_id = $config['list_id'] ?? 0;
				if ( ! $list_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'List ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Class Not Found'
						]
					];
				}
				$list = \FluentCrm\App\Models\Lists::find( $list_id );
				if ( ! $list ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'List not found'
						]
					];
				}
				$list->delete();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'message' => 'List deleted successfully'
					]
				];

			case 'get_company_all':
				if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Company Not Found'
						]
					];
				}
				$companies = \FluentCrm\App\Models\Company::all();
				if ( $companies->isEmpty() ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => true,
							'count' => 0,
							'companies' => []
						]
					];
				}
				$company_payload = [];
				foreach ( $companies as $company ) {
					$company_payload[] = self::resolve_company_payload( $company );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'count' => count( $company_payload ),
						'companies' => $company_payload
					]
				];

			case 'get_company_id':
				$company_id = $config['company_id'] ?? 0;
				if ( ! $company_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Company ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Company Not Found'
						]
					];
				}
				$company = \FluentCrm\App\Models\Company::find( $company_id );
				if ( ! $company ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Company Not Found'
						]
					];
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'company' => self::resolve_company_payload( $company )
					]
				];

			case 'created_company':
				if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Company Model Not Found'
						]
					];
				}
				$data = [
					'name'            => sanitize_text_field( $config['company_name'] ?? '' ),
					'description'     => sanitize_textarea_field( $config['description'] ?? '' ),
					'email'           => sanitize_email( $config['email'] ?? '' ),
					'phone'           => sanitize_text_field( $config['phone'] ?? '' ),
					'address_line_1'  => sanitize_text_field( $config['address_line_1'] ?? '' ),
					'address_line_2'  => sanitize_text_field( $config['address_line_2'] ?? '' ),
					'city'            => sanitize_text_field( $config['city'] ?? '' ),
					'state'           => sanitize_text_field( $config['state'] ?? '' ),
					'country'         => sanitize_text_field( $config['country'] ?? '' ),
					'postal_code'     => sanitize_text_field( $config['postal_code'] ?? '' ),
					'type'            => sanitize_text_field( $config['company_type'] ?? '' ),
					'owner_id'        => intval( $config['company_owner_id'] ?? 0 ),
					'employee_count'  => intval( $config['company_employee_count'] ?? 0 ),
					'industry'        => sanitize_text_field( $config['company_industry'] ?? '' ),
					'website'         => esc_url_raw( $config['company_website'] ?? '' ),
					'linkedin_url'    => esc_url_raw( $config['company_linkedin_url'] ?? '' ),
					'facebook_url'    => esc_url_raw( $config['company_facebook_url'] ?? '' ),
					'twitter_url'     => esc_url_raw( $config['company_twitter_url'] ?? '' ),
				];
				if ( empty( $data['name'] ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Company Name is required'
						]
					];
				}
				$companyModel = new \FluentCrm\App\Models\Company();
				$company = $companyModel->create( array_filter( $data ) );
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'company' => self::resolve_company_payload( $company )
					]
				];

			case 'add_company_to_contact':
				$contact_id = intval( $config['contact_id'] ?? 0 );
				$companies  = $config['company'] ?? [];

				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( ! is_array( $companies ) ) {
					if ( is_string( $companies ) ) {
						$companies = array_map( 'intval', explode( ',', $companies ) );
					} else {
						$companies = [ (int) $companies ];
					}
				}
				$companies = array_filter( $companies );
				if ( empty( $companies ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Companies is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) || ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Model Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact not found'
						]
					];
				}
				$valid_company_ids = [];
				foreach ( $companies as $company_id ) {
					$company = \FluentCrm\App\Models\Company::find( intval( $company_id ) );
					if ( $company ) {
						$valid_company_ids[] = $company->id;
					}
				}
				if ( empty( $valid_company_ids ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Companies not valid'
						]
					];
				}
				$contact->attachCompanies( $valid_company_ids );
				$contact = $contact->fresh( [ 'companies' ] );
				$company_payload = [];
				foreach ( $contact->companies as $company ) {
					if ( in_array( $company->id, $valid_company_ids ) ) {
						$company_payload[]   = self::resolve_company_payload( $company );
					}
				}
				if ( ! $contact->company_id ) {
					$contact->company_id = $valid_company_ids[0];
					$contact->save();
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'status' => $contact->status,
						'contact' => self::resolve_contact_payload( $contact ),
						'companies' => $company_payload
					]
				];

			case 'remove_company_from_contact':
				$contact_id = $config['contact_id'] ?? 0;
				$companies  = array_map( 'intval', (array) ( $config['company'] ?? [] ) );
				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact ID is required'
						]
					];
				}
				if ( empty( $companies ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Company is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::with( 'companies' )->find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact not found'
						]
					];
				}
				$contact->detachCompanies( $companies );
				$contact->load( 'companies' );
				if ( in_array( $contact->company_id, $companies, true ) ) {
					$first = $contact->companies->first();
					$contact->company_id = $first ? $first->id : null;
					$contact->save();
				}
				$company_payload = [];
				foreach ( $contact->companies as $company ) {
					$company_payload[] = array_merge( self::resolve_company_payload( $company ), [ 'pivot' => self::resolve_pivot_payload( $company ) ], );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'status' => $contact->status,
						'contact' => self::resolve_contact_payload( $contact ),
						'companies' => $company_payload
					]
				];

			case 'delete_company':
				$company_id = $config['company_id'] ?? null;
				if ( ! $company_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Company ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Class Not Found'
						]
					];
				}
				$company = \FluentCrm\App\Models\Company::find( $company_id );
				if ( ! $company ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Company not found'
						]
					];
				}
				$company->delete();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'message' => 'Company deleted successfully'
					]
				];

			case 'get_campaign_all':
				if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Campaign Not Found'
						]
					];
				}
				$campaigns = \FluentCrm\App\Models\Campaign::all();
				if ( $campaigns->isEmpty() ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => true,
							'count' => 0,
							'campaigns' => []
						]
					];
				}
				$campaign_payload = [];
				foreach ( $campaigns as $campaign ) {
					$campaign_payload[] = self::resolve_campaign_payload( $campaign );
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'count' => count( $campaign_payload ),
						'campaigns' => $campaign_payload
					]
				];

			case 'get_campaign_single':
				$campaign_id = $config['campaign_id'] ?? 0;
				if ( ! $campaign_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Campaign ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Campaign Not Found'
						]
					];
				}
				$campaign = \FluentCrm\App\Models\Campaign::find( $campaign_id );
				if ( ! $campaign ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Campaign Not Found'
						]
					];
				}
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'campaign' => self::resolve_campaign_payload( $campaign )
					]
				];

			case 'create_campaign':
				$title = $config['title'] ?? '';
				if ( ! $title ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Campaign Title is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Campaign Not Found'
						]
					];
				}
				$created_by = get_current_user_id();
				if ( ! $created_by ) {
					$created_by = 1;
				}
				$campaign             = new \FluentCrm\App\Models\Campaign();
				$campaign->type       = 'campaign';
				$campaign->title      = $title;
				$campaign->slug       = sanitize_title( $title ) . '-' . time();
				$campaign->status     = 'draft';
				$campaign->created_by = $created_by;
				$campaign->email_body = '';
				$campaign->settings   = [
					'subscribers' => [
						[
							'list' => 'all',
							'tag' => 'all'
						]
					],
					'excludedSubscribers' => [],
				];
				$campaign->save();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'campaign' => self::resolve_campaign_payload( $campaign )
					]
				];

			case 'delete_campaign':
				 $campaign_id = intval( $config['campaign_id'] ?? 0 );
				if ( ! $campaign_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Campaign ID is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Class Not Found'
						]
					];
				}
				$campaign = \FluentCrm\App\Models\Campaign::find( $campaign_id );
				if ( ! $campaign ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Campaign not found'
						]
					];
				}
				$campaign->delete();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'message' => 'Campaign deleted successfully'
					]
				];

			case 'add_event_tracking':
				$email       = sanitize_email( $config['email'] ?? '' );
				$event_key   = sanitize_text_field( $config['event_key'] ?? '' );
				$event_title = sanitize_text_field( $config['title'] ?? '' );
				$event_value = $config['value'] ?? null;
				$provider    = sanitize_text_field( $config['provider'] ?? 'custom' );
				if ( ! $email ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact Email is required'
						]
					];
				}
				if ( ! $event_key ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Event Key is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::where( 'email', $email )->first();
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact not found with this email'
						]
					];
				}
				$event = FluentCrmApi( 'event_tracker' )->track([
					'email'     => $email,
					'event_key' => $event_key,
					'title'     => $event_title,
					'value'     => $event_value,
					'provider'  => $provider ?: 'custom',
				], true );
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'contact' => self::resolve_contact_payload( $contact ),
						'event' => $event
					]
				];

			case 'add_note':
				$contact_id  = $config['contact_id'] ?? 0;
				$note_title  = sanitize_text_field( $config['title'] ?? '' );
				$note_type   = sanitize_text_field( $config['note_type'] ?? '' );
				$description = sanitize_text_field( $config['description'] ?? '' );
				if ( ! $contact_id ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact Id is required'
						]
					];
				}
				if ( ! $note_title ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Note Title is required'
						]
					];
				}
				if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'FluentCRM Subscriber Not Found'
						]
					];
				}
				$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
				if ( ! $contact ) {
					return [
						'port' => 'main',
						'data' => [
							'success' => false,
							'message' => 'Contact not found'
						]
					];
				}
				$note = new \FluentCrm\App\Models\SubscriberNote();
				$note->subscriber_id = $contact_id;
				$note->title         = $note_title;
				$note->description   = $description;
				$note->type          = $note_type;
				$note->created_by    = $contact->user_id ?? 0;
				$note->save();
				return [
					'port' => 'main',
					'data' => [
						'success' => true,
						'contact' => self::resolve_contact_payload( $contact ),
						'note' => [
							'id'         => $note->id,
							'title'      => $note->title,
							'type'       => $note->type,
							'description' => $note->description,
							'created_at' => $note->created_at,
							'updated_at' => $note->updated_at,
						],
					],
				];
		}//end switch
		return [
			'port' => 'main',
			'data' => $input
		];
	}
}
