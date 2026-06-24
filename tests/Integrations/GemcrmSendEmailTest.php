<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Gemcrm;

// Lightweight stubs for the GemCRM classes the action consults. They don't
// exist in the test environment, so defining them here lets us exercise the
// real tree-rendering / template branches without the full plugin.
if ( ! class_exists( \GemCrm\Classes\EmailTreeRenderer::class ) ) {
	// phpcs:ignore
	eval( 'namespace GemCrm\Classes; class EmailTreeRenderer { public static function render_content( array $tree ) { return "<rendered>" . ( $tree["root"]["text"] ?? "" ) . "</rendered>"; } }' );
}
if ( ! class_exists( \GemCrm\RegisterPostType::class ) ) {
	// phpcs:ignore
	eval( 'namespace GemCrm; class RegisterPostType { const EMAIL_TEMPLATE_CPT = "gemcrm_templates"; }' );
}

class GemcrmSendEmailTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Gemcrm::class;
	}

	private function sendEmailFields(): array {
		$fields = Gemcrm::get_action_config_schema( 'send_email' );
		$byKey  = [];
		foreach ( $fields as $field ) {
			$byKey[ $field['key'] ] = $field;
		}
		return $byKey;
	}

	public function test_template_select_is_always_visible_and_optional(): void {
		$fields = $this->sendEmailFields();

		// The template selector is a directly-visible (no depends_on) optional
		// dropdown wired to the Zaplane email-template query.
		$this->assertArrayHasKey( 'template_id', $fields );
		$this->assertSame( 'select', $fields['template_id']['type'] );
		$this->assertArrayNotHasKey( 'depends_on', $fields['template_id'] );
		$this->assertFalse( $fields['template_id']['required'] );
		$this->assertSame( 'gemcrm_email_template_query', $fields['template_id']['dynamic']['query'] );

		// No legacy body_source toggle.
		$this->assertArrayNotHasKey( 'body_source', $fields );
	}

	public function test_body_field_is_richtext_with_merge_tags(): void {
		$fields = $this->sendEmailFields();

		$this->assertArrayHasKey( 'body', $fields );
		// Inline body is the simple rich-text editor, always visible, optional
		// (a selected template takes precedence).
		$this->assertSame( 'richtext', $fields['body']['type'] );
		$this->assertArrayNotHasKey( 'depends_on', $fields['body'] );
		$this->assertFalse( $fields['body']['required'] );
		$this->assertNotEmpty( $fields['body']['merge_tags'] );

		$tags = array_column( $fields['body']['merge_tags'], 'value' );
		$this->assertContains( '{{contact.first_name}}', $tags );
		$this->assertContains( '{{unsubscribe_link}}', $tags );
	}

	public function test_email_template_query_registered(): void {
		$queries = Gemcrm::get_dynamic_queries();
		$this->assertArrayHasKey( 'gemcrm_email_template_query', $queries );
	}

	/** Invoke the private resolver under test. */
	private function resolveContent( array $config ): array {
		$ref    = new \ReflectionMethod( Gemcrm::class, 'resolve_email_content' );
		$ref->setAccessible( true );
		return $ref->invoke( null, $config );
	}

	public function test_inline_string_body_used_when_no_template(): void {
		$result = $this->resolveContent( [
			'subject' => 'Hello',
			'body'    => '<p>legacy html</p>',
		] );

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertSame( 'Hello', $result['subject'] );
		$this->assertSame( '<p>legacy html</p>', $result['body'] );
	}

	public function test_inline_tree_body_is_rendered_to_html(): void {
		$result = $this->resolveContent( [
			'subject' => 'Hello',
			'body'    => [ 'root' => [ 'text' => 'hi' ] ],
		] );

		$this->assertSame( '<rendered>hi</rendered>', $result['body'] );
	}

	public function test_selected_template_takes_precedence_over_inline_body(): void {
		// A non-existent template id still routes into the template branch
		// (precedence), surfacing a not-found error rather than using the body.
		$result = $this->resolveContent( [
			'subject'     => 'Hello',
			'template_id' => 999999,
			'body'        => '<p>ignored</p>',
		] );

		$this->assertArrayHasKey( 'error', $result );
	}

	/** Invoke the private tree renderer under test. */
	private function renderTree( $tree ): string {
		$ref = new \ReflectionMethod( Gemcrm::class, 'render_email_tree' );
		$ref->setAccessible( true );
		return $ref->invoke( null, $tree );
	}

	public function test_render_email_tree_handles_array_and_json(): void {
		// Array tree (inline editor value).
		$this->assertSame(
			'<rendered>arr</rendered>',
			$this->renderTree( [ 'root' => [ 'text' => 'arr' ] ] )
		);
		// JSON-string tree (template post_content).
		$this->assertSame(
			'<rendered>json</rendered>',
			$this->renderTree( json_encode( [ 'root' => [ 'text' => 'json' ] ] ) )
		);
	}

	public function test_render_email_tree_returns_empty_for_invalid_tree(): void {
		$this->assertSame( '', $this->renderTree( '' ) );
		$this->assertSame( '', $this->renderTree( [ 'no_root' => true ] ) );
		$this->assertSame( '', $this->renderTree( 'not json' ) );
	}
}
