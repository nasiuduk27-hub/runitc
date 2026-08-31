@extends('layouts.app')

@section('title', 'RUN-ITC | Audit Log')

@section('content')
@php
    // Closure, bukan fungsi global: deklarasi `function` di dalam Blade akan
    // fatal ("Cannot redeclare") kalau view ini dirender lebih dari sekali
    // dalam satu proses PHP.
    $auditActionClass = function (?string $action): string {
        $action = $action ?? '';
        if (str_starts_with($action, 'LOGIN_')) return $action === 'LOGIN_SUCCESS' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
        if (str_starts_with($action, 'USER_')) return 'bg-blue-100 text-blue-700';
        if (str_starts_with($action, 'ROLE_')) return 'bg-purple-100 text-purple-700';
        if (str_starts_with($action, 'MENU_')) return 'bg-amber-100 text-amber-700';
        if (str_starts_with($action, 'TEST_')) return 'bg-emerald-100 text-emerald-700';
        return 'bg-gray-100 text-gray-700';
    };

    $auditSummary = function (object $log): string {
        $meta = json_decode((string) ($log->metadata_json ?? ''), true) ?: [];
        $action = (string) ($log->action ?? '');
        if ($action === 'LOGIN_SUCCESS') return 'Login sukses: '.($meta['account_id'] ?? $log->account_nm ?? 'Unknown');
        if (str_starts_with($action, 'LOGIN_FAILED')) return 'Login gagal: '.($meta['account_id'] ?? 'Unknown');
        if ($action === 'LOGOUT') return 'Logout: '.($log->account_nm ?? 'Unknown');
        return '';
    };
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
        <p class="mt-1 text-sm text-gray-500">Riwayat aktivitas sistem.</p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <form method="GET" class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Cari</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Action / target / actor..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Action</label>
                <select name="action" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="">Semua</option>
                    @foreach ($distinctActions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Sampai Tanggal</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-brand-primaryHover"><i class="fa-solid fa-filter mr-1"></i>Filter</button>
                <a href="{{ url('/modules/admin/system_access/audit_log.php') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-200">Reset</a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Waktu</th>
                        <th class="px-4 py-3 font-semibold">Aktor</th>
                        <th class="px-4 py-3 font-semibold">Action</th>
                        <th class="px-4 py-3 font-semibold">Target</th>
                        <th class="px-4 py-3 font-semibold">IP</th>
                        <th class="px-4 py-3 font-semibold">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-700">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $log->account_nm ?: 'System / Unknown' }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $auditActionClass($log->action) }}">{{ $log->action }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-600">{{ $log->target_type ?: '-' }} @if($log->target_id)<span class="text-gray-400">#{{ $log->target_id }}</span>@endif</td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-500">{{ $log->ip_address ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @if ($auditSummary($log) !== '')<div class="mb-1 max-w-sm text-xs text-gray-700">{{ $auditSummary($log) }}</div>@endif
                                @if ($log->metadata_json)
                                    <button onclick='openDetailModal(@json($log))' class="text-xs font-semibold text-brand-primary hover:text-brand-primaryHover">Lihat Detail</button>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Tidak ada data audit log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalPages > 1)
            <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-4 py-3">
                <span class="text-xs text-gray-500">Total {{ $total }} record(s)</span>
                <div class="flex items-center gap-1">
                    @for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $i]) }}" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $i === $page ? 'bg-brand-primary text-white shadow-sm' : 'border border-gray-300 bg-white hover:bg-gray-100' }}">{{ $i }}</a>
                    @endfor
                </div>
            </div>
        @endif
    </div>
</div>

<div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" onclick="if(event.target===this)closeModal('detailModal')">
    <div class="max-h-[90vh] w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-100 bg-white p-6">
            <h2 class="text-lg font-bold text-gray-900">Detail Audit Log</h2>
            <button onclick="closeModal('detailModal')" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-xl leading-none text-gray-500 hover:bg-gray-200">&times;</button>
        </div>
        <div class="max-h-[calc(90vh-88px)] space-y-4 overflow-y-auto p-6" id="detailContent"></div>
    </div>
</div>

<script>
function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char])); }
function formatMetaValue(value) {
    if (Array.isArray(value)) return value.length ? '<ul class="list-disc pl-4 space-y-1">' + value.map(item => '<li>' + escapeHtml(typeof item === 'object' ? JSON.stringify(item) : item) + '</li>').join('') + '</ul>' : '-';
    if (value && typeof value === 'object') return '<div class="space-y-1">' + Object.entries(value).map(([k, v]) => '<div><span class="font-semibold">' + escapeHtml(k.replace(/_/g, ' ')) + ':</span> ' + formatMetaValue(v) + '</div>').join('') + '</div>';
    return escapeHtml(value || '-');
}
function openDetailModal(log) {
    let metaHtml = '';
    if (log.metadata_json) {
        try {
            const meta = JSON.parse(log.metadata_json);
            metaHtml = '<table class="w-full text-sm">' + Object.entries(meta).map(([k, v]) => '<tr><td class="px-3 py-1.5 font-semibold text-gray-600 capitalize align-top">' + escapeHtml(k.replace(/_/g, ' ')) + '</td><td class="px-3 py-1.5 text-gray-800">' + formatMetaValue(v) + '</td></tr>').join('') + '</table>';
        } catch (e) { metaHtml = '<pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-600">' + escapeHtml(log.metadata_json) + '</pre>'; }
    }
    document.getElementById('detailContent').innerHTML = '<div class="grid grid-cols-2 gap-4 text-sm"><div><span class="block text-xs font-semibold text-gray-500">Waktu</span><span>' + escapeHtml(log.created_at || '-') + '</span></div><div><span class="block text-xs font-semibold text-gray-500">Aktor</span><span>' + escapeHtml(log.account_nm || 'Unknown') + '</span></div><div><span class="block text-xs font-semibold text-gray-500">Action</span><span>' + escapeHtml(log.action || '-') + '</span></div><div><span class="block text-xs font-semibold text-gray-500">Target</span><span>' + escapeHtml(log.target_type || '-') + (log.target_id ? ' #' + escapeHtml(log.target_id) : '') + '</span></div><div><span class="block text-xs font-semibold text-gray-500">IP Address</span><span class="font-mono">' + escapeHtml(log.ip_address || '-') + '</span></div><div><span class="block text-xs font-semibold text-gray-500">User Agent</span><span class="block max-w-[200px] truncate text-xs" title="' + escapeHtml(log.user_agent || '') + '">' + escapeHtml(log.user_agent || '-') + '</span></div></div>' + (metaHtml ? '<div class="border-t border-gray-100 pt-4"><span class="mb-2 block text-xs font-semibold text-gray-500">Metadata</span>' + metaHtml + '</div>' : '');
    const modal = document.getElementById('detailModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeModal(id) { const modal = document.getElementById(id); modal.classList.add('hidden'); modal.classList.remove('flex'); }
</script>
@endsection
