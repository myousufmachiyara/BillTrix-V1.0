<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\HeadOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;
use App\Models\Attribute;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now    = now();
        $userId = 1;

        // ── Users ────────────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Admin', 'email' => null, 'password' => Hash::make('12345678')]
        );

        $manager = User::firstOrCreate(
            ['username' => 'manager'],
            ['name' => 'Manager', 'email' => null, 'password' => Hash::make('12345678')]
        );

        // branch_id will be set after a branch is created; null is fine for seeding
        $branchUser = User::firstOrCreate(
            ['username' => 'branch_user'],
            ['name' => 'Branch User', 'email' => null, 'password' => Hash::make('12345678'), 'branch_id' => null]
        );

        // ── Permission modules & actions ─────────────────────────────────────
        $crudPrint = ['index', 'create', 'edit', 'delete', 'print'];
        $crud      = ['index', 'create', 'edit', 'delete'];

        $modules = [
            // 1. Accounts
            'heads_of_accounts'     => $crudPrint,
            'sub_heads'             => $crudPrint,
            'coa'                   => $crudPrint,

            // 2. Users / Roles / Permissions
            'users'                 => $crudPrint,
            'roles'                 => $crudPrint,
            'permissions'           => $crud,           // no print for permissions

            // 3. Products
            'product_categories'    => $crudPrint,
            'product_subcategories' => $crudPrint,
            'attributes'            => $crudPrint,
            'products'              => $crudPrint,

            // 4. Purchase
            'purchase_orders'       => $crudPrint,
            'purchase_invoices'     => $crudPrint,
            'purchase_returns'      => $crudPrint,

            // 5. Production
            'production_orders'     => $crudPrint,
            'production_receiving'  => $crudPrint,
            'production_returns'    => $crudPrint,

            // 6. Inventory
            'locations'             => $crudPrint,
            'stock_in_out'          => $crudPrint,
            'gatepasses'            => $crudPrint,
            'stock_transfers'       => $crudPrint,

            // 7. Branches
            'branches'              => $crudPrint,
            'branch_users'          => $crud,           // no print for branch users

            // 8. Sales
            'quotations'            => $crudPrint,
            'sale_orders'           => $crudPrint,
            'sale_invoices'         => $crudPrint,
            'sale_returns'          => $crudPrint,

            // 9. Finance
            'vouchers'              => $crudPrint,
            'pdc'                   => $crudPrint,
        ];

        // 10. Report gates — single permission per report page (no CRUD actions)
        $reportGates = [
            // Inventory reports
            'reports.inventory',
            'reports.item_ledger',
            'reports.stock_in_hand',

            // Operational reports
            'reports.purchase',
            'reports.production',
            'reports.sales',

            // Accounting reports
            'reports.ledger',
            'reports.trial_balance',
            'reports.pnl',
            'reports.payables',
            'reports.receivables',

            // Extended accounting/summary reports (registered in web.php)
            'reports.cash_flow',
            'reports.balance_sheet',
            'reports.day_book',
        ];

        // Create all module permissions
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
            }
        }

        // Create all report permissions
        foreach ($reportGates as $gate) {
            Permission::firstOrCreate(['name' => $gate]);
        }

        // ── Roles ────────────────────────────────────────────────────────────

        // superadmin → every permission (including future ones added at runtime)
        $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $superAdminRole->syncPermissions(Permission::all());
        $admin->assignRole($superAdminRole);

        // manager → everything except:
        //   • branch creation/editing/deletion (admin-only)
        //   • permission management (admin-only)
        //   • role deletion (admin-only)
        $managerExclude = [
            'branches.create',   'branches.edit',   'branches.delete',
            'branch_users.create','branch_users.edit','branch_users.delete',
            'permissions.create','permissions.edit', 'permissions.delete',
            'roles.delete',
        ];
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions(
            Permission::whereNotIn('name', $managerExclude)->get()
        );
        $manager->assignRole($managerRole);

        // branch_user (POS) → minimal sale + quotation permissions only.
        // Controllers for these routes MUST scope queries by auth()->user()->branch_id.
        // See: BranchScope middleware and BranchScopedController trait.
        $branchRole = Role::firstOrCreate(['name' => 'branch_user']);
        $branchRole->syncPermissions(
            Permission::whereIn('name', [
                'sale_invoices.index',  'sale_invoices.create',  'sale_invoices.print',
                'sale_orders.index',    'sale_orders.create',    'sale_orders.print',
                'quotations.index',     'quotations.create',     'quotations.print',
                // Read-only access to own branch inventory for POS stock checks
                'locations.index',
                'stock_in_out.index',
                // Product lookup (no create/edit)
                'products.index',
            ])->get()
        );
        $branchUser->assignRole($branchRole);

        // ── Heads of Accounts ────────────────────────────────────────────────
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses',    'created_at' => $now, 'updated_at' => $now],
        ]);

        // ── Sub Heads ─────────────────────────────────────────────────────────
        SubHeadOfAccounts::insert([
            ['id' =>  1, 'hoa_id' => 1, 'name' => 'Cash',                'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'hoa_id' => 1, 'name' => 'Bank',                'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'hoa_id' => 1, 'name' => 'Accounts Receivable', 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'hoa_id' => 1, 'name' => 'Inventory',           'created_at' => $now, 'updated_at' => $now],
            ['id' =>  5, 'hoa_id' => 2, 'name' => 'Accounts Payable',    'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'hoa_id' => 2, 'name' => 'Loans',               'created_at' => $now, 'updated_at' => $now],
            ['id' =>  7, 'hoa_id' => 3, 'name' => 'Owner Capital',       'created_at' => $now, 'updated_at' => $now],
            ['id' =>  8, 'hoa_id' => 4, 'name' => 'Sales',               'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'hoa_id' => 5, 'name' => 'Purchases',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'hoa_id' => 5, 'name' => 'Salaries',            'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'hoa_id' => 5, 'name' => 'Rent',                'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'hoa_id' => 5, 'name' => 'Utilities',           'created_at' => $now, 'updated_at' => $now],
        ]);

        // ── Chart of Accounts ─────────────────────────────────────────────────
        $coaData = [
            // Assets
            ['account_code' => '104001', 'shoa_id' =>  4, 'name' => 'Stock in Hand',      'account_type' => 'asset',    'receivables' => 0,     'payables' => 0],
            ['account_code' => '103001', 'shoa_id' =>  3, 'name' => 'Customer 01',        'account_type' => 'customer', 'receivables' => 12000, 'payables' => 0],
            ['account_code' => '101001', 'shoa_id' =>  1, 'name' => 'Shop Cash',          'account_type' => 'cash',     'receivables' => 0,     'payables' => 0],
            ['account_code' => '102001', 'shoa_id' =>  2, 'name' => 'Meezan Yousuf',      'account_type' => 'bank',     'receivables' => 0,     'payables' => 0],
            // Liabilities
            ['account_code' => '205001', 'shoa_id' =>  5, 'name' => 'Vendor 01',          'account_type' => 'vendor',   'receivables' => 0,     'payables' => 7500],
            ['account_code' => '206001', 'shoa_id' =>  6, 'name' => 'Bank Loan',          'account_type' => 'liability','receivables' => 0,     'payables' => 0],
            // Equity
            ['account_code' => '307001', 'shoa_id' =>  7, 'name' => 'Owners Equity',      'account_type' => 'equity',   'receivables' => 0,     'payables' => 0],
            // Revenue
            ['account_code' => '408001', 'shoa_id' =>  8, 'name' => 'Sales Revenue',      'account_type' => 'revenue',  'receivables' => 0,     'payables' => 0],
            // Expenses / COGS
            ['account_code' => '509001', 'shoa_id' =>  9, 'name' => 'Cost of Goods Sold', 'account_type' => 'cogs',     'receivables' => 0,     'payables' => 0],
            ['account_code' => '510001', 'shoa_id' => 10, 'name' => 'Salaries Expense',   'account_type' => 'expense',  'receivables' => 0,     'payables' => 0],
            ['account_code' => '511001', 'shoa_id' => 11, 'name' => 'Rent Expense',       'account_type' => 'expense',  'receivables' => 0,     'payables' => 0],
            ['account_code' => '512001', 'shoa_id' => 12, 'name' => 'Utilities Expense',  'account_type' => 'expense',  'receivables' => 0,     'payables' => 0],
        ];

        foreach ($coaData as $data) {
            ChartOfAccounts::firstOrCreate(
                ['account_code' => $data['account_code']],
                array_merge($data, [
                    'opening_date' => '2026-01-19',
                    'credit_limit' => 0,
                    'remarks'      => null,
                    'address'      => null,
                    'phone_no'     => null,
                    'created_by'   => $userId,
                    'updated_by'   => $userId,
                ])
            );
        }

        // ── Measurement Units ─────────────────────────────────────────────────
        MeasurementUnit::upsert([
            ['id' =>  1, 'name' => 'Kilogram',   'shortcode' => 'kg'],
            ['id' =>  2, 'name' => 'Milligram',  'shortcode' => 'mg'],
            ['id' =>  3, 'name' => 'Gram',       'shortcode' => 'g'],
            ['id' =>  4, 'name' => 'Millimeter', 'shortcode' => 'mm'],
            ['id' =>  5, 'name' => 'Meters',     'shortcode' => 'm'],
            ['id' =>  6, 'name' => 'Yards',      'shortcode' => 'yrds'],
            ['id' =>  7, 'name' => 'Sq.Ft',      'shortcode' => 'sqft'],
            ['id' =>  8, 'name' => 'Tons',        'shortcode' => 'tns'],
            ['id' =>  9, 'name' => 'Pieces',      'shortcode' => 'pcs'],
            ['id' => 10, 'name' => 'Carton',      'shortcode' => 'ct'],
            ['id' => 11, 'name' => 'Set',         'shortcode' => 'set'],
            ['id' => 12, 'name' => 'Pair',        'shortcode' => 'pair'],
        ], ['id'], ['name', 'shortcode']);

        // ── Default attribute ─────────────────────────────────────────────────
        Attribute::firstOrCreate(
            ['slug' => Str::slug('Size')],
            ['name' => 'Size']
        );

        Attribute::firstOrCreate(
            ['slug' => Str::slug('Color')],
            ['name' => 'Color']
        );
    }
}