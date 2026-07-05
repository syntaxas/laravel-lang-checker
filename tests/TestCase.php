<?php

namespace Syntaxas\LaravelLangChecker\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Syntaxas\LaravelLangChecker\LaravelLangCheckerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelLangCheckerServiceProvider::class,
        ];
    }
}
