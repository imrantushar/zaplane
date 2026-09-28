<?php

namespace Zaplane\Tests\Framework;

use PHPUnit\Framework\TestCase;
use Zaplane\Framework\Classes\Query;

require_once ZAPLANE_INCLUDES_DIR_PATH . 'framework/classes/query.php';

class QueryTest extends TestCase {

	/**
	 * @test
	 */
	public function it_restores_all_manifest_hooks_for_an_older_trigger_node(): void {
		$node = [
			'data' => [
				'app'   => 'wpmapblock',
				'event' => 'marker_added',
				'hook'  => 'update_post_meta',
			],
		];

		$this->assertSame(
			[ 'update_post_meta', 'updated_post_meta' ],
			Query::hooks_for( $node )
		);
	}

	/**
	 * @test
	 */
	public function it_uses_all_persisted_hooks_for_a_new_trigger_node(): void {
		$node = [
			'data' => [
				'app'   => 'wpmapblock',
				'event' => 'marker_added',
				'hook'  => 'update_post_meta',
				'hooks' => [ 'update_post_meta', 'updated_post_meta' ],
			],
		];

		$this->assertSame(
			[ 'update_post_meta', 'updated_post_meta' ],
			Query::hooks_for( $node )
		);
	}

	/**
	 * @test
	 */
	public function it_keeps_single_hook_triggers_single(): void {
		$node = [
			'data' => [
				'app'   => 'wpmapblock',
				'event' => 'map_updated',
				'hook'  => 'updated_post_meta',
			],
		];

		$this->assertSame( [ 'updated_post_meta' ], Query::hooks_for( $node ) );
	}
}
