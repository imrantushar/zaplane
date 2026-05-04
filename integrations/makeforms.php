<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;

class Makeforms extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'makeforms';
	}

	public static function get_name(): string {
		return 'MakeForms';
	}

	public static function get_icon(): string {
		return 'makeforms.svg';
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_makeforms_webhook_form_submitted',
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
					'help'     => 'Copy this URL → MakeForms Dashboard → Your Form → Connect → Webhooks → Add a webhook → Paste & Save.',
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'form_submitted':
				$payload = $args[0] ?? [];

				if ( isset( $payload['payload']['form_response'] ) ) {
					$form_response = $payload['payload']['form_response'];
				} elseif ( isset( $payload['form_response'] ) ) {
					$form_response = $payload['form_response'];
				} elseif ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
					$form_response = $payload['data'];
				} else {
					$form_response = [];
				}

				if ( empty( $form_response ) ) {
					return false;
				}

				$form_id = $form_response['form_id']
					?? $form_response['formId']
					?? $form_response['id']
					?? '';

				if ( empty( $form_id ) ) {
					return false;
				}

				$definition = $form_response['definition'] ?? [];

				return [
					'makeforms_form_id'      => $form_id,
					'makeforms_form_title'   => $definition['title']
						?? $form_response['title']
						?? $form_response['form_title']
						?? '',
					'makeforms_entry_token'  => $form_response['token']
						?? $form_response['response_id']
						?? '',
					'makeforms_landed_at'    => $form_response['landed_at'] ?? '',
					'makeforms_submitted_at' => $form_response['submitted_at'] ?? '',
					'makeforms_answers'      => self::parse_answers( $form_response['answers'] ?? [] ),
					'makeforms_variables'    => $form_response['variables'] ?? [],
					'makeforms_hidden'       => $form_response['hidden'] ?? [],
				];
		}//end switch

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

		if ( ! empty( $payload['form_response'] ) ) {
			return [
				'event'   => 'form_submitted',
				'payload' => $payload,
			];
		}

		if ( ! empty( $payload['event_type'] ) && ! empty( $payload['data'] ) ) {
			return [
				'event'   => 'form_submitted',
				'payload' => [ 'form_response' => $payload['data'] ],
			];
		}

		if ( ! empty( $payload['form_id'] ) || ! empty( $payload['formId'] ) ) {
			return [
				'event'   => 'form_submitted',
				'payload' => [ 'form_response' => $payload ],
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
			$key = $answer['field']['ref'] ?? $answer['field']['id'] ?? null;

			if ( null === $key ) {
				continue;
			}

			$type = $answer['type'] ?? '';

			switch ( $type ) {
				case 'choice':
					$data[ $key ] = $answer['choice']['label'] ?? $answer['choice']['other'] ?? '';
					break;

				case 'choices':
					$labels = $answer['choices']['labels'] ?? [];
					$other  = $answer['choices']['other'] ?? null;
					if ( $other ) {
						$labels[] = $other;
					}
					$data[ $key ] = $labels;
					break;

				case 'boolean':
					$data[ $key ] = (bool) ( $answer['boolean'] ?? false );
					break;

				case 'number':
					$data[ $key ] = $answer['number'] ?? null;
					break;

				case 'date':
					$data[ $key ] = $answer['date'] ?? null;
					break;

				case 'file_url':
					$data[ $key ] = $answer['file_url'] ?? null;
					break;

				case 'payment':
					$data[ $key ] = $answer['payment'] ?? null;
					break;

				default:
					$data[ $key ] = $answer[ $type ] ?? null;
					break;
			}//end switch
		}//end foreach

		return $data;
	}
}
