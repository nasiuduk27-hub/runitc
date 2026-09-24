<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-4 py-3">
        <p class="text-xs font-bold uppercase tracking-wide text-gray-700">{{ $title }}</p>
        @if (! empty($subtitle))
            <p class="mt-0.5 text-[11px] text-gray-400">{{ $subtitle }}</p>
        @endif
    </div>
    @include('credit-union.skips.partials.schedule-table', ['rows' => $rows, 'statusBadge' => $statusBadge])
</div>
