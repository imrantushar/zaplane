<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailchimp extends IntegrationBase {


	private const API_VERSION = '3.0';

	public static function get_slug(): string {
		return 'mailchimp';
	}

	public static function get_name(): string {
		return 'Mailchimp';
	}

	public static function get_icon(): string {
		return 'mailchimp';
	}

	public static function get_actions(): array {
		return [
			'upsert_subscriber'     => [ 'label' => 'Add/Update Subscriber' ],
			'unsubscribe_subscriber' => [ 'label' => 'Unsubscribe Subscriber' ],
			'add_tags'              => [ 'label' => 'Add Tags to Subscriber' ],
			'remove_tags'           => [ 'label' => 'Remove Tags from Subscriber' ],
			'archive_subscriber'    => [ 'label' => 'Archive Subscriber' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$common = [
			[
				'key'         => 'list_id',
				'label'       => 'Audience/List ID',
				'type'        => 'select',
				'dynamic'     => [
					'integration' => 'mailchimp',
					'query'       => 'lists',
					'select'      => [ 'id', 'name' ],
				],
				'required'    => true,
				'help'        => 'Find it in Mailchimp → Audience → Settings → Audience name and defaults.',
			],
			[
				'key'         => 'email',
				'label'       => 'Email Address',
				'type'        => 'text',
				'placeholder' => 'name@example.com or {{email}}',
				'required'    => true,
			],
		];

		switch ( $action ) {
			case 'upsert_subscriber':
				return array_merge(
					$common,
					array(
						array(
							'key'     => 'subscriber_status',
							'label'   => 'Status (Existing)',
							'type'    => 'select',
							'options' => self::get_status_options(),
							'help'    => 'Applied when the subscriber already exists. Leave blank to keep current status.',
						),
						array(
							'key'     => 'subscriber_status_if_new',
							'label'   => 'Status If New',
							'type'    => 'select',
							'options' => self::get_status_options( false ),
							'help'    => 'Applied when creating a new subscriber (default: subscribed).',
						),
						array(
							'key'         => 'merge_fields',
							'label'       => 'Merge Fields (JSON)',
							'type'        => 'textarea',
							'placeholder' => '{"FNAME":"John","LNAME":"Doe"}',
							'help'        => 'Optional JSON object. Use merge tag keys from Mailchimp (e.g., FNAME, LNAME).',
						),
						array(
							'key'         => 'tags',
							'label'       => 'Tags',
							'type'        => 'text',
							'placeholder' => 'vip, newsletter, webinar',
							'help'        => 'Optional comma-separated tags to apply.',
						),
					)
				);
			case 'add_tags':
			case 'remove_tags':
				return array_merge(
					$common,
					array(
						array(
							'key'         => 'tags',
							'label'       => 'Tags',
							'type'        => 'text',
							'placeholder' => 'vip, newsletter, webinar',
							'required'    => true,
							'help'        => 'Comma-separated tags. Existing tags will be added or removed.',
						),
					)
				);
			case 'unsubscribe_subscriber':
			case 'archive_subscriber':
				return $common;
		}//end switch

		return [];
	}

	/**
	 * =====================================================
	 * DYNAMIC DATA QUERIES (API)
	 * =====================================================
	 */
	public static function get_dynamic_queries(): array {
		return [
			'lists' => [ self::class, 'query_lists' ],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = self::resolve_action( $node );
		if ( '' === $action ) {
			throw new \Exception( 'Mailchimp action type is missing' );
		}

		$method = 'action_' . $action;

		if ( method_exists( static::class, $method ) ) {
			return static::$method( $node, $input );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function query_lists( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$api_key = self::resolve_dynamic_api_key( $q );
		if ( '' === $api_key ) {
			return [];
		}

		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		try {
			[ $response ] = self::mailchimp_request( 'GET', '/lists?count=' . $limit, $api_key );
		} catch ( \Throwable $e ) {
			return [];
		}

		$lists = $response['lists'] ?? [];
		$items = [];

		foreach ( $lists as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}
			$id = $list['id'] ?? '';
			if ( '' === $id ) {
				continue;
			}
			$name = $list['name'] ?? $id;
			if ( ! self::matches_dynamic_search( $search, $name ) ) {
				continue;
			}
			$items[] = [
				'id' => (string) $id,
				'name' => $name,
			];
		}

		return array_slice( $items, 0, $limit );
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_auth_type(): string {
		return 'none';
	}

	public static function get_auth_fields( $auth_type = null ): array {
		return [
			'api_key' => [
				'type'        => 'password',
				'label'       => 'API Key',
				'placeholder' => 'xxxxxxxx-us1',
				'required'    => true,
				'help'        => 'Create a Mailchimp API key in Account → Extras → API keys (ends with -usX).',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$api_key = trim( $credentials['api_key'] ?? '' );
		if ( '' === $api_key ) {
			return [
				'success' => false,
				'message' => 'API key is required',
				'details' => [],
			];
		}

		try {
			[ $body, $status ] = self::mailchimp_request( 'GET', '/', $api_key );
		} catch ( \Throwable $e ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $e->getMessage(),
				'details' => [],
			];
		}

		$account = $body['account_name'] ?? $body['account_id'] ?? 'Mailchimp account';

		return [
			'success' => 200 === $status,
			'message' => 200 === $status ? 'Connected to ' . $account : 'Connection failed',
			'details' => $body,
		];
	}

	private static function resolve_node_api_key( array $node ): string {
		return self::resolve_api_key( $node['_connection_credentials'] ?? null );
	}

	private static function action_upsert_subscriber( array $node, array $input ): array {
		return self::run_upsert_subscriber( $node, $input, self::resolve_node_api_key( $node ) );
	}

	private static function action_unsubscribe_subscriber( array $node, array $input ): array {
		return self::run_unsubscribe_subscriber( $node, $input, self::resolve_node_api_key( $node ) );
	}

	private static function action_add_tags( array $node, array $input ): array {
		return self::run_update_tags( $node, $input, self::resolve_node_api_key( $node ), 'active' );
	}

	private static function action_remove_tags( array $node, array $input ): array {
		return self::run_update_tags( $node, $input, self::resolve_node_api_key( $node ), 'inactive' );
	}

	private static function action_archive_subscriber( array $node, array $input ): array {
		return self::run_archive_subscriber( $node, $input, self::resolve_node_api_key( $node ) );
	}

	private static function build_member_path( string $list_id, string $email, string $suffix = '' ): string {
		$path = '/lists/' . rawurlencode( $list_id ) . '/members/' . md5( strtolower( $email ) );
		if ( '' !== $suffix ) {
			$path .= '/' . ltrim( $suffix, '/' );
		}

		return $path;
	}

	private static function extract_subscriber_statuses( array $data, array $input ): array {
		$status = self::normalize_status_value(
			self::substitute_variables( $data['subscriber_status'] ?? ( $data['status'] ?? '' ), $input )
		);
		$status_if_new = self::normalize_status_value(
			self::substitute_variables( $data['subscriber_status_if_new'] ?? ( $data['status_if_new'] ?? '' ), $input )
		);

		return [ $status, $status_if_new ];
	}

	private static function subscriber_response( string $list_id, string $email, string $status, string $member_id = '' ): array {
		return [
			'port' => 'main',
			'data' => [
				'mailchimp_list_id' => $list_id,
				'mailchimp_email'   => $email,
				'mailchimp_status'  => $status,
				'mailchimp_id'      => $member_id,
			],
		];
	}

	private static function run_upsert_subscriber( array $node, array $input, string $api_key ): array {
		$data = self::get_node_config_data( $node );

		$list_id = self::substitute_variables( $data['list_id'] ?? '', $input );
		$email   = self::substitute_variables( $data['email'] ?? '', $input );

		if ( '' === $list_id ) {
			throw new \Exception( 'Mailchimp list ID is required' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			throw new \Exception( 'A valid email address is required' );
		}

		[ $status, $status_if_new ] = self::extract_subscriber_statuses( $data, $input );
		$merge_fields_raw = $data['merge_fields'] ?? '';
		$tags_raw         = $data['tags'] ?? '';

		$body = [
			'email_address' => $email,
		];

		$body['status_if_new'] = '' !== $status_if_new ? $status_if_new : 'subscribed';

		if ( '' !== $status ) {
			$body['status'] = $status;
		}

		$merge_fields = self::parse_merge_fields( $merge_fields_raw, $input );
		if ( ! empty( $merge_fields ) ) {
			$body['merge_fields'] = $merge_fields;
		}

		[ $response ] = self::mailchimp_request(
			'PUT',
			self::build_member_path( $list_id, $email ),
			$api_key,
			$body
		);

		$tags = self::parse_tags( $tags_raw, $input );
		if ( ! empty( $tags ) ) {
			self::mailchimp_request(
				'POST',
				self::build_member_path( $list_id, $email, 'tags' ),
				$api_key,
				[ 'tags' => $tags ]
			);
		}

		return self::subscriber_response(
			$list_id,
			$response['email_address'] ?? $email,
			$response['status'] ?? ( '' !== $status ? $status : ( '' !== $status_if_new ? $status_if_new : 'subscribed' ) ),
			$response['id'] ?? ''
		);
	}

	private static function run_unsubscribe_subscriber( array $node, array $input, string $api_key ): array {
		$data = self::get_node_config_data( $node );

		[ $list_id, $email ] = self::extract_list_and_email( $data, $input );

		$body = [
			'email_address' => $email,
			'status'        => 'unsubscribed',
			'status_if_new' => 'unsubscribed',
		];

		[ $response ] = self::mailchimp_request(
			'PUT',
			self::build_member_path( $list_id, $email ),
			$api_key,
			$body
		);

		return self::subscriber_response(
			$list_id,
			$response['email_address'] ?? $email,
			$response['status'] ?? 'unsubscribed',
			$response['id'] ?? ''
		);
	}

	private static function run_update_tags( array $node, array $input, string $api_key, string $status ): array {
		$data = self::get_node_config_data( $node );

		[ $list_id, $email ] = self::extract_list_and_email( $data, $input );

		$tags_raw = $data['tags'] ?? '';
		$tags = self::parse_tags( $tags_raw, $input );

		if ( empty( $tags ) ) {
			throw new \Exception( 'At least one tag is required' );
		}

		foreach ( $tags as &$tag ) {
			if ( is_array( $tag ) ) {
				$tag['status'] = $status;
			}
		}
		unset( $tag );

		self::mailchimp_request(
			'POST',
			self::build_member_path( $list_id, $email, 'tags' ),
			$api_key,
			[ 'tags' => $tags ]
		);

		return [
			'port' => 'main',
			'data' => [
				'mailchimp_list_id' => $list_id,
				'mailchimp_email'   => $email,
				'mailchimp_tags'    => array_map(static function ( $tag ) {
					return is_array( $tag ) ? ( $tag['name'] ?? '' ) : '';
				}, $tags),
				'mailchimp_tag_status' => $status,
			],
		];
	}

	private static function run_archive_subscriber( array $node, array $input, string $api_key ): array {
		$data = self::get_node_config_data( $node );

		[ $list_id, $email ] = self::extract_list_and_email( $data, $input );

		self::mailchimp_request(
			'DELETE',
			self::build_member_path( $list_id, $email ),
			$api_key
		);

		return self::subscriber_response( $list_id, $email, 'archived' );
	}

	private static function mailchimp_request( string $method, string $path, string $api_key, ?array $body = null ): array {
		$base = self::get_api_base( $api_key );
		$url  = rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );

		$headers = self::get_auth_headers( $api_key );
		$args = [
			'headers' => $headers,
		];

		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
			$args['headers'] = $headers;
			$args['body'] = wp_json_encode( $body );
		}

		[ $response_body, $status ] = self::http_request( $method, $url, $args );

		if ( $status >= 400 || ( isset( $response_body['status'] ) && (int) $response_body['status'] >= 400 ) ) {
			$detail = $response_body['detail'] ?? $response_body['title'] ?? 'Unknown error';
			throw new \Exception( 'Mailchimp API error: ' . esc_html( $detail ) );
		}

		return [ $response_body, $status ];
	}

	private static function get_api_base( string $api_key ): string {
		$parts = explode( '-', $api_key );
		if ( count( $parts ) < 2 ) {
			throw new \Exception( 'Invalid Mailchimp API key format (missing data center)' );
		}
		$dc = end( $parts );
		return 'https://' . $dc . '.api.mailchimp.com/' . self::API_VERSION;
	}

	private static function get_auth_headers( string $api_key ): array {
		return array(
			'Authorization' => 'Basic ' . base64_encode( 'zaplane:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for HTTP Basic auth header.
		);
	}

	private static function get_status_options( bool $allow_empty = true ): array {
		$options = array(
			array(
				'value' => 'mailchimp_subscribed',
				'label' => 'Subscribed'
			),
			array(
				'value' => 'mailchimp_pending',
				'label' => 'Pending (double opt-in)'
			),
			array(
				'value' => 'mailchimp_unsubscribed',
				'label' => 'Unsubscribed'
			),
			array(
				'value' => 'mailchimp_cleaned',
				'label' => 'Cleaned'
			),
			array(
				'value' => 'mailchimp_transactional',
				'label' => 'Transactional'
			),
		);

		if ( $allow_empty ) {
			array_unshift( $options, array(
				'value' => '',
				'label' => 'Leave Unchanged'
			) );
		}

		return $options;
	}

	private static function normalize_status_value( string $status ): string {
		$status = sanitize_key( $status );
		if ( 0 === strpos( $status, 'mailchimp_' ) ) {
			return substr( $status, strlen( 'mailchimp_' ) );
		}

		return $status;
	}

	private static function parse_merge_fields( $raw, array $input ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		$raw = self::substitute_variables( trim( $raw ), $input );
		if ( '' === $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			throw new \Exception( 'Merge Fields must be valid JSON (object)' );
		}

		return $decoded;
	}

	private static function parse_tags( $raw, array $input ): array {
		if ( is_array( $raw ) ) {
			return self::normalize_tags( $raw );
		}

		$raw = self::substitute_variables( trim( $raw ), $input );
		if ( '' === $raw ) {
			return [];
		}

		if ( '[' === $raw[0] ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize_tags( $decoded );
			}
		}

		$items = array_map( 'trim', explode( ',', $raw ) );
		$items = array_filter($items, static function ( $item ) {
			return '' !== $item;
		});

		return self::normalize_tags( array_values( $items ) );
	}

	private static function extract_list_and_email( array $data, array $input ): array {
		$list_id = self::substitute_variables( $data['list_id'] ?? '', $input );
		$email   = self::substitute_variables( $data['email'] ?? '', $input );

		if ( '' === $list_id ) {
			throw new \Exception( 'Mailchimp list ID is required' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			throw new \Exception( 'A valid email address is required' );
		}

		return [ $list_id, $email ];
	}

	private static function resolve_api_key( ?array $credentials ): string {
		$api_key = '';

		if ( is_array( $credentials ) ) {
			$api_key = trim( $credentials['api_key'] ?? '' );
		}

		if ( '' === $api_key && function_exists( 'mc4wp_get_api_key' ) ) {
			$api_key = trim( mc4wp_get_api_key() );
		}

		if ( '' === $api_key ) {
			throw new \Exception( 'Mailchimp API key not found. Set it in MC4WP settings or create a Zaplane connection.' );
		}

		return $api_key;
	}

	private static function resolve_dynamic_api_key( array $q ): string {
		$api_key = trim( (string) ( $q['api_key'] ?? '' ) );
		if ( '' === $api_key && isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$api_key = trim( (string) ( $q['where']['api_key'] ?? '' ) );
		}

		if ( '' !== $api_key ) {
			return $api_key;
		}

		$connection_id = null;
		if ( isset( $q['where'] ) && is_array( $q['where'] ) ) {
			$connection_id = $q['where']['connection_id'] ?? null;
		}
		if ( null === $connection_id && isset( $q['connection_id'] ) ) {
			$connection_id = $q['connection_id'];
		}

		if ( $connection_id ) {
			try {
				$manager = new ConnectionManager();
				$credentials = $manager->get_execution_credentials( (int) $connection_id );
				$api_key = trim( (string) ( $credentials['api_key'] ?? '' ) );
				if ( '' !== $api_key ) {
					return $api_key;
				}
			} catch ( \Throwable $e ) {
				unset( $e );
				// Ignore and fall through.
			}
		}

		if ( function_exists( 'get_current_user_id' ) ) {
			$user_id = (int) get_current_user_id();
			if ( $user_id > 0 ) {
				try {
					$manager = new ConnectionManager();
					$connections = $manager->get_user_connections( $user_id, 'mailchimp', 1, 1 );
					$first = $connections['data'][0] ?? null;
					if ( $first && isset( $first['id'] ) ) {
						$credentials = $manager->get_execution_credentials( (int) $first['id'] );
						$api_key = trim( (string) ( $credentials['api_key'] ?? '' ) );
						if ( '' !== $api_key ) {
							return $api_key;
						}
					}
				} catch ( \Throwable $e ) {
					unset( $e );
					// Ignore and fall through.
				}
			}
		}

		if ( function_exists( 'mc4wp_get_api_key' ) ) {
			$api_key = trim( mc4wp_get_api_key() );
			if ( '' !== $api_key ) {
				return $api_key;
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
		if ( '' === $search ) {
			return true;
		}
		return stripos( $value, $search ) !== false;
	}

	private static function resolve_action( array $node ): string {
		if ( isset( $node['config']['action'] ) && is_string( $node['config']['action'] ) ) {
			return $node['config']['action'];
		}

		$data = $node['data'] ?? [];
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

		$data = $node['data'] ?? [];
		if ( isset( $data['config'] ) && is_array( $data['config'] ) ) {
			return $data['config'];
		}

		if ( isset( $node['config'] ) && is_array( $node['config'] ) && ! isset( $node['config']['action'] ) ) {
			return $node['config'];
		}

		return [];
	}

	private static function normalize_tags( array $tags ): array {
		$normalized = [];

		foreach ( $tags as $tag ) {
			if ( is_string( $tag ) ) {
				$name = trim( $tag );
				if ( '' === $name ) {
					continue;
				}
				$normalized[] = [
					'name' => $name,
					'status' => 'active'
				];
				continue;
			}

			if ( is_array( $tag ) ) {
				$name = trim( $tag['name'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				$status = $tag['status'] ?? 'active';
				$normalized[] = [
					'name' => $name,
					'status' => $status
				];
			}
		}//end foreach

		return $normalized;
	}

	private static function substitute_variables( $text, array $data ): string {
		$text = (string) $text;
		return preg_replace_callback(
			'/\{\{([^}]+)\}\}/',
			static function ( $matches ) use ( $data ) {
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
