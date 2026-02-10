<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class EasyDigitalDownload extends IntegrationBase {

    public static function get_slug(): string {
        return 'easyDigitalDownload';
    }

    public static function get_triggers(): array {
        return [
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
      
    }
    
    private static function resolve_order_payload( int $order_id , array $extra= [] ) {
      
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

        
        }
        return false;
    }

    public static function get_actions(): array {
        return [
          
        ];
    }

    public static function get_action_config_schema( string $action ): array {

        $schemas = [

        ];

        return $schemas[$action] ?? [];
    }

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];

        switch ( $node['data']['event'] ?? '' ) {

        }
        return ['port'=>'main','data'=>$input];
    }
}
