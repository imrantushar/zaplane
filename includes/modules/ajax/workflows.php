<?php

namespace Zaplane\Modules\Ajax;

use Zaplane\Models\Workflow;

if (!defined('ABSPATH')) {
    exit;
}

class Workflows extends AbstractAjaxHandler
{
    protected string $action = 'zaplane/update_workflow_status';
    protected bool $requireAuth = true;
    protected string $capability = 'manage_options';
    protected bool $requireNonce = true;

    protected function getValidationRules(): array
    {
        return [
            'id' => [
                'type' => 'int',
                'required' => true,
                'validate' => fn($val) => $val > 0
            ],
            'status' => [
                'type' => 'string',
                'required' => true,
                'validate' => fn($val) => in_array($val, ['active', 'draft', 'inactive'])
            ]
        ];
    }

    protected function handle(array $params)
    {
        $workflow = Workflow::find($params['id']);

        if (!$workflow) {
            throw new \Exception(__('Workflow not found', 'zaplane'), 404);
        }

        $workflow->status = $params['status'];
        $isUpdated = $workflow->save();

        return $isUpdated;
    }
}
