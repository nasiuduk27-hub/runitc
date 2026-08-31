<div class="tab-content {{ $type === 'user_activity' ? 'hidden' : '' }}" id="tab_{{ $type }}">
    <div class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-gray-900">{{ $title }}</h2>
        <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-4">
            <div><label class="mb-1 block text-xs font-semibold text-gray-600">Date From</label><input type="date" id="{{ $prefix }}_date_from" value="{{ $defaultFrom }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-xs font-semibold text-gray-600">Date To</label><input type="date" id="{{ $prefix }}_date_to" value="{{ $defaultTo }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-xs font-semibold text-gray-600">Action</label><select id="{{ $prefix }}_action" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="">Semua</option>@foreach ($distinctActions as $action)<option value="{{ $action }}">{{ $action }}</option>@endforeach</select></div>
            <div class="flex gap-2"><button onclick="previewReport('{{ $type }}')" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-200"><i class="fa-solid fa-eye mr-1"></i>Preview</button><button onclick="exportReport('{{ $type }}', 'csv')" class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fa-solid fa-download mr-1"></i>CSV</button></div>
        </div>
        <div id="{{ $prefix }}_preview" class="hidden max-h-64 overflow-y-auto overflow-x-auto rounded-xl border border-gray-200"><table class="w-full text-xs"><thead><tr class="sticky top-0 bg-gray-50 font-semibold text-gray-500"><th class="px-3 py-2 text-left">Timestamp</th><th class="px-3 py-2 text-left">Actor</th><th class="px-3 py-2 text-left">Action</th><th class="px-3 py-2 text-left">Target</th></tr></thead><tbody id="{{ $prefix }}_preview_body" class="divide-y divide-gray-100"></tbody></table></div>
    </div>
</div>
