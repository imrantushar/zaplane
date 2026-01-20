<?php
namespace Zaplane\Integrations;



if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Classes\IntegrationBase;
// use Zaplane\Integrations\Wordpress\PostActionsTrait;
// use Zaplane\Integrations\Wordpress\TaxonomyActionsTrait;
// use Zaplane\Integrations\Wordpress\UserActionsTrait;
// use Zaplane\Integrations\Wordpress\RoleActionsTrait;
// use Zaplane\Integrations\Wordpress\OptionActionsTrait;
// use Zaplane\Integrations\Wordpress\MediaActionsTrait;
// use Zaplane\Integrations\Wordpress\CommentActionsTrait;
use Zaplane\Integrations\Storeengine\QueryTrait;
use Zaplane\Integrations\Storeengine\Helper;


class Storeengine extends IntegrationBase {
    // use PostActionsTrait;
    // use TaxonomyActionsTrait;
    // use UserActionsTrait;
    // use RoleActionsTrait;
    // use OptionActionsTrait;
    // use MediaActionsTrait;
    // use CommentActionsTrait;
    use QueryTrait;
    use Helper;

    public static function get_slug(): string {
        return 'storeengine';
    }

    /* =====================================================
     * TRIGGERS
     * ===================================================== */

    public static function get_triggers(): array {
        return [
            // Posts
            //Media
            // Users
            // Auth
            // Comments
            // Terms / Taxonomy
            //Plugin // Theme
            // Options / System
        ];
    }


    /**
     * Trigger UI Schema
     */
    public static function get_trigger_config_schema( string $trigger ): array {
        return [];
    }

    private static function resolve_comment_payload( int $comment_id ) {
    }


    /* =====================================================
     * TRIGGER PAYLOAD
     * ===================================================== */

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            /* ---------------- POSTS ---------------- */
            /* ---------------- MEDIA ---------------- */
            /* ---------------- COMMENTS ---------------- */
            /* ---------------- Plugin / Theme  ---------------- */
            /* ---------------- USERS ---------------- */
            /* ---------------- TERMS ---------------- */
            /* ---------------- SYSTEM ---------------- */
        }

        return false;
    }


    /* =====================================================
     * ACTIONS
     * ===================================================== */

    public static function get_actions(): array {
        return [
        ];
    }

    private static function field_comment_id(): array {
        return [];
    }

    /**
     * Action UI Schema
     */
    public static function get_action_config_schema( string $action ): array {

        $schemas = [

            /* ---------- POSTS ---------- */
            /* ---------- COMMENTS ---------- */
            /* ---------- USERS ---------- */
            /* ---------- OPTIONS ---------- */
            /* ---------- MEDIA ---------- */
            /* ---------- PLUGIN / THEME ---------- */
            /* ---------- AUTH ---------- */
        ];

        return $schemas[$action] ?? [];
    }

    /* =====================================================
     * ACTION EXECUTION
     * ===================================================== */

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];
        $event = $node['data']['event'];

        $method = 'action_' . $event;

        if (method_exists(static::class, $method)) {
            return static::$method($config, $input);
        }

        return ['port' => 'main', 'data' => $input];
    }

    /* =====================================================
     * DYNAMIC DATA QUERIES (API)
     * ===================================================== */

    // public static function get_dynamic_queries(): array {
    // }
}
