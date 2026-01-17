<?php
namespace Zaplane\Admin;

use Zaplane\Framework\Classes\Helper;

if (!defined('ABSPATH')) exit;

class Assets {

    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_app_assets' ] );
    }

    public function enqueue_app_assets($hook){
        if ( strpos( $hook, '_page_' . ZAPLANE_PLUGIN_SLUG ) !== false ) {
			remove_all_actions( 'admin_notices' ); 
            $dependencies = include_once ZAPLANE_ASSETS_DIR_PATH  . 'build/app.asset.php';
            wp_enqueue_style('zaplane-app-style', ZAPLANE_ASSETS_URI . 'build/app.css', array('wp-components'), filemtime(ZAPLANE_ASSETS_DIR_PATH . 'build/app.css'), 'all');
			wp_enqueue_script(
				'zaplane-app-scripts',
				ZAPLANE_ASSETS_URI . 'build/app.js',
				$dependencies['dependencies'],
				$dependencies['version'],
				true
			);
			wp_localize_script('zaplane-app-scripts', 'ZaplaneGlobal', [
                'nonce'                 => wp_create_nonce('wp_rest'),
                'zaplane_nonce'        => wp_create_nonce('zaplane_nonce'),
                'namespace'             => ZAPLANE_PLUGIN_SLUG . '/v1/',
                'rest_url'              => rest_url(),
                'ajaxurl'               => esc_url(admin_url('admin-ajax.php')),
                'site_url'              => site_url(),
                'admin_url'				=> admin_url(),
                'route_path'            => wp_parse_url(admin_url(), PHP_URL_PATH),
                'plugin_root_url'       => ZAPLANE_PLUGIN_ROOT_URI,
                'menu'                  => wp_json_encode( Helper::get_admin_menu_list() ),
                'integrations' => json_decode(file_get_contents(ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json'), true)
            ]);
			wp_set_script_translations('zaplane-app-scripts', 'zaplane', ZAPLANE_ROOT_DIR_PATH . 'languages/');
        }
    }
}