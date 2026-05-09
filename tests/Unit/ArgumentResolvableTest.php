<?php

declare(strict_types=1);

namespace Switon\Invoking\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Container\ContainerInterface;
use Switon\Binding\ArgumentResolvable;
use Switon\Invoking\Tests\TestCase;

/**
 * Test cases for ArgumentResolvable interface.
 *
 * @group invoker
 */
#[AllowMockObjectsWithoutExpectations]
class ArgumentResolvableTest extends TestCase
{
    /**
     * Test that ArgumentResolvable interface can be implemented.
     */
    public function testArgumentResolvableInterfaceCanBeImplemented(): void
    {
        // Create a test class that implements ArgumentResolvable
        $testClass = new class implements ArgumentResolvable {
            public static function argumentResolve(ContainerInterface $container): mixed
            {
                return 'resolved-value';
            }
        };

        // Assert that the class implements the interface
        $this->assertInstanceOf(ArgumentResolvable::class, $testClass);
        $this->assertTrue(is_subclass_of($testClass, ArgumentResolvable::class));
    }

    /**
     * Test that argumentResolve method can be called.
     */
    public function testArgumentResolveMethodCanBeCalled(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $testClass = new class implements ArgumentResolvable {
            public static function argumentResolve(ContainerInterface $container): mixed
            {
                return 'test-result';
            }
        };

        // Call the static method
        $result = $testClass::argumentResolve($container);

        $this->assertEquals('test-result', $result);
    }

    /**
     * Test that argumentResolve receives the container parameter.
     */
    public function testArgumentResolveReceivesContainerParameter(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('test-service')
            ->willReturn('service-instance');

        $testClass = new class implements ArgumentResolvable {
            public static function argumentResolve(ContainerInterface $container): mixed
            {
                return $container->get('test-service');
            }
        };

        $result = $testClass::argumentResolve($container);

        $this->assertEquals('service-instance', $result);
    }

    /**
     * Test that argumentResolve can return different types.
     */
    public function testArgumentResolveCanReturnDifferentTypes(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        // Test returning string
        $stringClass = new class implements ArgumentResolvable {
            public static function argumentResolve(ContainerInterface $container): mixed
            {
                return 'string-value';
            }
        };
        $this->assertIsString($stringClass::argumentResolve($container));

        // Test returning array
        $arrayClass = new class implements ArgumentResolvable {
            public static function argumentResolve(ContainerInterface $container): mixed
            {
                return ['key' => 'value'];
            }
        };
        $this->assertIsArray($arrayClass::argumentResolve($container));

        // Test returning object
        $objectClass = new class implements ArgumentResolvable {
            public static function argumentResolve(ContainerInterface $container): mixed
            {
                return new \stdClass();
            }
        };
        $this->assertIsObject($objectClass::argumentResolve($container));
    }
}
