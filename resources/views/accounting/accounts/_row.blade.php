<tr class="lvl-{{ min($level, 3) }} {{ $a->is_group ? 'group' : '' }} {{ $a->is_active ? '' : 'text-muted' }}">
    <td>
        <span class="text-muted me-2">{{ $a->code }}</span>
        @if ($a->is_group)<i class="far fa-folder-open me-1"></i>@endif
        {{ $a->name }}
        @if ($a->system_key)<i class="fas fa-lock text-muted ms-1 small" title="System account"></i>@endif
        @if ($a->subtype && in_array($a->subtype, ['cash', 'bank']))<span class="badge bg-light text-dark ms-1">{{ $a->subtype }}</span>@endif
    </td>
    <td class="small text-muted">{{ \App\Models\Account::TYPES[$a->type] }}</td>
    <td class="num {{ $balance < 0 ? 'text-danger' : '' }}">
        @if (! $a->is_group)
            <a href="{{ route('reports.ledger', ['account_id' => $a->id]) }}" class="text-reset">{{ money($balance) }}</a>
        @else
            {{ money($balance) }}
        @endif
    </td>
    <td class="text-end text-nowrap">
        @if ($a->is_group)
            @can('accounts.create')<a href="{{ route('accounts.create', ['parent' => $a->id]) }}" title="Add sub-account" class="me-2"><i class="fas fa-plus"></i></a>@endcan
        @endif
        @can('accounts.edit')<a href="{{ route('accounts.edit', $a) }}" title="Edit"><i class="fas fa-edit"></i></a>@endcan
    </td>
</tr>
