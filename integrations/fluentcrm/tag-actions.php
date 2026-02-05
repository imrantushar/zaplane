<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Traits\ActionResponseTrait;

trait TagActionsTrait
{
    use ActionResponseTrait; // include success/error helpers

    protected static function action_get_tag_all(array $config): array
    {
        if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Tag Not Found',],];
        }
        $tags = \FluentCrm\App\Models\Tag::all();
        if ($tags->isEmpty()) {
            return ['port' => 'main','data' => ['success' => true,'count' => 0,'tags' => [],],];
        }
        $tag_list = [];
        foreach ($tags as $tag) {
            $tag_list[] = FluentcrmHelpers::resolve_tag_payload($tag);
        }
        return ['port' => 'main','data' => ['success' => true,'count' => count($tag_list),'tags'=> $tag_list,],];
            
    }
    protected static function action_created_tag(array $config): array
    {
        $title       = $config['title'] ?? '';
        $slug        = $config['slug'] ?? '';
        $description = $config['description'] ?? '';
        if ( ! $title )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag Title is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Tag Not Found',],];
        }
        $tag = \FluentCrm\App\Models\Tag::where('title', $title)->first();
        if (! $tag) {
            $tag = new \FluentCrm\App\Models\Tag();
        }
        $tag->title       = $title;
        $tag->description = $description;
        $tag->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
        $tag->save();
        return ['port' => 'main','data' => ['success' => true,'tag' => FluentcrmHelpers::resolve_tag_payload($tag),],];

    }
    protected static function action_add_tag_to_contact(array $config): array
    {
        $contact_id = $config['contact_id'] ?? 0;
        $tags        = $config['tags'] ?? '';
        if ( ! $contact_id )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
        }
        if ( empty( $tags ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
        if ( ! $contact )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
        }
        $contact->attachTags((array) $tags );
        $contact = \FluentCrm\App\Models\Subscriber::with('tags')->find( $contact_id );
        $tag_payload = [];
        foreach ( $contact->tags as $tag ) {
            $tag_payload[] = array_merge( FluentcrmHelpers::resolve_tag_payload( $tag ), ['pivot' => FluentcrmHelpers::resolve_pivot_tag_payload( $tag )],);
        }
        return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => FluentcrmHelpers::resolve_sub_payload($contact),'tags' => $tag_payload,],];
    
    }
    protected static function action_remove_tag_from_contact(array $config): array
    {
        $contact_id = $config['contact_id'] ?? 0;
        $tags = array_map('intval', (array) ($config['tags'] ?? []));

        if ( ! $contact_id )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Contact ID is required',],];
        }
        if ( empty( $tags ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'Tag is required',],];
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'FluentCRM Subscriber Not Found',],];
        }
        $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
        if ( ! $contact )  {
            return ['port'=>'main','data'=>['success' => false, 'message' => 'contact not found',],];
        }
        $contact->detachTags( $tags );
        $contact = \FluentCrm\App\Models\Subscriber::with('tags')->find( $contact_id );
        $tag_payload = [];
        foreach ( $contact->tags as $tag ) {
            $tag_payload[] = array_merge( FluentcrmHelpers::resolve_tag_payload( $tag ), ['pivot' => FluentcrmHelpers::resolve_pivot_tag_payload( $tag )],);
        }
        return ['port' => 'main','data' => ['success' => true,'status' => $contact->status,'contact' => FluentcrmHelpers::resolve_sub_payload($contact),'tags' => $tag_payload,],];
    
    }
    
}