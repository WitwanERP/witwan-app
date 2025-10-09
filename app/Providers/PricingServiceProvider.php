<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Pricing\Services\PricingEngine;
use App\Domain\Pricing\Services\TariffFinder;
use App\Domain\Pricing\Services\AvailabilityChecker;
use App\Domain\Pricing\Services\LegacyTariffReplacementService;
use App\Domain\Pricing\Services\ProductSearchService;
use App\Domain\Pricing\Services\PricingContextService;
use App\Domain\Pricing\Repositories\ProductRepositoryInterface;
use App\Domain\Pricing\Repositories\TariffRepositoryInterface;
use App\Domain\Pricing\Repositories\QuotaRepositoryInterface;
use App\Domain\Pricing\Repositories\CommissionRepositoryInterface;
use App\Infrastructure\Pricing\Repositories\EloquentProductRepository;
use App\Infrastructure\Pricing\Repositories\EloquentTariffRepository;
use App\Infrastructure\Pricing\Repositories\EloquentQuotaRepository;
use App\Infrastructure\Pricing\Repositories\EloquentCommissionRepository;

class PricingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar interfaces con sus implementaciones
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(TariffRepositoryInterface::class, EloquentTariffRepository::class);
        $this->app->bind(QuotaRepositoryInterface::class, EloquentQuotaRepository::class);
        $this->app->bind(CommissionRepositoryInterface::class, EloquentCommissionRepository::class);

        // Registrar servicios principales
        $this->app->singleton(PricingService::class, function ($app) {
            return new PricingService();
        });

        $this->app->singleton(TariffFinder::class, function ($app) {
            return new TariffFinder(
                $app->make(TariffRepositoryInterface::class),
                $app->make(ProductRepositoryInterface::class)
            );
        });

        $this->app->singleton(AvailabilityChecker::class, function ($app) {
            return new AvailabilityChecker(
                $app->make(QuotaRepositoryInterface::class),
                $app->make(ProductRepositoryInterface::class)
            );
        });

        $this->app->singleton(PricingEngine::class, function ($app) {
            return new PricingEngine(
                $app->make(TariffFinder::class),
                $app->make(AvailabilityChecker::class),
                $app->make(CommissionRepositoryInterface::class)
            );
        });

        // Servicio principal que reemplaza el método obsoleto
        $this->app->singleton(LegacyTariffReplacementService::class, function ($app) {
            return new LegacyTariffReplacementService(
                $app->make(PricingService::class),
                $app->make(ProductRepositoryInterface::class),
                $app->make(TariffRepositoryInterface::class),
                $app->make(QuotaRepositoryInterface::class),
                $app->make(CommissionRepositoryInterface::class)
            );
        });

        // Servicio de contexto de pricing (markup y moneda)
        $this->app->singleton(PricingContextService::class, function ($app) {
            return new PricingContextService();
        });

        // Servicio de búsqueda de productos con pricing integrado
        $this->app->singleton(ProductSearchService::class, function ($app) {
            return new ProductSearchService(
                $app->make(ProductRepositoryInterface::class),
                $app->make(AvailabilityChecker::class),
                $app->make(PricingContextService::class),
                $app->make(PricingService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configuraciones adicionales si son necesarias
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ProductRepositoryInterface::class,
            TariffRepositoryInterface::class,
            QuotaRepositoryInterface::class,
            CommissionRepositoryInterface::class,
            PricingService::class,
            TariffFinder::class,
            AvailabilityChecker::class,
            PricingEngine::class,
            LegacyTariffReplacementService::class,
            PricingContextService::class,
            ProductSearchService::class,
        ];
    }
}