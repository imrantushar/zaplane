<?php
namespace Zaplane\API;

use WP_REST_Controller;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) exit;

class IntegrationsController extends WP_REST_Controller {

    protected ?Container $container = null;

    public function __construct(?Container $container = null) {
        $this->container = $container;
    }

    public function register_routes() {

        $namespace = 'zaplane/v1';

        register_rest_route( $namespace, '/integrations', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_integrations' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( $namespace, '/integrations/(?P<slug>[a-z0-9_-]+)/triggers', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_triggers' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( $namespace, '/integrations/(?P<slug>[a-z0-9_-]+)/triggers/(?P<trigger>[a-z0-9_-]+)/schema', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_trigger_schema' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( $namespace, '/integrations/(?P<slug>[a-z0-9_-]+)/actions', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_actions' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( $namespace, '/integrations/(?P<slug>[a-z0-9_-]+)/actions/(?P<action>[a-z0-9_-]+)/schema', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_action_schema' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_integrations() {
        $out = [];

        return rest_ensure_response( $out );
    }

    /**
     * Get triggers for an integration.
     *
     * Uses IntegrationLoader::getTriggers() which combines modular triggers
     * (from /triggers/*.php) with legacy triggers (from get_triggers()).
     */
    public function get_triggers( $request ) {
        $slug = $request['slug'];

        // Check if integration exists
        if ( ! IntegrationLoader::has( $slug ) ) {
            return new \WP_Error( 'not_found', 'Integration not found', [ 'status' => 404 ] );
        }

        // Get combined triggers (modular + legacy)
        $triggers = IntegrationLoader::getTriggers( $slug );

        return rest_ensure_response( $triggers );
    }

    /**
     * Get trigger config schema for an integration.
     *
     * Uses IntegrationLoader::getTriggerConfigSchema() which checks modular
     * triggers first, then falls back to legacy get_trigger_config_schema().
     */
    public function get_trigger_schema( $request ) {
        $slug    = $request['slug'];
        $trigger = $request['trigger'];

        // Check if integration exists
        if ( ! IntegrationLoader::has( $slug ) ) {
            return new \WP_Error( 'not_found', 'Integration not found', [ 'status' => 404 ] );
        }

        // Get config schema (modular or legacy)
        $schema = IntegrationLoader::getTriggerConfigSchema( $slug, $trigger );

        return rest_ensure_response( $schema );
    }

    /**
     * Get actions for an integration.
     *
     * Uses IntegrationLoader::getActions() which combines modular actions
     * (from /actions/*.php) with legacy actions (from get_actions()).
     */
    public function get_actions( $request ) {
        $slug = $request['slug'];

        // Check if integration exists
        if ( ! IntegrationLoader::has( $slug ) ) {
            return new \WP_Error( 'not_found', 'Integration not found', [ 'status' => 404 ] );
        }

        // Get combined actions (modular + legacy)
        $actions = IntegrationLoader::getActions( $slug );

        return rest_ensure_response( $actions );
    }

    /**
     * Get action config schema for an integration.
     *
     * Uses IntegrationLoader::getActionConfigSchema() which checks modular
     * actions first, then falls back to legacy get_action_config_schema().
     */
    public function get_action_schema( $request ) {
        $slug   = $request['slug'];
        $action = $request['action'];

        // Check if integration exists
        if ( ! IntegrationLoader::has( $slug ) ) {
            return new \WP_Error( 'not_found', 'Integration not found', [ 'status' => 404 ] );
        }

        // Get config schema (modular or legacy)
        $schema = IntegrationLoader::getActionConfigSchema( $slug, $action );

        return rest_ensure_response( $schema );
    }
}
