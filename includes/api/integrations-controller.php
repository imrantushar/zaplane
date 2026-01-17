<?php
namespace Zaplane\API;

use WP_REST_Controller;
use Zaplane\Framework\Classes\Container;

if ( ! defined( 'ABSPATH' ) ) exit;

class IntegrationsController extends WP_REST_Controller {

    protected Container $container;

    public function __construct(Container $container) {
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

    public function get_triggers( $request ) {
        $integrationLoader = $this->container->get('integrations'); // loader instance
        $integration = $integrationLoader->get($request['slug']);  // integration class
        if ( ! $integration ) {
            return new \WP_Error( 'not_found', 'Integration not found', [ 'status' => 404 ] );
        }

        return rest_ensure_response( $integration::get_triggers() );
    }

    public function get_trigger_schema( $request ) {
         $integrationLoader = $this->container->get('integrations'); // loader instance
        $integration = $integrationLoader->get($request['slug']);  // integration class
        if ( ! $integration || ! method_exists( $integration, 'get_trigger_config_schema' ) ) {
            return [];
        }

        return rest_ensure_response(
            $integration::get_trigger_config_schema( $request['trigger'] )
        );
    }

    public function get_actions( $request ) {
        $integrationLoader = $this->container->get('integrations'); // loader instance
        $integration = $integrationLoader->get($request['slug']);  // integration class
        if ( ! $integration ) {
            return new \WP_Error( 'not_found', 'Integration not found', [ 'status' => 404 ] );
        }

        return rest_ensure_response( $integration::get_actions() );
    }

    public function get_action_schema( $request ) {
        $integrationLoader = $this->container->get('integrations'); // loader instance
        $integration = $integrationLoader->get($request['slug']);  // integration class
        if ( ! $integration || ! method_exists( $integration, 'get_action_config_schema' ) ) {
            return [];
        }

        return rest_ensure_response(
            $integration::get_action_config_schema( $request['action'] )
        );
    }
}
