# BillTrix — Generic Edition

Inventory, billing and double-entry accounting for **retailers, wholesalers and traders** in Pakistan.
One configurable product: the business category only picks starting settings; every feature can be switched on or off per business.

- Laravel 13 (PHP 8.3+), MySQL / MariaDB (SQLite and PostgreSQL also work)
- UI follows the existing BillTrix admin theme (Porto, Bootstrap 5, jQuery, Select2) — no build step needed
- Multi-business (SaaS-ready), multi-branch, multi-warehouse, role-based permissions (spatie/laravel-permission with per-business roles)

## Install

```bash
composer install
cp .env.example .env          # set DB_* for MySQL
php artisan key:generate
php artisan migrate --seed    # creates permissions
php artisan storage:link      # logos & product images
```

Create a business either from the browser at `/register` (set `BILLTRIX_ALLOW_REGISTRATION=false` to disable), or:

```bash
php artisan billtrix:create-business
```

Demo data (a wholesaler with products, parties and a month of transactions):

```bash
php artisan db:seed --class=DemoSeeder     # login: demo@billtrix.pk / password
```

Run tests: `php artisan test` (SQLite in memory).

## What is in this release (Phase 1)

| Area | Included |
|---|---|
| Setup | Business profile, NTN/STRN, logo; feature switches; branches & warehouses; users; roles & permissions; units; categories; price levels; tax rates |
| Products | SKU, barcode, images, categories, base unit + packs (carton/box/piece with own barcode & price), price levels, reorder level, non-stock items, opening stock |
| Stock | Moving weighted-average cost, per-warehouse balances, immutable stock ledger, opening stock, adjustments (damaged/expired/lost/found), physical count, warehouse transfers, negative-stock guard |
| Sales | Fast invoice screen with barcode scanner (`qty*barcode`), product search, cash/credit, walk-in customers, line & bill discounts, tax (inclusive or exclusive), freight, round-off, partial payment, credit-limit check, below-cost check, A4 and 58/80 mm thermal print, previous balance on print |
| Purchases | Supplier bills with landed cost (discount + freight spread over items), partial payments |
| Returns | Sale & purchase returns against the original invoice (quantity guard, original cost), auto-settled against the invoice |
| Accounting | Chart of accounts tree (PK SME template), customers/suppliers as sub-ledgers of AR/AP, receipts & payments with invoice allocation, expense payments, journal vouchers, cash ⇄ bank transfers, opening balances |
| Reports | Dashboard, sales/purchase registers, product-wise sales & margin, P&L, balance sheet, trial balance, account ledger, cash & bank book, party statement, receivables/payables aging, day book, stock valuation, stock movement, low stock |

## Phase 2

| Area | Included |
|---|---|
| POS | Full-screen counter: scanner (`qty*barcode`), product tiles by category, walk-in or account customer, line & bill discount (amount or %), split tender across cash/bank/card accounts with change, hold & recall bills, reprint / return recent bills, thermal receipt, keyboard-driven (F2 scan, F6 bills, F7 hold, F8 recall, F9 pay) |
| Cash closing | Register shifts with opening float; expected cash from the shift's bills and refunds; denomination count (Rs 5000 → 1); over/short posted to *Cash Over / Short*; X/Z report on thermal paper; managers can see and close any register |
| Quotations | Valid-until date, terms, print; convert to a sales order or invoice directly |
| Sales orders | Deliver in parts through challans or invoice pending lines directly; ordered / delivered / pending per line; open → partly fulfilled → completed; cancel / re-open |
| Delivery challans | Stock leaves at average cost into *Goods Delivered Not Invoiced*; one invoice can bill several challans of a customer; challan prints without prices |
| Purchase orders | Receive and bill in parts through purchase invoices; pending quantity guard |
| Customer prices | Special rate per product per customer; lookup priority: customer price → price level → default |

Every Phase 2 feature is a switch in **Settings → Business & Features**. Wholesale and trading presets switch on orders and challans; retail switches on register shifts.

Upgrading an existing Phase 1 database: `php artisan migrate` adds the new tables, system accounts (*Goods Delivered Not Invoiced*, *Cash Over / Short*) and permissions to every business. `php artisan billtrix:upgrade` repeats that safely at any time.

### Planned next
- **Phase 3:** REST API for mobile (Sanctum), FBR POS integration, withholding tax, batch & expiry tracking (optional), Excel import/export, Urdu UI

## How it works

**Everything posts through two services, inside one DB transaction.**

- `StockService` — the only writer of `stock_movements` / `stock_balances`. Locks the product and balance rows, keeps the moving average cost, refuses negative stock unless allowed.
- `LedgerService` — the only writer of `journal_entries` / `journal_lines`. Rejects unbalanced entries, postings to group accounts, and AR/AP lines without a party.

Documents (`InvoiceService`, `VoucherService`, `StockDocumentService`) call those two. Editing a document reverses its stock and ledger effect and re-posts it, so stock, ledgers and reports never drift apart.

**Posting rules** use stable `system_key`s on accounts (`sales`, `inventory`, `receivable`…) so users can rename or renumber accounts freely.

| Document | Debit | Credit |
|---|---|---|
| Sale | Customer (AR), Sales Discount, COGS | Sales, Output Tax, Freight Income, Inventory (or GDNI for challan lines) |
| Delivery challan | Goods Delivered Not Invoiced | Inventory |
| Register close | Cash or Cash Over / Short | the other side |
| Purchase | Inventory (landed), Input Tax | Supplier (AP) |
| Sale return | Sales Returns, Output Tax, Inventory | Customer (AR), COGS |
| Purchase return | Supplier (AP) | Inventory, Input Tax |
| Receipt / payment | Cash/Bank or AR/AP/expense | the other side |
| Stock gain / loss | Inventory or Inventory Adjustments | the other side |

**Tenancy:** every business-owned model uses `BelongsToBusiness` (global scope + auto-fill). The `tenant` middleware binds the signed-in user's business; spatie roles use `business_id` as the team key.

**Configuration:** `config/billtrix.php` holds feature defaults, category presets, the default chart of accounts, units, permissions and default roles (Owner, Manager, Accountant, Cashier, Storekeeper).

## Code map

```
app/Services/           InvoiceService, OrderService, DeliveryService, CashSessionService, VoucherService, StockDocumentService, StockService, LedgerService, ReportService, BusinessProvisioner
app/Http/Controllers/   Trade (sales/purchases/returns), Accounting, Inventory, Reports, Settings
app/Models/             Business, Account, Party, JournalEntry/Line, Product, ProductUnit, StockMovement, Invoice ...
resources/views/        Blade views on the BillTrix theme; components in views/components
public/assets/js/       billtrix.js (common), invoice.js (invoice & order editor), pos.js (POS)
tests/Feature/          PostingTest, Phase2Test (orders, challans, POS, registers), PagesTest (every screen + HTTP flows)
```
