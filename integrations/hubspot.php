<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

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
			'create_contact' => array( 'label' => 'Create Contact' ),
			'update_contact' => array( 'label' => 'Update Contact (by Email)' ),
			'upsert_contact' => array( 'label' => 'Upsert Contact (by Email)' ),
			'get_contact'    => array( 'label' => 'Get Contact (by ID)' ),
			'find_contact'   => array( 'label' => 'Find Contact (by Email)' ),
			'create_deal'    => array( 'label' => 'Create Deal' ),
			'update_deal'    => array( 'label' => 'Update Deal (by ID)' ),
			'create_company' => array( 'label' => 'Create Company' ),
			'update_company' => array( 'label' => 'Update Company (by ID)' ),
			'submit_form'    => array( 'label' => 'Submit Form' ),
			'update_subscription_status' => array( 'label' => 'Update Email Subscription Status' ),
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

		if ( $action === 'create_contact' ) {
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
					'key'         => 'properties',
					'label'       => 'Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"phone":"+18884827768","company":"Acme Inc"}',
					'help'        => 'Optional JSON object of contact properties to set.',
				),
			) );
		}

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
					'key'         => 'properties',
					'label'       => 'Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"jobtitle":"Manager","phone":"+18884827768"}',
					'required'    => true,
					'help'        => 'JSON object of properties to update.',
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
					'key'         => 'properties',
					'label'       => 'Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"firstname":"Jane","lastname":"Doe"}',
					'required'    => true,
					'help'        => 'JSON object of properties to set or update.',
				),
			) );
		}

		if ( $action === 'get_contact' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'contact_id',
					'label'       => 'Contact ID',
					'type'        => 'text',
					'placeholder' => '12345',
					'required'    => true,
				),
				array(
					'key'         => 'properties',
					'label'       => 'Properties (comma-separated)',
					'type'        => 'text',
					'placeholder' => 'email,firstname,lastname,phone',
					'help'        => 'Optional list of properties to return.',
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

		if ( $action === 'create_deal' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'properties',
					'label'       => 'Deal Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"dealname":"New deal","dealstage":"contractsent","pipeline":"default","amount":"1500.00"}',
					'required'    => true,
					'help'        => 'Include at least dealname and dealstage. Add pipeline if you use multiple pipelines.',
				),
				array(
					'key'         => 'associations',
					'label'       => 'Associations (JSON)',
					'type'        => 'textarea',
					'placeholder' => '[{"to":{"id":123},"types":[{"associationCategory":"HUBSPOT_DEFINED","associationTypeId":5}]}]',
					'help'        => 'Optional associations array to link records (contacts, companies, etc.).',
				),
			) );
		}

		if ( $action === 'update_deal' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'deal_id',
					'label'       => 'Deal ID',
					'type'        => 'text',
					'placeholder' => '12345',
					'required'    => true,
				),
				array(
					'key'         => 'properties',
					'label'       => 'Deal Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"dealname":"Updated deal","amount":"2000.00"}',
					'required'    => true,
				),
			) );
		}

		if ( $action === 'create_company' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'properties',
					'label'       => 'Company Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"name":"Acme Inc","domain":"acme.com"}',
					'required'    => true,
				),
			) );
		}

		if ( $action === 'update_company' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'company_id',
					'label'       => 'Company ID',
					'type'        => 'text',
					'placeholder' => '12345',
					'required'    => true,
				),
				array(
					'key'         => 'properties',
					'label'       => 'Company Properties (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"name":"Acme Inc","phone":"+18884827768"}',
					'required'    => true,
				),
			) );
		}

		if ( $action === 'submit_form' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'portal_id',
					'label'       => 'Portal ID',
					'type'        => 'text',
					'placeholder' => '123456',
					'required'    => true,
				),
				array(
					'key'         => 'form_guid',
					'label'       => 'Form GUID',
					'type'        => 'text',
					'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
					'required'    => true,
				),
				array(
					'key'         => 'fields',
					'label'       => 'Fields (JSON)',
					'type'        => 'textarea',
					'placeholder' => '{"email":"name@example.com","firstname":"John"}',
					'required'    => true,
					'help'        => 'JSON object (field name to value) or array of field objects.',
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

		if ( $action === 'update_subscription_status' ) {
			return array_merge( $auth, array(
				array(
					'key'         => 'email',
					'label'       => 'Email',
					'type'        => 'text',
					'placeholder' => 'name@example.com or {{email}}',
					'required'    => true,
				),
				array(
					'key'         => 'subscription_id',
					'label'       => 'Subscription ID',
					'type'        => 'text',
					'placeholder' => '123',
					'required'    => true,
				),
				array(
					'key'      => 'status_state',
					'label'    => 'Status',
					'type'     => 'select',
					'options'  => array(
						array( 'value' => 'SUBSCRIBED', 'label' => 'Subscribed' ),
						array( 'value' => 'UNSUBSCRIBED', 'label' => 'Unsubscribed' ),
						array( 'value' => 'NOT_SPECIFIED', 'label' => 'Not Specified' ),
					),
					'required' => true,
				),
				array(
					'key'     => 'legal_basis',
					'label'   => 'Legal Basis',
					'type'    => 'select',
					'options' => array(
						array( 'value' => '', 'label' => 'Leave Empty' ),
						array( 'value' => 'CONSENT_WITH_NOTICE', 'label' => 'Consent With Notice' ),
						array( 'value' => 'LEGITIMATE_INTEREST_CLIENT', 'label' => 'Legitimate Interest (Client)' ),
						array( 'value' => 'LEGITIMATE_INTEREST_OTHER', 'label' => 'Legitimate Interest (Other)' ),
						array( 'value' => 'LEGITIMATE_INTEREST_PQL', 'label' => 'Legitimate Interest (PQL)' ),
						array( 'value' => 'NON_GDPR', 'label' => 'Non GDPR' ),
						array( 'value' => 'PERFORMANCE_OF_CONTRACT', 'label' => 'Performance of Contract' ),
						array( 'value' => 'PROCESS_AND_STORE', 'label' => 'Process and Store' ),
					),
				),
				array(
					'key'         => 'legal_basis_explanation',
					'label'       => 'Legal Basis Explanation',
					'type'        => 'text',
					'placeholder' => 'Consent collected via website form',
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

		if ( $action === 'create_contact' ) {
			return self::action_create_contact( $node, $input, $access_token );
		}

		if ( $action === 'update_contact' ) {
			return self::action_update_contact( $node, $input, $access_token );
		}

		if ( $action === 'upsert_contact' ) {
			return self::action_upsert_contact( $node, $input, $access_token );
		}

		if ( $action === 'create_deal' ) {
			return self::action_create_deal( $node, $input, $access_token );
		}

		if ( $action === 'get_contact' ) {
			return self::action_get_contact( $node, $input, $access_token );
		}

		if ( $action === 'find_contact' ) {
			return self::action_find_contact( $node, $input, $access_token );
		}

		if ( $action === 'update_deal' ) {
			return self::action_update_deal( $node, $input, $access_token );
		}

		if ( $action === 'create_company' ) {
			return self::action_create_company( $node, $input, $access_token );
		}

		if ( $action === 'update_company' ) {
			return self::action_update_company( $node, $input, $access_token );
		}

		if ( $action === 'submit_form' ) {
			return self::action_submit_form( $node, $input, $access_token );
		}

		if ( $action === 'update_subscription_status' ) {
			return self::action_update_subscription_status( $node, $input, $access_token );
		}

		return array(
			'port' => 'main',
			'data' => $input,
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

	private static function action_create_contact( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$email = self::substitute_variables( $data['email'] ?? '', $input );
		if ( $email === '' ) {
			throw new \Exception( 'Email is required' );
		}

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, array() );
		$firstname = self::substitute_variables( $data['firstname'] ?? '', $input );
		$lastname  = self::substitute_variables( $data['lastname'] ?? '', $input );

		$properties['email'] = $email;
		if ( $firstname !== '' ) {
			$properties['firstname'] = $firstname;
		}
		if ( $lastname !== '' ) {
			$properties['lastname'] = $lastname;
		}

		[ $response ] = self::hubspot_request(
			'POST',
			'/crm/v3/objects/contacts',
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

	private static function action_update_contact( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$email = self::substitute_variables( $data['email'] ?? '', $input );
		if ( $email === '' ) {
			throw new \Exception( 'Email is required' );
		}

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, null );
		if ( empty( $properties ) ) {
			throw new \Exception( 'Properties are required' );
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

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, null );
		if ( empty( $properties ) ) {
			throw new \Exception( 'Properties are required' );
		}

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

	private static function action_create_deal( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, null );
		if ( empty( $properties ) ) {
			throw new \Exception( 'Deal properties are required' );
		}

		$associations = self::parse_json_field( $data['associations'] ?? '', $input, array() );

		$payload = array( 'properties' => $properties );
		if ( ! empty( $associations ) ) {
			$payload['associations'] = $associations;
		}

		[ $response ] = self::hubspot_request(
			'POST',
			'/crm/v3/objects/deals',
			$access_token,
			$payload
		);

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_deal_id' => $response['id'] ?? '',
			),
		);
	}

	private static function action_get_contact( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$contact_id = self::substitute_variables( $data['contact_id'] ?? '', $input );
		if ( $contact_id === '' ) {
			throw new \Exception( 'Contact ID is required' );
		}

		$properties = self::substitute_variables( $data['properties'] ?? '', $input );
		$path = '/crm/v3/objects/contacts/' . rawurlencode( $contact_id );
		if ( $properties !== '' ) {
			$path .= '?properties=' . rawurlencode( $properties );
		}

		[ $response ] = self::hubspot_request( 'GET', $path, $access_token );

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_contact_id' => $response['id'] ?? $contact_id,
				'hubspot_contact'    => $response,
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

	private static function action_update_deal( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$deal_id = self::substitute_variables( $data['deal_id'] ?? '', $input );
		if ( $deal_id === '' ) {
			throw new \Exception( 'Deal ID is required' );
		}

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, null );
		if ( empty( $properties ) ) {
			throw new \Exception( 'Deal properties are required' );
		}

		[ $response ] = self::hubspot_request(
			'PATCH',
			'/crm/v3/objects/deals/' . rawurlencode( $deal_id ),
			$access_token,
			array( 'properties' => $properties )
		);

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_deal_id' => $response['id'] ?? $deal_id,
			),
		);
	}

	private static function action_create_company( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, null );
		if ( empty( $properties ) ) {
			throw new \Exception( 'Company properties are required' );
		}

		[ $response ] = self::hubspot_request(
			'POST',
			'/crm/v3/objects/companies',
			$access_token,
			array( 'properties' => $properties )
		);

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_company_id' => $response['id'] ?? '',
			),
		);
	}

	private static function action_update_company( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$company_id = self::substitute_variables( $data['company_id'] ?? '', $input );
		if ( $company_id === '' ) {
			throw new \Exception( 'Company ID is required' );
		}

		$properties = self::parse_json_field( $data['properties'] ?? '', $input, null );
		if ( empty( $properties ) ) {
			throw new \Exception( 'Company properties are required' );
		}

		[ $response ] = self::hubspot_request(
			'PATCH',
			'/crm/v3/objects/companies/' . rawurlencode( $company_id ),
			$access_token,
			array( 'properties' => $properties )
		);

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_company_id' => $response['id'] ?? $company_id,
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

	private static function action_update_subscription_status( array $node, array $input,  $access_token ): array {
		$data = self::get_node_config_data( $node );

		$email = self::substitute_variables( $data['email'] ?? '', $input );
		if ( $email === '' ) {
			throw new \Exception( 'Email is required' );
		}

		$subscription_id = (int) self::substitute_variables( $data['subscription_id'] ?? '', $input );
		if ( $subscription_id <= 0 ) {
			throw new \Exception( 'Subscription ID is required' );
		}

		$status_state = strtoupper( self::substitute_variables( $data['status_state'] ?? '', $input ) );
		if ( $status_state === '' ) {
			throw new \Exception( 'Status is required' );
		}

		$payload = array(
			'channel'        => 'EMAIL',
			'statusState'    => $status_state,
			'subscriptionId' => $subscription_id,
		);

		$legal_basis = self::substitute_variables( $data['legal_basis'] ?? '', $input );
		if ( $legal_basis !== '' ) {
			$payload['legalBasis'] = $legal_basis;
		}

		$legal_explanation = self::substitute_variables( $data['legal_basis_explanation'] ?? '', $input );
		if ( $legal_explanation !== '' ) {
			$payload['legalBasisExplanation'] = $legal_explanation;
		}

		[ $response ] = self::hubspot_request(
			'POST',
			'/communication-preferences/v4/statuses/' . rawurlencode( $email ),
			$access_token,
			$payload
		);

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_subscription_email'  => $email,
				'hubspot_subscription_id'     => $subscription_id,
				'hubspot_subscription_status' => $status_state,
				'hubspot_subscription_result' => $response,
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
