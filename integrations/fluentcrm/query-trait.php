<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QueryTrait {

	public static function tag_query_types( $query ) {
		$all_tag = [
			[
				'label' => 'Any Tag',
				'value' => 'any'
			],
		];
		if ( class_exists( 'FluentCrm\App\Models\Tag' ) ) {
			$tags = \FluentCrm\App\Models\Tag::all();
			foreach ( $tags as $tag ) {
				$all_tag[] = [
					'label' => $tag->title,
					'value' => $tag->id,
				];
			}
		}

		return $all_tag;
	}

	public static function list_query_types( $query ) {
		$all_list = [
			[
				'label' => 'Any List',
				'value' => 'any'
			],
		];
		if ( class_exists( 'FluentCrm\App\Models\Lists' ) ) {
			$lists = \FluentCrm\App\Models\Lists::all();
			foreach ( $lists as $list ) {
				$all_list[] = [
					'label' => $list->title,
					'value' => $list->id,
				];
			}
		}

		return $all_list;
	}

	public static function company_query_types( $query ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_companies';
		$all_company = [
			[
				'label' => 'Any Company',
				'value' => 'any'
			],
		];
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return $all_company;
		}
		if ( class_exists( 'FluentCrm\App\Models\Company' ) ) {
			$companies = \FluentCrm\App\Models\Company::all();
			foreach ( $companies as $company ) {
				$all_company[] = [
					'label' => $company->name,
					'value' => $company->id,
				];
			}
		}

		return $all_company;
	}
}
