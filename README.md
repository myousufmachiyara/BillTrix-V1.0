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

Users can sign in with their email or a username. Give a user a username (and password) from the server:

```bash
php artisan billtrix:set-login admin --password=yourpassword   # asks which user
```
or set it under *Settings → Users*.

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

## Phase 3

| Area | Included |
|---|---|
| FBR | **POS (IMS)** for Tier-1 retailers: each sale/return is sent with POSID, PCT codes and payment mode; FBR invoice number and QR printed on the receipt; Re 1 POS fee added and posted to *FBR POS Fee Payable*. **Digital Invoicing (PRAL)** for sales-tax registered sellers, sandbox (`_sb`) or production. Optional local fiscal component (`localhost:8524`). A failed submission never blocks the sale — it's retried every 5 minutes (`billtrix:fbr-sync`) or from **FBR Reporting**. Reported invoices are locked; correct them with a return. |
| Withholding tax | Collected on sales (236G / 236H) and deducted on purchases (153), with separate filer / non-filer rates, per-party filer status and exemption, manual rate override per invoice, *Withholding Tax Payable* account, and a sales-tax & WHT report. |
| Batch & expiry | Per-product switch. Batch no. and expiry on purchases and opening stock; sales and challans issue FEFO and never from expired batches; returns go back to the batch they came from; expired/damaged write-offs by batch; expiry report. |
| Excel | Import products (with opening stock) and customers/suppliers (with opening balance) from .xlsx/.csv using a downloadable template; row-by-row errors and nothing saved until the whole file is valid; re-importing updates by SKU/code. Export to .xlsx from products, parties and the main reports. |
| Mobile API | Sanctum tokens (`/api/v1`): login, me, lookups, products (search/barcode/stock), parties with balance/open invoices/statement, sales & orders, receipts, dashboard. Same permissions and tenancy as the web app; needs the `api.use` permission. See `docs/API.md`. |
| Urdu | Per-user language switch in the user menu; right-to-left layout. See *Final release* below — every screen is now translated. |

**FBR go-live checklist:** register the POS / get the DI token on IRIS, enter it in *Settings → Business & Features → FBR*, set the business NTN and province, fill the HS/PCT code on each product, test in sandbox, then switch to production. Upload FBR's POS logo to `public/assets/img/fbr-pos-logo.png` if you want it on receipts. Endpoints and field codes follow FBR's current published specs — confirm them during onboarding.

Run the scheduler on the server so failed FBR submissions are retried: `* * * * * php /path/artisan schedule:run`.

## Final release

| Area | Included |
|---|---|
| Urdu everywhere | Every screen, print, flash message, business-rule error and the common validation messages (`lang/ur.json`, `lang/ur/validation.php`), plus the JavaScript on the invoice, voucher and POS screens. Names you type (products, accounts, customers) stay as entered. The page tests render every screen in both languages. |
| WHT on vouchers | Receipts and payments take a withholding rate or amount. A receipt of Rs 9,800 with Rs 200 deducted by the customer settles Rs 10,000 of their invoices: Dr Cash 9,800, Dr *Advance Income Tax* 200, Cr Customer 10,000. A payment where you deduct tax posts the deduction to *Withholding Tax Payable*. Both appear on the sales-tax & WHT report. |
| Batch picking on sales | For batch-tracked items the sale line offers the in-stock, unexpired batches (earliest expiry first). Leave it on *Auto* for FEFO or pick a batch. |
| Audit trail | Settings → Audit Trail: who created, changed or deleted what (before → after values), invoice line changes, sign-ins / sign-outs / failed sign-ins, and exports. Filter by user, record type, event and date; *History* buttons on invoices, vouchers, parties, products and orders. Passwords and the FBR token are never logged. Needs the `audit.view` permission (Owner by default). |
| Offline POS | The POS keeps a copy of the product list on the device. When the connection drops it keeps selling: scanner and search work from that copy, bills are saved on the device with their own id and a provisional receipt prints. They're sent automatically (oldest first) when the connection returns; the server accepts each one only once and keeps the original sale date (up to 7 days back). Anything the server rejects (e.g. a credit limit) is shown in the *waiting bills* list to sort out. The cashier must have opened the POS online at least once on that device. |
| Installable app | BillTrix is a Progressive Web App: *Install app* in the user menu (Chrome / Edge / Android), or *Add to Home Screen* on iPhone. It opens full-screen with its own icon and shortcuts to POS, New sale and Customers; visited pages open read-only when offline. This is not a native store app — the Sanctum API (`docs/API.md`) is ready for one if you build it later. |
| Backups | Nightly full database backup at 01:30 into `storage/app/backups`, 14 days kept (`php artisan billtrix:backup --keep=14`; uses `mysqldump` when installed). Owners can download their whole business as a zip of JSON files from Settings → Backup & Export. Copy server backups off the machine regularly. |

**Before going live (your side):** test FBR in sandbox with your real POSID/token, then switch to production; set up the server (PHP 8.3+, MySQL, HTTPS — needed for the installable app and offline POS), the scheduler cron (`* * * * * php artisan schedule:run`) and an off-site copy of `storage/app/backups`; pilot with one store before rolling out.

## Product variations

Switch on **Product variations** in *Settings → Business & Features*.

| Area | Included |
|---|---|
| Attributes | *Inventory → Attributes*: Size, Colour, … with their values (`S, M, L`); an optional short code per value for SKUs (`Red=RD`); the order decides how variation names read (`Red / M`). |
| Products | Tick *This product comes in variations*, pick the values and *Create variations*: one row per combination with its own SKU, barcode, sale/purchase price and reorder level (blank prices use the product's). SKUs are generated as `SHIRT-RD-M`, barcodes as scannable store EAN-13 numbers; type your own to override. Opening stock per variation when the product is created. A variation that has been used is switched off rather than deleted. |
| Stock | Stock and average cost are kept per variation and per warehouse. Purchases, sales, returns (back to the same variation), POS (online and offline), quotations, orders, delivery challans, adjustments, physical counts and transfers all work per variation; a line for such a product must name its variation. A product either tracks batches or has variations. |
| Finding items | The barcode box, product search and POS list each variation separately with its own price and stock; scanning a variation's barcode adds exactly that variation. |
| Reports | Stock & valuation, low stock and product-wise sales show one row per variation; stock movement can be filtered by variation; the product page shows stock per variation per warehouse. |
| Barcode labels | *Inventory → Barcode Labels* (or *Print labels* on a product): A4 sheets (3×8, 4×10) or label rolls (38×25, 50×25 mm), with name, variation, Code 128 barcode and price. Needs the `products.labels` permission. |
| API | Products include `has_variants` and `variants[]`; send `variant_id` on document lines. |

Upgrade: `php artisan migrate` adds the tables and the new permission to every business.

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
public/assets/js/       billtrix.js (common), invoice.js (invoice & order editor), pos.js (POS, offline queue)
public/sw.js            service worker (installable app, offline POS)
tests/Feature/          PostingTest, Phase2Test (orders, challans, POS, registers), Phase3Test, FinalTest (audit, WHT vouchers, batch pick, backups, offline POS), VariantTest (variations end to end), PagesTest (every screen, English and Urdu)
```
