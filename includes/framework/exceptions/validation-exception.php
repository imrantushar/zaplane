<?php

namespace Zaplane\Framework\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ValidationException extends ZaplaneException {

	protected string $errorCode = 'validation_error';
	protected array $errors = [];

	public function __construct(
		string $message = 'Validation failed',
		array $errors = [],
		array $context = []
	) {
		$this->errors = $errors;
		parent::__construct( $message, $context );
	}

	public static function withErrors( array $errors ): self {
		$messages = [];
		foreach ( $errors as $field => $fieldErrors ) {
			if ( is_array( $fieldErrors ) ) {
				$messages[] = $field . ': ' . implode( ', ', $fieldErrors );
			} else {
				$messages[] = $field . ': ' . $fieldErrors;
			}
		}

		return new self(
			'Validation failed: ' . implode( '; ', $messages ),
			$errors
		);
	}

	public static function forField( string $field, string $message ): self {
		return new self(
			"Validation failed for {$field}: {$message}",
			[ $field => [ $message ] ]
		);
	}

	public static function required( string $field ): self {
		return self::forField( $field, 'This field is required' );
	}

	public static function invalidType( string $field, string $expectedType ): self {
		return self::forField( $field, "Expected {$expectedType}" );
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function getHttpStatusCode(): int {
		return 400;
	}

	public function toArray(): array {
		return array_merge(parent::toArray(), [
			'errors' => $this->errors,
		]);
	}
}
