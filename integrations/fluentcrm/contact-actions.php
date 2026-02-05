<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Traits\ActionResponseTrait;

trait ContactActionsTrait
{
    use ActionResponseTrait; // include success/error helpers

    protected static function action_created_contact(array $config): array
    {
        $field_map        = $config['field_map'] ?? [];
        $custom_field_map = $config['custom_field_map'] ?? [];
        $lists            = $config['lists'] ?? [];
        $tags             = $config['tags'] ?? [];
        $data             = [];
        $email            = '';
                
        foreach ( $field_map as $row ) {
            if ( empty( $row['field'] ) ) continue;
            $data[ $row['field'] ] = $row['value'] ?? '';
            if ( $row['field'] === 'email' ) {
                $email = $row['value'];
            }
        }
        if ( ! $email ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Email is required',],];
        }
        if ( ! class_exists('\FluentCrm\App\Models\Subscriber') ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Not Found',],];
        }
        $contact = \FluentCrm\App\Models\Subscriber::updateOrCreate(['email' => $email], $data );
        if ( ! $contact ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact Create/Update Failed',], ];
        }
        if ( ! empty( $lists ) && $lists !== 'any' ) {
            $contact->attachLists( (array) $lists );
        }
        if ( ! empty( $tags ) && $tags !== 'any' ) {
            $contact->attachTags( (array) $tags );
        }
        foreach ( $custom_field_map as $row ) {
            if ( empty( $row['field'] ) ) continue;
                $contact->updateCustomField( $row['field'], $row['value'] ?? '' );
        }
        return ['port'=>'main','data'=>['success' => true, 'contact' => FluentcrmHelpers::resolve_sub_payload( $contact ), ], ];
    }

    protected static function action_get_contact_all(array $config): array
    {
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $subscriber = \FluentCrm\App\Models\Subscriber::all();
        $contacts = [];
        foreach ( $subscriber as $sub ) {
            $contacts[] = FluentcrmHelpers::resolve_sub_payload($sub);
        }
        return ['port'=>'main','data'=>['success' => true, 'contacts' => $contacts ], ];
    }

    protected static function action_get_contact_id(array $config): array
    {
        $contact_id = $config['contact_id'] ?? 0;
        if ( ! $contact_id ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id);
        if ( ! $contact ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact is not found',],];
        }
        return ['port'=>'main','data'=>['success' => true, 'contact' => FluentcrmHelpers::resolve_sub_payload( $contact ), ], ];   
    }

    protected static function action_get_contact_email(array $config): array
    {
        $email = $config['email'] ?? 0;
        if ( ! $email ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Email is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $contact = \FluentCrm\App\Models\Subscriber::where('email', $email)->first();
        if ( ! $contact ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact is not found',],];
        }
        return ['port'=>'main','data'=>['success' => true, 'contact' => FluentcrmHelpers::resolve_sub_payload( $contact ), ], ];     
    }
    protected static function action_get_contact_by_tags(array $config): array
    {
        $tag_ids = FluentcrmHelpers::normalize_ids( $config['tag_id'] ?? [] );
        if ( empty( $tag_ids ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag ID is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $subscribers = \FluentCrm\App\Models\Subscriber::whereHas('tags', function ($q) use ($tag_ids) {
            $q->whereIn('fc_tags.id', $tag_ids);
        })->get();
        if ($subscribers->isEmpty()) {
            return ['port' => 'main','data' => ['success' => false,'message' => 'No contacts found for given tags',],];
        }
        $contacts = [];
        foreach ($subscribers as $sub) {
            $contacts[] = FluentcrmHelpers::resolve_sub_payload($sub);
        }
        return ['port' => 'main','data' => ['success' => true,'count' => count($contacts),'contacts'=> $contacts,],];
             
    }

    protected static function action_get_contact_by_lists(array $config): array
    {
        $list_ids = FluentcrmHelpers::normalize_ids( $config['list_id'] ?? [] );
        if ( empty( $list_ids ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'List ID is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $subscribers = \FluentCrm\App\Models\Subscriber::whereHas('lists', function ($q) use ($list_ids) {
            $q->whereIn('fc_lists.id', $list_ids);
        })->get();
        if ($subscribers->isEmpty()) {
            return ['port' => 'main','data' => ['success' => false,'message' => 'No contacts found for given lists',],];
        }
        $contacts = [];
        foreach ($subscribers as $sub) {
            $contacts[] = FluentcrmHelpers::resolve_sub_payload($sub);
        }
        return ['port' => 'main','data' => ['success' => true,'count' => count($contacts),'contacts'=> $contacts,],];
        
    }
    protected static function action_get_contact_by_status(array $config): array
    {
        $status = $config['status'] ?? [];
        if ( empty( $status ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Status is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $subscribers = \FluentCrm\App\Models\Subscriber::where('status', sanitize_text_field( $status) )->get();
        if ($subscribers->isEmpty()) {
            return ['port' => 'main','data' => ['success' => false,'message' => 'No contacts found for given status',],];
        }
        $contacts = [];
        foreach ($subscribers as $sub) {
            $contacts[] = FluentcrmHelpers::resolve_sub_payload($sub);
        }
        return ['port' => 'main','data' => ['success' => true,'status' => $status, 'count' => count($contacts),'contacts'=> $contacts,],];
            
    }

}