<?php

namespace Zaplane\Tests\Framework;

use PHPUnit\Framework\TestCase;
use Zaplane\Framework\Classes\MetaGraph;

/**
 * The Graph version a Meta call ends up on.
 *
 * The cases that matter are the ones that used to reach the URL builder intact:
 * an override saved blank produced a double slash, and anything that isn't a
 * version string would have been pasted into the path.
 */
class MetaGraphTest extends TestCase {

	/**
	 * @test
	 */
	public function it_uses_the_default_when_no_override_is_given(): void {
		$this->assertSame( MetaGraph::DEFAULT_VERSION, MetaGraph::version( null ) );
		$this->assertSame(
			'https://graph.facebook.com/' . MetaGraph::DEFAULT_VERSION . '/me/messages',
			MetaGraph::url( 'me/messages' )
		);
	}

	/**
	 * @test
	 * @dataProvider unusableOverrides
	 */
	public function it_falls_back_to_the_default_for_an_unusable_override( string $override ): void {
		$this->assertSame( MetaGraph::DEFAULT_VERSION, MetaGraph::version( $override ) );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function unusableOverrides(): array {
		return [
			'blank'          => [ '' ],
			'whitespace'     => [ '   ' ],
			'not a version'  => [ 'latest' ],
			'path injection' => [ 'v26.0/me' ],
			'major only'     => [ 'v26' ],
		];
	}

	/**
	 * @test
	 */
	public function it_honours_a_pinned_version( ): void {
		$this->assertSame( 'v25.0', MetaGraph::version( 'v25.0' ) );
		$this->assertSame( 'v24.0', MetaGraph::version( '24.0' ), 'the v prefix is optional' );
		$this->assertSame( 'v24.0', MetaGraph::version( 'V24.0' ), 'case is not the site owner\'s problem' );
		$this->assertSame( 'v23.0', MetaGraph::version( '  v23.0  ' ) );
	}

	/**
	 * @test
	 */
	public function it_builds_a_url_without_a_double_slash(): void {
		$this->assertSame(
			'https://graph.facebook.com/' . MetaGraph::DEFAULT_VERSION . '/me',
			MetaGraph::url( '/me', '' )
		);
	}
}
