<?php

declare(strict_types=1);

namespace Horizon\Tests\Unit\Config;

use Horizon\Config\ConfigurationException;
use Horizon\Config\Repository;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    protected Repository $config;

    protected function setUp(): void
    {
        $this->config = new Repository([
            'app' => [
                'name' => 'Horizon',
                'debug' => true,
                'nested' => [
                    'value' => 'test'
                ]
            ],
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'host' => 'localhost',
                        'port' => 3306
                    ]
                ]
            ]
        ]);
    }

    public function test_can_get_configuration_values(): void
    {
        $this->assertEquals('Horizon', $this->config->get('app.name'));
        $this->assertTrue($this->config->get('app.debug'));
        $this->assertEquals('test', $this->config->get('app.nested.value'));
    }

    public function test_can_get_default_values(): void
    {
        $this->assertEquals('default', $this->config->get('nonexistent', 'default'));
        $this->assertNull($this->config->get('nonexistent'));
    }

    public function test_can_set_configuration_values(): void
    {
        $this->config->set('new.value', 'test');
        $this->assertEquals('test', $this->config->get('new.value'));
        
        $this->config->set('app.name', 'New Name');
        $this->assertEquals('New Name', $this->config->get('app.name'));
    }

    public function test_can_check_if_configuration_exists(): void
    {
        $this->assertTrue($this->config->has('app.name'));
        $this->assertTrue($this->config->has('app.nested.value'));
        $this->assertFalse($this->config->has('nonexistent'));
    }

    public function test_can_get_all_configuration(): void
    {
        $all = $this->config->all();
        
        $this->assertArrayHasKey('app', $all);
        $this->assertArrayHasKey('database', $all);
        $this->assertEquals('Horizon', $all['app']['name']);
    }

    public function test_can_prepend_values_to_array(): void
    {
        $this->config->set('test.array', ['b', 'c']);
        $this->config->prepend('test.array', 'a');
        
        $this->assertEquals(['a', 'b', 'c'], $this->config->get('test.array'));
    }

    public function test_can_push_values_to_array(): void
    {
        $this->config->set('test.array', ['a', 'b']);
        $this->config->push('test.array', 'c');
        
        $this->assertEquals(['a', 'b', 'c'], $this->config->get('test.array'));
    }

    public function test_required_throws_exception_for_missing_values(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Required configuration key [missing] is not set');
        
        $this->config->required('missing');
    }

    public function test_required_returns_existing_values(): void
    {
        $this->assertEquals('Horizon', $this->config->required('app.name'));
    }

    public function test_array_access(): void
    {
        // Test offsetExists
        $this->assertTrue(isset($this->config['app.name']));
        $this->assertFalse(isset($this->config['nonexistent']));
        
        // Test offsetGet
        $this->assertEquals('Horizon', $this->config['app.name']);
        
        // Test offsetSet
        $this->config['new.key'] = 'new value';
        $this->assertEquals('new value', $this->config['new.key']);
        
        // Test offsetUnset
        unset($this->config['app.debug']);
        $this->assertNull($this->config->get('app.debug'));
    }
}