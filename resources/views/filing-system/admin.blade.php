@extends('layouts.app')

@section('title', 'RUN-ITC | Filing System Admin')

@section('content')
@php
    $formatSize = function ($bytes): string {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        return $bytes.' bytes';
    };
    $statusColors = [
        'active' => 'bg-green-100 text-green-700',
        'archived' => 'bg-gray-100 text-gray-700',
        'trashed' => 'bg-orange-100 text-orange-700',
        'deleted' => 'bg-red-100 text-red-700',
        'blocked' => 'bg-red-800 text-white',
        'expired' => 'bg-yellow-100 text-yellow-700',
    ];
    $getStatusBadge = fn (string $status): string => '<span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase '.(string) ($statusColors[$status] ?? 'bg-gray-100 text-gray-700').'">'.$status.'</span>';
    $queryFor = fn (array $overrides = []) => array_merge(request()->query(), $overrides);
@endphp

<div class="py-8 md:py-12">
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col items-start justify-between gap-4 border-b-2 border-red-500 pb-4 md:flex-row md:items-center">
            <div>
                <h2 class="flex items-center gap-3 text-3xl font-extrabold tracking-tight text-gray-900">
                    <i class="fas fa-shield-alt text-red-600"></i>
                    Filing System Admin
                </h2>
                <p class="mt-1 text-xs font-bold uppercase tracking-widest text-red-500"><i class="fas fa-exclamation-triangle"></i> Super Administrator Area</p>
            </div>
            <div>
                <a href="{{ url('/filing-system') }}" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <i class="fas fa-door-open"></i> Keluar Admin
                </a>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1 block text-xs font-bold text-gray-700">Cari File</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500">
                </div>

                <div class="w-32">
                    <label class="mb-1 block text-xs font-bold text-gray-700">Status</label>
                    <select name="status" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500">
                        <option value="">Semua</option>
                        @foreach (['active', 'archived', 'trashed', 'deleted', 'blocked', 'expired'] as $s)
                            <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-32">
                    <label class="mb-1 block text-xs font-bold text-gray-700">Security</label>
                    <select name="security_level" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500">
                        <option value="">Semua</option>
                        @foreach (['normal', 'restricted', 'confidential'] as $s)
                            <option value="{{ $s }}" @selected($filters['security_level'] === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="rounded bg-gray-800 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-gray-900">
                    <i class="fas fa-filter"></i> Filter
                </button>
                @if (array_filter(array_diff_key($filters, ['page' => 1, 'limit' => 1])))
                    <a href="{{ url('/modules/cbt_ops/filing_system/admin.php') }}" class="rounded bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600 transition hover:bg-gray-200"><i class="fas fa-times"></i> Reset</a>
                @endif
            </form>
        </div>

        <div class="mb-4 px-2">
            <span class="text-sm font-bold text-gray-500">Total System Files: <span class="text-red-600">{{ $totalItems }}</span></span>
        </div>

        <div class="overflow-hidden rounded-xl border border-red-100 bg-white shadow-lg">
            <div class="min-h-[400px] overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-100 text-[10px] uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-black">ID / Code</th>
                            <th class="w-1/4 px-4 py-3 font-black">Display Name</th>
                            <th class="px-4 py-3 font-black">Owner</th>
                            <th class="px-4 py-3 text-center font-black">Status</th>
                            <th class="px-4 py-3 text-center font-black">Security</th>
                            <th class="px-4 py-3 text-right font-black">Size</th>
                            <th class="px-4 py-3 text-center font-black">Diagnostics</th>
                            <th class="w-28 whitespace-nowrap px-4 py-3 text-right font-black">Admin Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $f)
                            <tr class="transition-colors hover:bg-red-50/20 {{ $f['status'] === 'deleted' ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3 font-mono text-xs">
                                    #{{ $f['rec_id'] }}<br>
                                    <span class="text-[9px] text-gray-400">{{ $f['file_code'] }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="break-all font-bold text-gray-800">{{ $f['display_name'] }}</div>
                                    <div class="mt-1 font-mono text-[10px] text-gray-500" title="Path Relative">.../{{ basename($f['storage_path']) }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs font-bold text-gray-600">{{ $f['owner_name'] }}</td>
                                <td class="px-4 py-3 text-center">{!! $getStatusBadge($f['status']) !!}</td>
                                <td class="px-4 py-3 text-center text-[10px] font-bold uppercase text-gray-500">{{ $f['security_level'] }}</td>
                                <td class="px-4 py-3 text-right font-mono text-xs text-gray-500">{{ $formatSize($f['zip_size']) }}</td>
                                <td class="px-4 py-3 text-center" id="diag_cell_{{ $f['rec_id'] }}">
                                    <button onclick="runDiagnostic({{ $f['rec_id'] }})" class="rounded border border-gray-200 bg-gray-100 px-2 py-1 text-[10px] font-bold text-gray-600 transition hover:bg-gray-200">Check</button>
                                </td>
                                <td class="relative whitespace-nowrap px-4 py-3 text-right dropdown-container">
                                    <button onclick="toggleAdminDropdown({{ $f['rec_id'] }})" class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition hover:bg-gray-100">
                                        <i class="fas fa-cog"></i>
                                    </button>

                                    <div id="admin_dd_{{ $f['rec_id'] }}" class="absolute right-0 bottom-full z-50 mb-2 hidden w-48 divide-y divide-gray-100 overflow-hidden rounded-md bg-white shadow-2xl ring-1 ring-black/10">
                                        <div class="py-1">
                                            <a href="{{ $auditBaseUrl }}?filing_id={{ $f['rec_id'] }}" class="flex items-center px-4 py-2 text-xs text-gray-700 hover:bg-gray-50"><i class="fas fa-history w-5 text-gray-400"></i> View Audit</a>
                                            <button onclick="runDiagnostic({{ $f['rec_id'] }})" class="flex w-full items-center px-4 py-2 text-left text-xs text-blue-700 hover:bg-blue-50"><i class="fas fa-stethoscope w-5 text-blue-400"></i> Storage Recheck</button>
                                        </div>
                                        <div class="py-1">
                                            @if ($f['status'] !== 'blocked' && $f['status'] !== 'deleted')
                                                <button onclick="executeAdminAction({{ $f['rec_id'] }}, 'block')" class="flex w-full items-center px-4 py-2 text-left text-xs text-orange-700 hover:bg-orange-50"><i class="fas fa-lock w-5 text-orange-400"></i> Block File</button>
                                            @endif
                                            @if ($f['status'] === 'blocked')
                                                <button onclick="executeAdminAction({{ $f['rec_id'] }}, 'unblock')" class="flex w-full items-center px-4 py-2 text-left text-xs text-green-700 hover:bg-green-50"><i class="fas fa-unlock w-5 text-green-400"></i> Unblock File</button>
                                            @endif
                                            @if (in_array($f['status'], ['archived', 'trashed', 'expired', 'blocked'], true))
                                                <button onclick="executeAdminAction({{ $f['rec_id'] }}, 'restore')" class="flex w-full items-center px-4 py-2 text-left text-xs text-indigo-700 hover:bg-indigo-50"><i class="fas fa-undo w-5 text-indigo-400"></i> Force Restore</button>
                                            @endif
                                        </div>
                                        @if ($f['status'] !== 'deleted')
                                            <div class="bg-red-50 py-1">
                                                <button onclick="executePermanentDelete({{ $f['rec_id'] }})" class="flex w-full items-center px-4 py-2 text-left text-xs font-bold text-red-700 hover:bg-red-100"><i class="fas fa-dumpster-fire w-5 text-red-500"></i> Permanent Delete</button>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-10 text-center text-gray-400">Tidak ada file.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($totalPages > 1)
                <div class="flex items-center justify-between border-t border-gray-200 bg-gray-100 px-6 py-4">
                    <p class="text-xs font-bold text-gray-500">Halaman {{ $filters['page'] }} dari {{ $totalPages }}</p>
                    <div class="flex gap-1">
                        @if ($filters['page'] > 1)
                            <a href="{{ url('/modules/cbt_ops/filing_system/admin.php') }}?{{ http_build_query($queryFor(['page' => $filters['page'] - 1])) }}" class="rounded border border-gray-300 bg-white px-3 py-1 text-xs hover:bg-gray-50"><i class="fas fa-chevron-left"></i></a>
                        @endif
                        @if ($filters['page'] < $totalPages)
                            <a href="{{ url('/modules/cbt_ops/filing_system/admin.php') }}?{{ http_build_query($queryFor(['page' => $filters['page'] + 1])) }}" class="rounded border border-gray-300 bg-white px-3 py-1 text-xs hover:bg-gray-50"><i class="fas fa-chevron-right"></i></a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    const adminActionEndpoint = @json($adminActionUrl);
    const auditBaseEndpoint = @json($auditBaseUrl);

    function toggleAdminDropdown(id) {
        document.querySelectorAll('[id^="admin_dd_"]').forEach(el => {
            if (el.id !== 'admin_dd_' + id) el.classList.add('hidden');
        });
        document.getElementById('admin_dd_' + id).classList.toggle('hidden');
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown-container')) {
            document.querySelectorAll('[id^="admin_dd_"]').forEach(el => el.classList.add('hidden'));
        }
    });

    function runDiagnostic(id) {
        const cell = document.getElementById('diag_cell_' + id);
        cell.innerHTML = '<i class="fas fa-circle-notch fa-spin text-gray-400"></i>';

        const fd = new FormData();
        fd.append('action', 'diagnose');
        fd.append('filing_id', id);

        fetch(adminActionEndpoint, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    let color = res.data.status === 'available' ? 'green' : (res.data.status === 'missing' ? 'red' : 'orange');
                    cell.innerHTML = `<span class="rounded bg-${color}-100 px-2 py-1 text-[9px] font-bold text-${color}-700 cursor-help" title="${res.data.message}">${res.data.status.toUpperCase()}</span>`;
                } else {
                    cell.innerHTML = `<span class="rounded bg-red-100 px-2 py-1 text-[9px] font-bold text-red-700">ERROR</span>`;
                }
            })
            .catch(() => {
                cell.innerHTML = `<span class="rounded bg-gray-200 px-2 py-1 text-[9px] font-bold text-gray-600">FAIL</span>`;
            });
    }

    function executeAdminAction(id, action) {
        const confirmMsg = {
            'block': 'Blokir file ini? User tidak akan bisa mengaksesnya.',
            'unblock': 'Buka blokir file ini?',
            'restore': 'Paksa restore file ini ke status Active?'
        };

        if (!confirm(confirmMsg[action])) return;

        const fd = new FormData();
        fd.append('action', action);
        fd.append('filing_id', id);

        fetch(adminActionEndpoint, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    location.reload();
                } else {
                    alert('Gagal: ' + res.message);
                }
            })
            .catch(() => alert('Koneksi terputus.'));
    }

    function executePermanentDelete(id) {
        const input = prompt('PERINGATAN KRITIS: Anda akan menghapus file fisik di storage secara permanen. Record database akan di-mark "deleted".\n\nKetik "DELETE" (tanpa kutip) untuk konfirmasi:');

        if (input !== 'DELETE') {
            if (input !== null) alert('Konfirmasi dibatalkan. Teks tidak sesuai.');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'permanent_delete');
        fd.append('filing_id', id);
        fd.append('confirm_text', 'DELETE');

        fetch(adminActionEndpoint, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) location.reload();
            })
            .catch(() => alert('Koneksi terputus.'));
    }
</script>
@endsection
