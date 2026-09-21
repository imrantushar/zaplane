<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Classes\MetaGraph;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What Messenger and WhatsApp share: a Zaplane connection for credentials,
 * the integration's signed webhook, and Meta's messaging windows.
 */
abstract class MetaChannel implements ChannelInterface {

	/** Replies after the customer's last message are free for this long. */
	protected const WINDOW_HOURS = 24;

	/** @var array<string,array<string,mixed>> */
	private static array $credentials = [];

	/**
	 * The workflow integration that owns the webhook and connection type.
	 *
	 * @return class-string<\Zaplane\Framework\Classes\IntegrationBase>
	 */
	abstract protected static function integration(): string;

	/**
	 * Turn one verified delivery into inbox messages.
	 *
	 * @return int How many messages were stored.
	 */
	abstract public static function ingest( \WP_REST_Request $request ): int;

	/**
	 * @return array{enabled:bool,connection_id:int}
	 */
	public static function settings(): array {
		$all = InboxSettings::get()['channels'];
		return $all[ static::slug() ] ?? [
			'enabled'       => false,
			'connection_id' => 0,
		];
	}

	public static function enabled(): bool {
		$settings = static::settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['connection_id'] );
	}

	/**
	 * Only deliveries Meta signed reach the inbox. An integration without an
	 * App Secret accepts unsigned deliveries for its workflow trigger; the
	 * inbox does not, or anyone could open conversations and spend AI replies.
	 */
	public static function verified( \WP_REST_Request $request ): bool {
		$integration = static::integration();
		if ( '' === $integration::get_webhook_app_secret() ) {
			return false;
		}
		return $integration::verify_webhook_signature( $request );
	}

	/**
	 * @return array<string,mixed>
	 */
	protected static function credentials(): array {
		$slug = static::slug();
		if ( ! isset( self::$credentials[ $slug ] ) ) {
			try {
				self::$credentials[ $slug ] = ( new ConnectionManager() )->get_execution_credentials( (int) static::settings()['connection_id'] );
			} catch ( \Throwable $e ) {
				self::$credentials[ $slug ] = [];
			}
		}
		return self::$credentials[ $slug ];
	}

	/** For tests: forget cached credentials. */
	public static function reset(): void {
		self::$credentials = [];
	}

	protected static function hours_since_customer( Conversation $conversation ): ?float {
		if ( empty( $conversation->last_customer_at ) ) {
			return null;
		}
		$then = strtotime( get_gmt_from_date( (string) $conversation->last_customer_at ) . ' UTC' );
		return $then ? ( time() - $then ) / HOUR_IN_SECONDS : null;
	}

	/**
	 * POST to the Graph API.
	 *
	 * @param array<string,mixed> $body
	 * @param array<string,string> $headers
	 * @return array{ok:bool,data:array<string,mixed>,error:string}
	 */
	protected static function graph_post( string $path, array $body, array $headers, ?string $version ): array {
		$response = wp_remote_post( MetaGraph::url( $path, $version ), [
			'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'ok'    => false,
				'data'  => [],
				'error' => $response->get_error_message(),
			];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : [];

		if ( isset( $data['error'] ) ) {
			return [
				'ok'    => false,
				'data'  => $data,
				'error' => (string) ( $data['error']['message'] ?? __( 'Unknown error from Meta.', 'zaplane' ) ),
			];
		}

		return [
			'ok'    => true,
			'data'  => $data,
			'error' => '',
		];
	}
}
