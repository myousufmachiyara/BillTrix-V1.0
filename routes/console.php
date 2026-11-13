<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retry invoices that could not reach FBR when they were saved.
\Illuminate\Support\Facades\Schedule::command('billtrix:fbr-sync')->everyFiveMinutes()->withoutOverlapping();

// Nightly database backup (kept for 14 days). Copy storage/app/backups off the server regularly.
\Illuminate\Support\Facades\Schedule::command('billtrix:backup')->dailyAt('01:30')->withoutOverlapping();

// Public demo only: reload fresh demo data every night (leads are kept). Needs BILLTRIX_DEMO_MODE and BILLTRIX_DEMO_RESET.
if (config('billtrix.demo.enabled') && config('billtrix.demo.nightly_reset')) {
    \Illuminate\Support\Facades\Schedule::command('billtrix:demo-reset --force')->dailyAt('03:00')->withoutOverlapping();
}
