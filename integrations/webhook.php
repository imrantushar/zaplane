<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Webhook — a generic catch-all trigger. Any service can POST JSON to the
 * workflow's webhook URL (/wp-json/zaplane/v1/hook/<workflow_id>) and the whole
 * payload becomes the trigger output. An optional secret can be required.
 *
 * Fired by IncomingWebhookController::handle_workflow_hook() via run_workflow,
 * not by a WordPress hook — the hook name only marks it as an active trigger.
 */
class Webhook extends IntegrationBase {

	public static function get_slug(): string {
		return 'webhook';
	}

	public static function get_name(): string {
		return 'Webhook';
	}

	public static function get_icon(): string {
		return 'webhook.svg';
	}

	public static function get_triggers(): array {
		return [
			'catch_hook' => [
				'label' => 'Catch Webhook',
				'hook'  => 'zaplane/webhook/catch',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'catch_hook' !== $trigger ) {
			return [];
		}

		return [
			[
				// Read-only URL to POST to. The `{workflow_id}` token is resolved
				// on the client from the open workflow, then prefixed with the
				// site's REST base (the CopyInput renderer handles both).
				'key'   => 'webhook_url',
				'label' => 'Webhook URL',
				'type'  => 'copy',
				'value' => 'zaplane/v1/hook/{workflow_id}',
				'help'  => 'Send a GET or POST request (JSON body and/or query params) to this URL to trigger the workflow. Available after the workflow is saved.',
			],
			[
				'key'      => 'secret',
				'label'    => 'Secret (optional)',
				'type'     => 'expression',
				'required' => false,
				'help'     => 'If set, requests must include ?secret=... (or an X-Zaplane-Secret header) that matches.',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$payload = $args[0] ?? [];
		return is_array( $payload ) ? $payload : [ 'body' => $payload ];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		return [ 'example' => 'value', 'nested' => [ 'a' => 1 ] ];
	}
}
