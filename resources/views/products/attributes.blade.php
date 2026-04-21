@extends('layouts.app')

@section('title', 'Products | Attributes')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header">
        <div style="display: flex; justify-content: space-between;">
          <h2 class="card-title">All Attributes</h2>
          <div class="d-flex gap-2">
            <button type="button" class="modal-with-form btn btn-secondary" href="#purityModal">
              Purity
            </button>
            <button type="button" class="modal-with-form btn btn-primary" href="#addAttributeModal">
              <i class="fas fa-plus"></i> Add Attribute
            </button>
          </div>
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-attributes">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Values</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($attributes as $attribute)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $attribute->name }}</td>
                <td>
                  @foreach ($attribute->values as $value)
                    <span class="badge bg-secondary">{{ $value->value }}</span>
                  @endforeach
                </td>
                <td>
                  <a class="text-primary modal-with-form" href="#editAttributeModal{{ $attribute->id }}">
                    <i class="fa fa-edit"></i>
                  </a>
                  <form action="{{ route('attributes.destroy', $attribute->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>

              {{-- Edit Attribute Modal --}}
              <div id="editAttributeModal{{ $attribute->id }}" class="modal-block modal-block-warning mfp-hide">
                <section class="card">
                  <form method="POST" action="{{ route('attributes.update', $attribute->id) }}">
                    @csrf
                    @method('PUT')
                    <header class="card-header">
                      <h2 class="card-title">Edit Attribute</h2>
                    </header>
                    <div class="card-body">
                      <div class="form-group mb-3">
                        <label>Attribute Name</label>
                        <input type="text" class="form-control" name="name" value="{{ $attribute->name }}" required>
                      </div>
                      <div class="form-group mb-3">
                        <label>Slug</label>
                        <input type="text" class="form-control" name="slug" value="{{ $attribute->slug }}" required>
                      </div>
                      <div class="form-group mb-3">
                        <label>Attribute Values (comma-separated)</label>
                        <input type="text" class="form-control" name="values" value="{{ $attribute->values->pluck('value')->implode(', ') }}" required>
                      </div>
                    </div>
                    <footer class="card-footer">
                      <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-warning">Update</button>
                        <button class="btn btn-default modal-dismiss">Cancel</button>
                      </div>
                    </footer>
                  </form>
                </section>
              </div>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>


    {{-- ================================================================ --}}
    {{-- PURITY SETTINGS MODAL                                            --}}
    {{-- ================================================================ --}}
    <div id="purityModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card" style="width:650px;max-width:100%">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title mb-0"><i class="fas fa-gem me-2"></i>Purity Settings</h2>
          <button type="button" class="btn btn-primary" onclick="showPurityAdd()">
            Add New
          </button>
        </header>

        <div class="card-body">

          {{-- Flash messages — auto-reopen modal if present --}}
          @if(session('purity_success'))
            <div class="alert alert-success alert-dismissible py-2 mb-3">
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              {{ session('purity_success') }}
            </div>
          @endif
          @if(session('purity_error'))
            <div class="alert alert-danger alert-dismissible py-2 mb-3">
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              {{ session('purity_error') }}
            </div>
          @endif

          {{-- Inline add row (hidden by default) --}}
          <div id="purityAddRow" class="card card-body bg-light py-2 mb-3 d-none">
            <form method="POST" action="{{ route('purities.store') }}" onkeydown="return event.key !== 'Enter';">
              @csrf
              <div class="row g-2 align-items-end">
                <div class="col-5">
                  <label class="form-label small mb-1">Label <span class="text-danger">*</span></label>
                  <input type="text" class="form-control form-control-sm" name="label"
                         placeholder="22K (92%)" required>
                </div>
                <div class="col-3">
                  <label class="form-label small mb-1">Value (0–1) <span class="text-danger">*</span></label>
                  <input type="number" class="form-control form-control-sm" name="value"
                         step="0.0001" min="0.0001" max="1" placeholder="0.9200" required>
                </div>
                <div class="col-2">
                  <label class="form-label small mb-1">Sort</label>
                  <input type="number" class="form-control form-control-sm" name="sort_order" value="0" min="0">
                </div>
                <div class="col-2 d-flex gap-1">
                  <button type="submit" class="btn btn-success btn-sm flex-fill">Save</button>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="hidePurityAdd()">✕</button>
                </div>
              </div>
            </form>
          </div>

          {{-- Purity list --}}
          <table class="table table-bordered table-striped table-sm mb-0">
            <thead>
              <tr>
                <th>Label</th>
                <th class="text-center" style="width:90px">Value</th>
                <th class="text-center" style="width:55px">Sort</th>
                <th class="text-center" style="width:70px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($purities as $p)
                <tr>
                  {{-- View cells --}}
                  <td class="pv-{{ $p->id }}">{{ $p->label }}</td>
                  <td class="pv-{{ $p->id }} text-center">{{ $p->value }}</td>
                  <td class="pv-{{ $p->id }} text-center">{{ $p->sort_order }}</td>

                  {{-- Inline edit form (replaces all 3 view cells when active) --}}
                  <td colspan="3" class="pe-{{ $p->id }} d-none p-1">
                    <form method="POST" action="{{ route('purities.update', $p->id) }}"
                          class="d-flex gap-1 align-items-center"
                          onkeydown="return event.key !== 'Enter';">
                      @csrf @method('PUT')
                      <input type="text"   class="form-control form-control-sm" name="label"
                             value="{{ $p->label }}" required style="flex:2;min-width:0">
                      <input type="number" class="form-control form-control-sm" name="value"
                             value="{{ $p->value }}" step="0.0001" min="0.0001" max="1"
                             required style="width:85px">
                      <input type="number" class="form-control form-control-sm" name="sort_order"
                             value="{{ $p->sort_order }}" min="0" style="width:55px">
                      <button type="submit" class="btn btn-success btn-sm text-nowrap">Save</button>
                      <button type="button" class="btn btn-secondary btn-sm"
                              onclick="cancelPurityEdit({{ $p->id }})">✕</button>
                    </form>
                  </td>

                  {{-- Action buttons --}}
                  <td class="pv-{{ $p->id }} text-center text-nowrap">
                    <a href="javascript:void(0)" class="text-primary me-1"
                       onclick="startPurityEdit({{ $p->id }})" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('purities.destroy', $p->id) }}" method="POST"
                          class="d-inline" onsubmit="return confirm('Delete this purity?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-link p-0 text-danger" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-3">
                    No purities yet. Click <strong>Add New</strong> above.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <footer class="card-footer text-end">
          <button type="button" class="btn btn-default modal-dismiss">Close</button>
        </footer>
      </section>
    </div>


    {{-- ================================================================ --}}
    {{-- ADD ATTRIBUTE MODAL (unchanged)                                  --}}
    {{-- ================================================================ --}}
    <div id="addAttributeModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="post" action="{{ route('attributes.store') }}">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Attribute</h2>
          </header>
          <div class="card-body">
            <div class="form-group mb-3">
              <label>Attribute Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="form-group mb-3">
              <label>Slug (unique)</label>
              <input type="text" class="form-control" name="slug" required>
            </div>
            <div class="form-group mb-3">
              <label>Attribute Values (comma-separated)</label>
              <input type="text" class="form-control" name="values" placeholder="Red, Blue, Small, Large" required>
            </div>
          </div>
          <footer class="card-footer">
            <div class="col-md-12 text-end">
              <button type="submit" class="btn btn-primary">Create</button>
              <button class="btn btn-default modal-dismiss">Cancel</button>
            </div>
          </footer>
        </form>
      </section>
    </div>

  </div>
</div>

<script>
  // ── Purity add row ────────────────────────────────────────────────────────────
  function showPurityAdd() {
      document.getElementById('purityAddRow').classList.remove('d-none');
  }
  function hidePurityAdd() {
      document.getElementById('purityAddRow').classList.add('d-none');
  }

  // ── Purity inline edit ────────────────────────────────────────────────────────
  function startPurityEdit(id) {
      document.querySelectorAll('.pv-' + id).forEach(el => el.classList.add('d-none'));
      document.querySelectorAll('.pe-' + id).forEach(el => el.classList.remove('d-none'));
  }
  function cancelPurityEdit(id) {
      document.querySelectorAll('.pe-' + id).forEach(el => el.classList.add('d-none'));
      document.querySelectorAll('.pv-' + id).forEach(el => el.classList.remove('d-none'));
  }

  // ── Re-open purity modal after redirect if there is a flash message ───────────
  @if(session('purity_success') || session('purity_error'))
  document.addEventListener('DOMContentLoaded', function () {
      if (typeof $.fn.magnificPopup !== 'undefined') {
          $.magnificPopup.open({ items: { src: '#purityModal' }, type: 'inline' });
      }
  });
  @endif
</script>
@endsection