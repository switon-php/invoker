<?php

declare(strict_types=1);

namespace Switon\Invoking;

use Switon\Core\ContainerInterface;
use Switon\Core\InjectorInterface;
use Switon\Core\ServiceProviderInterface;
use Switon\Di\InvokerInterface as BaseInvokerInterface;

/**
 * Wires the enhanced invoker into the container.
 *
 * @see \Switon\Core\ServiceProviderInterface
 * @see \Switon\Invoking\Invoker
 */
class ServiceProvider implements ServiceProviderInterface
{
    /** {@inheritDoc} */
    public function register(ContainerInterface $container): void
    {
        if (!$container->has(InjectorInterface::class)) {
            return;
        }

        $injector = $container->get(InjectorInterface::class);
        $invoker = new Invoker();
        $injector->inject($invoker);

        $container->replace(BaseInvokerInterface::class, $invoker);
        $container->replace(InvokerInterface::class, $invoker);
    }

    /** {@inheritDoc} */
    public function boot(): void
    {
    }
}
