<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hubspot extends IntegrationBase {

	private const FORMS_BASE_URL = 'https://api.hsforms.com';

	public static function get_slug(): string {
		return 'hubspot';
	}

	public static function get_name(): string {
		return 'HubSpot';
	}

	public static function get_icon(): string {
		return 'hubspot.svg';
	}

	public static function get_actions(): array {
		return array(
			'submit_form' => array( 'label' => 'Submit Form' ),
		);
	}

	public static function get_action_config_schema( $action ): array {
		if ( 'submit_form' === $action ) {
			return array(
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
						'select'      => array( 'id', 'label' ),
					),
					'required'    => true,
					'help'        => 'Shows HubSpot forms already embedded on this WordPress site through the official HubSpot plugin.',
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
						array(
							'value' => '',
							'label' => 'Default',
						),
						array(
							'value' => 'true',
							'label' => 'True',
						),
						array(
							'value' => 'false',
							'label' => 'False',
						),
					),
				),
			);
		}//end if

		return array();
	}

	public static function execute_node( array $node, array $input ): array {
		$action = self::resolve_action( $node );

		if ( '' === $action ) {
			throw new \Exception( 'HubSpot action type is missing' );
		}

		if ( 'submit_form' === $action ) {
			return self::action_submit_form( $node, $input );
		}

		return array(
			'port' => 'main',
			'data' => $input,
		);
	}

	public static function get_dynamic_queries(): array {
		return array(
			'portals' => array( self::class, 'query_portals' ),
			'forms'   => array( self::class, 'query_forms' ),
		);
	}

	public static function query_portals( $q ) {
		unset( $q );

		$hub_id = self::resolve_connected_portal_id();
		if ( '' === $hub_id ) {
			return array();
		}

		return array(
			array(
				'id'    => (string) $hub_id,
				'label' => 'Hub ID ' . $hub_id,
			),
		);
	}

	public static function query_forms( $q ) {
		$q = is_array( $q ) ? $q : array();

		$portal_id = '';
		if ( isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$portal_id = trim( (string) ( $q['where']['portal_id'] ?? $q['where']['portalId'] ?? '' ) );
		}

		if ( '' === $portal_id ) {
			$portal_id = self::resolve_connected_portal_id();
		}

		if ( '' === $portal_id ) {
			return array();
		}

		$search = self::normalize_dynamic_search( $q );
		$limit  = self::normalize_dynamic_limit( $q );
		$forms  = self::discover_embedded_forms( $portal_id );
		$items  = array();

		foreach ( $forms as $form ) {
			$label = (string) ( $form['label'] ?? $form['id'] ?? '' );
			$id    = (string) ( $form['id'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			if ( '' !== $search && ! self::matches_dynamic_search( $search, $label . ' ' . $id ) ) {
				continue;
			}

			$items[] = array(
				'id'       => $id,
				'label'    => $label,
				'portalId' => $portal_id,
			);
		}

		return array_slice( $items, 0, $limit );
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_auth_type(): string {
		return 'none';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return array();
	}

	public static function test_connection( array $credentials ): array {
		$portal_id = self::resolve_connected_portal_id();

		return array(
			'success' => '' !== $portal_id,
			'message' => '' !== $portal_id ? 'Connected HubSpot WordPress plugin detected' : 'HubSpot WordPress plugin is not connected',
			'details' => array(
				'portal_id' => $portal_id,
			),
		);
	}

	private static function action_submit_form( array $node, array $input ): array {
		$data = self::get_node_config_data( $node );

		$portal_id = self::substitute_variables( $data['portal_id'] ?? '', $input );
		$form_guid = self::substitute_variables( $data['form_guid'] ?? '', $input );

		if ( '' === $portal_id ) {
			$portal_id = self::resolve_connected_portal_id();
		}

		if ( '' === $portal_id ) {
			throw new \Exception( 'Portal ID is required. Connect the official HubSpot WordPress plugin first or enter a portal ID manually.' );
		}

		if ( '' === $form_guid ) {
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
		if ( '' !== $submitted_at ) {
			$payload['submittedAt'] = $submitted_at;
		}

		$skip_validation = self::substitute_variables( $data['skip_validation'] ?? '', $input );
		if ( '' !== $skip_validation ) {
			$payload['skipValidation'] = 'true' === $skip_validation;
		}

		$path = '/submissions/v3/integration/submit/' . rawurlencode( $portal_id ) . '/' . rawurlencode( $form_guid );

		[ $response ] = self::public_forms_request( 'POST', $path, $payload );

		return array(
			'port' => 'main',
			'data' => array(
				'hubspot_form_portal_id' => $portal_id,
				'hubspot_form_guid'      => $form_guid,
				'hubspot_form_response'  => $response,
			),
		);
	}

	private static function public_forms_request( $method, $path, ?array $body = null ): array {
		$url = self::FORMS_BASE_URL . $path;

		$headers = array(
			'Accept' => 'application/json',
		);

		$args = array(
			'headers' => $headers,
		);

		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$args['headers'] = $headers;
			$args['body'] = wp_json_encode( $body );
		}

		[ $response_body, $status ] = self::http_request( $method, $url, $args );

		if ( $status >= 400 || ( isset( $response_body['status'] ) && (int) $response_body['status'] >= 400 ) ) {
			$detail = $response_body['message'] ?? $response_body['error'] ?? 'Unknown error';
			throw new \Exception( sprintf( 'HubSpot Forms API error (%d): %s', (int) $status, $detail ) );
		}

		return array( $response_body, $status );
	}

	private static function resolve_connected_portal_id(): string {
		if ( class_exists( '\Leadin\data\Portal_Options' ) ) {
			try {
				$portal_id = \Leadin\data\Portal_Options::get_portal_id();
				if ( ! empty( $portal_id ) ) {
					return trim( (string) $portal_id );
				}
			} catch ( \Throwable $e ) {
				$portal_id = '';
				unset( $e );
			}
		}

		$portal_id = get_option( 'leadin_portalId', '' );
		if ( ! empty( $portal_id ) ) {
			return trim( (string) $portal_id );
		}

		return '';
	}

	private static function normalize_dynamic_limit( array $q ): int {
		$limit = (int) ( $q['limit'] ?? 20 );

		if ( 1 > $limit ) {
			$limit = 20;
		}

		if ( 200 < $limit ) {
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

		return false !== stripos( $value, $search );
	}

	private static function discover_embedded_forms( string $portal_id ): array {
		static $cache = array();

		if ( isset( $cache[ $portal_id ] ) ) {
			return $cache[ $portal_id ];
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return array();
		}

		$sources = array();
		$queries = array(
			"SELECT post_content AS content FROM {$wpdb->prefix}posts WHERE post_content LIKE '%[hubspot%' OR post_content LIKE '%formId%'",
			"SELECT meta_value AS content FROM {$wpdb->prefix}postmeta WHERE meta_value LIKE '%hubspot%' OR meta_value LIKE '%formId%'",
			"SELECT option_value AS content FROM {$wpdb->prefix}options WHERE option_value LIKE '%hubspot%' OR option_value LIKE '%formId%'",
		);

		foreach ( $queries as $query ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static internal discovery query with no user input.
			$rows = $wpdb->get_results( $query, ARRAY_A );
			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$content = (string) ( $row['content'] ?? '' );
				if ( '' !== $content ) {
					$sources[] = $content;
				}
			}
		}

		$forms = array();
		foreach ( $sources as $source ) {
			self::collect_embedded_forms_from_value( $source, $forms );
		}

		$forms = array_filter(
			$forms,
			static function ( array $form ) use ( $portal_id ): bool {
				return (string) ( $form['portal_id'] ?? '' ) === $portal_id;
			}
		);

		$cache[ $portal_id ] = array_values( $forms );

		return $cache[ $portal_id ];
	}

	private static function collect_embedded_forms_from_value( $value, array &$forms ): void {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}

		if ( is_array( $value ) ) {
			self::collect_form_from_structured_value( $value, $forms );

			foreach ( $value as $nested_value ) {
				self::collect_embedded_forms_from_value( $nested_value, $forms );
			}

			return;
		}

		if ( ! is_string( $value ) ) {
			return;
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return;
		}

		self::collect_forms_from_shortcode_string( $value, $forms );

		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			self::collect_embedded_forms_from_value( $decoded, $forms );
		}

		$unserialized = maybe_unserialize( $value );
		if ( $unserialized !== $value ) {
			self::collect_embedded_forms_from_value( $unserialized, $forms );
		}

		$unslashed_value = stripslashes( $value );
		if ( $unslashed_value !== $value ) {
			self::collect_forms_from_shortcode_string( $unslashed_value, $forms );

			$decoded = json_decode( $unslashed_value, true );
			if ( is_array( $decoded ) ) {
				self::collect_embedded_forms_from_value( $decoded, $forms );
			}
		}
	}

	private static function collect_form_from_structured_value( array $value, array &$forms ): void {
		$form_id = isset( $value['formId'] ) ? trim( (string) $value['formId'] ) : '';
		if ( '' === $form_id ) {
			return;
		}

		$portal_id = isset( $value['portalId'] ) ? trim( (string) $value['portalId'] ) : '';
		$label     = isset( $value['formName'] ) ? trim( (string) $value['formName'] ) : $form_id;

		self::store_discovered_form( $forms, $form_id, $portal_id, $label );
	}

	private static function collect_forms_from_shortcode_string( string $value, array &$forms ): void {
		if ( false === stripos( $value, '[hubspot' ) ) {
			return;
		}

		if ( ! preg_match_all( '/\[hubspot[^\]]+\]/i', $value, $matches ) ) {
			return;
		}

		foreach ( $matches[0] as $shortcode ) {
			$type = self::extract_shortcode_attribute( $shortcode, 'type' );
			if ( '' !== $type && 'form' !== strtolower( $type ) ) {
				continue;
			}

			$form_id   = self::extract_shortcode_attribute( $shortcode, 'id' );
			$portal_id = self::extract_shortcode_attribute( $shortcode, 'portal' );

			self::store_discovered_form( $forms, $form_id, $portal_id, $form_id );
		}
	}

	private static function extract_shortcode_attribute( string $shortcode, string $attribute ): string {
		$pattern = '/\b' . preg_quote( $attribute, '/' ) . '=(["\'])([^"\']+)\1/i';

		if ( ! preg_match( $pattern, $shortcode, $matches ) ) {
			return '';
		}

		return trim( (string) $matches[2] );
	}

	private static function store_discovered_form( array &$forms, string $form_id, string $portal_id, string $label ): void {
		$form_id = trim( $form_id );

		if ( '' === $form_id ) {
			return;
		}

		if ( '' === $portal_id ) {
			$portal_id = self::resolve_connected_portal_id();
		}

		if ( '' === $portal_id ) {
			return;
		}

		if ( ! isset( $forms[ $form_id ] ) ) {
			$forms[ $form_id ] = array(
				'id'        => $form_id,
				'label'     => '' !== $label ? $label : $form_id,
				'portal_id' => $portal_id,
			);
			return;
		}

		if ( $forms[ $form_id ]['label'] === $form_id && '' !== $label ) {
			$forms[ $form_id ]['label'] = $label;
		}
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
		if ( '' === $raw ) {
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
		if ( '' === $raw ) {
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
			if ( '' === $value ) {
				continue;
			}
			$fields[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}

		return $fields;
	}

	private static function substitute_variables( $text, array $data ) {
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
