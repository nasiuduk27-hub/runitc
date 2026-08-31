@extends('layouts.app')

@section('title', 'RUN-ITC | Test Admin')

@section('content')
@php
    $testAdmins = $testAdmins ?? [];
    $supervisors = $supervisors ?? [];
    $page = (int) ($page ?? 1);
    $totalPages = (int) ($total_pages ?? 0);
    $totalRows = (int) ($total_rows ?? 0);
    $buildMonitoringUrl = function (array $row, ?array $batch = null) {
        if (empty($row['testdt']) || empty($row['admin_no'])) return '#';
        $params = [
            'date' => date('Y-m-d', strtotime($row['testdt'])),
            'admin' => $row['admin_no'],
            'monitoring_mode' => ((int) ($row['conn_type'] ?? 1) === 2) ? 'hybrid' : 'online',
        ];
        if ($batch) {
            $params['batch_qty'] = (int) ($batch['authorize_amt'] ?? 0);
            $params['batch_no'] = (string) ($batch['batch_no'] ?? '');
        }
        return route('cbt-ops.test-watching.monitoring', $params);
    };
@endphp

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single { height: 42px !important; border-color: #E5E7EB !important; border-radius: 0.75rem !important; background-color: #FFFFFF !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; padding-left: 12px !important; color: #111827 !important; font-size: 0.875rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
</style>

<div class="space-y-5">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h1 class="text-2xl font-black text-gray-900">{{ ! empty($isAssignedOnlyView) ? 'Penugasan Saya' : 'Distribusi Jadwal Tes' }}</h1>
        <p class="mt-1 text-sm text-gray-500">Kelola distribusi admin tes ke TAD/SPV dan akses monitoring CBT.</p>
    </div>

    <form method="GET" action="{{ route('cbt-ops.test-admin.index') }}" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-3 md:grid-cols-5">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Admin No / Klien" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            <input type="date" name="date" value="{{ $filterDate ?? '' }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm" title="Filter tanggal tes">
            <select name="dist_status" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <option value="">Semua Status</option>
                <option value="0" @selected(($distStatus ?? '') === '0')>In Progress</option>
                <option value="1" @selected(($distStatus ?? '') === '1')>Completed</option>
            </select>
            @if (empty($isAssignedOnlyView))
                <select name="spv_id" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Semua Pengawas</option>
                    @foreach ($supervisors as $spv)
                        <option value="{{ $spv['rec_id'] }}" @selected((string) ($filterSpv ?? '') === (string) $spv['rec_id'])>{{ $spv['spv_name'] }}{{ ! empty($spv['account_id']) ? ' - '.$spv['account_id'] : '' }}</option>
                    @endforeach
                </select>
            @endif
            <select name="limit" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) ($limit ?? 10) === $size)>{{ $size }} baris</option>
                @endforeach
            </select>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black uppercase text-white hover:bg-indigo-700">Terapkan Filter</button>
            <a href="{{ route('cbt-ops.test-admin.index') }}" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-black uppercase text-gray-600 hover:bg-gray-200">Reset</a>
        </div>
    </form>

    @if ($db_error ?? null)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $db_error }}</div>
    @endif
    @if (session('cbt_ops_test_admin_success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('cbt_ops_test_admin_success') }}</div>
    @endif
    @if (session('cbt_ops_test_admin_error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('cbt_ops_test_admin_error') }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black uppercase text-indigo-700">Total: {{ number_format($totalRows) }} Admin</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1120px] text-left text-xs">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nomor Admin</th>
                        <th class="px-4 py-3">Klien / Jadwal</th>
                        <th class="px-4 py-3 text-center">Peserta</th>
                        <th class="px-4 py-3 text-center">Distribusi</th>
                        <th class="px-4 py-3">Batch / Pengawas</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($testAdmins as $row)
                        @php
                            $adminId = (int) ($row['rec_id'] ?? 0);
                            $totalTakers = (int) ($row['total_takers'] ?? 0);
                            $assigned = (int) ($row['assigned_takers'] ?? 0);
                            $finished = (int) ($row['finished_takers'] ?? 0);
                            $batches = $row['batches'] ?? [];
                            $remainingQuota = max(0, $totalTakers - $assigned);
                        @endphp
                        <tr class="align-top hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-black text-gray-900">{{ $row['admin_no'] ?? '-' }}</p>
                                <p class="mt-1 text-[11px] font-semibold text-gray-400">{{ ((int) ($row['conn_type'] ?? 1) === 2) ? 'Hybrid' : 'Online' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold text-gray-800">{{ $row['client_nm'] ?? '-' }}</p>
                                <p class="mt-1 text-[11px] text-gray-500">{{ ! empty($row['testdt']) ? \Carbon\Carbon::parse($row['testdt'])->format('d M Y H:i') : '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <p class="font-black text-gray-900">{{ number_format($totalTakers) }}</p>
                                <p class="text-[11px] text-gray-400">Selesai {{ number_format($finished) }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <p class="font-black {{ $assigned >= $totalTakers && $totalTakers > 0 ? 'text-green-700' : 'text-amber-700' }}">{{ number_format($assigned) }} / {{ number_format($totalTakers) }}</p>
                                <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase {{ $assigned >= $totalTakers && $totalTakers > 0 ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $assigned >= $totalTakers && $totalTakers > 0 ? 'Completed' : 'In Progress' }}
                                </span>
                                <p class="text-[11px] text-gray-400">Issue {{ number_format((int) ($row['total_issues'] ?? 0)) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="space-y-2">
                                    @forelse ($batches as $batch)
                                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="font-black text-gray-800">{{ $batch['spv_name'] ?? '-' }}</p>
                                                    <p class="text-[11px] text-gray-500">Batch {{ $batch['batch_no'] ?? '-' }} · Kuota {{ number_format((int) ($batch['authorize_amt'] ?? 0)) }}</p>
                                                </div>
                                                <div class="flex flex-col items-end gap-1">
                                                    <a href="{{ $buildMonitoringUrl($row, $batch) }}" target="_blank" rel="noopener noreferrer" title="Monitoring" aria-label="Monitoring" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white hover:bg-blue-700"><i class="fas fa-desktop"></i></a>
                                                    @if (! empty($canManageDistribution))
                                                        <button type="button" onclick="openEditBatchModal({{ (int) $batch['rec_id'] }}, {{ (int) $batch['spv_recid'] }}, {{ (int) $batch['authorize_amt'] }}, {{ (int) $batch['authorize_amt'] + $remainingQuota }})" title="Edit / Ganti" aria-label="Edit / Ganti" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100"><i class="fas fa-edit"></i></button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <span class="text-gray-400">Belum ada batch.</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ $buildMonitoringUrl($row) }}" target="_blank" rel="noopener noreferrer" title="Monitoring" aria-label="Monitoring" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100"><i class="fas fa-desktop"></i></a>
                                    @if (! empty($canManageDistribution))
                                        @if ($totalTakers > 0 && $remainingQuota > 0)
                                            <button type="button" onclick="openBatchModal({{ $adminId }}, {{ $remainingQuota }})" title="Bagikan" aria-label="Bagikan" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"><i class="fas fa-share-alt"></i></button>
                                        @endif
                                        <form method="POST" action="{{ route('cbt-ops.test-admin.index') }}" onsubmit="return confirm('Reset distribusi admin ini?')">
                                            @csrf
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="admin_id" value="{{ $adminId }}">
                                            <button type="submit" title="Reset distribusi" aria-label="Reset distribusi" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700 hover:bg-red-100"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-16 text-center text-gray-400">Tidak ada jadwal tes ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($totalPages > 1)
            <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                <span>Halaman {{ $page }} dari {{ $totalPages }}</span>
                <div class="flex gap-2">
                    @if ($page > 1)
                        <a href="{{ $buildPageUrl($page - 1) }}" class="rounded-lg border px-3 py-2 hover:bg-gray-50">Sebelumnya</a>
                    @endif
                    @if ($page < $totalPages)
                        <a href="{{ $buildPageUrl($page + 1) }}" class="rounded-lg border px-3 py-2 hover:bg-gray-50">Berikutnya</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@if (! empty($canManageDistribution))
    <div id="batchModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md overflow-hidden rounded-2xl border-t-4 border-indigo-600 bg-white shadow-2xl">
            <form method="POST" action="{{ route('cbt-ops.test-admin.index') }}" class="space-y-5 p-6" id="batchModalForm">
                @csrf
                <input type="hidden" name="action" value="assign_batch">
                <input type="hidden" name="admin_id" id="modal_admin_id">
                <h3 class="border-b border-gray-100 pb-3 text-lg font-black text-gray-800"><i class="fas fa-share-alt mr-2 text-indigo-600"></i>Bagikan Kuota Peserta</h3>
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-gray-500">Pilih Pengawas</span>
                    <select name="spv_recid" id="spv_select" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">-- Pilih SPV --</option>
                        @foreach ($supervisors as $spv)
                            <option value="{{ $spv['rec_id'] }}">{{ $spv['spv_name'] }}{{ ! empty($spv['spv_alias']) ? ' ('.$spv['spv_alias'].')' : '' }}{{ ! empty($spv['account_id']) ? ' - '.$spv['account_id'] : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-gray-500">Jumlah Peserta</span>
                    <input type="number" name="amount" id="amount_input" min="1" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                </label>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <button type="button" onclick="closeBatchModal()" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-black uppercase text-gray-600 hover:bg-gray-200">Batal</button>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-black uppercase text-white hover:bg-indigo-700">Proses</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editBatchModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md overflow-hidden rounded-2xl border-t-4 border-blue-600 bg-white shadow-2xl">
            <form method="POST" action="{{ route('cbt-ops.test-admin.index') }}" class="space-y-5 p-6" id="editBatchModalForm">
                @csrf
                <input type="hidden" name="action" value="update_batch">
                <input type="hidden" name="batch_id" id="edit_modal_batch_id">
                <h3 class="border-b border-gray-100 pb-3 text-lg font-black text-gray-800"><i class="fas fa-edit mr-2 text-blue-600"></i>Edit Pengawas & Kuota</h3>
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-gray-500">Pilih Pengawas Baru</span>
                    <select id="edit_spv_select" name="spv_recid" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">-- Pilih SPV --</option>
                        @foreach ($supervisors as $spv)
                            <option value="{{ $spv['rec_id'] }}">{{ $spv['spv_name'] }}{{ ! empty($spv['account_id']) ? ' - '.$spv['account_id'] : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-gray-500">Jumlah Kuota</span>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="number" name="amount" id="edit_amount_input" min="0" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <span class="whitespace-nowrap rounded-xl bg-gray-100 px-3 py-2 text-xs font-bold text-gray-500">Max: <span id="edit_max_label" class="text-gray-900"></span></span>
                    </div>
                    <span class="mt-1 block text-[11px] font-semibold text-red-500">Set 0 untuk menghapus tugas pengawas ini.</span>
                </label>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <button type="button" onclick="closeEditBatchModal()" class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-black uppercase text-gray-600 hover:bg-gray-200">Batal</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-xs font-black uppercase text-white hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function hideModal(id) {
            const modal = document.getElementById(id);
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openBatchModal(id, max) {
            document.getElementById('modal_admin_id').value = id;
            const input = document.getElementById('amount_input');
            input.max = max;
            input.value = max;
            $('#spv_select').val('').trigger('change');
            showModal('batchModal');
        }

        function closeBatchModal() {
            hideModal('batchModal');
        }

        function openEditBatchModal(id, spv, currentAmount, maxAllowed) {
            document.getElementById('edit_modal_batch_id').value = id;
            $('#edit_spv_select').val(spv).trigger('change');
            const input = document.getElementById('edit_amount_input');
            input.value = currentAmount;
            input.max = maxAllowed;
            document.getElementById('edit_max_label').innerText = maxAllowed;
            showModal('editBatchModal');
        }

        function closeEditBatchModal() {
            hideModal('editBatchModal');
        }

        $(function () {
            $('#spv_select').select2({
                placeholder: '-- Pilih SPV --',
                allowClear: false,
                width: '100%',
                dropdownParent: $('#batchModal'),
            });

            $('#edit_spv_select').select2({
                placeholder: '-- Pilih SPV --',
                allowClear: false,
                width: '100%',
                dropdownParent: $('#editBatchModal'),
            });

            $('#batchModalForm').on('submit', function (event) {
                if (!$(this).find('[name="spv_recid"]').val()) {
                    event.preventDefault();
                    alert('Silakan pilih pengawas terlebih dahulu.');
                }
            });

            $('#editBatchModalForm').on('submit', function (event) {
                if (!$(this).find('[name="spv_recid"]').val()) {
                    event.preventDefault();
                    alert('Silakan pilih pengawas terlebih dahulu.');
                }
            });
        });
    </script>
@endif
@endsection
