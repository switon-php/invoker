<?php

declare(strict_types=1);

namespace Switon\Invoking\Tests\Unit;

use Attribute;
use ReflectionMethod;
use Switon\Invocation\Attribute\GuardInterface;
use Switon\Invocation\Attribute\InterceptorInterface;
use Switon\Di\Exception\MissingConfigurationException;
use Switon\Di\Exception\MissingTypeDeclarationException;
use Switon\Di\Exception\ServiceInjectionException;
use Switon\Invoking\InvokerInterface;
use Switon\Invoking\Tests\Fixtures\TestDependency;
use Switon\Invoking\Tests\Fixtures\TestInterceptor;
use Switon\Invoking\Tests\Fixtures\TestService;
use Switon\Invoking\Tests\TestCase;

/**
 * Test cases for Invoker (callable resolution, parameters, guards, interceptors).
 *
 * @group invoker
 */
class InvokerTest extends TestCase
{
    protected InvokerInterface $invoker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoker = $this->container->get(InvokerInterface::class);
    }

    public function testCallWithArrayCallable(): void
    {
        $service = new class {
            public function method(string $param): string
            {
                return "called with $param";
            }
        };

        $result = $this->invoker->invoke([$service, 'method'], ['param' => 'test']);

        $this->assertSame('called with test', $result);
    }

    public function testCallWithClosure(): void
    {
        $closure = function (string $param): string {
            return "closure with $param";
        };

        $result = $this->invoker->invoke($closure, ['param' => 'test']);

        $this->assertSame('closure with test', $result);
    }

    public function testInvokeUsesDefaultValueWhenParameterMissingOnClosure(): void
    {
        $closure = static fn(string $name = 'world'): string => "Hello, {$name}";

        $result = $this->invoker->invoke($closure);

        $this->assertSame('Hello, world', $result);
    }

    public function testCallWithInvokableObject(): void
    {
        $callable = new class {
            public function __invoke(string $param): string
            {
                return "invokable with $param";
            }
        };

        $result = $this->invoker->invoke($callable, ['param' => 'test']);

        $this->assertSame('invokable with test', $result);
    }

    public function testCallWithPositionalParameters(): void
    {
        $service = new class {
            public function method(string $param1, int $param2): string
            {
                return "$param1:$param2";
            }
        };

        $result = $this->invoker->invoke([$service, 'method'], [0 => 'test', 1 => 42]);

        $this->assertSame('test:42', $result);
    }

    public function testCallWithDefaultValue(): void
    {
        $service = new class {
            public function method(string $param = 'default'): string
            {
                return $param;
            }
        };

        $result = $this->invoker->invoke([$service, 'method'], []);

        $this->assertSame('default', $result);
    }

    public function testCallWithEmptyVariadicParameters(): void
    {
        $callable = new class {
            /**
             * @return list<string>
             */
            public function __invoke(string ...$parts): array
            {
                return $parts;
            }
        };

        $result = $this->invoker->invoke($callable);

        $this->assertSame([], $result);
    }

    public function testCallWithVariadicPositionalParameters(): void
    {
        $callable = new class {
            /**
             * @return list<string>
             */
            public function __invoke(string $first, string ...$rest): array
            {
                return [$first, ...$rest];
            }
        };

        $result = $this->invoker->invoke($callable, [0 => 'alpha', 1 => 'beta', 2 => 'gamma']);

        $this->assertSame(['alpha', 'beta', 'gamma'], $result);
    }

    public function testInvokeResolvesTestDependencyFromContainer(): void
    {
        $this->container->set(TestDependency::class, TestDependency::class);

        $instance = new class {
            public function handle(TestDependency $service): string
            {
                return $service::class;
            }
        };

        $result = $this->invoker->invoke([$instance, 'handle']);

        $this->assertSame(TestDependency::class, $result);
    }

    public function testInvokeAcceptsTypeKeyedTestDependency(): void
    {
        $service = new TestDependency();
        $instance = new class {
            public function handle(TestDependency $service): string
            {
                return $service::class;
            }
        };

        $result = $this->invoker->invoke([$instance, 'handle'], [TestDependency::class => $service]);

        $this->assertSame(TestDependency::class, $result);
    }

    public function testCallWithAutowiredObject(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $service = new class {
            public function method(TestService $service): string
            {
                return 'autowired';
            }
        };

        $result = $this->invoker->invoke([$service, 'method'], []);

        $this->assertSame('autowired', $result);
    }

    public function testCallWithTypeParameter(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $service = new class {
            public function method(TestService $service): string
            {
                return 'type-param';
            }
        };

        $result = $this->invoker->invoke([$service, 'method'], [TestService::class => new TestService()]);

        $this->assertSame('type-param', $result);
    }

    public function testCallWithInvocationGuard(): void
    {
        $this->container->set(TestService::class, TestService::class);

        TestGuard::$calls = 0;
        TestGuard::$methodName = null;
        TestGuard::$resolvedService = null;

        $service = new class {
            #[TestGuard]
            public function guardedMethod(): string
            {
                return 'guarded';
            }
        };

        $result = $this->invoker->invoke([$service, 'guardedMethod']);

        $this->assertSame('guarded', $result);
        $this->assertSame(1, TestGuard::$calls);
        $this->assertSame('guardedMethod', TestGuard::$methodName);
        $this->assertInstanceOf(TestService::class, TestGuard::$resolvedService);
    }

    public function testCallWithInterceptor(): void
    {
        $service = new class {
            #[TestInterceptor]
            public function interceptedMethod(): string
            {
                return 'result';
            }
        };

        $interceptor = new TestInterceptor();
        $this->container->set(TestInterceptor::class, $interceptor);

        $result = $this->invoker->invoke([$service, 'interceptedMethod'], []);

        $this->assertSame('result', $result);
        $this->assertTrue($interceptor->preHandleCalled, 'Interceptor preHandle() should be called');
        $this->assertTrue($interceptor->postHandleCalled, 'Interceptor postHandle() should be called');
        $this->assertSame(1, $interceptor->preHandleCallCount, 'Interceptor should run once for one attribute');
        $this->assertSame(1, $interceptor->postHandleCallCount, 'Interceptor should run once for one attribute');
        $this->assertSame('result', $interceptor->returnValue, 'Interceptor postHandle() should receive method return value');
    }

    public function testCallWithMultipleInterceptors(): void
    {
        $service = new class {
            #[TestInterceptor]
            #[TestInterceptor]
            public function interceptedMethod(): string
            {
                return 'result';
            }
        };

        $interceptor = new TestInterceptor();
        $this->container->set(TestInterceptor::class, $interceptor);

        $result = $this->invoker->invoke([$service, 'interceptedMethod'], []);

        $this->assertSame('result', $result);
        $this->assertSame(2, $interceptor->preHandleCallCount, 'Interceptor should run twice for two attributes');
        $this->assertSame(2, $interceptor->postHandleCallCount, 'Interceptor should run twice for two attributes');
    }

    public function testCallWithInterceptorPostHandleReceivesArrayReturn(): void
    {
        $service = new class {
            #[TestInterceptor]
            public function methodWithResult(): array
            {
                return ['key' => 'value', 'number' => 42];
            }
        };

        $interceptor = new TestInterceptor();
        $this->container->set(TestInterceptor::class, $interceptor);

        $result = $this->invoker->invoke([$service, 'methodWithResult'], []);

        $this->assertSame(['key' => 'value', 'number' => 42], $result);
        $this->assertSame(['key' => 'value', 'number' => 42], $interceptor->returnValue);
    }

    public function testCallWithManyInterceptorAttributesRepeatedInvocations(): void
    {
        $service = new class {
            #[TestInterceptor]
            #[TestInterceptor]
            #[TestInterceptor]
            #[TestInterceptor]
            #[TestInterceptor]
            public function heavilyInterceptedMethod(): string
            {
                return 'result';
            }
        };

        for ($i = 0; $i < 100; $i++) {
            $result = $this->invoker->invoke([$service, 'heavilyInterceptedMethod'], []);
            $this->assertSame('result', $result);
        }
    }

    public function testCallThrowsMissingTypeDeclarationException(): void
    {
        $service = new class {
            public function method($param): void
            {
            }
        };

        $this->expectException(MissingTypeDeclarationException::class);

        $this->invoker->invoke([$service, 'method'], []);
    }

    public function testCallThrowsMissingConfigurationException(): void
    {
        $service = new class {
            public function method(string $param): void
            {
            }
        };

        $this->expectException(MissingConfigurationException::class);

        $this->invoker->invoke([$service, 'method'], []);
    }

    public function testCallThrowsServiceInjectionException(): void
    {
        $service = new class {
            public function method(\NonExistentInterface $service): void
            {
            }
        };

        $this->expectException(ServiceInjectionException::class);

        $this->invoker->invoke([$service, 'method'], []);
    }

    public function testCallWithStringCallable(): void
    {
        $result = $this->invoker->invoke('strlen', ['string' => 'hello']);

        $this->assertSame(5, $result);
    }

    public function testCallWithClosureAndInterceptorAttributes(): void
    {
        $closure = function (string $param): string {
            return "closure: $param";
        };

        $result = $this->invoker->invoke($closure, ['param' => 'test']);

        $this->assertSame('closure: test', $result);
    }

    public function testCallWithBuiltinTypeAndNoDefaultThrowsException(): void
    {
        $service = new class {
            public function method(int $param): void
            {
            }
        };

        $this->expectException(MissingConfigurationException::class);

        $this->invoker->invoke([$service, 'method'], []);
    }

    public function testCallWithTypeParameterOverride(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $service = new class {
            public function method(TestService $service): TestService
            {
                return $service;
            }
        };

        $customService = new TestService();
        $result = $this->invoker->invoke([$service, 'method'], [TestService::class => $customService]);

        $this->assertSame($customService, $result);
    }

    public function testCallWithPositionalAndNamedParameters(): void
    {
        $service = new class {
            public function method(string $param1, int $param2): string
            {
                return "$param1:$param2";
            }
        };

        $result = $this->invoker->invoke([$service, 'method'], [0 => 'test', 'param2' => 42]);

        $this->assertSame('test:42', $result);
    }

    public function testCallWithObjectValueForObjectType(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $service = new class {
            public function method(TestService $service): TestService
            {
                return $service;
            }
        };

        $customService = new TestService();
        $result = $this->invoker->invoke([$service, 'method'], ['service' => $customService]);

        $this->assertSame($customService, $result);
    }

    public function testCallWithStringValueForObjectType(): void
    {
        $this->container->set(TestService::class, TestService::class);

        $service = new class {
            public function method(TestService $service): TestService
            {
                return $service;
            }
        };

        $result = $this->invoker->invoke([$service, 'method'], ['service' => TestService::class]);

        $this->assertInstanceOf(TestService::class, $result);
    }

    public function testInvokePropagatesCallableExceptions(): void
    {
        $instance = new class {
            public function explode(): never
            {
                throw new \RuntimeException('Call failed');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Call failed');

        $this->invoker->invoke([$instance, 'explode']);
    }

    public function testInvokeCallsInterceptorOnExceptionInReverseOrder(): void
    {
        ExceptionalTestInterceptor::$events = [];

        $service = new class {
            #[ExceptionalTestInterceptor('first')]
            #[ExceptionalTestInterceptor('second')]
            public function fail(): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        try {
            $this->invoker->invoke([$service, 'fail']);
        } finally {
            $this->assertSame(
                ['pre:first', 'pre:second', 'ex:second', 'ex:first'],
                ExceptionalTestInterceptor::$events
            );
        }
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
class TestGuard implements GuardInterface
{
    public static int $calls = 0;
    public static ?string $methodName = null;
    public static ?TestService $resolvedService = null;

    public function handle(TestService $service, ReflectionMethod $method): void
    {
        self::$calls++;
        self::$methodName = $method->getName();
        self::$resolvedService = $service;
    }
}

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ExceptionalTestInterceptor implements InterceptorInterface
{
    /** @var list<string> */
    public static array $events = [];

    public function __construct(private readonly string $name)
    {
    }

    public function preHandle(ReflectionMethod $method): bool
    {
        self::$events[] = 'pre:' . $this->name;
        return true;
    }

    public function postHandle(ReflectionMethod $method, mixed &$return): void
    {
    }

    public function onException(ReflectionMethod $method, \Throwable $exception): void
    {
        self::$events[] = 'ex:' . $this->name;
    }
}
