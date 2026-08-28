<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: this box runs against the live production database, and
     * config is normally cached (which makes phpunit.xml's <env> overrides
     * silently ignored). RefreshDatabase migrates/wipes whatever it's
     * pointed at, so refuse to run unless the connected schema is clearly a
     * throwaway test database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_test')) {
            $this->fail(
                "Refusing to run tests against database '{$database}': expected a *_test schema. "
                . 'Run `php artisan config:clear` (cached config overrides phpunit.xml) and check phpunit.xml.'
            );
        }
    }
}
