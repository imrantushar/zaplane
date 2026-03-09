<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hubspot extends IntegrationBase {

	private const API_BASE_URL = 'https://api.hubapi.com';
	private const FORMS_BASE_URL = 'https://api.hsforms.com';

	public static function get_slug(): string {
		return 'hubspot';
	}

	public static function get_name(): string {
		return 'HubSpot';
	}

	public static function get_icon(): string {
		return 'hubspot';
	}

	public static function get_actions(): array {
		return array(
			'update_contact' => array( 'label' => 'Update Contact (by Email)' ),
			'upsert_contact' => array( 'label' => 'Create or Update Contact' ),
			'upsert_company' => array( 'label' => 'Create or Update Company' ),
			'upsert_deal'    => array( 'label' => 'Create or Update Deal' ),
			'add_contacts_to_list'    => array( 'label' => 'Add Contacts To List' ),
			'remove_contacts_from_list' => array( 'label' => 'Remove Contacts From List' ),
			'find_contact'   => array( 'label' => 'Find Contact (by Email)' ),
			'submit_form'    => array( 'label' => 'Submit Form' ),
		);
	}

	public static function get_action_config_schema(  $action ): array {
		$auth = array(
			array(
				'key'         => 'access_token',
				'label'       => 'Private App Token',
				'type'        => 'password',
				'placeholder' => 'pat-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
				'help'        => 'Optional if you already selected a connection. Required if no connection is used.',
			),
		);

		if ( $action === 'update_contact' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'email',
					'label'       => 'Email',
					'type'        => 'text',
					'placeholder' => 'name@example.com or {{email}}',
					'required'    => true,
				),
				array(
					'key'         => 'status',
					'label'       => 'Status',
					'type'        => 'text',
					'placeholder' => 'lead, customer, evangelist',
					'help'        => 'Optional. If provided, it will be merged into properties as "status".',
				),
				array(
					'key'         => 'status_if_new',
					'label'       => 'Status If New',
					'type'        => 'text',
					'placeholder' => 'lead',
					'help'        => 'Optional. If provided, it will be merged into properties as "status_if_new".',
				),
				array(
					'key'         => 'properties',
					'label'       => 'Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"jobtitle":"Manager","phone":"+18884827768"}',
					'help'        => 'Optional JSON object of properties to update.',
				),
			) );
		}

		if ( $action === 'upsert_contact' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'email',
					'label'       => 'Email',
					'type'        => 'text',
					'placeholder' => 'name@example.com or {{email}}',
					'required'    => true,
				),
				array(
					'key'         => 'firstname',
					'label'       => 'First Name',
					'type'        => 'text',
					'placeholder' => 'John or {{first_name}}',
				),
				array(
					'key'         => 'lastname',
					'label'       => 'Last Name',
					'type'        => 'text',
					'placeholder' => 'Doe or {{last_name}}',
				),
				array(
					'key'         => 'phone',
					'label'       => 'Phone',
					'type'        => 'text',
					'placeholder' => '+18884827768 or {{phone}}',
				),
				array(
					'key'         => 'properties',
					'label'       => 'Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"firstname":"Jane","lastname":"Doe"}',
					'help'        => 'Optional JSON object of properties to set or update.',
				),
			) );
		}

		if ( $action === 'upsert_company' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'company_id',
					'label'       => 'Company ID',
					'type'        => 'text',
					'placeholder' => '12345',
					'help'        => 'Optional. If provided, updates the company. Otherwise creates a new company.',
				),
				array(
					'key'         => 'name',
					'label'       => 'Company Name',
					'type'        => 'text',
					'placeholder' => 'Acme Inc',
				),
				array(
					'key'         => 'domain',
					'label'       => 'Domain',
					'type'        => 'text',
					'placeholder' => 'acme.com',
				),
				array(
					'key'         => 'phone',
					'label'       => 'Phone',
					'type'        => 'text',
					'placeholder' => '+18884827768',
				),
				array(
					'key'         => 'website',
					'label'       => 'Website',
					'type'        => 'text',
					'placeholder' => 'https://acme.com',
				),
				array(
					'key'         => 'properties',
					'label'       => 'Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"name":"Acme Inc","domain":"acme.com"}',
					'help'        => 'Optional JSON object of properties to set or update.',
				),
			) );
		}

		if ( $action === 'upsert_deal' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'deal_id',
					'label'       => 'Deal ID',
					'type'        => 'text',
					'placeholder' => '12345',
					'help'        => 'Optional. If provided, updates the deal. Otherwise creates a new deal.',
				),
				array(
					'key'         => 'dealname',
					'label'       => 'Deal Name',
					'type'        => 'text',
					'placeholder' => 'New deal',
				),
				array(
					'key'         => 'amount',
					'label'       => 'Amount',
					'type'        => 'text',
					'placeholder' => '1500.00',
				),
				array(
					'key'         => 'dealstage',
					'label'       => 'Deal Stage',
					'type'        => 'select',
					'dynamic'     => array(
						'integration' => 'hubspot',
						'query'       => 'deal_stages',
						'select'      => array( 'id', 'label' ),
					),
				),
				array(
					'key'         => 'pipeline',
					'label'       => 'Pipeline',
					'type'        => 'select',
					'dynamic'     => array(
						'integration' => 'hubspot',
						'query'       => 'pipelines',
						'select'      => array( 'id', 'label' ),
					),
				),
				array(
					'key'         => 'closedate',
					'label'       => 'Close Date',
					'type'        => 'text',
					'placeholder' => '2026-12-31',
					'help'        => 'Optional. Use a timestamp in ms or a date string supported by HubSpot.',
				),
				array(
					'key'         => 'properties',
					'label'       => 'Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"dealname":"New deal","dealstage":"contractsent","pipeline":"default","amount":"1500.00"}',
					'help'        => 'Optional JSON object of properties to set or update.',
				),
			) );
		}

		if ( $action === 'add_contacts_to_list' || $action === 'remove_contacts_from_list' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'list_id',
					'label'       => 'List ID',
					'type'        => 'select',
					'dynamic'     => array(
						'integration' => 'hubspot',
						'query'       => 'lists',
						'select'      => array( 'id', 'name' ),
					),
					'required'    => true,
					'help'        => 'Only works with static (manual) lists.',
				),
				array(
					'key'         => 'contact_ids',
					'label'       => 'Contact IDs',
					'type'        => 'text',
					'placeholder' => '123,456,789',
					'help'        => 'Optional. Comma-separated contact record IDs.',
				),
				array(
					'key'         => 'emails',
					'label'       => 'Emails',
					'type'        => 'text',
					'placeholder' => 'a@example.com, b@example.com',
					'help'        => 'Optional. Comma-separated emails (will be looked up to IDs).',
				),
			) );
		}

		if ( $action === 'find_contact' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'email',
					'label'       => 'Email',
					'type'        => 'text',
					'placeholder' => 'name@example.com or {{email}}',
					'required'    => true,
				),
				array(
					'key'         => 'properties',
					'label'       => 'Properties (comma-separated)',
					'type'        => 'text',
					'placeholder' => 'email,firstname,lastname',
					'help'        => 'Optional list of properties to return.',
				),
			) );
		}

		if ( $action === 'submit_form' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'portal_id',
					'label'       => 'Portal ID',
					'type'        => 'select',
					'dynamic'     => array(
						'integration' => 'hubspot',
						'query'       => 'portals',
						'select'      => array( 'id', 'label' ),
					),
					'required'    => true,
				),
				array(
					'key'         => 'form_guid',
					'label'       => 'Form GUID',
					'type'        => 'select',
					'dynamic'     => array(
						'integration' => 'hubspot',
						'query'       => 'forms',
						'select'      => array( 'guid', 'name' ),
					),
					'required'    => true,
				),
				array(
					'key'         => 'email',
					'label'       => 'Email',
					'type'        => 'text',
					'placeholder' => 'name@example.com or {{email}}',
					'help'        => 'Optional. Used to auto-build fields if Fields (JSON) is empty.',
				),
				array(
					'key'         => 'firstname',
					'label'       => 'First Name',
					'type'        => 'text',
					'placeholder' => 'John or {{first_name}}',
					'help'        => 'Optional. Used to auto-build fields if Fields (JSON) is empty.',
				),
				array(
					'key'         => 'lastname',
					'label'       => 'Last Name',
					'type'        => 'text',
					'placeholder' => 'Doe or {{last_name}}',
					'help'        => 'Optional. Used to auto-build fields if Fields (JSON) is empty.',
				),
				array(
					'key'         => 'phone',
					'label'       => 'Phone',
					'type'        => 'text',
					'placeholder' => '+18884827768 or {{phone}}',
					'help'        => 'Optional. Used to auto-build fields if Fields (JSON) is empty.',
				),
				array(
					'key'         => 'fields',
					'label'       => 'Fields (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"email":"name@example.com","firstname":"John"}',
					'help'        => 'Optional. JSON object (field name to value) or array of field objects. If empty, simple fields above will be used.',
				),
				array(
					'key'         => 'context',
					'label'       => 'Context (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"pageUri":"https://example.com","pageName":"Landing"}',
					'help'        => 'Optional. Include hutk, pageUri, pageName, ipAddress, etc.',
				),
				array(
					'key'         => 'legal_consent_options',
					'label'       => 'Legal Consent (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"consent":{"consentToProcess":true,"text":"...","communications":[{"value":true,"subscriptionTypeId":123,"text":"..."}]}}',
					'help'        => 'Optional. GDPR/consent data.',
				),
				array(
					'key'         => 'submitted_at',
					'label'       => 'Submitted At (ms)',
					'type'        => 'text',
					'placeholder' => '1700000000000',
					'help'        => 'Optional timestamp in milliseconds.',
				),
				array(
					'key'         => 'skip_validation',
					'label'       => 'Skip Validation',
					'type'        => 'select',
					'options'     => array(
						array( 'value' => '', 'label' => 'Default' ),
						array( 'value' => 'true', 'label' => 'True' ),
						array( 'value' => 'false', 'label' => 'False' ),
					),
				),
			) );
		}

		return array();
	}

	public static function execute_node( array $node, array $input ): array {
		$action = self::resolve_action( $node );
		$credentials = $node['_connection_credentials'] ?? null;

		if ( $action === '' ) {
			throw new \Exception( 'HubSpot action type is missing' );
		}

		$access_token = self::resolve_access_token( $node, $input, $credentials );

		if ( $action === 'update_contact' ) {
			return self::action_update_contact( $node, $input, $access_token );
		}

		if ( $action === 'upsert_contact' ) {
			return self::action_upsert_contact( $node, $input, $access_token );
		}

		if ( $action === 'upsert_company' ) {
			return self::action_upsert_company( $node, $input, $access_token );
		}

		if ( $action === 'upsert_deal' ) {
			return self::action_upsert_deal( $node, $input, $access_token );
		}

		if ( $action === 'add_contacts_to_list' ) {
			return self::action_update_list_memberships( $node, $input, $access_token, 'add' );
		}

		if ( $action === 'remove_contacts_from_list' ) {
			return self::action_update_list_memberships( $node, $input, $access_token, 'remove' );
		}

		if ( $action === 'find_contact' ) {
			return self::action_find_contact( $node, $input, $access_token );
		}

		if ( $action === 'submit_form' ) {
			return self::action_submit_form( $node, $input, $access_token );
		}

		return array(
			'port' => 'main',
			'data' => $input,
		);
	}

	/* =====================================================
	 * DYNAMIC DATA QUERIES (API)
	 * ===================================================== */

	public static function get_dynamic_queries(): array {
		return array(
			'lists'       => array( self::class, 'query_lists' ),
			'pipelines'   => array( self::class, 'query_pipelines' ),
			'deal_stages' => array( self::class, 'query_deal_stages' ),
			'forms'       => array( self::class, 'query_forms' ),
			'portals'     => array( self::class, 'query_portals' ),
		);
	}

	public static function query_lists( $q ) {
		$q = is_array( $q ) ? $q : array();
		$access_token = self::resolve_dynamic_access_token( $q );
		if ( $access_token === '' ) {
			return array();
		}

		$limit  = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		try {
			[ $response ] = self::hubspot_request(
				'GET',
				'/crm/v3/lists/?limit=' . $limit,
				$access_token
			);
		} catch ( \Throwable $e ) {
			return array();
		}

		$lists = $response['results'] ?? ( is_array( $response ) ? $response : array() );
		$items = array();

		foreach ( $lists as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}

			if ( isset( $list['listType'] ) && $list['listType'] !== 'STATIC' ) {
				continue;
			}

			$id = $list['listId'] ?? $list['id'] ?? '';
			if ( $id === '' ) {
				continue;
			}

			$name = $list['name'] ?? $list['label'] ?? (string) $id;
			if ( ! self::matches_dynamic_search( $search, $name ) ) {
				continue;
			}

			$items[] = array(
				'id'           => (string) $id,
				'name'         => $name,
				'listType'     => $list['listType'] ?? '',
				'objectTypeId' => $list['objectTypeId'] ?? '',
			);
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_pipelines( $q ) {
		$q = is_array( $q ) ? $q : array();
		$access_token = self::resolve_dynamic_access_token( $q );
		if ( $access_token === '' ) {
			return array();
		}

		$limit  = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		try {
			[ $response ] = self::hubspot_request(
				'GET',
				'/crm/v3/pipelines/deals',
				$access_token
			);
		} catch ( \Throwable $e ) {
			return array();
		}

		$pipelines = $response['results'] ?? array();
		$items = array();

		foreach ( $pipelines as $pipeline ) {
			if ( ! is_array( $pipeline ) ) {
				continue;
			}
			$id    = $pipeline['id'] ?? '';
			$label = $pipeline['label'] ?? $pipeline['displayName'] ?? $id;
			if ( $id === '' ) {
				continue;
			}
			if ( ! self::matches_dynamic_search( $search, $label ) ) {
				continue;
			}
			$items[] = array(
				'id'    => (string) $id,
				'label' => $label,
			);
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_deal_stages( $q ) {
		$q = is_array( $q ) ? $q : array();
		$access_token = self::resolve_dynamic_access_token( $q );
		if ( $access_token === '' ) {
			return array();
		}

		$limit  = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );
		$pipeline_id = '';

		if ( isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$pipeline_id = trim( (string) ( $q['where']['pipeline_id'] ?? $q['where']['pipeline'] ?? '' ) );
		}

		$items = array();

		try {
			if ( $pipeline_id !== '' ) {
				[ $pipeline ] = self::hubspot_request(
					'GET',
					'/crm/v3/pipelines/deals/' . rawurlencode( $pipeline_id ),
					$access_token
				);

				$pipeline_label = is_array( $pipeline ) ? ( $pipeline['label'] ?? $pipeline['displayName'] ?? $pipeline_id ) : $pipeline_id;
				$stages = is_array( $pipeline ) ? ( $pipeline['stages'] ?? array() ) : array();

				foreach ( $stages as $stage ) {
					if ( ! is_array( $stage ) ) {
						continue;
					}
					$id    = $stage['id'] ?? '';
					$label = $stage['label'] ?? $stage['displayName'] ?? $id;
					if ( $id === '' ) {
						continue;
					}
					if ( ! self::matches_dynamic_search( $search, $label ) ) {
						continue;
					}
					$items[] = array(
						'id'             => (string) $id,
						'label'          => $label,
						'pipeline_id'    => $pipeline_id,
						'pipeline_label' => $pipeline_label,
					);
					if ( count( $items ) >= $limit ) {
						break;
					}
				}
			} else {
				[ $response ] = self::hubspot_request(
					'GET',
					'/crm/v3/pipelines/deals',
					$access_token
				);

				$pipelines = $response['results'] ?? array();

				foreach ( $pipelines as $pipeline ) {
					if ( ! is_array( $pipeline ) ) {
						continue;
					}
					$pipeline_label = $pipeline['label'] ?? $pipeline['displayName'] ?? $pipeline['id'] ?? '';
					$pipeline_id = $pipeline['id'] ?? '';
					$stages = $pipeline['stages'] ?? array();

					foreach ( $stages as $stage ) {
						if ( ! is_array( $stage ) ) {
							continue;
						}
						$id = $stage['id'] ?? '';
						if ( $id === '' ) {
							continue;
						}
						$stage_label = $stage['label'] ?? $stage['displayName'] ?? $id;
						$label = trim( $pipeline_label . ' - ' . $stage_label );

						if ( $search !== '' ) {
							$matches_stage = self::matches_dynamic_search( $search, $stage_label );
							$matches_pipeline = self::matches_dynamic_search( $search, $pipeline_label );
							if ( ! $matches_stage && ! $matches_pipeline ) {
								continue;
							}
						}

						$items[] = array(
							'id'             => (string) $id,
							'label'          => $label,
							'pipeline_id'    => $pipeline_id,
							'pipeline_label' => $pipeline_label,
						);

						if ( count( $items ) >= $limit ) {
							break 2;
						}
					}
				}
			}
		} catch ( \Throwable $e ) {
			return array();
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_forms( $q ) {
		$q = is_array( $q ) ? $q : array();
		$access_token = self::resolve_dynamic_access_token( $q );
		if ( $access_token === '' ) {
			return array();
		}

		$limit  = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		try {
			[ $response ] = self::hubspot_request(
				'GET',
				'/forms/v2/forms',
				$access_token
			);
		} catch ( \Throwable $e ) {
			return array();
		}

		$forms = is_array( $response ) ? $response : array();
		$items = array();

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}
			$guid = $form['guid'] ?? '';
			if ( $guid === '' ) {
				continue;
			}
			$name = $form['name'] ?? $form['displayName'] ?? $guid;
			if ( ! self::matches_dynamic_search( $search, $name ) ) {
				continue;
			}
			$items[] = array(
				'guid'      => (string) $guid,
				'name'      => $name,
				'portal_id' => (string) ( $form['portalId'] ?? '' ),
			);
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_portals( $q ) {
		$q = is_array( $q ) ? $q : array();
		$access_token = self::resolve_dynamic_access_token( $q );
		if ( $access_token === '' ) {
			return array();
		}

		try {
			[ $response ] = self::hubspot_request(
				'POST',
				'/oauth/v2/private-apps/get/access-token-info',
				$access_token,
				array( 'tokenKey' => $access_token )
			);
		} catch ( \Throwable $e ) {
			return array();
		}

		$hub_id = $response['hubId'] ?? $response['hub_id'] ?? $response['portalId'] ?? '';
		if ( $hub_id === '' ) {
			return array();
		}

		return array(
			array(
				'id'    => (string) $hub_id,
				'label' => 'Hub ID ' . $hub_id,
			),
		);
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_auth_type(): string {
		return 'none';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return array(
			'access_token' => array(
				'type'        => 'password',
				'label'       => 'Private App Token',
				'placeholder' => 'pat-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
				'required'    => true,
				'help'        => 'Create a Private App in HubSpot and paste its access token.',
			),
		);
	}

	public static function test_connection( array $credentials ): array {
		$access_token = trim( $credentials['access_token'] ?? '' );
		if ( $access_token === '' ) {
			return array(
				'success' => false,
				'message' => 'Private App token is required',
				'details' => array(),
			);
		}

		try {
			[ $body, $status ] = self::hubspot_request(
				'POST',
				'/oauth/v2/private-apps/get/access-token-info',
				$access_token,
				array( 'tokenKey' => $access_token )
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => 'Connection test failed: ' . $e->getMessage(),
				'details' => array(),
			);
		}

		return array(
			'success' => $status >= 200 && $status < 300,
			'message' => $status >= 200 && $status < 300 ? 'Connected to HubSpot' : 'Connection failed',
			'details' => $body,
		);
	}

	private static function action_update_contact( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$email = self::substitute_variables( $data['email'] ?? '', $input );
		if ( $email === '' ) {
			throw new \Exception( 'Email is required' );
		}

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, array() );
		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$status = self::substitute_variables( $data['status'] ?? '', $input );
		if ( $status !== '' ) {
			$properties['status'] = $status;
		}

		$status_if_new = self::substitute_variables( $data['status_if_new'] ?? '', $input );
		if ( $status_if_new !== '' ) {
			$properties['status_if_new'] = $status_if_new;
		}

		if ( empty( $properties ) ) {
			throw new \Exception( 'At least one property is required' );
		}

		$path = '/crm/v3/objects/contacts/' . rawurlencode( $email ) . '?idProperty=email';

		[ $response ] = self::hubspot_request(
			'PATCH',
			$path,
			$access_token,
			array( 'properties' => $properties )
		);

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_contact_id' => $response['id'] ?? '',
				'hubspot_email'      => $email,
			),
		);
	}

	private static function action_upsert_contact( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$email = self::substitute_variables( $data['email'] ?? '', $input );
		if ( $email === '' ) {
			throw new \Exception( 'Email is required' );
		}

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, array() );
		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$properties = self::merge_contact_simple_fields( $properties, $data, $input );

		$properties['email'] = $email;

		$payload = array(
			'inputs' => array(
				array(
					'id'         => $email,
					'idProperty' => 'email',
					'properties' => $properties,
				),
			),
		);

		[ $response ] = self::hubspot_request(
			'POST',
			'/crm/v3/objects/contacts/batch/upsert',
			$access_token,
			$payload
		);

		$contact_id = '';
		if ( isset( $response['results'][0]['id'] ) ) {
			$contact_id = (string) $response['results'][0]['id'];
		}

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_contact_id' => $contact_id,
				'hubspot_email'      => $email,
			),
		);
	}

	private static function action_upsert_company( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$company_id = self::substitute_variables( $data['company_id'] ?? '', $input );
		$properties = self::parse_json_field( $data['properties'] ?? '', $input, array() );
		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$properties = self::merge_company_simple_fields( $properties, $data, $input );

		if ( empty( $properties ) ) {
			throw new \Exception( 'Company data is required' );
		}

		if ( $company_id !== '' ) {
			[ $response ] = self::hubspot_request(
				'PATCH',
				'/crm/v3/objects/companies/' . rawurlencode( $company_id ),
				$access_token,
				array( 'properties' => $properties )
			);
		} else {
			[ $response ] = self::hubspot_request(
				'POST',
				'/crm/v3/objects/companies',
				$access_token,
				array( 'properties' => $properties )
			);
		}

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_company_id' => $response['id'] ?? $company_id,
			),
		);
	}

	private static function action_upsert_deal( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$deal_id = self::substitute_variables( $data['deal_id'] ?? '', $input );
		$properties = self::parse_json_field( $data['properties'] ?? '', $input, array() );
		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$properties = self::merge_deal_simple_fields( $properties, $data, $input );

		if ( empty( $properties ) ) {
			throw new \Exception( 'Deal data is required' );
		}

		if ( $deal_id !== '' ) {
			[ $response ] = self::hubspot_request(
				'PATCH',
				'/crm/v3/objects/deals/' . rawurlencode( $deal_id ),
				$access_token,
				array( 'properties' => $properties )
			);
		} else {
			[ $response ] = self::hubspot_request(
				'POST',
				'/crm/v3/objects/deals',
				$access_token,
				array( 'properties' => $properties )
			);
		}

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_deal_id' => $response['id'] ?? $deal_id,
			),
		);
	}

	private static function action_find_contact( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$email = self::substitute_variables( $data['email'] ?? '', $input );
		if ( $email === '' ) {
			throw new \Exception( 'Email is required' );
		}

		$properties = self::substitute_variables( $data['properties'] ?? '', $input );
		$payload = array(
			'filterGroups' => array(
				array(
					'filters' => array(
						array(
							'propertyName' => 'email',
							'operator'     => 'EQ',
							'value'        => $email,
						),
					),
				),
			),
			'limit' => 1,
		);

		if ( $properties !== '' ) {
			$payload['properties'] = array_map( 'trim', explode( ',', $properties ) );
		}

		[ $response ] = self::hubspot_request(
			'POST',
			'/crm/v3/objects/contacts/search',
			$access_token,
			$payload
		);

		$contact = $response['results'][0] ?? array();

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_contact_id' => $contact['id'] ?? '',
				'hubspot_contact'    => $contact,
			),
		);
	}

	private static function action_update_list_memberships( array $node, array $input,  $access_token, string $mode ): array {
		$data = self::get_node_config_data( $node );

		$list_id = self::substitute_variables( $data['list_id'] ?? '', $input );
		if ( $list_id === '' ) {
			throw new \Exception( 'List ID is required' );
		}

		$contact_ids = self::resolve_contact_ids( $data, $input, $access_token );
		if ( empty( $contact_ids ) ) {
			throw new \Exception( 'At least one contact ID or email is required' );
		}

		$path = '/crm/v3/lists/' . rawurlencode( $list_id ) . '/memberships/' . ( $mode === 'remove' ? 'remove' : 'add' );

		[ $response ] = self::hubspot_request(
			'PUT',
			$path,
			$access_token,
			array_values( $contact_ids )
		);

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_list_id' => $list_id,
				'hubspot_list_mode' => $mode,
				'hubspot_list_contacts' => array_values( $contact_ids ),
				'hubspot_list_response' => $response,
			),
		);
	}

	private static function action_submit_form( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$portal_id = self::substitute_variables( $data['portal_id'] ?? '', $input );
		$form_guid = self::substitute_variables( $data['form_guid'] ?? '', $input );

		if ( $portal_id === '' ) {
			throw new \Exception( 'Portal ID is required' );
		}

		if ( $form_guid === '' ) {
			throw new \Exception( 'Form GUID is required' );
		}

		$fields = self::parse_form_fields( $data['fields'] ?? '', $input );
		if ( empty( $fields ) ) {
			$fields = self::build_simple_form_fields( $data, $input );
		}
		if ( empty( $fields ) ) {
			throw new \Exception( 'Fields are required' );
		}

		$payload = array(
			'fields' => $fields,
		);

		$context = self::parse_json_field( $data['context'] ?? '', $input, array() );
		if ( ! empty( $context ) ) {
			$payload['context'] = $context;
		}

		$legal = self::parse_json_field( $data['legal_consent_options'] ?? '', $input, array() );
		if ( ! empty( $legal ) ) {
			$payload['legalConsentOptions'] = $legal;
		}

		$submitted_at = self::substitute_variables( $data['submitted_at'] ?? '', $input );
		if ( $submitted_at !== '' ) {
			$payload['submittedAt'] = $submitted_at;
		}

		$skip_validation = self::substitute_variables( $data['skip_validation'] ?? '', $input );
		if ( $skip_validation !== '' ) {
			$payload['skipValidation'] = $skip_validation === 'true';
		}

		$path = '/submissions/v3/integration/secure/submit/' . rawurlencode( $portal_id ) . '/' . rawurlencode( $form_guid );

		[ $response ] = self::forms_request( 'POST', $path, $access_token, $payload );

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_form_portal_id' => $portal_id,
				'hubspot_form_guid'      => $form_guid,
				'hubspot_form_response'  => $response,
			),
		);
	}


	private static function hubspot_request(  $method,  $path,  $access_token, ?array $body = null ): array {
		$url = self::API_BASE_URL . $path;

		$headers = array(
			'Authorization' => 'Bearer ' . $access_token,
			'Accept'        => 'application/json',
		);

		$args = array(
			'headers' => $headers,
		);

		if ( $body !== null ) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$args['headers'] = $headers;
			$args['body'] = wp_json_encode( $body );
		}

		[ $response_body, $status ] = self::http_request( $method, $url, $args );

		if ( $status >= 400 || ( isset( $response_body['status'] ) && (int) $response_body['status'] >= 400 ) ) {
			$detail = $response_body['message'] ?? $response_body['error'] ?? 'Unknown error';
			throw new \Exception( 'HubSpot API error: ' . $detail );
		}

		return array( $response_body, $status );
	}

	private static function forms_request(  $method,  $path,  $access_token, ?array $body = null ): array {
		$url = self::FORMS_BASE_URL . $path;

		$headers = array(
			'Authorization' => 'Bearer ' . $access_token,
			'Accept'        => 'application/json',
		);

		$args = array(
			'headers' => $headers,
		);

		if ( $body !== null ) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$args['headers'] = $headers;
			$args['body'] = wp_json_encode( $body );
		}

		[ $response_body, $status ] = self::http_request( $method, $url, $args );

		if ( $status >= 400 || ( isset( $response_body['status'] ) && (int) $response_body['status'] >= 400 ) ) {
			$detail = $response_body['message'] ?? $response_body['error'] ?? 'Unknown error';
			throw new \Exception( 'HubSpot Forms API error: ' . $detail );
		}

		return array( $response_body, $status );
	}

	private static function resolve_access_token( array $node, array $input, ?array $credentials ) {
		$access_token = '';
		if ( is_array( $credentials ) ) {
			$access_token = trim( $credentials['access_token'] ?? '' );
		}

		if ( $access_token === '' ) {
			$data = self::get_node_config_data( $node );
			$access_token = self::substitute_variables( trim( $data['access_token'] ?? '' ), $input );
		}

		if ( $access_token === '' ) {
			throw new \Exception( 'HubSpot access token is missing. Provide a Private App token in the action or create a connection.' );
		}

		return $access_token;
	}

	private static function resolve_dynamic_access_token( array $q ): string {
		$access_token = trim( (string) ( $q['access_token'] ?? '' ) );
		if ( $access_token === '' && isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$access_token = trim( (string) ( $q['where']['access_token'] ?? '' ) );
		}

		if ( $access_token !== '' ) {
			return $access_token;
		}

		$connection_id = null;
		if ( isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$connection_id = $q['where']['connection_id'] ?? null;
		}
		if ( $connection_id === null && isset( $q['connection_id'] ) ) {
			$connection_id = $q['connection_id'];
		}

		if ( $connection_id ) {
			try {
				$manager = new ConnectionManager();
				$credentials = $manager->get_execution_credentials( (int) $connection_id );
				$access_token = trim( (string) ( $credentials['access_token'] ?? '' ) );
				if ( $access_token !== '' ) {
					return $access_token;
				}
			} catch ( \Throwable $e ) {
				// Ignore and fall through.
			}
		}

		if ( function_exists( 'get_current_user_id' ) ) {
			$user_id = (int) get_current_user_id();
			if ( $user_id > 0 ) {
				try {
					$manager = new ConnectionManager();
					$connections = $manager->get_user_connections( $user_id, 'hubspot', 1, 1 );
					$first = $connections['data'][0] ?? null;
					if ( $first && isset( $first['id'] ) ) {
						$credentials = $manager->get_execution_credentials( (int) $first['id'] );
						$access_token = trim( (string) ( $credentials['access_token'] ?? '' ) );
						if ( $access_token !== '' ) {
							return $access_token;
						}
					}
				} catch ( \Throwable $e ) {
					// Ignore and fall through.
				}
			}
		}

		return '';
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
		if ( $search === '' ) {
			return true;
		}
		return stripos( $value, $search ) !== false;
	}

	private static function resolve_action( array $node ) {
		if ( isset( $node['config']['action'] ) && is_string( $node['config']['action'] ) ) {
			return $node['config']['action'];
		}

		$data = $node['data'] ?? array();
		if ( isset( $data['event'] ) && is_string( $data['event'] ) ) {
			return $data['event'];
		}

		if ( isset( $data['action'] ) && is_string( $data['action'] ) ) {
			return $data['action'];
		}

		return '';
	}

	private static function get_node_config_data( array $node ): array {
		if ( isset( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
			return $node['config']['data'];
		}

		$data = $node['data'] ?? array();
		if ( isset( $data['config'] ) && is_array( $data['config'] ) ) {
			return $data['config'];
		}

		if ( isset( $node['config'] ) && is_array( $node['config'] ) && ! isset( $node['config']['action'] ) ) {
			return $node['config'];
		}

		return array();
	}

	private static function parse_json_field( $raw, array $input, $default ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( $raw === '' ) {
			return $default;
		}

		$raw = self::substitute_variables( $raw, $input );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			throw new \Exception( 'Invalid JSON provided.' );
		}

		return $decoded;
	}

	private static function parse_form_fields( $raw, array $input ): array {
		if ( is_array( $raw ) ) {
			return self::normalize_form_fields( $raw );
		}

		$raw = self::substitute_variables( trim( (string) $raw ), $input );
		if ( $raw === '' ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			throw new \Exception( 'Fields must be valid JSON (object or array)' );
		}

		return self::normalize_form_fields( $decoded );
	}

	private static function normalize_form_fields( array $fields ): array {
		$is_list = array_keys( $fields ) === range( 0, count( $fields ) - 1 );
		if ( $is_list ) {
			$out = array();
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				if ( empty( $field['name'] ) ) {
					continue;
				}
				$out[] = $field;
			}
			return $out;
		}

		$out = array();
		foreach ( $fields as $name => $value ) {
			$out[] = array(
				'name'  => (string) $name,
				'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
			);
		}

		return $out;
	}

	private static function merge_contact_simple_fields( array $properties, array $data, array $input ): array {
		$fields = array(
			'firstname' => 'firstname',
			'lastname'  => 'lastname',
			'phone'     => 'phone',
		);

		foreach ( $fields as $key => $prop ) {
			$value = self::substitute_variables( $data[ $key ] ?? '', $input );
			if ( $value !== '' ) {
				$properties[ $prop ] = $value;
			}
		}

		return $properties;
	}

	private static function merge_company_simple_fields( array $properties, array $data, array $input ): array {
		$fields = array(
			'name'    => 'name',
			'domain'  => 'domain',
			'phone'   => 'phone',
			'website' => 'website',
		);

		foreach ( $fields as $key => $prop ) {
			$value = self::substitute_variables( $data[ $key ] ?? '', $input );
			if ( $value !== '' ) {
				$properties[ $prop ] = $value;
			}
		}

		return $properties;
	}

	private static function merge_deal_simple_fields( array $properties, array $data, array $input ): array {
		$fields = array(
			'dealname'  => 'dealname',
			'amount'    => 'amount',
			'dealstage' => 'dealstage',
			'pipeline'  => 'pipeline',
			'closedate' => 'closedate',
		);

		foreach ( $fields as $key => $prop ) {
			$value = self::substitute_variables( $data[ $key ] ?? '', $input );
			if ( $value !== '' ) {
				$properties[ $prop ] = $value;
			}
		}

		return $properties;
	}

	private static function build_simple_form_fields( array $data, array $input ): array {
		$map = array(
			'email'     => 'email',
			'firstname' => 'firstname',
			'lastname'  => 'lastname',
			'phone'     => 'phone',
		);

		$fields = array();
		foreach ( $map as $key => $name ) {
			$value = self::substitute_variables( $data[ $key ] ?? '', $input );
			if ( $value === '' ) {
				continue;
			}
			$fields[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}

		return $fields;
	}

	private static function resolve_contact_ids( array $data, array $input,  $access_token ): array {
		$ids = self::parse_csv_list( self::substitute_variables( $data['contact_ids'] ?? '', $input ) );
		$emails = self::parse_csv_list( self::substitute_variables( $data['emails'] ?? '', $input ) );

		$resolved_ids = array();
		foreach ( $ids as $id ) {
			$resolved_ids[ $id ] = (string) $id;
		}

		$missing = array();
		foreach ( $emails as $email ) {
			if ( ! is_email( $email ) ) {
				$missing[] = $email;
				continue;
			}
			$contact_id = self::lookup_contact_id_by_email( $email, $access_token );
			if ( $contact_id === '' ) {
				$missing[] = $email;
				continue;
			}
			$resolved_ids[ $contact_id ] = (string) $contact_id;
		}

		if ( ! empty( $missing ) ) {
			throw new \Exception( 'Contacts not found for emails: ' . implode( ', ', $missing ) );
		}

		return $resolved_ids;
	}

	private static function lookup_contact_id_by_email( string $email,  $access_token ): string {
		$payload = array(
			'filterGroups' => array(
				array(
					'filters' => array(
						array(
							'propertyName' => 'email',
							'operator'     => 'EQ',
							'value'        => $email,
						),
					),
				),
			),
			'limit' => 1,
		);

		[ $response ] = self::hubspot_request(
			'POST',
			'/crm/v3/objects/contacts/search',
			$access_token,
			$payload
		);

		$contact = $response['results'][0] ?? array();
		return isset( $contact['id'] ) ? (string) $contact['id'] : '';
	}

	private static function parse_csv_list( string $raw ): array {
		if ( trim( $raw ) === '' ) {
			return array();
		}

		$items = array_map( 'trim', explode( ',', $raw ) );
		$items = array_filter( $items, static function ( $item ) {
			return $item !== '';
		} );

		return array_values( $items );
	}

	private static function substitute_variables(  $text, array $data )  {
		return preg_replace_callback(
			'/\{\{([^}]+)\}\}/',
			function ( $matches ) use ( $data ) {
				$key = trim( $matches[1] );
				$keys = explode( '.', $key );
				$value = $data;

				foreach ( $keys as $k ) {
					if ( is_array( $value ) && isset( $value[ $k ] ) ) {
						$value = $value[ $k ];
					} else {
						return $matches[0];
					}
				}

				return is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			},
			$text
		);
	}
}
