<?php

declare(strict_types=1);

namespace AIArmada\References;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ReferencesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('references')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }
}
