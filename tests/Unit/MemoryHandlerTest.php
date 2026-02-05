<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Logging\LogLevel;
use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\Handlers\MemoryHandler;

class MemoryHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $handler = new MemoryHandler();

        $entry = new LogEntry(LogLevel::INFO, 'Test message');
        $result = $handler->handle($entry);

        $this->assertTrue($result);
        $this->assertCount(1, $handler->getEntries());
    }

    public function testIsHandling(): void
    {
        $handler = new MemoryHandler(LogLevel::WARNING);

        $this->assertFalse($handler->isHandling(LogLevel::DEBUG));
        $this->assertFalse($handler->isHandling(LogLevel::INFO));
        $this->assertTrue($handler->isHandling(LogLevel::WARNING));
        $this->assertTrue($handler->isHandling(LogLevel::ERROR));
    }

    public function testMinLevelFiltering(): void
    {
        $handler = new MemoryHandler(LogLevel::ERROR);

        $debugEntry = new LogEntry(LogLevel::DEBUG, 'Debug');
        $errorEntry = new LogEntry(LogLevel::ERROR, 'Error');

        $handler->handle($debugEntry);
        $handler->handle($errorEntry);

        $this->assertCount(1, $handler->getEntries());
    }

    public function testGetEntriesByLevel(): void
    {
        $handler = new MemoryHandler();

        $handler->handle(new LogEntry(LogLevel::INFO, 'Info 1'));
        $handler->handle(new LogEntry(LogLevel::ERROR, 'Error 1'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Info 2'));

        $infoEntries = $handler->getEntriesByLevel(LogLevel::INFO);
        $this->assertCount(2, $infoEntries);

        $errorEntries = $handler->getEntriesByLevel(LogLevel::ERROR);
        $this->assertCount(1, $errorEntries);
    }

    public function testGetEntriesByChannel(): void
    {
        $handler = new MemoryHandler();

        $handler->handle(new LogEntry(LogLevel::INFO, 'Message', [], 'workflow'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Message', [], 'api'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Message', [], 'workflow'));

        $workflowEntries = $handler->getEntriesByChannel('workflow');
        $this->assertCount(2, $workflowEntries);
    }

    public function testGetEntriesByTraceId(): void
    {
        $handler = new MemoryHandler();

        $handler->handle(new LogEntry(LogLevel::INFO, 'Message', [], 'default', 'trace1'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Message', [], 'default', 'trace2'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Message', [], 'default', 'trace1'));

        $entries = $handler->getEntriesByTraceId('trace1');
        $this->assertCount(2, $entries);
    }

    public function testSearch(): void
    {
        $handler = new MemoryHandler();

        $handler->handle(new LogEntry(LogLevel::INFO, 'User logged in'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Order created'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'User logged out'));

        $results = $handler->search('User');
        $this->assertCount(2, $results);

        $results = $handler->search('Order');
        $this->assertCount(1, $results);
    }

    public function testClear(): void
    {
        $handler = new MemoryHandler();

        $handler->handle(new LogEntry(LogLevel::INFO, 'Test'));
        $this->assertCount(1, $handler->getEntries());

        $handler->clear();
        $this->assertCount(0, $handler->getEntries());
    }

    public function testCount(): void
    {
        $handler = new MemoryHandler();

        $this->assertEquals(0, $handler->count());

        $handler->handle(new LogEntry(LogLevel::INFO, 'Test 1'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Test 2'));

        $this->assertEquals(2, $handler->count());
    }

    public function testLast(): void
    {
        $handler = new MemoryHandler();

        $this->assertNull($handler->last());

        $handler->handle(new LogEntry(LogLevel::INFO, 'First'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Last'));

        $this->assertEquals('Last', $handler->last()->getMessage());
    }

    public function testHasErrors(): void
    {
        $handler = new MemoryHandler();

        $handler->handle(new LogEntry(LogLevel::INFO, 'Info'));
        $this->assertFalse($handler->hasErrors());

        $handler->handle(new LogEntry(LogLevel::ERROR, 'Error'));
        $this->assertTrue($handler->hasErrors());
    }

    public function testMaxEntries(): void
    {
        $handler = new MemoryHandler(LogLevel::DEBUG, 3);

        $handler->handle(new LogEntry(LogLevel::INFO, 'Entry 1'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Entry 2'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Entry 3'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Entry 4'));

        $this->assertCount(3, $handler->getEntries());

        // Should have the last 3 entries
        $entries = array_values($handler->getEntries());
        $this->assertEquals('Entry 2', $entries[0]->getMessage());
        $this->assertEquals('Entry 4', $entries[2]->getMessage());
    }

    public function testToArray(): void
    {
        $handler = new MemoryHandler();

        $handler->handle(new LogEntry(LogLevel::INFO, 'Test 1'));
        $handler->handle(new LogEntry(LogLevel::INFO, 'Test 2'));

        $array = $handler->toArray();

        $this->assertCount(2, $array);
        $this->assertIsArray($array[0]);
        $this->assertEquals('Test 1', $array[0]['message']);
    }
}
