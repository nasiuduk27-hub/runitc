@extends('layouts.app')

@section('title', 'RUN-ITC | Audit Log Koperasi')

@section('content')
@php
    $actionLabel = function (string $action): string {
        $labels = [
            'cooperative.member.savings_updated' => 'Perbarui Simpanan Wajib',
            'cooperative.member.created' => 'Tambah Anggota',
            'cooperative.member.synced' => 'Sinkronkan Anggota',
            'cooperative.savings_withdrawal.submitted' => 'Ajukan Penarikan Simpanan',
            'cooperative.savings_withdrawal.approved' => 'Setujui Penarikan Simpanan',
            'cooperative.savings_withdrawal.rejected' => 'Tolak Penarikan Simpanan',
            'cooperative.savings_withdrawal.cancelled' => 'Batalkan Penarikan Simpanan',
            'cooperative.bank_transaction.deleted' => 'Hapus Transaksi Bank',
        ];

        if (isset($labels[$action])) {
            return $labels[$action];
        }

        foreach ([
            'cooperative.loan_application.' => 'Pengajuan Pinjaman',
            'cooperative.loan_payment.' => 'Pembayaran Angsuran',
            'cooperative.loan_skip.' => 'Refinancing',
        ] as $prefix => $label) {
            if (str_starts_with($action, $prefix)) {
                $status = str_replace('_', ' ', str_replace($prefix, '', $action));
                return $label.($status !== '' ? ': '.ucfirst($status) : '');
            }
        }

        return 'Aktivitas Koperasi';
    };

    $auditSummary = function (object $log) use ($actionLabel): string {
        $meta = json_decode((string) ($log->metadata_json ?? ''), true) ?: [];
        $money = fn ($value): string => 'Rp '.number_format((int) $value, 0, ',', '.');

        return match ($log->action) {
            'cooperative.member.savings_updated' => 'Perbarui simpanan wajib bulanan dari '.$money($meta['old_swajib'] ?? 0).' menjadi '.$money($meta['new_swajib'] ?? 0),
            'cooperative.member.created' => 'Tambah anggota koperasi baru'.(! empty($meta['icuno']) ? ' ('.$meta['icuno'].')' : ''),
            'cooperative.member.synced' => 'Sinkronkan akun dengan anggota koperasi',
            default => $actionLabel((string) $log->action),
        };
    };
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-primary">Koperasi</p>
            <h1 class="text-2xl font-bold text-gray-900">Audit Log Koperasi</h1>
            <p class="mt-0.5 text-sm text-gray-500">Riwayat perubahan dan aktivitas pada modul simpan pinjam.</p>
        </div>
        <a href="{{ route('cooperative.dashboard') }}" class="self-start rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-600 shadow-sm hover:border-brand-primary/40 hover:text-brand-primary">Kembali ke dashboard</a>
    </div>

    <form method="GET" class="grid grid-cols-1 items-end gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="mb-1 block text-xs font-semibold text-gray-600">Cari</label>
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Action / target / aktor..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-gray-600">Action</label>
            <select name="action" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">Semua aktivitas</option>
                @foreach ($distinctActions as $action)
                    <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $actionLabel($action) }}</option>
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
            <a href="{{ route('cooperative.audit-log.index') }}" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-200">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-sm">
                <thead class="bg-gray-50 text-left text-gray-600"><tr>
                    <th class="px-4 py-3 font-semibold">Waktu</th><th class="px-4 py-3 font-semibold">Aktivitas</th>
                    <th class="px-4 py-3 font-semibold">Action</th><th class="px-4 py-3 font-semibold">Detail</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-700">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-700"><span class="font-semibold">{{ $log->account_nm ?: 'System / Unknown' }}</span><span class="block text-xs text-gray-500">| {{ $auditSummary($log) }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">{{ $actionLabel($log->action) }}</span></td>
                            <td class="px-4 py-3">@if ($log->metadata_json)<button type="button" onclick='showAuditDetail(@json($log))' class="text-xs font-semibold text-brand-primary hover:underline">Lihat Detail</button>@else<span class="text-xs text-gray-400">-</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-gray-400">Tidak ada data audit koperasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-4 py-3">{{ $logs->links() }}</div>
    </div>
</div>

<div id="auditDetail" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" onclick="if(event.target===this)closeAuditDetail()">
    <div class="max-h-[90vh] w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-100 p-5"><h2 class="font-bold text-gray-900">Detail Audit Log</h2><button type="button" onclick="closeAuditDetail()" class="text-2xl leading-none text-gray-400">&times;</button></div>
        <pre id="auditDetailContent" class="max-h-[calc(90vh-80px)] overflow-auto whitespace-pre-wrap p-5 text-xs text-gray-700"></pre>
    </div>
</div>
<script>
const actionAliases = {
    'cooperative.member.savings_updated': 'Perbarui Simpanan Wajib',
    'cooperative.member.created': 'Tambah Anggota',
    'cooperative.member.synced': 'Sinkronkan Anggota',
    'cooperative.savings_withdrawal.submitted': 'Ajukan Penarikan Simpanan',
    'cooperative.savings_withdrawal.approved': 'Setujui Penarikan Simpanan',
    'cooperative.savings_withdrawal.rejected': 'Tolak Penarikan Simpanan',
    'cooperative.savings_withdrawal.cancelled': 'Batalkan Penarikan Simpanan',
    'cooperative.bank_transaction.deleted': 'Hapus Transaksi Bank'
};
function displayAction(action) {
    if (actionAliases[action]) return actionAliases[action];
    for (const [prefix, label] of [['cooperative.loan_application.', 'Pengajuan Pinjaman'], ['cooperative.loan_payment.', 'Pembayaran Angsuran'], ['cooperative.loan_skip.', 'Refinancing']]) {
        if (action.startsWith(prefix)) return label + ': ' + action.slice(prefix.length).replaceAll('_', ' ');
    }
    return 'Aktivitas Koperasi';
}
function showAuditDetail(log) {
    let metadata = log.metadata_json;
    try { metadata = JSON.stringify(JSON.parse(metadata), null, 2); } catch (e) {}
    document.getElementById('auditDetailContent').textContent = JSON.stringify({ waktu: log.created_at, aktor: log.account_nm || 'Unknown', aktivitas: displayAction(log.action), metadata }, null, 2);
    document.getElementById('auditDetail').classList.remove('hidden');
    document.getElementById('auditDetail').classList.add('flex');
}
function closeAuditDetail() { document.getElementById('auditDetail').classList.add('hidden'); document.getElementById('auditDetail').classList.remove('flex'); }
</script>
@endsection
