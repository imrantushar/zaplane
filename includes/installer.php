<?php

namespace Zaplane;

use Zaplane\Framework\Database\ORM\Migrator;
use Zaplane\Database\Seeders\DefaultRecipesSeeder;
use Zaplane\Database\Seeders\BirthdayRecipeSeeder;
use Zaplane\Database\Seeders\InactiveCustomerRecipeSeeder;
use Zaplane\Database\Seeders\OrderCompleteFeedbackRecipeSeeder;
use Zaplane\Database\Seeders\PostPurchaseUpsellRecipeSeeder;
use Zaplane\Database\Seeders\ProductRecommendationRecipeSeeder;

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
		$this->create_feedback_page();
		( new DefaultRecipesSeeder() )->run();
		( new BirthdayRecipeSeeder() )->run();
		( new InactiveCustomerRecipeSeeder() )->run();
		( new OrderCompleteFeedbackRecipeSeeder() )->run();
		( new PostPurchaseUpsellRecipeSeeder() )->run();
		( new ProductRecommendationRecipeSeeder() )->run();

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

	public function create_feedback_page(): void {
		if ( get_option( 'zaplane_feedback_page_id' ) ) {
			return;
		}

		$existing = get_posts( [
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'name'           => 'feedback',
			'posts_per_page' => 1,
		] );

		if ( $existing ) {
			update_option( 'zaplane_feedback_page_id', $existing[0]->ID );
			return;
		}

		$page_id = wp_insert_post( [
			'post_title'   => 'Feedback',
			'post_name'    => 'feedback',
			'post_content' => '[zaplane_feedback]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		] );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'zaplane_feedback_page_id', $page_id );
		}
	}

	public static function uninstall(): void {
	}
}
