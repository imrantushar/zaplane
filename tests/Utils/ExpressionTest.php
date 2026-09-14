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

	/**
	 * The tag used to be compiled to PHP and handed to eval(), and only
	 * identifiers were rewritten — so `a.b(c.d)` became a call whose function
	 * name was read out of the run's own data. For a webhook-triggered workflow
	 * that data is a JSON body posted by a stranger.
	 *
	 * @test
	 */
	public function a_tag_cannot_call_a_function_named_by_the_data(): void {
		$data = [ 'a' => [ 'b' => 'strtoupper' ], 'c' => [ 'd' => 'proof' ] ];

		$this->assertNull( Expression::evaluate( '{{a.b(c.d)}}', $data ) );
		$this->assertNull( Expression::evaluate( '{{a.b()}}', $data ) );
		$this->assertNull( Expression::evaluate( '{{ (a.b)(c.d) }}', $data ) );
	}

	/**
	 * Everything that was not an identifier used to reach eval() untouched.
	 *
	 * @test
	 */
	public function nothing_outside_the_grammar_resolves(): void {
		$data = [ 'x' => [ 'y' => 'phpinfo' ], 'cmd' => 'id' ];

		foreach ( [
			'{{1);phpinfo();//}}',
			'{{1;phpinfo()}}',
			'{{1+1;system(cmd)}}',
			'{{$GLOBALS}}',
			'{{`id`}}',
			'{{x.y[0]()}}',
		] as $attempt ) {
			$this->assertNull( Expression::evaluate( $attempt, $data ), $attempt . ' should not resolve' );
		}
	}

	/**
	 * A step's output addressed by its number is the commonest tag in the
	 * product, and it starts with a digit without being one.
	 *
	 * @test
	 */
	public function a_path_rooted_at_a_number_is_a_path_not_a_number(): void {
		$data = [ '1' => [ 'first_name' => 'Ada', 'order_id' => 77 ] ];

		$this->assertSame( 'Ada', Expression::evaluate( '{{1.first_name}}', $data ) );
		$this->assertSame( 77, Expression::evaluate( '{{1.order_id}}', $data ) );
		$this->assertSame( 'Hi Ada, order 77.', Expression::evaluate( 'Hi {{1.first_name}}, order {{1.order_id}}.', $data ) );
	}

	/**
	 * Arithmetic and comparison worked before and have to keep working — a
	 * security fix that quietly changes what a live workflow computes is not one.
	 *
	 * @test
	 */
	public function the_operators_still_work(): void {
		$data = [ 'n' => 5, 'name' => 'Ada' ];

		$this->assertSame( 6, Expression::evaluate( '{{n + 1}}', $data ) );
		$this->assertSame( 11, Expression::evaluate( '{{n * 2 + 1}}', $data ) );
		$this->assertSame( 12, Expression::evaluate( '{{(n + 1) * 2}}', $data ) );
		$this->assertTrue( Expression::evaluate( '{{n >= 5}}', $data ) );
		$this->assertFalse( Expression::evaluate( '{{n > 5}}', $data ) );
		$this->assertTrue( Expression::evaluate( '{{n > 3 && true}}', $data ) );
		$this->assertFalse( Expression::evaluate( '{{!true}}', $data ) );
		$this->assertSame( 'Ada', Expression::evaluate( '{{name}}', $data ) );
	}

	/**
	 * A missing tag has always meant "nothing here". It must not become a zero
	 * in a total, and a run must not stop over it.
	 *
	 * @test
	 */
	public function a_missing_path_is_nothing_rather_than_zero(): void {
		$this->assertNull( Expression::evaluate( '{{nowhere.at.all}}', [] ) );
		$this->assertNull( Expression::evaluate( '{{nowhere + 1}}', [] ) );

		// A tag standing alone keeps the value's own type; one inside a sentence
		// becomes text, and nothing is an empty string rather than the word null.
		$this->assertSame( 'Hello , welcome.', Expression::evaluate( 'Hello {{nowhere}}, welcome.', [] ) );
	}

	/**
	 * Form fields are often named with hyphens. Contact Form 7's `your-email` read as
	 * `your` minus `email`, so every step that used it got nothing.
	 *
	 * @test
	 */
	public function a_field_name_with_a_hyphen_is_one_key(): void {
		$form = [ 'form' => [ 'form_data' => [ 'your-name' => 'Rimon', 'your-email' => 'rimon@example.com' ] ] ];
		$data = [ '2' => $form, 'trigger' => $form, 'your-email' => 'top@example.com' ];

		$this->assertSame( 'rimon@example.com', Expression::evaluate( '{{2.form.form_data.your-email}}', $data ) );
		$this->assertSame( 'rimon@example.com', Expression::evaluate( '{{ trigger.form.form_data.your-email }}', $data ) );
		$this->assertSame( 'top@example.com', Expression::evaluate( '{{your-email}}', $data ) );
		$this->assertSame(
			'Hi Rimon, we will write to rimon@example.com.',
			Expression::evaluate( 'Hi {{2.form.form_data.your-name}}, we will write to {{2.form.form_data.your-email}}.', $data )
		);
		$this->assertTrue( Expression::evaluate( '{{2.form.form_data.your-email == "rimon@example.com"}}', $data ) );
	}

	/**
	 * @test
	 */
	public function a_key_with_several_hyphens_is_one_key(): void {
		$this->assertSame( 'Ada', Expression::evaluate( '{{1.billing-first-name}}', [ '1' => [ 'billing-first-name' => 'Ada' ] ] ) );
	}

	/**
	 * A hyphen is part of a name only when the data has that name. Between two
	 * values it is still a minus sign.
	 *
	 * @test
	 */
	public function a_minus_between_two_values_still_subtracts(): void {
		$data = [ '1' => [ 'total' => 10, 'discount' => 3 ], 'n' => 5 ];

		$this->assertSame( 7, Expression::evaluate( '{{1.total-1.discount}}', $data ) );
		$this->assertSame( 4, Expression::evaluate( '{{n-1}}', $data ) );
		$this->assertSame( 4, Expression::evaluate( '{{n - 1}}', $data ) );
	}

	/**
	 * @test
	 */
	public function a_hyphenated_field_the_run_does_not_have_is_nothing(): void {
		$data = [ '2' => [ 'form' => [ 'form_data' => [ 'your-email' => 'rimon@example.com' ] ] ] ];

		$this->assertNull( Expression::evaluate( '{{2.form.form_data.your-phone}}', $data ) );
		$this->assertSame( 'Call .', Expression::evaluate( 'Call {{2.form.form_data.your-phone}}.', $data ) );
	}
}
