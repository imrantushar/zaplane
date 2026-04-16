<?php
namespace Zaplane\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {

	protected static $client = null;

	protected function register() {
		if ( ! did_action( 'plugins_loaded' ) ) {
			add_action( 'plugins_loaded', [ $this, 'get_sdk' ] );
		} else {
			$this->get_sdk();
		}
	}

	public function get_sdk() {
		if ( null === self::$client ) {
			self::$client = se_license_init( [
				'package_file'        => ZAPLANE_PLUGIN_FILE,
				'package_name'        => 'Zaplane', // translation remove due to load too early
				'product_id'          => 256,
				'is_free'             => true,
				'use_update'          => true,
				'slug'                => 'zaplane',
				'basename'            => ZAPLANE_PLUGIN_BASENAME,
				'package_type'        => 'plugin',
				'package_version'     => ZAPLANE_VERSION,
				'allow_local'         => true,
				'license_server'      => 'https://store.kodezen.com/',
				'purchase_url'        => 'https://store.kodezen.com/product/zaplane/',
				'product_logo'        => defined( 'ZAPLANE_ASSETS_URI' ) ? ZAPLANE_ASSETS_URI . 'images/zaplane.svg' : '',
				'store_dashboard_url' => 'https://store.kodezen.com/dashboard/license-keys/',
				'terms_url'           => 'https://kodezen.com/terms-and-conditions/',
				'privacy_policy_url'  => 'https://store.kodezen.com/privacy-policy/',
				'ticket_recipient'    => 'support@kodezen.com',
				'primary_color'       => '#2A3BEE',
				'first_install_time'  => get_option( 'gemcrm_first_install_time' ),
				'optin_notice_delay'  => 3 * DAY_IN_SECONDS,
			] );
		}

		return self::$client;
	}
}

// End of file admin.php
