<?php

namespace Zaplane\Framework\Exceptions;

if( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}

use Exception;
use Throwable;

class ZaplaneException extends Exception
{
    protected array $context = [];
    protected string $errorCode = 'zaplane_error';

    public function __construct(
        string $message = '',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    public function toWpError(): \WP_Error
    {
        return new \WP_Error(
            $this->errorCode,
            $this->getMessage(),
            array_merge($this->context, ['status' => $this->getHttpStatusCode()])
        );
    }

    public function getHttpStatusCode(): int
    {
        return 500;
    }
}
