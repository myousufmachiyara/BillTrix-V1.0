@php
    $u = auth()->user();
    $is = fn (...$patterns) => request()->routeIs(...$patterns) ? 'nav-active' : '';
    $open = fn (...$patterns) => request()->routeIs(...$patterns) ? 'nav-expanded nav-active' : '';
    $doc = request()->route('doc');
    $vt = request()->route('voucherType');
    $od = request()->route('orderDoc');
@endphp
<aside id="sidebar-left" class="sidebar-left">
    <div class="sidebar-header">
        <div class="sidebar-title">Menu</div>
        <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
            <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
        </div>
    </div>

    <div class="nano">
        <div class="nano-content">
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">
                    <li class="{{ $is('dashboard') }}">
                        <a class="nav-link" href="{{ route('dashboard') }}"><i class="fas fa-home"></i><span>Dashboard</span></a>
                    </li>

                    @feature('pos')
                        @canany(['pos.use', 'cash_sessions.view'])
                        <li class="{{ $is('pos', 'cash-sessions.*') }}">
                            <a class="nav-link" href="{{ auth()->user()->can('pos.use') ? route('pos') : route('cash-sessions.index') }}"><i class="fas fa-cash-register"></i><span>POS</span></a>
                        </li>
                        @endcanany
                    @endfeature

                    @canany(['sales.view', 'sale_returns.view', 'parties.view'])
                    <li class="nav-parent {{ in_array($doc, ['sales', 'sale-returns']) || in_array($od, ['quotations', 'sales-orders']) || request()->routeIs('deliveries.*') || ($vt === 'receipt') || request('type') === 'customer' ? 'nav-expanded nav-active' : '' }}">
                        <a class="nav-link" href="#"><i class="fas fa-shopping-cart"></i><span>Sales</span></a>
                        <ul class="nav nav-children">
                            @can('sales.create')<li><a class="nav-link" href="{{ route('invoices.create', 'sales') }}">New Sale</a></li>@endcan
                            @feature('quotations')@can('quotations.view')<li class="{{ $od === 'quotations' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('orders.index', 'quotations') }}">Quotations</a></li>@endcan @endfeature
                            @feature('sales_orders')@can('sales_orders.view')<li class="{{ $od === 'sales-orders' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('orders.index', 'sales-orders') }}">Sales Orders</a></li>@endcan @endfeature
                            @feature('delivery_challans')@can('deliveries.view')<li class="{{ $is('deliveries.*') }}"><a class="nav-link" href="{{ route('deliveries.index') }}">Delivery Challans</a></li>@endcan @endfeature
                            @can('sales.view')<li class="{{ $doc === 'sales' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('invoices.index', 'sales') }}">Sale Invoices</a></li>@endcan
                            @can('sale_returns.view')<li class="{{ $doc === 'sale-returns' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('invoices.index', 'sale-returns') }}">Sale Returns</a></li>@endcan
                            @can('parties.view')<li><a class="nav-link" href="{{ route('parties.index', ['type' => 'customer']) }}">Customers</a></li>@endcan
                            @can('vouchers.view')<li class="{{ $vt === 'receipt' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('vouchers.index', 'receipt') }}">Receipts</a></li>@endcan
                        </ul>
                    </li>
                    @endcanany

                    @canany(['purchases.view', 'purchase_returns.view', 'parties.view'])
                    <li class="nav-parent {{ in_array($doc, ['purchases', 'purchase-returns']) || $od === 'purchase-orders' || ($vt === 'payment') || request('type') === 'supplier' ? 'nav-expanded nav-active' : '' }}">
                        <a class="nav-link" href="#"><i class="fas fa-truck-loading"></i><span>Purchases</span></a>
                        <ul class="nav nav-children">
                            @can('purchases.create')<li><a class="nav-link" href="{{ route('invoices.create', 'purchases') }}">New Purchase</a></li>@endcan
                            @feature('purchase_orders')@can('purchase_orders.view')<li class="{{ $od === 'purchase-orders' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('orders.index', 'purchase-orders') }}">Purchase Orders</a></li>@endcan @endfeature
                            @can('purchases.view')<li class="{{ $doc === 'purchases' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('invoices.index', 'purchases') }}">Purchase Invoices</a></li>@endcan
                            @can('purchase_returns.view')<li class="{{ $doc === 'purchase-returns' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('invoices.index', 'purchase-returns') }}">Purchase Returns</a></li>@endcan
                            @can('parties.view')<li><a class="nav-link" href="{{ route('parties.index', ['type' => 'supplier']) }}">Suppliers</a></li>@endcan
                            @can('vouchers.view')<li class="{{ $vt === 'payment' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('vouchers.index', 'payment') }}">Payments</a></li>@endcan
                        </ul>
                    </li>
                    @endcanany

                    @canany(['products.view', 'stock.view'])
                    <li class="nav-parent {{ $open('products.*', 'stock-adjustments.*', 'stock-transfers.*') }} {{ in_array(request()->route('master'), ['units', 'categories']) ? 'nav-expanded nav-active' : '' }}">
                        <a class="nav-link" href="#"><i class="fas fa-boxes"></i><span>Inventory</span></a>
                        <ul class="nav nav-children">
                            @can('products.view')<li class="{{ $is('products.*') }}"><a class="nav-link" href="{{ route('products.index') }}">Products</a></li>@endcan
                            @can('products.edit')
                                <li><a class="nav-link" href="{{ route('masters.index', 'categories') }}">Categories</a></li>
                                <li><a class="nav-link" href="{{ route('masters.index', 'units') }}">Units</a></li>
                            @endcan
                            @can('stock.adjust')
                                <li><a class="nav-link" href="{{ route('stock-adjustments.create', ['type' => 'opening']) }}">Opening Stock</a></li>
                                <li><a class="nav-link" href="{{ route('stock-adjustments.create', ['type' => 'count']) }}">Physical Stock Count</a></li>
                            @endcan
                            @can('stock.view')<li class="{{ $is('stock-adjustments.index', 'stock-adjustments.show') }}"><a class="nav-link" href="{{ route('stock-adjustments.index') }}">Stock Adjustments</a></li>@endcan
                            @feature('multi_warehouse')
                                @can('stock.view')<li class="{{ $is('stock-transfers.*') }}"><a class="nav-link" href="{{ route('stock-transfers.index') }}">Stock Transfers</a></li>@endcan
                            @endfeature
                        </ul>
                    </li>
                    @endcanany

                    @canany(['accounts.view', 'vouchers.view'])
                    <li class="nav-parent {{ $open('accounts.*') }} {{ in_array($vt, ['journal', 'contra']) ? 'nav-expanded nav-active' : '' }}">
                        <a class="nav-link" href="#"><i class="fas fa-book"></i><span>Accounts</span></a>
                        <ul class="nav nav-children">
                            @can('accounts.view')<li class="{{ $is('accounts.*') }}"><a class="nav-link" href="{{ route('accounts.index') }}">Chart of Accounts</a></li>@endcan
                            @can('vouchers.view')
                                <li><a class="nav-link" href="{{ route('vouchers.index', 'receipt') }}">Receipt Vouchers</a></li>
                                <li><a class="nav-link" href="{{ route('vouchers.index', 'payment') }}">Payment / Expense Vouchers</a></li>
                                <li class="{{ $vt === 'contra' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('vouchers.index', 'contra') }}">Cash ⇄ Bank Transfers</a></li>
                                <li class="{{ $vt === 'journal' ? 'nav-active' : '' }}"><a class="nav-link" href="{{ route('vouchers.index', 'journal') }}">Journal Vouchers</a></li>
                            @endcan
                        </ul>
                    </li>
                    @endcanany

                    @canany(['reports.sales', 'reports.purchases', 'reports.inventory', 'reports.accounts', 'reports.profit'])
                    <li class="{{ $is('reports.*') }}">
                        <a class="nav-link" href="{{ route('reports.index') }}"><i class="fas fa-chart-line"></i><span>Reports</span></a>
                    </li>
                    @endcanany

                    @canany(['settings.manage', 'users.view', 'roles.view', 'branches.view'])
                    <li class="nav-parent {{ $open('settings.*') }} {{ in_array(request()->route('master'), ['price-levels', 'tax-rates']) ? 'nav-expanded nav-active' : '' }}">
                        <a class="nav-link" href="#"><i class="fas fa-cog"></i><span>Settings</span></a>
                        <ul class="nav nav-children">
                            @can('settings.manage')<li class="{{ $is('settings.business') }}"><a class="nav-link" href="{{ route('settings.business') }}">Business & Features</a></li>@endcan
                            @can('branches.view')<li class="{{ $is('settings.branches.*') }}"><a class="nav-link" href="{{ route('settings.branches.index') }}">Branches & Warehouses</a></li>@endcan
                            @can('users.view')<li class="{{ $is('settings.users.*') }}"><a class="nav-link" href="{{ route('settings.users.index') }}">Users</a></li>@endcan
                            @can('roles.view')<li class="{{ $is('settings.roles.*') }}"><a class="nav-link" href="{{ route('settings.roles.index') }}">Roles & Permissions</a></li>@endcan
                            @can('settings.manage')
                                @feature('price_levels')<li><a class="nav-link" href="{{ route('masters.index', 'price-levels') }}">Price Levels</a></li>@endfeature
                                @feature('tax')<li><a class="nav-link" href="{{ route('masters.index', 'tax-rates') }}">Tax Rates</a></li>@endfeature
                            @endcan
                        </ul>
                    </li>
                    @endcanany
                </ul>
            </nav>
        </div>
    </div>
</aside>
