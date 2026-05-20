<?php

namespace Zaplane;

use Zaplane\Framework\Database\ORM\Migrator;
use Zaplane\Database\Seeders\DefaultRecipesSeeder;
use Zaplane\Database\Seeders\BirthdayRecipeSeeder;
use Zaplane\Database\Seeders\InactiveCustomerRecipeSeeder;
use Zaplane\Database\Seeders\OrderCompleteFeedbackRecipeSeeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {

	protected static ?self $instance = null;
	protected string $db_version_option = 'zaplane_db_version';
	public string $plugin_version;

	public static function init(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->plugin_version = ZAPLANE_VERSION;
	}

	public function run(): void {
		$this->migrate();
		( new DefaultRecipesSeeder() )->run();
		( new BirthdayRecipeSeeder() )->run();
		( new InactiveCustomerRecipeSeeder() )->run();
		( new OrderCompleteFeedbackRecipeSeeder() )->run();

		$current_db_version = get_option( $this->db_version_option, '0.0.0' );
		if ( version_compare( $current_db_version, $this->plugin_version, '<' ) ) {
			update_option( $this->db_version_option, $this->plugin_version );
		}

		if ( ! get_option( 'zaplane_first_install_time' ) ) {
			add_option( 'zaplane_first_install_time', time() );
		}
	}

	protected function migrate(): void {
		$migrator = Migrator::getInstance();
		$migrator->run();
	}

	public static function uninstall(): void {
	}
}
