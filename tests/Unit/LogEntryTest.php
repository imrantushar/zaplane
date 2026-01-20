<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

class LogEntryTest extends TestCase
{
    public function testBasicEntry(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test message');

        $this->assertEquals(LogLevel::INFO, $entry->getLevel());
        $this->assertEquals('Test message', $entry->getMessage());
        $this->assertEquals([], $entry->getContext());
        $this->assertEquals('default', $entry->getChannel());
    }

    public function testEntryWithContext(): void
    {
        $context = ['user_id' => 123, 'action' => 'login'];
        $entry = new LogEntry(LogLevel::INFO, 'User action', $context);

        $this->assertEquals($context, $entry->getContext());
    }

    public function testEntryWithChannel(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test', [], 'workflow');

        $this->assertEquals('workflow', $entry->getChannel());
    }

    public function testEntryWithTraceId(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test', [], 'default', 'trace123');

        $this->assertEquals('trace123', $entry->getTraceId());
    }

    public function testInterpolatedMessage(): void
    {
        $entry = new LogEntry(
            LogLevel::INFO,
            'User {user_id} performed {action}',
            ['user_id' => 123, 'action' => 'login']
        );

        $this->assertEquals('User 123 performed login', $entry->getInterpolatedMessage());
    }

    public function testInterpolatedMessageWithTypes(): void
    {
        $entry = new LogEntry(
            LogLevel::INFO,
            'Bool: {bool}, Null: {null}, Array: {array}',
            [
                'bool' => true,
                'null' => null,
                'array' => ['a', 'b'],
            ]
        );

        $interpolated = $entry->getInterpolatedMessage();

        $this->assertStringContainsString('Bool: true', $interpolated);
        $this->assertStringContainsString('Null: null', $interpolated);
        $this->assertStringContainsString('Array: ["a","b"]', $interpolated);
    }

    public function testFormat(): void
    {
        $entry = new LogEntry(LogLevel::ERROR, 'Error occurred', ['code' => 500], 'api');

        $formatted = $entry->format('[{timestamp}] {channel}.{level}: {message}');

        $this->assertStringContainsString('API.ERROR', $formatted);
        $this->assertStringContainsString('Error occurred', $formatted);
    }

    public function testToArray(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test', ['key' => 'value'], 'test', 'trace123');
        $entry->addExtra('extra_key', 'extra_value');

        $array = $entry->toArray();

        $this->assertEquals(LogLevel::INFO, $array['level']);
        $this->assertEquals('Test', $array['message']);
        $this->assertEquals(['key' => 'value'], $array['context']);
        $this->assertEquals('test', $array['channel']);
        $this->assertEquals('trace123', $array['trace_id']);
        $this->assertEquals(['extra_key' => 'extra_value'], $array['extra']);
    }

    public function testJsonSerialize(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test');

        $json = json_encode($entry);
        $decoded = json_decode($json, true);

        $this->assertEquals('info', $decoded['level']);
        $this->assertEquals('Test', $decoded['message']);
    }

    public function testToString(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test message', [], 'app');

        $string = (string) $entry;

        $this->assertStringContainsString('APP.INFO', $string);
        $this->assertStringContainsString('Test message', $string);
    }

    public function testAddExtra(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test');

        $entry->addExtra('key1', 'value1');
        $entry->addExtra('key2', 'value2');

        $extra = $entry->getExtra();
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $extra);
    }

    public function testSetExtra(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test');

        $entry->setExtra(['custom' => 'data']);

        $this->assertEquals(['custom' => 'data'], $entry->getExtra());
    }

    public function testTimestamp(): void
    {
        $entry = new LogEntry(LogLevel::INFO, 'Test');

        $timestamp = $entry->getTimestamp();
        $this->assertNotEmpty($timestamp);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $timestamp);
    }
}
