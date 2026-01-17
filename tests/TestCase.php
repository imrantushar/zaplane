<?php

namespace Zaplane\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WPMocks::reset();
    }

    protected function tearDown(): void
    {
        WPMocks::reset();
        parent::tearDown();
    }
}
