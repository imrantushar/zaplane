<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Human-in-the-Loop approval node.
 *
 * Pauses the workflow run and asks a person to approve or reject before it
 * continues. The approver receives an email (and/or Slack message) with signed
 * approve/reject links; clicking one resumes the run down the matching output
 * branch. An optional timeout auto-resolves the request if nobody responds.
 *
 * Pausing reuses the same mechanism as the Delay node: execute_node returns a
 * `delayed` status, and the run is later resumed via
 * Automation::resume_delayed_run() — here triggered by the HitlController
 * endpoint (human click) or the Scheduler (timeout).
 */
class HumanApproval extends IntegrationBase {

	public const REST_ROUTE = 'zaplane/v1/hitl/respond';

	public static function get_slug(): string {
		return 'human_approval';
	}

	public static function get_name(): string {
		return 'Human Approval';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'condition';
	}

	public static function get_actions(): array {
		return [
			'request_approval' => [ 'label' => 'Request Approval' ],
		];
	}

	/**
	 * Two branches: what runs when approved, and when rejected.
	 */
	public static function get_output_ports(): array {
		return [ 'approved', 'rejected' ];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'      => 'approver_emails',
				'label'    => 'Approver email(s)',
				'type'     => 'expression',
				'required' => true,
				'help'     => 'Comma-separated. Supports variables, e.g. {{ manager_email }}.',
			],
			[
				'key'      => 'subject',
				'label'    => 'Email subject',
				'type'     => 'expression',
				'required' => true,
			],
			[
				'key'      => 'message',
				'label'    => 'Message',
				'type'     => 'textarea',
				'required' => true,
				'help'     => 'Shown to the approver. Supports variables.',
			],
			[
				'key'     => 'approve_label',
				'label'   => 'Approve button label',
				'type'    => 'text',
				'default' => 'Approve',
			],
			[
				'key'     => 'reject_label',
				'label'   => 'Reject button label',
				'type'    => 'text',
				'default' => 'Reject',
			],
			[
				'key'     => 'allow_reject',
				'label'   => 'Show a Reject button',
				'type'    => 'checkbox',
				'default' => true,
			],
			[
				'key'   => 'slack_webhook',
				'label' => 'Slack incoming webhook URL (optional)',
				'type'  => 'text',
				'help'  => 'If set, the request is also posted to Slack with the same links.',
			],
			[
				'key'     => 'timeout_amount',
				'label'   => 'Timeout (0 = wait forever)',
				'type'    => 'number',
				'default' => 0,
			],
			[
				'key'     => 'timeout_unit',
				'label'   => 'Timeout unit',
				'type'    => 'select',
				'default' => 'days',
				'options' => [
					[ 'value' => 'hours', 'label' => 'Hours' ],
					[ 'value' => 'days', 'label' => 'Days' ],
				],
			],
			[
				'key'     => 'on_timeout',
				'label'   => 'If it times out',
				'type'    => 'select',
				'default' => 'rejected',
				'options' => [
					[ 'value' => 'approved', 'label' => 'Treat as approved' ],
					[ 'value' => 'rejected', 'label' => 'Treat as rejected' ],
				],
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$run_id      = (int) ( $node['_run_id'] ?? 0 );
		$node_run_id = (int) ( $node['_node_run_id'] ?? 0 );
		$node_key    = (int) ( $node['id'] ?? 0 );
		$config      = $node['data']['config'] ?? [];

		// No run context (e.g. a test invocation) — can't pause, so pass through.
		if ( ! $run_id || ! $node_run_id ) {
			return [ 'port' => 'approved', 'data' => array_merge( $input, [ 'approval_status' => 'approved' ] ) ];
		}

		$timeout = self::timeout_seconds( $config );
		// Link validity: the timeout, or 30 days when waiting indefinitely.
		$expires = time() + ( $timeout > 0 ? $timeout : 30 * DAY_IN_SECONDS );

		$approve_url = self::response_url( $run_id, $node_run_id, $node_key, 'approved', $expires );
		$reject_url  = self::response_url( $run_id, $node_run_id, $node_key, 'rejected', $expires );

		self::notify( $config, $approve_url, $reject_url );

		// Schedule the timeout resolution so the run never hangs forever.
		if ( $timeout > 0 ) {
			$port = 'approved' === ( $config['on_timeout'] ?? 'rejected' ) ? 'approved' : 'rejected';
			Scheduler::enqueue(
				$expires,
				$run_id,
				$node_run_id,
				$node_key,
				[ 'port' => $port, 'data' => array_merge( $input, [ 'approval_status' => 'timeout' ] ) ]
			);
		}

		return [
			'port'   => '__halt__',
			'status' => 'delayed',
			'data'   => [],
		];
	}

	/**
	 * Resolve the configured timeout to seconds (0 = wait forever).
	 *
	 * @param array<string,mixed> $config
	 */
	protected static function timeout_seconds( array $config ): int {
		$amount = (int) ( $config['timeout_amount'] ?? 0 );
		if ( $amount <= 0 ) {
			return 0;
		}
		$unit = ( $config['timeout_unit'] ?? 'days' ) === 'hours' ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
		return $amount * $unit;
	}

	/**
	 * A signed, self-contained response URL. The signature covers the run/node
	 * identity, expiry, and decision so links can't be forged or flipped, and no
	 * server-side pending record is needed.
	 */
	public static function response_url( int $run_id, int $node_run_id, int $node_key, string $decision, int $expires ): string {
		$args = [
			'run'      => $run_id,
			'nr'       => $node_run_id,
			'nk'       => $node_key,
			'exp'      => $expires,
			'decision' => $decision,
		];
		$args['sig'] = self::sign( $args );

		return add_query_arg( $args, rest_url( self::REST_ROUTE ) );
	}

	/**
	 * HMAC of the response params, keyed by the site's auth salt.
	 *
	 * @param array<string,mixed> $args
	 */
	public static function sign( array $args ): string {
		$payload = implode(
			':',
			[ (int) $args['run'], (int) $args['nr'], (int) $args['nk'], (int) $args['exp'], (string) $args['decision'] ]
		);
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Notify the approver(s) over the configured channels.
	 *
	 * @param array<string,mixed> $config
	 */
	protected static function notify( array $config, string $approve_url, string $reject_url ): void {
		$subject       = (string) ( $config['subject'] ?? '' ) ?: __( 'Approval required', 'zaplane' );
		$message       = (string) ( $config['message'] ?? '' );
		$approve_label = (string) ( $config['approve_label'] ?? '' ) ?: __( 'Approve', 'zaplane' );
		$reject_label  = (string) ( $config['reject_label'] ?? '' ) ?: __( 'Reject', 'zaplane' );
		$allow_reject  = ! empty( $config['allow_reject'] ) || ! isset( $config['allow_reject'] );

		$emails = array_filter( array_map( 'trim', explode( ',', (string) ( $config['approver_emails'] ?? '' ) ) ) );
		if ( ! empty( $emails ) ) {
			wp_mail(
				$emails,
				$subject,
				self::email_html( $message, $approve_url, $approve_label, $reject_url, $reject_label, $allow_reject ),
				[ 'Content-Type: text/html; charset=UTF-8' ]
			);
		}

		$slack = trim( (string) ( $config['slack_webhook'] ?? '' ) );
		if ( '' !== $slack ) {
			$text = $message . "\n\n" . $approve_label . ': ' . $approve_url;
			if ( $allow_reject ) {
				$text .= "\n" . $reject_label . ': ' . $reject_url;
			}
			wp_remote_post(
				$slack,
				[
					'headers' => [ 'Content-Type' => 'application/json' ],
					'body'    => wp_json_encode( [ 'text' => $text ] ),
					'timeout' => 15,
				]
			);
		}
	}

	/**
	 * Build the approval email body with inline-styled buttons (email-client safe).
	 */
	protected static function email_html( string $message, string $approve_url, string $approve_label, string $reject_url, string $reject_label, bool $allow_reject ): string {
		$btn = 'display:inline-block;padding:11px 22px;border-radius:6px;color:#fff;text-decoration:none;font-weight:600;font-family:sans-serif;font-size:14px;';

		$buttons = '<a href="' . esc_url( $approve_url ) . '" style="' . esc_attr( $btn . 'background:#16a34a;' ) . '">' . esc_html( $approve_label ) . '</a>';
		if ( $allow_reject ) {
			$buttons .= '&nbsp;&nbsp;<a href="' . esc_url( $reject_url ) . '" style="' . esc_attr( $btn . 'background:#dc2626;' ) . '">' . esc_html( $reject_label ) . '</a>';
		}

		return '<div style="font-family:sans-serif;font-size:15px;color:#1f2937;line-height:1.6;">'
			. '<p>' . nl2br( esc_html( $message ) ) . '</p>'
			. '<p style="margin-top:24px;">' . $buttons . '</p>'
			. '</div>';
	}
}
