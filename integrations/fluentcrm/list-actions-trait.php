<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ListActionsTrait {

	protected static function action_get_list_all( array $config, array $input ): array {
		if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
			return self::action_error( 'FluentCRM Lists Not Found', $input );
		}
		$lists = \FluentCrm\App\Models\Lists::all();
		if ( $lists->isEmpty() ) {
			return self::action_error( 'List Not Found', $input );
		}
		$list_payload = [];
		foreach ( $lists as $list ) {
			$list_payload[] = self::resolve_list_payload( $list );
		}

		return self::action_success(array_merge($input, [
			'count' => count( $list_payload ),
			'lists' => $list_payload,
		]));
	}

	protected static function action_created_list( array $config, array $input ): array {
		$title       = $config['title'] ?? '';
		$slug        = $config['slug'] ?? '';
		$description = $config['description'] ?? '';
		if ( ! $title ) {
			return self::action_error( 'List Title is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
			return self::action_error( 'FluentCRM List Not Found', $input );
		}
		$list = \FluentCrm\App\Models\Lists::where( 'title', $title )->first();
		if ( ! $list ) {
			$list = new \FluentCrm\App\Models\Lists();
		}
		$list->title       = $title;
		$list->description = $description;
		$list->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
		$list->save();

		return self::action_success(array_merge($input, [
			'list' => self::resolve_list_payload( $list ),
		]));
	}

	protected static function action_add_list_to_contact( array $config, array $input ): array {
		$contact_id = $config['contact_id'] ?? 0;
		$lists      = $config['lists'] ?? '';
		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( empty( $lists ) ) {
			return self::action_error( 'Lists is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
		if ( ! $contact ) {
			return self::action_error( 'contact not found', $input );
		}
		$contact->attachLists( (array) $lists );
		$contact = \FluentCrm\App\Models\Subscriber::with( 'lists' )->find( $contact_id );
		$list_payload = [];
		foreach ( $contact->lists as $list ) {
			$list_payload[] = array_merge( self::resolve_list_payload( $list ), [ 'pivot' => self::resolve_pivot_payload( $list ) ], );
		}

		return self::action_success(array_merge($input, [
			'status' => $contact->status,
			'contact' => self::resolve_contact_payload( $contact ),
			'lists' => $list_payload,
		]));
	}

	protected static function action_remove_list_from_contact( array $config, array $input ): array {
		$contact_id = $config['contact_id'] ?? 0;
		$lists      = array_map( 'intval', (array) ( $config['lists'] ?? '' ) );

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( empty( $lists ) ) {
			return self::action_error( 'Lists is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
		if ( ! $contact ) {
			return self::action_error( 'contact not found', $input );
		}
		$contact->detachLists( $lists );
		$contact = \FluentCrm\App\Models\Subscriber::with( 'lists' )->find( $contact_id );
		$list_payload = [];
		foreach ( $contact->lists as $list ) {
			$list_payload[] = array_merge( self::resolve_list_payload( $list ), [ 'pivot' => self::resolve_pivot_payload( $list ) ], );
		}

		return self::action_success(array_merge($input, [
			'status' => $contact->status,
			'contact' => self::resolve_contact_payload( $contact ),
			'lists' => $list_payload,
		]));
	}

	protected static function action_delete_list( array $config, array $input ): array {
		$list_id = $config['list_id'] ?? 0;
		if ( ! $list_id ) {
			return self::action_error( 'List ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Lists' ) ) {
			return self::action_error( 'FluentCRM Class Not Found', $input );
		}
		$list = \FluentCrm\App\Models\Lists::find( $list_id );
		if ( ! $list ) {
			return self::action_error( 'List not found', $input );
		}
		$list->delete();

		return self::action_success(array_merge($input, [
			'message' => 'List deleted successfully',
		]));
	}
}
