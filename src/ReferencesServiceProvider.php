<?php

declare(strict_types=1);

namespace AIArmada\References;

use AIArmada\References\Models\Reference;
use AIArmada\References\Policies\ReferencePolicy;
use Illuminate\Support\Facades\Gate;
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

    public function bootingPackage(): void
    {
        Gate::policy(Reference::class, ReferencePolicy::class);
    }
}
