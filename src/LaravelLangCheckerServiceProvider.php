<?php

namespace Syntaxas\LaravelLangChecker;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syntaxas\LaravelLangChecker\Commands\LanguageCheckCommand;

class LaravelLangCheckerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-lang-checker')
            ->hasCommand(LanguageCheckCommand::class);
    }
}
