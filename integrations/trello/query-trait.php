<?php
namespace Zaplane\Integrations\Trello;

trait QueryTrait {

    public static function query_board( array $query ): array {
		$options = [];
		$creds   = self::extract_credentials( $query );
		$api_key = $creds['api_key'] ?? '';
		$token   = $creds['token'] ?? '';
		if ( empty( $api_key ) || empty( $token ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/members/me/boards?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'filter' => 'open',
					'fields' => 'id,name',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$boards = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $boards as $board ) {
			if ( empty( $board['id'] ) ) {
				continue;
			}
			$options[] = [
				'label' => $board['name'] ?? 'Untitled Board',
				'value' => $board['id'],
			];
		}
		return $options;
	}

    public static function query_list( array $query ): array {
		$options  = [];
		$creds    = self::extract_credentials( $query );
		$api_key  = $creds['api_key'] ?? '';
		$token    = $creds['token'] ?? '';
		$board_id = $query['where']['board_id'] ?? $query['board_id'] ?? '';
		if ( empty( $api_key ) || empty( $token ) || empty( $board_id ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/boards/' . rawurlencode( $board_id ) . '/lists?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'filter' => 'open',
					'fields' => 'id,name',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$lists = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $lists as $list ) {
			if ( empty( $list['id'] ) ) {
				continue;
			}
			$options[] = [
				'label' => $list['name'] ?? 'Untitled List',
				'value' => $list['id'],
			];
		}
		return $options;
	}

    public static function query_label( array $query ): array {
		$options  = [];
		$creds    = self::extract_credentials( $query );
		$api_key  = $creds['api_key'] ?? '';
		$token    = $creds['token'] ?? '';
		$board_id = $query['where']['board_id'] ?? $query['board_id'] ?? '';
		if ( empty( $api_key ) || empty( $token ) || empty( $board_id ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/boards/' . rawurlencode( $board_id ) . '/labels?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'fields' => 'id,name,color',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$labels = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $labels as $label ) {
			if ( empty( $label['id'] ) ) {
				continue;
			}
			$options[] = [
				'label' => ! empty( $label['name'] ) ? $label['name'] : ucfirst( $label['color'] ?? 'Label' ),
				'value' => $label['id'],
			];
		}
		return $options;
	}

    public static function query_card( array $query ): array {
		$options  = [];
		$creds    = self::extract_credentials( $query );
		$api_key  = $creds['api_key'] ?? '';
		$token    = $creds['token'] ?? '';
		$board_id = $query['where']['board_id'] ?? $query['board_id'] ?? '';
		if ( empty( $api_key ) || empty( $token ) || empty( $board_id ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/boards/' . rawurlencode( $board_id ) . '/cards?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'filter' => 'open',
					'fields' => 'id,name',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$cards = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $cards as $card ) {
			if ( empty( $card['id'] ) ) {
				continue;
			}
			$options[] = [
				'label' => $card['name'] ?? 'Untitled Card',
				'value' => $card['id'],
			];
		}
		return $options;
	}

    public static function query_checklist( array $query ): array {
		$options = [];
		$creds   = self::extract_credentials( $query );
		$api_key = $creds['api_key'] ?? '';
		$token   = $creds['token'] ?? '';
		$card_id = $query['where']['card_id'] ?? $query['card_id'] ?? '';
		if ( empty( $api_key ) || empty( $token ) || empty( $card_id ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/cards/' . rawurlencode( $card_id ) . '/checklists?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'fields' => 'id,name',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$checklists = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $checklists as $cl ) {
			if ( empty( $cl['id'] ) ) {
				continue;
			}
			$options[] = [
				'label' => $cl['name'] ?? 'Untitled Checklist',
				'value' => $cl['id'],
			];
		}
		return $options;
	}

    public static function query_checklist_item( array $query ): array {
		$options      = [];
		$creds        = self::extract_credentials( $query );
		$api_key      = $creds['api_key'] ?? '';
		$token        = $creds['token'] ?? '';
		$checklist_id = $query['where']['checklist_id'] ?? $query['checklist_id'] ?? '';
		if ( empty( $api_key ) || empty( $token ) || empty( $checklist_id ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/checklists/' . rawurlencode( $checklist_id ) . '/checkItems?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'fields' => 'id,name,state',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$items = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $items as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}
			$state     = isset( $item['state'] ) && 'complete' === $item['state'] ? ' ✓' : '';
			$options[] = [
				'label' => ( $item['name'] ?? 'Untitled Item' ) . $state,
				'value' => $item['id'],
			];
		}
		return $options;
	}

    public static function query_attachment( array $query ): array {
		$options = [];
		$creds   = self::extract_credentials( $query );
		$api_key = $creds['api_key'] ?? '';
		$token   = $creds['token'] ?? '';
		$card_id = $query['where']['card_id'] ?? $query['card_id'] ?? '';
		if ( empty( $api_key ) || empty( $token ) || empty( $card_id ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/cards/' . rawurlencode( $card_id ) . '/attachments?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'fields' => 'id,name,url',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$attachments = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $attachments as $att ) {
			if ( empty( $att['id'] ) ) {
				continue;
			}
			$label = ! empty( $att['name'] ) ? $att['name'] : wp_parse_url( $att['url'] ?? '', PHP_URL_HOST );
			$options[] = [
				'label' => ! empty( $label ) ? $label : 'Attachment',
				'value' => $att['id'],
			];
		}
		return $options;
	}

    public static function query_member( array $query ): array {
		$options  = [];
		$creds    = self::extract_credentials( $query );
		$api_key  = $creds['api_key'] ?? '';
		$token    = $creds['token'] ?? '';
		$board_id = $query['where']['board_id'] ?? $query['board_id'] ?? '';
		if ( empty( $api_key ) || empty( $token ) || empty( $board_id ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/boards/' . rawurlencode( $board_id ) . '/members?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'fields' => 'id,fullName,username',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$members = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $members as $member ) {
			if ( empty( $member['id'] ) ) {
				continue;
			}
			$label = ! empty( $member['fullName'] )
				? $member['fullName'] . ' (@' . ( $member['username'] ?? '' ) . ')'
				: ( $member['username'] ?? $member['id'] );
			$options[] = [
				'label' => $label,
				'value' => $member['id'],
			];
		}
		return $options;
	}

    public static function query_org( array $query ): array {
		$options = [];
		$creds   = self::extract_credentials( $query );
		$api_key = $creds['api_key'] ?? '';
		$token   = $creds['token'] ?? '';
		if ( empty( $api_key ) || empty( $token ) ) {
			return $options;
		}
		$response = wp_remote_get(
			self::API_BASE_URL . '/members/me/organizations?' . http_build_query(
				[
					'key'    => $api_key,
					'token'  => $token,
					'fields' => 'id,displayName,name',
				]
			),
			[ 'timeout' => 15 ]
		);
		if ( is_wp_error( $response ) ) {
			return $options;
		}
		$orgs = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		foreach ( $orgs as $org ) {
			if ( empty( $org['id'] ) ) {
				continue;
			}
			$label = ! empty( $org['displayName'] ) ? $org['displayName'] : ( $org['name'] ?? $org['id'] );
			$options[] = [
				'label' => $label,
				'value' => $org['id'],
			];
		}
		return $options;
	}
}
