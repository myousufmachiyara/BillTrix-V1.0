@props(['title' => null, 'actions' => null, 'body' => true])
<section {{ $attributes->merge(['class' => 'card mb-4']) }}>
    @if ($title || $actions)
        <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h2 class="card-title mb-0">{{ (is_string($title) ? __($title) : $title) }}</h2>
            @if ($actions)<div class="d-flex flex-wrap gap-2">{{ $actions }}</div>@endif
        </header>
    @endif
    @if ($body)
        <div class="card-body">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
