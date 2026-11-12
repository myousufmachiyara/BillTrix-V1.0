@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="row">
    <div class="col-lg-6">
        <x-card title="Upload">
            <ol class="small">
                <li><a href="{{ route('imports.template', $type) }}"><i class="fas fa-file-excel"></i> {{ __('Download the template') }}</a> {{ __('and fill one row per') }} {{ $type === 'products' ? 'product' : 'customer / supplier' }}{{ __('. Columns marked * are required.') }}</li>
                @if ($type === 'products')
                    <li>{{ __('Rows whose') }} <strong>{{ __('SKU') }}</strong> already exists update that product; others are created. Missing categories are created; units must already exist.</li>
                    <li>{{ __('Opening quantity is posted as opening stock (new products only) into the default warehouse.') }}</li>
                @else
                    <li>{{ __('Rows whose') }} <strong>{{ __('Code') }}</strong> already exists update that record; blank codes are numbered automatically.</li>
                    <li>Opening balance: positive = they owe you, negative = you owe them.</li>
                @endif
                <li>{{ __('The whole file is checked first — if any row has a problem nothing is saved.') }}</li>
            </ol>
            <form method="POST" action="{{ route('imports.store', $type) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" required>
                <button class="btn btn-primary text-nowrap"><i class="fas fa-upload"></i> {{ __('Import') }}</button>
            </form>
        </x-card>
    </div>
    <div class="col-lg-6">
        @if (session('import_errors'))
            <x-card title="Rows to fix" :body="false">
                <table class="table table-sm mb-0">
                    @foreach (session('import_errors') as $row => $messages)
                        <tr><td class="text-nowrap fw-semibold">{{ __('Row') }} {{ $row }}</td><td>{!! collect($messages)->map(fn ($m) => e($m))->join('<br>') !!}</td></tr>
                    @endforeach
                </table>
            </x-card>
        @else
            <x-card title="Columns">
                <ul class="small mb-0">@foreach ($template['headings'] as $h)<li>{{ $h }}</li>@endforeach</ul>
            </x-card>
        @endif
    </div>
</div>
@endsection
