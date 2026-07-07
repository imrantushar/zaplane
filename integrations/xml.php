<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xml extends IntegrationBase {

	public static function get_slug(): string {
		return 'xml';
	}

	public static function get_name(): string {
		return 'XML';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'xml.svg';
	}

	public static function get_actions(): array {
		return [
			'parse' => [ 'label' => 'Parse XML' ],
			'build' => [ 'label' => 'Build XML' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'build' === $action ) {
			return [
				[
					'key' => 'data',
					'label' => 'Data',
					'type' => 'textarea',
					'required' => true
				],
				[
					'key' => 'root',
					'label' => 'Root element',
					'type' => 'text',
					'default' => 'root'
				],
			];
		}

		return [
			[
				'key' => 'xml',
				'label' => 'XML text',
				'type' => 'textarea',
				'required' => true
			],
		];
	}

	public static function get_action_sample_output( string $action ): array {
		if ( 'build' === $action ) {
			return [ 'xml' => '<root><item>value</item></root>' ];
		}
		return [ 'data' => [ 'item' => 'value' ], 'success' => true, 'error' => null ];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['data']['event'] ?? 'parse';
		$config = $node['data']['config'] ?? [];

		return [
			'port' => 'main',
			'data' => 'build' === $action ? self::build( $config ) : self::parse( $config ),
		];
	}

	protected static function parse( array $config ): array {
		$text = (string) ( $config['xml'] ?? '' );
		if ( '' === trim( $text ) ) {
			return [
				'data' => null,
				'success' => false,
				'error' => 'Empty XML.'
			];
		}

		$previous = libxml_use_internal_errors( true );
		$xml      = simplexml_load_string( $text, 'SimpleXMLElement', LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $xml ) {
			return [
				'data' => null,
				'success' => false,
				'error' => 'Invalid XML.'
			];
		}

		// Normalise to plain nested arrays via JSON round-trip.
		$data = json_decode( (string) wp_json_encode( $xml ), true );

		return [
			'data' => $data,
			'success' => true,
			'error' => null
		];
	}

	protected static function build( array $config ): array {
		$data = $config['data'] ?? [];
		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			$data    = is_array( $decoded ) ? $decoded : [ 'value' => $data ];
		}
		$root = (string) ( $config['root'] ?? 'root' );
		$root = '' !== $root ? $root : 'root';

		$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><' . self::tag( $root ) . '/>' );
		self::array_to_xml( is_array( $data ) ? $data : [ 'value' => $data ], $xml );

		return [ 'xml' => $xml->asXML() ];
	}

	protected static function array_to_xml( array $data, \SimpleXMLElement $node ): void {
		foreach ( $data as $key => $value ) {
			$tag = is_numeric( $key ) ? 'item' : self::tag( (string) $key );
			if ( is_array( $value ) ) {
				$child = $node->addChild( $tag );
				self::array_to_xml( $value, $child );
			} else {
				$node->addChild( $tag, htmlspecialchars( (string) $value ) );
			}
		}
	}

	protected static function tag( string $name ): string {
		$name = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $name );
		if ( '' === $name || ! preg_match( '/^[a-zA-Z_]/', $name ) ) {
			$name = 'node' . $name;
		}
		return $name;
	}
}
