# Installation Guide

Complete guide to installing and setting up Zaplane on your WordPress site.

---

## System Requirements

### Minimum Requirements

- **WordPress:** 6.0 or higher
- **PHP:** 8.1 or higher
- **MySQL:** 5.7+ or MariaDB 10.3+
- **PHP Extensions:**
  - `json`
  - `mbstring`
  - `openssl`
  - `mysqli`

### Recommended

- **WordPress:** Latest version
- **PHP:** 8.2 or higher
- **MySQL:** 8.0+ or MariaDB 10.6+
- **Server Memory:** 256MB minimum, 512MB recommended
- **PHP Memory Limit:** 256MB or higher

### Server Compatibility

Zaplane works on most hosting providers:

✅ **Supported Platforms:**
- VPS (DigitalOcean, Linode, Vultr)
- Managed WordPress (WP Engine, Kinsta, Flywheel)
- Shared Hosting (SiteGround, Bluehost, DreamHost)
- Local Development (Local by Flywheel, MAMP, XAMPP)

⚠️ **Limited Support:**
- Free hosting with strict resource limits
- Hosts that disable WordPress cron

---

## Installation Methods

### Method 1: WordPress Admin (Recommended)

**Step 1: Download Plugin**

1. Go to [Zaplane Releases](https://github.com/yourusername/zaplane/releases)
2. Download the latest `zaplane.zip` file

**Step 2: Upload Plugin**

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin** button
4. Choose the `zaplane.zip` file
5. Click **Install Now**

**Step 3: Activate Plugin**

1. Click **Activate Plugin** after installation
2. You'll be redirected to the Zaplane welcome screen

**Step 4: Run Setup Wizard**

1. Click **Run Setup Wizard**
2. Configure basic settings
3. Click **Complete Setup**

---

### Method 2: FTP/SFTP

**Step 1: Download and Extract**

```bash
# Download latest release
wget https://github.com/yourusername/zaplane/releases/latest/download/zaplane.zip

# Extract
unzip zaplane.zip
```

**Step 2: Upload via FTP**

1. Connect to your server via FTP/SFTP
2. Navigate to `/wp-content/plugins/`
3. Upload the `zaplane` folder
4. Set folder permissions to 755

**Step 3: Activate**

1. Log in to WordPress admin
2. Go to **Plugins → Installed Plugins**
3. Find **Zaplane** and click **Activate**

---

### Method 3: WP-CLI (Advanced)

Perfect for developers and automated deployments:

```bash
# Install plugin
wp plugin install https://github.com/yourusername/zaplane/releases/latest/download/zaplane.zip

# Activate plugin
wp plugin activate zaplane

# Run migrations
wp zaplane migrate

# Verify installation
wp zaplane status
```

---

### Method 4: Git Clone (Development)

For contributing or custom development:

```bash
# Navigate to plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Clone repository
git clone https://github.com/yourusername/zaplane.git

# Enter directory
cd zaplane

# Install dependencies
composer install
npm install

# Build assets
npm run build

# Activate plugin
wp plugin activate zaplane

# Run migrations
wp zaplane migrate
```

---

## Post-Installation Setup

### 1. Database Migrations

Zaplane automatically runs migrations on activation. To manually run:

```bash
wp zaplane migrate
```

**Verify migrations:**

```bash
wp zaplane migrate:status
```

Expected output:
```
✓ CreateWorkflowsTable (2024_01_01_000001)
✓ CreateWorkflowVersionsTable (2024_01_01_000002)
✓ CreateRunsTable (2024_01_01_000003)
✓ CreateNodeRunsTable (2024_01_01_000004)
✓ CreateConnectionsTable (2024_01_01_000005)
✓ CreateQueueTable (2024_01_01_000006)

All migrations completed successfully.
```

---

### 2. Configure Settings

Navigate to **Zaplane → Settings** in WordPress admin:

**General Settings**
- **Site Name:** Your site name (defaults to WordPress site name)
- **Admin Email:** Email for notifications (defaults to WordPress admin email)
- **Timezone:** Your timezone (defaults to WordPress timezone)

**Performance Settings**
- **Enable Query Cache:** ✅ Recommended (3X performance boost)
- **Cache TTL:** 5 minutes (default)
- **Enable Debug Mode:** ❌ Only for development

**Security Settings**
- **API Access:** Enable REST API access
- **Webhook Secret:** Auto-generated (used for webhook verification)
- **Encryption Key:** Auto-generated (used for credential encryption)

---

### 3. Create Your First Connection

**Step 1: Navigate to Connections**

Go to **Zaplane → Connections** → Click **Add Connection**

**Step 2: Choose Integration**

Select an integration (e.g., Slack, Email, WordPress)

**Step 3: Configure Authentication**

**For API Key:**
```
Integration: Slack
Name: My Slack Workspace
API Key: xoxb-your-api-key-here
```

**For OAuth2:**
1. Click **Connect with OAuth**
2. Authorize in popup window
3. Return to WordPress

**Step 4: Test Connection**

Click **Test Connection** to verify it works.

---

### 4. Create Your First Workflow

See [Quick Start Guide](quick-start.md) for a step-by-step walkthrough.

---

## Verification

### Check Plugin Status

```bash
wp zaplane status
```

Expected output:
```
Zaplane Status Report
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Version:           1.0.0
WordPress:         6.4.2
PHP:               8.2.0
Database:          MySQL 8.0.35

Tables:            ✓ 6/6 tables exist
Migrations:        ✓ All up to date
Cache:             ✓ Working
Queue:             ✓ Working

Workflows:         5 active, 2 paused, 3 draft
Connections:       4 active
Integrations:      15 available

Status:            ✓ Everything working correctly
```

---

### Test Workflows

**Via WP-CLI:**
```bash
# List workflows
wp zaplane workflow:list

# Test specific workflow
wp zaplane workflow:test 123

# Run workflow manually
wp zaplane workflow:run 123 --trigger-data='{"test": true}'
```

**Via Admin:**
1. Go to **Zaplane → Workflows**
2. Create a simple test workflow
3. Click **Test Run**
4. Verify execution in **Run History**

---

## Troubleshooting

### Common Installation Issues

#### Issue: "Plugin could not be activated"

**Cause:** PHP version too low

**Solution:**
```bash
# Check PHP version
php -v

# Update PHP (contact host or use control panel)
```

---

#### Issue: "Database migration failed"

**Cause:** Insufficient database permissions

**Solution:**
```bash
# Check database permissions
wp db query "SHOW GRANTS"

# Grant permissions (run as database admin)
GRANT CREATE, ALTER, DROP ON database_name.* TO 'wp_user'@'localhost';
```

---

#### Issue: "Memory limit exhausted"

**Cause:** PHP memory limit too low

**Solution:**

Add to `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '256M');
```

Or in `php.ini`:
```ini
memory_limit = 256M
```

---

#### Issue: "Workflows not executing"

**Cause:** WordPress cron not working

**Solution:**

**Option 1:** Enable system cron

Add to `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

Add to server cron:
```bash
*/5 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

**Option 2:** Verify WP cron is running
```bash
wp cron event list
```

---

### Debug Mode

Enable debug mode for troubleshooting:

**In wp-config.php:**
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**In Zaplane settings:**
```php
zaplane_config('app.debug', true);
```

**View logs:**
```bash
# WordPress debug log
tail -f wp-content/debug.log

# Zaplane logs (if configured)
tail -f wp-content/uploads/zaplane/logs/zaplane.log
```

---

## Performance Optimization

### Enable Object Cache

**Using Redis:**
```bash
# Install Redis plugin
wp plugin install redis-cache --activate

# Enable Redis
wp redis enable
```

**Using Memcached:**
```bash
# Install Memcached plugin
wp plugin install memcached --activate
```

---

### Configure PHP OPcache

In `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

---

### Database Optimization

```bash
# Optimize tables
wp db optimize

# Add indexes (automatic in migrations)
wp zaplane migrate
```

---

## Security Hardening

### File Permissions

```bash
# Set correct permissions
chmod 755 wp-content/plugins/zaplane
chmod 644 wp-content/plugins/zaplane/**/*.php
chmod 600 wp-config.php
```

### Restrict API Access

In `wp-config.php`:
```php
// Restrict API to logged-in users
add_filter('rest_authentication_errors', function($result) {
    if (!is_user_logged_in()) {
        return new WP_Error(
            'rest_forbidden',
            'Authentication required',
            ['status' => 401]
        );
    }
    return $result;
});
```

### Enable SSL

Ensure your site uses HTTPS:
```bash
# Check SSL
wp option get siteurl
wp option get home

# Update to HTTPS if needed
wp option update siteurl 'https://yoursite.com'
wp option update home 'https://yoursite.com'
```

---

## Multisite Installation

### Network Activate

```bash
# Network activate
wp plugin activate zaplane --network

# Run migrations for all sites
wp site list --field=url | xargs -I {} wp --url={} zaplane migrate
```

### Per-Site Configuration

Each site in the network can have its own:
- Workflows
- Connections
- Settings
- Integrations

---

## Uninstallation

### Remove Plugin

**Via Admin:**
1. Deactivate plugin
2. Click **Delete**
3. Confirm deletion

**Via WP-CLI:**
```bash
wp plugin deactivate zaplane
wp plugin delete zaplane
```

### Remove Database Tables

**Warning:** This deletes ALL workflow data!

```bash
wp zaplane uninstall --force
```

Or manually:
```sql
DROP TABLE IF EXISTS zaplane_workflows;
DROP TABLE IF EXISTS zaplane_workflow_versions;
DROP TABLE IF EXISTS zaplane_runs;
DROP TABLE IF EXISTS zaplane_node_runs;
DROP TABLE IF EXISTS zaplane_connections;
DROP TABLE IF EXISTS zaplane_queue;
```

---

## Next Steps

Now that Zaplane is installed:

1. **[Quick Start](quick-start.md)** - Create your first workflow
2. **[Configuration](configuration.md)** - Configure advanced settings
3. **[Integrations](../guides/creating-integrations.md)** - Connect external services
4. **[Workflows](first-workflow.md)** - Build automation workflows

---

## Getting Help

- **Documentation:** [Full docs](../README.md)
- **Community:** [GitHub Discussions](https://github.com/yourusername/zaplane/discussions)
- **Issues:** [Report a bug](https://github.com/yourusername/zaplane/issues)
- **Support:** [Contact support](mailto:support@zaplane.com)

---

**Questions?** [Open an issue](https://github.com/yourusername/zaplane/issues) or [start a discussion](https://github.com/yourusername/zaplane/discussions).
