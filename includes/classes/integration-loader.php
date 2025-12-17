<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IntegrationLoader {

    protected static array $integrations = [];

    /**
     * Load all integrations
     */
    public static function load(): void {

        if ( ! empty( self::$integrations ) ) {
            return;
        }

        /**
         * Allow integrations to self-register
         */
        do_action( 'zaplane_register_integrations' );
    }

    /**
     * Register an integration class
     */
    public static function register( string $class ): void {

        if ( ! class_exists( $class ) ) {
            return;
        }

        $slug = $class::get_slug();
        self::$integrations[ $slug ] = $class;
    }

    /**
     * Get integration by slug
     */
    public static function get( string $slug ): ?string {
        return self::$integrations[ $slug ] ?? null;
    }

    /**
     * Get all integrations (UI usage)
     */
    public static function all(): array {
        return self::$integrations;
    }
}
