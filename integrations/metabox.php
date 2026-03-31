<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Metabox extends IntegrationBase {


	public static function get_slug(): string {
		return 'metabox';
	}

	public static function get_name(): string {
		return 'MetaBox';
	}

	public static function get_icon(): string {
		return 'metabox.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submission' => [
				'label' => 'Form Submission',
				'hook'  => 'rwmb_frontend_after_save_post'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submission' === $trigger ) {
			return [
				[
					'key'      => 'form_id',
					'label'    => 'Forms',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'metabox',
						'query'       => 'form_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submission':

		}//end switch
		return false;
	}

	public static function get_dynamic_queries(): array {
		return [
			'form_query' => [ self::class, 'form_query_types' ],
		];
	}

	public static function form_query_types($q) {
    // যদি Meta Box বা Frontend Submission প্লাগইন না থাকে
    if (!function_exists('rwmb_meta') || !function_exists('mb_frontend_submission_load')) {
        return [
            [
                'label' => 'MetaBox is not installed or activated',
                'name'  => '',
            ],
        ];
    }

    // সব Meta Box ফর্ম নেওয়া
    $meta_box_registry = rwmb_get_registry('meta_box');
    $forms = array_values($meta_box_registry->all());

    $options = [];

    foreach ($forms as $form) {
        if (isset($form->meta_box['id'], $form->meta_box['title'])) {
            $options[] = [
                'label' => $form->meta_box['title'], // দেখানোর নাম
                'name'  => $form->meta_box['id'],    // value/ID
            ];
        }
    }

    // প্রথমে "Any Form" যুক্ত করা
    array_unshift(
        $options,
        [
            'label' => 'Any Form',
            'name'  => 'any',
        ]
    );

    return $options;
}
}
