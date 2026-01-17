<?php

define('ZAPLANE_TESTING', true);
define('ABSPATH', dirname(__DIR__) . '/');
define('ZAPLANE_ROOT_DIR_PATH', dirname(__DIR__) . '/');
define('ZAPLANE_INCLUDES_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'includes/');
define('ZAPLANE_INTEGRATION_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'integration/');
define('ZAPLANE_PLUGIN_SLUG', 'zaplane');
define('ZAPLANE_ALLOW_LOGS', false);

require_once ZAPLANE_ROOT_DIR_PATH . 'vendor/autoload.php';
require_once ZAPLANE_INCLUDES_DIR_PATH . 'autoload.php';

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/WPMocks.php';
require_once __DIR__ . '/WPDBMock.php';
