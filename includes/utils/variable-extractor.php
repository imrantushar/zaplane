<?php

namespace Zaplane\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariableExtractor {



	public static function extract( $data, string $prefix = '' ): array {

		if ( ! is_array( $data ) ) {
			if ( null === $data ) {
				return [];
			}
			return [
				[
					'key' => $prefix ? $prefix : 'value',
					'type' => self::detectType( $data ),
					'sample' => self::getSample( $data ),
				]
			];
		}

		if ( empty( $data ) ) {
			return [];
		}

		$variables = [];

		foreach ( $data as $key => $value ) {
			$fullKey = $prefix ? "{$prefix}.{$key}" : $key;

			if ( is_array( $value ) ) {
				if ( self::isIndexedArray( $value ) ) {
					$variables[] = [
						'key' => $fullKey,
						'type' => 'array',
						'sample' => self::getSample( $value ),
					];

					if ( ! empty( $value ) && is_array( $value[0] ) ) {
						$nestedVars = self::extract( $value[0], "{$fullKey}[]" );
						$variables = array_merge( $variables, $nestedVars );
					}
				} else {
					$nestedVars = self::extract( $value, $fullKey );
					$variables = array_merge( $variables, $nestedVars );
				}
			} else {
				$variables[] = [
					'key' => $fullKey,
					'type' => self::detectType( $value ),
					'sample' => self::getSample( $value ),
				];
			}//end if
		}//end foreach

		return $variables;
	}



	private static function detectType( $value ): string {
		if ( is_null( $value ) ) {
			return 'null';
		}

		if ( is_bool( $value ) ) {
			return 'boolean';
		}

		if ( is_int( $value ) ) {
			return 'integer';
		}

		if ( is_float( $value ) ) {
			return 'float';
		}

		if ( is_string( $value ) ) {
			if ( self::isDateString( $value ) ) {
				return 'datetime';
			}

			if ( self::isEmailString( $value ) ) {
				return 'email';
			}

			if ( self::isUrlString( $value ) ) {
				return 'url';
			}

			if ( self::isHtmlString( $value ) ) {
				return 'html';
			}

			return 'string';
		}

		return 'mixed';
	}



	private static function isIndexedArray( array $array ): bool {
		if ( empty( $array ) ) {
			return true;
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}



	private static function getSample( $value ) {
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return [];
			}

			return count( $value ) . ' items';
		}

		if ( is_string( $value ) && strlen( $value ) > 100 ) {
			return substr( $value, 0, 100 ) . '...';
		}

		return $value;
	}



	private static function isDateString( string $value ): bool {

		$patterns = [
			'/^\d{4}-\d{2}-\d{2}/',
			'/^\d{2}\/\d{2}\/\d{4}/',
			'/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}



	private static function isEmailString( string $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false;
	}



	private static function isUrlString( string $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
	}



	private static function isHtmlString( string $value ): bool {
		return wp_strip_all_tags( $value ) !== $value;
	}



	public static function flatten( array $data, string $prefix = '' ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			$fullKey = $prefix ? "{$prefix}.{$key}" : $key;

			if ( is_array( $value ) && ! self::isIndexedArray( $value ) ) {
				$nested = self::flatten( $value, $fullKey );
				$result = array_merge( $result, $nested );
			} else {
				$result[ $fullKey ] = $value;
			}
		}

		return $result;
	}
}
