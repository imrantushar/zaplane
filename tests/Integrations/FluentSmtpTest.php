<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\FluentSmtp;

class FluentSmtpTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return FluentSmtp::class;
	}

	protected function getTriggerTests(): array {
		return [
			'email_sent' => [
				[
					'to'          => [ 'jane@example.com', 'team@example.com' ],
					'subject'     => 'Welcome',
					'message'     => '<p>Hello Jane</p>',
					'headers'     => [ 'Content-Type: text/html; charset=UTF-8' ],
					'attachments' => [ '/tmp/file.pdf' ],
				],
			],
			'email_failed' => [
				new \WP_Error(
					'wp_mail_failed',
					'SMTP authentication failed',
					[
						'to'          => [ 'jane@example.com' ],
						'subject'     => 'Broken email',
						'message'     => 'Unable to send',
						'headers'     => [ 'Content-Type: text/plain; charset=UTF-8' ],
						'attachments' => [],
					]
				),
			],
			'delivery_failed_no_fallback' => [
				91,
				$this->makeHandler( 'gmail' ),
				[
					'to'          => serialize(
						[
							[ 'email' => 'jane@example.com', 'name' => 'Jane' ],
						]
					),
					'subject'     => 'Retry failed',
					'body'        => 'Still failed',
					'headers'     => serialize( [ 'Content-Type: text/plain; charset=UTF-8' ] ),
					'attachments' => serialize( [] ),
					'response'    => serialize( [ 'message' => 'No fallback provider configured' ] ),
					'extra'       => serialize( [ 'provider' => 'gmail' ] ),
				],
			],
		];
	}

	protected function getActionTests(): array {
		return [
			'send_email' => [
				'to'           => 'jane@example.com, team@example.com',
				'subject'      => 'Test Subject',
				'message'      => '<p>Body</p>',
				'content_type' => 'text/html',
			],
		];
	}

	public function test_integration_exposes_introduction(): void {
		$this->assertSame(
			'Route WordPress email through SMTP and provider APIs with logging, fallback delivery, and delivery diagnostics powered by FluentSMTP.',
			FluentSmtp::get_introduction()
		);
	}

	public function test_email_sent_trigger_filters_by_recipient(): void {
		$node = $this->makeTriggerNode(
			'email_sent',
			[
				'recipient_email' => 'billing@example.com',
			]
		);

		$result = FluentSmtp::resolve_trigger(
			$node,
			[
				[
					'to'      => [ 'jane@example.com' ],
					'subject' => 'Welcome',
					'message' => 'Hello',
				],
			]
		);

		$this->assertFalse( $result );
	}

	public function test_email_failed_trigger_returns_error_details(): void {
		$node = $this->makeTriggerNode( 'email_failed' );
		$result = FluentSmtp::resolve_trigger(
			$node,
			[
				new \WP_Error(
					'wp_mail_failed',
					'SMTP auth failed',
					[
						'to'      => [ 'ops@example.com' ],
						'subject' => 'Alert',
						'message' => 'Delivery failed',
					]
				),
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( 'SMTP auth failed', $result['error_message'] );
		$this->assertSame( [ 'ops@example.com' ], $result['recipient_emails'] );
	}

	public function test_no_fallback_trigger_includes_provider_and_log_id(): void {
		$node = $this->makeTriggerNode(
			'delivery_failed_no_fallback',
			[
				'provider' => 'gmail',
			]
		);

		$result = FluentSmtp::resolve_trigger(
			$node,
			[
				55,
				$this->makeHandler( 'gmail' ),
				[
					'to'      => serialize(
						[
							[ 'email' => 'owner@example.com' ],
						]
					),
					'subject' => 'Retry failed',
					'body'    => 'No fallback left',
					'extra'   => serialize( [ 'provider' => 'gmail' ] ),
				],
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 55, $result['log_id'] );
		$this->assertSame( 'gmail', $result['provider'] );
	}

	public function test_send_email_action_returns_error_without_valid_recipient(): void {
		$result = FluentSmtp::execute_node(
			$this->makeActionNode(
				'send_email',
				[
					'to'      => 'bad-email',
					'subject' => 'Subject',
					'message' => 'Body',
				]
			),
			[]
		);

		$this->assertSame( 'At least one valid recipient email is required', $result['data']['error'] );
	}

	public function test_send_email_action_returns_mail_payload(): void {
		$result = FluentSmtp::execute_node(
			$this->makeActionNode(
				'send_email',
				[
					'to'           => 'jane@example.com, team@example.com',
					'subject'      => 'Quarterly Update',
					'message'      => '<p>Hello Team</p>',
					'content_type' => 'text/html',
					'cc'           => 'manager@example.com',
					'bcc'          => 'audit@example.com',
					'reply_to'     => 'reply@example.com',
					'attachments'  => "/tmp/a.pdf\n/tmp/b.pdf",
				]
			),
			[]
		);

		$this->assertTrue( $result['data']['mail_sent'] );
		$this->assertSame( [ 'jane@example.com', 'team@example.com' ], $result['data']['recipient_emails'] );
		$this->assertSame( 'Quarterly Update', $result['data']['subject'] );
		$this->assertSame( [ '/tmp/a.pdf', '/tmp/b.pdf' ], $result['data']['attachments'] );
		$this->assertContains( 'Cc: manager@example.com', $result['data']['headers'] );
		$this->assertContains( 'Bcc: audit@example.com', $result['data']['headers'] );
		$this->assertContains( 'Reply-To: reply@example.com', $result['data']['headers'] );
	}

	private function makeHandler( string $provider ) {
		return new class( $provider ) {

			private string $provider;

			public function __construct( string $provider ) {
				$this->provider = $provider;
			}

			public function getSetting( string $key, $default = null ) {
				if ( 'provider' === $key ) {
					return $this->provider;
				}

				return $default;
			}
		};
	}
}
