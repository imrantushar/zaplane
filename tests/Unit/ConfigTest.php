<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Config\Repository;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Config::resetInstance();
    }

    public function testGetInstance(): void
    {
        $config = Config::getInstance();
        $this->assertInstanceOf(Config::class, $config);

        // Test singleton
        $config2 = Config::getInstance();
        $this->assertSame($config, $config2);
    }

    public function testGetWithDefault(): void
    {
        $config = Config::getInstance();

        // Existing key
        $this->assertNotNull($config->get('app.name'));

        // Non-existing key with default
        $this->assertEquals('default', $config->get('non.existing.key', 'default'));
    }

    public function testSetAndGet(): void
    {
        $config = Config::getInstance();

        $config->set('test.key', 'value');
        $this->assertEquals('value', $config->get('test.key'));

        // Nested setting
        $config->set('test.nested.deep.key', 'deep_value');
        $this->assertEquals('deep_value', $config->get('test.nested.deep.key'));
    }

    public function testDotNotation(): void
    {
        $config = Config::getInstance();

        $config->set('parent', [
            'child' => [
                'grandchild' => 'value'
            ]
        ]);

        $this->assertEquals('value', $config->get('parent.child.grandchild'));
    }

    public function testHas(): void
    {
        $config = Config::getInstance();

        $config->set('exists', 'yes');

        $this->assertTrue($config->has('exists'));
        $this->assertFalse($config->has('does.not.exist'));
    }

    public function testForget(): void
    {
        $config = Config::getInstance();

        $config->set('to.be.removed', 'value');
        $this->assertTrue($config->has('to.be.removed'));

        $config->forget('to.be.removed');
        $this->assertFalse($config->has('to.be.removed'));
    }

    public function testAll(): void
    {
        $config = Config::getInstance();
        $all = $config->all();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('app', $all);
        $this->assertArrayHasKey('logging', $all);
    }

    public function testMerge(): void
    {
        $config = Config::getInstance();

        $config->merge([
            'custom' => [
                'setting' => 'merged_value'
            ]
        ]);

        $this->assertEquals('merged_value', $config->get('custom.setting'));
    }

    public function testPush(): void
    {
        $config = Config::getInstance();

        $config->set('list', ['item1']);
        $config->push('list', 'item2');

        $list = $config->get('list');
        $this->assertCount(2, $list);
        $this->assertEquals(['item1', 'item2'], $list);
    }

    public function testPrepend(): void
    {
        $config = Config::getInstance();

        $config->set('list', ['item2']);
        $config->prepend('list', 'item1');

        $list = $config->get('list');
        $this->assertEquals(['item1', 'item2'], $list);
    }

    public function testArrayAccess(): void
    {
        $config = Config::getInstance();

        $config['array.access'] = 'works';
        $this->assertEquals('works', $config['array.access']);
        $this->assertTrue(isset($config['array.access']));

        unset($config['array.access']);
        $this->assertFalse(isset($config['array.access']));
    }

    public function testDefaultValues(): void
    {
        $config = Config::getInstance();

        // Check default configuration values exist
        $this->assertNotNull($config->get('app.name'));
        $this->assertNotNull($config->get('logging.enabled'));
        $this->assertNotNull($config->get('database.prefix'));
    }

    public function testEnvironment(): void
    {
        $config = Config::getInstance();

        $env = $config->getEnvironment();
        $this->assertIsString($env);

        $isEnv = $config->isEnvironment($env);
        $this->assertTrue($isEnv);
    }
}
