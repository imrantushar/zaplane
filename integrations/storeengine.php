<?php
namespace Zaplane\Integrations;



if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Classes\IntegrationBase;


class Storeengine extends IntegrationBase {
    public static function get_slug(): string {
        return 'storeengine';
    }

    public static function get_triggers(): array {
        return [
            // Posts
            'publish_post'           => ['label' => 'Post Published', 'hook' => 'publish_post'], // example code
        ];
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        if ( $trigger === 'add_action' ) {
            return [
                [
                    'key'      => 'hook_name',
                    'label'    => 'Hook Name',
                    'type'     => 'text',
                    'required' => true,
                ],
            ];
        }
        return [];
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {
            case 'publish_post':
                // return self::resolve_post_payload( $args[0] ?? 0 ); // example code remove before proceed
        }

        return false;
    }

    public static function get_actions(): array {
        return [
            'create_post'                   => ['label'=>'Create Post'], // example code
        ];
    }

    public static function get_action_config_schema( string $action ): array {

        $schemas = [
            'action' => ['schema_key' => 'schema_value' ] // example code
        ];

        return $schemas[$action] ?? [];
    }


    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];
        $event = $node['data']['event'];

        $method = 'action_' . $event;

        if (method_exists(static::class, $method)) {
            return static::$method($config, $input);
        }

        return ['port' => 'main', 'data' => $input];
    }
}
