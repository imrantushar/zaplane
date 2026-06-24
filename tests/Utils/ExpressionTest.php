<?php

namespace Zaplane\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Zaplane\Framework\Classes\Expression;

class ExpressionTest extends TestCase {

	/**
	 * @test
	 */
	public function it_resolves_a_full_match_workflow_variable(): void {
		$this->assertSame( 'Ada', Expression::evaluate( '{{trigger.name}}', [ 'trigger' => [ 'name' => 'Ada' ] ] ) );
	}

	/**
	 * @test
	 */
	public function it_interpolates_inline_workflow_variables(): void {
		$this->assertSame(
			'Hi Ada!',
			Expression::evaluate( 'Hi {{trigger.name}}!', [ 'trigger' => [ 'name' => 'Ada' ] ] )
		);
	}

	/**
	 * @test
	 */
	public function it_passes_reserved_contact_tags_through_untouched(): void {
		// Full match.
		$this->assertSame(
			'{{contact.first_name}}',
			Expression::evaluate( '{{contact.first_name}}', [] )
		);
		// Inline.
		$this->assertSame(
			'Hello {{contact.first_name}}',
			Expression::evaluate( 'Hello {{contact.first_name}}', [] )
		);
	}

	/**
	 * @test
	 */
	public function it_passes_reserved_link_tags_through_untouched(): void {
		$this->assertSame(
			'{{unsubscribe_link}}',
			Expression::evaluate( '{{unsubscribe_link}}', [] )
		);
		$this->assertSame(
			'{{update_preferences_link}}',
			Expression::evaluate( '{{update_preferences_link}}', [] )
		);
	}

	/**
	 * @test
	 */
	public function it_mixes_resolved_variables_and_reserved_tags(): void {
		$out = Expression::evaluate(
			'Hi {{trigger.name}}, your email is {{contact.email}} — {{unsubscribe_link}}',
			[ 'trigger' => [ 'name' => 'Ada' ] ]
		);

		$this->assertSame( 'Hi Ada, your email is {{contact.email}} — {{unsubscribe_link}}', $out );
	}

	/**
	 * @test
	 */
	public function it_returns_non_string_input_unchanged(): void {
		$tree = [ 'root' => [ 'children' => [] ] ];
		$this->assertSame( $tree, Expression::evaluate( $tree, [] ) );
	}
}
