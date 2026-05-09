<?php

declare(strict_types=1);

namespace Switon\Invoking\Tests;

use Switon\Testing\TestCase as BaseTestCase;

/**
 * Base test case for Invoker tests.
 *
 * Delegates container setup to Switon\Testing\TestCase, so that all
 * DI wiring (including InjectorInterface and InvokerInterface) is
 * configured centrally in the testing package.
 */
abstract class TestCase extends BaseTestCase
{
}

