<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Models\QueueJob;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;

if (!defined('ABSPATH')) exit;

/**
 * WP-CLI command: wp zaplane queue:status
 *
 * Shows queue health: pending jobs, running jobs, failed jobs, stale jobs.
 *
 * Usage:
 *   wp zaplane queue:status
 *   wp zaplane queue:status --stale-minutes=30
 */
class QueueStatusCommand extends Command
{
    protected string $signature = 'queue:status';
    protected string $description = 'Show queue health and job statistics';

    public function handle(array $args, array $assoc_args): void
    {
        $stale_minutes = (int) ($assoc_args['stale-minutes'] ?? 15);

        $this->info('Zaplane Queue Status');
        $this->line('');

        // Queue jobs
        try {
            $available = QueueJob::available();
            $available_count = count($available);
        } catch (\Throwable $e) {
            $available_count = 0;
        }

        try {
            $stale = QueueJob::stale($stale_minutes);
            $stale_count = count($stale);
        } catch (\Throwable $e) {
            $stale_count = 0;
        }

        // Runs
        try {
            $running_runs = Run::running();
            $running_count = count($running_runs);
        } catch (\Throwable $e) {
            $running_count = 0;
        }

        // Node Runs
        try {
            $pending_nodes = NodeRun::pending();
            $pending_node_count = count($pending_nodes);
        } catch (\Throwable $e) {
            $pending_node_count = 0;
        }

        try {
            $failed_nodes = NodeRun::where('status', 'failed')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();
            $failed_node_count = NodeRun::where('status', 'failed')->count();
        } catch (\Throwable $e) {
            $failed_nodes = [];
            $failed_node_count = 0;
        }

        // Summary
        $this->line('  Queue Jobs:');
        $this->line("    Available:     {$available_count}");
        $this->line("    Stale (>{$stale_minutes}m): {$stale_count}");
        $this->line('');

        $this->line('  Workflow Runs:');
        $this->line("    Running:       {$running_count}");
        $this->line('');

        $this->line('  Node Runs:');
        $this->line("    Pending:       {$pending_node_count}");
        $this->line("    Failed (total):{$failed_node_count}");
        $this->line('');

        // Warnings
        if ($stale_count > 0) {
            $this->warning("Found {$stale_count} stale queue job(s) locked for more than {$stale_minutes} minutes");
        }

        if ($failed_node_count > 0) {
            $this->line('  Recent failures:');
            foreach ($failed_nodes as $node) {
                $error = is_array($node->output_json) ? ($node->output_json['error'] ?? 'Unknown') : 'Unknown';
                $error = substr($error, 0, 80);
                $this->line("    Run #{$node->run_id} / Node {$node->node_key}: {$error}");
            }
            $this->line('');
        }

        if ($stale_count === 0 && $failed_node_count === 0) {
            $this->success('Queue is healthy');
        }
    }
}
