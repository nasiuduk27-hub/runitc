<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-5 py-4">
        <p class="text-sm font-bold text-gray-800">{{ $title }}</p>
        @if (! empty($subtitle))
            <p class="mt-0.5 text-xs text-gray-400">{{ $subtitle }}</p>
        @endif
    </div>
    @include('cooperative.skips.partials.schedule-table', ['rows' => $rows, 'statusBadge' => $statusBadge])
</div>
