<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Logging\LogLevel;

class LogLevelTest extends TestCase
{
    public function testAllLevels(): void
    {
        $levels = LogLevel::all();

        $this->assertContains('debug', $levels);
        $this->assertContains('info', $levels);
        $this->assertContains('notice', $levels);
        $this->assertContains('warning', $levels);
        $this->assertContains('error', $levels);
        $this->assertContains('critical', $levels);
        $this->assertContains('alert', $levels);
        $this->assertContains('emergency', $levels);
    }

    public function testIsValid(): void
    {
        $this->assertTrue(LogLevel::isValid('debug'));
        $this->assertTrue(LogLevel::isValid('error'));
        $this->assertFalse(LogLevel::isValid('invalid'));
    }

    public function testPriority(): void
    {
        $this->assertEquals(0, LogLevel::priority('debug'));
        $this->assertEquals(1, LogLevel::priority('info'));
        $this->assertEquals(4, LogLevel::priority('error'));
        $this->assertEquals(7, LogLevel::priority('emergency'));
    }

    public function testMeetsThreshold(): void
    {
        // Error meets error threshold
        $this->assertTrue(LogLevel::meetsThreshold('error', 'error'));

        // Emergency meets error threshold
        $this->assertTrue(LogLevel::meetsThreshold('emergency', 'error'));

        // Debug does not meet error threshold
        $this->assertFalse(LogLevel::meetsThreshold('debug', 'error'));

        // Info does not meet warning threshold
        $this->assertFalse(LogLevel::meetsThreshold('info', 'warning'));

        // Warning meets warning threshold
        $this->assertTrue(LogLevel::meetsThreshold('warning', 'warning'));
    }

    public function testLevelConstants(): void
    {
        $this->assertEquals('emergency', LogLevel::EMERGENCY);
        $this->assertEquals('alert', LogLevel::ALERT);
        $this->assertEquals('critical', LogLevel::CRITICAL);
        $this->assertEquals('error', LogLevel::ERROR);
        $this->assertEquals('warning', LogLevel::WARNING);
        $this->assertEquals('notice', LogLevel::NOTICE);
        $this->assertEquals('info', LogLevel::INFO);
        $this->assertEquals('debug', LogLevel::DEBUG);
    }
}
