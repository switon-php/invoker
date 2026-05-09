# Switon Invoker Package

Method invocation orchestration for Switon Framework.

## Installation

```bash
composer require switon/invoker
```

**Requirements:** PHP 8.3+

## Quick Start

```php
use ReflectionMethod;
use Switon\Binding\ArgumentsBinderInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Invoking\InvokerInterface;

class Dispatcher
{
    #[Autowired] protected ArgumentsBinderInterface $argumentsBinder;
    #[Autowired] protected InvokerInterface $invoker;

    public function dispatch(object $controller, string $action): mixed
    {
        $method = new ReflectionMethod($controller, $action);
        $arguments = $this->argumentsBinder->resolve($method);

        return $this->invoker->invoke([$controller, $action], $arguments);
    }
}

class PostController
{
    public function showAction(int $id): array
    {
        return ['id' => $id];
    }
}
```

Docs: https://docs.switon.dev/latest/invoker

This package owns the framework invoker contract:
`Switon\Invoking\InvokerInterface`

Invocation hooks now live in `switon/invocation`.

## License

MIT.
