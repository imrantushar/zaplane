<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Ablocks extends IntegrationBase {


	public static function get_slug(): string {
		return 'ablocks';
	}

	public static function get_name(): string {
		return 'ABlocks';
	}

	public static function get_icon(): string {
		return 'ablocks.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'ablocks/form_builder/after_submission',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submitted' === $trigger ) {
			return [
				[
					'key'      => 'form_id',
					'label'    => 'Forms',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'ablocks',
						'query'       => 'form_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}
		return [];
	}

	/**
	 * Resolve the trigger payload from the aBlocks submission hook args.
	 *
	 * Hook signature: do_action( 'ablocks/form_builder/after_submission', $form_info, $block_data, $validate )
	 *  - $form_info: [ 'info' => [ type, postId, email, actions, config ], 'data' => [ field => [ 'value' => mixed ] ] ]
	 *  - $block_data: resolved form block attributes/inner blocks
	 *  - $validate:  ValidateFormData object ( state_data holds submission_id )
	 */
	public static function resolve_trigger( array $node, array $args ) {
		if ( 'form_submitted' !== ( $node['event'] ?? '' ) ) {
			return false;
		}

		$form_info  = $args[0] ?? [];
		$block_data = $args[1] ?? [];
		$validate   = $args[2] ?? null;

		if ( ! is_array( $form_info ) || empty( $form_info['info'] ) ) {
			return false;
		}

		$info    = $form_info['info'];
		$config  = is_array( $info['config'] ?? null ) ? $info['config'] : [];
		$block_id = (string) ( $config['block_id'] ?? $block_data['parentAttributes']['block_id'] ?? '' );

		$select_form = $node['config']['form_id'] ?? 'any';

		if ( 'any' !== $select_form && (string) $select_form !== $block_id ) {
			return false;
		}

		$payload = [
			'form_id'       => $block_id,
			'form_name'     => (string) ( $config['formName'] ?? '' ),
			'form_type'     => (string) ( $info['type'] ?? '' ),
			'post_id'       => $info['postId'] ?? '',
			'email'         => (string) ( $info['email'] ?? '' ),
			'submission_id' => is_object( $validate ) ? ( $validate->state_data['submission_id'] ?? null ) : null,
			'form_data'     => self::flatten_form_data( $form_info['data'] ?? [] ),
		];

		return [
			'success' => true,
			'form'    => $payload,
		];
	}

	/**
	 * aBlocks stores each captured field as [ name => [ 'value' => mixed ] ].
	 * Flatten it to a plain [ name => value ] map for friendlier field mapping.
	 */
	public static function flatten_form_data( $data ): array {
		$flat = [];
		if ( ! is_array( $data ) ) {
			return $flat;
		}
		foreach ( $data as $name => $field ) {
			$flat[ $name ] = is_array( $field ) && array_key_exists( 'value', $field ) ? $field['value'] : $field;
		}
		return $flat;
	}

	public static function get_trigger_sample_output( string $event ): array {
		// form_submitted fields are user-defined per form — the "@" picker fills
		// them from a captured real submission, so no static sample here.
		return [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'form_query' => [ self::class, 'form_query_types' ],
		];
	}

	/**
	 * List aBlocks form-builder forms by scanning published posts/pages for the
	 * `ablocks/form-builder` block. Each form is identified by its block_id.
	 */
	public static function form_query_types( $query ) {
		$options = [
			[
				'label' => 'Any Form',
				'name'  => 'any',
			],
		];

		$posts = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				's'              => 'ablocks/form-builder',
			]
		);

		foreach ( $posts as $post ) {
			if ( ! has_block( 'ablocks/form-builder', $post ) ) {
				continue;
			}

			foreach ( parse_blocks( $post->post_content ) as $block ) {
				foreach ( self::collect_form_blocks( $block ) as $form_block ) {
					$attrs    = $form_block['attrs'] ?? [];
					$block_id = $attrs['block_id'] ?? '';
					if ( '' === $block_id ) {
						continue;
					}

					$name  = $attrs['formName'] ?? '';
					$label = ( '' !== $name ? $name : __( 'Untitled form', 'zaplane' ) ) . ' — ' . get_the_title( $post );

					$options[] = [
						'name'  => $block_id,
						'label' => $label,
					];
				}
			}
		}

		return $options;
	}

	/**
	 * Recursively pull every `ablocks/form-builder` block out of a parsed block tree.
	 */
	protected static function collect_form_blocks( array $block ): array {
		$found = [];

		if ( 'ablocks/form-builder' === ( $block['blockName'] ?? '' ) ) {
			$found[] = $block;
		}

		foreach ( $block['innerBlocks'] ?? [] as $inner ) {
			$found = array_merge( $found, self::collect_form_blocks( $inner ) );
		}

		return $found;
	}
}
