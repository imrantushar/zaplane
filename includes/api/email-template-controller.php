<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailTemplateController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/email-templates', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'page'     => [
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/email-templates/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_items( $request ) {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$total = EmailTemplate::count();
		$items = EmailTemplate::orderBy( 'updated_at', 'desc' )
			->forPage( $page, $perPage )
			->get()
			->map( fn( $t ) => $t->toResponse() )
			->toArray();

		return rest_ensure_response( [
			'data'       => $items,
			'pagination' => [
				'page'        => $page,
				'per_page'    => $perPage,
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		] );
	}

	public function get_item( $request ) {
		$template = EmailTemplate::find( (int) $request['id'] );
		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Email template not found.', [ 'status' => 404 ] );
		}

		return rest_ensure_response( $template->toResponse() );
	}

	public function create_item( $request ) {
		$params = $request->get_json_params() ?? [];

		$template = EmailTemplate::create( [
			'title'      => isset( $params['title'] ) && '' !== trim( (string) $params['title'] )
				? sanitize_text_field( $params['title'] )
				: __( 'Untitled template', 'zaplane' ),
			'subject'    => isset( $params['subject'] ) ? sanitize_text_field( $params['subject'] ) : null,
			'pre_header' => isset( $params['pre_header'] ) ? sanitize_text_field( $params['pre_header'] ) : null,
			'content'    => isset( $params['content'] )
				? ( is_string( $params['content'] ) ? $params['content'] : wp_json_encode( $params['content'] ) )
				: null,
			'created_by' => get_current_user_id(),
		] );

		return rest_ensure_response( $template->toResponse() );
	}

	public function update_item( $request ) {
		$template = EmailTemplate::find( (int) $request['id'] );
		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Email template not found.', [ 'status' => 404 ] );
		}

		$params = $request->get_json_params() ?? [];

		if ( isset( $params['title'] ) ) {
			$template->title = sanitize_text_field( $params['title'] );
		}
		if ( array_key_exists( 'subject', $params ) ) {
			$template->subject = $params['subject'] ? sanitize_text_field( $params['subject'] ) : null;
		}
		if ( array_key_exists( 'pre_header', $params ) ) {
			$template->pre_header = $params['pre_header'] ? sanitize_text_field( $params['pre_header'] ) : null;
		}
		if ( array_key_exists( 'content', $params ) ) {
			// Content is the builder's JSON tree; store it verbatim as a string.
			$template->content = is_string( $params['content'] )
				? $params['content']
				: wp_json_encode( $params['content'] );
		}

		$template->save();

		return rest_ensure_response( $template->toResponse() );
	}

	public function delete_item( $request ) {
		$template = EmailTemplate::find( (int) $request['id'] );
		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Email template not found.', [ 'status' => 404 ] );
		}

		$template->delete();

		return rest_ensure_response( [ 'deleted' => true, 'id' => (int) $request['id'] ] );
	}
}
