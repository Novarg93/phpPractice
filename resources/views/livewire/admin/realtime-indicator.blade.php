@php
    $hasOrdersIndex = \Illuminate\Support\Facades\Route::has('filament.admin.resources.orders.index');
@endphp

<div
    x-data
    x-on:realtime-contact.window="$wire.refreshCounts()"
    x-on:realtime-orders.window="$wire.refreshCounts()"
    style="display:flex;align-items:center;gap:1rem;margin-left:1rem;"
>
    {{-- Messages --}}
    <a href="{{ route('filament.admin.resources.contact-messages.index') }}"
       aria-label="Contact messages"
       style="display:inline-flex;align-items:center;gap:.375rem;text-decoration:none;"
    >
        <span style="color: {{ $messages > 0 ? '#dc2626' : '#6b7280' }};">
            <x-filament::icon icon="heroicon-o-inbox" class="h-5 w-5" />
        </span>
        @if ($messages > 0)
            <span style="font-size:12px;font-weight:600;color:#dc2626;line-height:1;">{{ $messages }}</span>
        @endif
    </a>

    {{-- Orders --}}
    @if ($hasOrdersIndex)
        <a href="{{ route('filament.admin.resources.orders.index') }}"
           aria-label="Orders"
           style="display:inline-flex;align-items:center;gap:.375rem;text-decoration:none;"
        >
            <span style="color: {{ $orders > 0 ? '#d97706' : '#6b7280' }};">
                <x-filament::icon icon="heroicon-o-shopping-cart" class="h-5 w-5" />
            </span>
            @if ($orders > 0)
                <span style="font-size:12px;font-weight:600;color:#d97706;line-height:1;">{{ $orders }}</span>
            @endif
        </a>
    @endif
</div>