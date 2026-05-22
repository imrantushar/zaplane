<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Fluentcrm\ActionsResponseTrait;
use Zaplane\Integrations\Fluentcrm\ContactActionsTrait;
use Zaplane\Integrations\Fluentcrm\TagActionsTrait;
use Zaplane\Integrations\Fluentcrm\ListActionsTrait;
use Zaplane\Integrations\Fluentcrm\CompanyActionsTrait;
use Zaplane\Integrations\Fluentcrm\CampaignActionsTrait;
use Zaplane\Integrations\Fluentcrm\QueryTrait;


class FluentCrm extends IntegrationBase {

	use ActionsResponseTrait;
	use ContactActionsTrait;
	use TagActionsTrait;
	use ListActionsTrait;
	use CompanyActionsTrait;
	use CampaignActionsTrait;
	use QueryTrait;

	public static function get_slug(): string {
		return 'fluentcrm';
	}

	public static function get_name(): string {
		return 'FluentCRM';
	}

	public static function get_icon(): string {
		return 'fluentcrm-logo-icon.svg';
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
			return [
				[
					'key' => 'tag_id',
					'label' => 'Tags',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'fluentcrm',
						'query'       => 'tag_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true
				],
			];
		}//end if

		if ( in_array( $trigger, [ 'added_list', 'removed_list' ], true ) ) {
			return [
				[
					'key' => 'list_id',
					'label' => 'List',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'fluentcrm',
						'query'       => 'list_query',
						'select'      => [ 'value', 'label' ],
					],
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
				$tag_ids = $args[0] ?? [];
				$contact = $args[1] ?? null;

				if ( ! $contact || empty( $tag_ids ) ) {
					return false;
				}

				$selected_tag_id = $node['config']['tag_id'] ?? null;

				if ( ! empty( $selected_tag_id ) && $selected_tag_id !== 'any' ) {
					$selected_ids = self::normalize_ids( $selected_tag_id );
					$added_ids    = self::normalize_ids( $tag_ids );

					if ( empty( array_intersect( $selected_ids, $added_ids ) ) ) {
						return false;
					}
				}

				return [
					'success'  => true,
					'contact'  => self::resolve_contact_payload( $contact ),
					'tag_ids'  => $tag_ids,
				];

			case 'added_list':
			case 'removed_list':
				$list_ids = $args[0] ?? [];
				$contact  = $args[1] ?? null;

				if ( ! $contact || empty( $list_ids ) ) {
					return false;
				}

				$selected_list_id = $node['config']['list_id'] ?? null;

				if ( ! empty( $selected_list_id ) && $selected_list_id !== 'any' ) {
					$selected_ids = self::normalize_ids( $selected_list_id );
					$added_ids    = self::normalize_ids( $list_ids );

					if ( empty( array_intersect( $selected_ids, $added_ids ) ) ) {
						return false;
					}
				}

				return [
					'success'  => true,
					'contact'  => self::resolve_contact_payload( $contact ),
					'list_ids' => $list_ids,
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
				$new_status = $args[1] ?? [];
				if ( ! $company ) {
					return false;
				}
				return [
					'success' => true,
					'old_status' => self::resolve_company_payload( $company ),
					'new_status' => $new_status,
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

	public static function select_tag(): array {
		return [
			[
				'key' => 'tags',
				'label' => 'Tags',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'fluentcrm',
					'query'       => 'tag_query',
					'select'      => [ 'value', 'label' ],
				],
				'required' => true,
				'multiple' => true
			]
		];
	}

	public static function select_list(): array {
		return [
			[
				'key' => 'lists',
				'label' => 'Lists',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'fluentcrm',
					'query'       => 'list_query',
					'select'      => [ 'value', 'label' ],
				],
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
				'type' => 'email',
				'required' => true
			]
		];
	}

	public static function select_company(): array {
		return [
			[
				'key' => 'company',
				'label' => 'company',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'fluentcrm',
					'query'       => 'company_query',
					'select'      => [ 'value', 'label' ],
				],
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
				'key' => 'fluent_status',
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
					'type' => 'text',
					'required' => true,
				],
				[
					'key' => 'last_name',
					'label' => 'Last Name',
					'type' => 'text',
					'required' => true,
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
		$event = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$method = 'action_' . $event;

		if ( method_exists( static::class, $method ) ) {
			return static::$method( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'tag_query'     => [ self::class, 'tag_query_types' ],
			'list_query'    => [ self::class, 'list_query_types' ],
			'company_query' => [ self::class, 'company_query_types' ],
		];
	}
}
