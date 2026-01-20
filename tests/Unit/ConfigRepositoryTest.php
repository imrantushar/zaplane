<?php

namespace Zaplane\Tests\Unit;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Config\Repository;

class ConfigRepositoryTest extends TestCase
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

    public function testGetAndSet(): void
    {
        $config = Config::getInstance();
        $repo = new Repository($config, 'mymodule');

        $repo->set('key', 'value');
        $this->assertEquals('value', $repo->get('key'));

        // Verify it's stored in the main config
        $this->assertEquals('value', $config->get('mymodule.key'));
    }

    public function testHas(): void
    {
        $config = Config::getInstance();
        $repo = new Repository($config, 'mymodule');

        $repo->set('exists', true);

        $this->assertTrue($repo->has('exists'));
        $this->assertFalse($repo->has('not.exists'));
    }

    public function testForget(): void
    {
        $config = Config::getInstance();
        $repo = new Repository($config, 'mymodule');

        $repo->set('to.forget', 'value');
        $this->assertTrue($repo->has('to.forget'));

        $repo->forget('to.forget');
        $this->assertFalse($repo->has('to.forget'));
    }

    public function testAll(): void
    {
        $config = Config::getInstance();
        $config->set('mymodule', [
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $repo = new Repository($config, 'mymodule');
        $all = $repo->all();

        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $all);
    }

    public function testMerge(): void
    {
        $config = Config::getInstance();
        $config->set('mymodule', ['existing' => 'value']);

        $repo = new Repository($config, 'mymodule');
        $repo->merge(['new' => 'merged']);

        $this->assertEquals('value', $repo->get('existing'));
        $this->assertEquals('merged', $repo->get('new'));
    }

    public function testGetNamespace(): void
    {
        $config = Config::getInstance();
        $repo = new Repository($config, 'mymodule');

        $this->assertEquals('mymodule', $repo->getNamespace());
    }

    public function testChild(): void
    {
        $config = Config::getInstance();
        $repo = new Repository($config, 'parent');

        $child = $repo->child('child');
        $child->set('key', 'nested_value');

        $this->assertEquals('nested_value', $config->get('parent.child.key'));
    }
}
