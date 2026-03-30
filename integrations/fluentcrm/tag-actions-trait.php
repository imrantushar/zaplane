<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait TagActionsTrait {

	protected static function action_get_tag_all( array $config, array $input ): array {
		if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
			return self::action_error( 'FluentCRM Tag Not Found', $input );
		}
		$tags = \FluentCrm\App\Models\Tag::all();
		if ( $tags->isEmpty() ) {
			return self::action_error( 'Tag Not Found', $input );
		}
		$tag_list = [];
		foreach ( $tags as $tag ) {
			$tag_list[] = self::resolve_tag_payload( $tag );
		}

		return self::action_success(array_merge($input, [
			'count' => count( $tag_list ),
			'tags' => $tag_list,
		]));
	}

	protected static function action_created_tag( array $config, array $input ): array {
		$title       = $config['title'] ?? '';
		$slug        = $config['slug'] ?? '';
		$description = $config['description'] ?? '';
		if ( ! $title ) {
			return self::action_error( 'Tag Title is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
			return self::action_error( 'FluentCRM Tag Not Found', $input );
		}
		$tag = \FluentCrm\App\Models\Tag::where( 'title', $title )->first();
		if ( ! $tag ) {
			$tag = new \FluentCrm\App\Models\Tag();
		}
		$tag->title       = $title;
		$tag->description = $description;
		$tag->slug        = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
		$tag->save();

		return self::action_success(array_merge($input, [
			'tag' => self::resolve_tag_payload( $tag ),
		]));
	}

	protected static function action_add_tag_to_contact( array $config, array $input ): array {
		$contact_id = $config['contact_id'] ?? 0;
		$tags       = $config['tags'] ?? '';
		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( empty( $tags ) ) {
			return self::action_error( 'Tag is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
		if ( ! $contact ) {
			return self::action_error( 'contact not found', $input );
		}
		$contact->attachTags( (array) $tags );
		$contact = \FluentCrm\App\Models\Subscriber::with( 'tags' )->find( $contact_id );
		$tag_payload = [];
		foreach ( $contact->tags as $tag ) {
			$tag_payload[] = array_merge( self::resolve_tag_payload( $tag ), [ 'pivot' => self::resolve_pivot_payload( $tag ) ], );
		}

		return self::action_success(array_merge($input, [
			'status' => $contact->status,
			'contact' => self::resolve_contact_payload( $contact ),
			'tags' => $tag_payload,
		]));
	}

	protected static function action_remove_tag_from_contact( array $config, array $input ): array {
		$contact_id = $config['contact_id'] ?? 0;
		$tags       = array_map( 'intval', (array) ( $config['tags'] ?? '' ) );

		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( empty( $tags ) ) {
			return self::action_error( 'Tag is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
		if ( ! $contact ) {
			return self::action_error( 'Contact not found', $input );
		}
		$contact->detachTags( $tags );
		$contact = \FluentCrm\App\Models\Subscriber::with( 'tags' )->find( $contact_id );
		$tag_payload = [];
		foreach ( $contact->tags as $tag ) {
			$tag_payload[] = array_merge( self::resolve_tag_payload( $tag ), [ 'pivot' => self::resolve_pivot_payload( $tag ) ], );
		}

		return self::action_success(array_merge($input, [
			'status' => $contact->status,
			'contact' => self::resolve_contact_payload( $contact ),
			'tags' => $tag_payload,
		]));
	}

	protected static function action_delete_tag( array $config, array $input ): array {
		$tag_id = $config['tag_id'] ?? 0;
		if ( ! $tag_id ) {
			return self::action_error( 'Tag ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Tag' ) ) {
			return self::action_error( 'FluentCRM Class Not Found', $input );
		}
		$tag = \FluentCrm\App\Models\Tag::find( $tag_id );
		if ( ! $tag ) {
			return self::action_error( 'Tag not found', $input );
		}
		$tag->delete();

		return self::action_success(array_merge($input, [
			'message' => 'Tag deleted successfully',
		]));
	}
}
