<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Framework\Database\ORM\QueryBuilder;
use Zaplane\Tests\TestCase;

/**
 * where() took any callable as a nested group. A column that shares its name
 * with a function ("comment_ID" is a WordPress template tag) was called
 * instead of filtered on, so Comment::find() returned the site's first
 * comment to every comment trigger.
 */
class QueryBuilderWhereTest extends TestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		if ( ! function_exists( 'zaplane_test_column_fn' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- A function named like a column, for this test only.
			eval( 'function zaplane_test_column_fn() { echo "0"; }' );
		}
	}

	public function test_a_column_named_like_a_function_is_filtered_on(): void {
		ob_start();
		$sql = ( new QueryBuilder( 'wp_comments' ) )->where( 'zaplane_test_column_fn', 328 )->toSql();
		$out = ob_get_clean();

		$this->assertStringContainsString( 'WHERE', $sql );
		$this->assertStringContainsString( 'zaplane_test_column_fn', $sql );
		$this->assertSame( '', $out, 'The function must not be called.' );
	}

	public function test_a_closure_is_still_a_nested_group(): void {
		$sql = ( new QueryBuilder( 'wp_comments' ) )
			->where( 'comment_post_ID', 5 )
			->where( function ( $q ) {
				$q->where( 'comment_approved', '1' )->orWhere( 'user_id', 1 );
			} )
			->toSql();

		$this->assertMatchesRegularExpression( '/WHERE .*comment_post_ID.* AND \(.*comment_approved.* OR .*user_id.*\)/', $sql );
	}
}
