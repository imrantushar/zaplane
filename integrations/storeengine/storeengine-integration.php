<?php

namespace Zaplane\Integrations\Storeengine;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\WordPressPluginIntegration;

/**
 * StoreEngine Integration
 *
 * Identity and availability only.
 * Triggers and actions are in separate modular files.
 */
class StoreengineIntegration extends WordPressPluginIntegration {

    public static function get_slug(): string {
        return 'storeengine';
    }

    public static function get_name(): string {
        return 'StoreEngine';
    }

    public static function get_icon(): string {
        return 'storeengine';
    }
}
