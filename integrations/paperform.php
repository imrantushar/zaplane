<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;

class Paperform extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'paperform';
	}

	public static function get_name(): string {
		return 'Paperform';
	}

	public static function get_icon(): string {
		return 'paperform.svg';
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_paperform_webhook_form_submitted',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submitted' === $trigger ) {
			return [
				[
					'key'      => 'webhook_endpoint',
					'label'    => 'Webhook Endpoint URL',
					'type'     => 'copy',
					'value'    => self::get_webhook_url(),
					'readonly' => true,
					'help'     => 'Copy this URL → Paperform → Your Form → After Submission → Integrations & Webhooks → Webhooks → Add Webhook → Paste & Save.',
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
        switch ( $node['event'] ) {
            case 'form_submitted':
                $payload = $args[0] ?? [];

                if ( empty( $payload ) || ! is_array( $payload ) ) {
                    return false;
                }

                $submission_id = $payload['submission_id'] ?? '';

                if ( empty( $submission_id ) ) {
                    return false;
                }

                return [
                    'paperform_submission_id' => $submission_id,
                    'paperform_created_at'    => $payload['created_at'] ?? '',
                    'paperform_ip_address'    => $payload['ip_address'] ?? '',
                    'paperform_charge'        => $payload['charge'] ?? null,
                    'paperform_answers'       => self::parse_answers( $payload['data'] ?? [] ),
                ];
        }

        return false;
    }

	public static function supports_webhook(): bool {
		return true;
	}

	public static function get_webhook_url(): string {
		$url = rest_url( 'zaplane/v1/incoming/' . self::get_slug() );
		return set_url_scheme( $url, 'https' );
	}

	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
        $payload = $request->get_json_params();

        if ( empty( $payload ) ) {
            $body = $request->get_body();
            if ( ! empty( $body ) ) {
                $payload = json_decode( $body, true );
            }
        }

        if ( empty( $payload ) || ! is_array( $payload ) ) {
            return null;
        }

        if ( ! empty( $payload['submission_id'] ) ) {
            return [
                'event'   => 'form_submitted',
                'payload' => $payload,
            ];
        }

        return null;
    }

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		return true;
	}

	private static function parse_answers( array $answers ): array {
		$data = [];

		foreach ( $answers as $answer ) {
			$key = $answer['custom_key'] ?? $answer['key'] ?? null;

			if ( null === $key ) {
				continue;
			}

			$data[ $key ] = $answer['value'] ?? null;
		}

		return $data;
	}
}