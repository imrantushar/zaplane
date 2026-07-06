<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sticky Note — a canvas annotation for documenting a workflow.
 *
 * It carries no logic: if it ever ends up in the execution path it simply passes
 * its input straight through, so notes never affect a run. (A richer borderless,
 * resizable canvas rendering is a frontend concern; this keeps the node safe and
 * available everywhere in the meantime.)
 */
class StickyNote extends IntegrationBase {

	public static function get_slug(): string {
		return 'sticky_note';
	}

	public static function get_name(): string {
		return 'Sticky Note';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'sticky-note.svg';
	}

	public static function get_actions(): array {
		return [
			'note' => [ 'label' => 'Sticky Note' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key' => 'content',
				'label' => 'Note',
				'type' => 'textarea',
				'help' => 'Free-form notes for your team. Not executed.'
			],
			[
				'key'     => 'color',
				'label'   => 'Colour',
				'type'    => 'select',
				'default' => 'yellow',
				'options' => [
					[
						'value' => 'yellow',
						'label' => 'Yellow'
					],
					[
						'value' => 'blue',
						'label' => 'Blue'
					],
					[
						'value' => 'green',
						'label' => 'Green'
					],
					[
						'value' => 'pink',
						'label' => 'Pink'
					],
				],
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		// Pure annotation — pass input through untouched.
		return [
			'port' => 'main',
			'data' => $input
		];
	}
}
