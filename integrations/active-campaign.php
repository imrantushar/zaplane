<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActiveCampaign extends IntegrationBase {


	private const LIST_STATUSES = [
		'subscribed'   => '1',
		'unsubscribed' => '2',
	];

	public static function get_slug(): string {
		return 'activecampaign';
	}

	public static function get_name(): string {
		return 'ActiveCampaign';
	}

	public static function get_icon(): string {
		return 'activecampaign.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'wp_loaded',
			],
			'contact_subscribed' => [
				'label' => 'Contact Subscribed',
				'hook'  => 'wp_loaded',
			],
			'contact_unsubscribed' => [
				'label' => 'Contact Unsubscribed',
				'hook'  => 'wp_loaded',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'upsert_contact' => [ 'label' => 'Add/Update Contact' ],
			'add_contact_to_list' => [ 'label' => 'Add Contact To List' ],
			'add_tag_to_contact' => [ 'label' => 'Add Tag To Contact' ],
			'remove_tag_from_contact' => [ 'label' => 'Remove Tag From Contact' ],
			'add_contact_to_automation' => [ 'label' => 'Add Contact To Automation' ],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$schema = [
			[
				'key'      => 'form_id',
				'label'    => 'Form',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'activecampaign',
					'query'       => 'forms',
					'select'      => [ 'id', 'name' ],
				],
				'help'     => 'Optional. Only continue when the submitted ActiveCampaign form matches this value.',
			],
			[
				'key'         => 'email',
				'label'       => 'Contact Email',
				'type'        => 'text',
				'placeholder' => 'name@example.com',
				'help'        => 'Optional. Only continue when the submitted contact email matches this value.',
			],
		];

		if ( in_array( $trigger, [ 'form_submitted', 'contact_subscribed', 'contact_unsubscribed' ], true ) ) {
			$schema[] = [
				'key'         => 'list_id',
				'label'       => 'List ID',
				'type'        => 'text',
				'placeholder' => '12',
				'help'        => 'Optional. Only continue when the submitted form includes this list ID.',
			];
		}

		return $schema;
	}

	public static function get_action_config_schema( string $action ): array {
		$email_field = [
			[
				'key'         => 'email',
				'label'       => 'Email Address',
				'type'        => 'text',
				'placeholder' => 'name@example.com or {{email}}',
				'required'    => true,
			],
		];

		switch ( $action ) {
			case 'upsert_contact':
				return array_merge(
					$email_field,
					[
						[
							'key'         => 'first_name',
							'label'       => 'First Name',
							'type'        => 'text',
							'placeholder' => 'John or {{first_name}}',
						],
						[
							'key'         => 'last_name',
							'label'       => 'Last Name',
							'type'        => 'text',
							'placeholder' => 'Doe or {{last_name}}',
						],
						[
							'key'         => 'phone',
							'label'       => 'Phone',
							'type'        => 'text',
							'placeholder' => '+8801XXXXXXXXX or {{phone}}',
						],
					]
				);

			case 'add_contact_to_list':
				return array_merge(
					$email_field,
					[
						[
							'key'      => 'list_id',
							'label'    => 'List',
							'type'     => 'select',
							'dynamic'  => [
								'integration' => 'activecampaign',
								'query'       => 'lists',
								'select'      => [ 'id', 'name' ],
							],
							'required' => true,
						],
						[
							'key'      => 'status',
							'label'    => 'List Status',
							'type'     => 'select',
							'options'  => [
								[
									'value' => 'subscribed',
									'label' => 'Subscribed',
								],
								[
									'value' => 'unsubscribed',
									'label' => 'Unsubscribed',
								],
							],
							'help'     => 'Subscribed creates an active membership. Unsubscribed removes the active list status.',
						],
					]
				);

			case 'add_tag_to_contact':
			case 'remove_tag_from_contact':
				return array_merge(
					$email_field,
					[
						[
							'key'      => 'tag_id',
							'label'    => 'Tag',
							'type'     => 'select',
							'dynamic'  => [
								'integration' => 'activecampaign',
								'query'       => 'tags',
								'select'      => [ 'id', 'name' ],
							],
							'required' => true,
						],
					]
				);

			case 'add_contact_to_automation':
				return array_merge(
					$email_field,
					[
						[
							'key'      => 'automation_id',
							'label'    => 'Automation',
							'type'     => 'select',
							'dynamic'  => [
								'integration' => 'activecampaign',
								'query'       => 'automations',
								'select'      => [ 'id', 'name' ],
							],
							'required' => true,
						],
					]
				);
		}//end switch

		return [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'lists'       => [ self::class, 'query_lists' ],
			'tags'        => [ self::class, 'query_tags' ],
			'automations' => [ self::class, 'query_automations' ],
			'forms'       => [ self::class, 'query_forms' ],
		];
	}

	public static function supports_webhook(): bool {
		return false;
	}

	public static function resolve_trigger( array $node, array $args ) {
		$payload = self::normalize_trigger_payload( $args[0] ?? null );
		if ( null === $payload ) {
			$payload = self::capture_form_process_payload();
		}

		if ( ! is_array( $payload ) || empty( $payload ) ) {
			return false;
		}

		$config = self::get_node_config_data( $node );
		$event  = self::resolve_event( $node );

		if ( ! self::matches_trigger_event( $event, $payload ) ) {
			return false;
		}

		$form_filter = trim( (string) ( $config['form_id'] ?? '' ) );
		$form_id     = (string) ( $payload['form']['id'] ?? '' );
		if ( '' !== $form_filter && 'any' !== $form_filter && $form_filter !== $form_id ) {
			return false;
		}

		$email_filter  = sanitize_email( self::substitute_variables( $config['email'] ?? '', [] ) );
		$contact_email = sanitize_email( (string) ( $payload['contact']['email'] ?? '' ) );
		if ( '' !== $email_filter && $email_filter !== $contact_email ) {
			return false;
		}

		$list_filter = trim( (string) ( $config['list_id'] ?? '' ) );
		if ( '' !== $list_filter && ! self::payload_has_list( $payload, $list_filter ) ) {
			return false;
		}

		return [
			'event'   => $event,
			'form_id' => $form_id,
			'action'  => (string) ( $payload['action'] ?? '' ),
			'contact' => [
				'id'         => (string) ( $payload['contact']['id'] ?? '' ),
				'email'      => $contact_email,
				'first_name' => (string) ( $payload['contact']['first_name'] ?? '' ),
				'last_name'  => (string) ( $payload['contact']['last_name'] ?? '' ),
				'phone'      => (string) ( $payload['contact']['phone'] ?? '' ),
				'fields'     => $payload['contact']['fields'] ?? [],
			],
			'lists'   => $payload['lists'] ?? [],
			'message' => (string) ( $payload['message'] ?? '' ),
			'raw'     => $payload,
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = self::resolve_event( $node );
		$credentials = self::resolve_node_credentials( $node );

		switch ( $action ) {
			case 'upsert_contact':
				return self::run_upsert_contact( $node, $input, $credentials );
			case 'add_contact_to_list':
				return self::run_add_contact_to_list( $node, $input, $credentials );
			case 'add_tag_to_contact':
				return self::run_add_tag_to_contact( $node, $input, $credentials );
			case 'remove_tag_from_contact':
				return self::run_remove_tag_from_contact( $node, $input, $credentials );
			case 'add_contact_to_automation':
				return self::run_add_contact_to_automation( $node, $input, $credentials );
		}//end switch

		throw new \Exception( 'ActiveCampaign action type is missing or unsupported' );
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_auth_type(): string {
		return 'none';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'api_url' => [
				'type'        => 'text',
				'label'       => 'API URL',
				'placeholder' => 'https://youraccount.api-us1.com',
				'required'    => true,
				'help'        => 'Use the same API URL you save in the ActiveCampaign WordPress plugin settings.',
			],
			'api_key' => [
				'type'        => 'password',
				'label'       => 'API Key',
				'placeholder' => 'Paste your ActiveCampaign API key',
				'required'    => true,
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		try {
			[ $body, $status ] = self::activecampaign_request( 'GET', 'users/me', self::normalize_credentials( $credentials ) );
		} catch ( \Throwable $e ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $e->getMessage(),
				'details' => [],
			];
		}

		$user    = $body['user'] ?? [];
		$name    = $user['username'] ?? $user['email'] ?? 'ActiveCampaign account';
		$success = $status >= 200 && $status < 300;

		return [
			'success' => $success,
			'message' => $success ? 'Connected to ' . $name : 'Connection failed',
			'details' => $body,
		];
	}

	public static function query_lists( $q ): array {
		return self::query_collection( $q, 'lists', 'lists', static function ( array $item ): ?array {
			$id = (string) ( $item['id'] ?? '' );
			if ( '' === $id ) {
				return null;
			}

			return [
				'id'   => $id,
				'name' => (string) ( $item['name'] ?? $id ),
			];
		} );
	}

	public static function query_tags( $q ): array {
		return self::query_collection( $q, 'tags', 'tags', static function ( array $item ): ?array {
			$id = (string) ( $item['id'] ?? '' );
			if ( '' === $id ) {
				return null;
			}

			return [
				'id'   => $id,
				'name' => (string) ( $item['tag'] ?? $item['name'] ?? $id ),
			];
		} );
	}

	public static function query_automations( $q ): array {
		return self::query_collection( $q, 'automations', 'automations', static function ( array $item ): ?array {
			$id = (string) ( $item['id'] ?? '' );
			if ( '' === $id ) {
				return null;
			}

			return [
				'id'   => $id,
				'name' => (string) ( $item['name'] ?? $id ),
			];
		} );
	}

	public static function query_forms( $q ): array {
		return self::query_collection( $q, 'forms', 'forms', static function ( array $item ): ?array {
			$id = (string) ( $item['id'] ?? '' );
			if ( '' === $id ) {
				return null;
			}

			return [
				'id'   => $id,
				'name' => (string) ( $item['name'] ?? $id ),
			];
		} );
	}

	private static function run_upsert_contact( array $node, array $input, array $credentials ): array {
		$contact = self::sync_contact( $node, $input, $credentials );

		return [
			'port' => 'main',
			'data' => [
				'activecampaign_contact_id'    => (string) ( $contact['id'] ?? '' ),
				'activecampaign_contact_email' => (string) ( $contact['email'] ?? '' ),
				'activecampaign_first_name'    => (string) ( $contact['firstName'] ?? '' ),
				'activecampaign_last_name'     => (string) ( $contact['lastName'] ?? '' ),
			],
		];
	}

	private static function run_add_contact_to_list( array $node, array $input, array $credentials ): array {
		$config      = self::get_node_config_data( $node );
		$contact     = self::sync_contact( $node, $input, $credentials );
		$list_id     = trim( self::substitute_variables( $config['list_id'] ?? '', $input ) );
		$status      = self::normalize_list_status( self::substitute_variables( $config['status'] ?? 'subscribed', $input ) );
		$contact_id  = (string) ( $contact['id'] ?? '' );

		if ( '' === $list_id ) {
			throw new \Exception( 'ActiveCampaign list ID is required' );
		}

		[ $response ] = self::activecampaign_request(
			'POST',
			'contactLists',
			$credentials,
			[
				'contactList' => [
					'list'    => $list_id,
					'contact' => $contact_id,
					'status'  => $status,
				],
			]
		);

		$list_membership = $response['contactList'] ?? [];

		return [
			'port' => 'main',
			'data' => [
				'activecampaign_contact_id'       => $contact_id,
				'activecampaign_contact_email'    => (string) ( $contact['email'] ?? '' ),
				'activecampaign_list_id'          => $list_id,
				'activecampaign_contact_list_id'  => (string) ( $list_membership['id'] ?? '' ),
				'activecampaign_list_status'      => (string) ( $list_membership['status'] ?? $status ),
			],
		];
	}

	private static function run_add_tag_to_contact( array $node, array $input, array $credentials ): array {
		$config     = self::get_node_config_data( $node );
		$contact    = self::sync_contact( $node, $input, $credentials );
		$tag_id     = trim( self::substitute_variables( $config['tag_id'] ?? '', $input ) );
		$contact_id = (string) ( $contact['id'] ?? '' );

		if ( '' === $tag_id ) {
			throw new \Exception( 'ActiveCampaign tag ID is required' );
		}

		[ $response ] = self::activecampaign_request(
			'POST',
			'contactTags',
			$credentials,
			[
				'contactTag' => [
					'contact' => $contact_id,
					'tag'     => $tag_id,
				],
			]
		);

		$contact_tag = $response['contactTag'] ?? [];

		return [
			'port' => 'main',
			'data' => [
				'activecampaign_contact_id'      => $contact_id,
				'activecampaign_contact_email'   => (string) ( $contact['email'] ?? '' ),
				'activecampaign_tag_id'          => $tag_id,
				'activecampaign_contact_tag_id'  => (string) ( $contact_tag['id'] ?? '' ),
			],
		];
	}

	private static function run_remove_tag_from_contact( array $node, array $input, array $credentials ): array {
		$config     = self::get_node_config_data( $node );
		$contact    = self::sync_contact( $node, $input, $credentials );
		$tag_id     = trim( self::substitute_variables( $config['tag_id'] ?? '', $input ) );
		$contact_id = (string) ( $contact['id'] ?? '' );

		if ( '' === $tag_id ) {
			throw new \Exception( 'ActiveCampaign tag ID is required' );
		}

		$contact_tag_id = self::find_contact_tag_id( $contact_id, $tag_id, $credentials );
		if ( '' === $contact_tag_id ) {
			return [
				'port' => 'main',
				'data' => [
					'activecampaign_contact_id'    => $contact_id,
					'activecampaign_contact_email' => (string) ( $contact['email'] ?? '' ),
					'activecampaign_tag_id'        => $tag_id,
					'activecampaign_tag_removed'   => false,
				],
			];
		}

		self::activecampaign_request( 'DELETE', 'contactTags/' . rawurlencode( $contact_tag_id ), $credentials );

		return [
			'port' => 'main',
			'data' => [
				'activecampaign_contact_id'     => $contact_id,
				'activecampaign_contact_email'  => (string) ( $contact['email'] ?? '' ),
				'activecampaign_tag_id'         => $tag_id,
				'activecampaign_contact_tag_id' => $contact_tag_id,
				'activecampaign_tag_removed'    => true,
			],
		];
	}

	private static function run_add_contact_to_automation( array $node, array $input, array $credentials ): array {
		$config         = self::get_node_config_data( $node );
		$contact        = self::sync_contact( $node, $input, $credentials );
		$automation_id  = trim( self::substitute_variables( $config['automation_id'] ?? '', $input ) );
		$contact_id     = (string) ( $contact['id'] ?? '' );

		if ( '' === $automation_id ) {
			throw new \Exception( 'ActiveCampaign automation ID is required' );
		}

		[ $response ] = self::activecampaign_request(
			'POST',
			'contactAutomations',
			$credentials,
			[
				'contactAutomation' => [
					'contact'    => $contact_id,
					'automation' => $automation_id,
				],
			]
		);

		$automation = $response['contactAutomation'] ?? [];

		return [
			'port' => 'main',
			'data' => [
				'activecampaign_contact_id'            => $contact_id,
				'activecampaign_contact_email'         => (string) ( $contact['email'] ?? '' ),
				'activecampaign_automation_id'         => $automation_id,
				'activecampaign_contact_automation_id' => (string) ( $automation['id'] ?? '' ),
			],
		];
	}

	private static function sync_contact( array $node, array $input, array $credentials ): array {
		$config  = self::get_node_config_data( $node );
		$email   = sanitize_email( self::substitute_variables( $config['email'] ?? '', $input ) );
		$contact = [
			'email' => $email,
		];

		if ( '' === $email || ! is_email( $email ) ) {
			throw new \Exception( 'A valid ActiveCampaign contact email is required' );
		}

		$first_name = trim( self::substitute_variables( $config['first_name'] ?? '', $input ) );
		$last_name  = trim( self::substitute_variables( $config['last_name'] ?? '', $input ) );
		$phone      = trim( self::substitute_variables( $config['phone'] ?? '', $input ) );

		if ( '' !== $first_name ) {
			$contact['firstName'] = $first_name;
		}
		if ( '' !== $last_name ) {
			$contact['lastName'] = $last_name;
		}
		if ( '' !== $phone ) {
			$contact['phone'] = $phone;
		}

		[ $response ] = self::activecampaign_request(
			'POST',
			'contact/sync',
			$credentials,
			[ 'contact' => $contact ]
		);

		$resolved = $response['contact'] ?? [];
		if ( empty( $resolved ) ) {
			throw new \Exception( 'ActiveCampaign contact sync failed' );
		}

		return $resolved;
	}

	private static function find_contact_tag_id( string $contact_id, string $tag_id, array $credentials ): string {
		[ $response ] = self::activecampaign_request( 'GET', 'contacts/' . rawurlencode( $contact_id ) . '/contactTags', $credentials );
		$items = $response['contactTags'] ?? [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( (string) ( $item['tag'] ?? '' ) !== $tag_id ) {
				continue;
			}
			return (string) ( $item['id'] ?? '' );
		}

		return '';
	}

	private static function activecampaign_request( string $method, string $path, array $credentials, ?array $body = null ): array {
		$url = self::build_api_url( $credentials['api_url'], $path );
		$args = [
			'headers' => [
				'Api-Token'    => $credentials['api_key'],
				'Accept'       => 'application/json',
			],
		];

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $body );
		}

		[ $response_body, $status ] = self::http_request( $method, $url, $args );

		if ( $status >= 400 ) {
			$message = self::extract_api_error( $response_body );
			throw new \Exception( 'ActiveCampaign API error: ' . $message );
		}

		return [ $response_body, $status ];
	}

	private static function query_collection( $q, string $path, string $response_key, callable $map ): array {
		try {
			$credentials = self::resolve_dynamic_credentials( is_array( $q ) ? $q : [] );
		} catch ( \Throwable $e ) {
			return [];
		}

		$search = self::normalize_dynamic_search( is_array( $q ) ? $q : [] );
		$limit  = self::normalize_dynamic_limit( is_array( $q ) ? $q : [] );

		try {
			[ $response ] = self::activecampaign_request( 'GET', $path, $credentials );
		} catch ( \Throwable $e ) {
			return [];
		}

		$items  = $response[ $response_key ] ?? [];
		$result = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$mapped = $map( $item );
			if ( ! is_array( $mapped ) ) {
				continue;
			}
			if ( ! self::matches_dynamic_search( $search, (string) ( $mapped['name'] ?? '' ) ) ) {
				continue;
			}
			$result[] = $mapped;
		}

		return array_slice( $result, 0, $limit );
	}

	private static function resolve_node_credentials( array $node ): array {
		return self::resolve_credentials( $node['_connection_credentials'] ?? null );
	}

	private static function resolve_credentials( ?array $credentials ): array {
		if ( is_array( $credentials ) ) {
			$api_url = trim( (string) ( $credentials['api_url'] ?? '' ) );
			$api_key = trim( (string) ( $credentials['api_key'] ?? '' ) );

			if ( '' !== $api_url && '' !== $api_key ) {
				return self::normalize_credentials( [
					'api_url' => $api_url,
					'api_key' => $api_key,
				] );
			}
		}

		$settings = get_option( 'settings_activecampaign', [] );
		$api_url  = trim( (string) ( $settings['api_url'] ?? '' ) );
		$api_key  = trim( (string) ( $settings['api_key'] ?? '' ) );

		if ( '' === $api_url || '' === $api_key ) {
			throw new \Exception( 'ActiveCampaign credentials not found. Save them in the ActiveCampaign plugin settings or create a Zaplane connection.' );
		}

		return self::normalize_credentials( [
			'api_url' => $api_url,
			'api_key' => $api_key,
		] );
	}

	private static function normalize_credentials( array $credentials ): array {
		$api_url = trim( (string) ( $credentials['api_url'] ?? '' ) );
		$api_key = trim( (string) ( $credentials['api_key'] ?? '' ) );

		if ( '' === $api_url || '' === $api_key ) {
			throw new \Exception( 'ActiveCampaign API URL and API key are required' );
		}

		if ( ! self::is_valid_api_url( $api_url ) ) {
			throw new \Exception( 'ActiveCampaign API URL is invalid. Expected a secure https:// account domain.' );
		}

		return [
			'api_url' => preg_replace( '#/api/3/?$#', '', rtrim( $api_url, '/' ) ),
			'api_key' => $api_key,
		];
	}

	private static function resolve_dynamic_credentials( array $q ): array {
		$api_url = trim( (string) ( $q['api_url'] ?? '' ) );
		$api_key = trim( (string) ( $q['api_key'] ?? '' ) );

		if ( '' === $api_url && isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$api_url = trim( (string) ( $q['where']['api_url'] ?? '' ) );
		}
		if ( '' === $api_key && isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$api_key = trim( (string) ( $q['where']['api_key'] ?? '' ) );
		}

		if ( '' !== $api_url && '' !== $api_key ) {
			return self::normalize_credentials( [
				'api_url' => $api_url,
				'api_key' => $api_key,
			] );
		}

		$connection_id = $q['connection_id'] ?? null;
		if ( null === $connection_id && isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$connection_id = $q['where']['connection_id'] ?? null;
		}

		if ( $connection_id ) {
			try {
				$manager     = new ConnectionManager();
				$credentials = $manager->get_execution_credentials( (int) $connection_id );
				return self::normalize_credentials( $credentials );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		if ( function_exists( 'get_current_user_id' ) ) {
			$user_id = (int) get_current_user_id();
			if ( $user_id > 0 ) {
				try {
					$manager     = new ConnectionManager();
					$connections = $manager->get_user_connections( $user_id, self::get_slug(), 1, 1 );
					$first       = $connections['data'][0] ?? null;
					if ( $first && isset( $first['id'] ) ) {
						$credentials = $manager->get_execution_credentials( (int) $first['id'] );
						return self::normalize_credentials( $credentials );
					}
				} catch ( \Throwable $e ) {
					unset( $e );
				}
			}
		}

		return self::resolve_credentials( null );
	}

	private static function build_api_url( string $api_url, string $path ): string {
		return rtrim( $api_url, '/' ) . '/api/3/' . ltrim( $path, '/' );
	}

	private static function extract_api_error( $response_body ): string {
		if ( ! is_array( $response_body ) ) {
			return 'Unknown error';
		}

		if ( isset( $response_body['message'] ) && is_string( $response_body['message'] ) ) {
			return $response_body['message'];
		}

		if ( isset( $response_body['error'] ) && is_string( $response_body['error'] ) ) {
			return $response_body['error'];
		}

		$errors = $response_body['errors'] ?? null;
		if ( is_array( $errors ) ) {
			$first = reset( $errors );
			if ( is_array( $first ) && isset( $first['title'] ) ) {
				return (string) $first['title'];
			}
			if ( is_string( $first ) ) {
				return $first;
			}
		}

		return 'Unknown error';
	}

	private static function is_valid_api_url( string $api_url ): bool {
		return (bool) preg_match( '#^https://[a-zA-Z0-9_-]+\.(?:api-[a-z0-9]+|activehosted)\.com(?:/api/3)?/?$#', $api_url );
	}

	private static function normalize_list_status( string $status ): string {
		$status = sanitize_key( $status );
		return self::LIST_STATUSES[ $status ] ?? self::LIST_STATUSES['subscribed'];
	}

	private static function normalize_trigger_payload( $payload ): ?array {
		return is_array( $payload ) && ! empty( $payload ) ? $payload : null;
	}

	private static function capture_form_process_payload(): ?array {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return null;
		}

		$request_uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		$request_path = (string) parse_url( $request_uri, PHP_URL_PATH );
		if ( '' === $request_path || ! preg_match( '#/activecampaign-subscription-forms/form_process\.php$#', $request_path ) ) {
			return null;
		}

		$form_id = trim( (string) ( $_POST['f'] ?? '' ) );
		$email   = sanitize_email( (string) ( $_POST['email'] ?? '' ) );
		$lists   = self::normalize_request_lists( $_POST['nlbox'] ?? [] );

		if ( '' === $form_id || '' === $email || empty( $lists ) ) {
			return null;
		}

		$action     = self::normalize_request_action();
		$names      = self::resolve_request_names();
		$phone      = trim( (string) ( $_POST['phone'] ?? '' ) );
		$fields     = isset( $_POST['field'] ) && is_array( $_POST['field'] ) ? $_POST['field'] : [];
		$sync_value = isset( $_GET['sync'] ) ? (int) $_GET['sync'] : 0;

		return [
			'form'    => [
				'id' => $form_id,
			],
			'action'  => $action,
			'sync'    => $sync_value,
			'contact' => [
				'id'         => '',
				'email'      => $email,
				'first_name' => $names['first_name'],
				'last_name'  => $names['last_name'],
				'phone'      => $phone,
				'fields'     => $fields,
			],
			'lists'   => $lists,
			'message' => '',
		];
	}

	private static function normalize_request_lists( $lists ): array {
		if ( ! is_array( $lists ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $lists as $list_id ) {
			$list_id = trim( (string) $list_id );
			if ( '' === $list_id ) {
				continue;
			}
			$normalized[] = [ 'id' => $list_id ];
		}

		return $normalized;
	}

	private static function normalize_request_action(): string {
		$action = (string) ( $_POST['act_radio'] ?? $_POST['act'] ?? 'sub' );
		return 'unsub' === $action ? 'unsub' : 'sub';
	}

	private static function resolve_request_names(): array {
		$full_name = trim( (string) ( $_POST['fullname'] ?? '' ) );
		if ( '' !== $full_name ) {
			$parts      = preg_split( '/\s+/', $full_name );
			$first_name = (string) array_shift( $parts );
			$last_name  = implode( ' ', $parts );

			return [
				'first_name' => $first_name,
				'last_name'  => $last_name,
			];
		}

		return [
			'first_name' => trim( (string) ( $_POST['firstname'] ?? $_POST['first_name'] ?? '' ) ),
			'last_name'  => trim( (string) ( $_POST['lastname'] ?? $_POST['last_name'] ?? '' ) ),
		];
	}

	private static function matches_trigger_event( string $event, array $payload ): bool {
		$action = (string) ( $payload['action'] ?? '' );

		if ( 'contact_subscribed' === $event ) {
			return 'unsub' !== $action;
		}

		if ( 'contact_unsubscribed' === $event ) {
			return 'unsub' === $action;
		}

		return 'form_submitted' === $event;
	}

	private static function payload_has_list( array $payload, string $required_list_id ): bool {
		$lists = $payload['lists'] ?? [];
		if ( ! is_array( $lists ) ) {
			return false;
		}

		foreach ( $lists as $list ) {
			if ( is_array( $list ) && (string) ( $list['id'] ?? '' ) === $required_list_id ) {
				return true;
			}
		}

		return false;
	}

	private static function normalize_dynamic_limit( array $q ): int {
		$limit = (int) ( $q['limit'] ?? 20 );
		if ( $limit < 1 ) {
			$limit = 20;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		return $limit;
	}

	private static function normalize_dynamic_search( array $q ): string {
		return trim( (string) ( $q['search'] ?? '' ) );
	}

	private static function matches_dynamic_search( string $search, string $value ): bool {
		if ( '' === $search ) {
			return true;
		}

		return stripos( $value, $search ) !== false;
	}

	private static function resolve_event( array $node ): string {
		if ( isset( $node['config']['action'] ) && is_string( $node['config']['action'] ) ) {
			return $node['config']['action'];
		}

		$data = $node['data'] ?? [];
		if ( isset( $data['event'] ) && is_string( $data['event'] ) ) {
			return $data['event'];
		}

		if ( isset( $node['event'] ) && is_string( $node['event'] ) ) {
			return $node['event'];
		}

		return '';
	}

	private static function get_node_config_data( array $node ): array {
		$data = $node['data'] ?? [];
		if ( isset( $data['config'] ) && is_array( $data['config'] ) ) {
			return $data['config'];
		}

		if ( isset( $node['config'] ) && is_array( $node['config'] ) && ! isset( $node['config']['action'] ) ) {
			return $node['config'];
		}

		return [];
	}

	private static function substitute_variables( $text, array $data ): string {
		$text = (string) $text;

		return preg_replace_callback(
			'/\{\{([^}]+)\}\}/',
			static function ( $matches ) use ( $data ) {
				$key   = trim( $matches[1] );
				$keys  = explode( '.', $key );
				$value = $data;

				foreach ( $keys as $part ) {
					if ( is_array( $value ) && isset( $value[ $part ] ) ) {
						$value = $value[ $part ];
						continue;
					}

					return $matches[0];
				}

				return is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			},
			$text
		);
	}
}
