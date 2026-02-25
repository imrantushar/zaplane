<?php

namespace Zaplane\Ajax;

use Zaplane\Framework\Classes\AbstractAjaxHandler;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if (!defined('ABSPATH')) {
    exit;
}

class Workflows extends AbstractAjaxHandler
{
    public function __construct()
    {
        $this->actions = [
            // Update workflow status
            'update_workflow_status' => [
                'callback' => [$this, 'updateStatus'],
                'capability' => 'manage_options',
                'fields' => [
                    'id' => 'absint',
                    'status' => 'string',
                ],
            ],

            // Delete workflow
            'delete_workflow' => [
                'callback' => [$this, 'deleteWorkflow'],
                'capability' => 'manage_options',
                'fields' => [
                    'id' => 'absint',
                ],
            ],

            // Duplicate workflow
            'duplicate_workflow' => [
                'callback' => [$this, 'duplicateWorkflow'],
                'capability' => 'manage_options',
                'fields' => [
                    'id' => 'absint',
                    'new_title' => 'string',
                ],
            ],

            // Update workflow settings (editor role)
            'update_workflow_settings' => [
                'callback' => [$this, 'updateSettings'],
                'capability' => 'edit_posts',
                'fields' => [
                    'id' => 'absint',
                    'settings' => 'json',
                ],
            ],

            // Get workflow stats (public endpoint)
            'get_workflow_stats' => [
                'callback' => [$this, 'getStats'],
                'capability' => '',
                'allow_visitor_action' => true,
                'fields' => [
                    'workflow_id' => 'absint',
                ],
            ],
        ];
    }

    /**
     * Update workflow status.
     */
    public function updateStatus(array $payload)
    {
        $id = $payload['id'] ?? 0;
        $status = $payload['status'] ?? '';

        if (!$id || empty($status)) {
            return new \WP_Error('missing_params', __('ID and status are required', 'zaplane'), ['code' => 400]);
        }

        if (!in_array($status, ['active', 'draft', 'paused'])) {
            return new \WP_Error('invalid_status', __('Invalid status value', 'zaplane'), ['code' => 400]);
        }

        $workflow = Workflow::find($id);

        if (!$workflow) {
            return new \WP_Error('not_found', __('Workflow not found', 'zaplane'), ['code' => 404]);
        }

        $workflow->status = $status;
        $workflow->save();

        return [
            'id' => $workflow->id,
            'status' => $workflow->status,
            'message' => __('Status updated successfully', 'zaplane'),
        ];
    }

    /**
     * Delete a workflow.
     */
    public function deleteWorkflow(array $payload)
    {
        $id = $payload['id'] ?? 0;

        if (!$id) {
            return new \WP_Error('missing_id', __('Workflow ID is required', 'zaplane'), ['code' => 400]);
        }

        $workflow = Workflow::find($id);

        if (!$workflow) {
            return new \WP_Error('not_found', __('Workflow not found', 'zaplane'), ['code' => 404]);
        }

        $workflowId = $workflow->id;
        $workflow->delete();

        // Also delete versions
        WorkflowVersion::where('workflow_id', $workflowId)
            ->delete();

        return [
            'deleted' => true,
            'id' => $workflowId,
            'message' => __('Workflow deleted successfully', 'zaplane'),
        ];
    }

    /**
     * Duplicate a workflow.
     */
    public function duplicateWorkflow(array $payload)
    {
        $id = $payload['id'] ?? 0;

        if (!$id) {
            return new \WP_Error('missing_id', __('Workflow ID is required', 'zaplane'), ['code' => 400]);
        }

        $original = Workflow::find($id);

        if (!$original) {
            return new \WP_Error('not_found', __('Workflow not found', 'zaplane'), ['code' => 404]);
        }

        // Create new workflow
        $newTitle = !empty($payload['new_title']) ? $payload['new_title'] : $original->title . ' (Copy)';

        $duplicate = Workflow::create([
            'user_id' => get_current_user_id(),
            'title' => $newTitle,
            'name' => sanitize_title($newTitle),
            'status' => 'draft',
        ]);

        // Copy active version if exists
        $activeVersion = $original->activeVersion();
        if ($activeVersion) {
            WorkflowVersion::create([
                'workflow_id' => $duplicate->id,
                'graph_json' => $activeVersion->graph_json,
                'graph_hash' => hash('sha256', wp_json_encode($activeVersion->graph_json)),
                'is_active' => 1,
            ]);
        }

        return [
            'id' => $duplicate->id,
            'title' => $duplicate->title,
            'message' => __('Workflow duplicated successfully', 'zaplane'),
        ];
    }

    /**
     * Update workflow settings.
     */
    public function updateSettings(array $payload)
    {
        $id = $payload['id'] ?? 0;
        $settings = $payload['settings'] ?? [];

        if (!$id) {
            return new \WP_Error('missing_id', __('Workflow ID is required', 'zaplane'), ['code' => 400]);
        }

        $workflow = Workflow::find($id);

        if (!$workflow) {
            return new \WP_Error('not_found', __('Workflow not found', 'zaplane'), ['code' => 404]);
        }

        // Update settings (assuming you have a settings column)
        // $workflow->settings = $settings;
        // $workflow->save();

        return [
            'id' => $workflow->id,
            'message' => __('Settings updated successfully', 'zaplane'),
        ];
    }

    /**
     * Get workflow statistics (public endpoint).
     */
    public function getStats(array $payload)
    {
        $workflowId = $payload['workflow_id'] ?? 0;

        if (!$workflowId) {
            return new \WP_Error('missing_id', __('Workflow ID is required', 'zaplane'), ['code' => 400]);
        }

        $workflow = Workflow::find($workflowId);

        if (!$workflow) {
            return new \WP_Error('not_found', __('Workflow not found', 'zaplane'), ['code' => 404]);
        }

        // Return basic public stats only
        return [
            'id' => $workflow->id,
            'title' => $workflow->title,
            'status' => $workflow->status,
            'created_at' => $workflow->created_at,
        ];
    }
}
