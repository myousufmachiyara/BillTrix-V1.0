<aside id="sidebar-left" class="sidebar-left">
  <div class="sidebar-header">
    <div class="sidebar-title" style="display:flex;justify-content:space-between;">
      <a href="{{ route('dashboard') }}" class="logo">
        <img src="/assets/img/billtrix-logo-1.png" class="sidebar-logo" alt="BillTrix Logo" />
      </a>
      <div class="d-md-none toggle-sidebar-left col-1"
           data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
        <i class="fas fa-times" aria-label="Toggle sidebar"></i>
      </div>
    </div>
    <div class="sidebar-toggle d-none d-md-block"
         data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
      <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
    </div>
  </div>

  <div class="nano">
    <div class="nano-content">
      <nav id="menu" class="nav-main" role="navigation">
        <ul class="nav nav-main">

          {{-- Dashboard --}}
          <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('dashboard') }}">
              <i class="fa fa-home"></i> <span>Dashboard</span>
            </a>
          </li>

          {{-- 1. Accounts --}}
          @if(auth()->user()->canany(['heads_of_accounts.index','sub_heads.index','coa.index']))
          <li class="nav-parent {{ request()->routeIs('hoa.*','shoa.*','coa.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-book"></i> <span>Accounts</span></a>
            <ul class="nav nav-children">
              @can('heads_of_accounts.index')
              <li class="{{ request()->routeIs('hoa.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('hoa.index') }}">Heads</a>
              </li>
              @endcan
              @can('sub_heads.index')
              <li class="{{ request()->routeIs('shoa.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('shoa.index') }}">Sub heads</a>
              </li>
              @endcan
              @can('coa.index')
              <li class="{{ request()->routeIs('coa.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('coa.index') }}">Chart of accounts</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 2. Users, Roles & Permissions --}}
          @if(auth()->user()->canany(['users.index','roles.index','permissions.index']))
          <li class="nav-parent {{ request()->routeIs('users.*','roles.*','permissions.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-user-shield"></i> <span>Users</span></a>
            <ul class="nav nav-children">
              @can('users.index')
              <li class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('users.index') }}">All users</a>
              </li>
              @endcan
              @can('roles.index')
              <li class="{{ request()->routeIs('roles.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('roles.index') }}">Roles</a>
              </li>
              @endcan
              @can('permissions.index')
              <li class="{{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('permissions.index') }}">Permissions</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 3. Products --}}
          @if(auth()->user()->canany(['product_categories.index','product_subcategories.index','attributes.index','products.index']))
          <li class="nav-parent {{ request()->routeIs('product_categories.*','product_subcategories.*','attributes.*','products.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-layer-group"></i> <span>Products</span></a>
            <ul class="nav nav-children">
              @can('product_categories.index')
              <li class="{{ request()->routeIs('product_categories.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('product_categories.index') }}">Categories</a>
              </li>
              @endcan
              @can('product_subcategories.index')
              <li class="{{ request()->routeIs('product_subcategories.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('product_subcategories.index') }}">Sub categories</a>
              </li>
              @endcan
              @can('attributes.index')
              <li class="{{ request()->routeIs('attributes.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('attributes.index') }}">Attributes</a>
              </li>
              @endcan
              @can('products.index')
              <li class="{{ request()->routeIs('products.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('products.index') }}">All products</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 4. Purchase --}}
          @if(auth()->user()->canany(['purchase_orders.index','purchase_invoices.index','purchase_returns.index']))
          <li class="nav-parent {{ request()->routeIs('purchase_orders.*','purchase_invoices.*','purchase_returns.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-shopping-cart"></i> <span>Purchase</span></a>
            <ul class="nav nav-children">
              @can('purchase_orders.index')
              <li class="{{ request()->routeIs('purchase_orders.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('purchase_orders.index') }}">Orders</a>
              </li>
              @endcan
              @can('purchase_invoices.index')
              <li class="{{ request()->routeIs('purchase_invoices.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('purchase_invoices.index') }}">Invoices</a>
              </li>
              @endcan
              @can('purchase_returns.index')
              <li class="{{ request()->routeIs('purchase_returns.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('purchase_returns.index') }}">Returns</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 5. Production --}}
          @if(auth()->user()->canany(['production_orders.index','production_receiving.index','production_returns.index']))
          <li class="nav-parent {{ request()->routeIs('production_orders.*','production_receiving.*','production_returns.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-industry"></i> <span>Production</span></a>
            <ul class="nav nav-children">
              @can('production_orders.index')
              <li class="{{ request()->routeIs('production_orders.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('production_orders.index') }}">Orders</a>
              </li>
              @endcan
              @can('production_receiving.index')
              <li class="{{ request()->routeIs('production_receiving.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('production_receiving.index') }}">Receiving</a>
              </li>
              @endcan
              @can('production_returns.index')
              <li class="{{ request()->routeIs('production_returns.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('production_returns.index') }}">Returns</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 6. Inventory --}}
          @if(auth()->user()->canany(['locations.index','stock_in_out.index','gatepasses.index','stock_transfers.index']))
          <li class="nav-parent {{ request()->routeIs('locations.*','stock_in_out.*','gatepasses.*','stock_transfers.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-warehouse"></i> <span>Inventory</span></a>
            <ul class="nav nav-children">
              @can('locations.index')
              <li class="{{ request()->routeIs('locations.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('locations.index') }}">Locations</a>
              </li>
              @endcan
              @can('stock_in_out.index')
              <li class="{{ request()->routeIs('stock_in_out.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('stock_in_out.index') }}">Stock in / out</a>
              </li>
              @endcan
              @can('gatepasses.index')
              <li class="{{ request()->routeIs('gatepasses.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('gatepasses.index') }}">Gatepasses</a>
              </li>
              @endcan
              @can('stock_transfers.index')
              <li class="{{ request()->routeIs('stock_transfers.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('stock_transfers.index') }}">Transfers</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 7. Branches --}}
          @if(auth()->user()->canany(['branches.index','branch_users.index']))
          <li class="nav-parent {{ request()->routeIs('branches.*','branch_users.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-code-branch"></i> <span>Branches</span></a>
            <ul class="nav nav-children">
              @can('branches.index')
              <li class="{{ request()->routeIs('branches.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('branches.index') }}">All branches</a>
              </li>
              @endcan
              @can('branch_users.index')
              <li class="{{ request()->routeIs('branch_users.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('branch_users.index') }}">Branch users</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 8. Sales --}}
          @if(auth()->user()->canany(['quotations.index','sale_orders.index','sale_invoices.index','sale_returns.index']))
          <li class="nav-parent {{ request()->routeIs('quotations.*','sale_orders.*','sale_invoices.*','sale_returns.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-cash-register"></i> <span>Sales</span></a>
            <ul class="nav nav-children">
              @can('quotations.index')
              <li class="{{ request()->routeIs('quotations.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('quotations.index') }}">Quotations</a>
              </li>
              @endcan
              @can('sale_orders.index')
              <li class="{{ request()->routeIs('sale_orders.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('sale_orders.index') }}">Orders</a>
              </li>
              @endcan
              @can('sale_invoices.index')
              <li class="{{ request()->routeIs('sale_invoices.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('sale_invoices.index') }}">Invoices</a>
              </li>
              @endcan
              @can('sale_returns.index')
              <li class="{{ request()->routeIs('sale_returns.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('sale_returns.index') }}">Returns</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 9. Finance --}}
          @if(auth()->user()->canany(['vouchers.index','pdc.index']))
          <li class="nav-parent {{ request()->routeIs('vouchers.*','pdc.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-money-check-alt"></i> <span>Finance</span></a>
            <ul class="nav nav-children">
              @can('vouchers.index')
              <li class="{{ request()->routeIs('vouchers.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('vouchers.index', 'payment') }}">Vouchers</a>
              </li>
              @endcan
              @can('pdc.index')
              <li class="{{ request()->routeIs('pdc.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('pdc.index') }}">PDC</a>
              </li>
              @endcan
            </ul>
          </li>
          @endif

          {{-- 10. Reports --}}
          @if(auth()->user()->canany([
            'reports.inventory','reports.item_ledger','reports.stock_in_hand',
            'reports.purchase','reports.production','reports.sales',
            'reports.ledger','reports.trial_balance','reports.pnl',
            'reports.payables','reports.receivables'
          ]))
          <li class="nav-parent {{ request()->routeIs('reports.*') ? 'nav-expanded nav-active' : '' }}">
            <a class="nav-link" href="#"><i class="fa fa-chart-bar"></i> <span>Reports</span></a>
            <ul class="nav nav-children">

              {{-- Inventory reports --}}
              @canany(['reports.inventory','reports.item_ledger','reports.stock_in_hand'])
              <li class="nav-parent">
                <a class="nav-link" href="#">Inventory</a>
                <ul class="nav nav-children">
                  @can('reports.inventory')
                  <li><a class="nav-link" href="{{ route('reports.inventory') }}">Summary</a></li>
                  @endcan
                  @can('reports.item_ledger')
                  <li><a class="nav-link" href="{{ route('reports.item_ledger') }}">Item ledger</a></li>
                  @endcan
                  @can('reports.stock_in_hand')
                  <li><a class="nav-link" href="{{ route('reports.stock_in_hand') }}">Stock in hand</a></li>
                  @endcan
                </ul>
              </li>
              @endcanany

              @can('reports.purchase')
              <li class="{{ request()->routeIs('reports.purchase') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('reports.purchase') }}">Purchase</a>
              </li>
              @endcan

              @can('reports.production')
              <li class="{{ request()->routeIs('reports.production') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('reports.production') }}">Production</a>
              </li>
              @endcan

              @can('reports.sales')
              <li class="{{ request()->routeIs('reports.sales') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('reports.sales') }}">Sales</a>
              </li>
              @endcan

              {{-- Accounting reports --}}
              @canany(['reports.ledger','reports.trial_balance','reports.pnl','reports.payables','reports.receivables'])
              <li class="nav-parent">
                <a class="nav-link" href="#">Accounts</a>
                <ul class="nav nav-children">
                  @can('reports.ledger')
                  <li><a class="nav-link" href="{{ route('reports.ledger') }}">Ledger</a></li>
                  @endcan
                  @can('reports.trial_balance')
                  <li><a class="nav-link" href="{{ route('reports.trial_balance') }}">Trial balance</a></li>
                  @endcan
                  @can('reports.pnl')
                  <li><a class="nav-link" href="{{ route('reports.pnl') }}">P&amp;L statement</a></li>
                  @endcan
                  @can('reports.payables')
                  <li><a class="nav-link" href="{{ route('reports.payables') }}">Payables</a></li>
                  @endcan
                  @can('reports.receivables')
                  <li><a class="nav-link" href="{{ route('reports.receivables') }}">Receivables</a></li>
                  @endcan
                </ul>
              </li>
              @endcanany

            </ul>
          </li>
          @endif

        </ul>
      </nav>
    </div>

    <script>
      if (typeof localStorage !== 'undefined') {
        var pos = localStorage.getItem('sidebar-left-position');
        if (pos !== null) {
          document.querySelector('#sidebar-left .nano-content').scrollTop = pos;
        }
      }
    </script>
  </div>
</aside>