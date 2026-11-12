@extends('layouts.app')
@section('title', __('Backup & Export'))
@section('content')
<div class="row">
    <div class="col-lg-6">
        <x-card :title="__('Download your data')">
            <p>{{ __('Get a copy of everything your business has in BillTrix — products, customers, suppliers, invoices, vouchers, stock and the audit trail — as one zip file.') }}</p>
            <p class="small text-muted">{{ __('Each table is a JSON file that opens in any text editor or can be loaded into another system. Passwords and the FBR token are left out.') }}</p>
            <a href="{{ route('settings.backup.download') }}" class="btn btn-primary"><i class="fas fa-download"></i> {{ __('Download data (.zip)') }}</a>
        </x-card>
        <x-card :title="__('Spreadsheets')">
            <p class="mb-2">{{ __('Most lists and reports also have an Excel button for a quick .xlsx copy.') }}</p>
            <a href="{{ route('products.index', ['export' => 'xlsx']) }}" class="btn btn-sm btn-default"><i class="fas fa-file-excel"></i> {{ __('Products') }}</a>
            <a href="{{ route('parties.index', ['type' => 'customer', 'export' => 'xlsx']) }}" class="btn btn-sm btn-default"><i class="fas fa-file-excel"></i> {{ __('Customers') }}</a>
            <a href="{{ route('parties.index', ['type' => 'supplier', 'export' => 'xlsx']) }}" class="btn btn-sm btn-default"><i class="fas fa-file-excel"></i> {{ __('Suppliers') }}</a>
            <a href="{{ route('reports.trial-balance', ['export' => 'xlsx']) }}" class="btn btn-sm btn-default"><i class="fas fa-file-excel"></i> {{ __('Trial balance') }}</a>
        </x-card>
    </div>
    <div class="col-lg-6">
        <x-card :title="__('Server backups')" :body="false">
            <div class="card-body">
                <p class="mb-0 small text-muted">{{ __('The whole database is backed up every night at 1:30 am and kept for 14 days (command: billtrix:backup). Ask your administrator to copy these files to another place regularly.') }}</p>
            </div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>{{ __('File') }}</th><th>{{ __('Taken') }}</th><th class="num">{{ __('Size') }}</th></tr></thead>
                <tbody>
                @forelse ($server as $b)
                    <tr><td class="small">{{ $b['name'] }}</td><td>{{ $b['at']->format('d-m-Y H:i') }}</td><td class="num">{{ number_format($b['size'] / 1048576, 2) }} {{ __('MB') }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-warning text-center py-3">{{ __('No server backups yet. Make sure the scheduler is running.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</div>
@endsection
