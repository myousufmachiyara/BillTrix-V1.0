<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Party;
use App\Models\PriceLevel;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Sets up a brand-new business with everything it needs to start billing. */
class BusinessProvisioner
{
    /**
     * @param  array  $business  name, category, phone?, address?
     * @param  array  $owner  name, email, password
     */
    public function create(array $business, array $owner): User
    {
        return DB::transaction(function () use ($business, $owner) {
            $model = Business::create([
                'name' => $business['name'],
                'category' => $business['category'] ?? 'retail',
                'phone' => $business['phone'] ?? null,
                'address' => $business['address'] ?? null,
            ]);

            return Tenant::run($model, function () use ($model, $owner) {
                $branch = Branch::create(['name' => 'Main Branch', 'code' => 'MAIN']);
                Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main Store', 'is_default' => true]);

                $this->chartOfAccounts(config('billtrix.chart_of_accounts'));
                $this->units();

                $retail = PriceLevel::create(['name' => 'Retail', 'is_default' => true]);
                PriceLevel::create(['name' => 'Wholesale']);

                TaxRate::create(['name' => 'GST 18%', 'rate' => 18]);
                TaxRate::create(['name' => 'Exempt', 'rate' => 0]);

                Party::create(['type' => 'customer', 'name' => 'Walk-in Customer', 'code' => 'WALKIN', 'is_walk_in' => true, 'price_level_id' => $retail->id]);

                $this->roles();

                $user = User::create($owner + ['branch_id' => $branch->id, 'business_id' => $model->id]);
                $user->assignRole('Owner');

                return $user;
            });
        });
    }

    public static function syncPermissions(): void
    {
        foreach (config('billtrix.permissions') as $module => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$module}.{$action}", 'web');
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Bring an existing business up to date after an upgrade: add new system accounts
     * and grant newly added permissions to the built-in roles. Never removes anything.
     */
    public function upgrade(Business $business): void
    {
        Tenant::run($business, function () {
            Account::flushSystemCache();
            $this->addMissingAccounts(config('billtrix.chart_of_accounts'));
            self::syncPermissions();
            foreach (config('billtrix.roles') as $name => $patterns) {
                $role = Role::where('business_id', Tenant::idOrFail())->where('name', $name)->first();
                $role?->givePermissionTo($this->permissionsFor($patterns));
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function roles(): void
    {
        self::syncPermissions();

        foreach (config('billtrix.roles') as $name => $patterns) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'business_id' => Tenant::idOrFail()]);
            $role->syncPermissions($this->permissionsFor($patterns));
        }
    }

    private function permissionsFor(string|array $patterns)
    {
        $all = Permission::pluck('name');

        return $patterns === '*' ? $all : $all->filter(function ($perm) use ($patterns) {
            foreach ($patterns as $p) {
                if ($p === $perm || (str_ends_with($p, '.*') && str_starts_with($perm, substr($p, 0, -1)))) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function addMissingAccounts(array $nodes, ?Account $parent = null): void
    {
        foreach ($nodes as $node) {
            [$code, $name, $type, $subtype, $key] = $node;
            $children = $node[5] ?? null;
            $existing = $key ? Account::where('system_key', $key)->first() : Account::where('code', $code)->first();

            if (! $existing && $key && $parent) {
                $code = Account::where('code', $code)->exists() ? $code.'9' : $code;
                $existing = Account::create(['parent_id' => $parent->id, 'code' => $code, 'name' => $name, 'type' => $type,
                    'subtype' => $subtype, 'system_key' => $key, 'is_group' => $children !== null]);
            }
            if ($children && $existing) {
                $this->addMissingAccounts($children, $existing);
            }
        }
    }

    private function chartOfAccounts(array $nodes, ?int $parentId = null): void
    {
        foreach ($nodes as $node) {
            [$code, $name, $type, $subtype, $key] = $node;
            $children = $node[5] ?? null;
            $account = Account::create([
                'parent_id' => $parentId,
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'subtype' => $subtype,
                'system_key' => $key,
                'is_group' => $children !== null,
            ]);
            if ($children) {
                $this->chartOfAccounts($children, $account->id);
            }
        }
    }

    private function units(): void
    {
        foreach (config('billtrix.units') as [$name, $short, $decimal]) {
            Unit::create(['name' => $name, 'short_name' => $short, 'allow_decimal' => $decimal]);
        }
    }
}
