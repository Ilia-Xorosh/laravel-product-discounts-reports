<?php

namespace ProductDiscounts\Reports;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ProductDiscounts\Events\CalculationCompleted;

class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/product-discounts-reports.php', 'product-discounts-reports');
    }

    public function boot(): void
    {
        Event::listen(CalculationCompleted::class, [RecordCalculation::class, 'handle']);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'product-discounts-reports');
        $this->publishes([__DIR__.'/../config/product-discounts-reports.php' => config_path('product-discounts-reports.php')], 'product-discounts-reports-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'product-discounts-reports-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/product-discounts-reports')], 'product-discounts-reports-views');
        if (!$this->app->routesAreCached() && config('product-discounts-reports.admin.enabled')) {
            Route::middleware(config('product-discounts-reports.admin.middleware'))
                ->prefix(config('product-discounts-reports.admin.prefix'))->name('product-discounts-reports.')
                ->group(function () {
                    Route::get('/', [ReportController::class, 'index'])->name('index');
                    Route::get('export/{mode}', [ReportController::class, 'export'])->whereIn('mode', ['mini', 'max'])->name('export');
                    Route::get('{basket}', [ReportController::class, 'show'])->whereNumber('basket')->name('show');
                });
        }
    }
}
