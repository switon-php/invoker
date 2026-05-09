<?php

declare(strict_types=1);

namespace Switon\Invoking\Tests\Fixtures;

use ReflectionMethod;
use Switon\Invocation\Attribute\InterceptorInterface;

/**
 * Test interceptor for Invoker attribute/interception coverage.
 */
class TestInterceptor implements InterceptorInterface
{
    public bool $preHandleCalled = false;
    public bool $postHandleCalled = false;
    public int $preHandleCallCount = 0;
    public int $postHandleCallCount = 0;
    public mixed $returnValue = null;

    public function preHandle(ReflectionMethod $method): bool
    {
        $this->preHandleCalled = true;
        $this->preHandleCallCount++;

        return true;
    }

    public function postHandle(ReflectionMethod $method, mixed &$return): void
    {
        $this->postHandleCalled = true;
        $this->postHandleCallCount++;
        $this->returnValue = $return;
    }

    public function onException(ReflectionMethod $method, \Throwable $exception): void
    {
    }
}
