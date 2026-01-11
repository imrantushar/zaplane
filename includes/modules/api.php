<?php
namespace Zaplane\Modules;

use Zaplane\Core\ModuleInterface;
use Zaplane\Classes\Container;

if (!defined('ABSPATH')) exit;

class API implements ModuleInterface {
    protected Container $container;
    protected static ?self $instance = null;

    public static function init(Container $container): self {
        if (!self::$instance) {
            self::$instance = new self($container);
        }
        return self::$instance;
    }

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function register_hooks(): void { 
        add_action( 'rest_api_init', [$this, 'register_route']);
    }
    public function register_route(){
        (new API\IntegrationsController($this->container))->register_routes();
        (new API\WorkflowsController($this->container))->register_routes();
        (new API\RunController($this->container))->register_routes();
        (new API\ConnectionsController($this->container))->register_routes();

        register_rest_route( 'zaplane/v1', '/runs/(?P<id>\d+)', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function ( $req ) {
                global $wpdb;
                return $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}zaplane_run_logs WHERE run_id = %d",
                        $req['id']
                    )
                );
            }
        ]);

        register_rest_route('zaplane/v1', '/dynamic', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => function( $req ) {
                // Get container instance
                $integrationLoader = $this->container->get('integrations');
                $integration = $integrationLoader->get($req['integration']);

                if (! $integration) return [];

                $queries = $integration::get_dynamic_queries();

                $query = $req['query'];

                if (! isset($queries[$query])) return [];

                return call_user_func(
                    $queries[$query],
                    $req->get_json_params()
                );
            }
        ]);
    }
}

