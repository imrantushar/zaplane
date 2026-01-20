<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Exceptions\ZaplaneException;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testSetAndGetService(): void
    {
        $this->container->set('test_service', fn($c) => new \stdClass());

        $service = $this->container->get('test_service');

        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testGetReturnsSameInstance(): void
    {
        $this->container->set('singleton', fn($c) => new \stdClass());

        $instance1 = $this->container->get('singleton');
        $instance2 = $this->container->get('singleton');

        $this->assertSame($instance1, $instance2);
    }

    public function testGetThrowsExceptionForUnregisteredService(): void
    {
        $this->expectException(ZaplaneException::class);
        $this->expectExceptionMessage('Service unknown_service not registered');

        $this->container->get('unknown_service');
    }

    public function testHasReturnsTrueForRegisteredService(): void
    {
        $this->container->set('existing', fn($c) => 'value');

        $this->assertTrue($this->container->has('existing'));
    }

    public function testHasReturnsFalseForUnregisteredService(): void
    {
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testFactoryReceivesContainer(): void
    {
        $receivedContainer = null;

        $this->container->set('test', function ($c) use (&$receivedContainer) {
            $receivedContainer = $c;
            return 'value';
        });

        $this->container->get('test');

        $this->assertSame($this->container, $receivedContainer);
    }

    public function testDependencyInjection(): void
    {
        $this->container->set('config', fn($c) => ['key' => 'value']);

        $this->container->set('service', function ($c) {
            $config = $c->get('config');
            return (object) ['config' => $config];
        });

        $service = $this->container->get('service');

        $this->assertEquals(['key' => 'value'], $service->config);
    }

    public function testOverwriteService(): void
    {
        $this->container->set('service', fn($c) => 'first');
        $this->container->set('service', fn($c) => 'second');

        $value = $this->container->get('service');

        $this->assertEquals('second', $value);
    }

    public function testFactoryCanReturnScalarValues(): void
    {
        $this->container->set('string', fn($c) => 'hello');
        $this->container->set('number', fn($c) => 42);
        $this->container->set('array', fn($c) => [1, 2, 3]);
        $this->container->set('bool', fn($c) => true);

        $this->assertEquals('hello', $this->container->get('string'));
        $this->assertEquals(42, $this->container->get('number'));
        $this->assertEquals([1, 2, 3], $this->container->get('array'));
        $this->assertTrue($this->container->get('bool'));
    }
}
