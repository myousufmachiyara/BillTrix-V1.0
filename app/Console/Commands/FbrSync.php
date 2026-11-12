<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Invoice;
use App\Services\FbrService;
use App\Support\Tenant;
use Illuminate\Console\Command;

class FbrSync extends Command
{
    protected $signature = 'billtrix:fbr-sync {--limit=200}';

    protected $description = 'Retry sending pending and failed invoices to FBR';

    public function handle(FbrService $fbr): int
    {
        Business::query()->where('is_active', true)->each(function (Business $business) use ($fbr) {
            if ($business->setting('fbr_mode', 'off') === 'off') {
                return;
            }
            Tenant::run($business, function () use ($fbr, $business) {
                $invoices = Invoice::whereIn('fbr_status', ['pending', 'failed'])->where('fbr_attempts', '<', 50)
                    ->oldest('id')->limit((int) $this->option('limit'))->get();
                $ok = $invoices->filter(fn (Invoice $i) => $fbr->submit($i))->count();
                if ($invoices->isNotEmpty()) {
                    $this->line("{$business->name}: {$ok}/{$invoices->count()} reported");
                }
            });
        });

        return self::SUCCESS;
    }
}
