<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Essentialaddons extends IntegrationBase {

    public static function get_slug(): string { return 'essentialaddons'; }

    public static function get_triggers(): array {
        return [
            'eael/login-register/after-login' => ['label'=>'User Login','hook'=>'eael/login-register/after-login'],
            'eael/login-register/after-insert-user' => ['label'=>'User Registration','hook'=>'eael/login-register/after-insert-user'],
    
        ];
    }


       public static function resolve_trigger(array $node, array $args) {
        
        switch ($node['event']) {
         case 'eael/login-register/after-login':
             ray($args);

          $form_data  = $args[0] ?? '';
          if(!$form_data){
            return [] ;
          }
          $data  = $form_data->get('sent_data');
          $result  = [];
          foreach($data as $form_field  => $value){
            $result[$form_field]  = $value ;
          }

         case 'eael/login-register/after-insert-user':
           ray($args);
          $form_data  = $args[0] ?? '';
          if(!$form_data){
            return [] ;
          }
          $data  = $form_data->get('sent_data');
          $result  = [];
          foreach($data as $form_field  => $value){
            $result[$form_field]  = $value ;
          }

        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}