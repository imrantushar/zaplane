<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\GraphValidator;
use Zaplane\Framework\Database\ORM\QueryBuilder;
use Zaplane\Models\Recipe;
use Zaplane\Recipes\RecipeCompiler;
use Zaplane\Recipes\Registry;
use Zaplane\Recipes\ShippedRecipes;
use Zaplane\Services\RecipeGroupBuilder;
use Zaplane\Services\RecipeGroupService;
use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPDBMock;

class RecipeRegistryTest extends TestCase {

	private const EMAIL = [
		'action' => 'gemcrm.send_email',
		'config' => [
			'recipient_type' => 'custom',
			'custom_email'   => '{{trigger.email}}',
			'subject'        => 'Hello',
			'content_source' => 'custom',
			'body'           => '<p>Hello</p>',
		],
	];

	/** @var mixed */
	private $wpdb;

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
		Registry::reset();
		unset( $GLOBALS['zaplane_test_doing_it_wrong'] );

		$this->wpdb      = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = new WPDBMock();

		$cache = ( new \ReflectionClass( QueryBuilder::class ) )->getProperty( 'queryCache' );
		$cache->setAccessible( true );
		$cache->setValue( null, [] );
	}

	protected function tearDown(): void {
		Registry::reset();
		$GLOBALS['wpdb'] = $this->wpdb;
		parent::tearDown();
	}

	public function test_steps_become_a_line_of_connected_steps_in_order(): void {
		list( $graph, $options ) = RecipeCompiler::graph(
			[
				[ 'trigger' => 'woocommerce.cart_abandoned' ],
				[
					'action' => 'delay.wait',
					'config' => [
						'unit'   => 'days',
						'amount' => 1,
					],
				],
				self::EMAIL,
			]
		);

		$this->assertSame( [ '1', '2', '3' ], array_column( $graph['nodes'], 'id' ) );
		$this->assertSame( [ 'trigger', 'action', 'action' ], array_column( $graph['nodes'], 'type' ) );
		$this->assertSame(
			[
				[
					'id'     => 'e1-2',
					'source' => '1',
					'target' => '2',
				],
				[
					'id'     => 'e2-3',
					'source' => '2',
					'target' => '3',
				],
			],
			$graph['edges']
		);
		$this->assertSame( [], $options );

		// Laid out left to right, on one row.
		$this->assertLessThan( $graph['nodes'][1]['position']['x'], $graph['nodes'][0]['position']['x'] );
		$this->assertLessThan( $graph['nodes'][2]['position']['x'], $graph['nodes'][1]['position']['x'] );
		$this->assertSame( $graph['nodes'][1]['position']['y'], $graph['nodes'][2]['position']['y'] );
	}

	public function test_the_catalog_fills_in_each_steps_label_icon_name_and_hook(): void {
		list( $graph ) = RecipeCompiler::graph(
			[
				[ 'trigger' => 'woocommerce.cart_abandoned' ],
				array_merge( self::EMAIL, [ 'name' => 'Send Reminder' ] ),
			]
		);

		$woo     = Catalog::entry( 'woocommerce' );
		$gemcrm  = Catalog::entry( 'gemcrm' );
		$trigger = $woo['triggers']['cart_abandoned'];

		$this->assertSame(
			[
				'app'    => 'woocommerce',
				'event'  => 'cart_abandoned',
				'hook'   => Catalog::primary_hook( $trigger ),
				'label'  => $woo['name'],
				'icon'   => $woo['icon'],
				'name'   => $trigger['label'],
				'config' => [],
			],
			$graph['nodes'][0]['data']
		);
		$this->assertNotSame( '', $graph['nodes'][0]['data']['hook'] );

		$this->assertSame( $gemcrm['name'], $graph['nodes'][1]['data']['label'] );
		$this->assertSame( 'Send Reminder', $graph['nodes'][1]['data']['name'] );
		$this->assertArrayNotHasKey( 'hook', $graph['nodes'][1]['data'] );
	}

	public function test_an_app_the_catalog_does_not_know_still_builds(): void {
		list( $graph ) = RecipeCompiler::graph(
			[
				[ 'trigger' => 'acme.thing_happened' ],
				[ 'action' => 'acme.do_thing' ],
			]
		);

		$this->assertSame( 'acme', $graph['nodes'][1]['data']['label'] );
		$this->assertSame( 'do_thing', $graph['nodes'][1]['data']['name'] );
	}

	public function test_every_trigger_leads_to_the_first_action(): void {
		list( $graph ) = RecipeCompiler::graph(
			[
				[ 'trigger' => 'storeengine.order_fully_refunded' ],
				[ 'trigger' => 'storeengine.order_partially_refunded' ],
				self::EMAIL,
			]
		);

		$this->assertSame( [ 'e1-3', 'e2-3' ], array_column( $graph['edges'], 'id' ) );
		$this->assertNotSame( $graph['nodes'][0]['position']['y'], $graph['nodes'][1]['position']['y'] );
	}

	public function test_an_agents_model_memory_and_tools_are_wired_into_it_and_share_its_option(): void {
		list( $graph, $options ) = RecipeCompiler::graph(
			[
				[ 'trigger' => 'webhook.catch_hook' ],
				[
					'action' => 'ai-agent.run_agent',
					'option' => 'agent',
					'model'  => [ 'action' => 'ai.generate_response' ],
					'memory' => [ 'action' => 'memory.get_history' ],
					'tools'  => [ [ 'action' => 'knowledge.retrieve' ] ],
				],
				[ 'action' => 'webhook.send_hook' ],
			]
		);

		$lines = array_column( $graph['edges'], null, 'id' );

		$this->assertSame( '3', $lines['e2-3']['target'] );
		$this->assertSame(
			[
				'id'           => 'e4-2-model',
				'source'       => '4',
				'sourceHandle' => 'sub_out',
				'target'       => '2',
				'targetHandle' => 'ai_model',
			],
			$lines['e4-2-model']
		);
		$this->assertSame( 'ai_memory', $lines['e5-2-memory']['targetHandle'] );
		$this->assertSame( 'ai_tool', $lines['e6-2-tool']['targetHandle'] );
		$this->assertSame( [ 'agent' => [ '2', '4', '5', '6' ] ], $options );
	}

	public function test_a_recipe_of_steps_is_stored_as_a_group_of_one(): void {
		$record = RecipeCompiler::record(
			[
				'title'       => 'Say hello',
				'description' => 'Emails a hello.',
				'steps'       => [
					[ 'trigger' => 'woocommerce.cart_abandoned' ],
					self::EMAIL,
				],
			]
		);

		$this->assertSame( Recipe::TYPE_WORKFLOW, $record['type'] );
		$this->assertSame( 'Say hello', $record['title'] );

		$blueprint = json_decode( $record['blueprint'], true );
		$this->assertSame( 'Say hello', $blueprint['folder'] );
		$this->assertSame( [ 'workflow' ], array_column( $blueprint['workflows'], 'key' ) );
		$this->assertSame( [ 'woo.svg', 'crm.svg' ], json_decode( $record['integration_icons'], true ) );
	}

	public function test_a_recipe_of_workflows_is_a_group_whose_options_name_their_steps(): void {
		$record = RecipeCompiler::record(
			[
				'title'     => 'Two',
				'workflows' => [
					[
						'key'     => 'first',
						'options' => [
							[
								'key'   => 'again',
								'label' => 'Send it again',
							],
						],
						'steps'   => [
							[ 'trigger' => 'woocommerce.cart_abandoned' ],
							self::EMAIL,
							array_merge( self::EMAIL, [ 'option' => 'again' ] ),
						],
					],
					[
						'key'     => 'second',
						'default' => false,
						'steps'   => [
							[ 'trigger' => 'woocommerce.cart_abandoned' ],
							self::EMAIL,
						],
					],
				],
			]
		);

		$this->assertSame( Recipe::TYPE_GROUP, $record['type'] );

		$workflows = RecipeGroupBuilder::workflows( json_decode( $record['blueprint'], true ) );
		$this->assertSame( [ '3' ], $workflows[0]['options'][0]['nodes'] );
		$this->assertFalse( $workflows[1]['default'] );
	}

	/**
	 * @return array<string,array{0:array<string,mixed>}>
	 */
	public static function mistakes(): array {
		$line = [
			[ 'trigger' => 'woocommerce.cart_abandoned' ],
			self::EMAIL,
		];

		return [
			'no title'                => [ [ 'steps' => $line ] ],
			'no trigger'              => [
				[
					'title' => 'x',
					'steps' => [ self::EMAIL ],
				],
			],
			'a trigger after actions' => [
				[
					'title' => 'x',
					'steps' => array_merge( $line, [ [ 'trigger' => 'woocommerce.cart_abandoned' ] ] ),
				],
			],
			'a step without an event' => [
				[
					'title' => 'x',
					'steps' => [ [ 'trigger' => 'woocommerce' ] ],
				],
			],
			'an option not listed'    => [
				[
					'title' => 'x',
					'steps' => [ $line[0], array_merge( self::EMAIL, [ 'option' => 'nope' ] ) ],
				],
			],
			'two workflows, one key'  => [
				[
					'title'     => 'x',
					'workflows' => [
						[
							'key'   => 'same',
							'steps' => $line,
						],
						[
							'key'   => 'same',
							'steps' => $line,
						],
					],
				],
			],
			'a key with spaces'       => [
				[
					'title'     => 'x',
					'workflows' => [
						[
							'key'   => 'Not A Key',
							'steps' => $line,
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider mistakes
	 * @param array<string,mixed> $recipe
	 */
	public function test_a_recipe_that_cannot_be_built_says_so( array $recipe ): void {
		$this->expectException( \InvalidArgumentException::class );

		RecipeCompiler::record( $recipe );
	}

	public function test_every_shipped_recipe_passes_the_validator_with_everything_on(): void {
		foreach ( ShippedRecipes::files() as $slug => $file ) {
			$group = RecipeCompiler::blueprint( require $file );
			$given = [
				'workflows' => [],
				'options'   => [],
				'values'    => [],
			];

			foreach ( RecipeGroupBuilder::workflows( $group ) as $workflow ) {
				$given['workflows'][ $workflow['key'] ] = true;
				$given['options'][ $workflow['key'] ]   = array_fill_keys( array_column( $workflow['options'], 'key' ), true );
			}

			foreach ( RecipeGroupBuilder::values( $group ) as $value ) {
				// A required address with no default, such as where store alerts go.
				$given['values'][ $value['key'] ] = ( 'text' === $value['type'] && '' === (string) $value['default'] ) ? 'orders@example.com' : $value['default'];
			}

			foreach ( RecipeGroupBuilder::build( $group, RecipeGroupBuilder::answers( $group, $given ) ) as $workflow ) {
				$this->assertSame( [], $this->errors( $workflow['graph'] ), $slug . ' ' . $workflow['key'] );
			}
		}
	}

	/**
	 * The validator's errors for a graph, less the settings an AI agent's sub-nodes
	 * leave empty. The agent fills those itself: it reads only the model from a
	 * Chat Model node, and the model gives a tool its arguments when it calls it.
	 * GraphValidator doesn't know that yet, and asks for them anyway.
	 *
	 * @param array<string,mixed> $graph
	 * @return array<int,array<string,string>>
	 */
	private function errors( array $graph ): array {
		$sub_nodes = [];
		foreach ( $graph['edges'] as $edge ) {
			if ( in_array( $edge['targetHandle'] ?? '', RecipeGroupBuilder::SUB_NODE_PORTS, true ) ) {
				$sub_nodes[] = 'node ' . $edge['source'];
			}
		}

		return array_values(
			array_filter(
				GraphValidator::check( $graph )['errors'],
				static function ( $error ) use ( $sub_nodes ) {
					return ! ( in_array( $error['where'], $sub_nodes, true ) && 0 === strpos( $error['message'], 'Required field' ) );
				}
			)
		);
	}

	public function test_it_collects_the_shipped_recipes_and_takes_them_away_on_request(): void {
		$registry = Registry::instance();

		$this->assertSame( array_keys( ShippedRecipes::files() ), array_keys( $registry->all() ) );
		$this->assertTrue( $registry->collected() );

		$registry->remove( 'birthday-discount' );
		$this->assertArrayNotHasKey( 'birthday-discount', $registry->all() );
	}

	public function test_a_recipe_needs_a_slug(): void {
		$this->expectException( \InvalidArgumentException::class );

		Registry::instance()->add( '!!!', [ 'title' => 'x' ] );
	}

	public function test_sync_saves_the_recipes_once_until_they_change(): void {
		global $wpdb;

		$registry = Registry::instance();
		$registry->all();
		$registry->add(
			'say-hello',
			[
				'title' => 'Say hello',
				'steps' => [
					[ 'trigger' => 'woocommerce.cart_abandoned' ],
					self::EMAIL,
				],
			]
		);

		$registry->sync();
		$saved = $wpdb->tables[ Recipe::getTable() ] ?? [];

		$this->assertCount( count( ShippedRecipes::files() ) + 1, $saved );
		$this->assertContains( 'say-hello', array_column( $saved, 'slug' ) );

		$registry->sync();
		$this->assertCount( count( $saved ), $wpdb->tables[ Recipe::getTable() ], 'Nothing changed, so nothing is saved.' );

		$registry->remove( 'say-hello' );
		$registry->sync();
		$this->assertGreaterThan( count( $saved ), count( $wpdb->tables[ Recipe::getTable() ] ), 'A change saves the recipes again.' );
	}

	public function test_a_broken_recipe_does_not_keep_the_others_out(): void {
		global $wpdb;

		$registry = Registry::instance();
		$registry->all();
		$registry->add( 'broken', [ 'steps' => [] ] );

		$registry->sync();

		$this->assertCount( count( ShippedRecipes::files() ), $wpdb->tables[ Recipe::getTable() ] );
		$this->assertStringContainsString( 'broken', implode( ' ', $GLOBALS['zaplane_test_doing_it_wrong'] ?? [] ) );
	}

	public function test_a_recipe_saved_from_a_workflow_is_set_up_as_a_group_of_one_without_its_connections(): void {
		$recipe            = new Recipe();
		$recipe->title     = 'Saved';
		$recipe->type      = Recipe::TYPE_WORKFLOW;
		$recipe->blueprint = (string) wp_json_encode(
			[
				'title'    => 'Saved',
				'layout'   => 'TB',
				'versions' => [
					[
						'is_active'  => false,
						'graph_json' => [
							'nodes' => [],
							'edges' => [],
						],
					],
					[
						'is_active'  => true,
						'graph_json' => [
							'nodes' => [
								[
									'id'   => '1',
									'type' => 'trigger',
									'data' => [
										'app'           => 'webhook',
										'event'         => 'catch_hook',
										'connection_id' => '4',
									],
								],
							],
							'edges' => [],
						],
					],
				],
			]
		);

		$group = RecipeGroupService::group_of( $recipe );

		$this->assertSame( [ 'workflow' ], array_column( $group['workflows'], 'key' ) );
		$this->assertSame( 'TB', $group['workflows'][0]['layout'] );
		$this->assertCount( 1, $group['workflows'][0]['graph']['nodes'] );
		$this->assertArrayNotHasKey( 'connection_id', $group['workflows'][0]['graph']['nodes'][0]['data'] );
	}
}
