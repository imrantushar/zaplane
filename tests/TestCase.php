<?php

namespace Zaplane\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Zaplane\Framework\Database\ORM\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WPMocks::reset();
        Schema::resetPrefix();
    }

    protected function tearDown(): void
    {
        WPMocks::reset();
        Schema::resetPrefix();
        parent::tearDown();
    }
}
