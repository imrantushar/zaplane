<?php

namespace Zaplane\Framework\Logging;

use JsonSerializable;

if (!defined('ABSPATH')) exit;

/**
 * Represents a single log entry.
 */
class LogEntry implements JsonSerializable
{
    protected string $level;
    protected string $message;
    protected array $context;
    protected string $channel;
    protected string $timestamp;
    protected ?string $traceId;
    protected ?string $userId;
    protected array $extra;

    public function __construct(
        string $level,
        string $message,
        array $context = [],
        string $channel = 'default',
        ?string $traceId = null
    ) {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->channel = $channel;
        $this->timestamp = current_time('mysql');
        $this->traceId = $traceId;
        $this->userId = $this->getCurrentUserId();
        $this->extra = [];
    }

    /**
     * Get the current user ID if available.
     */
    protected function getCurrentUserId(): ?string
    {
        if (function_exists('get_current_user_id')) {
            $userId = get_current_user_id();
            return $userId > 0 ? (string) $userId : null;
        }
        return null;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * Add extra data to the log entry.
     */
    public function addExtra(string $key, $value): self
    {
        $this->extra[$key] = $value;
        return $this;
    }

    /**
     * Set extra data.
     */
    public function setExtra(array $extra): self
    {
        $this->extra = $extra;
        return $this;
    }

    /**
     * Get interpolated message with context values.
     */
    public function getInterpolatedMessage(): string
    {
        $replace = [];

        foreach ($this->context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $replace['{' . $key . '}'] = $value;
            } elseif (is_bool($value)) {
                $replace['{' . $key . '}'] = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $replace['{' . $key . '}'] = 'null';
            } elseif (is_array($value) || is_object($value)) {
                $replace['{' . $key . '}'] = json_encode($value);
            }
        }

        return strtr($this->message, $replace);
    }

    /**
     * Format the entry as a string.
     */
    public function format(string $format = '[{timestamp}] {channel}.{level}: {message} {context}'): string
    {
        $replacements = [
            '{timestamp}' => $this->timestamp,
            '{channel}' => strtoupper($this->channel),
            '{level}' => strtoupper($this->level),
            '{message}' => $this->getInterpolatedMessage(),
            '{context}' => !empty($this->context) ? json_encode($this->context) : '',
            '{trace_id}' => $this->traceId ?? '',
            '{user_id}' => $this->userId ?? '',
        ];

        $formatted = strtr($format, $replacements);

        // Clean up empty context
        return preg_replace('/\s*\{\}$/', '', trim($formatted));
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'interpolated_message' => $this->getInterpolatedMessage(),
            'context' => $this->context,
            'channel' => $this->channel,
            'timestamp' => $this->timestamp,
            'trace_id' => $this->traceId,
            'user_id' => $this->userId,
            'extra' => $this->extra,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
