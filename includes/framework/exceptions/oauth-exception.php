<?php

namespace Zaplane\Framework\Exceptions;

if( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}
class OAuthException extends ZaplaneException
{
    protected string $errorCode = 'oauth_error';
    protected string $integration = '';
    protected ?string $oauthError = null;

    public function __construct(
        string $message,
        string $integration = '',
        ?string $oauthError = null,
        array $context = []
    ) {
        $this->integration = $integration;
        $this->oauthError = $oauthError;

        if ($integration) {
            $context['integration'] = $integration;
        }
        if ($oauthError) {
            $context['oauth_error'] = $oauthError;
        }

        parent::__construct($message, $context);
    }

    public static function invalidState(): self
    {
        return new self('Invalid or expired OAuth state token');
    }

    public static function tokenExchangeFailed(string $integration, string $error): self
    {
        return new self(
            "OAuth token exchange failed: {$error}",
            $integration,
            $error
        );
    }

    public static function refreshFailed(string $integration, string $error): self
    {
        return new self(
            "OAuth token refresh failed: {$error}",
            $integration,
            $error
        );
    }

    public static function noAccessToken(string $integration): self
    {
        return new self(
            'No access token received from OAuth provider',
            $integration
        );
    }

    public static function noRefreshToken(string $integration): self
    {
        return new self(
            'No refresh token available',
            $integration
        );
    }

    public static function authUrlFailed(string $integration): self
    {
        return new self(
            'Failed to generate OAuth authorization URL',
            $integration
        );
    }

    public static function notSupported(string $integration): self
    {
        return new self(
            "Integration does not support OAuth2",
            $integration
        );
    }

    public static function scopeError(string $integration, array $missingScopes): self
    {
        return new self(
            'Missing required OAuth scopes: ' . implode(', ', $missingScopes),
            $integration,
            null,
            ['missing_scopes' => $missingScopes]
        );
    }

    public function getIntegration(): string
    {
        return $this->integration;
    }

    public function getOAuthError(): ?string
    {
        return $this->oauthError;
    }

    public function getHttpStatusCode(): int
    {
        return 401;
    }
}
