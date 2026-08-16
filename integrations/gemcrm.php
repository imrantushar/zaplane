<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Gemcrm\QueryTrait;
use Zaplane\Integrations\Gemcrm\BirthdayCronTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gemcrm extends IntegrationBase {

	use QueryTrait;
	use BirthdayCronTrait;

	public static function get_slug(): string {
		return 'gemcrm';
	}

	public static function get_name(): string {
		return 'GemCRM';
	}

	public static function get_icon(): string {
		return 'crm.svg';
	}

	public static function get_triggers(): array {
		return [
			'contact_created' => [
				'label' => 'Contact Created',
				'hook'  => 'gemcrm/contact/created',
			],
			'contact_tag_attached' => [
				'label' => 'Tag Added To Contact',
				'hook'  => 'gemcrm/contact/tag/attached',
			],
			'contact_tag_removed' => [
				'label' => 'Tag Removed From Contact',
				'hook'  => 'gemcrm/contact/tag/removed',
			],
			'contact_list_attached' => [
				'label' => 'Contact Added To List',
				'hook'  => 'gemcrm/contact/list/attached',
			],
			'contact_list_removed' => [
				'label' => 'Contact Removed From List',
				'hook'  => 'gemcrm/contact/list/removed',
			],
			'contact_birthday' => [
				'label' => 'Contact Birthday',
				'hook'  => 'zaplane_gemcrm_contact_birthday',
			],
			'sequence_enrolled' => [
				'label' => 'Contact Enrolled In Sequence',
				'hook'  => 'gemcrm/email_seq/subscriber/enrolled',
			],
			'sequence_completed' => [
				'label' => 'Contact Completed Sequence',
				'hook'  => 'gemcrm/email_seq/subscriber/completed',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		switch ( $trigger ) {
			case 'contact_tag_attached':
			case 'contact_tag_removed':
				return [
					[
						'key'         => 'tag_id',
						'label'       => 'Tag',
						'type'        => 'select',
						'required'    => true,
						'placeholder' => 'Leave empty to trigger for any tag',
						'dynamic'     => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_tag_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'contact_list_attached':
			case 'contact_list_removed':
				return [
					[
						'key'         => 'list_id',
						'label'       => 'List',
						'type'        => 'select',
						'required'    => true,
						'placeholder' => 'Leave empty to trigger for any list',
						'dynamic'     => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_list_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'contact_birthday':
				return [
					[
						'key'         => 'purchase_tag_id',
						'label'       => 'Apply Tag on Purchase (optional)',
						'type'        => 'select',
						'required'    => false,
						'placeholder' => 'Leave empty to skip tag on purchase',
						'dynamic'     => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_tag_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'sequence_enrolled':
			case 'sequence_completed':
				return [
					[
						'key'         => 'sequence_id',
						'label'       => 'Email Sequence',
						'type'        => 'select',
						'required'    => false,
						'placeholder' => 'Leave empty to trigger for any sequence',
						'dynamic'     => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_sequence_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];
		}//end switch

		return [];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$contact_base = [
			'contact_id'      => 1,
			'id'              => 1,
			'user_id'         => 1,
			'first_name'      => 'John',
			'last_name'       => 'Doe',
			'email'           => 'john.doe@example.com',
			'phone'           => '+1234567890',
			'status'          => 'subscribed',
			'type'            => 'lead',
			'clicks'          => 0,
			'total_mail_sent' => 0,
			'email_open_rate' => 0,
			'meta'            => [],
			'lists'           => [],
			'tags'            => [],
			'companies'       => [],
			'creator'         => [],
			'created_at'      => '2024-01-01 00:00:00',
			'updated_at'      => '2024-01-01 00:00:00',
		];

		$samples = [
			'contact_created'       => $contact_base,
			'contact_tag_attached'  => [
				'contact_id' => 1,
				'tag_ids' => [ 1 ]
			],
			'contact_tag_removed'   => [
				'contact_id' => 1,
				'tag_ids' => [ 1 ]
			],
			'contact_list_attached' => [
				'contact_id' => 1,
				'list_ids' => [ 1 ]
			],
			'contact_list_removed'  => [
				'contact_id' => 1,
				'list_ids' => [ 1 ]
			],
			'contact_birthday'      => array_merge( $contact_base, [
				'dob'               => '1990-03-15',
				'_zaplane_birthday' => true,
			] ),
			'sequence_enrolled'     => [
				'contact_id'  => 1,
				'sequence_id' => 1,
			],
			'sequence_completed'    => [
				'contact_id'  => 1,
				'sequence_id' => 1,
			],
		];

		return $samples[ $trigger ] ?? [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event = $node['event'] ?? '';

		switch ( $event ) {

			case 'contact_created':
				$contact_id = $args[0] ?? null;
				$data       = $args[1] ?? [];

				if ( ! $contact_id || empty( $data ) ) {
					return false;
				}

				return [
					'contact_id'      => (int) $contact_id,
					'id'              => $data['id'] ?? $contact_id,
					'user_id'         => $data['user_id'] ?? null,
					'first_name'      => $data['first_name'] ?? '',
					'last_name'       => $data['last_name'] ?? '',
					'email'           => $data['email'] ?? '',
					'phone'           => $data['phone'] ?? '',
					'status'          => $data['status'] ?? '',
					'type'            => $data['type'] ?? '',
					'clicks'          => $data['clicks'] ?? 0,
					'total_mail_sent' => $data['total_mail_sent'] ?? 0,
					'email_open_rate' => $data['email_open_rate'] ?? 0,
					'meta'            => $data['meta'] ?? [],
					'lists'           => $data['lists'] ?? [],
					'tags'            => $data['tags'] ?? [],
					'companies'       => $data['companies'] ?? [],
					'creator'         => $data['creator'] ?? [],
					'created_at'      => $data['created_at'] ?? '',
					'updated_at'      => $data['updated_at'] ?? '',
				];

			case 'contact_tag_attached':
			case 'contact_tag_removed':
				$contact_id = $args[0] ?? null;
				$tag_ids    = $args[1] ?? [];

				if ( ! $contact_id || empty( $tag_ids ) ) {
					return false;
				}

				$config        = $node['data']['config'] ?? $node['config'] ?? [];
				$filter_tag_id = ! empty( $config['tag_id'] )
					? (int) $config['tag_id']
					: null;

				if ( $filter_tag_id && ! in_array( $filter_tag_id, array_map( 'intval', (array) $tag_ids ), true ) ) {
					return false;
				}

				return [
					'contact_id' => (int) $contact_id,
					'tag_ids'    => array_map( 'intval', (array) $tag_ids ),
				];

			case 'contact_list_attached':
			case 'contact_list_removed':
				$contact_id = $args[0] ?? null;
				$list_ids   = $args[1] ?? [];

				if ( ! $contact_id || empty( $list_ids ) ) {
					return false;
				}

				$config         = $node['data']['config'] ?? $node['config'] ?? [];
				$filter_list_id = ! empty( $config['list_id'] )
					? (int) $config['list_id']
					: null;

				if ( $filter_list_id && ! in_array( $filter_list_id, array_map( 'intval', (array) $list_ids ), true ) ) {
					return false;
				}

				return [
					'contact_id' => (int) $contact_id,
					'list_ids'   => array_map( 'intval', (array) $list_ids ),
				];

			case 'contact_birthday':
				$data = $args[0] ?? [];

				if ( empty( $data ) || empty( $data['id'] ) ) {
					return false;
				}

				return [
					'contact_id'        => (int) ( $data['id'] ?? 0 ),
					'first_name'        => $data['first_name'] ?? '',
					'last_name'         => $data['last_name'] ?? '',
					'email'             => $data['email'] ?? '',
					'phone'             => $data['phone'] ?? '',
					'dob'               => $data['meta']['dob'] ?? '',
					'meta'              => $data['meta'] ?? [],
					'lists'             => $data['lists'] ?? [],
					'tags'              => $data['tags'] ?? [],
					'_zaplane_birthday' => true,
				];

			case 'sequence_enrolled':
			case 'sequence_completed':
				// Fired as do_action( hook, $sequence_id, $contact_id, ...) —
				// sequence_id first, unlike the contact-centric hooks above.
				$sequence_id = $args[0] ?? null;
				$contact_id  = $args[1] ?? null;

				if ( ! $sequence_id || ! $contact_id ) {
					return false;
				}

				$config              = $node['data']['config'] ?? $node['config'] ?? [];
				$filter_sequence_id  = ! empty( $config['sequence_id'] )
					? (int) $config['sequence_id']
					: null;

				if ( $filter_sequence_id && $filter_sequence_id !== (int) $sequence_id ) {
					return false;
				}

				return [
					'contact_id'  => (int) $contact_id,
					'sequence_id' => (int) $sequence_id,
				];
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_contact'   => [ 'label' => 'Create Contact' ],
			'update_contact'   => [ 'label' => 'Update Contact' ],
			'delete_contact'   => [ 'label' => 'Delete Contact' ],
			'apply_tag'        => [ 'label' => 'Apply Tag To Contact' ],
			'apply_list'       => [ 'label' => 'Add Contact To List' ],
			'remove_from_tag'  => [ 'label' => 'Remove Tag From Contact' ],
			'remove_from_list' => [ 'label' => 'Remove Contact From List' ],
			'send_campaign'       => [ 'label' => 'Send Email Campaign' ],
			'send_email'          => [ 'label' => 'Send Email' ],
			'reapply_sequence'    => [ 'label' => 'Re Apply A Sequence' ],
			'enroll_in_sequence'    => [ 'label' => 'Enroll Contact In Sequence' ],
			'remove_from_sequence'  => [ 'label' => 'Remove Contact From Sequence' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'create_contact':
				return self::contact_fields();

			case 'update_contact':
				return array_merge(
					[ self::contact_id_field( true ) ],
					self::contact_fields()
				);

			case 'delete_contact':
				return [ self::contact_id_field( true ) ];

			case 'apply_tag':
			case 'remove_from_tag':
				return [
					self::contact_id_field( true ),
					[
						'key'      => 'tag_id',
						'label'    => 'Tag',
						'type'     => 'multi-select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_tag_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'apply_list':
			case 'remove_from_list':
				return [
					self::contact_id_field( true ),
					[
						'key'      => 'list_id',
						'label'    => 'List',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_list_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'send_campaign':
				return [
					[
						'key'      => 'campaign_id',
						'label'    => 'Email Campaign',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_campaign_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'send_email':
				return self::send_email_fields();

			case 'reapply_sequence':
				return [
					[
						'key'      => 'sequence_id',
						'label'    => 'Email Sequence',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_sequence_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];

			case 'enroll_in_sequence':
				return [
					self::contact_id_field( true ),
					[
						'key'      => 'sequence_id',
						'label'    => 'Email Sequence',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_sequence_query',
							'select'      => [ 'value', 'label' ],
						],
					],
					[
						'key'      => 'restart_if_exist',
						'label'    => 'If already enrolled',
						'type'     => 'select',
						'required' => false,
						'default'  => 'no',
						'options'  => [
							[ 'value' => 'no', 'label' => 'Leave their current progress' ],
							[ 'value' => 'yes', 'label' => 'Restart from the first email' ],
						],
					],
				];

			case 'remove_from_sequence':
				return [
					self::contact_id_field( true ),
					[
						'key'      => 'sequence_id',
						'label'    => 'Email Sequence',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'gemcrm',
							'query'       => 'gemcrm_sequence_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];
		}//end switch

		return [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'gemcrm_contact_query'  => [ self::class, 'query_contacts' ],
			'gemcrm_tag_query'      => [ self::class, 'query_tags' ],
			'gemcrm_list_query'     => [ self::class, 'query_lists' ],
			'gemcrm_campaign_query' => [ self::class, 'query_campaigns' ],
			'gemcrm_sequence_query' => [ self::class, 'query_sequences' ],
			'gemcrm_email_template_query' => [ self::class, 'query_email_templates' ],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$method = 'action_' . $event;

		if ( method_exists( static::class, $method ) ) {
			return static::$method( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	private static function send_email_fields(): array {
		return [
			[
				'key'      => 'recipient_type',
				'label'    => 'Send To',
				'type'     => 'select',
				'required' => true,
				'default'  => 'contact',
				'options'  => [
					[
						'value' => 'contact',
						'label' => 'Specific Contact'
					],
					[
						'value' => 'list',
						'label' => 'Contact List'
					],
					[
						'value' => 'custom',
						'label' => 'Custom Email'
					],
				],
			],
			[
				'key'        => 'contact_id',
				'label'      => 'Contact',
				'type'       => 'select',
				'required'   => false,
				'depends_on' => [ 'recipient_type' => 'contact' ],
				'dynamic'    => [
					'integration' => 'gemcrm',
					'query'       => 'gemcrm_contact_query',
					'select'      => [ 'value', 'label' ],
				],
			],
			[
				'key'        => 'list_id',
				'label'      => 'Contact List',
				'type'       => 'select',
				'required'   => false,
				'depends_on' => [ 'recipient_type' => 'list' ],
				'dynamic'    => [
					'integration' => 'gemcrm',
					'query'       => 'gemcrm_list_query',
					'select'      => [ 'value', 'label' ],
				],
			],
			[
				'key'        => 'custom_email',
				'label'      => 'Email Address',
				'type'     => 'email',
				'subtype'  => 'expression',
				'required'   => false,
				'depends_on' => [ 'recipient_type' => 'custom' ],
				'placeholder' => 'someone@example.com or use @ to pick a variable',
			],
			[
				'key'      => 'subject',
				'label'    => 'Subject',
				'type'     => 'expression',
				'required' => true,
				'placeholder' => 'Your subject line… (leave empty to use the template subject)',
			],
			[
				'key'      => 'content_source',
				'label'    => 'Email Content',
				'type'     => 'select',
				'required' => true,
				'default'  => 'custom',
				'options'  => [
					[
						'value' => 'custom',
						'label' => 'Custom Email'
					],
					[
						'value' => 'template',
						'label' => 'Existing Template'
					],
				],
			],
			[
				'key'         => 'template_id',
				'label'       => 'Email Template',
				'type'        => 'select',
				'required'    => false,
				'depends_on'  => [ 'content_source' => 'template' ],
				'help'        => 'Pick a saved template (designed on the Zaplane → Email Templates page). Its design is used as the email body.',
				'dynamic'     => [
					'integration' => 'gemcrm',
					'query'       => 'gemcrm_email_template_query',
					'select'      => [ 'value', 'label' ],
				],
			],
			[
				'key'         => 'body',
				'label'       => 'Email Body',
				'type'        => 'richtext',
				'required'    => false,
				'depends_on'  => [ 'content_source' => 'custom' ],
				'placeholder' => 'Write your email here…',
				'merge_tags'  => self::email_merge_tags(),
			],
			[
				'key'      => 'pre_header',
				'label'    => 'Pre-header Text',
				'type'     => 'expression',
				'required' => false,
				'placeholder' => 'Short preview text shown in inbox…',
			],
			[
				'key'      => 'from_email',
				'label'    => 'From Email',
				'type'     => 'email',
				'subtype'  => 'expression',
				'required' => false,
				'placeholder' => 'Leave empty to use system default',
			],
			[
				'key'      => 'from_name',
				'label'    => 'From Name',
				'type'     => 'expression',
				'required' => false,
				'placeholder' => 'Leave empty to use system default',
			],
			[
				'key'      => 'reply_to_email',
				'label'    => 'Reply-To Email',
				'type'     => 'email',
				'subtype'  => 'expression',
				'required' => false,
			],
			[
				'key'      => 'reply_to_name',
				'label'    => 'Reply-To Name',
				'type'     => 'expression',
				'required' => false,
			],
		];
	}

	/**
	 * Contact merge tags offered inside the email designer. Mirrors GemCRM's
	 * own contactShortcodes; these {{contact.*}} tags are left untouched by
	 * Zaplane's variable resolver (reserved prefixes) and resolved per-recipient
	 * by GemCRM's send pipeline.
	 */
	private static function email_merge_tags(): array {
		return [
			[
				'value' => '{{contact.first_name}}',
				'label' => 'First Name'
			],
			[
				'value' => '{{contact.last_name}}',
				'label' => 'Last Name'
			],
			[
				'value' => '{{contact.email}}',
				'label' => 'Email'
			],
			[
				'value' => '{{contact.phone}}',
				'label' => 'Phone'
			],
			[
				'value' => '{{contact.id}}',
				'label' => 'Contact ID'
			],
			[
				'value' => '{{unsubscribe_link}}',
				'label' => 'Unsubscribe Link'
			],
			[
				'value' => '{{update_preferences_link}}',
				'label' => 'Update Preferences Link'
			],
		];
	}

	private static function contact_id_field( bool $required = false ): array {
		return [
			'key'         => 'contact_id',
			'label'       => 'Contact ID',
			'type'        => 'expression',
			'subtype'     => 'number',
			'required'    => $required,
			'placeholder' => 'Enter ID or use {{previous_node.contact_id}}',
			'dynamic'     => [
				'integration' => 'gemcrm',
				'query'       => 'gemcrm_contact_query',
				'select'      => [ 'value', 'label' ],
			],
		];
	}

	private static function contact_fields(): array {
		return [
			[
				'key'      => 'first_name',
				'label'    => 'First Name',
				'type'     => 'expression',
				'required' => true,
			],
			[
				'key'      => 'last_name',
				'label'    => 'Last Name',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'     => 'email',
				'label'   => 'Email',
				'type'    => 'email',
				'subtype' => 'expression',
				'required' => true,
			],
			[
				'key'      => 'phone',
				'label'    => 'Phone',
				'type'     => 'number',
				'required' => false,
			],
			[
				'key'      => 'gemcrm_status',
				'label'    => 'Status',
				'type'     => 'select',
				'required' => false,
				'options'  => self::get_contact_statuses(),
			],
			[
				'key'      => 'type',
				'label'    => 'Type',
				'type'     => 'select',
				'required' => false,
				'options'  => [
					[
						'value' => 'lead',
						'label' => 'Lead'
					],
					[
						'value' => 'customer',
						'label' => 'Customer'
					],
				],
			],
			[
				'key'      => 'linked_id',
				'label'    => 'Linked ID',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'      => 'photo_id',
				'label'    => 'Photo ID',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'      => 'tag_ids',
				'label'    => 'Tags',
				'type'     => 'multi-select',
				'required' => false,
				'dynamic'  => [
					'integration' => 'gemcrm',
					'query'       => 'gemcrm_tag_query',
					'select'      => [ 'value', 'label' ],
				],
			],
			[
				'key'      => 'list_ids',
				'label'    => 'Lists',
				'type'     => 'multi-select',
				'required' => false,
				'dynamic'  => [
					'integration' => 'gemcrm',
					'query'       => 'gemcrm_list_query',
					'select'      => [ 'value', 'label' ],
				],
			],
			[
				'key'      => 'addr_line_1',
				'label'    => 'Address Line 1',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'      => 'addr_line_2',
				'label'    => 'Address Line 2',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'      => 'city',
				'label'    => 'City',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'      => 'state',
				'label'    => 'State',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'      => 'zip',
				'label'    => 'ZIP',
				'type'     => 'expression',
				'required' => false,
			],
			[
				'key'      => 'country',
				'label'    => 'Country',
				'type'     => 'expression',
				'required' => false,
			],
		];
	}

	private static function get_contact_statuses(): array {
		return [
			[
				'value' => 'draft',
				'label' => 'Draft'
			],
			[
				'value' => 'pending',
				'label' => 'Pending'
			],
			[
				'value' => 'subscribed',
				'label' => 'Subscribed'
			],
			[
				'value' => 'unsubscribed',
				'label' => 'Unsubscribed'
			],
			[
				'value' => 'spamed',
				'label' => 'Spamed'
			],
			[
				'value' => 'bounced',
				'label' => 'Bounced'
			],
			[
				'value' => 'complained',
				'label' => 'Complained'
			],
			[
				'value' => 'transactional',
				'label' => 'Transactional'
			],
		];
	}

	private static function action_error( string $message, array $input ): array {
		return [
			'port' => 'main',
			'data' => array_merge( $input, [ 'error' => $message ] ),
		];
	}

	private static function action_success( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function build_contact_data( array $config ): array {
		$data = [];

		foreach ( [ 'type', 'first_name', 'last_name', 'phone', 'email' ] as $field ) {
			if ( isset( $config[ $field ] ) && '' !== $config[ $field ] ) {
				$data[ $field ] = sanitize_text_field( $config[ $field ] );
			}
		}

		// 'gemcrm_status' is the schema key (avoids frontend key collision); maps to 'status' for GemCRM.
		if ( isset( $config['gemcrm_status'] ) && '' !== $config['gemcrm_status'] ) {
			$data['status'] = sanitize_text_field( $config['gemcrm_status'] );
		}

		foreach ( [ 'linked_id', 'photo_id' ] as $int_field ) {
			if ( ! empty( $config[ $int_field ] ) ) {
				$data[ $int_field ] = (int) $config[ $int_field ];
			}
		}

		foreach ( [ 'tag_ids', 'list_ids' ] as $id_field ) {
			if ( ! empty( $config[ $id_field ] ) ) {
				$raw = $config[ $id_field ];
				// multi-select sends an array; fallback handles a single value.
				$data[ $id_field ] = array_values(
					array_filter( array_map( 'intval', (array) $raw ) )
				);
			}
		}

		$meta = [];
		foreach ( [ 'addr_line_1', 'addr_line_2', 'city', 'state', 'zip', 'country' ] as $key ) {
			if ( isset( $config[ $key ] ) && '' !== $config[ $key ] ) {
				$meta[ $key ] = sanitize_text_field( $config[ $key ] );
			}
		}
		if ( ! empty( $meta ) ) {
			$meta['add_addr_info'] = true;
			$data['meta']         = $meta;
		}

		return $data;
	}

	protected static function action_create_contact( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$email = sanitize_email( $config['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return self::action_error( 'A valid email is required to create a contact', $input );
		}

		$data    = self::build_contact_data( $config );
		$contact = \GemCrm\Database\Models\Contact::create( $data );

		if ( ! $contact ) {
			return self::action_error( 'Failed to create contact', $input );
		}

		return self::action_success( array_merge( $input, [ 'contact' => $contact ] ) );
	}

	protected static function action_update_contact( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$contact_id = (int) ( $config['contact_id'] ?? 0 );
		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required to update a contact', $input );
		}

		$data    = self::build_contact_data( $config );
		$contact = \GemCrm\Database\Models\Contact::update( $contact_id, $data );

		if ( ! $contact ) {
			return self::action_error( 'Failed to update contact', $input );
		}

		return self::action_success( array_merge( $input, [ 'contact' => $contact ] ) );
	}

	protected static function action_delete_contact( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$contact_id = (int) ( $config['contact_id'] ?? 0 );
		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required to delete a contact', $input );
		}

		$result = \GemCrm\Database\Models\Contact::delete( $contact_id );

		if ( ! $result ) {
			return self::action_error( 'Failed to delete contact', $input );
		}

		return self::action_success( array_merge( $input, [ 'deleted_contact_id' => $contact_id ] ) );
	}

	protected static function action_apply_tag( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Tag::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$contact_id = (int) ( $config['contact_id'] ?? 0 );
		$raw_tag    = $config['tag_id'] ?? null;
		$tag_ids    = array_filter( array_map( 'intval', (array) ( $raw_tag ?? [] ) ) );

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( empty( $tag_ids ) ) {
			return self::action_error( 'Tag is required', $input );
		}

		foreach ( $tag_ids as $tag_id ) {
			\GemCrm\Database\Models\Tag::attach_single( $contact_id, $tag_id );
		}

		// Preserve the original key shape: scalar in → scalar out, array → array.
		$tag_payload = is_array( $raw_tag ) ? array_values( $tag_ids ) : reset( $tag_ids );

		return self::action_success( array_merge( $input, [
			'contact_id' => $contact_id,
			'tag_id'     => $tag_payload,
		] ) );
	}

	protected static function action_apply_list( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\ListModel::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$contact_id = (int) ( $config['contact_id'] ?? 0 );
		$list_id    = (int) ( $config['list_id'] ?? 0 );

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( ! $list_id ) {
			return self::action_error( 'List is required', $input );
		}

		\GemCrm\Database\Models\ListModel::attach_single( $contact_id, $list_id );

		return self::action_success( array_merge( $input, [
			'contact_id' => $contact_id,
			'list_id'    => $list_id,
		] ) );
	}

	protected static function action_remove_from_tag( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Tag::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$contact_id = (int) ( $config['contact_id'] ?? 0 );
		$raw_tag    = $config['tag_id'] ?? null;
		$tag_ids    = array_filter( array_map( 'intval', (array) ( $raw_tag ?? [] ) ) );

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( empty( $tag_ids ) ) {
			return self::action_error( 'Tag is required', $input );
		}

		foreach ( $tag_ids as $tag_id ) {
			\GemCrm\Database\Models\Tag::detach_single( $contact_id, $tag_id );
		}

		// Preserve the original key shape: scalar in → scalar out, array → array.
		$tag_payload = is_array( $raw_tag ) ? array_values( $tag_ids ) : reset( $tag_ids );

		return self::action_success( array_merge( $input, [
			'contact_id' => $contact_id,
			'tag_id'     => $tag_payload,
		] ) );
	}

	protected static function action_remove_from_list( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\ListModel::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$contact_id = (int) ( $config['contact_id'] ?? 0 );
		$list_id    = (int) ( $config['list_id'] ?? 0 );

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( ! $list_id ) {
			return self::action_error( 'List is required', $input );
		}

		\GemCrm\Database\Models\ListModel::detach_single( $contact_id, $list_id );

		return self::action_success( array_merge( $input, [
			'contact_id' => $contact_id,
			'list_id'    => $list_id,
		] ) );
	}

	/**
	 * Resolve the email subject/body/pre-header from the action config.
	 *
	 * Inline source: `body` is the editor's JSON tree (array) rendered to an
	 * HTML fragment via EmailTreeRenderer; a legacy string body is used as-is.
	 * Template source: load the chosen email_templates post, render its tree,
	 * and use its subject/pre_header meta as defaults the action can override.
	 *
	 * @return array{subject:string,body:string,pre_header:?string}|array{error:string}
	 */
	private static function resolve_email_content( array $config ): array {
		$subject    = trim( (string) ( $config['subject'] ?? '' ) );
		$pre_header = $config['pre_header'] ?? null;
		// Default to 'custom' so configs saved before this field existed (which
		// only have an inline body) keep working.
		$source      = $config['content_source'] ?? ( ! empty( $config['template_id'] ) ? 'template' : 'custom' );
		$template_id = 'template' === $source ? (int) ( $config['template_id'] ?? 0 ) : 0;

		// A selected template takes precedence over the inline body.
		if ( $template_id ) {
			if ( ! class_exists( \Zaplane\Models\EmailTemplate::class ) ) {
				return [ 'error' => 'Email templates are unavailable' ];
			}

			$template = \Zaplane\Models\EmailTemplate::find( $template_id );
			if ( ! $template ) {
				return [ 'error' => 'Selected email template was not found' ];
			}

			$body = self::render_email_tree( $template->getTree() );
			if ( '' === $body ) {
				return [ 'error' => 'Email template has no renderable content' ];
			}

			// The action's own subject/pre-header win; otherwise fall back to
			// the template's stored values.
			if ( '' === $subject ) {
				$subject = (string) ( $template->subject ?? '' );
			}
			if ( null === $pre_header || '' === $pre_header ) {
				$pre_header = $template->pre_header ?: null;
			}

			return [
				'subject' => $subject,
				'body' => $body,
				'pre_header' => $pre_header
			];
		}//end if

		// Inline: an HTML string from the simple editor (a legacy editor tree
		// array is rendered to HTML for backward compatibility).
		$raw  = $config['body'] ?? '';
		$body = is_array( $raw ) ? self::render_email_tree( $raw ) : (string) $raw;

		return [
			'subject' => $subject,
			'body' => $body,
			'pre_header' => $pre_header
		];
	}

	/**
	 * Render an EMB editor tree (array or JSON string) to an HTML fragment using
	 * GemCRM's standalone renderer. Returns '' when the tree is empty/invalid or
	 * the renderer is unavailable.
	 */
	private static function render_email_tree( $tree ): string {
		if ( is_string( $tree ) ) {
			$decoded = json_decode( $tree, true );
			$tree    = is_array( $decoded ) ? $decoded : null;
		}

		if ( ! is_array( $tree ) || empty( $tree['root'] ) ) {
			return '';
		}

		if ( ! class_exists( \GemCrm\Classes\EmailTreeRenderer::class ) ) {
			return '';
		}

		return (string) \GemCrm\Classes\EmailTreeRenderer::render_content( $tree );
	}

	protected static function action_send_email( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Classes\EmailSender::class ) ) {
			return self::action_error( 'GemCRM EmailSender is not available', $input );
		}

		$from_email     = $config['from_email'] ?? null;
		$from_name      = $config['from_name'] ?? null;
		$reply_to_email = $config['reply_to_email'] ?? null;
		$reply_to_name  = $config['reply_to_name'] ?? null;
		$recipient_type = $config['recipient_type'] ?? 'contact';

		// Resolve the body + subject from either the inline designer (an editor
		// JSON tree, rendered to HTML server-side) or a saved GemCRM template.
		$content = self::resolve_email_content( $config );
		if ( isset( $content['error'] ) ) {
			return self::action_error( $content['error'], $input );
		}
		$subject    = $content['subject'];
		$body       = $content['body'];
		$pre_header = $content['pre_header'];

		if ( ! $subject ) {
			return self::action_error( 'Email subject is required', $input );
		}
		if ( ! $body ) {
			return self::action_error( 'Email body is required', $input );
		}

		switch ( $recipient_type ) {

			case 'contact':
				$contact_id = (int) ( $config['contact_id'] ?? 0 );
				if ( ! $contact_id ) {
					return self::action_error( 'Contact is required', $input );
				}

				$to = self::find_contact_email( $contact_id );
				if ( ! $to || ! is_email( $to ) ) {
					return self::action_error( 'Contact not found or has no valid email', $input );
				}

				try {
					( new \GemCrm\Classes\EmailSender( $subject, $body, $pre_header ?: null ) )
						->from( $from_email ?: null, $from_name ?: null )
						->reply_to( $reply_to_email ?: null, $reply_to_name ?: null )
						->to( $to )
						->send();
				} catch ( \Throwable $e ) {
					return self::action_error( 'Failed to send email: ' . $e->getMessage(), $input );
				}

				return self::action_success( array_merge( $input, [
					'recipient_type' => 'contact',
					'sent_to'        => $to,
					'sent_count'     => 1,
				] ) );

			case 'list':
				$list_id = (int) ( $config['list_id'] ?? 0 );
				if ( ! $list_id ) {
					return self::action_error( 'Contact list is required', $input );
				}

				$stats = self::send_to_list_contacts(
					$list_id, $subject, $body,
					$pre_header ?: null,
					$from_email ?: null, $from_name ?: null,
					$reply_to_email ?: null, $reply_to_name ?: null
				);

				return self::action_success( array_merge( $input, [
					'recipient_type' => 'list',
					'list_id'        => $list_id,
					'sent_count'     => $stats['sent'],
					'failed_count'   => count( $stats['failed'] ),
					'failed_emails'  => $stats['failed'],
				] ) );

			case 'custom':
			default:
				$to = sanitize_email( $config['custom_email'] ?? '' );
				if ( ! $to || ! is_email( $to ) ) {
					return self::action_error( 'A valid custom email address is required', $input );
				}

				try {
					( new \GemCrm\Classes\EmailSender( $subject, $body, $pre_header ?: null ) )
						->from( $from_email ?: null, $from_name ?: null )
						->reply_to( $reply_to_email ?: null, $reply_to_name ?: null )
						->to( $to )
						->send();
				} catch ( \Throwable $e ) {
					return self::action_error( 'Failed to send email: ' . $e->getMessage(), $input );
				}

				return self::action_success( array_merge( $input, [
					'recipient_type' => 'custom',
					'sent_to'        => $to,
					'sent_count'     => 1,
				] ) );
		}//end switch
	}

	/**
	 * Fetch a contact's email address by ID.
	 * Tries Contact::find() first (ORM pattern), falls back to index().
	 */
	private static function find_contact_email( int $contact_id ): ?string {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return null;
		}

		if ( method_exists( \GemCrm\Database\Models\Contact::class, 'find' ) ) {
			$contact = \GemCrm\Database\Models\Contact::find( $contact_id );
			if ( $contact ) {
				return is_array( $contact )
					? ( $contact['email'] ?? null )
					: ( $contact->email ?? null );
			}
			return null;
		}

		// Fallback: search by ID via index
		$result = \GemCrm\Database\Models\Contact::index( [
			'id'       => $contact_id,
			'per_page' => 1,
		], null );

		return $result['records'][0]['email'] ?? null;
	}

	/**
	 * Send the email to every contact in a list, paginating 100 at a time
	 * so large lists don't exhaust memory or hit the API in a single burst.
	 *
	 * @return array{ sent: int, failed: string[] }
	 */
	private static function send_to_list_contacts(
		int $list_id,
		string $subject,
		string $body,
		?string $pre_header,
		?string $from_email,
		?string $from_name,
		?string $reply_to_email,
		?string $reply_to_name
	): array {
		$page     = 1;
		$per_page = 100;
		$sent     = 0;
		$failed   = [];

		do {
			$result  = \GemCrm\Database\Models\Contact::index( [
				'list_id'  => $list_id,
				'page'     => $page,
				'per_page' => $per_page,
			], null );

			$records = $result['records'] ?? [];

			foreach ( $records as $contact ) {
				$to = sanitize_email( $contact['email'] ?? '' );
				if ( ! is_email( $to ) ) {
					continue;
				}

				try {
					( new \GemCrm\Classes\EmailSender( $subject, $body, $pre_header ) )
						->from( $from_email, $from_name )
						->reply_to( $reply_to_email, $reply_to_name )
						->to( $to )
						->send();
					$sent++;
				} catch ( \Throwable $e ) {
					$failed[] = $to;
				}
			}

			$page++;
		} while ( count( $records ) === $per_page );

		return [
			'sent' => $sent,
			'failed' => $failed
		];
	}

	protected static function action_reapply_sequence( array $config, array $input ): array {
		if ( ! class_exists( \GemCrmPro\Database\Models\EmailSequence::class ) ) {
			return self::action_error( 'GemCRM Pro is not installed', $input );
		}

		$sequence_id = (int) ( $config['sequence_id'] ?? 0 );

		if ( ! $sequence_id ) {
			return self::action_error( 'Email sequence is required', $input );
		}

		$result = \GemCrmPro\Database\Models\EmailSequence::refresh_campaigns( $sequence_id );

		if ( ! $result ) {
			return self::action_error( 'Failed to re-apply sequence', $input );
		}

		return self::action_success( array_merge( $input, [
			'sequence_id' => $sequence_id,
		] ) );
	}

	protected static function action_enroll_in_sequence( array $config, array $input ): array {
		if ( ! class_exists( \GemCrmPro\Database\Models\EmailSequence::class ) ) {
			return self::action_error( 'GemCRM Pro is not installed', $input );
		}

		$contact_id  = (int) ( $config['contact_id'] ?? 0 );
		$sequence_id = (int) ( $config['sequence_id'] ?? 0 );
		$restart     = ( $config['restart_if_exist'] ?? 'no' ) === 'yes';

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( ! $sequence_id ) {
			return self::action_error( 'Email sequence is required', $input );
		}

		$tracker_ids = \GemCrmPro\Database\Models\EmailSequence::subscribe( $sequence_id, [ $contact_id ], $restart );

		return self::action_success( array_merge( $input, [
			'contact_id'  => $contact_id,
			'sequence_id' => $sequence_id,
			// false when the contact was already enrolled and restart_if_exist
			// was left "no" — not a failure, just a no-op enrollment.
			'enrolled'    => ! empty( $tracker_ids ),
		] ) );
	}

	protected static function action_remove_from_sequence( array $config, array $input ): array {
		if ( ! class_exists( \GemCrmPro\Database\Models\EmailSequence::class ) ) {
			return self::action_error( 'GemCRM Pro is not installed', $input );
		}

		$contact_id  = (int) ( $config['contact_id'] ?? 0 );
		$sequence_id = (int) ( $config['sequence_id'] ?? 0 );

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( ! $sequence_id ) {
			return self::action_error( 'Email sequence is required', $input );
		}

		\GemCrmPro\Database\Models\EmailSequence::unsubscribe( $sequence_id, [ $contact_id ], 'Removed by Zaplane automation' );

		return self::action_success( array_merge( $input, [
			'contact_id'  => $contact_id,
			'sequence_id' => $sequence_id,
		] ) );
	}

	protected static function action_send_campaign( array $config, array $input ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Campaign::class ) ) {
			return self::action_error( 'GemCRM is not installed', $input );
		}

		$campaign_id = (int) ( $config['campaign_id'] ?? 0 );

		if ( ! $campaign_id ) {
			return self::action_error( 'Campaign is required', $input );
		}

		$campaign = \GemCrm\Database\Models\Campaign::update( $campaign_id, [ 'status' => 'pending' ] );

		if ( ! $campaign ) {
			return self::action_error( 'Failed to start campaign', $input );
		}

		return self::action_success( array_merge( $input, [
			'campaign_id' => $campaign_id,
			'campaign'    => $campaign,
		] ) );
	}
}
