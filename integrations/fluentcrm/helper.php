<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Helper {

    public static function resolve_sub_payload( $sub ): array {
        return [
            'id'             => $sub->id,
            'user_id'        => $sub->user_id,
            'hash'           => $sub->hash,
            'contact_owner'  => $sub->contact_owner,
            'company_id'     => $sub->company_id,
            'prefix'         => $sub->prefix,
            'first_name'     => $sub->first_name,
            'last_name'      => $sub->last_name,
            'full_name'      => $sub->full_name,
            'email'          => $sub->email,
            'timezone'       => $sub->timezone,
            'address_line_1' => $sub->address_line_1,
            'address_line_2' => $sub->address_line_2,
            'postal_code'    => $sub->postal_code,
            'city'           => $sub->city,
            'state'          => $sub->state,
            'country'        => $sub->country,
            'ip'             => $sub->ip,
            'latitude'       => $sub->latitude,
            'longitude'      => $sub->longitude,
            'total_points'   => $sub->total_points,
            'life_time_value'=> $sub->life_time_value,
            'phone'          => $sub->phone,
            'status'         => $sub->status,
            'contact_type'   => $sub->contact_type,
            'source'         => $sub->source,
            'avatar'         => $sub->avatar,
            'date_of_birth'  => $sub->date_of_birth,
            'created_at'     => $sub->created_at,
            'last_activity'  => $sub->last_activity,
            'updated_at'     => $sub->updated_at,
            'photo'          => $sub->photo,
        ];
    }

    public static function resolve_companies_payload( $company ): array {
        return [
            'id'               => $company->id,
            'hash'             => $company->hash,
            'owner_id'         => $company->owner_id,
            'name'             => $company->name,
            'industry'         => $company->industry,
            'email'            => $company->email,
            'timezone'         => $company->timezone,
            'address_line_1'   => $company->address_line_1,
            'address_line_2'   => $company->address_line_2,
            'postal_code'      => $company->postal_code,
            'city'             => $company->city,
            'state'            => $company->state,
            'country'          => $company->country,
            'employees_number' => $company->employees_number,
            'description'      => $company->description,
            'phone'            => $company->phone,
            'type'             => $company->type,
            'logo'             => $company->logo,
            'website'          => $company->website,
            'linkedin_url'     => $company->linkedin_url,
            'facebook_url'     => $company->facebook_url,
            'twitter_url'      => $company->twitter_url,
            'date_of_start'    => $company->date_of_start,
            'meta'             => $company->meta ?? ['custom_values'=>[]],
            'created_at'       => $company->created_at,
            'updated_at'       => $company->updated_at,
        ];
    }

    public static function resolve_tag_payload( $tag ): array {
        return [
            'id'          => $tag->id,
            'title'       => $tag->title,
            'slug'        => $tag->slug,
            'description' => $tag->description,
            'created_at'  => $tag->created_at,
            'updated_at'  => $tag->updated_at,
        ];
    }

    public static function resolve_list_payload( $list ): array {
        return [
            'id'          => $list->id,
            'title'       => $list->title,
            'slug'        => $list->slug,
            'description' => $list->description,
            'created_at'  => $list->created_at,
            'updated_at'  => $list->updated_at,
        ];
    }

    public static function resolve_pivot_tag_payload( $tag ): array {
        return [
            'subscriber_id' => $tag->pivot->subscriber_id,
            'object_id'     => $tag->pivot->object_id,
            'object_type'   => $tag->pivot->object_type,
            'created_at'    => $tag->pivot->created_at,
            'updated_at'    => $tag->pivot->updated_at,
        ];
    }

    public static function resolve_pivot_list_payload( $list ): array {
        return [
            'subscriber_id' => $list->pivot->subscriber_id,
            'object_id'     => $list->pivot->object_id,
            'object_type'   => $list->pivot->object_type,
            'created_at'    => $list->pivot->created_at,
            'updated_at'    => $list->pivot->updated_at,
        ];
    }

    public static function normalize_ids( $ids ): array {
        if ( empty( $ids ) ) return [];
        if ( is_string( $ids ) ) {
            $ids = strpos( $ids, ',') !== false ? explode( ',', $ids ) : [ $ids ];
        }
        return array_map( 'intval', (array) $ids );
    }
}