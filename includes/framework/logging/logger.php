<?php

namespace Zaplane\Framework\Logging;

use Zaplane\Framework\Logging\Handlers\HandlerInterface;
use Zaplane\Framework\Config\Config;

if (!defined('ABSPATH')) exit;

/**
 * PSR-3 inspired Logger
 *
 * Provides structured logging with multiple handlers, channels, and context support.
 *
 * Usage:
 *   $logger = Logger::getInstance();
 *   $logger->info('User logged in', ['user_id' => 123]);
 *   $logger->error('Payment failed', ['order_id' => 456, 'error' => $e->getMessage()]);
 *
 *   // With channel
 *   $logger->channel('workflow')->info('Workflow started', ['workflow_id' => 1]);
 *
 *   // With trace ID for request tracking
 *   $logger->withTraceId('abc123')->info('Processing request');
 */
class Logger
{
    private static ?self $instance = null;

    /**
     * Registered handlers.
     */
    protected array $handlers = [];

    /**
     * Current channel.
     */
    protected string $channel = 'default';

    /**
     * Current trace ID for request tracking.
     */
    protected ?string $traceId = null;

    /**
     * Global context added to all log entries.
     */
    protected array $globalContext = [];

    /**
     * Whether logging is enabled.
     */
    protected bool $enabled = true;

    /**
     * Minimum log level.
     */
    protected string $minLevel = LogLevel::DEBUG;

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->close();
        }
        self::$instance = null;
    }

    private function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load configuration from Config system.
     */
    protected function loadConfiguration(): void
    {
        $config = Config::getInstance();

        $this->enabled = $config->get('logging.enabled', true);
        $this->minLevel = $config->get('logging.level', LogLevel::DEBUG);
    }

    /**
     * Add a handler.
     */
    public function pushHandler(HandlerInterface $handler): self
    {
        array_unshift($this->handlers, $handler);
        return $this;
    }

    /**
     * Pop a handler from the stack.
     */
    public function popHandler(): ?HandlerInterface
    {
        return array_shift($this->handlers);
    }

    /**
     * Get all handlers.
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Set handlers, replacing existing ones.
     */
    public function setHandlers(array $handlers): self
    {
        $this->handlers = [];
        foreach ($handlers as $handler) {
            $this->pushHandler($handler);
        }
        return $this;
    }

    /**
     * Create a new logger instance for a specific channel.
     */
    public function channel(string $channel): self
    {
        $logger = clone $this;
        $logger->channel = $channel;
        return $logger;
    }

    /**
     * Set the trace ID for request tracking.
     */
    public function withTraceId(string $traceId): self
    {
        $logger = clone $this;
        $logger->traceId = $traceId;
        return $logger;
    }

    /**
     * Generate a new trace ID.
     */
    public function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get the current trace ID.
     */
    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * Add global context.
     */
    public function withContext(array $context): self
    {
        $logger = clone $this;
        $logger->globalContext = array_merge($logger->globalContext, $context);
        return $logger;
    }

    /**
     * Set global context.
     */
    public function setGlobalContext(array $context): self
    {
        $this->globalContext = $context;
        return $this;
    }

    /**
     * Enable logging.
     */
    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * Disable logging.
     */
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Check if logging is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set minimum log level.
     */
    public function setMinLevel(string $level): self
    {
        $this->minLevel = $level;
        return $this;
    }

    /**
     * Log a message at a given level.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!LogLevel::meetsThreshold($level, $this->minLevel)) {
            return;
        }

        $context = array_merge($this->globalContext, $context);

        $entry = new LogEntry(
            $level,
            $message,
            $context,
            $this->channel,
            $this->traceId
        );

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($level)) {
                $handler->handle($entry);
            }
        }

        // Fire WordPress action for extensibility
        do_action('zaplane_log', $entry);
        do_action("zaplane_log_{$level}", $entry);
    }

    /**
     * System is unusable.
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log an exception.
     */
    public function exception(\Throwable $exception, string $level = LogLevel::ERROR, array $context = []): void
    {
        $context = array_merge($context, [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        if ($exception->getPrevious()) {
            $context['previous'] = [
                'exception' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
            ];
        }

        $this->log($level, $exception->getMessage(), $context);
    }

    /**
     * Log workflow execution.
     */
    public function workflow(int $workflowId, string $message, array $context = []): void
    {
        $context['workflow_id'] = $workflowId;
        $this->channel('workflow')->info($message, $context);
    }

    /**
     * Log node execution.
     */
    public function node(string $nodeId, string $message, array $context = []): void
    {
        $context['node_id'] = $nodeId;
        $this->channel('node')->debug($message, $context);
    }

    /**
     * Log integration activity.
     */
    public function integration(string $integration, string $message, array $context = []): void
    {
        $context['integration'] = $integration;
        $this->channel('integration')->info($message, $context);
    }

    /**
     * Log API request.
     */
    public function api(string $method, string $endpoint, array $context = []): void
    {
        $context['method'] = $method;
        $context['endpoint'] = $endpoint;
        $this->channel('api')->info("{$method} {$endpoint}", $context);
    }

    /**
     * Start timing a process.
     */
    public function startTiming(string $name): void
    {
        $this->globalContext["_timing_{$name}"] = microtime(true);
    }

    /**
     * End timing and log duration.
     */
    public function endTiming(string $name, string $message = null, array $context = []): float
    {
        $startKey = "_timing_{$name}";
        $start = $this->globalContext[$startKey] ?? microtime(true);
        unset($this->globalContext[$startKey]);

        $duration = microtime(true) - $start;
        $context['duration_ms'] = round($duration * 1000, 2);

        $message = $message ?? "Completed: {$name}";
        $this->debug($message, $context);

        return $duration;
    }

    /**
     * Close all handlers.
     */
    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }

    /**
     * Clean up on destruct.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Prevent cloning from outside.
     */
    public function __clone()
    {
        // Allow cloning for channel() method
    }
}
