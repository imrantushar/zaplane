<?php

namespace Zaplane\Exceptions;

if( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}
class IntegrationException extends ZaplaneException
{
    protected string $errorCode = 'integration_error';
    protected string $integration = '';
    protected ?string $action = null;

    public function __construct(
        string $message,
        string $integration,
        ?string $action = null,
        array $context = []
    ) {
        $this->integration = $integration;
        $this->action = $action;

        $context['integration'] = $integration;
        if ($action) {
            $context['action'] = $action;
        }

        parent::__construct($message, $context);
    }

    public static function notFound(string $integration): self
    {
        return new self(
            "Integration not found: {$integration}",
            $integration
        );
    }

    public static function actionFailed(string $integration, string $action, string $reason): self
    {
        return new self(
            "Action '{$action}' failed: {$reason}",
            $integration,
            $action
        );
    }

    public static function triggerFailed(string $integration, string $trigger, string $reason): self
    {
        return new self(
            "Trigger '{$trigger}' failed: {$reason}",
            $integration,
            $trigger
        );
    }

    public static function missingCredentials(string $integration): self
    {
        return new self(
            "No connection credentials available for {$integration}",
            $integration
        );
    }

    public static function apiError(string $integration, string $action, string $apiMessage, int $statusCode = 0): self
    {
        $context = ['api_status_code' => $statusCode];
        return new self(
            "API error during '{$action}': {$apiMessage}",
            $integration,
            $action,
            $context
        );
    }

    public static function rateLimited(string $integration, int $retryAfter = 0): self
    {
        $context = $retryAfter > 0 ? ['retry_after' => $retryAfter] : [];
        return new self(
            "Rate limit exceeded for {$integration}",
            $integration,
            null,
            $context
        );
    }

    public function getIntegration(): string
    {
        return $this->integration;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getHttpStatusCode(): int
    {
        return 502;
    }
}
