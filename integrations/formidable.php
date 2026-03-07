<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use FrmEntryValues;
use FrmField;
use FrmFieldsHelper;
use FrmForm;

class Formidable extends IntegrationBase {


	public static function get_slug(): string {
		return 'formidable';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'frm_success_action',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( $trigger === 'form_submitted' ) {
			$options[] = [
				'label' => 'Any Form',
				'value' => 'any'
			];

			if ( function_exists( 'load_formidable_forms' ) ) {
				$forms = FrmForm::getAll();

				foreach ( $forms as $form ) {
					$options[] = [
						'label' => $form->name,
						'value' => $form->id,
					];
				}
			}

			return [
				[
					'key'      => 'form_id',
					'label'    => 'Forms',
					'type'     => 'select',
					'options'  => $options,
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	public static function fields( $form_id ): array {
		$fields = FrmField::get_all_for_form( $form_id, '', 'include' );
		$field_list = [];

		if ( empty( $fields ) ) {
			return [];
		}

		foreach ( $fields as $value ) {
			if ( $value->type === 'name' ) {
				$field_list[] = (object) [
					'name'  => $value->field_key . '_first',
					'label' => $value->name . ' (First)',
					'type'  => 'name'
				];
				$field_list[] = (object) [
					'name'  => $value->field_key . '_middle',
					'label' => $value->name . ' (Middle)',
					'type'  => 'name'
				];
				$field_list[] = (object) [
					'name'  => $value->field_key . '_last',
					'label' => $value->name . ' (Last)',
					'type'  => 'name'
				];
				continue;
			}

			if ( $value->type === 'address' ) {
				foreach ( $value->default_value as $key => $val ) {
					$field_list[] = (object) [
						'name'  => $value->field_key . '_' . $key,
						'label' => 'address_' . $key,
						'type'  => 'address',
					];
				}
				continue;
			}

			$field_list[] = (object) [
				'name'  => $value->field_key,
				'label' => $value->name,
				'type'  => $value->type,
			];
		}//end foreach

		return $field_list;
	}

	public static function getfieldvalues( $form_id, $entry_id ): array {
		$form_fields  = [];
		$fields      = FrmFieldsHelper::get_form_fields( $form_id );
		$entry_values = new FrmEntryValues( $entry_id );
		$field_values = $entry_values->get_field_values();

		foreach ( $fields as $field ) {
			$key = $field->field_key;
			$val = isset( $field_values[ $field->id ] ) ? $field_values[ $field->id ]->get_saved_value() : '';

			if ( is_array( $val ) ) {
				if ( $field->type === 'name' ) {
					$form_fields[ $key . '_first' ]  = $val['first'] ?? '';
					$form_fields[ $key . '_middle' ] = $val['middle'] ?? '';
					$form_fields[ $key . '_last' ]   = $val['last'] ?? '';
				} elseif ( $field->type === 'checkbox' ) {
					$form_fields[ $key ] = $val;
				} elseif ( $field->type === 'file' ) {
					$urls = [];
					foreach ( $val as $attachment_id ) {
						$urls[] = wp_get_attachment_url( $attachment_id );
					}
					$form_fields[ $key ] = $urls;
				} elseif ( $field->type === 'address' ) {
					foreach ( $val as $k => $value ) {
						$form_fields[ $key . '_' . $k ] = $value;
					}
				}
				continue;
			}
			$form_fields[ $key ] = $val;
		}//end foreach

		return $form_fields;
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'form_submitted':
				$entry_id = $args[4]['entry_id'] ?? 0;
				$form_id  = $args[1]->id ?? 0;

				if ( empty( $entry_id ) || empty( $form_id ) ) {
					return false;
				}

				if ( ! empty( $node['form_id'] ) && $node['form_id'] !== 'any' ) {
					if ( (int) $form_id !== (int) $node['form_id'] ) {
						return false;
					}
				}

				$data = self::getfieldvalues( $form_id, $entry_id );

				return [
					'success'  => true,
					'form_id'  => $form_id,
					'entry_id' => $entry_id,
					'data'     => $data,
				];
		}//end switch

		return false;
	}
}
