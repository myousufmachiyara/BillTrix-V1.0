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
        // The demo install's owner (not the shared visitor login) sees who tried the demo.
        \Illuminate\Support\Facades\Gate::define('manage-leads', fn (\App\Models\User $user) => config('billtrix.demo.enabled')
            && ! $user->isDemoVisitor() && $user->hasRole('Owner'));
        \Illuminate\Support\Facades\Route::bind('lead', fn ($id) => \App\Models\DemoLead::findOrFail($id));

        Relation::enforceMorphMap([
            'invoice' => Invoice::class,
            'party' => Party::class,
            'stock_adjustment' => StockAdjustment::class,
            'stock_transfer' => StockTransfer::class,
            'user' => \App\Models\User::class,
            'delivery' => \App\Models\Delivery::class,
            'cash_session' => \App\Models\CashSession::class,
            'batch' => \App\Models\Batch::class,
            'journal_entry' => \App\Models\JournalEntry::class,
            'product' => \App\Models\Product::class,
            'product_variant' => \App\Models\ProductVariant::class,
            'product_attribute' => \App\Models\ProductAttribute::class,
            'account' => \App\Models\Account::class,
            'order' => \App\Models\Order::class,
            'business' => \App\Models\Business::class,
            'branch' => \App\Models\Branch::class,
            'warehouse' => \App\Models\Warehouse::class,
            'party_price' => \App\Models\PartyPrice::class,
            'tax_rate' => \App\Models\TaxRate::class,
            'price_level' => \App\Models\PriceLevel::class,
            'category' => \App\Models\Category::class,
            'unit' => \App\Models\Unit::class,
        ]);

        // Sign-ins in the audit trail.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn ($e) => \App\Support\Audit::event('login', $e->user, [], $e->user->business_id ?? null, $e->user->id));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, fn ($e) => $e->user && \App\Support\Audit::event('logout', $e->user, [], $e->user->business_id ?? null, $e->user->id));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, fn ($e) => $e->user && \App\Support\Audit::event('login_failed', $e->user, ['email' => $e->credentials['email'] ?? null], $e->user->business_id ?? null, $e->user->id));

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
