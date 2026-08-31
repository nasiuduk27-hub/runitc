<div class="tab-content hidden" id="tab_operational">
    <div class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-gray-900">Operational Summary Report</h2>
        <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-3">
            <div><label class="mb-1 block text-xs font-semibold text-gray-600">Date</label><input type="date" id="op_date" value="{{ now()->toDateString() }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
            <div></div>
            <div><button onclick="exportReport('operational', 'html')" class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fa-solid fa-file-export mr-1"></i>View / Export HTML</button></div>
        </div>
    </div>
</div>
