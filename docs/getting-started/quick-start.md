# Quick Start Guide

Get up and running with Zaplane in 5 minutes!

---

## Prerequisites

Before you begin, ensure you have:

- ✅ WordPress 6.0 or higher
- ✅ PHP 8.0 or higher
- ✅ MySQL 5.7+ or MariaDB 10.3+
- ✅ Composer installed

---

## Step 1: Installation

### Option A: Via Composer
```bash
cd /path/to/wordpress/wp-content/plugins
composer create-project zaplane/zaplane zaplane
```

### Option B: Manual Installation
```bash
# Clone the repository
git clone https://github.com/yourusername/zaplane.git wp-content/plugins/zaplane

# Navigate to plugin directory
cd wp-content/plugins/zaplane

# Install dependencies
composer install --no-dev
```

---

## Step 2: Activate Plugin

### Via WP-CLI
```bash
wp plugin activate zaplane
```

### Via WordPress Admin
1. Navigate to **Plugins** → **Installed Plugins**
2. Find **Zaplane** in the list
3. Click **Activate**

---

## Step 3: Verify Installation

```bash
# Check if plugin is active
wp plugin list --status=active | grep zaplane

# Run system check
wp eval 'echo zaplane_config("app.name");'
# Should output: Zaplane
```

---

## Step 4: Access Zaplane

1. Navigate to **Zaplane** in the WordPress admin menu
2. You should see the Zaplane dashboard

---

## Step 5: Create Your First Workflow

### Via Admin UI
1. Go to **Zaplane** → **Workflows**
2. Click **Create New Workflow**
3. Add a **Trigger** (e.g., "Post Published")
4. Add an **Action** (e.g., "Send Slack Message")
5. Configure the action
6. Click **Save & Activate**

### Via Code
```php
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

// Create workflow
$workflow = Workflow::create([
    'user_id' => get_current_user_id(),
    'title' => 'My First Workflow',
    'name' => 'my-first-workflow',
    'status' => 'active'
]);

// Create workflow version with graph
$version = WorkflowVersion::create([
    'workflow_id' => $workflow->id,
    'graph_json' => json_encode([
        'nodes' => [
            [
                'id' => 'trigger_1',
                'type' => 'trigger',
                'data' => [
                    'app' => 'WordPress',
                    'event' => 'publish_post'
                ]
            ],
            [
                'id' => 'action_1',
                'type' => 'action',
                'data' => [
                    'app' => 'Slack',
                    'action' => 'send_message',
                    'config' => [
                        'channel' => '#general',
                        'text' => 'New post published!'
                    ]
                ]
            ]
        ],
        'edges' => [
            ['source' => 'trigger_1', 'target' => 'action_1']
        ]
    ]),
    'is_active' => true
]);
```

---

## Step 6: Test Your Workflow

### Trigger Test
```bash
# Publish a test post
wp post create --post_title="Test Post" --post_status=publish

# Check workflow runs
wp eval 'var_dump(\Zaplane\Models\Run::latest()->limit(5)->get());'
```

### Check Logs
```bash
# View recent logs
wp eval '
$logs = \Zaplane\Models\NodeRun::latest()->limit(10)->get();
foreach ($logs as $log) {
    echo $log->node_key . " - " . $log->status . "\n";
}
'
```

---

## Configuration

### Basic Config

Create or edit `wp-config.php`:

```php
// Enable debug mode
define('ZAPLANE_ALLOW_LOGS', true);

// Set log level
define('ZAPLANE_LOG_LEVEL', 'debug');
```

### Advanced Config

Edit `includes/config/app.php`:

```php
return [
    'name' => 'Zaplane',
    'version' => ZAPLANE_VERSION,
    'debug' => defined('WP_DEBUG') && WP_DEBUG,
    // ... more config
];
```

---

## Verify Everything Works

Run this test script:

```bash
wp eval '
echo "=== Zaplane System Check ===\n\n";

// Check config
echo "Config: " . zaplane_config("app.name") . " ✅\n";

// Check database
echo "Workflows: " . \Zaplane\Models\Workflow::all()->count() . " ✅\n";

// Check integrations
$loader = \Zaplane\Framework\Core\IntegrationLoader::init();
$integrations = $loader->getRegistry();
echo "Integrations: " . count($integrations) . " ✅\n";

// Check logging
zaplane_log_info("System check completed");
echo "Logging: Working ✅\n";

echo "\n✅ All systems operational!\n";
'
```

Expected output:
```
=== Zaplane System Check ===

Config: Zaplane ✅
Workflows: 0 ✅
Integrations: 11 ✅
Logging: Working ✅

✅ All systems operational!
```

---

## Next Steps

Now that Zaplane is installed and running:

1. 📖 Read the [Architecture Overview](../architecture/overview.md)
2. 🔌 Learn about [Creating Integrations](../guides/creating-integrations.md)
3. 💾 Understand [Working with Models](../guides/working-with-models.md)
4. ⚡ Review [Performance Optimization](../performance/optimization.md)

---

## Troubleshooting

### Plugin won't activate

**Problem**: Error during activation

**Solution**: Check PHP and WordPress versions
```bash
php -v  # Should be 8.0+
wp core version  # Should be 6.0+
```

### Database tables not created

**Problem**: Tables missing after activation

**Solution**: Run migrations manually
```bash
wp eval '\Zaplane\Framework\Database\ORM\Migrator::getInstance()->run();'
```

### Config not loading

**Problem**: `zaplane_config()` returns null

**Solution**: Check that constants are defined
```bash
wp eval 'var_dump(defined("ZAPLANE_INCLUDES_DIR_PATH"));'
```

### Workflows not executing

**Problem**: Workflows don't run when triggered

**Solution**: Check Action Scheduler
```bash
# Check pending actions
wp action-scheduler list --status=pending

# Process queue manually
wp action-scheduler run
```

---

## Getting Help

- 📚 [Full Documentation](../README.md)
- 🐛 [Report Issues](https://github.com/yourusername/zaplane/issues)
- 💬 [Discussions](https://github.com/yourusername/zaplane/discussions)
- 📧 Email: support@zaplane.pro

---

## What's Next?

- [Installation Guide](installation.md) - Detailed installation steps
- [Configuration Guide](configuration.md) - Advanced configuration
- [First Workflow](first-workflow.md) - Build a complete workflow
- [Architecture Overview](../architecture/overview.md) - Understand the system

---

🎉 **Congratulations!** You're now ready to build powerful WordPress automations with Zaplane!
