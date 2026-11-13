<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rebuilds the public demo from scratch: wipes the database, loads fresh demo data, restores the leads,
 * and sets up the owner login (for you) and the shared demo login (for visitors).
 */
class DemoReset extends Command
{
    protected $signature = 'billtrix:demo-reset {--force : Do not ask for confirmation}';

    protected $description = 'Wipe and reload the public demo (keeps demo leads). Demo installs only.';

    public function handle(): int
    {
        $cfg = config('billtrix.demo');
        if (! $cfg['enabled']) {
            $this->error('Demo mode is off (BILLTRIX_DEMO_MODE). This command deletes ALL data, so it only runs on a demo install.');

            return self::FAILURE;
        }
        if (strlen((string) $cfg['admin_password']) < 8) {
            $this->error('Set BILLTRIX_DEMO_ADMIN_PASSWORD (8+ characters) in .env first: it is your own login to see the leads.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('This deletes every business record in this database and reloads the demo. Continue?')) {
            return self::FAILURE;
        }

        $leads = Schema::hasTable('demo_leads') ? DB::table('demo_leads')->get()->map(fn ($r) => (array) $r)->all() : [];
        $this->info('Keeping '.count($leads).' lead(s).');

        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        foreach (array_chunk($leads, 200) as $chunk) {
            DB::table('demo_leads')->insert($chunk);
        }

        $owner = User::withoutGlobalScopes()->where('email', 'demo@billtrix.pk')->firstOrFail();
        $owner->forceFill(['username' => $cfg['admin_username'], 'password' => $cfg['admin_password']])->save();

        $business = Business::findOrFail($owner->business_id);
        $business->update(['settings' => array_merge($business->settings ?? [], $cfg['settings'])]);

        self::createVisitor($business->fresh());

        $this->info("Demo ready. Your login: {$cfg['admin_username']} / (BILLTRIX_DEMO_ADMIN_PASSWORD). Visitors use the demo form.");

        return self::SUCCESS;
    }

    /** The shared "Demo" role (everything except settings, users, FBR…) and the visitor login that uses it. */
    public static function createVisitor(Business $business): User
    {
        $cfg = config('billtrix.demo');

        return Tenant::run($business, function () use ($business, $cfg) {
            $denied = $cfg['denied'];
            $allowed = Permission::pluck('name')->reject(function ($perm) use ($denied) {
                foreach ($denied as $p) {
                    if ($p === $perm || (str_ends_with($p, '.*') && str_starts_with($perm, substr($p, 0, -1)))) {
                        return true;
                    }
                }

                return false;
            })->values();
            $role = Role::firstOrCreate(['name' => 'Demo', 'guard_name' => 'web', 'business_id' => $business->id]);
            $role->syncPermissions($allowed);

            $visitor = User::create([
                'business_id' => $business->id,
                'branch_id' => Branch::value('id'),
                'name' => 'Demo Visitor',
                'email' => 'visitor@demo.billtrix.local',
                'username' => $cfg['username'],
                'password' => Str::random(40),   // nobody signs in with it: the demo form signs visitors in
            ]);
            $visitor->assignRole($role);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $visitor;
        });
    }
}
