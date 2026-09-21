<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The seam between a messaging integration's workflow "send" actions and
 * whatever else keeps a record of that conversation (the Inbox module).
 *
 * A send action asks {@see self::check()} before it sends, and reports what it
 * sent with {@see self::sent()}. Nothing here knows about the Inbox: with no
 * listener attached every send is allowed and nothing is recorded.
 *
 * Context keys passed to the filters and actions:
 * - channel     string  Integration slug, e.g. "messenger".
 * - recipient   string  The customer's address on that channel (PSID, wa_id…).
 * - account_id  string  The business's address when known (phone_number_id…).
 * - body        string  Text sent, or the caption of a file.
 * - attachments array   Files sent, in the Inbox's attachment shape.
 * - message_id  string  The provider's id for the sent message (sent() only).
 * - run_id      int     The workflow run, 0 when a person ran one step by hand.
 * - step        string  The step's name in the workflow.
 * - mode        string  "respect" (default) or "always"; see mode_field().
 */
class ChannelSend {

	public const MODE_KEY = 'inbox_mode';

	/**
	 * The step setting that decides what happens when the Inbox is already
	 * answering this customer some other way.
	 *
	 * @return array<string,mixed>
	 */
	public static function mode_field(): array {
		return [
			'key'      => self::MODE_KEY,
			'label'    => 'If this customer is in the Inbox',
			'type'     => 'select',
			'required' => false,
			'default'  => 'respect',
			'options'  => [
				[
					'value' => 'respect',
					'label' => 'Send only when workflows answer them (avoids double replies)',
				],
				[
					'value' => 'always',
					'label' => 'Always send (order updates and other notifications)',
				],
			],
			'help'     => 'The Inbox decides who answers each conversation: the AI assistant, your team, or workflows. With the first option this step waits its turn; a held reply is saved in the conversation as a private note.',
		];
	}

	/**
	 * Build the context for one send from the step and what it is about to send.
	 *
	 * @param array<string,mixed> $node
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	public static function context( string $channel, string $recipient, array $node, array $extra = [] ): array {
		$config = (array) ( $node['data']['config'] ?? [] );

		return array_merge(
			[
				'channel'     => $channel,
				'recipient'   => $recipient,
				'account_id'  => '',
				'body'        => '',
				'attachments' => [],
				'message_id'  => '',
				'run_id'      => (int) ( $node['_run_id'] ?? 0 ),
				'step'        => (string) ( $node['data']['name'] ?? '' ),
				'mode'        => 'always' === ( $config[ self::MODE_KEY ] ?? '' ) ? 'always' : 'respect',
			],
			$extra
		);
	}

	/**
	 * Why this send should wait, or an empty string when it may go.
	 *
	 * @param array<string,mixed> $context
	 */
	public static function check( array $context ): string {
		/**
		 * Return a reason to hold a workflow's message to a customer.
		 *
		 * @param string $reason  Empty to allow the send.
		 * @param array  $context See the class docblock.
		 */
		return (string) apply_filters( 'zaplane/channel_send/check', '', $context );
	}

	/**
	 * The step's result when its message was held, so the run carries on and
	 * its log explains why nothing was sent.
	 *
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $input
	 * @return array{port:string,data:array<string,mixed>}
	 */
	public static function held( array $context, string $reason, array $input ): array {
		/**
		 * A workflow message was held instead of sent.
		 *
		 * @param array  $context See the class docblock.
		 * @param string $reason
		 */
		do_action( 'zaplane/channel_send/held', $context, $reason );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'success' => false,
				'skipped' => true,
				'reason'  => $reason,
			] ),
		];
	}

	/**
	 * Report a message the step delivered.
	 *
	 * @param array<string,mixed> $context
	 */
	public static function sent( array $context ): void {
		/**
		 * A workflow delivered a message to a customer.
		 *
		 * @param array $context See the class docblock.
		 */
		do_action( 'zaplane/channel_send/sent', $context );
	}
}
