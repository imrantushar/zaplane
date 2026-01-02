<?php
namespace Zaplane;

if (!defined('ABSPATH')) exit;

class Installer {

    protected static ?self $instance = null;
    protected string $db_version_option = 'zaplane_db_version';
    public string $plugin_version;

    /**
     * Singleton instance
     */
    public static function init(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->plugin_version = ZAPLANE_VERSION;
    }

    /**
     * Run Installer / Migration on activation or version change
     */
    public function run(): void {
        $current_db_version = get_option($this->db_version_option, '0.0.0');
        // Only run migration if plugin version > db version
        if (version_compare($current_db_version, $this->plugin_version, '<')) {
            $this->migrate();
            update_option($this->db_version_option, $this->plugin_version);
        }

    }

    /**
     * Migration logic (DB tables)
     */
    protected function migrate(): void {
        // Call Database helper to create tables
        Database::create_initial_custom_table();
    }
}
