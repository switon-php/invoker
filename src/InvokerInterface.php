<?php

declare(strict_types=1);

namespace Switon\Invoking;

/**
 * Invokes callables with argument values from explicit input, defaults, and DI.
 *
 * @see \Switon\Invoking\Invoker
 * @see \Switon\Invocation\Attribute\GuardInterface
 * @see \Switon\Invocation\Attribute\InterceptorInterface
 */
interface InvokerInterface
{
    /**
     * Invoke a callable, including invokable objects, with resolved parameters.
     *
     * @param callable $callable
     * @param array<int|string, mixed> $parameters Positional, named, type-keyed, or variadic overrides
     * @return mixed Callable return value
     */
    public function invoke(callable $callable, array $parameters = []): mixed;
}
