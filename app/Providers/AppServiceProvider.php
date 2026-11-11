<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'invoice' => Invoice::class,
            'party' => Party::class,
            'stock_adjustment' => StockAdjustment::class,
            'stock_transfer' => StockTransfer::class,
            'user' => \App\Models\User::class,
            'delivery' => \App\Models\Delivery::class,
            'cash_session' => \App\Models\CashSession::class,
        ]);

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Paginator::useBootstrapFive();

        View::composer('*', function ($view) {
            static $business = false;
            if ($business === false) {
                $business = Tenant::id() ? Tenant::business() : null;
            }
            $view->with('business', $business);
        });

        Blade::if('feature', fn (string $key) => Tenant::id() && Tenant::business()->feature($key));
    }
}
