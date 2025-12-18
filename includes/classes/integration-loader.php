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

        error_log(print_r('Run Integration Loader', true));

        self::register(\Zaplane\Integration\Wordpress::class);
        self::register(\Zaplane\Integration\Woo::class);
        self::register(\Zaplane\Integration\Slack::class);
        self::register(\Zaplane\Integration\Gmail::class);
        self::register(\Zaplane\Integration\Trello::class);
        self::register(\Zaplane\Integration\Stripe::class);
        self::register(\Zaplane\Integration\Mailerlite::class);

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
        error_log(print_r(self::$integrations, true));
        return self::$integrations[ $slug ] ?? null;
    }

    /**
     * Get all integrations (UI usage)
     */
    public static function all(): array {
        return self::$integrations;
    }
}
