<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Ninjaform extends IntegrationBase
{


	public static function get_slug(): string
	{
		return 'ninjaform';
	}

	public static function get_name(): string
	{
		return 'Ninja Form';
	}

	public static function get_icon(): string
	{
		return 'ninjaform.svg';
	}

	public static function get_triggers(): array
	{
		return [
			'process_ninja_form' => [
				'label' => 'Form Submit',
				'hook'  => 'ninja_forms_after_submission'
			],

		];
	}

	public static function get_trigger_config_schema(string $trigger): array
	{
		if ('process_ninja_form' !== $trigger) {
			return [];
		}

		return [
			[
				'key'      => 'form_id',
				'label'    => 'Form',
				'type'     => 'select',
				'dynamic' => [
					'integration' => 'ninjaform',
					'query'       => 'forms',
					'select'      => ['name', 'label'],
				],
				'required' => true,
			],
		];
	}

	private static function resolve_form_payload($form): array
	{
		if (! $form) {
			return [];
		}

		$id    = method_exists($form, 'get_id') ? (int) $form->get_id() : 0;
		$title = method_exists($form, 'get_setting') ? (string) $form->get_setting('title') : '';

		return [
			'id'    => $id,
			'title' => $title,
		];
	}

	public static function resolve_trigger(array $node, array $args)
	{

		switch ($node['event']) {
			case 'process_ninja_form':
				$formData = $args[0] ?? null;
				if (empty($formData) || ! is_array($formData)) {
					return false;
				}

				$currentFormId = $formData['form_id']
					?? $formData['id']
					?? ($formData['form']['id'] ?? null)
					?? null;

				if (empty($currentFormId)) {
					return false;
				}

				$config       = $node['data']['config'] ?? [];
				$requiredForm = $config['form_id'] ?? 'any';

				if ('any' !== $requiredForm && (int) $requiredForm !== (int) $currentFormId) {
					return false;
				}

				$form = null;
				$nf = function_exists('Ninja_Forms') ? Ninja_Forms() : null;
				if ($nf) {
					$form = $nf->form((int) $currentFormId);
				}

				$entryId =
					$formData['sub_id']
					?? ($formData['extra']['sub_id'] ?? null)
					?? ($formData['submission']['id'] ?? null)
					?? null;

				return [
					'success'   => true,
					'entry_id'  => $entryId,
					'form_data' => $formData,
					'form'      => $form ? self::resolve_form_payload($form) : null,
				];
		} //end switch
		return false;
	}

	public static function get_dynamic_queries(): array
	{
		return [
			'forms' => [self::class, 'query_forms'],
		];
	}

	public static function query_forms()
	{

		$options = [
			[
				'label' => 'Any Form',
				'name' => 'any',
			],
		];

		$nf = function_exists('Ninja_Forms') ? Ninja_Forms() : null;
		if ($nf) {
			$forms = $nf->form()->get_forms();

			if (! empty($forms)) {
				foreach ($forms as $form) {
					$options[] = [
						'label' => $form->get_setting('title'),
						'name' => $form->get_id(),
					];
				}
			}
		}

		return $options;
	}

	public static function get_output_ports(): array
	{
		return [
			'main' => 'Main output',
		];
	}
}
