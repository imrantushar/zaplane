<?php
namespace Zaplane\Integrations\Buddyboss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QueryTrait {

	public static function query_forums( $query = [] ): array {
		if ( ! function_exists( 'bbp_get_forum_post_type' ) ) {
			return [];
		}

		$forums = get_posts( [
			'post_type'      => bbp_get_forum_post_type(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		] );

		return array_map( fn( $forum ) => [
			'value' => $forum->ID,
			'label' => $forum->post_title,
		], $forums );
	}

	public static function query_group( $query = [] ): array {
		if ( ! function_exists( 'groups_get_groups' ) ) {
			return [];
		}

		$result = groups_get_groups();
		$data   = [];

		if ( ! empty( $result['groups'] ) ) {
			foreach ( $result['groups'] as $group ) {
				$data[] = [
					'value' => $group->id,
					'label' => $group->name,
				];
			}
		}

		return $data;
	}

	public static function query_group_types( $query = [] ): array {
		if ( ! function_exists( 'bp_groups_get_group_types' ) ) {
			return [];
		}

		$types = bp_groups_get_group_types();
		$data  = [];

		foreach ( $types as $key => $type ) {
			$data[] = [
				'value' => $key,
				'label' => $type,
			];
		}

		return $data;
	}

	public static function query_member_types( $query = [] ): array {
		if ( ! function_exists( 'bp_get_member_types' ) ) {
			return [];
		}

		$types = bp_get_member_types( [] );
		$data  = [];

		foreach ( $types as $key => $type ) {
			$data[] = [
				'value' => $key,
				'label' => $type,
			];
		}

		return $data;
	}
}
