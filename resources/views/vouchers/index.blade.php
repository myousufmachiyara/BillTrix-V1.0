@extends('layouts.app')

@section('title', ucfirst($type) . ' Vouchers')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">{{ ucfirst($type) }} Vouchers</h2>
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> Add New
        </button>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0" id="voucher-datatable">
            <thead>
              <tr>
                <th>Voch#</th>
                <th>Date</th>
                <th>Account Debit</th>
                <th>Account Credit</th>
                <th>Remarks</th>
                <th>Amount</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($vouchers as $row)
                <tr>
                  <td>{{ $row->id }}</td>
                  <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                  <td>{{ $row->debitAccount->name ?? 'N/A' }}</td>
                  <td>{{ $row->creditAccount->name ?? 'N/A' }}</td>
                  <td>{{ $row->remarks }}</td>
                  <td><strong>{{ number_format($row->amount, 0, '.', ',') }}</strong></td>
                  <td class="actions">
                    <a class="text-success" href="{{ route('vouchers.print', ['type' => $type, 'id' => $row->id]) }}" target="_blank"><i class="fas fa-print"></i></a>
                    <a class="text-primary modal-with-form" onclick="getVoucherDetails({{ $row->id }})" href="#updateModal"><i class="fas fa-edit"></i></a>
                    <a class="btn btn-link p-0 m-0 text-danger" onclick="setDeleteId({{ $row->id }})" href="#deleteModal"><i class="fas fa-trash-alt"></i></a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    {{-- ── ADD MODAL ──────────────────────────────────────────────── --}}
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="post" action="{{ route('vouchers.store', $type) }}"
              enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
          @csrf
          <input type="hidden" name="voucher_type" value="{{ $type }}">
          <header class="card-header">
            <h2 class="card-title">Add {{ ucfirst($type) }} Voucher</h2>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Date</label>
                <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" required>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Account Debit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_dr_sid" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Account Credit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_cr_sid" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Amount <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount" step="any" value="0" required>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Attachments</label>
                <input type="file" class="form-control" name="att[]" multiple
                       accept=".zip,application/pdf,image/png,image/jpeg">
              </div>
              <div class="col-lg-12 mb-2">
                <label>Remarks</label>
                <textarea rows="3" class="form-control" name="remarks"></textarea>
              </div>
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Add {{ ucfirst($type) }} Voucher</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    {{-- ── UPDATE MODAL ────────────────────────────────────────────── --}}
    <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="updateForm" enctype="multipart/form-data"
              onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')
          <input type="hidden" name="voucher_type" value="{{ $type }}">
          <header class="card-header">
            <h2 class="card-title">Update {{ ucfirst($type) }} Voucher</h2>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Voucher ID</label>
                <input type="text" class="form-control" id="update_id" disabled>
                <input type="hidden" name="voucher_id" id="update_id_hidden">
              </div>
              <div class="col-lg-6 mb-2">
                <label>Date</label>
                <input type="date" class="form-control" name="date" id="update_date" required>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Account Debit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_dr_sid" id="update_ac_dr_sid" required>
                  <option value="" disabled>Select Account</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Account Credit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_cr_sid" id="update_ac_cr_sid" required>
                  <option value="" disabled>Select Account</option>
                  @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Amount <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount"
                       id="update_amount" step="any" required>
              </div>
              <div class="col-lg-6 mb-2">
                <label>Attachments</label>
                <input type="file" class="form-control" name="att[]" multiple
                       accept=".zip,application/pdf,image/png,image/jpeg">
              </div>
              <div class="col-lg-12 mb-2">
                <label>Remarks</label>
                <textarea rows="3" class="form-control" name="remarks" id="update_remarks"></textarea>
              </div>
            </div>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Update {{ ucfirst($type) }} Voucher</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    {{-- ── DELETE MODAL ────────────────────────────────────────────── --}}
    <div id="deleteModal" class="modal-block modal-block-warning mfp-hide">
      <section class="card">
        <form method="POST" id="deleteForm">
          @csrf
          @method('DELETE')
          <header class="card-header">
            <h2 class="card-title">Delete Voucher</h2>
          </header>
          <div class="card-body">
            <p>Are you sure you want to delete this voucher?</p>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-danger">Delete</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

  </div>
</div>

<style>
/* ── Prevent background scroll when any modal is open ──────────── */
/*
   overflow:hidden on body alone is not enough because this Porto
   theme sets overflow on several nested containers. We must block
   scroll on every element that can independently scroll.
*/
body.modal-open-noscroll {
    overflow: hidden !important;
    padding-right: var(--scrollbar-width, 0px); /* prevent layout jump */
}
body.modal-open-noscroll section.body,
body.modal-open-noscroll .inner-wrapper,
body.modal-open-noscroll .content-body,
body.modal-open-noscroll main,
body.modal-open-noscroll .page-wrapper {
    overflow: hidden !important;
}

/* ── Ensure the mfp overlay sits above everything ─────────────── */
.mfp-wrap { z-index: 10000 !important; }
.mfp-bg   { z-index: 9999  !important; }
</style>

<script>
// ────────────────────────────────────────────────────────────────
// FOCUS TRAP + SCROLL LOCK FOR MAGNIFIC POPUP MODALS
//
// Problems being solved:
//   1. Tab key cycles focus through the background page, not the modal
//   2. Mouse scroll wheel scrolls the background page behind the modal
//   3. Keyboard focus is never moved into the modal on open
//
// Approach:
//   - On mfp open:  lock body scroll, measure scrollbar width to avoid
//     layout shift, move focus to the first focusable element in the modal
//   - On Tab/Shift+Tab: intercept and keep focus inside the modal
//   - On mfp close: restore scroll and remove keydown trap
// ────────────────────────────────────────────────────────────────

(function ($) {

    // Focusable elements we allow Tab to cycle through
    var FOCUSABLE = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(', ');

    var trapHandler = null; // holds the active keydown listener so we can remove it

    // Measure scrollbar width once (prevents layout jump on body overflow:hidden)
    function getScrollbarWidth() {
        var div = document.createElement('div');
        div.style.cssText = 'width:100px;height:100px;overflow:scroll;position:absolute;top:-9999px';
        document.body.appendChild(div);
        var w = div.offsetWidth - div.clientWidth;
        document.body.removeChild(div);
        return w;
    }

    function lockScroll() {
        var sw = getScrollbarWidth();
        document.documentElement.style.setProperty('--scrollbar-width', sw + 'px');
        document.body.classList.add('modal-open-noscroll');
        // Belt-and-suspenders: block wheel events on the document itself
        // so no scrollable container underneath the overlay can respond
        document.addEventListener('wheel', preventScroll, { passive: false });
        document.addEventListener('touchmove', preventScroll, { passive: false });
    }

    function unlockScroll() {
        document.body.classList.remove('modal-open-noscroll');
        document.documentElement.style.removeProperty('--scrollbar-width');
        document.removeEventListener('wheel', preventScroll);
        document.removeEventListener('touchmove', preventScroll);
    }

    // Allows scrolling INSIDE the modal content, blocks everything else
    function preventScroll(e) {
        var mfpContent = document.querySelector('.mfp-content');
        // If the scroll target is inside the modal content, allow it
        if (mfpContent && mfpContent.contains(e.target)) return;
        e.preventDefault();
    }

    function trapFocus(modalEl) {
        var focusable = Array.from(modalEl.querySelectorAll(FOCUSABLE))
                             .filter(function (el) { return el.offsetParent !== null; }); // only visible

        if (!focusable.length) return;

        var first = focusable[0];
        var last  = focusable[focusable.length - 1];

        // Move focus into the modal immediately
        // Small delay lets Magnific finish its DOM placement
        setTimeout(function () { first.focus(); }, 50);

        // Remove any previous trap
        if (trapHandler) document.removeEventListener('keydown', trapHandler);

        trapHandler = function (e) {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                // Shift+Tab: if focus is on first element, wrap to last
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                // Tab: if focus is on last element, wrap to first
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };

        document.addEventListener('keydown', trapHandler);
    }

    function releaseTrap() {
        if (trapHandler) {
            document.removeEventListener('keydown', trapHandler);
            trapHandler = null;
        }
    }

    // ── Hook into Magnific Popup callbacks ──────────────────────
    $(document).on('click', '.modal-with-form, .modal-with-zoom-anim', function () {
        // Magnific opens on the next tick — wait for it
        setTimeout(function () {
            var wrap = document.querySelector('.mfp-wrap');
            var content = wrap ? wrap.querySelector('.mfp-content') : null;
            var modal = content ? content.querySelector('.modal-block, .zoom-anim-dialog') : null;
            if (modal) {
                lockScroll();
                trapFocus(modal);
            }
        }, 80);
    });

    // Re-trap when update modal is opened via JS (getVoucherDetails)
    // because it doesn't go through the click handler above
    $(document).on('mfpOpen', function () {
        var wrap = document.querySelector('.mfp-wrap');
        var content = wrap ? wrap.querySelector('.mfp-content') : null;
        var modal = content ? content.querySelector('.modal-block, .zoom-anim-dialog') : null;
        if (modal) {
            lockScroll();
            trapFocus(modal);
        }
    });

    // Release on close
    $(document).on('click', '.modal-dismiss, .mfp-close', function () {
        releaseTrap();
        unlockScroll();
    });

    // Also release if clicking the dark overlay
    $(document).on('mfpClose', function () {
        releaseTrap();
        unlockScroll();
    });

    // Escape key — Magnific handles closing, we handle cleanup
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('modal-open-noscroll')) {
            releaseTrap();
            unlockScroll();
        }
    });

}(jQuery));

// ── Voucher detail loader ───────────────────────────────────────
function getVoucherDetails(id) {
    document.getElementById('updateForm').action = `/vouchers/{{ $type }}/${id}`;
    fetch(`/vouchers/{{ $type }}/${id}`)
        .then(function (res) { return res.json(); })
        .then(function (data) {
            document.getElementById('update_id').value        = id;
            document.getElementById('update_id_hidden').value = id;
            document.getElementById('update_date').value      = data.date;
            $('#update_ac_dr_sid').val(data.ac_dr_sid).trigger('change');
            $('#update_ac_cr_sid').val(data.ac_cr_sid).trigger('change');
            document.getElementById('update_amount').value  = data.amount;
            document.getElementById('update_remarks').value = data.remarks;
        });
}

function setDeleteId(id) {
    document.getElementById('deleteForm').action = `/vouchers/{{ $type }}/${id}`;
}

$(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    $('#voucher-datatable').DataTable({
        pageLength: 50,
        order: [[0, 'desc']],
    });
});
</script>
@endsection