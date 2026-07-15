<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;
use Zaplane\Integrations\Trello\QueryTrait;
use Zaplane\Integrations\Trello\Helper;

class Trello extends IntegrationBase {
	use ActionResponseTrait;
	use QueryTrait;
	use Helper;

	private const API_BASE_URL = 'https://api.trello.com/1';

	public static function get_slug(): string {
		return 'trello';
	}

	public static function get_name(): string {
		return 'Trello';
	}

	public static function get_icon(): string {
		return 'trello-icon.svg';
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'token_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'api_key' => [
				'type'     => 'text',
				'label'    => 'API Key',
				'required' => true,
				'help'     => 'Go to https://trello.com/power-ups/admin → Create New Power-Up → API Key section → Copy your API Key.',
			],
			'token'   => [
				'type'     => 'password',
				'label'    => 'Token',
				'required' => true,
				'help'     => 'On the API Key page, click "generate a token" → Allow (ensure read, write & account permissions) → Copy the Token.',
			],
		];
	}
	public static function test_connection( array $credentials ): array {
		$api_key = $credentials['api_key'] ?? '';
		$token   = $credentials['token'] ?? '';
		if ( empty( $api_key ) || empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'api_key and token are required.',
				'details' => [],
			];
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/members/me?' . http_build_query(
				[
					'key'   => $api_key,
					'token' => $token,
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection failed: ' . $response->get_error_message(),
				'details' => [],
			];
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		if ( 200 !== $code || empty( $body['id'] ) ) {
			return [
				'success' => false,
				'message' => 'Invalid credentials (HTTP ' . $code . ').',
				'details' => [],
			];
		}
		return [
			'success' => true,
			'message' => 'Connected as: ' . ( $body['fullName'] ?? $body['username'] ?? 'Unknown' ),
			'details' => [
				'full_name' => $body['fullName'] ?? '',
				'username'  => $body['username'] ?? '',
				'email'     => $body['email'] ?? '',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'create_card'           => [ 'label' => 'Create Card' ],
			'get_card'              => [ 'label' => 'Get Card' ],
			'update_card'           => [ 'label' => 'Update Card' ],
			'delete_card'           => [ 'label' => 'Delete Card' ],
			'create_board'          => [ 'label' => 'Create Board' ],
			'get_board'             => [ 'label' => 'Get Board' ],
			'update_board'          => [ 'label' => 'Update Board' ],
			'delete_board'          => [ 'label' => 'Delete Board' ],
			'create_label'          => [ 'label' => 'Create Label' ],
			'get_label'             => [ 'label' => 'Get Label' ],
			'update_label'          => [ 'label' => 'Update Label' ],
			'delete_label'          => [ 'label' => 'Delete Label' ],
			'list_labels'           => [ 'label' => 'List Labels' ],
			'add_label_to_card'     => [ 'label' => 'Add Label to Card' ],
			'remove_label_card'     => [ 'label' => 'Remove Label from Card' ],
			'add_board_member'      => [ 'label' => 'Add Board Member' ],
			'get_board_members'     => [ 'label' => 'Get Board Members' ],
			'invite_board_member'   => [ 'label' => 'Invite Board Member' ],
			'remove_board_member'   => [ 'label' => 'Remove Board Member' ],
			'create_attachment'     => [ 'label' => 'Create Attachment' ],
			'get_attachment'        => [ 'label' => 'Get Attachment' ],
			'get_attachments'       => [ 'label' => 'Get Many Attachments' ],
			'delete_attachment'     => [ 'label' => 'Delete Attachment' ],
			'create_checklist'      => [ 'label' => 'Create Checklist' ],
			'create_checklist_item' => [ 'label' => 'Create Checklist Item' ],
			'delete_checklist'      => [ 'label' => 'Delete Checklist' ],
			'delete_checklist_item' => [ 'label' => 'Delete Checklist Item' ],
			'get_checklist'         => [ 'label' => 'Get Checklist' ],
			'get_checklist_items'   => [ 'label' => 'Get Checklist Items' ],
			'get_completed_items'   => [ 'label' => 'Get Completed Checklist Items' ],
			'get_many_checklists'   => [ 'label' => 'Get Many Checklists' ],
			'update_checklist_item' => [ 'label' => 'Update Checklist Item' ],
		];
	}
	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'create_card':
				return array_merge(
					self::field_board(),
					self::field_list(),
					self::field_card_name(),
					[
						[
							'key'         => 'card_desc',
							'type'        => 'textarea',
							'label'       => 'Description',
							'placeholder' => 'Card description...',
							'required'    => false,
						],
						[
							'key'         => 'card_due',
							'type'        => 'text',
							'label'       => 'Due Date (ISO 8601)',
							'placeholder' => '2026-12-31T23:59:00Z',
							'required'    => false,
						],
						[
							'key'      => 'label_ids',
							'type'     => 'text',
							'label'    => 'Label IDs (comma-separated)',
							'required' => false,
						],
						[
							'key'      => 'member_ids',
							'type'     => 'text',
							'label'    => 'Member IDs (comma-separated)',
							'required' => false,
						],
					]
				);
			case 'get_card':
				return self::field_card_select();
			case 'update_card':
				return array_merge(
					self::field_card_select(),
					[
						[
							'key'         => 'card_name',
							'type'        => 'text',
							'label'       => 'New Card Name',
							'placeholder' => 'Updated name',
							'required'    => false,
						],
						[
							'key'      => 'card_desc',
							'type'     => 'textarea',
							'label'    => 'New Description',
							'required' => false,
						],
						[
							'key'      => 'card_due',
							'type'     => 'text',
							'label'    => 'New Due Date (ISO 8601)',
							'required' => false,
						],
						[
							'key'      => 'closed',
							'type'     => 'select',
							'label'    => 'Archive Card?',
							'required' => false,
							'options'  => [
								[
									'label' => 'No',
									'value' => 'false'
								],
								[
									'label' => 'Yes',
									'value' => 'true'
								],
							],
						],
					]
				);
			case 'delete_card':
				return self::field_card_select();
			case 'create_board':
				return [
					[
						'key'      => 'board_name',
						'type'     => 'text',
						'label'    => 'Board Name',
						'required' => true,
					],
					[
						'key'      => 'board_desc',
						'type'     => 'textarea',
						'label'    => 'Description',
						'required' => false,
					],
					// org_id: dynamic dropdown from /members/me/organizations
					self::field_org_select_inline(),
					[
						'key'      => 'default_lists',
						'type'     => 'select',
						'label'    => 'Create Default Lists?',
						'required' => false,
						'options'  => [
							[
								'label' => 'Yes',
								'value' => 'true'
							],
							[
								'label' => 'No',
								'value' => 'false'
							],
						],
					],
				];
			case 'get_board':
				return self::field_board();
			case 'update_board':
				return array_merge(
					self::field_board(),
					[
						[
							'key'      => 'board_name',
							'type'     => 'text',
							'label'    => 'New Board Name',
							'required' => false,
						],
						[
							'key'      => 'board_desc',
							'type'     => 'textarea',
							'label'    => 'New Description',
							'required' => false,
						],
						[
							'key'      => 'closed',
							'type'     => 'select',
							'label'    => 'Close/Archive Board?',
							'required' => false,
							'options'  => [
								[
									'label' => 'No',
									'value' => 'false'
								],
								[
									'label' => 'Yes',
									'value' => 'true'
								],
							],
						],
					]
				);
			case 'delete_board':
				return self::field_board();
			case 'create_label':
				return array_merge(
					self::field_board(),
					[
						[
							'key'      => 'label_name',
							'type'     => 'text',
							'label'    => 'Label Name',
							'required' => true,
						],
					],
					self::field_label_color()
				);
			case 'get_label':
				return self::field_label_select();
			case 'update_label':
				return array_merge(
					self::field_label_select(),
					[
						[
							'key'      => 'label_name',
							'type'     => 'text',
							'label'    => 'New Label Name',
							'required' => false,
						],
					],
					self::field_label_color()
				);
			case 'delete_label':
				return self::field_label_select();
			case 'list_labels':
				return self::field_board();
			case 'add_label_to_card':
				return array_merge(
					self::field_card_select(),
					self::field_label()
				);
			case 'remove_label_card':
				return array_merge(
					self::field_card_select(),
					self::field_label()
				);
			case 'add_board_member':
				return array_merge(
					self::field_board(),
					[
						[
							'key'      => 'member_id',
							'type'     => 'text',
							'label'    => 'Member ID or Username',
							'required' => true,
						],
					],
					[
						[
							'key'      => 'member_type',
							'type'     => 'select',
							'label'    => 'Member Type',
							'required' => false,
							'options'  => [
								[
									'label' => 'Normal',
									'value' => 'normal'
								],
								[
									'label' => 'Admin',
									'value' => 'admin'
								],
								[
									'label' => 'Observer',
									'value' => 'observer'
								],
							],
						],
					]
				);
			case 'get_board_members':
				return self::field_board();
			case 'invite_board_member':
				return array_merge(
					self::field_board(),
					[
						[
							'key'      => 'email',
							'type'     => 'email',
							'label'    => 'Email Address',
							'required' => true,
						],
						[
							'key'      => 'full_name',
							'type'     => 'text',
							'label'    => 'Full Name',
							'required' => false,
						],
						[
							'key'      => 'member_type',
							'type'     => 'select',
							'label'    => 'Member Type',
							'required' => false,
							'options'  => [
								[
									'label' => 'Normal',
									'value' => 'normal'
								],
								[
									'label' => 'Admin',
									'value' => 'admin'
								],
								[
									'label' => 'Observer',
									'value' => 'observer'
								],
							],
						],
					]
				);
			case 'remove_board_member':
				return array_merge(
					self::field_board(),
					self::field_member_select()
				);
			case 'create_attachment':
				return array_merge(
					self::field_card_select(),
					[
						[
							'key'      => 'attachment_url',
							'type'     => 'text',
							'label'    => 'Attachment URL',
							'required' => true,
						],
						[
							'key'      => 'name',
							'type'     => 'text',
							'label'    => 'Attachment Name (optional)',
							'required' => false,
						],
					]
				);
			case 'get_attachment':
				return array_merge(
					self::field_card_select(),
					self::field_attachment_select()
				);
			case 'get_attachments':
				return self::field_card_select();
			case 'delete_attachment':
				return array_merge(
					self::field_card_select(),
					self::field_attachment_select()
				);
			case 'create_checklist':
				return array_merge(
					self::field_card_select(),
					[
						[
							'key'      => 'checklist_name',
							'type'     => 'text',
							'label'    => 'Checklist Name',
							'required' => true,
						],
					]
				);
			case 'create_checklist_item':
				return array_merge(
					self::field_card_select(),
					self::field_checklist_select(),
					[
						[
							'key'      => 'item_name',
							'type'     => 'text',
							'label'    => 'Item Name',
							'required' => true,
						],
						[
							'key'      => 'pos',
							'type'     => 'text',
							'label'    => 'Position (top, bottom, or number)',
							'required' => false,
						],
						[
							'key'      => 'checked',
							'type'     => 'select',
							'label'    => 'Checked?',
							'required' => false,
							'options'  => [
								[
									'label' => 'No',
									'value' => 'false'
								],
								[
									'label' => 'Yes',
									'value' => 'true'
								],
							],
						],
					]
				);
			case 'delete_checklist':
				return array_merge(
					self::field_card_select(),
					self::field_checklist_select()
				);
			case 'delete_checklist_item':
				return array_merge(
					self::field_card_select(),
					self::field_checklist_select(),
					self::field_checklist_item_select()
				);
			case 'get_checklist':
				return array_merge(
					self::field_card_select(),
					self::field_checklist_select()
				);
			case 'get_checklist_items':
				return array_merge(
					self::field_card_select(),
					self::field_checklist_select()
				);
			case 'get_completed_items':
				return array_merge(
					self::field_card_select(),
					self::field_checklist_select()
				);
			case 'get_many_checklists':
				return self::field_card_select();
			case 'update_checklist_item':
				return array_merge(
					self::field_card_select(),
					self::field_checklist_select(),
					self::field_checklist_item_select(),
					[
						[
							'key'      => 'item_name',
							'type'     => 'text',
							'label'    => 'New Item Name',
							'required' => false,
						],
						[
							'key'      => 'state',
							'type'     => 'select',
							'label'    => 'State',
							'required' => false,
							'options'  => [
								[
									'label' => 'Incomplete',
									'value' => 'incomplete'
								],
								[
									'label' => 'Complete',
									'value' => 'complete'
								],
							],
						],
					]
				);
		}//end switch
		return [];
	}
	public static function execute_node( array $node, array $input ): array {
		$config  = $node['data']['config'] ?? [];
		$event   = $node['data']['event'] ?? '';
		$creds   = self::get_connection_credentials( $node );
		$api_key = $creds['api_key'] ?? '';
		$token   = $creds['token'] ?? '';
		if ( empty( $api_key ) || empty( $token ) ) {
			return self::error( __( 'Trello api_key and token are required.', 'zaplane' ), $input );
		}
		switch ( $event ) {
			case 'create_card':
				$list_id = $config['list_id'] ?? '';
				$name    = $config['card_name'] ?? '';
				if ( empty( $list_id ) || empty( $name ) ) {
					return self::error( __( 'Trello: list_id and card_name are required.', 'zaplane' ), $input );
				}
				$payload = [
					'idList' => $list_id,
					'name'   => $name,
				];
				if ( ! empty( $config['card_desc'] ) ) {
					$payload['desc'] = $config['card_desc'];
				}
				if ( ! empty( $config['card_due'] ) ) {
					$payload['due'] = $config['card_due'];
				}
				if ( ! empty( $config['label_ids'] ) ) {
					$payload['idLabels'] = $config['label_ids'];
				}
				if ( ! empty( $config['member_ids'] ) ) {
					$payload['idMembers'] = $config['member_ids'];
				}
				try {
					$body = self::trello_request( $api_key, $token, 'POST', '/cards', $payload );
					return self::success( array_merge( $input, [
						'trello_card_id'   => $body['id'] ?? '',
						'trello_card_name' => $body['name'] ?? '',
						'trello_card_url'  => $body['shortUrl'] ?? '',
						'trello_card_desc' => $body['desc'] ?? '',
						'trello_list_id'   => $body['idList'] ?? '',
						'trello_board_id'  => $body['idBoard'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_card':
				$card_id = $config['card_id'] ?? '';
				if ( empty( $card_id ) ) {
					return self::error( __( 'Trello: card_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'GET', '/cards/' . rawurlencode( $card_id ) );
					return self::success( array_merge( $input, [
						'trello_card_id'   => $body['id'] ?? '',
						'trello_card_name' => $body['name'] ?? '',
						'trello_card_url'  => $body['shortUrl'] ?? '',
						'trello_card_desc' => $body['desc'] ?? '',
						'trello_list_id'   => $body['idList'] ?? '',
						'trello_board_id'  => $body['idBoard'] ?? '',
						'trello_due'       => $body['due'] ?? '',
						'trello_closed'    => $body['closed'] ?? false,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'update_card':
				$card_id = $config['card_id'] ?? '';
				if ( empty( $card_id ) ) {
					return self::error( __( 'Trello: card_id is required.', 'zaplane' ), $input );
				}
				$payload = [];
				if ( isset( $config['card_name'] ) && '' !== $config['card_name'] ) {
					$payload['name'] = $config['card_name'];
				}
				if ( isset( $config['card_desc'] ) && '' !== $config['card_desc'] ) {
					$payload['desc'] = $config['card_desc'];
				}
				if ( isset( $config['card_due'] ) && '' !== $config['card_due'] ) {
					$payload['due'] = $config['card_due'];
				}
				if ( isset( $config['closed'] ) && '' !== $config['closed'] ) {
					$payload['closed'] = ( 'true' === $config['closed'] );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'PUT', '/cards/' . rawurlencode( $card_id ), $payload );
					return self::success( array_merge( $input, [
						'trello_card_id'   => $body['id'] ?? '',
						'trello_card_name' => $body['name'] ?? '',
						'trello_card_url'  => $body['shortUrl'] ?? '',
						'trello_card_desc' => $body['desc'] ?? '',
						'trello_closed'    => $body['closed'] ?? false,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'delete_card':
				$card_id = $config['card_id'] ?? '';
				if ( empty( $card_id ) ) {
					return self::error( __( 'Trello: card_id is required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request( $api_key, $token, 'DELETE', '/cards/' . rawurlencode( $card_id ) );
					return self::success( array_merge( $input, [
						'trello_deleted_card_id' => $card_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'create_board':
				$name = $config['board_name'] ?? '';
				if ( empty( $name ) ) {
					return self::error( __( 'Trello: board_name is required.', 'zaplane' ), $input );
				}
				$payload = [ 'name' => $name ];
				if ( ! empty( $config['board_desc'] ) ) {
					$payload['desc'] = $config['board_desc'];
				}
				if ( ! empty( $config['org_id'] ) ) {
					$payload['idOrganization'] = $config['org_id'];
				}
				if ( isset( $config['default_lists'] ) ) {
					$payload['defaultLists'] = ( 'true' === $config['default_lists'] );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'POST', '/boards', $payload );
					return self::success( array_merge( $input, [
						'trello_board_id'   => $body['id'] ?? '',
						'trello_board_name' => $body['name'] ?? '',
						'trello_board_url'  => $body['url'] ?? '',
						'trello_board_desc' => $body['desc'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_board':
				$board_id = $config['board_id'] ?? '';
				if ( empty( $board_id ) ) {
					return self::error( __( 'Trello: board_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'GET', '/boards/' . rawurlencode( $board_id ) );
					return self::success( array_merge( $input, [
						'trello_board_id'   => $body['id'] ?? '',
						'trello_board_name' => $body['name'] ?? '',
						'trello_board_url'  => $body['url'] ?? '',
						'trello_board_desc' => $body['desc'] ?? '',
						'trello_closed'     => $body['closed'] ?? false,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'update_board':
				$board_id = $config['board_id'] ?? '';
				if ( empty( $board_id ) ) {
					return self::error( __( 'Trello: board_id is required.', 'zaplane' ), $input );
				}
				$payload = [];
				if ( isset( $config['board_name'] ) && '' !== $config['board_name'] ) {
					$payload['name'] = $config['board_name'];
				}
				if ( isset( $config['board_desc'] ) && '' !== $config['board_desc'] ) {
					$payload['desc'] = $config['board_desc'];
				}
				if ( isset( $config['closed'] ) && '' !== $config['closed'] ) {
					$payload['closed'] = ( 'true' === $config['closed'] );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'PUT', '/boards/' . rawurlencode( $board_id ), $payload );
					return self::success( array_merge( $input, [
						'trello_board_id'   => $body['id'] ?? '',
						'trello_board_name' => $body['name'] ?? '',
						'trello_board_url'  => $body['url'] ?? '',
						'trello_board_desc' => $body['desc'] ?? '',
						'trello_closed'     => $body['closed'] ?? false,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'delete_board':
				$board_id = $config['board_id'] ?? '';
				if ( empty( $board_id ) ) {
					return self::error( __( 'Trello: board_id is required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request( $api_key, $token, 'DELETE', '/boards/' . rawurlencode( $board_id ) );
					return self::success( array_merge( $input, [
						'trello_deleted_board_id' => $board_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'create_label':
				$board_id = $config['board_id'] ?? '';
				$name     = $config['label_name'] ?? '';
				if ( empty( $board_id ) || empty( $name ) ) {
					return self::error( __( 'Trello: board_id and label_name are required.', 'zaplane' ), $input );
				}
				$payload = [
					'name'    => $name,
					'idBoard' => $board_id,
				];
				if ( ! empty( $config['label_color'] ) ) {
					$payload['color'] = $config['label_color'];
				}
				try {
					$body = self::trello_request( $api_key, $token, 'POST', '/labels', $payload );
					return self::success( array_merge( $input, [
						'trello_label_id'    => $body['id'] ?? '',
						'trello_label_name'  => $body['name'] ?? '',
						'trello_label_color' => $body['color'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_label':
				$label_id = $config['label_id'] ?? '';
				if ( empty( $label_id ) ) {
					return self::error( __( 'Trello: label_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'GET', '/labels/' . rawurlencode( $label_id ) );
					return self::success( array_merge( $input, [
						'trello_label_id'    => $body['id'] ?? '',
						'trello_label_name'  => $body['name'] ?? '',
						'trello_label_color' => $body['color'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'update_label':
				$label_id = $config['label_id'] ?? '';
				if ( empty( $label_id ) ) {
					return self::error( __( 'Trello: label_id is required.', 'zaplane' ), $input );
				}
				$payload = [];
				if ( isset( $config['label_name'] ) && '' !== $config['label_name'] ) {
					$payload['name'] = $config['label_name'];
				}
				if ( isset( $config['label_color'] ) ) {
					$payload['color'] = $config['label_color'];
				}
				try {
					$body = self::trello_request( $api_key, $token, 'PUT', '/labels/' . rawurlencode( $label_id ), $payload );
					return self::success( array_merge( $input, [
						'trello_label_id'    => $body['id'] ?? '',
						'trello_label_name'  => $body['name'] ?? '',
						'trello_label_color' => $body['color'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'delete_label':
				$label_id = $config['label_id'] ?? '';
				if ( empty( $label_id ) ) {
					return self::error( __( 'Trello: label_id is required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request( $api_key, $token, 'DELETE', '/labels/' . rawurlencode( $label_id ) );
					return self::success( array_merge( $input, [
						'trello_deleted_label_id' => $label_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'list_labels':
				$board_id = $config['board_id'] ?? '';
				if ( empty( $board_id ) ) {
					return self::error( __( 'Trello: board_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'GET', '/boards/' . rawurlencode( $board_id ) . '/labels' );
					return self::success( array_merge( $input, [
						'trello_labels' => $body,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'add_label_to_card':
				$card_id  = $config['card_id'] ?? '';
				$label_id = $config['label_id'] ?? '';
				if ( empty( $card_id ) || empty( $label_id ) ) {
					return self::error( __( 'Trello: card_id and label_id are required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request(
						$api_key,
						$token,
						'POST',
						'/cards/' . rawurlencode( $card_id ) . '/idLabels',
						[ 'value' => $label_id ]
					);
					return self::success( array_merge( $input, [
						'trello_card_id'  => $card_id,
						'trello_label_id' => $label_id,
						'trello_status'   => 'label_added',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'remove_label_card':
				$card_id  = $config['card_id'] ?? '';
				$label_id = $config['label_id'] ?? '';
				if ( empty( $card_id ) || empty( $label_id ) ) {
					return self::error( __( 'Trello: card_id and label_id are required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request(
						$api_key,
						$token,
						'DELETE',
						'/cards/' . rawurlencode( $card_id ) . '/idLabels/' . rawurlencode( $label_id )
					);
					return self::success( array_merge( $input, [
						'trello_card_id'  => $card_id,
						'trello_label_id' => $label_id,
						'trello_status'   => 'label_removed',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'add_board_member':
				$board_id  = $config['board_id'] ?? '';
				$member_id = $config['member_id'] ?? '';
				$type      = $config['member_type'] ?? 'normal';
				if ( empty( $board_id ) || empty( $member_id ) ) {
					return self::error( __( 'Trello: board_id and member_id are required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'PUT',
						'/boards/' . rawurlencode( $board_id ) . '/members/' . rawurlencode( $member_id ),
						[ 'type' => $type ]
					);
					return self::success( array_merge( $input, [
						'trello_board_id'  => $board_id,
						'trello_member_id' => $member_id,
						'trello_status'    => 'member_added',
						'trello_members'   => $body,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_board_members':
				$board_id = $config['board_id'] ?? '';
				if ( empty( $board_id ) ) {
					return self::error( __( 'Trello: board_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request( $api_key, $token, 'GET', '/boards/' . rawurlencode( $board_id ) . '/members' );
					return self::success( array_merge( $input, [
						'trello_board_members' => $body,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'invite_board_member':
				$board_id = $config['board_id'] ?? '';
				$email    = $config['email'] ?? '';
				$type     = $config['member_type'] ?? 'normal';
				if ( empty( $board_id ) || empty( $email ) ) {
					return self::error( __( 'Trello: board_id and email are required.', 'zaplane' ), $input );
				}
				$payload = [
					'email' => $email,
					'type'  => $type,
				];
				if ( ! empty( $config['full_name'] ) ) {
					$payload['fullName'] = $config['full_name'];
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'PUT',
						'/boards/' . rawurlencode( $board_id ) . '/members',
						$payload
					);
					return self::success( array_merge( $input, [
						'trello_board_id' => $board_id,
						'trello_email'    => $email,
						'trello_status'   => 'member_invited',
						'trello_members'  => $body,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'remove_board_member':
				$board_id  = $config['board_id'] ?? '';
				$member_id = $config['member_id'] ?? '';
				if ( empty( $board_id ) || empty( $member_id ) ) {
					return self::error( __( 'Trello: board_id and member_id are required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request(
						$api_key,
						$token,
						'DELETE',
						'/boards/' . rawurlencode( $board_id ) . '/members/' . rawurlencode( $member_id )
					);
					return self::success( array_merge( $input, [
						'trello_board_id'  => $board_id,
						'trello_member_id' => $member_id,
						'trello_status'    => 'member_removed',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'create_attachment':
				$card_id = $config['card_id'] ?? '';
				$url     = $config['attachment_url'] ?? '';
				if ( empty( $card_id ) || empty( $url ) ) {
					return self::error( __( 'Trello: card_id and attachment_url are required.', 'zaplane' ), $input );
				}
				$payload = [ 'url' => $url ];
				if ( ! empty( $config['name'] ) ) {
					$payload['name'] = $config['name'];
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'POST',
						'/cards/' . rawurlencode( $card_id ) . '/attachments',
						$payload
					);
					return self::success( array_merge( $input, [
						'trello_attachment_id'   => $body['id'] ?? '',
						'trello_attachment_name' => $body['name'] ?? '',
						'trello_attachment_url'  => $body['url'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_attachment':
				$card_id       = $config['card_id'] ?? '';
				$attachment_id = $config['attachment_id'] ?? '';
				if ( empty( $card_id ) || empty( $attachment_id ) ) {
					return self::error( __( 'Trello: card_id and attachment_id are required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'GET',
						'/cards/' . rawurlencode( $card_id ) . '/attachments/' . rawurlencode( $attachment_id )
					);
					return self::success( array_merge( $input, [
						'trello_attachment_id'   => $body['id'] ?? '',
						'trello_attachment_name' => $body['name'] ?? '',
						'trello_attachment_url'  => $body['url'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_attachments':
				$card_id = $config['card_id'] ?? '';
				if ( empty( $card_id ) ) {
					return self::error( __( 'Trello: card_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'GET',
						'/cards/' . rawurlencode( $card_id ) . '/attachments'
					);
					return self::success( array_merge( $input, [
						'trello_attachments' => $body,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'delete_attachment':
				$card_id       = $config['card_id'] ?? '';
				$attachment_id = $config['attachment_id'] ?? '';
				if ( empty( $card_id ) || empty( $attachment_id ) ) {
					return self::error( __( 'Trello: card_id and attachment_id are required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request(
						$api_key,
						$token,
						'DELETE',
						'/cards/' . rawurlencode( $card_id ) . '/attachments/' . rawurlencode( $attachment_id )
					);
					return self::success( array_merge( $input, [
						'trello_deleted_attachment_id' => $attachment_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'create_checklist':
				$card_id = $config['card_id'] ?? '';
				$name    = $config['checklist_name'] ?? '';
				if ( empty( $card_id ) || empty( $name ) ) {
					return self::error( __( 'Trello: card_id and checklist_name are required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'POST',
						'/cards/' . rawurlencode( $card_id ) . '/checklists',
						[ 'name' => $name ]
					);
					return self::success( array_merge( $input, [
						'trello_checklist_id'   => $body['id'] ?? '',
						'trello_checklist_name' => $body['name'] ?? '',
						'trello_card_id'        => $body['idCard'] ?? $card_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'create_checklist_item':
				$checklist_id = $config['checklist_id'] ?? '';
				$name         = $config['item_name'] ?? '';
				if ( empty( $checklist_id ) || empty( $name ) ) {
					return self::error( __( 'Trello: checklist_id and item_name are required.', 'zaplane' ), $input );
				}
				$payload = [ 'name' => $name ];
				if ( ! empty( $config['pos'] ) ) {
					$payload['pos'] = $config['pos'];
				}
				if ( isset( $config['checked'] ) ) {
					$payload['checked'] = ( 'true' === $config['checked'] );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'POST',
						'/checklists/' . rawurlencode( $checklist_id ) . '/checkItems',
						$payload
					);
					return self::success( array_merge( $input, [
						'trello_item_id'      => $body['id'] ?? '',
						'trello_item_name'    => $body['name'] ?? '',
						'trello_item_state'   => $body['state'] ?? 'incomplete',
						'trello_checklist_id' => $body['idChecklist'] ?? $checklist_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'delete_checklist':
				$checklist_id = $config['checklist_id'] ?? '';
				if ( empty( $checklist_id ) ) {
					return self::error( __( 'Trello: checklist_id is required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request(
						$api_key,
						$token,
						'DELETE',
						'/checklists/' . rawurlencode( $checklist_id )
					);
					return self::success( array_merge( $input, [
						'trello_deleted_checklist_id' => $checklist_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'delete_checklist_item':
				$checklist_id = $config['checklist_id'] ?? '';
				$item_id      = $config['item_id'] ?? '';
				if ( empty( $checklist_id ) || empty( $item_id ) ) {
					return self::error( __( 'Trello: checklist_id and item_id are required.', 'zaplane' ), $input );
				}
				try {
					self::trello_request(
						$api_key,
						$token,
						'DELETE',
						'/checklists/' . rawurlencode( $checklist_id ) . '/checkItems/' . rawurlencode( $item_id )
					);
					return self::success( array_merge( $input, [
						'trello_deleted_item_id' => $item_id,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_checklist':
				$checklist_id = $config['checklist_id'] ?? '';
				if ( empty( $checklist_id ) ) {
					return self::error( __( 'Trello: checklist_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'GET',
						'/checklists/' . rawurlencode( $checklist_id )
					);
					return self::success( array_merge( $input, [
						'trello_checklist_id'   => $body['id'] ?? '',
						'trello_checklist_name' => $body['name'] ?? '',
						'trello_card_id'        => $body['idCard'] ?? '',
						'trello_check_items'    => $body['checkItems'] ?? [],
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_checklist_items':
				$checklist_id = $config['checklist_id'] ?? '';
				if ( empty( $checklist_id ) ) {
					return self::error( __( 'Trello: checklist_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'GET',
						'/checklists/' . rawurlencode( $checklist_id ) . '/checkItems'
					);
					return self::success( array_merge( $input, [
						'trello_checklist_items' => $body,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'get_completed_items':
				$checklist_id = $config['checklist_id'] ?? '';
				if ( empty( $checklist_id ) ) {
					return self::error( __( 'Trello: checklist_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'GET',
						'/checklists/' . rawurlencode( $checklist_id ) . '/checkItems'
					);
					$completed = [];
					if ( is_array( $body ) ) {
						foreach ( $body as $item ) {
							if ( isset( $item['state'] ) && 'complete' === $item['state'] ) {
								$completed[] = $item;
							}
						}
					}
					return self::success( array_merge( $input, [
						'trello_completed_items' => $completed,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}//end try
			case 'get_many_checklists':
				$card_id = $config['card_id'] ?? '';
				if ( empty( $card_id ) ) {
					return self::error( __( 'Trello: card_id is required.', 'zaplane' ), $input );
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'GET',
						'/cards/' . rawurlencode( $card_id ) . '/checklists'
					);
					return self::success( array_merge( $input, [
						'trello_checklists' => $body,
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
			case 'update_checklist_item':
				$card_id = $config['card_id'] ?? '';
				$item_id = $config['item_id'] ?? '';
				if ( empty( $card_id ) || empty( $item_id ) ) {
					return self::error( __( 'Trello: card_id and item_id are required.', 'zaplane' ), $input );
				}
				$payload = [];
				if ( isset( $config['item_name'] ) && '' !== $config['item_name'] ) {
					$payload['name'] = $config['item_name'];
				}
				if ( ! empty( $config['state'] ) ) {
					$payload['state'] = $config['state'];
				}
				try {
					$body = self::trello_request(
						$api_key,
						$token,
						'PUT',
						'/cards/' . rawurlencode( $card_id ) . '/checkItem/' . rawurlencode( $item_id ),
						$payload
					);
					return self::success( array_merge( $input, [
						'trello_item_id'    => $body['id'] ?? '',
						'trello_item_name'  => $body['name'] ?? '',
						'trello_item_state' => $body['state'] ?? 'incomplete',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
		}//end switch
		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'board_query'          => [ self::class, 'query_board' ],
			'list_query'           => [ self::class, 'query_list' ],
			'label_query'          => [ self::class, 'query_label' ],
			'card_query'           => [ self::class, 'query_card' ],
			'checklist_query'      => [ self::class, 'query_checklist' ],
			'checklist_item_query' => [ self::class, 'query_checklist_item' ],
			'attachment_query'     => [ self::class, 'query_attachment' ],
			'member_query'         => [ self::class, 'query_member' ],
			'org_query'            => [ self::class, 'query_org' ],
		];
	}

	public static function get_dynamic_fields(): array {
		return self::get_dynamic_queries();
	}

	private static function get_connection_credentials( array $node ): array {
		$connection_id = (int) (
			$node['data']['connection_id']
			?? $node['connection_id']
			?? 0
		);
		return self::get_decrypted_credentials( $connection_id );
	}
	private static function extract_credentials( array $params ): array {
		$connection_id = $params['where']['connection_id']
			?? $params['connection_id']
			?? 0;
		return self::get_decrypted_credentials( (int) $connection_id );
	}
	private static function get_decrypted_credentials( int $connection_id = 0 ): array {
		try {
			$cm = new ConnectionManager();
			if ( $connection_id <= 0 ) {
				$connection_id = self::get_trello_connection_id();
			}
			if ( $connection_id <= 0 ) {
				return [];
			}
			$creds = $cm->get_execution_credentials( $connection_id );
			if ( is_array( $creds ) && ! empty( $creds['api_key'] ) ) {
				return $creds;
			}
			if ( is_object( $creds ) ) {
				$creds = (array) $creds;
				if ( ! empty( $creds['api_key'] ) ) {
					return $creds;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}//end try
		return [];
	}
	private static function get_trello_connection_id(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'zaplane_connections';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				'trello'
			)
		);
		return (int) $id;
	}
	private static function trello_request( string $api_key, string $token, string $method, string $endpoint, array $payload = [] ): array {
		$auth_params = [
			'key'   => $api_key,
			'token' => $token,
		];
		$is_get = 'GET' === strtoupper( $method );
		if ( $is_get && ! empty( $payload ) ) {
			$auth_params = array_merge( $auth_params, $payload );
		}
		$url  = self::API_BASE_URL . $endpoint . '?' . http_build_query( $auth_params );
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => [ 'Content-Type' => 'application/json' ],
		];
		if ( ! $is_get && ! empty( $payload ) ) {
			$args['body'] = wp_json_encode( $payload );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Trello API request failed: ' . esc_html( $response->get_error_message() ) );
		}
		$code     = (int) wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		if ( 204 === $code ) {
			return [];
		}
		if ( 200 === $code || 201 === $code ) {
			return json_decode( $body_raw, true ) ?? [];
		}
		if ( $code >= 400 ) {
			$decoded = json_decode( $body_raw, true );
			$message = is_array( $decoded )
				? ( $decoded['message'] ?? $decoded['error'] ?? ( 'HTTP ' . $code ) )
				: ( ! empty( $body_raw ) ? $body_raw : 'HTTP ' . $code );
			throw new \Exception( 'Trello API error: ' . esc_html( $message ) );
		}
		return json_decode( $body_raw, true ) ?? [];
	}
}
