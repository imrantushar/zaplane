<?php

namespace Zaplane\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Zaplane\Framework\Database\ORM\Schema;

abstract class TestCase extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		unset( $GLOBALS['zaplane_test_app_password_uuid'], $GLOBALS['zaplane_test_caps'] );
		WPMocks::reset();
		Schema::resetPrefix();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['zaplane_test_app_password_uuid'], $GLOBALS['zaplane_test_caps'] );
		WPMocks::reset();
		Schema::resetPrefix();
		parent::tearDown();
	}
}
