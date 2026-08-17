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
			$result[] = [
				'value' => $id,
				'label' => $label
			];
		}

		return $result;
	}

	/**
	 * Returns Zaplane's own email templates for the "Use saved template"
	 * dropdown. Newest first; search narrows by title. These are designed with
	 * the drag-and-drop builder on the Zaplane Email Templates page.
	 */
	public static function query_email_templates( $q = null ): array {
		if ( ! class_exists( \Zaplane\Models\EmailTemplate::class ) ) {
			return [];
		}

		$query = \Zaplane\Models\EmailTemplate::orderBy( 'updated_at', 'desc' );

		if ( ! empty( $q['search'] ?? '' ) ) {
			$query = $query->where( 'title', 'like', '%' . $q['search'] . '%' );
		}

		$result = [];

		foreach ( $query->forPage( 1, 50 )->get() as $template ) {
			$title    = (string) $template->title;
			$result[] = [
				'value' => (int) $template->id,
				'label' => '' !== $title ? $title : sprintf( '#%d', $template->id ),
			];
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
				$result[] = [
					'value' => $id,
					'label' => $name
				];
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
				$result[] = [
					'value' => $id,
					'label' => $name
				];
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
				$result[] = [
					'value' => $id,
					'label' => $name
				];
			}
		}

		return $result;
	}

	/**
	 * Returns the latest 20 sequence emails (steps) across all sequences,
	 * each labeled with its parent sequence's title — for the "Activate
	 * Sequence Email" action's step picker. Search narrows by step title.
	 */
	public static function query_sequence_campaigns( $q = null ): array {
		if ( ! class_exists( \GemCrmPro\Database\Models\EmailSequenceCampaign::class ) ) {
			return [];
		}

		$search = (string) ( $q['search'] ?? '' );
		$items  = \GemCrmPro\Database\Models\EmailSequenceCampaign::search_with_sequence_title( $search, 20 );
		$result = [];

		foreach ( $items as $item ) {
			$step_title     = '' !== $item['title'] ? $item['title'] : sprintf( '#%d', $item['id'] );
			$sequence_title = '' !== $item['sequence_title'] ? $item['sequence_title'] : 'Sequence';
			$status_suffix  = 'pending' === $item['status'] ? ' (active)' : ' (draft)';

			$result[] = [
				'value' => $item['id'],
				'label' => "{$sequence_title} → {$step_title}{$status_suffix}",
			];
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
				$result[] = [
					'value' => $id,
					'label' => $name
				];
			}
		}

		return $result;
	}
}
