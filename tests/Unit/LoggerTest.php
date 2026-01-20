<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Logging\Logger;
use Zaplane\Framework\Logging\LogLevel;
use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\Handlers\MemoryHandler;

class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::resetInstance();
        Logger::resetInstance();
        // Enable logging for tests
        Config::getInstance()->set('logging.enabled', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Logger::resetInstance();
        Config::resetInstance();
    }

    public function testGetInstance(): void
    {
        $logger = Logger::getInstance();
        $this->assertInstanceOf(Logger::class, $logger);

        // Test singleton
        $logger2 = Logger::getInstance();
        $this->assertSame($logger, $logger2);
    }

    public function testLogLevels(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->notice('Notice message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        $logger->critical('Critical message');
        $logger->alert('Alert message');
        $logger->emergency('Emergency message');

        $this->assertCount(8, $handler->getEntries());
    }

    public function testLogWithContext(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->info('User logged in', ['user_id' => 123, 'ip' => '192.168.1.1']);

        $entry = $handler->last();
        $this->assertEquals('User logged in', $entry->getMessage());
        $this->assertEquals(['user_id' => 123, 'ip' => '192.168.1.1'], $entry->getContext());
    }

    public function testChannel(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->channel('workflow')->info('Workflow started');

        $entry = $handler->last();
        $this->assertEquals('workflow', $entry->getChannel());
    }

    public function testTraceId(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $traceId = 'abc123';
        $logger->withTraceId($traceId)->info('Traced message');

        $entry = $handler->last();
        $this->assertEquals($traceId, $entry->getTraceId());
    }

    public function testGenerateTraceId(): void
    {
        $logger = Logger::getInstance();
        $traceId = $logger->generateTraceId();

        $this->assertIsString($traceId);
        $this->assertEquals(32, strlen($traceId)); // 16 bytes = 32 hex chars
    }

    public function testGlobalContext(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $loggerWithContext = $logger->withContext(['request_id' => 'req123']);
        $loggerWithContext->info('Message');

        $entry = $handler->last();
        $this->assertArrayHasKey('request_id', $entry->getContext());
    }

    public function testDisableEnable(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->disable();
        $logger->info('Should not be logged');
        $this->assertCount(0, $handler->getEntries());

        $logger->enable();
        $logger->info('Should be logged');
        $this->assertCount(1, $handler->getEntries());
    }

    public function testMinLevel(): void
    {
        $handler = new MemoryHandler(LogLevel::WARNING);
        $logger = Logger::getInstance();
        $logger->setMinLevel(LogLevel::WARNING);
        $logger->pushHandler($handler);

        $logger->debug('Should not be logged');
        $logger->info('Should not be logged');
        $logger->warning('Should be logged');
        $logger->error('Should be logged');

        $this->assertCount(2, $handler->getEntries());
    }

    public function testException(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $exception = new \RuntimeException('Test exception', 500);
        $logger->exception($exception);

        $entry = $handler->last();
        $context = $entry->getContext();

        $this->assertEquals('RuntimeException', $context['exception']);
        $this->assertEquals('Test exception', $context['message']);
        $this->assertEquals(500, $context['code']);
    }

    public function testWorkflowLogging(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->workflow(123, 'Workflow executed');

        $entry = $handler->last();
        $this->assertEquals('workflow', $entry->getChannel());
        $this->assertEquals(123, $entry->getContext()['workflow_id']);
    }

    public function testNodeLogging(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->node('node-1', 'Node executed');

        $entry = $handler->last();
        $this->assertEquals('node', $entry->getChannel());
        $this->assertEquals('node-1', $entry->getContext()['node_id']);
    }

    public function testIntegrationLogging(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->integration('mailerlite', 'Subscriber added');

        $entry = $handler->last();
        $this->assertEquals('integration', $entry->getChannel());
        $this->assertEquals('mailerlite', $entry->getContext()['integration']);
    }

    public function testApiLogging(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->api('GET', '/workflows', ['status' => 200]);

        $entry = $handler->last();
        $this->assertEquals('api', $entry->getChannel());
        $this->assertEquals('GET', $entry->getContext()['method']);
        $this->assertEquals('/workflows', $entry->getContext()['endpoint']);
    }

    public function testTiming(): void
    {
        $handler = new MemoryHandler();
        $logger = Logger::getInstance();
        $logger->pushHandler($handler);

        $logger->startTiming('operation');
        usleep(10000); // 10ms
        $duration = $logger->endTiming('operation');

        $this->assertGreaterThan(0, $duration);

        $entry = $handler->last();
        $this->assertArrayHasKey('duration_ms', $entry->getContext());
    }
}
