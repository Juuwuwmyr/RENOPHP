<?php

declare(strict_types=1);

namespace Horizon\Tests\Unit\Container;

use Horizon\Container\Container;
use Horizon\Container\Exceptions\BindingResolutionException;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_container_can_bind_and_resolve_concrete_classes(): void
    {
        $this->container->bind(TestClass::class);
        
        $instance = $this->container->make(TestClass::class);
        
        $this->assertInstanceOf(TestClass::class, $instance);
    }

    public function test_container_can_bind_singletons(): void
    {
        $this->container->singleton(TestClass::class);
        
        $instance1 = $this->container->make(TestClass::class);
        $instance2 = $this->container->make(TestClass::class);
        
        $this->assertSame($instance1, $instance2);
    }

    public function test_container_can_bind_interfaces_to_implementations(): void
    {
        $this->container->bind(TestInterface::class, TestImplementation::class);
        
        $instance = $this->container->make(TestInterface::class);
        
        $this->assertInstanceOf(TestImplementation::class, $instance);
    }

    public function test_container_can_resolve_dependencies(): void
    {
        $this->container->bind(TestInterface::class, TestImplementation::class);
        
        $instance = $this->container->make(TestClassWithDependency::class);
        
        $this->assertInstanceOf(TestClassWithDependency::class, $instance);
        $this->assertInstanceOf(TestImplementation::class, $instance->dependency);
    }

    public function test_container_throws_exception_for_unresolvable_dependencies(): void
    {
        $this->expectException(BindingResolutionException::class);
        
        $this->container->make(TestClassWithUnresolvableDependency::class);
    }

    public function test_container_can_bind_closures(): void
    {
        $this->container->bind(TestClass::class, function () {
            return new TestClass('custom');
        });
        
        $instance = $this->container->make(TestClass::class);
        
        $this->assertEquals('custom', $instance->value);
    }

    public function test_container_can_bind_instances(): void
    {
        $instance = new TestClass('instance');
        $this->container->instance(TestClass::class, $instance);
        
        $resolved = $this->container->make(TestClass::class);
        
        $this->assertSame($instance, $resolved);
    }

    public function test_container_can_tag_services(): void
    {
        $this->container->bind(TestClass::class);
        $this->container->bind(TestImplementation::class);
        $this->container->tag([TestClass::class, TestImplementation::class], 'test-services');
        
        $services = iterator_to_array($this->container->tagged('test-services'));
        
        $this->assertCount(2, $services);
        $this->assertInstanceOf(TestClass::class, $services[0]);
        $this->assertInstanceOf(TestImplementation::class, $services[1]);
    }

    public function test_container_contextual_binding(): void
    {
        $this->container->bind(TestInterface::class, TestImplementation::class);
        $this->container->when(TestClassWithDependency::class)
            ->needs(TestInterface::class)
            ->give(AnotherTestImplementation::class);
        
        // Regular resolution should use the default binding
        $regularInstance = $this->container->make(TestInterface::class);
        $this->assertInstanceOf(TestImplementation::class, $regularInstance);
        
        // Contextual resolution should use the contextual binding
        $contextualInstance = $this->container->make(TestClassWithDependency::class);
        $this->assertInstanceOf(AnotherTestImplementation::class, $contextualInstance->dependency);
    }
}

// Test classes
interface TestInterface {}

class TestClass
{
    public function __construct(public string $value = 'default') {}
}

class TestImplementation implements TestInterface {}

class AnotherTestImplementation implements TestInterface {}

class TestClassWithDependency
{
    public function __construct(public TestInterface $dependency) {}
}

class TestClassWithUnresolvableDependency
{
    public function __construct(string $unresolvable) {}
}