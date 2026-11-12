<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\BusinessProvisioner;
use Illuminate\Console\Command;

class UpgradeBusinesses extends Command
{
    protected $signature = 'billtrix:upgrade';

    protected $description = 'Add new system accounts and permissions to every existing business (safe to run repeatedly)';

    public function handle(BusinessProvisioner $provisioner): int
    {
        BusinessProvisioner::syncPermissions();
        Business::query()->each(function (Business $b) use ($provisioner) {
            $provisioner->upgrade($b);
            $this->line("Upgraded #{$b->id} {$b->name}");
        });

        return self::SUCCESS;
    }
}
