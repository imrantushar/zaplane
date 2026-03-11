<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CampaignActionsTrait {

	protected static function action_get_campaign_all( array $config, array $input ): array {
		if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
			return self::action_error( 'FluentCRM Campaign Not Found', $input );
		}
		$campaigns = \FluentCrm\App\Models\Campaign::all();
		if ( $campaigns->isEmpty() ) {
			return self::action_error( 'Campaign Not Found', $input );
		}
		$campaign_payload = [];
		foreach ( $campaigns as $campaign ) {
			$campaign_payload[] = self::resolve_campaign_payload( $campaign );
		}

		return self::action_success(array_merge($input, [
			'count' => count( $campaign_payload ),
			'campaigns' => $campaign_payload,
		]));
	}

	protected static function action_get_campaign_single( array $config, array $input ): array {
		$campaign_id = $config['campaign_id'] ?? 0;
		if ( ! $campaign_id ) {
			return self::action_error( 'Campaign ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
			return self::action_error( 'FluentCRM Campaign Not Found', $input );
		}
		$campaign = \FluentCrm\App\Models\Campaign::find( $campaign_id );
		if ( ! $campaign ) {
			return self::action_error( 'Campaign Not Found', $input );
		}

		return self::action_success(array_merge($input, [
			'campaign' => self::resolve_campaign_payload( $campaign ),
		]));
	}

	protected static function action_create_campaign( array $config, array $input ): array {
		$title = $config['title'] ?? '';
		if ( ! $title ) {
			return self::action_error( 'Campaign Title is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
			return self::action_error( 'FluentCRM Campaign Not Found', $input );
		}
		$created_by = get_current_user_id();
		if ( ! $created_by ) {
			$created_by = 1;
		}
		$campaign             = new \FluentCrm\App\Models\Campaign();
		$campaign->type       = 'campaign';
		$campaign->title      = $title;
		$campaign->slug       = sanitize_title( $title ) . '-' . time();
		$campaign->status     = 'draft';
		$campaign->created_by = $created_by;
		$campaign->email_body = '';
		$campaign->settings   = [
			'subscribers' => [
				[
					'list' => 'all',
					'tag' => 'all'
				]
			],
			'excludedSubscribers' => [],
		];
		$campaign->save();

		return self::action_success(array_merge($input, [
			'campaign' => self::resolve_campaign_payload( $campaign ),
		]));
	}

	protected static function action_delete_campaign( array $config, array $input ): array {
		$campaign_id = intval( $config['campaign_id'] ?? 0 );
		if ( ! $campaign_id ) {
			return self::action_error( 'Campaign ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Campaign' ) ) {
			return self::action_error( 'FluentCRM class Not Found', $input );
		}
		$campaign = \FluentCrm\App\Models\Campaign::find( $campaign_id );
		if ( ! $campaign ) {
			return self::action_error( 'Campaign Not Found', $input );
		}
		$campaign->delete();

		return self::action_success(array_merge($input, [
			'message' => 'Campaign deleted successfully',
		]));
	}

	protected static function action_add_event_tracking( array $config, array $input ): array {
		$email       = sanitize_email( $config['email'] ?? '' );
		$event_key   = sanitize_text_field( $config['event_key'] ?? '' );
		$event_title = sanitize_text_field( $config['title'] ?? '' );
		$event_value = $config['value'] ?? null;
		$provider    = sanitize_text_field( $config['provider'] ?? 'custom' );
		if ( ! $email ) {
			return self::action_error( 'Contact Email is required', $input );
		}
		if ( ! $event_key ) {
			return self::action_error( 'Event Key is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::where( 'email', $email )->first();
		if ( ! $contact ) {
			return self::action_error( 'Contact not found with this email', $input );
		}
		$event = FluentCrmApi( 'event_tracker' )->track([
			'email'     => $email,
			'event_key' => $event_key,
			'title'     => $event_title,
			'value'     => $event_value,
			'provider'  => $provider ? $provider : 'custom',
		], true );

		return self::action_success(array_merge($input, [
			'contact' => self::resolve_contact_payload( $contact ),
			'event' => $event,
		]));
	}

	protected static function action_add_note( array $config, array $input ): array {
		$contact_id  = $config['contact_id'] ?? 0;
		$note_title  = sanitize_text_field( $config['title'] ?? '' );
		$note_type   = sanitize_text_field( $config['note_type'] ?? '' );
		$description = sanitize_text_field( $config['description'] ?? '' );
		if ( ! $contact_id ) {
			return self::action_error( 'Contact Id is required', $input );
		}
		if ( ! $note_title ) {
			return self::action_error( 'Note Title is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
		if ( ! $contact ) {
			return self::action_error( 'Contact not found', $input );
		}
		$note = new \FluentCrm\App\Models\SubscriberNote();
		$note->subscriber_id = $contact_id;
		$note->title         = $note_title;
		$note->description   = $description;
		$note->type          = $note_type;
		$note->created_by    = $contact->user_id ?? 0;
		$note->save();

		return self::action_success(array_merge($input, [
			'contact' => self::resolve_contact_payload( $contact ),
			'note' => [
				'id'          => $note->id,
				'title'       => $note->title,
				'type'        => $note->type,
				'description' => $note->description,
				'created_at'  => $note->created_at,
				'updated_at'  => $note->updated_at,
			],
		]));
	}
}
