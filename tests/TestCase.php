<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $sqliteConfig = $app['config']->get('database.connections.sqlite');
        if (is_array($sqliteConfig)) {
            $app['config']->set('database.connections.mysql', $sqliteConfig);
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $sqliteConfig = config('database.connections.sqlite');
        if (is_array($sqliteConfig)) {
            config(['database.connections.mysql' => $sqliteConfig]);
            DB::purge('mysql');
        }
    }
}
