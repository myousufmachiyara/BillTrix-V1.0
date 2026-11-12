<?php

namespace Database\Seeders;

use App\Services\BusinessProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        BusinessProvisioner::syncPermissions();

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
