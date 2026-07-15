<?php
namespace Zaplane\Integrations\Trello;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
trait Helper {

	private static function field_board(): array {
		return [
			[
				'key' => 'board_id',
				'type' => 'select',
				'label' => 'Board',
				'required' => true,
				'dynamic' => [
					'integration' => 'trello',
					'query' => 'board_query',
					'select' => [ 'value', 'label' ],
				],
			],
		];
	}
	private static function field_list(): array {
		return [
			[
				'key' => 'list_id',
				'type' => 'select',
				'label' => 'List',
				'required' => true,
				'dynamic' => [
					'integration' => 'trello',
					'query' => 'list_query',
					'select' => [ 'value', 'label' ],
					'depends_on' => [ 'board_id' ],
				],
			],
		];
	}
	private static function field_card_name(): array {
		return [
			[
				'key' => 'card_name',
				'type' => 'text',
				'label' => 'Card Name',
				'placeholder' => 'New Task',
				'required' => true,
			],
		];
	}
	private static function field_card_select(): array {
		return array_merge(
			self::field_board(),
			[
				[
					'key' => 'card_id',
					'type' => 'select',
					'label' => 'Card',
					'required' => true,
					'dynamic' => [
						'integration' => 'trello',
						'query' => 'card_query',
						'select' => [ 'value', 'label' ],
						'depends_on' => [ 'board_id' ],
					],
				],
			]
		);
	}
	private static function field_label(): array {
		return [
			[
				'key' => 'label_id',
				'type' => 'select',
				'label' => 'Label',
				'required' => true,
				'dynamic' => [
					'integration' => 'trello',
					'query' => 'label_query',
					'select' => [ 'value', 'label' ],
					'depends_on' => [ 'board_id' ],
				],
			],
		];
	}
	private static function field_label_select(): array {
		return array_merge(
			self::field_board(),
			self::field_label()
		);
	}
	private static function field_label_color(): array {
		return [
			[
				'key' => 'label_color',
				'type' => 'select',
				'label' => 'Label Color',
				'required' => false,
				'options' => [
					[
						'label' => 'None',
						'value' => ''
					],
					[
						'label' => 'Yellow',
						'value' => 'yellow'
					],
					[
						'label' => 'Purple',
						'value' => 'purple'
					],
					[
						'label' => 'Blue',
						'value' => 'blue'
					],
					[
						'label' => 'Green',
						'value' => 'green'
					],
					[
						'label' => 'Orange',
						'value' => 'orange'
					],
					[
						'label' => 'Red',
						'value' => 'red'
					],
					[
						'label' => 'Black',
						'value' => 'black'
					],
					[
						'label' => 'Sky',
						'value' => 'sky'
					],
					[
						'label' => 'Pink',
						'value' => 'pink'
					],
					[
						'label' => 'Lime',
						'value' => 'lime'
					],
				],
			],
		];
	}

	private static function field_checklist_select(): array {
		return [
			[
				'key' => 'checklist_id',
				'type' => 'select',
				'label' => 'Checklist',
				'required' => true,
				'dynamic' => [
					'integration' => 'trello',
					'query' => 'checklist_query',
					'select' => [ 'value', 'label' ],
					'depends_on' => [ 'card_id' ],
				],
			],
		];
	}

	private static function field_checklist_item_select(): array {
		return [
			[
				'key' => 'item_id',
				'type' => 'select',
				'label' => 'Checklist Item',
				'required' => true,
				'dynamic' => [
					'integration' => 'trello',
					'query' => 'checklist_item_query',
					'select' => [ 'value', 'label' ],
					'depends_on' => [ 'checklist_id' ],
				],
			],
		];
	}

	private static function field_attachment_select(): array {
		return [
			[
				'key' => 'attachment_id',
				'type' => 'select',
				'label' => 'Attachment',
				'required' => true,
				'dynamic' => [
					'integration' => 'trello',
					'query' => 'attachment_query',
					'select' => [ 'value', 'label' ],
					'depends_on' => [ 'card_id' ],
				],
			],
		];
	}

	private static function field_member_select(): array {
		return [
			[
				'key' => 'member_id',
				'type' => 'select',
				'label' => 'Member',
				'required' => true,
				'dynamic' => [
					'integration' => 'trello',
					'query' => 'member_query',
					'select' => [ 'value', 'label' ],
					'depends_on' => [ 'board_id' ],
				],
			],
		];
	}

	private static function field_org_select_inline(): array {
		return [
			'key' => 'org_id',
			'type' => 'select',
			'label' => 'Organization / Workspace',
			'required' => false,
			'dynamic' => [
				'integration' => 'trello',
				'query' => 'org_query',
				'select' => [ 'value', 'label' ],
			],
		];
	}
}
