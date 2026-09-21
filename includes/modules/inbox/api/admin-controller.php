<?php

namespace Zaplane\Modules\Inbox\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use Zaplane\Models\Connection;
use Zaplane\Modules\Inbox\Channels\MetaChannel;
use Zaplane\Modules\Inbox\Channels\Registry;
use Zaplane\Modules\Inbox\Commerce\Commerce;
use Zaplane\Modules\Inbox\Models\CannedReply;
use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Models\Tag;
use Zaplane\Modules\Inbox\Services\Conversations;
use Zaplane\Modules\Inbox\Services\Outbound;
use Zaplane\Modules\Inbox\Services\Presenter;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints behind the Inbox screen.
 */
class AdminController {

	private const NS = 'zaplane/v1';

	public static function capability(): string {
		/**
		 * Filter the capability needed to work in the inbox.
		 *
		 * @param string $capability
		 */
		return (string) apply_filters( 'zaplane/inbox/capability', 'manage_options' );
	}

	public function can_manage(): bool {
		return current_user_can( self::capability() );
	}

	public function register_routes(): void {
		$id = '(?P<id>\d+)';

		register_rest_route( self::NS, '/inbox/conversations', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_conversations' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NS, "/inbox/conversations/{$id}", [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_conversation' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_conversation' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );

		register_rest_route( self::NS, "/inbox/conversations/{$id}/messages", [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_messages' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_message' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );

		register_rest_route( self::NS, "/inbox/conversations/{$id}/read", [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'mark_read' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NS, "/inbox/conversations/{$id}/product", [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'send_product' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NS, "/inbox/conversations/{$id}/order", [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'place_order' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NS, '/inbox/products', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'search_products' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NS, '/inbox/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );

		register_rest_route( self::NS, '/inbox/canned-replies', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_canned' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_canned' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );

		register_rest_route( self::NS, "/inbox/canned-replies/{$id}", [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'delete_canned' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );
	}

	/**
	 * Conversation list with filters, plus per-status counts for the tabs.
	 * The screen polls this, so it also carries the server time to poll from.
	 */
	public function list_conversations( WP_REST_Request $request ) {
		$status   = sanitize_key( (string) ( $request->get_param( 'status' ) ?? 'open' ) );
		$assignee = sanitize_key( (string) ( $request->get_param( 'assignee' ) ?? 'any' ) );
		$search   = trim( sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 30 ) ) );

		$query = Conversation::query()->fresh();

		if ( in_array( $status, Conversations::STATUSES, true ) ) {
			$query->where( 'status', $status );
		}
		if ( 'me' === $assignee ) {
			$query->where( 'assignee_id', get_current_user_id() );
		} elseif ( 'unassigned' === $assignee ) {
			$query->whereRaw( '(assignee_id IS NULL OR assignee_id = 0)' );
		}
		if ( 'bot' === $assignee ) {
			$query->where( 'handler', 'bot' );
		}

		if ( '' !== $search ) {
			$like        = '%' . $GLOBALS['wpdb']->esc_like( $search ) . '%';
			$contact_ids = Contact::query()->fresh()
				->whereRaw( '(name LIKE %s OR email LIKE %s OR phone LIKE %s)', [ $like, $like, $like ] )
				->limit( 500 )
				->pluck( 'id' )
				->toArray();
			$contact_ids = array_map( 'intval', $contact_ids );
			if ( empty( $contact_ids ) ) {
				$query->whereRaw( '(last_message_preview LIKE %s)', [ $like ] );
			} else {
				$query->whereRaw(
					'(last_message_preview LIKE %s OR contact_id IN (' . implode( ',', $contact_ids ) . '))',
					[ $like ]
				);
			}
		}

		$total = ( clone $query )->count();
		$rows  = $query->orderBy( 'last_message_at', 'desc' )
			->orderBy( 'id', 'desc' )
			->forPage( $page, $per_page )
			->get()
			->all();

		[ $contacts, $tags ] = $this->preload( $rows );

		return rest_ensure_response( [
			'items'       => array_map(
				static function ( $row ) use ( $contacts, $tags ) {
					return Presenter::conversation( $row, $contacts, $tags );
				},
				$rows
			),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'counts'      => $this->counts(),
			'server_time' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		] );
	}

	/**
	 * @param array<int,Conversation> $rows
	 * @return array{0:array<int,Contact>,1:array<int,array<int,string>>}
	 */
	private function preload( array $rows ): array {
		if ( empty( $rows ) ) {
			return [ [], [] ];
		}

		$contact_ids = array_values( array_unique( array_map( static function ( $r ) {
			return (int) $r->contact_id;
		}, $rows ) ) );
		$conv_ids    = array_map( static function ( $r ) {
			return (int) $r->id;
		}, $rows );

		$contacts = [];
		foreach ( Contact::query()->fresh()->whereIn( 'id', $contact_ids )->get()->all() as $contact ) {
			$contacts[ (int) $contact->id ] = $contact;
		}

		$tags = array_fill_keys( $conv_ids, [] );
		foreach ( Tag::query()->fresh()->whereIn( 'conversation_id', $conv_ids )->get()->all() as $tag ) {
			$tags[ (int) $tag->conversation_id ][] = (string) $tag->tag;
		}

		return [ $contacts, $tags ];
	}

	/**
	 * @return array<string,int>
	 */
	private function counts(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live counts for a polled screen.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS n, SUM(unread_count > 0) AS unread FROM %i GROUP BY status',
				Conversation::getTable()
			),
			ARRAY_A
		);

		$out = array_fill_keys( Conversations::STATUSES, 0 ) + [ 'unread' => 0 ];
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['status'] ] = (int) $row['n'];
			$out['unread']                 += (int) $row['unread'];
		}
		return $out;
	}

	public function get_conversation( WP_REST_Request $request ) {
		$conversation = Conversations::find( (int) $request['id'] );
		if ( ! $conversation ) {
			return $this->not_found();
		}

		$contact = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();
		$history = Conversation::where( 'contact_id', (int) $conversation->contact_id )
			->where( 'id', '!=', (int) $conversation->id )
			->orderBy( 'id', 'desc' )
			->limit( 10 )
			->fresh()
			->get()
			->all();

		return rest_ensure_response( [
			'conversation' => Presenter::conversation( $conversation, $contact ? [ (int) $contact->id => $contact ] : [] ),
			'messages'     => $this->messages( (int) $conversation->id, 0, (int) ( $request->get_param( 'before_id' ) ?? 0 ) ),
			'other'        => array_map( static function ( $c ) {
				return [
					'id'              => (int) $c->id,
					'channel'         => (string) $c->channel,
					'status'          => (string) $c->status,
					'last_message_at' => Presenter::time( $c->last_message_at ),
				];
			}, $history ),
		] );
	}

	public function get_messages( WP_REST_Request $request ) {
		$conversation = Conversations::find( (int) $request['id'] );
		if ( ! $conversation ) {
			return $this->not_found();
		}

		return rest_ensure_response( [
			'messages'     => $this->messages( (int) $conversation->id, (int) ( $request->get_param( 'after_id' ) ?? 0 ), (int) ( $request->get_param( 'before_id' ) ?? 0 ) ),
			'conversation' => Presenter::conversation( $conversation ),
		] );
	}

	/**
	 * Newest 50 by default; `after_id` for polling forward, `before_id` for
	 * loading older ones. Returned oldest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function messages( int $conversation_id, int $after_id, int $before_id ): array {
		$query = Message::where( 'conversation_id', $conversation_id )->fresh();

		if ( $after_id > 0 ) {
			$rows = $query->where( 'id', '>', $after_id )->orderBy( 'id', 'asc' )->limit( 200 )->get()->all();
		} else {
			if ( $before_id > 0 ) {
				$query->where( 'id', '<', $before_id );
			}
			$rows = array_reverse( $query->orderBy( 'id', 'desc' )->limit( 50 )->get()->all() );
		}

		return array_map( [ Presenter::class, 'message' ], $rows );
	}

	public function send_message( WP_REST_Request $request ) {
		$conversation = Conversations::find( (int) $request['id'] );
		if ( ! $conversation ) {
			return $this->not_found();
		}

		$body = trim( (string) $request->get_param( 'body' ) );
		if ( '' === $body ) {
			return new WP_Error( 'zaplane_inbox_empty', __( 'Write a message first.', 'zaplane' ), [ 'status' => 400 ] );
		}

		$message = Outbound::send( $conversation, sanitize_textarea_field( $body ), [
			'sender_type' => 'agent',
			'sender_id'   => get_current_user_id(),
			'is_note'     => rest_sanitize_boolean( $request->get_param( 'is_note' ) ),
		] );

		return rest_ensure_response( [
			'message'      => Presenter::message( $message ),
			'conversation' => Presenter::conversation( Conversations::find( (int) $conversation->id ) ),
		] );
	}

	public function update_conversation( WP_REST_Request $request ) {
		$conversation = Conversations::find( (int) $request['id'] );
		if ( ! $conversation ) {
			return $this->not_found();
		}

		$params = $request->get_json_params() ?: $request->get_body_params();

		if ( isset( $params['status'] ) ) {
			Conversations::set_status( $conversation, sanitize_key( (string) $params['status'] ) );
		}
		if ( array_key_exists( 'assignee_id', $params ) ) {
			Conversations::assign( $conversation, (int) $params['assignee_id'] );
		}
		if ( isset( $params['handler'] ) ) {
			Conversations::set_handler( $conversation, sanitize_key( (string) $params['handler'] ) );
		}
		if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
			Conversations::set_tags( $conversation, $params['tags'] );
		}
		if ( isset( $params['contact'] ) && is_array( $params['contact'] ) ) {
			$this->update_contact( (int) $conversation->contact_id, $params['contact'] );
		}

		return rest_ensure_response( [
			'conversation' => Presenter::conversation( Conversations::find( (int) $conversation->id ) ),
		] );
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	private function update_contact( int $contact_id, array $fields ): void {
		$contact = Contact::where( 'id', $contact_id )->fresh()->first();
		if ( ! $contact ) {
			return;
		}
		if ( isset( $fields['name'] ) ) {
			$contact->name = mb_substr( sanitize_text_field( (string) $fields['name'] ), 0, 191 );
		}
		if ( isset( $fields['email'] ) ) {
			$email          = sanitize_email( (string) $fields['email'] );
			$contact->email = is_email( $email ) ? strtolower( $email ) : null;
		}
		if ( isset( $fields['phone'] ) ) {
			$contact->phone = mb_substr( preg_replace( '/[^0-9+]/', '', (string) $fields['phone'] ), 0, 50 );
		}
		$contact->save();
	}

	public function search_products( WP_REST_Request $request ) {
		$store = Commerce::store();
		if ( ! $store ) {
			return rest_ensure_response( [
				'store'    => null,
				'products' => [],
			] );
		}
		return rest_ensure_response( [
			'store'    => $store::label(),
			'products' => $store::search( sanitize_text_field( (string) $request->get_param( 'search' ) ), 12 ),
		] );
	}

	public function send_product( WP_REST_Request $request ) {
		$conversation = Conversations::find( (int) $request['id'] );
		if ( ! $conversation ) {
			return $this->not_found();
		}

		$message = Commerce::send_product(
			$conversation,
			(int) $request->get_param( 'product_id' ),
			'agent',
			get_current_user_id(),
			sanitize_textarea_field( (string) $request->get_param( 'message' ) )
		);
		if ( is_wp_error( $message ) ) {
			$message->add_data( [ 'status' => 400 ] );
			return $message;
		}

		return rest_ensure_response( [
			'message'      => Presenter::message( $message ),
			'conversation' => Presenter::conversation( Conversations::find( (int) $conversation->id ) ),
		] );
	}

	public function place_order( WP_REST_Request $request ) {
		$conversation = Conversations::find( (int) $request['id'] );
		if ( ! $conversation ) {
			return $this->not_found();
		}

		$items = [];
		foreach ( (array) $request->get_param( 'items' ) as $item ) {
			if ( is_array( $item ) && ! empty( $item['product_id'] ) ) {
				$items[] = [
					'product_id' => (int) $item['product_id'],
					'qty'        => max( 1, min( 99, (int) ( $item['qty'] ?? 1 ) ) ),
				];
			}
		}

		$customer = [];
		foreach ( [ 'name', 'phone', 'email', 'address', 'city', 'note' ] as $key ) {
			$customer[ $key ] = sanitize_text_field( (string) ( $request->get_param( 'customer' )[ $key ] ?? '' ) );
		}

		$order = Commerce::place_order( $conversation, $items, $customer, 'agent', get_current_user_id() );
		if ( is_wp_error( $order ) ) {
			$order->add_data( [ 'status' => 400 ] );
			return $order;
		}

		// Tell the customer, in the conversation, what was ordered.
		if ( rest_sanitize_boolean( $request->get_param( 'notify' ) ) ) {
			Outbound::send( Conversations::find( (int) $conversation->id ), sprintf(
				/* translators: 1: order number, 2: order total. */
				__( 'Your order #%1$s is confirmed. Total %2$s, to pay on delivery. We will let you know when it ships.', 'zaplane' ),
				$order['number'],
				$order['total_text']
			), [
				'sender_type' => 'agent',
				'sender_id'   => get_current_user_id(),
			] );
		}

		return rest_ensure_response( [
			'order'        => $order,
			'conversation' => Presenter::conversation( Conversations::find( (int) $conversation->id ) ),
		] );
	}

	public function mark_read( WP_REST_Request $request ) {
		$conversation = Conversations::find( (int) $request['id'] );
		if ( ! $conversation ) {
			return $this->not_found();
		}
		Conversations::mark_read( $conversation );
		return rest_ensure_response( [ 'ok' => true ] );
	}

	public function get_settings() {
		return rest_ensure_response( $this->settings_payload() );
	}

	public function save_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: [];
		InboxSettings::save( is_array( $params ) ? $params : [] );
		return rest_ensure_response( $this->settings_payload() );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function settings_payload(): array {
		$connections = [];
		foreach ( Connection::where( 'app', 'ai-agent' )->fresh()->get()->all() as $connection ) {
			$connections[] = [
				'id'   => (int) $connection->id,
				'name' => (string) $connection->name,
			];
		}

		$team = [];
		foreach ( get_users( [
			'capability' => self::capability(),
			'fields'     => [ 'ID', 'display_name' ],
			'number'     => 100,
		] ) as $user ) {
			$team[] = [
				'id'   => (int) $user->ID,
				'name' => (string) $user->display_name,
			];
		}

		$channels = [];
		foreach ( Registry::all() as $slug => $class ) {
			if ( ! is_subclass_of( $class, MetaChannel::class ) ) {
				continue;
			}
			$integration = \Zaplane\Framework\Core\IntegrationLoader::get( $slug );
			$options     = [];
			foreach ( Connection::where( 'app', $slug )->fresh()->get()->all() as $connection ) {
				$options[] = [
					'id'   => (int) $connection->id,
					'name' => (string) $connection->name,
				];
			}
			$channels[ $slug ] = [
				'label'           => $class::label(),
				'connections'     => $options,
				'webhook_url'     => $integration ? $integration::get_webhook_url() : '',
				'has_app_secret'  => $integration ? '' !== $integration::get_webhook_app_secret() : false,
				'has_verify_token' => $integration ? '' !== $integration::get_webhook_verify_token() : false,
			];
		}

		$store = Commerce::store();

		return [
			'settings'       => InboxSettings::get(),
			'ai_connections' => $connections,
			'team'           => $team,
			'site_origin'    => untrailingslashit( home_url() ),
			'channels'       => $channels,
			'store'          => $store ? $store::label() : null,
		];
	}

	public function list_canned() {
		return rest_ensure_response( array_map( static function ( $row ) {
			return [
				'id'       => (int) $row->id,
				'title'    => (string) $row->title,
				'shortcut' => (string) $row->shortcut,
				'body'     => (string) $row->body,
			];
		}, CannedReply::query()->fresh()->orderBy( 'title', 'asc' )->get()->all() ) );
	}

	public function save_canned( WP_REST_Request $request ) {
		$title = trim( sanitize_text_field( (string) $request->get_param( 'title' ) ) );
		$body  = trim( sanitize_textarea_field( (string) $request->get_param( 'body' ) ) );
		if ( '' === $title || '' === $body ) {
			return new WP_Error( 'zaplane_inbox_canned', __( 'A saved reply needs a title and a message.', 'zaplane' ), [ 'status' => 400 ] );
		}

		$data = [
			'title'    => mb_substr( $title, 0, 191 ),
			'shortcut' => mb_substr( sanitize_key( (string) $request->get_param( 'shortcut' ) ), 0, 50 ),
			'body'     => $body,
		];

		$id  = (int) $request->get_param( 'id' );
		$row = $id > 0 ? CannedReply::where( 'id', $id )->fresh()->first() : null;
		if ( $row ) {
			$row->fill( $data );
			$row->save();
		} else {
			$row = CannedReply::create( $data );
		}

		return $this->list_canned();
	}

	public function delete_canned( WP_REST_Request $request ) {
		CannedReply::where( 'id', (int) $request['id'] )->delete();
		return $this->list_canned();
	}

	private function not_found(): WP_Error {
		return new WP_Error( 'zaplane_inbox_not_found', __( 'Conversation not found.', 'zaplane' ), [ 'status' => 404 ] );
	}
}
