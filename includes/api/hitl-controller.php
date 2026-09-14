<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Core\Automation;
use Zaplane\Integrations\HumanApproval;
use Zaplane\Models\NodeRun;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles approve/reject link clicks for the Human Approval node.
 *
 * The endpoint is public (approvers aren't logged in) — security comes entirely
 * from the HMAC signature on the link, which covers the run/node identity,
 * expiry, and decision. Opening the link only asks: mail scanners and link
 * previews fetch every URL in a message, so a GET that decided would approve on
 * the approver's behalf before anyone read it. The confirmation form posts the
 * same signed fields back, and only that resumes the run.
 */
class HitlController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'hitl';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/respond', [
			'methods'             => [ WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ],
			'callback'            => [ $this, 'respond' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'run'      => [ 'required' => true ],
				'nr'       => [ 'required' => true ],
				'nk'       => [ 'required' => true ],
				'exp'      => [ 'required' => true ],
				'decision' => [ 'required' => true ],
				'sig'      => [ 'required' => true ],
			],
		] );
	}

	public function respond( WP_REST_Request $request ) {
		$run      = (int) $request['run'];
		$nr       = (int) $request['nr'];
		$nk       = (int) $request['nk'];
		$exp      = (int) $request['exp'];
		$decision = (string) $request['decision'];
		$sig      = (string) $request['sig'];

		if ( ! in_array( $decision, [ 'approved', 'rejected' ], true ) ) {
			return self::page( __( 'Invalid approval request.', 'zaplane' ), false );
		}

		$expected = HumanApproval::sign( compact( 'run', 'nr', 'nk', 'exp', 'decision' ) );
		if ( ! hash_equals( $expected, $sig ) ) {
			return self::page( __( 'This approval link is invalid.', 'zaplane' ), false );
		}

		if ( time() > $exp ) {
			return self::page( __( 'This approval link has expired.', 'zaplane' ), false );
		}

		$node_run = NodeRun::find( $nr );
		if ( ! $node_run || 'delayed' !== $node_run->status ) {
			return self::page( __( 'This request has already been handled.', 'zaplane' ), true );
		}

		if ( 'POST' !== $request->get_method() ) {
			self::confirm_page( compact( 'run', 'nr', 'nk', 'exp', 'decision', 'sig' ) );
		}

		$automation = $this->container ? $this->container->get( 'automation' ) : Automation::get_instance();
		if ( $automation ) {
			$automation->resume_delayed_run( $run, $nr, $nk, [
				'port' => $decision,
				'data' => [
					'approval_status' => $decision,
					'decided_at'      => current_time( 'mysql' ),
				],
			] );
		}

		$message = 'approved' === $decision
			? __( 'You have approved this request. The workflow will now continue.', 'zaplane' )
			: __( 'You have rejected this request.', 'zaplane' );

		return self::page( $message, true );
	}

	/**
	 * Ask before deciding, with a form that posts the signed fields back.
	 *
	 * @param array<string,int|string> $fields The signed link's parameters.
	 */
	protected static function confirm_page( array $fields ): void {
		$approve = 'approved' === $fields['decision'];
		$inputs  = '';
		foreach ( $fields as $name => $value ) {
			$inputs .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}

		$html = '<div style="font-family:sans-serif;max-width:440px;margin:12vh auto;text-align:center;">'
			. '<p style="font-size:16px;color:#1f2937;">' . esc_html( $approve ? __( 'Approve this request?', 'zaplane' ) : __( 'Reject this request?', 'zaplane' ) ) . '</p>'
			. '<form method="post" action="' . esc_url( rest_url( HumanApproval::REST_ROUTE ) ) . '">'
			. $inputs
			. '<button type="submit" style="font-size:15px;padding:10px 22px;border-radius:6px;border:0;color:#fff;background:' . esc_attr( $approve ? '#16a34a' : '#dc2626' ) . ';">'
			. esc_html( $approve ? __( 'Approve', 'zaplane' ) : __( 'Reject', 'zaplane' ) )
			. '</button></form></div>';

		wp_die(
			wp_kses(
				$html,
				[
					'div'    => [ 'style' => true ],
					'p'      => [ 'style' => true ],
					'form'   => [ 'method' => true, 'action' => true ],
					'input'  => [ 'type' => true, 'name' => true, 'value' => true ],
					'button' => [ 'type' => true, 'style' => true ],
				]
			),
			esc_html__( 'Zaplane Approval', 'zaplane' ),
			[ 'response' => 200 ]
		);
	}

	/**
	 * Render a minimal confirmation page for the email-link click and end the
	 * request. Uses wp_die so the approver gets a styled standalone page rather
	 * than a JSON blob.
	 */
	protected static function page( string $message, bool $ok ): void {
		$color = $ok ? '#16a34a' : '#dc2626';
		$html  = '<div style="font-family:sans-serif;max-width:440px;margin:12vh auto;text-align:center;">'
			. '<div style="font-size:44px;line-height:1;color:' . esc_attr( $color ) . ';margin-bottom:12px;">'
			. ( $ok ? '&#10003;' : '&#10007;' ) . '</div>'
			. '<p style="font-size:16px;color:#1f2937;">' . esc_html( $message ) . '</p>'
			. '</div>';

		wp_die(
			wp_kses_post( $html ),
			esc_html__( 'Zaplane Approval', 'zaplane' ),
			[ 'response' => $ok ? 200 : 400 ]
		);
	}
}
