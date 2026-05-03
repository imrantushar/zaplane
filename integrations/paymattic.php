<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Paymattic extends IntegrationBase {

	public static function get_slug(): string {
		return 'paymattic';
	}

	public static function get_name(): string {
		return 'Paymattic';
	}

	public static function get_icon(): string {
		return 'paymattic.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'wppayform/after_form_submission_complete',
			],
			'payment_success' => [
				'label' => 'Payment Success',
				'hook'  => 'wppayform/form_payment_success',
			],
			'payment_failed' => [
				'label' => 'Payment Failed',
				'hook'  => 'wppayform/form_payment_failed',
			],
			'payment_status_changed' => [
				'label' => 'Payment Status Changed',
				'hook'  => 'wppayform/after_payment_status_change',
			],
			'payment_refunded' => [
				'label' => 'Payment Refunded',
				'hook'  => 'wppayform/payment_refunded',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( ! in_array( $trigger, [ 'form_submitted', 'payment_success', 'payment_failed', 'payment_status_changed', 'payment_refunded' ], true ) ) {
			return [];
		}

		$fields = [
			[
				'key'      => 'form_id',
				'label'    => 'Form',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'paymattic',
					'query'       => 'forms',
					'select'      => [ 'name', 'label' ],
				],
				'required' => true,
			],
		];

		if ( 'payment_status_changed' === $trigger ) {
			$fields[] = [
				'key'      => 'payment_status',
				'label'    => 'Payment Status',
				'type'     => 'select',
				'options'  => [
					[ 'label' => 'Any Status', 'value' => 'any' ],
					[ 'label' => 'Paid', 'value' => 'paid' ],
					[ 'label' => 'Pending', 'value' => 'pending' ],
					[ 'label' => 'Failed', 'value' => 'failed' ],
					[ 'label' => 'Refunded', 'value' => 'refunded' ],
					[ 'label' => 'Partially Refunded', 'value' => 'partially_refunded' ],
				],
				'required' => false,
			];
		}

		return $fields;
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event  = self::node_event( $node );
		$config = self::node_config( $node );

		switch ( $event ) {
			case 'form_submitted':
				return self::resolve_form_submitted( $args, $config );
			case 'payment_success':
				return self::resolve_payment_success( $args, $config );
			case 'payment_failed':
				return self::resolve_payment_failed( $args, $config );
			case 'payment_status_changed':
				return self::resolve_payment_status_changed( $args, $config );
			case 'payment_refunded':
				return self::resolve_payment_refunded( $args, $config );
		}

		return false;
	}

	public static function get_actions(): array {
		return [
			'get_form_single' => [
				'label' => 'Get Form (Single)',
			],
			'get_forms_all' => [
				'label' => 'Get Forms (All)',
			],
			'get_submission_single' => [
				'label' => 'Get Submission (Single)',
			],
			'get_submissions_all' => [
				'label' => 'Get Submissions (All)',
			],
			'update_submission_payment_status' => [
				'label' => 'Update Submission Payment Status',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'get_form_single' => [
				[
					'key'      => 'form_id',
					'label'    => 'Form',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'paymattic',
						'query'       => 'forms',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_forms_all' => [
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'default' => 50,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
				],
			],
			'get_submission_single' => [
				[
					'key'      => 'submission_id',
					'label'    => 'Submission',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'paymattic',
						'query'       => 'submissions',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_submissions_all' => [
				[
					'key'      => 'form_id',
					'label'    => 'Form',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'paymattic',
						'query'       => 'forms',
						'select'      => [ 'name', 'label' ],
					],
					'required' => false,
				],
				[
					'key'      => 'payment_status',
					'label'    => 'Payment Status',
					'type'     => 'select',
					'options'  => [
						[ 'label' => 'Any Status', 'value' => 'any' ],
						[ 'label' => 'Paid', 'value' => 'paid' ],
						[ 'label' => 'Pending', 'value' => 'pending' ],
						[ 'label' => 'Failed', 'value' => 'failed' ],
						[ 'label' => 'Refunded', 'value' => 'refunded' ],
					],
					'required' => false,
				],
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'default' => 20,
				],
				[
					'key'     => 'page',
					'label'   => 'Page',
					'type'    => 'number',
					'default' => 1,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
				],
			],
			'update_submission_payment_status' => [
				[
					'key'      => 'submission_id',
					'label'    => 'Submission',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'paymattic',
						'query'       => 'submissions',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'payment_status',
					'label'    => 'Payment Status',
					'type'     => 'select',
					'options'  => [
						[ 'label' => 'Paid', 'value' => 'paid' ],
						[ 'label' => 'Pending', 'value' => 'pending' ],
						[ 'label' => 'Failed', 'value' => 'failed' ],
						[ 'label' => 'Refunded', 'value' => 'refunded' ],
						[ 'label' => 'Partially Refunded', 'value' => 'partially_refunded' ],
					],
					'required' => true,
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = self::node_event( $node );
		$config = self::node_config( $node );

		if ( ! self::is_paymattic_available() ) {
			return [
				'port' => 'error',
				'data' => array_merge(
					$input,
					[
						'error' => 'Paymattic is not available',
					]
				),
			];
		}

		switch ( $action ) {
			case 'get_form_single':
				return self::action_get_form_single( $config, $input );
			case 'get_forms_all':
				return self::action_get_forms_all( $config, $input );
			case 'get_submission_single':
				return self::action_get_submission_single( $config, $input );
			case 'get_submissions_all':
				return self::action_get_submissions_all( $config, $input );
			case 'update_submission_payment_status':
				return self::action_update_submission_payment_status( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'forms' => [ self::class, 'query_forms' ],
			'submissions' => [ self::class, 'query_submissions' ],
		];
	}

	public static function query_forms( $q = [] ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'value' => 'any',
				'label' => 'Any Form',
			],
		];

		$forms = get_posts(
			[
				'post_type'      => 'wp_payform',
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => 200,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			]
		);

		foreach ( $forms as $form ) {
			$options[] = [
				'name'  => (string) ( $form->ID ?? 0 ),
				'value' => (string) ( $form->ID ?? 0 ),
				'label' => (string) ( $form->post_title ?? 'Form' ) . ' (#' . (int) ( $form->ID ?? 0 ) . ')',
			];
		}

		return $options;
	}

	public static function query_submissions( $q = [] ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'value' => 'any',
				'label' => 'Any Submission',
			],
		];

		$limit   = max( 1, (int) ( $q['limit'] ?? 50 ) );
		$page    = max( 1, (int) ( $q['page'] ?? 1 ) );
		$search  = trim( (string) ( $q['search'] ?? '' ) );
		$form_id = self::to_int( $q['form_id'] ?? 0 );

		$rows = self::list_submissions( $form_id, 'any', $limit, $page, $search );
		foreach ( $rows as $row ) {
			$submission_id = self::to_int( $row['id'] ?? 0 );
			if ( $submission_id <= 0 ) {
				continue;
			}

			$status = sanitize_key( (string) ( $row['payment_status'] ?? '' ) );
			$label  = '#' . $submission_id;
			if ( '' !== $status ) {
				$label .= ' - ' . ucfirst( str_replace( '_', ' ', $status ) );
			}

			$options[] = [
				'name'  => (string) $submission_id,
				'value' => (string) $submission_id,
				'label' => $label,
			];
		}

		return $options;
	}

	public static function get_output_ports(): array {
		return [
			'main' => 'Main output',
			'error' => 'Error output',
		];
	}

	private static function resolve_form_submitted( array $args, array $config ) {
		$submission = self::normalize_value( $args[0] ?? [] );
		$form_id    = self::to_int( $args[1] ?? ( $submission['form_id'] ?? 0 ) );

		if ( ! self::match_form_id( $config, $form_id ) ) {
			return false;
		}

		return [
			'event'         => 'form_submitted',
			'submission_id' => self::to_int( $submission['id'] ?? 0 ),
			'form_id'       => $form_id,
			'submission'    => $submission,
		];
	}

	private static function resolve_payment_success( array $args, array $config ) {
		$submission = self::normalize_value( $args[0] ?? [] );
		$transaction = self::normalize_value( $args[1] ?? [] );
		$form_id    = self::to_int( $args[2] ?? ( $submission['form_id'] ?? ( $transaction['form_id'] ?? 0 ) ) );

		if ( ! self::match_form_id( $config, $form_id ) ) {
			return false;
		}

		return [
			'event'         => 'payment_success',
			'submission_id' => self::to_int( $submission['id'] ?? ( $transaction['submission_id'] ?? 0 ) ),
			'form_id'       => $form_id,
			'submission'    => $submission,
			'transaction'   => $transaction,
			'update_data'   => self::normalize_value( $args[3] ?? [] ),
		];
	}

	private static function resolve_payment_failed( array $args, array $config ) {
		$submission_arg = $args[0] ?? null;
		$submission     = [];
		$submission_id  = 0;
		$form_id        = 0;

		if ( is_scalar( $submission_arg ) ) {
			$submission_id = self::to_int( $submission_arg );
			$submission    = self::get_submission_by_id( $submission_id );
			$form_id       = self::to_int( $submission['form_id'] ?? 0 );
		} else {
			$submission    = self::normalize_value( $submission_arg );
			$submission_id = self::to_int( $submission['id'] ?? 0 );
			$form_id       = self::to_int( $args[1] ?? ( $submission['form_id'] ?? 0 ) );
		}

		if ( ! self::match_form_id( $config, $form_id ) ) {
			return false;
		}

		$status = '';
		if ( isset( $args[1] ) && is_string( $args[1] ) ) {
			$status = sanitize_key( $args[1] );
		} elseif ( isset( $args[3] ) && is_string( $args[3] ) ) {
			$status = sanitize_key( $args[3] );
		}
		if ( '' === $status ) {
			$status = 'failed';
		}

		return [
			'event'         => 'payment_failed',
			'submission_id' => $submission_id,
			'form_id'       => $form_id,
			'status'        => $status,
			'submission'    => $submission,
			'transaction'   => self::normalize_value( $args[2] ?? [] ),
			'raw_args'      => self::normalize_value( $args ),
		];
	}

	private static function resolve_payment_status_changed( array $args, array $config ) {
		$submission_id = self::to_int( $args[0] ?? 0 );
		$status        = sanitize_key( (string) ( $args[1] ?? '' ) );
		$submission    = self::get_submission_by_id( $submission_id );
		$form_id       = self::to_int( $submission['form_id'] ?? 0 );

		if ( ! self::match_form_id( $config, $form_id ) ) {
			return false;
		}

		$config_status = sanitize_key( (string) ( $config['payment_status'] ?? 'any' ) );
		if ( '' !== $config_status && 'any' !== $config_status && $config_status !== $status ) {
			return false;
		}

		return [
			'event'         => 'payment_status_changed',
			'submission_id' => $submission_id,
			'form_id'       => $form_id,
			'payment_status'=> $status,
			'submission'    => $submission,
		];
	}

	private static function resolve_payment_refunded( array $args, array $config ) {
		$refund     = self::normalize_value( $args[0] ?? [] );
		$form_id    = self::to_int( $args[1] ?? ( $refund['form_id'] ?? 0 ) );
		$submission_id = self::to_int( $refund['submission_id'] ?? 0 );
		$submission = self::get_submission_by_id( $submission_id );
		if ( 0 === $form_id ) {
			$form_id = self::to_int( $submission['form_id'] ?? 0 );
		}

		if ( ! self::match_form_id( $config, $form_id ) ) {
			return false;
		}

		return [
			'event'         => 'payment_refunded',
			'submission_id' => $submission_id,
			'form_id'       => $form_id,
			'refund'        => $refund,
			'submission'    => $submission,
			'charge'        => self::normalize_value( $args[2] ?? [] ),
		];
	}

	private static function get_submission_by_id( int $submission_id ): array {
		if ( $submission_id <= 0 ) {
			return [];
		}

		$submission_class = '\\WPPayForm\\App\\Models\\Submission';
		if ( class_exists( $submission_class ) ) {
			try {
				$model = new $submission_class();
				if ( method_exists( $model, 'getSubmission' ) ) {
					$submission = $model->getSubmission( $submission_id );
					return self::normalize_value( $submission );
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return [];
		}

		$table = $wpdb->prefix . 'wpf_submissions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $submission_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return [];
		}

		return self::normalize_value( $row );
	}

	private static function match_form_id( array $config, int $form_id ): bool {
		$selected = $config['form_id'] ?? 'any';
		if ( is_array( $selected ) ) {
			$selected = $selected['name'] ?? ( $selected['value'] ?? 'any' );
		}

		if ( ! is_scalar( $selected ) ) {
			return true;
		}

		$selected = trim( (string) $selected );
		if ( '' === $selected || 'any' === strtolower( $selected ) ) {
			return true;
		}

		$selected_id = self::to_int( $selected );
		if ( $selected_id <= 0 ) {
			return true;
		}

		return $selected_id === $form_id;
	}

	private static function action_get_form_single( array $config, array $input ): array {
		$form_id = self::resolve_action_id( $config, $input, 'form_id' );
		if ( $form_id <= 0 ) {
			return self::error_response( 'Form ID is required', $input );
		}

		$form = self::get_form_by_id( $form_id );
		if ( empty( $form ) ) {
			return self::error_response( 'Form not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'form_id' => $form_id,
					'form'    => $form,
				]
			)
		);
	}

	private static function action_get_forms_all( array $config, array $input ): array {
		$limit  = max( 1, (int) ( $config['limit'] ?? 50 ) );
		$search = trim( (string) ( $config['search'] ?? '' ) );
		$forms  = self::list_forms( $limit, $search );

		return self::main_response(
			array_merge(
				$input,
				[
					'items' => $forms,
					'total' => count( $forms ),
					'limit' => $limit,
				]
			)
		);
	}

	private static function action_get_submission_single( array $config, array $input ): array {
		$submission_id = self::resolve_action_id( $config, $input, 'submission_id' );
		if ( $submission_id <= 0 ) {
			return self::error_response( 'Submission ID is required', $input );
		}

		$submission = self::get_submission_by_id( $submission_id );
		if ( empty( $submission ) ) {
			return self::error_response( 'Submission not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'submission_id' => $submission_id,
					'submission'    => $submission,
				]
			)
		);
	}

	private static function action_get_submissions_all( array $config, array $input ): array {
		$form_id        = self::to_int( $config['form_id'] ?? 0 );
		$payment_status = sanitize_key( (string) ( $config['payment_status'] ?? 'any' ) );
		$limit          = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page           = max( 1, (int) ( $config['page'] ?? 1 ) );
		$search         = trim( (string) ( $config['search'] ?? '' ) );

		$items = self::list_submissions( $form_id, $payment_status, $limit, $page, $search );

		return self::main_response(
			array_merge(
				$input,
				[
					'items'          => $items,
					'total'          => count( $items ),
					'form_id'        => $form_id,
					'payment_status' => $payment_status,
					'limit'          => $limit,
					'page'           => $page,
				]
			)
		);
	}

	private static function action_update_submission_payment_status( array $config, array $input ): array {
		$submission_id = self::resolve_action_id( $config, $input, 'submission_id' );
		if ( $submission_id <= 0 ) {
			return self::error_response( 'Submission ID is required', $input );
		}

		$payment_status = sanitize_key( (string) ( $config['payment_status'] ?? '' ) );
		if ( '' === $payment_status ) {
			return self::error_response( 'Payment status is required', $input );
		}

		$updated = self::update_submission_status( $submission_id, $payment_status );
		if ( ! $updated ) {
			return self::error_response( 'Submission status update failed', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'submission_id' => $submission_id,
					'payment_status'=> $payment_status,
					'updated'       => true,
					'submission'    => self::get_submission_by_id( $submission_id ),
				]
			)
		);
	}

	private static function is_paymattic_available(): bool {
		return defined( 'WPPAYFORM_VERSION' ) || class_exists( '\\WPPayForm\\App\\Models\\Form' );
	}

	private static function resolve_action_id( array $config, array $input, string $key ): int {
		$id = self::to_int( $config[ $key ] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		$id = self::to_int( $input[ $key ] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		return 0;
	}

	private static function list_forms( int $limit, string $search ): array {
		$args = [
			'post_type'      => 'wp_payform',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		];

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$forms = get_posts( $args );
		$items = [];
		foreach ( $forms as $form ) {
			$items[] = [
				'id'         => self::to_int( $form->ID ?? 0 ),
				'post_title' => (string) ( $form->post_title ?? '' ),
				'post_status'=> (string) ( $form->post_status ?? '' ),
				'post_type'  => (string) ( $form->post_type ?? '' ),
				'post_date'  => (string) ( $form->post_date ?? '' ),
			];
		}

		return $items;
	}

	private static function get_form_by_id( int $form_id ): array {
		if ( $form_id <= 0 ) {
			return [];
		}

		$post = get_post( $form_id );
		if ( ! $post || 'wp_payform' !== (string) ( $post->post_type ?? '' ) ) {
			return [];
		}

		return [
			'id'         => self::to_int( $post->ID ?? 0 ),
			'post_title' => (string) ( $post->post_title ?? '' ),
			'post_status'=> (string) ( $post->post_status ?? '' ),
			'post_type'  => (string) ( $post->post_type ?? '' ),
			'post_date'  => (string) ( $post->post_date ?? '' ),
		];
	}

	private static function list_submissions( int $form_id, string $payment_status, int $limit, int $page, string $search ): array {
		$payment_status = sanitize_key( $payment_status );
		if ( '' === $payment_status ) {
			$payment_status = 'any';
		}

		$submission_class = '\\WPPayForm\\App\\Models\\Submission';
		if ( class_exists( $submission_class ) ) {
			try {
				$model  = new $submission_class();
				$skip   = max( 0, ( $page - 1 ) * $limit );
				$wheres = [];
				if ( 'any' !== $payment_status ) {
					$wheres['payment_status'] = $payment_status;
				}

				if ( method_exists( $model, 'getAll' ) ) {
					$result = $model->getAll( $form_id > 0 ? $form_id : false, $wheres, $limit, $skip, 'DESC', $search );
					$items  = self::normalize_value( $result->items ?? [] );

					return is_array( $items ) ? array_values( $items ) : [];
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return [];
		}

		$table = $wpdb->prefix . 'wpf_submissions';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT " . (int) $limit, ARRAY_A );
		$rows  = is_array( $rows ) ? $rows : [];
		return self::normalize_value( $rows );
	}

	private static function update_submission_status( int $submission_id, string $payment_status ): bool {
		if ( $submission_id <= 0 || '' === $payment_status ) {
			return false;
		}

		$submission_class = '\\WPPayForm\\App\\Models\\Submission';
		if ( class_exists( $submission_class ) ) {
			try {
				$model = new $submission_class();
				if ( method_exists( $model, 'updateSubmission' ) ) {
					$updated = $model->updateSubmission(
						$submission_id,
						[
							'payment_status' => $payment_status,
						]
					);

					return ! empty( $updated );
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		global $wpdb;
		if ( ! $wpdb ) {
			return false;
		}

		$table = $wpdb->prefix . 'wpf_submissions';
		$rows  = $wpdb->update(
			$table,
			[
				'payment_status' => $payment_status,
				'updated_at'     => current_time( 'mysql' ),
			],
			[ 'id' => $submission_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $rows;
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
		return [
			'port' => 'error',
			'data' => array_merge(
				$input,
				[
					'error' => $message,
				]
			),
		];
	}

	private static function node_event( array $node ): string {
		$candidates = [
			$node['event'] ?? null,
			$node['action'] ?? null,
			$node['trigger'] ?? null,
			$node['data']['event'] ?? null,
			$node['data']['action'] ?? null,
			$node['data']['trigger'] ?? null,
			$node['config']['event'] ?? null,
			$node['config']['action'] ?? null,
			$node['config']['trigger'] ?? null,
		];

		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}

			$candidate = trim( $candidate );
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	private static function node_config( array $node ): array {
		if ( isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			return $node['data']['config'];
		}

		if ( isset( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
			return $node['config']['data'];
		}

		if ( isset( $node['config']['config'] ) && is_array( $node['config']['config'] ) ) {
			return $node['config']['config'];
		}

		if ( isset( $node['config'] ) && is_array( $node['config'] ) ) {
			$config = $node['config'];
			unset( $config['event'], $config['action'], $config['trigger'] );
			return $config;
		}

		if ( isset( $node['data'] ) && is_array( $node['data'] ) ) {
			$config = $node['data'];
			unset( $config['app'], $config['label'], $config['icon'], $config['event'], $config['action'], $config['trigger'], $config['connection_id'] );
			return $config;
		}

		return [];
	}

	private static function to_int( $value ): int {
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (int) $value;
			return $value > 0 ? $value : 0;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value || 'any' === strtolower( $value ) ) {
				return 0;
			}
			if ( is_numeric( $value ) ) {
				$value = (int) $value;
				return $value > 0 ? $value : 0;
			}
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'id', 'ID', 'value', 'name', 'form_id', 'submission_id' ] as $key ) {
				if ( isset( $value[ $key ] ) ) {
					return self::to_int( $value[ $key ] );
				}
			}
		}

		if ( is_object( $value ) ) {
			foreach ( [ 'id', 'ID', 'value', 'name', 'form_id', 'submission_id' ] as $key ) {
				if ( isset( $value->{$key} ) ) {
					return self::to_int( $value->{$key} );
				}
			}
		}

		return 0;
	}

	private static function normalize_value( $value ) {
		if ( null === $value || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::normalize_value( $item );
			}

			return $value;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'toArray' ) ) {
				return self::normalize_value( $value->toArray() );
			}

			return self::normalize_value( get_object_vars( $value ) );
		}

		return [];
	}
}
