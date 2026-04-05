<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ContactActionsTrait {

	protected static function action_created_contact( array $config, array $input ): array {
		$email  = sanitize_email( $config['email'] ?? '' );
		$status = sanitize_text_field( $config['fluent_status'] ?? '' );
		if ( ! is_email( $email ) ) {
			return self::action_error( 'Valid Email is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Not Installed', $input );
		}
		$data = [
			'email'           => $email,
			'status'          => $status,
			'prefix'          => sanitize_text_field( $config['prefix'] ?? '' ),
			'first_name'      => sanitize_text_field( $config['first_name'] ?? '' ),
			'last_name'       => sanitize_text_field( $config['last_name'] ?? '' ),
			'phone'           => sanitize_text_field( $config['phone'] ?? '' ),
			'date_of_birth'   => sanitize_text_field( $config['date_of_birth'] ?? '' ),
			'address_line_1'  => sanitize_text_field( $config['address_line_1'] ?? '' ),
			'address_line_2'  => sanitize_text_field( $config['address_line_2'] ?? '' ),
			'city'            => sanitize_text_field( $config['city'] ?? '' ),
			'state'           => sanitize_text_field( $config['state'] ?? '' ),
			'country'         => sanitize_text_field( $config['country'] ?? '' ),
			'postal_code'     => sanitize_text_field( $config['postal_code'] ?? '' ),
		];
		$subscriberModel = new \FluentCrm\App\Models\Subscriber();
		$contact = $subscriberModel->where( 'email', $email )->first();
		if ( $contact ) {
			$contact->fill( array_filter( $data ) );
			$contact->save();
		} else {
			$contact = $subscriberModel->create( array_filter( $data ) );
		}
		if ( ! $contact ) {
			return self::action_error( 'Contact Create/Update Failed', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::find( $contact->id );
		$custom_fields = [
			'date_of_birth',
			'address_line_1',
			'address_line_2',
			'city',
			'state',
			'country',
			'postal_code'
		];
		foreach ( $custom_fields as $field ) {
			if ( ! empty( $config[ $field ] ) ) {
				$contact->updateMeta( $field, sanitize_text_field( $config[ $field ] ), false );
			}
		}
		if ( ! empty( $config['lists'] ) && 'any' !== $config['lists'] ) {
			$contact->attachLists( (array) $config['lists'] );
		}
		if ( ! empty( $config['tags'] ) && 'any' !== $config['tags'] ) {
			$contact->attachTags( (array) $config['tags'] );
		}
		if ( ! empty( $config['company_id'] ) ) {
			$company_id = (int) $config['company_id'];
			$contact->attachCompanies( [ $company_id ] );
			$contact->company_id = $company_id;
			$contact->save();
		}

		return self::action_success(array_merge($input, [
			'contact' => self::resolve_contact_payload( $contact ),
		]));
	}

	protected static function action_get_contact_all( array $config, array $input ): array {
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$subscriber = \FluentCrm\App\Models\Subscriber::all();
		$contacts   = [];
		foreach ( $subscriber as $sub ) {
			$contacts[] = self::resolve_contact_payload( $sub );
		}

		return self::action_success(array_merge($input, [
			'contacts' => $contacts,
		]));
	}

	protected static function action_get_contact_id( array $config, array $input ): array {
		$contact_id = $config['contact_id'] ?? 0;
		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
		if ( ! $contact ) {
			return self::action_error( 'Contact is not found', $input );
		}

		return self::action_success(array_merge($input, [
			'contact' => self::resolve_contact_payload( $contact ),
		]));
	}

	protected static function action_get_contact_email( array $config, array $input ): array {
		$email = $config['email'] ?? 0;
		if ( ! $email ) {
			return self::action_error( 'Email is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$contact = \FluentCrm\App\Models\Subscriber::where( 'email', $email )->first();
		if ( ! $contact ) {
			return self::action_error( 'Contact is not found', $input );
		}

		return self::action_success(array_merge($input, [
			'contact' => self::resolve_contact_payload( $contact ),
		]));
	}

	protected static function action_get_contact_by_tags( array $config, array $input ): array {
		$tag_ids = self::normalize_ids( $config['tag_id'] ?? [] );
		if ( empty( $tag_ids ) ) {
			return self::action_error( 'Tag ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$subscribers = \FluentCrm\App\Models\Subscriber::whereHas('tags', function ( $q ) use ( $tag_ids ) {
			$q->whereIn( 'fc_tags.id', $tag_ids );
		})->get();
		if ( $subscribers->isEmpty() ) {
			return self::action_error( 'No contacts found for given tags', $input );
		}
		$contacts = [];
		foreach ( $subscribers as $sub ) {
			$contacts[] = self::resolve_contact_payload( $sub );
		}

		return self::action_success(array_merge($input, [
			'count' => count( $contacts ),
			'contacts' => $contacts,
		]));
	}

	protected static function action_get_contact_by_lists( array $config, array $input ): array {
		$list_ids = self::normalize_ids( $config['list_id'] ?? [] );
		if ( empty( $list_ids ) ) {
			return self::action_error( 'List ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		$subscribers = \FluentCrm\App\Models\Subscriber::whereHas('lists', function ( $q ) use ( $list_ids ) {
			$q->whereIn( 'fc_lists.id', $list_ids );
		})->get();
		if ( $subscribers->isEmpty() ) {
			return self::action_error( 'No contacts found for given lists', $input );
		}
		$contacts = [];
		foreach ( $subscribers as $sub ) {
			$contacts[] = self::resolve_contact_payload( $sub );
		}

		return self::action_success(array_merge($input, [
			'count' => count( $contacts ),
			'contacts' => $contacts,
		]));
	}

	protected static function action_get_contact_by_status( array $config, array $input ): array {
		$status = sanitize_text_field( $config['fluent_status'] ?? '' );
		if ( empty( $status ) ) {
			return self::action_error( 'Status is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Subscriber Not Found', $input );
		}
		if ( 'any' === $status ) {
			$subscribers = \FluentCrm\App\Models\Subscriber::all();
		} else {
			$subscribers = \FluentCrm\App\Models\Subscriber::where( 'status', $status )->get();
		}
		if ( $subscribers->isEmpty() ) {
			return self::action_error( 'No contacts found for given status', $input );
		}
		$contacts = [];
		foreach ( $subscribers as $sub ) {
			$contacts[] = self::resolve_contact_payload( $sub );
		}

		return self::action_success(array_merge($input, [
			'status' => $status,
			'count' => count( $contacts ),
			'contacts' => $contacts,
		]));
	}

	protected static function action_delete_contact( array $config, array $input ): array {
		$contact_id = $config['contact_id'] ?? 0;
		if ( ! $contact_id ) {
			return self::action_error( 'Contact ID is required', $input );
		}
		if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
			return self::action_error( 'FluentCRM Class Not Found', $input );
		}
		$subscriber = \FluentCrm\App\Models\Subscriber::find( $contact_id );
		if ( ! $subscriber ) {
			return self::action_error( 'Contact not found', $input );
		}
		$subscriber->delete();

		return self::action_success(array_merge($input, [
			'message' => 'Contact deleted successfully',
		]));
	}
}
