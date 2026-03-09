<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use WPCF7_ContactForm;
use WPCF7_Submission;

class ContactForm extends IntegrationBase {


	public static function get_slug(): string {
		return 'contact-form-7';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'wpcf7_before_send_mail'
			],
			'form_created' => [
				'label' => 'Form Created',
				'hook'  => 'wpcf7_after_create'
			],
			'form_updated' => [
				'label' => 'Form Updated',
				'hook'  => 'wpcf7_after_update'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {

		if ( $trigger === 'form_submitted' ) {
			return [
				[
					'key'      => 'form_id',
					'label'    => 'Forms',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'contact-form-7',
						'query'       => 'form_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	public static function resolve_form_submit_payload( $contact_form, $abort = null, $submission_obj = null ) {
		if ( ! class_exists( 'WPCF7_Submission' ) || ! $contact_form ) {
			return false;
		}

		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			return false;
		}

		$form_id   = $contact_form->id();
		$form_data = $submission->get_posted_data() ?? [];
		$files     = $submission->uploaded_files() ?? [];
		$form_data = array_merge(
			$form_data,
			self::resolve_file_root_payload( $files ),
		);

		$post_id = $submission->get_meta( 'container_post_id' );

		if ( $post_id !== 0 ) {
			$form_data['post_id'] = $post_id;
		}

		return [
			'form_id'   => $form_id,
			'form_data' => $form_data,
		];
	}

	public static function resolve_file_root_payload( $files ) {
		$all_files = [];

		foreach ( $files as $key => $file ) {
			$all_files[ $key ] = is_array( $file ) ?
			self::resolve_file_root_payload( $file ) :
			self::resolve_file_url_payload( $file );
		}

		return $all_files;
	}

	public static function resolve_file_url_payload( $file ) {
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_path  = $upload_dir['basedir'];

		if ( is_array( $file ) ) {
			$url = [];
			foreach ( $file as $key => $file_url ) {
				$url[ $key ] = str_replace( $base_path, $base_url, $file_url );
			}
		} else {
			$url = str_replace( $base_path, $base_url, $file );
		}

		return $url;
	}

	public static function resolve_form_admin_payload( $contact_form ) {
		if ( ! $contact_form || ! method_exists( $contact_form, 'id' ) ) {
			return false;
		}

		return [
			'form_id'    => $contact_form->id(),
			'form_title' => $contact_form->title(),
			'status'     => $contact_form->prop( 'status' ),
			'locale'     => $contact_form->locale(),
			'title'      => current_time( 'mysql' ),
			'user_id'    => get_current_user_id(),
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$contact_form = $args[0] ?? null;

				if ( ! $contact_form ) {
					return false;
				}

				$payload = self::resolve_form_submit_payload(
					$args[0] ?? null,
					$args[1] ?? null,
					$args[2] ?? null
				);

				if ( ! $payload ) {
					return false;
				}

				$select_form = $node['config']['form_id'] ?? 'any';

				if ( $select_form !== 'any' && (int) $select_form != (int) $payload['form_id'] ) {
					return false;
				}

				return [
					'success' => true,
					'form'    => $payload,
				];

			case 'form_created':
			case 'form_updated':
				$payload = self::resolve_form_admin_payload(
					$args[0] ?? null,
				);

				if ( ! $payload ) {
					return false;
				}
				$action_map = [
					'form_created' => 'created',
					'form_updated' => 'updated',
				];

				if ( isset( $action_map[ $node['event'] ] ) ) {
					$payload['action'] = $action_map[ $node['event'] ];
				}

				return [
					'success' => true,
					'form'    => $payload,
				];
		}//end switch
		return false;
	}

    public static function get_dynamic_queries(): array {
		return [
			'form_query' => [ self::class, 'form_query_types' ],
		];
	}

    public static function form_query_types( $q ) {
		$options = [
            [
                'label' => 'Any Form',
                'name' => 'any'
            ],
		];

        if ( class_exists( 'WPCF7_ContactForm' ) ) {
            $forms = \WPCF7_ContactForm::find();
            foreach ( $forms as $form ) {
                $options[]  = [
                    'name' => $form->id(),
                    'label' => $form->title(),
                ];
            }
        }

        return $options;
	}
}
