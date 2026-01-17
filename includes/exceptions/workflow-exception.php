<?php

namespace Zaplane\Exceptions;

if( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}
class WorkflowException extends ZaplaneException
{
    protected string $errorCode = 'workflow_error';
    protected ?int $workflowId = null;
    protected ?int $runId = null;
    protected ?string $nodeKey = null;

    public function __construct(
        string $message,
        ?int $workflowId = null,
        ?int $runId = null,
        ?string $nodeKey = null,
        array $context = []
    ) {
        $this->workflowId = $workflowId;
        $this->runId = $runId;
        $this->nodeKey = $nodeKey;

        if ($workflowId) {
            $context['workflow_id'] = $workflowId;
        }
        if ($runId) {
            $context['run_id'] = $runId;
        }
        if ($nodeKey) {
            $context['node_key'] = $nodeKey;
        }

        parent::__construct($message, $context);
    }

    public static function notFound(int $workflowId): self
    {
        return new self(
            "Workflow not found: {$workflowId}",
            $workflowId
        );
    }

    public static function versionNotFound(int $workflowId, int $versionId): self
    {
        return new self(
            "Workflow version not found: {$versionId}",
            $workflowId,
            null,
            null,
            ['version_id' => $versionId]
        );
    }

    public static function nodeNotFound(int $runId, string $nodeKey): self
    {
        return new self(
            "Node not found: {$nodeKey}",
            null,
            $runId,
            $nodeKey
        );
    }

    public static function executionFailed(int $runId, string $nodeKey, string $reason): self
    {
        return new self(
            "Node execution failed: {$reason}",
            null,
            $runId,
            $nodeKey
        );
    }

    public static function invalidGraph(string $reason): self
    {
        return new self("Invalid workflow graph: {$reason}");
    }

    public static function alreadyRunning(int $workflowId, int $runId): self
    {
        return new self(
            'Workflow is already running',
            $workflowId,
            $runId
        );
    }

    public static function noActiveVersion(int $workflowId): self
    {
        return new self(
            'No active version found for workflow',
            $workflowId
        );
    }

    public static function maxRetriesExceeded(int $runId, string $nodeKey, int $attempts): self
    {
        return new self(
            "Max retries exceeded after {$attempts} attempts",
            null,
            $runId,
            $nodeKey,
            ['attempts' => $attempts]
        );
    }

    public function getWorkflowId(): ?int
    {
        return $this->workflowId;
    }

    public function getRunId(): ?int
    {
        return $this->runId;
    }

    public function getNodeKey(): ?string
    {
        return $this->nodeKey;
    }

    public function getHttpStatusCode(): int
    {
        if ($this->workflowId && strpos($this->getMessage(), 'not found') !== false) {
            return 404;
        }
        return 500;
    }
}
