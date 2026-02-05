<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Traits\ActionResponseTrait;

trait CompanyActionsTrait
{
    use ActionResponseTrait; // include success/error helpers

    protected static function action_created_company(array $config): array
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
    protected static function action_add_company_to_contact(array $config): array
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
        return ['port' => 'main','data' => ['success' => true,'list' => self::resolve_list_payload($list),],];

    }
    protected static function action_remove_company_from_contact(array $config): array
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
        return ['port' => 'main','data' => ['success' => true,'list' => self::resolve_list_payload($list),],];

    }
}