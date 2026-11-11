<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS · {{ $business->name }}</title>
    <link rel="shortcut icon" href="{{ asset('assets/img/favicon.png') }}">
    <link href="https://fonts.googleapis.com/css?family=Poppins:400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/font-awesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2-bootstrap-theme/select2-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pos.css') }}">
</head>
<body>
<div class="pos">
    <header class="pos-top">
        <a href="{{ route('dashboard') }}" class="brand"><img src="{{ asset('assets/img/billtrix-logo-1.png') }}" alt="BillTrix" height="26"></a>
        <span class="text-white-50 small d-none d-md-inline">{{ $business->name }} · {{ auth()->user()->branch?->name }}</span>
        <div class="ms-auto d-flex align-items-center gap-2">
            @if ($session)
                <span class="badge bg-success-subtle text-success-emphasis"><i class="fas fa-cash-register"></i> {{ $session->number }} · {{ $session->account->name }}</span>
            @endif
            <span class="text-white small"><i class="fas fa-user"></i> {{ auth()->user()->name }}</span>
            <button class="btn btn-sm btn-outline-light" id="btn-recent" title="Recent bills (F6)"><i class="fas fa-history"></i><span class="d-none d-lg-inline"> Bills</span></button>
            @if ($session)
                <a href="{{ route('cash-sessions.close', $session) }}" class="btn btn-sm btn-warning"><i class="fas fa-lock"></i><span class="d-none d-lg-inline"> Close register</span></a>
            @endif
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light" title="Back to BillTrix"><i class="fas fa-times"></i></a>
        </div>
    </header>

    <main class="pos-main">
        <section class="pos-left">
            <div class="pos-search">
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                    <input id="scan" class="form-control" placeholder="Scan barcode or type to search  (F2)" autocomplete="off" autofocus>
                    <button class="btn btn-outline-secondary" id="clear-search" type="button" title="Clear"><i class="fas fa-times"></i></button>
                </div>
                <div class="chips mt-2">
                    <button class="chip active" data-cat="">Top items</button>
                    @foreach ($categories as $c)<button class="chip" data-cat="{{ $c->id }}">{{ $c->name }}</button>@endforeach
                </div>
            </div>
            <div class="pos-grid" id="grid"></div>
        </section>

        <section class="pos-right">
            <div class="pos-customer">
                <div class="d-flex gap-2">
                    <div class="flex-grow-1"><select id="party" data-type="customer"></select></div>
                    <button class="btn btn-outline-secondary" id="walkin-btn" title="Back to walk-in"><i class="fas fa-user-slash"></i></button>
                </div>
                <div class="d-flex gap-2 mt-2" id="walkin-fields">
                    <input id="cust-name" class="form-control form-control-sm" placeholder="Customer name (optional)" maxlength="100">
                    <input id="cust-phone" class="form-control form-control-sm" placeholder="Phone" maxlength="30">
                </div>
            </div>
            <div class="pos-cart" id="cart">
                <div class="empty text-center text-muted py-5" id="cart-empty"><i class="fas fa-shopping-basket fa-3x mb-3 opacity-25"></i><div>Scan or tap a product to start</div></div>
            </div>
            <div class="pos-totals">
                <div class="d-flex justify-content-between"><span>Items</span><span id="t-count">0</span></div>
                <div class="d-flex justify-content-between"><span>Gross</span><span id="t-gross">0.00</span></div>
                <div class="d-flex justify-content-between text-danger"><span>Item discounts</span><span id="t-idisc">0.00</span></div>
                @if ($business->feature('tax'))<div class="d-flex justify-content-between"><span>Tax</span><span id="t-tax">0.00</span></div>@endif
                <div class="d-flex justify-content-between align-items-center mt-1"><span>Bill discount</span>
                    <div class="input-group input-group-sm" style="width:150px"><input id="disc" type="number" min="0" step="any" class="form-control text-end" value="0"><button class="btn btn-outline-secondary" id="disc-pct" type="button" title="Enter % instead">%</button></div></div>
                <div class="grand d-flex justify-content-between"><span>TOTAL</span><span id="t-total">0.00</span></div>
            </div>
            <div class="pos-actions">
                <button class="btn btn-outline-secondary" id="btn-hold" title="Hold bill (F7)"><i class="fas fa-pause"></i> Hold</button>
                <button class="btn btn-outline-secondary position-relative" id="btn-recall" title="Recall held bill (F8)"><i class="fas fa-play"></i> Recall <span class="badge bg-danger position-absolute top-0 start-100 translate-middle d-none" id="held-count">0</span></button>
                <button class="btn btn-outline-danger" id="btn-clear" title="Clear (Esc)"><i class="fas fa-trash"></i></button>
                <button class="btn btn-success btn-pay" id="btn-pay" disabled>PAY <small>(F9)</small></button>
            </div>
        </section>
    </main>
</div>

{{-- Payment --}}
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-7">
                        <div class="display-6 fw-bold mb-3">Total <span class="float-end" id="pay-total">0.00</span></div>
                        @foreach ($accounts as $acc)
                            <div class="input-group mb-2">
                                <span class="input-group-text" style="width:150px"><i class="fas {{ $acc->subtype === 'cash' ? 'fa-money-bill-wave' : 'fa-credit-card' }} me-2"></i>{{ $acc->name }}</span>
                                <input type="number" min="0" step="any" class="form-control form-control-lg text-end pay-amt" data-account="{{ $acc->id }}" data-cash="{{ $acc->subtype === 'cash' && (! $session || $session->account_id === $acc->id) ? 1 : 0 }}">
                                <button class="btn btn-outline-secondary pay-rest" type="button" title="Put the balance here">Rest</button>
                            </div>
                        @endforeach
                        <div class="d-flex flex-wrap gap-2 mt-2" id="quick-cash">
                            <button class="btn btn-light border" data-add="exact">Exact</button>
                            @foreach ([100, 500, 1000, 5000] as $n)<button class="btn btn-light border" data-add="{{ $n }}">+{{ number_format($n) }}</button>@endforeach
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="pay-summary">
                            <div class="d-flex justify-content-between"><span>Paying</span><strong id="pay-sum">0.00</strong></div>
                            <div class="d-flex justify-content-between text-danger" id="pay-due-row"><span>Remaining</span><strong id="pay-due">0.00</strong></div>
                            <div class="d-flex justify-content-between text-success fs-3 fw-bold" id="pay-change-row"><span>Change</span><span id="pay-change">0.00</span></div>
                        </div>
                        <div class="alert alert-warning small mt-3 d-none" id="credit-note"></div>
                        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="auto-print" checked><label class="form-check-label" for="auto-print">Print receipt</label></div>
                    </div>
                </div>
                <div class="alert alert-danger mt-3 d-none" id="pay-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                <button type="button" class="btn btn-success btn-lg px-5" id="btn-confirm"><i class="fas fa-check"></i> Complete sale <small>(Enter)</small></button>
            </div>
        </div>
    </div>
</div>

{{-- Recent bills --}}
<div class="modal fade" id="recentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Recent bills</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0"><input class="form-control rounded-0 border-0 border-bottom" id="recent-q" placeholder="Bill number…"><div class="list-group list-group-flush" id="recent-list"></div></div>
    </div></div>
</div>

{{-- Held bills --}}
<div class="modal fade" id="heldModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Held bills</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0"><div class="list-group list-group-flush" id="held-list"></div></div>
    </div></div>
</div>

<div class="toast-container position-fixed bottom-0 start-0 p-3"><div id="toast" class="toast align-items-center border-0" role="alert"><div class="d-flex"><div class="toast-body" id="toast-body"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>
<iframe id="print-frame" title="print" style="position:absolute;width:0;height:0;border:0;left:-9999px"></iframe>

<script>
    window.BT = { urls: { parties: @js(route('parties.lookup')), products: @js(route('products.lookup')) } };
    window.POS = {
        storeUrl: @js(route('pos.store')),
        recentUrl: @js(route('pos.recent')),
        warehouseId: @js($warehouse?->id),
        walkIn: @js($walkIn ? ['id' => $walkIn->id, 'text' => $walkIn->name, 'walk_in' => true, 'price_level_id' => $walkIn->price_level_id] : null),
        tax: @js($business->feature('tax')),
        inclusive: @js($business->feature('prices_include_tax')),
        belowCost: @js($business->feature('below_cost_warning') && ! auth()->user()->can('sales.below_cost')),
        creditSales: @js($business->feature('credit_sales')),
        holdKey: @js('bt-pos-held-'.auth()->id()),
        quick: @js($quick->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'category_id' => $p->category_id, 'base_unit_id' => $p->unit_id, 'unit_id' => $p->unit_id,
            'units' => $p->unitOptions(), 'sale_price' => (float) $p->sale_price, 'avg_cost' => (float) $p->avg_cost,
            'tax_rate' => (float) ($p->taxRate?->rate ?? 0), 'stock' => $p->track_stock ? (float) ($p->stock ?? 0) : null,
            'image' => $p->image_path ? asset('storage/'.$p->image_path) : null,
        ])->values()),
    };
</script>
<script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/js/billtrix.js') }}"></script>
<script src="{{ asset('assets/js/pos.js') }}"></script>
</body>
</html>
