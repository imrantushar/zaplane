<?php

namespace Zaplane;

use Zaplane\Framework\Database\ORM\Migrator;
use Zaplane\Settings;
use Zaplane\Database\Seeders\DefaultRecipesSeeder;
use Zaplane\Database\Seeders\BirthdayRecipeSeeder;
use Zaplane\Database\Seeders\InactiveCustomerRecipeSeeder;
use Zaplane\Database\Seeders\OrderCompleteFeedbackRecipeSeeder;
use Zaplane\Database\Seeders\PostPurchaseUpsellRecipeSeeder;
use Zaplane\Database\Seeders\ProductRecommendationRecipeSeeder;
use Zaplane\Database\Seeders\AiWebhookAgentRecipeSeeder;
use Zaplane\Database\Seeders\AiKnowledgeReplyRecipeSeeder;
use Zaplane\Database\Seeders\AiSupportAgentRecipeSeeder;
use Zaplane\Database\Seeders\AiVoiceSupportRecipeSeeder;

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

	/**
	 * Modules that were on by default before they became opt-in, and the version
	 * that changed it. An install predating that change keeps them on.
	 */
	protected const PREVIOUSLY_DEFAULT_ON = [ 'custom_apps', 'knowledge' ];
	protected const MODULE_PIN_OPTION     = 'zaplane_modules_pinned';

	public function run(): void {
		$this->migrate();
		$this->pin_module_state();
		( new DefaultRecipesSeeder() )->run();
		( new BirthdayRecipeSeeder() )->run();
		( new InactiveCustomerRecipeSeeder() )->run();
		( new OrderCompleteFeedbackRecipeSeeder() )->run();
		( new PostPurchaseUpsellRecipeSeeder() )->run();
		( new ProductRecommendationRecipeSeeder() )->run();
		( new AiWebhookAgentRecipeSeeder() )->run();
		( new AiKnowledgeReplyRecipeSeeder() )->run();
		( new AiSupportAgentRecipeSeeder() )->run();
		( new AiVoiceSupportRecipeSeeder() )->run();

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

	/**
	 * Preserve module state across the change that made every module opt-in.
	 *
	 * Custom Apps and Business Knowledge used to default to on, and settings are
	 * stored sparsely — a site that never opened the Modules screen has no saved
	 * value for them at all, so flipping the default would silently switch off
	 * features people are using and make their menus disappear on update.
	 *
	 * Runs once. On an existing install any module without an explicit saved value
	 * is pinned to what it used to resolve to; a fresh install gets the new
	 * opt-in defaults untouched.
	 */
	protected function pin_module_state(): void {
		if ( get_option( self::MODULE_PIN_OPTION ) ) {
			return;
		}

		// Set on first install, so its absence means this install is brand new and
		// there is no prior state to preserve.
		$is_upgrade = (bool) get_option( 'zaplane_first_install_time' );

		if ( $is_upgrade ) {
			$raw   = get_option( Settings::OPTION, '' );
			$saved = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : [] );
			$saved = is_array( $saved ) ? $saved : [];

			$features = isset( $saved['features'] ) && is_array( $saved['features'] ) ? $saved['features'] : [];
			$changed  = false;

			foreach ( self::PREVIOUSLY_DEFAULT_ON as $key ) {
				if ( ! array_key_exists( $key, $features ) ) {
					$features[ $key ] = true;
					$changed          = true;
				}
			}

			if ( $changed ) {
				$saved['features'] = $features;
				update_option( Settings::OPTION, wp_json_encode( $saved ) );
			}
		}

		update_option( self::MODULE_PIN_OPTION, 1, false );
	}

	public static function uninstall(): void {
	}
}
