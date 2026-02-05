<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Models\Option;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;

if (!defined('ABSPATH')) exit;

class ListenerController extends WP_REST_Controller
{
    protected ?Container $container = null;

    private const LISTENER_TIMEOUT = 120;
    private const POLL_INTERVAL = 1;

    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    public function register_routes()
    {
        $ns = 'zaplane/v1';

        register_rest_route($ns, '/node-listener/(?P<workflow_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'start_listener'],
            'permission_callback' => [$this, 'permissions'],
        ]);

        register_rest_route($ns, '/node-listener/(?P<workflow_id>\d+)/stop', [
            'methods' => 'POST',
            'callback' => [$this, 'stop_listener'],
            'permission_callback' => [$this, 'permissions'],
        ]);

        register_rest_route($ns, '/node-listener/(?P<workflow_id>\d+)/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_listener_status'],
            'permission_callback' => [$this, 'permissions'],
        ]);

        register_rest_route($ns, '/node-listener/cleanup', [
            'methods' => 'POST',
            'callback' => [$this, 'cleanup_all_listeners'],
            'permission_callback' => [$this, 'permissions'],
        ]);
    }

    /**
     * Cleanup all stale listeners
     */
    public function cleanup_all_listeners()
    {
        $stateOptions = Option::where('option_name', 'LIKE', 'zaplane_listener_state_%')->get();
        $hookOptions = Option::where('option_name', 'LIKE', 'zaplane_listener_hook_%')->get();

        $deletedStates = 0;
        $deletedHooks = 0;

        foreach ($stateOptions as $option) {
            $option->delete();
            $deletedStates++;
        }

        foreach ($hookOptions as $option) {
            $option->delete();
            $deletedHooks++;
        }

        return [
            'status' => 'success',
            'code' => 'CLEANED',
            'message' => "Cleaned up {$deletedStates} listener states and {$deletedHooks} hook configs",
        ];
    }

    public function permissions()
    {
        return current_user_can('manage_options');
    }

    /**
     * Start listening for a trigger node
     */
    public function start_listener($req)
    {
        $workflowId = (int) $req['workflow_id'];

        $workflow = Workflow::find($workflowId);
        if (!$workflow) {
            return new WP_Error('not_found', 'Workflow not found', ['status' => 404]);
        }

        $version = $workflow->activeVersion();
        if (!$version) {
            return new WP_Error('no_version', 'No active workflow version', ['status' => 404]);
        }

        $graph = $version->getGraph();
        $targetNode = null;

        // Find the trigger node in the workflow
        foreach ($graph['nodes'] as $node) {
            if (($node['type'] ?? '') === 'trigger') {
                $targetNode = $node;
                break;
            }
        }

        if (!$targetNode) {
            return new WP_Error('no_trigger', 'No trigger node found in workflow', ['status' => 404]);
        }

        $nodeKey = (int) $targetNode['id'];

        $hook = $targetNode['data']['hook'] ?? null;
        if (!$hook) {
            return new WP_Error('no_hook', 'Trigger node has no hook defined', ['status' => 400]);
        }

        $optionName = $this->get_option_name($workflowId);

        // Check if already listening - with fresh DB read
        $existingState = $this->get_state_fresh($optionName);
        if ($existingState && $existingState['status'] === 'listening') {
            // Check if it's stale (older than timeout)
            $startedAt = strtotime($existingState['started_at'] ?? '');
            if ($startedAt && (time() - $startedAt) < self::LISTENER_TIMEOUT) {
                return new WP_Error('already_listening', 'Listener is already active for this workflow', ['status' => 409]);
            }
            // Stale listener, clean it up
            Option::remove($optionName);
        }

        // Initialize listener state in database
        $initialState = [
            'status' => 'listening',
            'started_at' => current_time('mysql'),
            'hook' => $hook,
            'workflow_id' => $workflowId,
            'node_key' => $nodeKey,
            'data' => null,
            'triggered_at' => null,
        ];
        Option::set($optionName, $initialState, 'no');

        // Register the hook listener info
        $this->register_listener_hook($workflowId, $hook, $targetNode, $version);

        // Long-poll: wait for trigger to fire or timeout
        $startTime = time();

        while (time() - $startTime < self::LISTENER_TIMEOUT) {
            // Force fresh read from database (bypass all caches)
            $state = $this->get_state_fresh($optionName);

            if (!$state) {
                // State was deleted - stopped
                $this->unregister_listener_hook($workflowId);
                return [
                    'status' => 'stopped',
                    'code' => 'STOPPED',
                    'message' => 'Listener was stopped',
                    'data' => null,
                ];
            }

            // Check if stopped manually
            if ($state['status'] === 'stopped') {
                $this->cleanup($optionName, $workflowId);
                return [
                    'status' => 'stopped',
                    'code' => 'STOPPED',
                    'message' => 'Listener was stopped',
                    'data' => null,
                ];
            }

            // Check if trigger fired
            if ($state['status'] === 'triggered' && $state['data'] !== null) {
                $triggerData = $state['data'];
                $this->cleanup($optionName, $workflowId);

                // Execute the workflow with the captured data
                $result = $this->execute_triggered_workflow($version, $targetNode, $triggerData);

                return [
                    'status' => 'success',
                    'code' => 'TRIGGERED',
                    'data' => [
                        'node' => [
                            'id' => $nodeKey,
                            'app' => $targetNode['data']['app'] ?? null,
                            'event' => $targetNode['data']['event'] ?? null,
                            'label' => $targetNode['data']['label'] ?? null,
                        ],
                        'trigger_data' => $triggerData,
                        'run' => $result,
                    ],
                ];
            }

            // Sleep before next check
            sleep(self::POLL_INTERVAL);
        }

        // Timeout reached
        $this->cleanup($optionName, $workflowId);

        return [
            'status' => 'timeout',
            'code' => 'TIMEOUT',
            'message' => 'Listener timed out after ' . self::LISTENER_TIMEOUT . ' seconds',
            'data' => null,
        ];
    }

    /**
     * Stop an active listener
     */
    public function stop_listener($req)
    {
        $workflowId = (int) $req['workflow_id'];
        $optionName = $this->get_option_name($workflowId);

        $state = $this->get_state_fresh($optionName);

        if (!$state) {
            // No listener found, clean up anyway
            $this->cleanup($optionName, $workflowId);
            return [
                'status' => 'success',
                'code' => 'STOPPED',
                'message' => 'Listener stopped (was not active)',
            ];
        }

        // Full cleanup - delete state and hook info
        $this->cleanup($optionName, $workflowId);

        return [
            'status' => 'success',
            'code' => 'STOPPED',
            'message' => 'Listener stopped and cleaned up successfully',
        ];
    }

    /**
     * Get listener status
     */
    public function get_listener_status($req)
    {
        $workflowId = (int) $req['workflow_id'];
        $optionName = $this->get_option_name($workflowId);

        $state = $this->get_state_fresh($optionName);

        if (!$state) {
            return [
                'status' => 'inactive',
                'listening' => false,
            ];
        }

        return [
            'status' => $state['status'],
            'listening' => $state['status'] === 'listening',
            'started_at' => $state['started_at'] ?? null,
            'hook' => $state['hook'] ?? null,
            'node_key' => $state['node_key'] ?? null,
        ];
    }

    /**
     * Register hook info for the listener
     */
    private function register_listener_hook(int $workflowId, string $hook, array $node, WorkflowVersion $version): void
    {
        $hookInfoOption = 'zaplane_listener_hook_' . $workflowId;
        Option::set($hookInfoOption, [
            'hook' => $hook,
            'node' => $node,
            'version_hash' => $version->graph_hash,
            'workflow_id' => $workflowId,
        ], 'no');
    }

    /**
     * Unregister listener hook info
     */
    private function unregister_listener_hook(int $workflowId): void
    {
        $hookInfoOption = 'zaplane_listener_hook_' . $workflowId;
        Option::remove($hookInfoOption);
    }

    /**
     * Execute the workflow after trigger fires
     */
    private function execute_triggered_workflow(WorkflowVersion $version, array $triggerNode, array $payload): array
    {
        $run = Run::create([
            'workflow_version_hash' => $version->graph_hash,
            'status' => 'running',
            'trigger_data' => $payload,
            'start_node_key' => (int) $triggerNode['id'],
            'target_node_key' => null,
            'started_at' => current_time('mysql'),
        ]);

        // Execute trigger node synchronously
        $nodeRun = NodeRun::create([
            'run_id' => $run->id,
            'node_key' => (int) $triggerNode['id'],
            'parent_node_run_id' => null,
            'status' => 'completed',
            'input_json' => $payload,
            'output_json' => $payload,
            'started_at' => current_time('mysql'),
            'finished_at' => current_time('mysql'),
        ]);

        // Spawn child nodes asynchronously
        $graph = $version->getGraph();
        foreach ($graph['edges'] as $edge) {
            if ((int) $edge['source'] === (int) $triggerNode['id']) {
                $this->container->get('automation')->spawn_node_run(
                    $run->id,
                    (int) $edge['target'],
                    $payload,
                    $nodeRun->id
                );
            }
        }

        return [
            'run_id' => $run->id,
            'node_run_id' => $nodeRun->id,
            'input' => $payload,
            'output' => $payload,
        ];
    }

    /**
     * Cleanup all listener resources
     */
    private function cleanup(string $optionName, int $workflowId): void
    {
        Option::remove($optionName);
        $this->unregister_listener_hook($workflowId);
    }

    /**
     * Get option name for listener state
     */
    private function get_option_name(int $workflowId): string
    {
        return 'zaplane_listener_state_' . $workflowId;
    }

    /**
     * Get state with fresh DB read (bypass all caches)
     * Note: Using direct DB query here to ensure we always get fresh data
     * during the polling loop, bypassing any ORM or WordPress caching
     */
    private function get_state_fresh(string $optionName): ?array
    {
        global $wpdb;

        // Direct DB query to bypass all caches
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $optionName
            )
        );

        if ($value === null) {
            return null;
        }

        return maybe_unserialize($value);
    }
}
