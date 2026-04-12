<?php

namespace {
	require_once __DIR__ . '/../mocks/easydigitaldownload.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\EasyDigitalDownload;
use Zaplane\Tests\WPMocks;

class EasyDigitalDownloadTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return EasyDigitalDownload::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		WPMocks::setPost( 55, [
			'post_title'  => 'EDD Download',
			'post_status' => 'publish',
			'post_type'   => 'download',
		] );
	}

	protected function getTriggerTests(): array {
		return [
			'purchase_product'       => [ 88, null, (object) [ 'id' => 21 ] ],
			'payment_status_changed' => [ 88, 'complete', 'pending' ],
			'customer_created'       => [ 21, [ 'email' => 'customer@example.com' ] ],
			'customer_updated'       => [ true, 21, [ 'email' => 'customer@example.com' ] ],
			'customer_deleted'       => [ 21 ],
			'discount_created'       => [ [ 'code' => 'SAVE10' ], 91 ],
			'discount_updated'       => [ [ 'code' => 'SAVE20' ], 91 ],
			'discount_deleted'       => [ 91 ],
			'download_created'       => [ 55, get_post( 55 ), false ],
			'download_updated'       => [ 55, get_post( 55 ), true ],
			'download_deleted'       => [ 55 ],
			'download_purchased'     => [ 55, 88, 'default', [ 'price' => 29.99 ], 0 ],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_customer'       => [ 'email' => 'customer@example.com', 'name' => 'EDD Customer' ],
			'create_discount'       => [ 'name' => 'Summer Sale', 'code' => 'SAVE10', 'amount' => '10', 'type' => 'percent' ],
			'update_payment_status' => [ 'payment_id' => 88, 'payment_status' => 'edd_payment_complete' ],
			'add_payment_note'      => [ 'payment_id' => 88, 'note' => 'Paid manually' ],
			'create_download'       => [ 'name' => 'Digital Product', 'description' => 'A file', 'price' => '19.99', 'download_status' => 'edd_download_publish' ],
			'update_download'       => [ 'download_id' => 55, 'name' => 'Updated Download', 'price' => '29.99' ],
			'delete_download'       => [ 'download_id' => 55, 'force_delete' => '1' ],
		];
	}

	public function test_trigger_purchase_product_returns_payment_and_customer_ids(): void {
		$result = EasyDigitalDownload::resolve_trigger(
			$this->makeTriggerNode( 'purchase_product' ),
			[ 88, null, (object) [ 'id' => 21 ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 88, $result['payment_id'] );
		$this->assertEquals( 21, $result['customer_id'] );
	}

	public function test_trigger_payment_status_changed_returns_false_without_new_status(): void {
		$result = EasyDigitalDownload::resolve_trigger(
			$this->makeTriggerNode( 'payment_status_changed' ),
			[ 88, '', 'pending' ]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_download_created_returns_false_for_non_download_post(): void {
		$result = EasyDigitalDownload::resolve_trigger(
			$this->makeTriggerNode( 'download_created' ),
			[ 1, get_post( 1 ), false ]
		);

		$this->assertFalse( $result );
	}

	public function test_action_create_customer_returns_customer_payload(): void {
		$result = EasyDigitalDownload::execute_node(
			$this->makeActionNode( 'create_customer', [ 'email' => 'customer@example.com', 'name' => 'EDD Customer' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 321, $result['data']['customer_id'] );
	}

	public function test_action_create_discount_returns_discount_payload(): void {
		$result = EasyDigitalDownload::execute_node(
			$this->makeActionNode( 'create_discount', [ 'name' => 'Summer Sale', 'code' => 'SAVE10', 'amount' => '10', 'type' => 'percent' ] ),
			[]
		);

		$this->assertEquals( 91, $result['data']['discount_id'] );
		$this->assertEquals( 'SAVE10', $result['data']['discount_code'] );
	}

	public function test_action_update_payment_status_returns_error_without_payment_id(): void {
		$result = EasyDigitalDownload::execute_node(
			$this->makeActionNode( 'update_payment_status', [ 'payment_status' => 'edd_payment_complete' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'Payment ID and status are required', $result['data']['error'] );
	}

	public function test_action_delete_download_returns_error_for_missing_post(): void {
		$result = EasyDigitalDownload::execute_node(
			$this->makeActionNode( 'delete_download', [ 'download_id' => 9999 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'Product not found', $result['data']['error'] );
	}
}
}
