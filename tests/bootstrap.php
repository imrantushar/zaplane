<?php

if ( ! defined( 'ZAPLANE_TESTING' ) ) {
	define( 'ZAPLANE_TESTING', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'ZAPLANE_ROOT_DIR_PATH' ) ) {
	define( 'ZAPLANE_ROOT_DIR_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'ZAPLANE_INCLUDES_DIR_PATH' ) ) {
	define( 'ZAPLANE_INCLUDES_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'includes/' );
}
if ( ! defined( 'ZAPLANE_INTEGRATION_DIR_PATH' ) ) {
	define( 'ZAPLANE_INTEGRATION_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'integrations/' );
}
if ( ! defined( 'ZAPLANE_PLUGIN_SLUG' ) ) {
	define( 'ZAPLANE_PLUGIN_SLUG', 'zaplane' );
}
if ( ! defined( 'ZAPLANE_ALLOW_LOGS' ) ) {
	define( 'ZAPLANE_ALLOW_LOGS', false );
}

require_once ZAPLANE_ROOT_DIR_PATH . 'vendor/autoload.php';
require_once __DIR__ . '/WPDBMock.php';
require_once __DIR__ . '/WPMocks.php';
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/Integrations/IntegrationTestCase.php';

global $wpdb;
$wpdb = new \Zaplane\Tests\WPDBMock();

require_once ZAPLANE_INCLUDES_DIR_PATH . 'autoload.php';
require_once ZAPLANE_INCLUDES_DIR_PATH . 'framework/functions.php';
require_once ZAPLANE_INCLUDES_DIR_PATH . 'utils/functions.php';
