<?php
if ( ! defined( 'WP_CLI' ) ) return;

use Zaplane\Core\IntegrationLoader;

class ZaplaneIntegrationJsonGenerator {

    public function __invoke() {

        IntegrationLoader::init();

        $manifest = [
            'version'      => ZAPLANE_VERSION ?? '1.0.0',
            'generated_at' => current_time( 'mysql' ),
            'integrations' => []
        ];

        foreach ( IntegrationLoader::all() as $slug => $class ) {

            $integration = [
                'slug'     => $slug,
                'name'     => $class::get_name(),
                'icon'     => $class::get_icon(),
                'triggers' => [],
                'actions'  => []
            ];

            /* ---------------- TRIGGERS ---------------- */

            foreach ( $class::get_triggers() as $key => $trigger ) {

                $integration['triggers'][ $key ] = [
                    'key'    => $key,
                    'label'  => $trigger['label'],
                    'hook'   => $trigger['hook'],
                    'schema' => method_exists( $class, 'get_trigger_config_schema' )
                        ? $class::get_trigger_config_schema( $key )
                        : [],
                    'outputs' => $class::get_output_ports()
                ];
            }

            /* ---------------- ACTIONS ---------------- */

            foreach ( $class::get_actions() as $key => $action ) {

                $integration['actions'][ $key ] = [
                    'key'    => $key,
                    'label'  => $action['label'],
                    'schema' => method_exists( $class, 'get_action_config_schema' )
                        ? $class::get_action_config_schema( $key )
                        : [],
                    'outputs' => $class::get_output_ports()
                ];
            }

            $manifest['integrations'][ $slug ] = $integration;
        }

        /* -------- Save to plugin assets -------- */

        $file = ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json';

        if ( ! is_writable( dirname( $file ) ) ) {
            \WP_CLI::error( 'assets/ folder is not writable.' );
        }

        file_put_contents(
            $file,
            wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
        );

        \WP_CLI::success( 'Zaplane integrations.json built successfully.' );
        \WP_CLI::log( $file );
    }
}

WP_CLI::add_command( 'zaplane-build-integration', 'ZaplaneIntegrationJsonGenerator' );
