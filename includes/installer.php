<?php

namespace Zaplane;

use Zaplane\Database\ORM\Migrator;

if (!defined('ABSPATH')) exit;

class Installer
{
    protected static ?self $instance = null;
    protected string $db_version_option = 'zaplane_db_version';
    public string $plugin_version;

    public static function init(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->plugin_version = ZAPLANE_VERSION;
    }

    public function run(): void
    {
        $current_db_version = get_option($this->db_version_option, '0.0.0');

        if (version_compare($current_db_version, $this->plugin_version, '<')) {
            $this->migrate();
            update_option($this->db_version_option, $this->plugin_version);
        }
    }

    protected function migrate(): void
    {
        $migrator = Migrator::getInstance();
        $migrator->run();
    }

    public static function uninstall(): void
    {
        // Optionally drop all tables on uninstall
        // Migrator::getInstance()->reset();
    }
}
