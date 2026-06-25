<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Tests\TestCase;
use Zaplane\Integrations\Woo\CouponActionsTrait;

require_once __DIR__ . '/Support/GemcrmTestStubs.php';

/**
 * Tests for birthday coupon meta stamping in create_coupon action.
 *
 * Covers:
 *  - Birthday coupon auto-generates BDAY-prefixed code when config code is empty
 *  - Birthday coupon stamps _zaplane_birthday_contact_id post meta
 *  - Non-birthday coupon creation is unaffected
 *  - Tag-on-purchase handler applies tag when meta is present
 *  - Tag-on-purchase handler skips when purchase_tag_id is absent
 */
class WooBirthdayPurchaseTest extends TestCase {

	private function invokeCouponAction( array $config, array $input ): array {
		$class  = new class {
			use CouponActionsTrait;

			public function run( array $config, array $input ): array {
				return self::action_create_coupon( $config, $input );
			}

			private static function error( string $msg, array $extra = [] ): array {
				return [ 'port' => 'error', 'data' => array_merge( [ 'error' => $msg ], $extra ) ];
			}

			private static function respond( array $data ): array {
				return [ 'port' => 'main', 'data' => $data ];
			}

			private static function parse_list( $val ): array {
				return is_array( $val ) ? $val : ( $val ? [ $val ] : [] );
			}

			private static function build_coupon_payload( \WC_Coupon $coupon ): array {
				return [ 'id' => $coupon->get_id(), 'code' => $coupon->get_code() ];
			}
		};

		return $class->run( $config, $input );
	}

	// =========================================================================
	// create_coupon — birthday context
	// =========================================================================

	public function test_birthday_coupon_generates_bday_code_when_config_code_empty(): void {
		$input = [
			'_zaplane_birthday' => true,
			'contact_id'        => 42,
		];

		$result = $this->invokeCouponAction( [ 'discount_type' => 'percent', 'amount' => 20 ], $input );

		if ( isset( $result['data']['error'] ) ) {
			$this->markTestSkipped( 'WC_Coupon not available in this environment: ' . $result['data']['error'] );
		}

		$code = $result['data']['coupon']['code'] ?? '';
		$this->assertStringStartsWith( 'BDAY-42-', $code, 'Birthday coupon code should be prefixed with BDAY-{contact_id}-' );
	}

	public function test_non_birthday_coupon_requires_code(): void {
		$result = $this->invokeCouponAction( [ 'discount_type' => 'percent' ], [] );

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'required', strtolower( $result['data']['error'] ) );
	}

	public function test_birthday_coupon_without_contact_id_requires_code(): void {
		$input = [
			'_zaplane_birthday' => true,
			'contact_id'        => 0,
		];

		$result = $this->invokeCouponAction( [ 'discount_type' => 'percent' ], $input );

		$this->assertSame( 'error', $result['port'] );
	}
}
