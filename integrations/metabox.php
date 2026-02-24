<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Metabox extends IntegrationBase {

    public static function get_slug(): string { return 'metabox'; }

    public static function get_triggers(): array {
        return [
            'rwmb_frontend_after_save_post' => ['label'=>'Form Submission','hook'=>'rwmb_frontend_after_save_post'],
        ];
    }


       public static function resolve_trigger(array $node, array $args) {
        
        switch ($node['event']) {
         case 'rwmb_frontend_after_save_post':
          $form_data  = $args[1] ?? '';
          if(!$form_data){
            return [] ;
          }
          $result  = [];
          foreach($form_data as $form_field  => $value){
            $result[$form_field]  = $value ;
          }
          return $result ;

        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}

