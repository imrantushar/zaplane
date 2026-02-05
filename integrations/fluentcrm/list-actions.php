<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Traits\ActionResponseTrait;

trait ListActionsTrait
{
    use ActionResponseTrait; // include success/error helpers

    protected static function action_get_list_all(array $config): array
    {
        if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Lists Not Found',],];
        }
        $lists = \FluentCrm\App\Models\Lists::all();
        if ($lists->isEmpty()) {
            return ['port' => 'main','data' => ['success' => true,'count' => 0,'lists' => [],],];
        }
        $list_payload = [];
        foreach ($lists as $list) {
            $list_payload[] = FluentcrmHelpers::resolve_list_payload($list);
        }
        return ['port' => 'main','data' => ['success' => true,'count' => count($list_payload),'lists'=> $list_payload,],];
            
    }
    protected static function action_created_list(array $config): array
    {
        $title       = $config['title'] ?? '';
        $slug        = $config['slug'] ?? '';
        $description = $config['description'] ?? '';
        if ( ! $title )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'List Title is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM List Not Found',],];
        }
        $list = \FluentCrm\App\Models\Lists::where('title', $title)->first();
        if (! $list) {
            $list = new \FluentCrm\App\Models\Lists();
        }
        $list->title       = $title;
        $list->description = $description;
        $list->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
        $list->save();
        return ['port' => 'main','data' => ['success' => true,'list' => FluentcrmHelpers::resolve_list_payload($list),],];
 
    }
    protected static function action_add_list_to_contact(array $config): array
    {
        $contact_id = $config['contact_id'] ?? 0;
        $lists      = $config['lists'] ?? [];
        if ( ! $contact_id )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
        }
        if ( empty( $lists ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Lists is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
        if ( ! $contact )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
        }
        $contact->attachLists((array) $lists );
        $contact = \FluentCrm\App\Models\Subscriber::with('lists')->find( $contact_id );
        $list_payload = [];
        foreach ( $contact->lists as $list ) {
            $list_payload[] = array_merge( FluentcrmHelpers::resolve_list_payload( $list ), ['pivot' => FluentcrmHelpers::resolve_pivot_list_payload( $list )],);
        }
        return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => FluentcrmHelpers::resolve_sub_payload($contact),'lists' => $list_payload,],];
            
    }
    protected static function action_remove_list_from_contact(array $config): array
    {
        $contact_id = $config['contact_id'] ?? 0;
        $lists = array_map('intval', (array) ($config['lists'] ?? []));

        if ( ! $contact_id )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
        }
        if ( empty( $lists ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Lists is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
        if ( ! $contact )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
        }
        $contact->detachLists( $lists );
        $contact = \FluentCrm\App\Models\Subscriber::with('lists')->find( $contact_id );
        $list_payload = [];
        foreach ( $contact->lists as $list ) {
            $list_payload[] = array_merge( FluentcrmHelpers::resolve_list_payload( $list ), ['pivot' => FluentcrmHelpers::resolve_pivot_list_payload( $list )],);
        }
        return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => FluentcrmHelpers::resolve_sub_payload($contact),'lists' => $list_payload,],];
              
    }
}