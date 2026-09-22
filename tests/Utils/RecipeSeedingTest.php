<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Database\Seeders\RecipeSeeding;
use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPMocks;

class RecipeSeedingTest extends TestCase {

	private const TABLE = 'wp_zaplane_recipes';

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->reset();

		// Each test reads the database afresh.
		$cache = ( new \ReflectionClass( \Zaplane\Framework\Database\ORM\QueryBuilder::class ) )->getProperty( 'queryCache' );
		$cache->setAccessible( true );
		$cache->setValue( null, [] );
	}

	private function attributes(): array {
		return [
			'title'             => 'Birthday Discount',
			'description'       => 'A coupon on a contact\'s birthday.',
			'blueprint'         => '{"title":"Birthday Discount"}',
			'integration_icons' => '["crm.svg"]',
		];
	}

	public function test_a_recipe_that_is_not_there_yet_is_created_with_its_slug(): void {
		global $wpdb;
		$wpdb->tables['results'] = [];

		RecipeSeeding::save( 'birthday-discount', $this->attributes() );

		$rows = $wpdb->tables[ self::TABLE ] ?? [];

		$this->assertCount( 1, $rows );
		$this->assertSame( 'birthday-discount', $rows[0]['slug'] );
		$this->assertSame( 'workflow', $rows[0]['type'] );
		$this->assertSame( 'Birthday Discount', $rows[0]['title'] );
		$this->assertSame( 0, $rows[0]['created_by'] );
	}

	public function test_a_recipe_that_is_already_there_is_not_created_again(): void {
		global $wpdb;
		// Whatever the lookup, the recipe is found: renamed since it was seeded.
		$wpdb->tables['results'] = [
			[
				'id'         => 7,
				'slug'       => 'birthday-discount',
				'title'      => 'Our birthday email',
				'created_by' => 0,
			],
		];

		RecipeSeeding::save( 'birthday-discount', $this->attributes() );

		$this->assertArrayNotHasKey( self::TABLE, $wpdb->tables );
	}

	public function test_a_deleted_recipe_is_not_seeded_again(): void {
		global $wpdb;
		$wpdb->tables['results'] = [];

		RecipeSeeding::dismiss( 'birthday-discount' );
		RecipeSeeding::save( 'birthday-discount', $this->attributes() );

		$this->assertTrue( RecipeSeeding::is_dismissed( 'birthday-discount' ) );
		$this->assertArrayNotHasKey( self::TABLE, $wpdb->tables );
	}

	public function test_dismissing_twice_keeps_one_entry(): void {
		RecipeSeeding::dismiss( 'birthday-discount' );
		RecipeSeeding::dismiss( 'birthday-discount' );

		$this->assertSame( [ 'birthday-discount' ], WPMocks::getOption( RecipeSeeding::DISMISSED_OPTION ) );
	}
}
