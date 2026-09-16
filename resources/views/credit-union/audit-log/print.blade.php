<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Log Credit Union | RUN-ITC</title>
    @php
        $actionLabel = function (string $action): string {
            $labels = [
                'cu.member.savings_updated' => 'Perbarui Simpanan Wajib',
                'cu.member.created' => 'Tambah Anggota',
                'cu.member.synced' => 'Sinkronkan Anggota',
                'cu.savings_withdrawal.submitted' => 'Ajukan Penarikan Simpanan',
                'cu.savings_withdrawal.approved' => 'Setujui Penarikan Simpanan',
                'cu.savings_withdrawal.rejected' => 'Tolak Penarikan Simpanan',
                'cu.savings_withdrawal.cancelled' => 'Batalkan Penarikan Simpanan',
                'cu.bank_transaction.deleted' => 'Hapus Transaksi Bank',
            ];

            if (isset($labels[$action])) {
                return $labels[$action];
            }

            foreach ([
                'cu.loan_application.' => 'Pengajuan Pinjaman',
                'cu.loan_payment.' => 'Pembayaran Angsuran',
                'cu.loan_skip.' => 'Refinancing',
            ] as $prefix => $label) {
                if (str_starts_with($action, $prefix)) {
                    $status = str_replace('_', ' ', str_replace($prefix, '', $action));
                    return $label.($status !== '' ? ': '.ucfirst($status) : '');
                }
            }

            return 'Aktivitas Credit Union';
        };
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; padding: 24px; font-size: 12px; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1D4ED8; padding-bottom: 12px; margin-bottom: 12px; }
        .header img { height: 40px; }
        .header h1 { font-size: 18px; margin: 0; color: #1D4ED8; }
        .header p { margin: 2px 0 0; color: #6b7280; }
        .meta { margin-bottom: 16px; color: #374151; font-size: 11px; line-height: 1.6; }
        .meta strong { color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #f3f4f6; text-align: left; padding: 8px 10px; border: 1px solid #d1d5db; font-size: 11px; }
        tbody td { padding: 7px 10px; border: 1px solid #e5e7eb; vertical-align: top; }
        tbody tr:nth-child(even) { background: #fafafa; }
        .nowrap { white-space: nowrap; }
        .muted { color: #6b7280; font-size: 10px; }
        .empty { text-align: center; color: #9ca3af; padding: 32px; }
        .actions { margin-bottom: 16px; }
        .btn { background: #1D4ED8; color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; }
        @media print {
            body { padding: 0; font-size: 10px; }
            .actions { display: none; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="actions">
        <button class="btn" type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </div>

    <div class="header">
        <div>
            <h1>Audit Log Credit Union</h1>
            <p>Riwayat perubahan dan aktivitas pada modul simpan pinjam.</p>
        </div>
        <img src="{{ asset('assets/images/RUNITC_LOGO.png') }}" alt="RUN-ITC">
    </div>

    <div class="meta">
        <div><strong>Activity:</strong> {{ $filters['action'] !== '' ? $actionLabel($filters['action']) : 'Semua aktivitas' }}</div>
        <div><strong>Pencarian:</strong> {{ $filters['search'] !== '' ? $filters['search'] : '-' }}</div>
        <div><strong>Periode:</strong> {{ $filters['date_from'] !== '' ? $filters['date_from'] : 'Awal' }} s/d {{ $filters['date_to'] !== '' ? $filters['date_to'] : 'Sekarang' }}</div>
        <div><strong>Total data:</strong> {{ $logs->count() }}</div>
        <div><strong>Dicetak:</strong> {{ $generatedAt->format('d M Y H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="nowrap">Waktu</th>
                <th>Aktor</th>
                <th>Aktivitas</th>
                <th>Action</th>
                <th>Metadata</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="nowrap">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</td>
                    <td>{{ $log->account_nm ?: 'System / Unknown' }}</td>
                    <td>{{ $actionLabel($log->action) }}</td>
                    <td>{{ $log->action }}</td>
                    <td class="muted">{{ $log->metadata_json ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Tidak ada data audit credit union.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
