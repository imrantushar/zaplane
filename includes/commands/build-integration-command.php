<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Core\IntegrationLoader;

if (!defined('ABSPATH')) exit;

class BuildIntegrationCommand extends Command
{
    protected string $signature = 'build:integration';
    protected string $description = 'Generate integrations.json manifest from registered integrations';

    public function handle(array $args, array $assoc_args): void
    {
        $this->info('🔨 Building integrations.json manifest...');

        $manifest = [
            'version'      => defined('ZAPLANE_VERSION') ? ZAPLANE_VERSION : '1.0.0',
            'generated_at' => current_time('mysql'),
            'apps'         => [],
            'tools'        => [],
        ];

        $appCount = 0;
        $toolCount = 0;

        foreach (IntegrationLoader::all() as $slug => $instance) {
            $class = get_class($instance);

            $category = method_exists($class, 'get_category')
                ? $class::get_category()
                : 'app';

            $integration = [
                'slug'     => $slug,
                'name'     => $class::get_name(),
                'icon'     => $class::get_icon(),
                'category' => $category,
                'triggers' => [],
                'actions'  => [],
            ];

            // Use IntegrationLoader to get triggers (combines modular + legacy)
            foreach (IntegrationLoader::getTriggers($slug) as $key => $trigger) {
                if ($category === 'tool') {
                    continue;
                }

                if (!isset($trigger['hook'])) {
                    $this->warning("⚠️  Trigger '{$key}' in {$slug} is missing 'hook' field - skipping");
                    continue;
                }

                $integration['triggers'][$key] = [
                    'key'     => $key,
                    'label'   => $trigger['label'] ?? $key,
                    'hook'    => $trigger['hook'],
                    'schema'  => $trigger['config_schema'] ?? IntegrationLoader::getTriggerConfigSchema($slug, $key),
                    'outputs' => $trigger['output_schema'] ?? [],
                ];
            }

            // Use IntegrationLoader to get actions (combines modular + legacy)
            foreach (IntegrationLoader::getActions($slug) as $key => $action) {
                $integration['actions'][$key] = [
                    'key'     => $key,
                    'label'   => $action['label'] ?? $key,
                    'schema'  => $action['config_schema'] ?? IntegrationLoader::getActionConfigSchema($slug, $key),
                    'outputs' => $action['output_ports'] ?? ['main'],
                ];
            }

            if ($category === 'tool') {
                $manifest['tools'][$slug] = $integration;
                $toolCount++;
                $this->line("  ✓ {$slug} (tool) - " . count($integration['actions']) . " actions");
            } else {
                $manifest['apps'][$slug] = $integration;
                $appCount++;
                $triggerCount = count($integration['triggers']);
                $actionCount = count($integration['actions']);
                $this->line("  ✓ {$slug} (app) - {$triggerCount} triggers, {$actionCount} actions");
            }
        }

        $assetsDir = ZAPLANE_ROOT_DIR_PATH . 'assets/json';
        $file = $assetsDir . '/integrations.json';

        if (!is_dir($assetsDir)) {
            if (!mkdir($assetsDir, 0755, true)) {
                $this->error('Failed to create assets/json/ directory');
                return;
            }
        }

        if (!is_writable($assetsDir)) {
            $this->error('assets/json/ folder is not writable');
            return;
        }

        $json = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->error('Failed to encode JSON');
            return;
        }

        if (file_put_contents($file, $json) === false) {
            $this->error('Failed to write integrations.json file');
            return;
        }

        $this->line('');
        $this->success('✅ Integrations manifest built successfully!');
        $this->line('');
        $this->line("  📦 Apps:  {$appCount}");
        $this->line("  🔧 Tools: {$toolCount}");
        $this->line("  📄 File:  {$file}");
        $this->line('');
    }
}
