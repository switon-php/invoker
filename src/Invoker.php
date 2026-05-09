<?php

declare(strict_types=1);

namespace Switon\Invoking;

use ReflectionAttribute;
use ReflectionMethod;
use Switon\Di\Invoker as BaseInvoker;
use Switon\Invocation\Attribute\GuardInterface;
use Switon\Invocation\Attribute\InterceptorInterface;

/**
 * Invokes callables with argument values from explicit input, defaults, and DI.
 *
 * Guidance: Use this only when interceptor and guard attributes must run.
 *
 * @see \Switon\Invocation\Attribute\GuardInterface
 * @see \Switon\Invocation\Attribute\InterceptorInterface
 */
class Invoker extends BaseInvoker implements InvokerInterface
{
    public function invoke(callable $callable, array $parameters = []): mixed
    {
        $rFunction = $this->reflectCallable($callable);
        if (!$rFunction instanceof ReflectionMethod || $rFunction->getName() === '__construct') {
            return parent::invoke($callable, $parameters);
        }

        $guards = $this->getInvocationGuards($rFunction);
        $interceptors = $this->getInterceptors($rFunction);

        foreach ($guards as $guard) {
            parent::invoke([$guard, 'handle'], [
                ReflectionMethod::class => $rFunction,
            ]);
        }

        foreach ($interceptors as $interceptor) {
            $interceptor->preHandle($rFunction);
        }

        try {
            $return = parent::invoke($callable, $parameters);
            foreach ($interceptors as $interceptor) {
                $interceptor->postHandle($rFunction, $return);
            }
            return $return;
        } catch (\Throwable $e) {
            foreach (array_reverse($interceptors) as $interceptor) {
                $interceptor->onException($rFunction, $e);
            }
            throw $e;
        }
    }

    /**
     * @return list<GuardInterface>
     */
    protected function getInvocationGuards(ReflectionMethod $rMethod): array
    {
        $guards = [];

        $attributes = $rMethod->getAttributes(GuardInterface::class, ReflectionAttribute::IS_INSTANCEOF);
        foreach ($attributes as $attribute) {
            $guards[] = $this->container->make($attribute->getName(), $attribute->getArguments());
        }

        return $guards;
    }

    /**
     * @return list<InterceptorInterface>
     */
    protected function getInterceptors(ReflectionMethod $rMethod): array
    {
        $interceptors = [];

        $attributes = $rMethod->getAttributes(InterceptorInterface::class, ReflectionAttribute::IS_INSTANCEOF);
        foreach ($attributes as $attribute) {
            $interceptors[] = $this->container->make($attribute->getName(), $attribute->getArguments());
        }

        return $interceptors;
    }
}
