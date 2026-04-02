<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Hubspot;
use Zaplane\Tests\WPMocks;

class HubspotTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Hubspot::class;
	}

	public function test_query_portals_returns_connected_portal(): void {
		WPMocks::setOption( 'leadin_portalId', '245438398' );

		$result = Hubspot::query_portals( [] );

		$this->assertCount( 1, $result );
		$this->assertEquals( '245438398', $result[0]['id'] );
		$this->assertEquals( 'Hub ID 245438398', $result[0]['label'] );
	}

	public function test_query_forms_returns_embedded_forms_for_connected_portal(): void {
		global $wpdb;

		WPMocks::setOption( 'leadin_portalId', '245438398' );
		$wpdb->tables['results_sequence'] = array(
			array(
				array(
					'content' => '[hubspot portal="245438398" id="11111111-1111-1111-1111-111111111111" type="form"]',
				),
			),
			array(
				array(
					'content' => wp_json_encode(
						array(
							'portalId' => '245438398',
							'formId'   => '22222222-2222-2222-2222-222222222222',
							'formName' => 'Contact Us',
						)
					),
				),
			),
			array(),
		);

		$result = Hubspot::query_forms(
			array(
				'where'  => array(
					'portal_id' => '245438398',
				),
				'search' => 'contact',
				'limit'  => 10,
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( '22222222-2222-2222-2222-222222222222', $result[0]['id'] );
		$this->assertEquals( 'Contact Us', $result[0]['label'] );
	}

	public function test_connection_succeeds_when_official_plugin_portal_exists(): void {
		WPMocks::setOption( 'leadin_portalId', '245438398' );

		$result = Hubspot::test_connection( [] );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( '245438398', $result['details']['portal_id'] );
	}

	public function test_connection_fails_without_connected_portal(): void {
		$result = Hubspot::test_connection( [] );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'HubSpot WordPress plugin is not connected', $result['message'] );
	}

	public function test_submit_form_uses_connected_portal_and_simple_fields(): void {
		WPMocks::setOption( 'leadin_portalId', '245438398' );
		$this->mockHttp(
			[
				'inlineMessage' => 'Thanks for submitting',
				'redirectUri'   => null,
			]
		);

		$result = Hubspot::execute_node(
			$this->makeActionNode(
				'submit_form',
				[
					'form_guid' => '12345678-1234-1234-1234-123456789abc',
					'email'     => 'user@example.com',
					'firstname' => 'Siyam',
				]
			),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( '245438398', $result['data']['hubspot_form_portal_id'] );
		$this->assertEquals( '12345678-1234-1234-1234-123456789abc', $result['data']['hubspot_form_guid'] );
		$this->assertEquals( 'Thanks for submitting', $result['data']['hubspot_form_response']['inlineMessage'] );
	}

	public function test_submit_form_throws_without_portal_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Portal ID is required. Connect the official HubSpot WordPress plugin first or enter a portal ID manually.' );

		Hubspot::execute_node(
			$this->makeActionNode(
				'submit_form',
				[
					'form_guid' => '12345678-1234-1234-1234-123456789abc',
					'email'     => 'user@example.com',
				]
			),
			[]
		);
	}

	public function test_submit_form_throws_without_fields(): void {
		WPMocks::setOption( 'leadin_portalId', '245438398' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Fields are required' );

		Hubspot::execute_node(
			$this->makeActionNode(
				'submit_form',
				[
					'form_guid' => '12345678-1234-1234-1234-123456789abc',
				]
			),
			[]
		);
	}
}
