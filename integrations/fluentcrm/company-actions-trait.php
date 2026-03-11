<?php
namespace Zaplane\Integrations\Fluentcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CompanyActionsTrait {

    protected static function action_get_company_all( array $config, array $input ): array {
        if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
            return self::action_error( 'FluentCRM Company Not Found', $input );
        }
        $companies = \FluentCrm\App\Models\Company::all();
        if ( $companies->isEmpty() ) {
            return self::action_error( 'Company Not Found', $input );
        }
        $company_payload = [];
        foreach ( $companies as $company ) {
            $company_payload[] = self::resolve_company_payload( $company );
        }

        return self::action_success(array_merge($input, [
            'count' => count( $company_payload ),
            'companies' => $company_payload,
        ]));
    }

    protected static function action_get_company_id( array $config, array $input ): array {
        $company_id = $config['company_id'] ?? 0;
        if ( ! $company_id ) {
            return self::action_error( 'Company ID is required', $input );
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
            return self::action_error( 'FluentCRM Company Not Found', $input );
        }
        $company = \FluentCrm\App\Models\Company::find( $company_id );
        if ( ! $company ) {
            return self::action_error( 'Company Not Found', $input );
        }

        return self::action_success(array_merge($input, [
            'company' => self::resolve_company_payload( $company ),
        ]));
    }

    protected static function action_created_company( array $config, array $input ): array {
        if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
            return self::action_error( 'FluentCRM Company Model Not Found', $input );
        }
        $data = [
            'name'            => sanitize_text_field( $config['company_name'] ?? '' ),
            'description'     => sanitize_textarea_field( $config['description'] ?? '' ),
            'email'           => sanitize_email( $config['email'] ?? '' ),
            'phone'           => sanitize_text_field( $config['phone'] ?? '' ),
            'address_line_1'  => sanitize_text_field( $config['address_line_1'] ?? '' ),
            'address_line_2'  => sanitize_text_field( $config['address_line_2'] ?? '' ),
            'city'            => sanitize_text_field( $config['city'] ?? '' ),
            'state'           => sanitize_text_field( $config['state'] ?? '' ),
            'country'         => sanitize_text_field( $config['country'] ?? '' ),
            'postal_code'     => sanitize_text_field( $config['postal_code'] ?? '' ),
            'type'            => sanitize_text_field( $config['company_type'] ?? '' ),
            'owner_id'        => intval( $config['company_owner_id'] ?? 0 ),
            'employee_count'  => intval( $config['company_employee_count'] ?? 0 ),
            'industry'        => sanitize_text_field( $config['company_industry'] ?? '' ),
            'website'         => esc_url_raw( $config['company_website'] ?? '' ),
            'linkedin_url'    => esc_url_raw( $config['company_linkedin_url'] ?? '' ),
            'facebook_url'    => esc_url_raw( $config['company_facebook_url'] ?? '' ),
            'twitter_url'     => esc_url_raw( $config['company_twitter_url'] ?? '' ),
        ];
        if ( empty( $data['name'] ) ) {
            return self::action_error( 'Company Name is required', $input );
        }
        $companyModel = new \FluentCrm\App\Models\Company();
        $company = $companyModel->create( array_filter( $data ) );

        return self::action_success(array_merge($input, [
            'company' => self::resolve_company_payload( $company ),
        ]));
    }

    protected static function action_add_company_to_contact( array $config, array $input ): array {
        $contact_id = intval( $config['contact_id'] ?? 0 );
        $companies  = $config['company'] ?? [];

        if ( ! $contact_id ) {
            return self::action_error( 'Contact ID is required', $input );
        }
        if ( ! is_array( $companies ) ) {
            if ( is_string( $companies ) ) {
                $companies = array_map( 'intval', explode( ',', $companies ) );
            } else {
                $companies = [ (int) $companies ];
            }
        }
        $companies = array_filter( $companies );
        if ( empty( $companies ) ) {
            return self::action_error( 'Companies is required', $input );
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) || ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
            return self::action_error( 'FluentCRM Model Not Found', $input );
        }
        $contact = \FluentCrm\App\Models\Subscriber::find( $contact_id );
        if ( ! $contact ) {
            return self::action_error( 'Contact not found', $input );
        }
        $valid_company_ids = [];
        foreach ( $companies as $company_id ) {
            $company = \FluentCrm\App\Models\Company::find( intval( $company_id ) );
            if ( $company ) {
                $valid_company_ids[] = $company->id;
            }
        }
        if ( empty( $valid_company_ids ) ) {
            return self::action_error( 'Companies not valid', $input );
        }
        $contact->attachCompanies( $valid_company_ids );
        $contact = $contact->fresh( [ 'companies' ] );
        $company_payload = [];
        $valid_company_map = array_flip( $valid_company_ids );
        foreach ( $contact->companies as $company ) {
            if ( isset( $valid_company_map[ $company->id ] ) ) {
                $company_payload[] = self::resolve_company_payload( $company );
            }
        }
        if ( ! $contact->company_id ) {
            $contact->company_id = $valid_company_ids[0];
            $contact->save();
        }

        return self::action_success(array_merge($input, [
            'status' => $contact->status,
            'contact' => self::resolve_contact_payload( $contact ),
            'companies' => $company_payload,
        ]));
    }

    protected static function action_remove_company_from_contact( array $config, array $input ): array {
        $contact_id = $config['contact_id'] ?? 0;
        $companies  = array_map( 'intval', (array) ( $config['company'] ?? [] ) );
        if ( ! $contact_id ) {
            return self::action_error( 'Contact ID is required', $input );
        }
        if ( empty( $companies ) ) {
            return self::action_error( 'Company is required', $input );
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Subscriber' ) ) {
            return self::action_error( 'FluentCRM Subscriber Not Found', $input );
        }
        $contact = \FluentCrm\App\Models\Subscriber::with( 'companies' )->find( $contact_id );
        if ( ! $contact ) {
            return self::action_error( 'Contact not found', $input );
        }
        $contact->detachCompanies( $companies );
        $contact->load( 'companies' );
        if ( in_array( $contact->company_id, $companies, true ) ) {
            $first = $contact->companies->first();
            $contact->company_id = $first ? $first->id : null;
            $contact->save();
        }
        $company_payload = [];
        foreach ( $contact->companies as $company ) {
            $company_payload[] = array_merge( self::resolve_company_payload( $company ), [ 'pivot' => self::resolve_pivot_payload( $company ) ], );
        }

        return self::action_success(array_merge($input, [
            'status' => $contact->status,
            'contact' => self::resolve_contact_payload( $contact ),
            'companies' => $company_payload,
        ]));
    }

    protected static function action_delete_company( array $config, array $input ): array {
        $company_id = $config['company_id'] ?? null;
        if ( ! $company_id ) {
            return self::action_error( 'Company ID is required', $input );
        }
        if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
            return self::action_error( 'FluentCRM Class Not Found', $input );
        }
        $company = \FluentCrm\App\Models\Company::find( $company_id );
        if ( ! $company ) {
            return self::action_error( 'Company not found', $input );
        }
        $company->delete();

        return self::action_success(array_merge($input, [
            'message' => 'Company deleted successfully',
        ]));
    }
}