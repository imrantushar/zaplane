<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Advancedcustomfields extends IntegrationBase {

    public static function get_slug(): string { return 'Advancedcustomfields'; }

    public static function get_triggers(): array {
        return [
            'acf/save_post' => ['label'=>'Options Page Field is Updated','hook'=>'acf/save_post'],
            'updated_post_meta' => ['label'=>'Post Custom Field is Updated','hook'=>'updated_post_meta'],
            'updated_user_meta' => ['label'=>'User Custom Field is Updated','hook'=>'updated_user_meta'],
           
        ];
    }


       public static function resolve_trigger(array $node, array $args) {
        
        switch ($node['event']) {
         case 'acf/save_post':
          ray($args);
          $form_data  = $args[1] ?? '';
          if(!$form_data){
            return [] ;
          }
          $result  = [];
          foreach($form_data as $form_field  => $value){
            $result[$form_field]  = $value ;
          }
          return $result ;
          
       case 'updated_post_meta':
          $form_data  = $args[1] ?? '';
          ray($args);
          if(!$form_data){
            return [] ;
          }
          $result  = [];
          foreach($form_data as $form_field  => $value){
            $result[$form_field]  = $value ;
          }
          return $result ;
       case 'updated_user_meta':
        ray($args);
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

