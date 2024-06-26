<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Uc\HttpTrafficLogger\HttpTrafficLoggerServiceProvider;

class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            HttpTrafficLoggerServiceProvider::class,
        ];
    }
}
