@extends('layouts.app')
@section('title', $def['title'])
@section('content')
<div class="row">
    <div class="col-lg-8">
        <x-card :body="false">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr>@foreach ($def['fields'] as [$label])<th>{{ $label }}</th>@endforeach<th style="width:130px"></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php $fid = 'upd-'.$row->id; @endphp
                    <tr>
                        @foreach ($def['fields'] as $field => [$label, $type])
                            <td>
                                @if ($type === 'checkbox')
                                    <input type="hidden" name="{{ $field }}" value="0" form="{{ $fid }}"><input type="checkbox" class="form-check-input" name="{{ $field }}" value="1" form="{{ $fid }}" @checked($row->{$field})>
                                @elseif ($type === 'parent')
                                    <select name="{{ $field }}" form="{{ $fid }}" class="form-select form-select-sm"><option value="">—</option>@foreach ($parents as $id => $n)@if ($id !== $row->id)<option value="{{ $id }}" @selected($row->parent_id === $id)>{{ $n }}</option>@endif @endforeach</select>
                                @else
                                    <input type="{{ $type }}" step="any" name="{{ $field }}" form="{{ $fid }}" value="{{ $row->{$field} }}" class="form-control form-control-sm" required>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('masters.update', [$master, $row->id]) }}" id="{{ $fid }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-outline-primary">Save</button></form>
                            <x-delete :action="route('masters.destroy', [$master, $row->id])" class="btn btn-link p-0 text-danger ms-2" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($def['fields']) + 1 }}" class="text-muted text-center py-4">Nothing here yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
    <div class="col-lg-4">
        <x-card title="Add new">
            <form method="POST" action="{{ route('masters.store', $master) }}" class="row">
                @csrf
                @foreach ($def['fields'] as $field => [$label, $type])
                    @if ($type === 'checkbox')
                        <x-check :name="$field" :label="$label" col="col-12" :checked="$field === 'is_active'" />
                    @elseif ($type === 'parent')
                        <x-select :name="$field" :label="$label" :options="$parents" placeholder="— none (top level) —" col="col-12" />
                    @else
                        <x-input :name="$field" :label="$label" :type="$type" step="any" col="col-12" required />
                    @endif
                @endforeach
                <div class="col-12"><button class="btn btn-primary w-100">Add</button></div>
            </form>
        </x-card>
    </div>
</div>
@endsection
