<?php

namespace Zaplane\Integrations\Gemcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QueryTrait {

	/**
	 * Returns the latest 10 contacts by default.
	 * Search is passed through to the API so large contact bases stay fast.
	 */
	public static function query_contacts( $q = null ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Contact::class ) ) {
			return [];
		}

		$params = [
			'per_page' => 10,
			'page'     => 1,
		];

		if ( ! empty( $q['search'] ?? '' ) ) {
			$params['search'] = $q['search'];
		}

		$items  = \GemCrm\Database\Models\Contact::index( $params, null );
		$result = [];

		foreach ( (array) ( $items['records'] ?? [] ) as $item ) {
			$id    = $item['id'] ?? null;
			$first = $item['first_name'] ?? '';
			$last  = $item['last_name'] ?? '';
			$email = $item['email'] ?? '';

			if ( ! $id ) {
				continue;
			}

			$name     = trim( "{$first} {$last}" );
			$label    = $name !== '' ? "{$name} ({$email})" : $email;
			$result[] = [ 'value' => $id, 'label' => $label ];
		}

		return $result;
	}

	/**
	 * Returns the latest 10 tags by default; search narrows results.
	 */
	public static function query_tags( $q = null ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Tag::class ) ) {
			return [];
		}

		$params = [
			'per_page' => 10,
			'page'     => 1,
		];

		if ( ! empty( $q['search'] ?? '' ) ) {
			$params['search'] = $q['search'];
		}

		$items  = \GemCrm\Database\Models\Tag::index( $params, null );
		$result = [];

		foreach ( (array) ( $items['records'] ?? $items ) as $item ) {
			$id   = $item['id'] ?? null;
			$name = $item['title'] ?? '';

			if ( $id ) {
				$result[] = [ 'value' => $id, 'label' => $name ];
			}
		}

		return $result;
	}

	/**
	 * Returns the latest 10 lists by default; search narrows results.
	 */
	public static function query_lists( $q = null ): array {
		if ( ! class_exists( \GemCrm\Database\Models\ListModel::class ) ) {
			return [];
		}

		$params = [
			'per_page' => 10,
			'page'     => 1,
		];

		if ( ! empty( $q['search'] ?? '' ) ) {
			$params['search'] = $q['search'];
		}

		$items  = \GemCrm\Database\Models\ListModel::index( $params, null );
		$result = [];

		foreach ( (array) ( $items['records'] ?? $items ) as $item ) {
			$id   = $item['id'] ?? null;
			$name = $item['title'] ?? '';

			if ( $id ) {
				$result[] = [ 'value' => $id, 'label' => $name ];
			}
		}

		return $result;
	}

	/**
	 * Returns the latest 10 email sequences by default; search narrows results.
	 */
	public static function query_sequences( $q = null ): array {
		if ( ! class_exists( \GemCrmPro\Database\Models\EmailSequence::class ) ) {
			return [];
		}

		$params = [
			'per_page' => 10,
			'page'     => 1,
		];

		if ( ! empty( $q['search'] ?? '' ) ) {
			$params['search'] = $q['search'];
		}

		$items  = \GemCrmPro\Database\Models\EmailSequence::index( $params, null );
		$result = [];

		foreach ( (array) ( $items['records'] ?? $items ) as $item ) {
			$id   = $item['id'] ?? null;
			$name = $item['title'] ?? $item['name'] ?? '';

			if ( $id ) {
				$result[] = [ 'value' => $id, 'label' => $name ];
			}
		}

		return $result;
	}

	/**
	 * Returns the latest 10 campaigns by default; search narrows results.
	 */
	public static function query_campaigns( $q = null ): array {
		if ( ! class_exists( \GemCrm\Database\Models\Campaign::class ) ) {
			return [];
		}

		$params = [
			'per_page' => 10,
			'page'     => 1,
		];

		if ( ! empty( $q['search'] ?? '' ) ) {
			$params['search'] = $q['search'];
		}

		$items  = \GemCrm\Database\Models\Campaign::index( $params, null );
		$result = [];

		foreach ( (array) ( $items['records'] ?? $items ) as $item ) {
			$id   = $item['id'] ?? null;
			$name = $item['title'] ?? $item['name'] ?? '';

			if ( $id ) {
				$result[] = [ 'value' => $id, 'label' => $name ];
			}
		}

		return $result;
	}
}
